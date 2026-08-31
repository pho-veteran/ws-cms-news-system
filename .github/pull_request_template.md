<!--
Title: conventional commits with the pgds scope, e.g. `feat(pgds): add video facade`.
Keep it under 70 characters; put the detail in the body below.
-->

## Summary

<!-- What changed and why, in two or three sentences. Link the proposal section or
     RUNBOOK step this implements when one applies. -->

## Scope

<!-- Tick what this PR touches. -->

- [ ] Theme PHP (`wp-content/themes/pgds/`)
- [ ] SCSS / JS (`src/`)
- [ ] mu-plugins
- [ ] Infrastructure (`infra/`)
- [ ] Tools / WP-CLI (`tools/`, `inc/cli-import.php`)
- [ ] Documentation

## Verification

<!-- State what actually ran, with the result. Do not tick a box that was not run. -->

- [ ] `npm run build` succeeds and `assets/dist/manifest.json` is regenerated
- [ ] `npm run lint:js` passes
- [ ] `docker compose run --rm wpcli /scripts/lint.sh` passes (`php -l`)
- [ ] Front page verified at `localhost:8080` — all 11 blocks render
- [ ] Not verified (explain below)

Notes:

## Checklist

- [ ] Code, comments, and docs are in English; Vietnamese only in visitor-facing content
- [ ] Every new PHP file opens with `if ( ! defined( 'ABSPATH' ) ) { exit; }`
- [ ] Output escaped by context (`esc_html` / `esc_attr` / `esc_url` / `wp_kses_post`);
      JSON-LD uses `wp_json_encode`
- [ ] New globals use the `pgds_` prefix; new meta keys use `_pgds_`
- [ ] `functions.php` only requires modules; module load order preserved
- [ ] No generated files committed (`assets/dist/**`)
- [ ] Design tokens changed in `_tokens.scss`, not hardcoded in components
- [ ] PHP changes reviewed with the `wp-security-reviewer` subagent

## Business rules

<!-- Tick only the ones this PR affects, and say how the invariant still holds. -->

- [ ] Video: one canonical `_pgds_youtube_id`, facade stays click-to-load
- [ ] Import stays idempotent on `_pgds_source_id`; dry-run error rate under 2%
- [ ] Cache purge order stays origin first, edge second
- [ ] YouTube API batching stays at 50 IDs per call, quota-aware

## Risk and rollback

<!-- Blast radius, and how to undo this. Deployment is a manual rsync — CI does not
     exist yet, so `git revert` alone does not roll production back. -->
