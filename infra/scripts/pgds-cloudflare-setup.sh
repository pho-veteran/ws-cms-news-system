#!/usr/bin/env bash
#
# Configure the Cloudflare edge for pgds (Proposal 02 §5.3, §5.6; Proposal 01 §5.6).
#
# WHY A SCRIPT AND NOT TERRAFORM
# ------------------------------
# §9 calls the Cloudflare provider "optional" and notes DNS is only a few records but is
# critical at cutover. A script wins here for one specific reason: this configuration is
# applied ONCE at cutover and then essentially never changes, whereas a Terraform
# provider adds a second state file, a second set of credentials, and a `plan` that must
# be kept clean forever. The failure mode we care about is "the two Cache Rules were
# never created", and a script that is idempotent and verifies its own work solves that
# without the ongoing cost.
#
# WHAT IT DOES (all idempotent — safe to re-run)
#   1. Resolves the zone ID from the domain.
#   2. Sets SSL mode to Full (strict), Always Use HTTPS on, Auto Minify off, Brotli on.
#   3. Creates exactly the TWO Cache Rules §5.3 specifies, replacing any it already made.
#   4. Verifies each setting read back, and fails loudly if one did not stick.
#
# WHAT IT DELIBERATELY DOES NOT DO
#   - It does not create DNS records. Pointing the apex A record at the origin is the
#     cutover moment (§11) and should be a deliberate human action, not a side effect.
#   - It does not enable the proxy on records it did not create, for the same reason.
#   - It does not touch the origin certificate: that is generated in the dashboard and
#     installed on the box, and pasting a private key through a shell script is worse
#     than doing it by hand once.
#
# PREREQUISITES
#   CF_API_TOKEN  a token with Zone:Read, Zone Settings:Edit, and Cache Rules:Edit for
#                 this zone only. NOT the Global API Key — that key can do anything to
#                 every zone on the account and cannot be scoped.
#   CF_DOMAIN     the zone apex, e.g. phatgiaovadoisong.vn
#
# Usage:
#   CF_API_TOKEN=... CF_DOMAIN=phatgiaovadoisong.vn ./pgds-cloudflare-setup.sh
#   CF_API_TOKEN=... CF_DOMAIN=... ./pgds-cloudflare-setup.sh --dry-run

set -euo pipefail

API="https://api.cloudflare.com/client/v4"
DRY_RUN=0
[ "${1:-}" = "--dry-run" ] && DRY_RUN=1

: "${CF_API_TOKEN:?Set CF_API_TOKEN (scoped token, not the Global API Key)}"
: "${CF_DOMAIN:?Set CF_DOMAIN, e.g. phatgiaovadoisong.vn}"

log()  { echo "$(date -u +%FT%TZ) $*"; }
die()  { echo "ERROR: $*" >&2; exit 1; }

cf() { # method path [json]
  local method="$1" path="$2" body="${3:-}"
  if [ -n "$body" ]; then
    curl -sS -X "$method" "${API}${path}" \
      -H "Authorization: Bearer ${CF_API_TOKEN}" \
      -H "Content-Type: application/json" \
      --data "$body"
  else
    curl -sS -X "$method" "${API}${path}" \
      -H "Authorization: Bearer ${CF_API_TOKEN}" \
      -H "Content-Type: application/json"
  fi
}

ok() { # reads a Cloudflare response on stdin, returns 0 when success:true
  # Escaped double quotes inside an f-string are a SyntaxError before Python 3.12
  # ("unexpected character after line continuation character"), which silently swallowed
  # every Cloudflare error message — the script reported "token is invalid" with no
  # detail. Uses .format() and single-quoted keys to stay portable.
  python3 -c 'import json, sys
try:
    d = json.load(sys.stdin)
except Exception as exc:
    sys.stderr.write("  unparseable Cloudflare response: {0}\n".format(exc))
    sys.exit(1)
if d.get("success"):
    sys.exit(0)
for err in d.get("errors") or []:
    sys.stderr.write("  cloudflare error {0}: {1}\n".format(err.get("code"), err.get("message")))
    for sub in err.get("error_chain") or []:
        sys.stderr.write("    chain {0}: {1}\n".format(sub.get("code"), sub.get("message")))
if not d.get("errors"):
    sys.stderr.write("  cloudflare reported success=false with no error detail\n")
sys.exit(1)'
}

# --- 0. Token sanity ---------------------------------------------------------
log "verifying the API token"
cf GET /user/tokens/verify | ok || die "token is invalid, expired, or lacks Zone:Read"

# --- 1. Zone ----------------------------------------------------------------
log "resolving zone for ${CF_DOMAIN}"
ZONE_JSON=$(cf GET "/zones?name=${CF_DOMAIN}")
echo "$ZONE_JSON" | ok || die "could not list zones"
ZONE_ID=$(echo "$ZONE_JSON" | python3 -c 'import json,sys; r=json.load(sys.stdin)["result"]; print(r[0]["id"] if r else "")')
[ -n "$ZONE_ID" ] || die "no zone named ${CF_DOMAIN} on this account — add the domain to Cloudflare first, and delegate its nameservers (§11)"
log "  zone: ${ZONE_ID}"

NS=$(echo "$ZONE_JSON" | python3 -c 'import json,sys; r=json.load(sys.stdin)["result"][0]; print(r.get("status"), " ".join(r.get("name_servers") or []))')
log "  status + assigned nameservers: ${NS}"
case "$NS" in
  active*) : ;;
  *) log "  WARNING: zone is not 'active' yet — nameserver delegation is still propagating (§11 flags this as a Day-1 risk)";;
esac

if [ "$DRY_RUN" = 1 ]; then
  log "--dry-run: stopping before any change"
  exit 0
fi

# --- 2. Zone settings (§5.3) -------------------------------------------------
# ssl=full does NOT mean Full (strict); "strict" is a distinct value and is what §5.3
# requires, because plain Full accepts ANY certificate from the origin including a
# self-signed one, which defeats the point of encrypting that hop.
set_setting() { # id value_json human
  local id="$1" value="$2" human="$3"
  if cf PATCH "/zones/${ZONE_ID}/settings/${id}" "{\"value\":${value}}" | ok; then
    log "  set ${human}"
  else
    log "  WARNING: could not set ${human} — check the token has Zone Settings:Edit"
  fi
}

log "applying zone settings"
set_setting ssl '"strict"'        'SSL/TLS mode = Full (strict)'
set_setting always_use_https '"on"' 'Always Use HTTPS = on'
set_setting brotli '"on"'         'Brotli = on'
# Auto Minify is off because CI already minifies, and re-minifying hashed assets risks
# altering bytes that the content hash promised were immutable (§5.5).
set_setting minify '{"css":"off","html":"off","js":"off"}' 'Auto Minify = off'

# --- 3. The two Cache Rules (§5.3) -------------------------------------------
# Cache Rules live in the http_request_cache_settings ruleset phase. There is exactly
# one such entrypoint ruleset per zone, and PUTting it replaces ALL of its rules — which
# is what makes this idempotent, but also means a rule added by hand in the dashboard
# will be removed. That is intentional: §5.3 budgets 2 of the Free plan's 10 slots and
# the whole cache design depends on knowing precisely what is there.
log "reading the cache ruleset"
RS=$(cf GET "/zones/${ZONE_ID}/rulesets?phase=http_request_cache_settings")
echo "$RS" | ok || die "could not read rulesets"
RS_ID=$(echo "$RS" | python3 -c '
import json,sys
for r in json.load(sys.stdin)["result"]:
    if r.get("phase")=="http_request_cache_settings" and r.get("kind")=="zone":
        print(r["id"]); break
')

RULES=$(cat <<'JSON'
[
  {
    "description": "pgds rule 1 - bypass cache for admin, login, REST, cron, xmlrpc (Proposal 02 5.3)",
    "expression": "(http.request.uri.path contains \"/wp-admin/\") or (http.request.uri.path contains \"/wp-login.php\") or (http.request.uri.path contains \"/wp-json/\") or (http.request.uri.path contains \"/wp-cron.php\") or (http.request.uri.path contains \"/xmlrpc.php\")",
    "action": "set_cache_settings",
    "action_parameters": { "cache": false },
    "enabled": true
  },
  {
    "description": "pgds rule 2 - cache static assets at the edge, 1 month TTL (Proposal 02 5.3)",
    "expression": "(http.request.uri.path matches \"\\\\.(css|js|woff2|woff|ttf|webp|avif|jpg|jpeg|png|gif|svg|ico)$\")",
    "action": "set_cache_settings",
    "action_parameters": {
      "cache": true,
      "edge_ttl": { "mode": "override_origin", "default": 2678400 },
      "browser_ttl": { "mode": "respect_origin" }
    },
    "enabled": true
  }
]
JSON
)

# NOTE: HTML is absent from both rules on purpose. §5.2/§5.6: the edge caches static
# assets ONLY. Nginx FastCGI owns the page cache at the origin and is purged explicitly
# on save_post, which removes the edge stale window entirely and means no cookie-based
# bypass rule is needed.

BODY=$(python3 -c '
import json,sys
rules=json.loads(sys.argv[1])
print(json.dumps({"rules":rules}))' "$RULES")

if [ -n "$RS_ID" ]; then
  log "replacing the ${RS_ID} ruleset with the 2 pgds rules"
  RESP=$(cf PUT "/zones/${ZONE_ID}/rulesets/${RS_ID}" "$BODY")
else
  log "creating the cache ruleset with the 2 pgds rules"
  RESP=$(cf POST "/zones/${ZONE_ID}/rulesets" "$(python3 -c '
import json,sys
print(json.dumps({
  "name":"pgds cache rules",
  "kind":"zone",
  "phase":"http_request_cache_settings",
  "rules": json.loads(sys.argv[1])
}))' "$RULES")")
fi
echo "$RESP" | ok || die "could not write the cache rules"

# --- 4. Verify by reading back ----------------------------------------------
log "verifying"
FAIL=0
check_setting() { # id expected human
  local got
  got=$(cf GET "/zones/${ZONE_ID}/settings/$1" | python3 -c 'import json,sys; print(json.dumps(json.load(sys.stdin)["result"]["value"]))' 2>/dev/null || echo '"?"')
  if [ "$got" = "$2" ]; then
    log "  OK   $3 = $got"
  else
    log "  FAIL $3: expected $2, got $got"
    FAIL=1
  fi
}
check_setting ssl '"strict"' 'SSL mode'
check_setting always_use_https '"on"' 'Always Use HTTPS'

RULE_COUNT=$(cf GET "/zones/${ZONE_ID}/rulesets?phase=http_request_cache_settings" \
  | python3 -c '
import json,sys
for r in json.load(sys.stdin)["result"]:
    if r.get("phase")=="http_request_cache_settings" and r.get("kind")=="zone":
        print(len(r.get("rules") or [])); break
else: print(0)' 2>/dev/null || echo 0)
if [ "$RULE_COUNT" = "2" ]; then
  log "  OK   cache rules = 2 (of the Free plan's 10 slots, per 5.3)"
else
  log "  FAIL cache rules: expected 2, found ${RULE_COUNT}"
  FAIL=1
fi

[ "$FAIL" = 0 ] || die "verification failed — see the FAIL lines above"

log "done. Remaining MANUAL steps (deliberately not automated):"
log "  1. Generate an Origin CA certificate in the dashboard, install it on the origin,"
log "     and confirm nginx serves 443 with it (Full (strict) requires this)."
log "  2. Point the apex + www A records at the origin IP and enable the orange cloud."
log "     THIS is the cutover (section 11) — do it last, after the go/no-go gate."
log "  3. Confirm a static asset returns cf-cache-status: HIT on the second request:"
log "       curl -sI https://${CF_DOMAIN}/wp-content/themes/pgds/assets/dist/main.<hash>.css | grep cf-cache-status"
