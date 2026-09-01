<?php
/**
 * Scheduled jobs.
 *
 * §6.4: "Run a daily cron job to fetch `duration` + `title` for posts with
 * `_pgds_youtube_id`."
 *
 * Only `wp pgds yt-sync` existed, as a command an operator had to remember to type. The
 * RUNBOOK meanwhile documented a `pgds_fetch_yt_meta` cron that was never implemented, so
 * the written procedure described a job that did not exist — the worst of both, because
 * nobody would look for a missing schedule they believed was already running. Without it,
 * a video whose duration changes, or which is made private after import, keeps its stale
 * badge and its VideoObject schema indefinitely (§6.3 depends on the sync to set
 * `_pgds_video_unavailable`).
 *
 * Registered here rather than in inc/cli-import.php because that file returns early unless
 * WP_CLI is defined — a schedule declared there would never be registered by a web request,
 * which is what runs `wp_next_scheduled()`/`wp_schedule_event()` bookkeeping.
 *
 * @package pgds
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Cron hook name. Matches the name documented in RUNBOOK.md §6.
 */
const PGDS_YT_SYNC_HOOK = 'pgds_fetch_yt_meta';

/**
 * Register the daily YouTube metadata sync.
 *
 * Scheduled at an off-peak, off-the-hour time for the same reason the backup jobs are
 * (RUNBOOK §7): 2 GB of RAM cannot absorb the sync, a backup, and a traffic spike at once,
 * and everything scheduled at :00 collides.
 *
 * Uses the site's timezone via wp_timestamp, so "03:40" means 03:40 in Vietnam rather than
 * UTC — a job that touches published content should run when the newsroom is quiet.
 */
function pgds_schedule_jobs() {
	if ( wp_next_scheduled( PGDS_YT_SYNC_HOOK ) ) {
		return;
	}
	/*
	 * Next 03:40 in the SITE's timezone, expressed as the UTC timestamp
	 * wp_schedule_event() expects.
	 *
	 * Built with DateTimeImmutable in wp_timezone() rather than by adding gmt_offset to a
	 * gmdate() string: an offset is wrong for half the year in any DST zone, and getTimestamp()
	 * on a zone-aware object is the only form that stays correct across a DST boundary.
	 */
	try {
		$tz     = wp_timezone();
		$target = new DateTimeImmutable( 'today 03:40', $tz );
		if ( $target->getTimestamp() <= time() ) {
			$target = $target->modify( '+1 day' );
		}
		$next = $target->getTimestamp();
	} catch ( Exception $e ) {
		// A malformed timezone must not prevent the job existing at all.
		$next = time() + HOUR_IN_SECONDS;
	}

	wp_schedule_event( $next, 'daily', PGDS_YT_SYNC_HOOK );
}
add_action( 'init', 'pgds_schedule_jobs' );

/**
 * Clear the schedule when the theme is deactivated.
 *
 * Without this the event stays in the cron array after a theme switch, firing an action
 * with no listener twice a day forever — harmless but untraceable, and it makes
 * `wp cron event list` lie about what the site does.
 */
function pgds_unschedule_jobs() {
	wp_clear_scheduled_hook( PGDS_YT_SYNC_HOOK );
}
add_action( 'switch_theme', 'pgds_unschedule_jobs' );

/**
 * Run the YouTube metadata sync.
 *
 * Delegates to the same code path as `wp pgds yt-sync` so there is exactly one
 * implementation of the §6.4 rules (batch 50 IDs per call, never overwrite stored meta
 * with an empty value, set `_pgds_video_unavailable` for private/removed videos).
 *
 * Requires PGDS_YT_API_KEY. Without it the command can still refresh posters but cannot
 * reach the Data API, so the job logs and returns instead of doing partial work on a
 * schedule nobody is watching.
 */
function pgds_run_yt_sync() {
	if ( ! defined( 'PGDS_YT_API_KEY' ) || ! PGDS_YT_API_KEY ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( '[pgds] ' . PGDS_YT_SYNC_HOOK . ': PGDS_YT_API_KEY is not defined; skipping.' );
		}
		return;
	}

	/*
	 * Gate on WP_CLI, NOT on class_exists( 'PGDS_CLI_Command' ).
	 *
	 * PGDS_CLI_Command::yt_sync() reports progress through WP_CLI::log() and
	 * WP_CLI::warning(), which only exist inside a WP-CLI process. A class_exists() guard
	 * looked equivalent and was not: measured under plain PHP with wp-load.php,
	 *
	 *   WP_CLI defined: no
	 *   PGDS_CLI_Command exists: yes
	 *   -> Error: Class "WP_CLI" not found
	 *
	 * because the class file had already been loaded in that process while the WP_CLI
	 * runner was absent. Anything that fires this hook outside WP-CLI — a stray
	 * `wp_cron.php` request if DISABLE_WP_CRON were ever removed, or another plugin
	 * calling do_action() — hit an uncaught Error. The guard has to test the thing
	 * yt_sync() actually needs.
	 *
	 * In the intended deployment this is always satisfied: system cron runs
	 * `wp cron event run --due-now` (see infra/wp-config-hardening.sample.php), so the
	 * event fires inside WP-CLI.
	 */
	if ( ! ( defined( 'WP_CLI' ) && WP_CLI && class_exists( 'WP_CLI' ) && class_exists( 'PGDS_CLI_Command' ) ) ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log(
				'[pgds] ' . PGDS_YT_SYNC_HOOK . ': fired outside WP-CLI; skipping. Run it with'
				. ' `wp cron event run ' . PGDS_YT_SYNC_HOOK . '` (see RUNBOOK §6).'
			);
		}
		return;
	}

	$cmd = new PGDS_CLI_Command();
	$cmd->yt_sync( array(), array() );
}
add_action( PGDS_YT_SYNC_HOOK, 'pgds_run_yt_sync' );
