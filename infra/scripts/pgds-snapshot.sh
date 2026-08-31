#!/usr/bin/env bash
#
# Daily instance snapshot with 4-rolling retention (Proposal 02 §6.1).
#
# §9.2 keeps snapshots OUT of Terraform on purpose: a snapshot created on a schedule
# is drift by definition, so every `terraform plan` would propose destroying and
# recreating it. This script owns them instead.
#
# The snapshot IS the media backup (§6.1) — uploads are NOT mirrored to S3, because
# that would duplicate 25-40GB for no additional durability. Only the database is
# dumped separately (pgds-db-backup.sh), because a database needs point-in-time
# granularity that a daily block snapshot cannot give.
#
# EC2 vs Lightsail: on EC2 a "snapshot" is an AMI plus its backing EBS snapshot, and
# BOTH must be pruned — deregistering the AMI alone orphans the snapshot, which keeps
# billing at $0.05/GB-month invisibly. That trap is the main reason this script exists
# rather than a one-line cron.
#
# Install (as root on the origin):
#   install -m 0750 pgds-snapshot.sh /usr/local/sbin/
#   # Needs ec2:CreateImage/DescribeImages/DeregisterImage/DeleteSnapshot +
#   # ec2:CreateTags. The backup IAM user does NOT have these (it is S3 PutObject only,
#   # §6.2), so either attach a separate policy or run it from the admin workstation.
#   # Prefer the workstation: a key that can delete snapshots is exactly what should
#   # not sit on an internet-facing box.
#   41 2 * * * /usr/local/sbin/pgds-snapshot.sh

set -euo pipefail

REGION="${PGDS_REGION:-ap-southeast-1}"
KEEP="${PGDS_SNAPSHOT_KEEP:-4}"
NAME_PREFIX="${PGDS_SNAPSHOT_PREFIX:-pgds-auto}"
LOG_TAG=pgds-snapshot

log() { logger -t "$LOG_TAG" -- "$*" 2>/dev/null || true; echo "$(date -u +%FT%TZ) $*"; }
die() { log "ERROR: $*"; exit 1; }

# Resolve the instance from IMDSv2 when running on the box, or take it from the env
# when running from a workstation.
INSTANCE_ID="${PGDS_INSTANCE_ID:-}"
if [ -z "$INSTANCE_ID" ]; then
  TOKEN=$(curl -sf -X PUT "http://169.254.169.254/latest/api/token" \
    -H "X-aws-ec2-metadata-token-ttl-seconds: 60" 2>/dev/null) \
    || die "not on EC2 and PGDS_INSTANCE_ID is unset"
  INSTANCE_ID=$(curl -sf -H "X-aws-ec2-metadata-token: $TOKEN" \
    "http://169.254.169.254/latest/meta-data/instance-id" 2>/dev/null) \
    || die "could not read instance-id from IMDS"
fi

STAMP="$(date -u +%Y%m%d-%H%M%S)"
AMI_NAME="${NAME_PREFIX}-${STAMP}"

log "creating AMI $AMI_NAME from $INSTANCE_ID"

# --no-reboot: rebooting to snapshot would take the site down daily. The tradeoff is
# that the filesystem is captured live, so the database inside the image may be
# mid-write. That is acceptable BECAUSE pgds-db-backup.sh produces a consistent
# --single-transaction dump twice a day; restore procedure is image first, then replay
# the newest dump over it (RUNBOOK §4).
# NOTE: the description below is deliberately ASCII-only. CreateImage rejects the
# section sign with "InvalidParameterValue: Character sets beyond ASCII are not
# supported", so it reads "Proposal 02 section 6.1" rather than using the § glyph.
AMI_ID=$(aws ec2 create-image \
  --region "$REGION" \
  --instance-id "$INSTANCE_ID" \
  --name "$AMI_NAME" \
  --description "pgds automatic daily snapshot (Proposal 02 section 6.1)" \
  --no-reboot \
  --tag-specifications "ResourceType=image,Tags=[{Key=Name,Value=$AMI_NAME},{Key=Project,Value=pgds},{Key=CreatedBy,Value=pgds-snapshot.sh}]" \
  --query ImageId --output text) || die "create-image failed"

log "created $AMI_ID"

# Belt-and-braces re-tag. --tag-specifications above should already have done this, but
# pruning selects on the CreatedBy tag, so an image that somehow ends up UNTAGGED is
# invisible to the pruner and bills forever. This actually happened during development
# when the script died between create-image and the tagging step, leaving a 60 GB
# snapshot nothing would ever collect. create-tags is idempotent, so re-applying costs
# nothing and closes the window.
aws ec2 create-tags --region "$REGION" --resources "$AMI_ID" \
  --tags "Key=Name,Value=$AMI_NAME" "Key=Project,Value=pgds" \
         "Key=CreatedBy,Value=pgds-snapshot.sh" >/dev/null 2>&1 \
  || log "WARNING: could not tag $AMI_ID — it will NOT be auto-pruned; tag it by hand"

# Tag the backing EBS snapshots too, so an orphan is identifiable later even if the
# AMI is gone. create-image does not propagate image tags to them.
sleep 10
for snap in $(aws ec2 describe-images --region "$REGION" --image-ids "$AMI_ID" \
    --query 'Images[0].BlockDeviceMappings[].Ebs.SnapshotId' --output text 2>/dev/null); do
  [ -n "$snap" ] && [ "$snap" != "None" ] && \
    aws ec2 create-tags --region "$REGION" --resources "$snap" \
      --tags "Key=Name,Value=$AMI_NAME" "Key=Project,Value=pgds" \
             "Key=CreatedBy,Value=pgds-snapshot.sh" >/dev/null 2>&1 || true
done

# --- Prune to KEEP most recent ------------------------------------------------
# Only images this script created are considered, matched on the CreatedBy tag rather
# than the name prefix, so a hand-made or restore-test image is never deleted by the
# cron job.
mapfile -t OLD < <(aws ec2 describe-images --region "$REGION" --owners self \
  --filters "Name=tag:CreatedBy,Values=pgds-snapshot.sh" \
  --query "reverse(sort_by(Images, &CreationDate))[${KEEP}:].ImageId" \
  --output text 2>/dev/null | tr '\t' '\n' | grep -v '^$' || true)

if [ "${#OLD[@]}" -eq 0 ]; then
  log "nothing to prune (keeping $KEEP)"
else
  for ami in "${OLD[@]}"; do
    # Collect snapshot IDs BEFORE deregistering: once the AMI is gone the mapping is
    # unrecoverable and the snapshots bill forever.
    mapfile -t SNAPS < <(aws ec2 describe-images --region "$REGION" --image-ids "$ami" \
      --query 'Images[0].BlockDeviceMappings[].Ebs.SnapshotId' --output text 2>/dev/null \
      | tr '\t' '\n' | grep -v '^$\|None' || true)

    if aws ec2 deregister-image --region "$REGION" --image-id "$ami" 2>/dev/null; then
      log "deregistered $ami"
      for s in "${SNAPS[@]}"; do
        aws ec2 delete-snapshot --region "$REGION" --snapshot-id "$s" 2>/dev/null \
          && log "  deleted snapshot $s" \
          || log "  WARNING: could not delete snapshot $s — check for an orphan"
      done
    else
      log "WARNING: could not deregister $ami"
    fi
  done
fi

TOTAL=$(aws ec2 describe-images --region "$REGION" --owners self \
  --filters "Name=tag:CreatedBy,Values=pgds-snapshot.sh" \
  --query 'length(Images)' --output text 2>/dev/null || echo '?')

# --- Leak detector -----------------------------------------------------------
# Every self-owned EBS snapshot should belong to a live AMI. A count mismatch means
# something is billing at $0.05/GB-month that nothing will ever collect: an untagged
# AMI (invisible to the pruner above) or a snapshot whose AMI was deregistered without
# it. At 60 GB per snapshot that is $3/month each, silently, against a $100 cap.
ALL_AMIS=$(aws ec2 describe-images --region "$REGION" --owners self \
  --query 'length(Images)' --output text 2>/dev/null || echo 0)
ALL_SNAPS=$(aws ec2 describe-snapshots --region "$REGION" --owner-ids self \
  --query 'length(Snapshots)' --output text 2>/dev/null || echo 0)

if [ "$ALL_AMIS" != "$ALL_SNAPS" ]; then
  log "WARNING: ${ALL_AMIS} self-owned AMI(s) but ${ALL_SNAPS} snapshot(s) — possible leak."
  log "  Untagged AMIs (will NOT be auto-pruned):"
  aws ec2 describe-images --region "$REGION" --owners self \
    --query "Images[?!not_null(Tags[?Key=='CreatedBy'].Value)].[ImageId,Name]" \
    --output text 2>/dev/null | while read -r line; do [ -n "$line" ] && log "    $line"; done
  log "  Investigate before the next run; see RUNBOOK section 7."
fi

log "done — $TOTAL managed snapshot(s) retained; ${ALL_AMIS} AMI(s) / ${ALL_SNAPS} snapshot(s) total"
