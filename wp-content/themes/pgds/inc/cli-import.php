<?php
/**
 * WP-CLI: import 2,000 posts (idempotent), generate image variants, and build redirects.
 * Loaded only when running under WP-CLI.
 *
 * Commands (proposal §9.2):
 *   wp pgds import --file=data.json --batch=200 --dry-run
 *   wp pgds import --file=data.json --batch=200
 *   wp pgds media-variants --regenerate
 *   wp pgds build-redirects --out=/etc/nginx/redirects.map
 *
 * data.json schema (array of objects):
 *   source_id (required, deduplication key), title, slug, sapo, body_html,
 *   primary_cat (slug), cats (slug[]), tags (string[]), author (login/email),
 *   published_at (Y-m-d H:i:s), featured_image_url, gallery (url[]),
 *   youtube_url, source, old_url (for building redirects)
 *
 * @package pgds
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! ( defined( 'WP_CLI' ) && WP_CLI ) ) {
	return;
}

/**
 * PGDS commands.
 */
class PGDS_CLI_Command {

	/**
	 * Media that could not be imported, as human-readable lines.
	 *
	 * Kept separate from record errors: a post whose photo failed still imported, so it
	 * must not count toward §9.2's 2% record stop threshold. §13 gates the two rates
	 * independently ("Media failure rate < 2%, failure list reviewed"), and §14 requires
	 * the list itself as a handover deliverable.
	 *
	 * @var string[]
	 */
	private $media_failures = array();

	/**
	 * Import posts from a JSON file. Idempotent by _pgds_source_id.
	 *
	 * ## OPTIONS
	 *
	 * --file=<path>
	 * : Path to the JSON file.
	 *
	 * [--batch=<n>]
	 * : Number of posts per batch. Defaults to 200.
	 *
	 * [--dry-run]
	 * : Validate only; do not write to the database. Stop threshold: errors > 2%.
	 *
	 * @param array $args       Positional.
	 * @param array $assoc_args Flags.
	 */
	public function import( $args, $assoc_args ) {
		$file    = $assoc_args['file'] ?? '';
		$batch   = (int) ( $assoc_args['batch'] ?? 200 );
		$dry_run = isset( $assoc_args['dry-run'] );

		if ( ! $file || ! is_readable( $file ) ) {
			WP_CLI::error( "Unable to read file: {$file}" );
		}

		$raw  = file_get_contents( $file );
		$data = json_decode( $raw, true );
		if ( ! is_array( $data ) ) {
			WP_CLI::error( 'Invalid JSON or the root value is not an array.' );
		}

		$total   = count( $data );
		$errors  = array();
		$created = 0;
		$skipped = 0;
		$logfile = trailingslashit( sys_get_temp_dir() ) . 'pgds-import-' . gmdate( 'Ymd-His' ) . '.log';

		WP_CLI::log( sprintf( '%s %d records (batch=%d)%s', $dry_run ? '[DRY-RUN]' : '[IMPORT]', $total, $batch, $dry_run ? '' : '' ) );

		$progress = \WP_CLI\Utils\make_progress_bar( 'Processing', $total );

		foreach ( $data as $i => $rec ) {
			$err = $this->validate_record( $rec );
			if ( $err ) {
				$errors[] = "#{$i}: {$err}";
				$progress->tick();
				continue;
			}

			// Idempotent: already exists?
			$existing = $this->find_by_source_id( $rec['source_id'] );
			if ( $existing ) {
				$skipped++;
				$progress->tick();
				continue;
			}

			if ( ! $dry_run ) {
				$res = $this->create_post( $rec );
				if ( is_wp_error( $res ) ) {
					$errors[] = "#{$i} ({$rec['source_id']}): " . $res->get_error_message();
				} else {
					$created++;
				}
			} else {
				$created++; // Count as created
			}

			$progress->tick();

			// Pause between batches to avoid pushing 2 GB of RAM into swap.
			if ( 0 === ( ( $i + 1 ) % $batch ) ) {
				if ( function_exists( 'wp_cache_flush' ) ) {
					wp_cache_flush();
				}
				usleep( 200000 ); // 0.2s
			}
		}

		$progress->finish();

		$fail_rate = $total > 0 ? ( count( $errors ) / $total ) : 0;

		/*
		 * Two logs, because §13 gates two independent rates: the RECORD failure rate
		 * (§9.2's 2% stop threshold) and the MEDIA failure rate ("Media failure rate < 2%,
		 * failure list reviewed"). The media list is also a §14 handover deliverable, so it
		 * is written to its own reviewable file rather than mixed into the error log.
		 */
		file_put_contents( $logfile, implode( "\n", $errors ) . "\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents

		$media_log   = '';
		$media_rate  = 0.0;
		$media_total = 0;
		foreach ( $data as $rec ) {
			if ( ! is_array( $rec ) ) {
				continue;
			}
			$media_total += empty( $rec['featured_image_url'] ) ? 0 : 1;
			$media_total += count( (array) ( $rec['gallery'] ?? array() ) );
		}
		if ( $this->media_failures ) {
			$media_log = str_replace( 'pgds-import-', 'pgds-media-failures-', $logfile );
			file_put_contents( $media_log, implode( "\n", $this->media_failures ) . "\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		}
		if ( $media_total > 0 ) {
			$media_rate = count( $this->media_failures ) / $media_total;
		}

		WP_CLI::log( '----------------------------------------' );
		WP_CLI::log( sprintf( 'Created: %d | Skipped (existing): %d | Errors: %d/%d (%.2f%%)', $created, $skipped, count( $errors ), $total, $fail_rate * 100 ) );
		WP_CLI::log( sprintf( 'Media: %d referenced | failed: %d (%.2f%%)', $media_total, count( $this->media_failures ), $media_rate * 100 ) );
		WP_CLI::log( "Error log: {$logfile}" );
		if ( $media_log ) {
			WP_CLI::log( "Media failure list (§13/§14 deliverable): {$media_log}" );
		}
		// Reported as a warning, not an error: these posts imported correctly and the
		// images can be back-filled with `wp pgds yt-sync`/a re-run, so stopping the whole
		// migration for them would be wrong. §13 still requires a human to review the list.
		if ( $media_rate > 0.02 ) {
			WP_CLI::warning( sprintf( 'Media failure rate %.2f%% exceeds the 2%% §13 gate — review %s before go-live.', $media_rate * 100, $media_log ) );
		}

		// Stop threshold: 2% (proposal §9.2).
		if ( $fail_rate > 0.02 ) {
			WP_CLI::error( sprintf( 'Error rate %.2f%% exceeds 2%% — STOP. Fix the mapping and run again; do not continue the import.', $fail_rate * 100 ) );
		}

		if ( $dry_run ) {
			WP_CLI::success( 'Dry run passed. The real import can proceed.' );
		} else {
			WP_CLI::success( "Import complete. Created {$created} posts." );
		}
	}

	/**
	 * Download YouTube posters locally and optionally fetch duration.
	 *
	 * Poster: download from i.ytimg.com (maxres -> hq fallback), save to the Media Library,
	 * store the URL in _pgds_youtube_poster meta. Do not hotlink on display.
	 * Duration: fetch only when PGDS_YT_API_KEY is defined (YouTube Data API v3).
	 *
	 * ## OPTIONS
	 *
	 * [--post=<id>]
	 * : Process one post only. Empty = all posts with _pgds_youtube_id.
	 *
	 * [--force]
	 * : Re-download posters even when one already exists.
	 */
	public function yt_sync( $args, $assoc_args ) {
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$force   = isset( $assoc_args['force'] );
		$only    = isset( $assoc_args['post'] ) ? (int) $assoc_args['post'] : 0;
		$api_key = defined( 'PGDS_YT_API_KEY' ) ? PGDS_YT_API_KEY : '';

		$query_args = array(
			'post_type'      => 'post',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'no_found_rows'  => true,
			'meta_query'     => array( array( 'key' => '_pgds_youtube_id', 'compare' => 'EXISTS' ) ),
		);
		if ( $only ) {
			$query_args = array( 'post_type' => 'post', 'p' => $only, 'post_status' => 'any' );
		}
		$posts = get_posts( $query_args );

		if ( ! $posts ) {
			WP_CLI::warning( 'No posts have _pgds_youtube_id.' );
			return;
		}

		$ids   = array();
		$done  = 0;
		foreach ( $posts as $p ) {
			$vid = get_post_meta( $p->ID, '_pgds_youtube_id', true );
			if ( ! $vid ) {
				continue;
			}
			$ids[ $vid ] = $p->ID;

			// Poster.
			$has_poster = get_post_meta( $p->ID, '_pgds_youtube_poster', true );
			if ( $has_poster && ! $force ) {
				WP_CLI::log( "• {$vid}: poster already exists; skipping." );
			} else {
				$url = $this->yt_thumb_url( $vid );
				if ( $url ) {
					$tmp = download_url( $url );
					if ( ! is_wp_error( $tmp ) ) {
						$att_id = media_handle_sideload(
							array( 'name' => $vid . '.jpg', 'tmp_name' => $tmp ),
							$p->ID
						);
						if ( ! is_wp_error( $att_id ) ) {
							update_post_meta( $p->ID, '_pgds_youtube_poster', wp_get_attachment_image_url( $att_id, 'pgds-lead' ) );
							// Store the ID as well as the URL: the facade renders through
							// wp_get_attachment_image() when it has an ID, which yields the
							// attachment's real width/height plus srcset. A bare URL can only
							// be emitted with guessed dimensions.
							update_post_meta( $p->ID, '_pgds_youtube_poster_id', (int) $att_id );
							$done++;
							WP_CLI::log( "✓ {$vid}: poster downloaded." );
						} else {
							@unlink( $tmp );
							WP_CLI::warning( "{$vid}: sideload error — " . $att_id->get_error_message() );
						}
					} else {
						WP_CLI::warning( "{$vid}: unable to download thumbnail." );
					}
				}
			}
		}

		// Duration (batch 50) when an API key is available.
		if ( $api_key && $ids ) {
			$this->yt_fetch_durations( $ids, $api_key );
		} elseif ( ! $api_key ) {
			WP_CLI::log( 'Skipping duration (PGDS_YT_API_KEY is not configured).' );
		}

		WP_CLI::success( "yt-sync complete. New posters: {$done}." );
	}

	/**
	 * YouTube thumbnail URL (maxres; no API key required).
	 *
	 * @param string $vid Video ID.
	 * @return string
	 */
	private function yt_thumb_url( $vid ) {
		// maxresdefault is not always available; try maxres, then hq.
		$candidates = array(
			"https://i.ytimg.com/vi/{$vid}/maxresdefault.jpg",
			"https://i.ytimg.com/vi/{$vid}/hqdefault.jpg",
		);
		foreach ( $candidates as $u ) {
			$resp = wp_remote_head( $u, array( 'timeout' => 8 ) );
			if ( ! is_wp_error( $resp ) && 200 === wp_remote_retrieve_response_code( $resp ) ) {
				return $u;
			}
		}
		return '';
	}

	/**
	 * Fetch duration for multiple videos (batch 50, 1 unit/call).
	 *
	 * @param array  $ids     [video_id => post_id].
	 * @param string $api_key API key.
	 */
	private function yt_fetch_durations( $ids, $api_key ) {
		$vids   = array_keys( $ids );
		// 50 IDs per call: videos.list costs 1 quota unit per CALL, not per video, so
		// batching is what keeps 2,000 posts inside the 10,000 units/day quota (§6.4).
		$chunks = array_chunk( $vids, 50 );
		$stats  = array(
			'checked'     => 0,
			'duration'    => 0,
			'title'       => 0,
			'unavailable' => 0,
			'kept'        => 0,
		);

		foreach ( $chunks as $chunk ) {
			// 'snippet' as well as 'contentDetails': §6.4 requires duration AND title.
			// Both parts come back in the same call, so this costs no extra quota.
			$url  = add_query_arg(
				array(
					'part' => 'contentDetails,snippet,status',
					'id'   => implode( ',', $chunk ),
					'key'  => $api_key,
				),
				'https://www.googleapis.com/youtube/v3/videos'
			);
			$resp = wp_remote_get( $url, array( 'timeout' => 15 ) );

			if ( is_wp_error( $resp ) ) {
				// §6.4: on API failure keep the stored metadata. Continuing to the next
				// chunk (rather than treating the silence as "no data") is what prevents
				// a transient outage from blanking good durations.
				WP_CLI::warning( 'Data API transport error, keeping stored meta: ' . $resp->get_error_message() );
				$stats['kept'] += count( $chunk );
				continue;
			}

			$code = (int) wp_remote_retrieve_response_code( $resp );
			$body = json_decode( wp_remote_retrieve_body( $resp ), true );

			if ( 200 !== $code ) {
				/*
				 * The response code was previously never checked, so a 403 looked exactly
				 * like a success with zero items — and the loop below would simply write
				 * nothing while reporting success. 403 is also how the API reports
				 * quotaExceeded, which §6.4 singles out.
				 */
				$reason = $body['error']['errors'][0]['reason'] ?? 'unknown';
				if ( 403 === $code && in_array( $reason, array( 'quotaExceeded', 'dailyLimitExceeded' ), true ) ) {
					WP_CLI::warning( 'YouTube API quota exhausted — stopping. Stored metadata is unchanged (§6.4).' );
					// Stop entirely: every further chunk would fail the same way and burn
					// wall-clock time for nothing.
					break;
				}
				WP_CLI::warning( sprintf( 'Data API HTTP %d (%s), keeping stored meta.', $code, $reason ) );
				$stats['kept'] += count( $chunk );
				continue;
			}

			$returned = array();
			foreach ( ( $body['items'] ?? array() ) as $item ) {
				$vid = $item['id'] ?? '';
				if ( ! $vid || ! isset( $ids[ $vid ] ) ) {
					continue;
				}
				$returned[] = $vid;
				$post_id    = $ids[ $vid ];
				$stats['checked']++;

				// --- Availability (§6.3) ------------------------------------------
				// A video that is present but not publicly playable: private, or region
				// blocked everywhere. uploadStatus covers rejected/deleted uploads.
				$privacy  = $item['status']['privacyStatus'] ?? '';
				$upload   = $item['status']['uploadStatus'] ?? '';
				$embeddable = $item['status']['embeddable'] ?? true;
				$blocked  = ! empty( $item['contentDetails']['regionRestriction']['blocked'] );

				$unavailable = ( 'private' === $privacy )
					|| in_array( $upload, array( 'rejected', 'deleted', 'failed' ), true )
					|| ! $embeddable;

				if ( $unavailable ) {
					update_post_meta( $post_id, '_pgds_video_unavailable', '1' );
					$stats['unavailable']++;
					WP_CLI::warning(
						sprintf(
							'%s: not playable (privacy=%s upload=%s embeddable=%s) — facade hidden, VideoObject dropped.',
							$vid,
							$privacy ?: '?',
							$upload ?: '?',
							$embeddable ? 'yes' : 'no'
						)
					);
					// Do NOT continue: duration and title are still worth storing so the
					// card keeps its badge if the video is later made public again.
				} else {
					// Recovered: clear a stale flag so the facade comes back.
					if ( get_post_meta( $post_id, '_pgds_video_unavailable', true ) ) {
						delete_post_meta( $post_id, '_pgds_video_unavailable' );
						WP_CLI::log( "✓ {$vid}: available again — flag cleared." );
					}
				}
				if ( $blocked ) {
					WP_CLI::warning( "{$vid}: has regional blocks; still shown, but check manually." );
				}

				// --- Duration (§6.4) ----------------------------------------------
				$iso = $item['contentDetails']['duration'] ?? '';
				$sec = $iso ? $this->iso8601_to_seconds( $iso ) : 0;
				// Never overwrite stored metadata with an empty value (§6.4). A live
				// stream returns PT0S, which would otherwise wipe a real duration.
				if ( $sec > 0 ) {
					update_post_meta( $post_id, '_pgds_youtube_dur', $sec );
					$stats['duration']++;
				} elseif ( get_post_meta( $post_id, '_pgds_youtube_dur', true ) ) {
					$stats['kept']++;
					WP_CLI::log( "• {$vid}: API returned no usable duration; keeping stored value." );
				}

				// --- Title (§6.4) -------------------------------------------------
				// Stored in its own meta key rather than overwriting post_title: the
				// editor's headline is editorial copy and must not be replaced by
				// YouTube's. This is used for the facade's aria-label and VideoObject.
				$title = $item['snippet']['title'] ?? '';
				if ( '' !== trim( $title ) ) {
					update_post_meta( $post_id, '_pgds_youtube_title', sanitize_text_field( $title ) );
					$stats['title']++;
				}
			}

			/*
			 * IDs absent from the response are removed or permanently unavailable —
			 * videos.list omits them rather than returning an error, which is the only
			 * signal that a video is gone (§6.3 "private / removed").
			 */
			$missing = array_diff( $chunk, $returned );
			foreach ( $missing as $vid ) {
				if ( ! isset( $ids[ $vid ] ) ) {
					continue;
				}
				update_post_meta( $ids[ $vid ], '_pgds_video_unavailable', '1' );
				$stats['unavailable']++;
				WP_CLI::warning( "{$vid}: not returned by the API (removed) — facade hidden, VideoObject dropped." );
			}
		}

		WP_CLI::log(
			sprintf(
				'YouTube sync: %d checked, %d durations, %d titles, %d unavailable, %d kept from stored meta.',
				$stats['checked'],
				$stats['duration'],
				$stats['title'],
				$stats['unavailable'],
				$stats['kept']
			)
		);
	}

	/**
	 * ISO-8601 duration -> seconds.
	 *
	 * @param string $iso PT#H#M#S.
	 * @return int
	 */
	private function iso8601_to_seconds( $iso ) {
		try {
			$d = new DateInterval( $iso );
			return ( $d->h * 3600 ) + ( $d->i * 60 ) + $d->s + ( $d->d * 86400 );
		} catch ( Exception $e ) {
			return 0;
		}
	}

	/**
	 * Regenerate image variants (§9.3). Sub-sizes are written as WebP.
	 *
	 * The WebP conversion itself lives in pgds_webp_output_format() (inc/setup.php) on
	 * core's `image_editor_output_format`, so it applies to editor uploads as well as to
	 * this command — a command-only conversion would leave every future upload as JPEG.
	 *
	 * ## OPTIONS
	 *
	 * [--regenerate]
	 * : Rebuild variants for attachments that already have complete metadata. Without
	 * this flag only attachments MISSING a variant are processed, which is what makes
	 * the command safe to re-run after a partial pass.
	 *
	 * [--dry-run]
	 * : Report what would be processed and exit without writing files.
	 *
	 * ## EXAMPLES
	 *
	 *     # Fill in whatever is missing (safe to repeat after an interrupted run).
	 *     nice -n 19 wp pgds media-variants
	 *
	 *     # Rebuild everything, e.g. after changing pgds_image_sizes().
	 *     nice -n 19 wp pgds media-variants --regenerate
	 *
	 * @param array $args       Positional args.
	 * @param array $assoc_args Associative args.
	 */
	public function media_variants( $args, $assoc_args ) {
		$regenerate = ! empty( $assoc_args['regenerate'] );
		$dry_run    = ! empty( $assoc_args['dry-run'] );

		WP_CLI::log( 'Run with nice -n 19 and reduce pm.max_children to 2 for this operation (proposal §9.3).' );

		$q = new WP_Query(
			array(
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'post_mime_type' => 'image',
				'posts_per_page' => -1,
				'no_found_rows'  => true,
				'fields'         => 'ids',
			)
		);
		$ids = $q->posts;
		if ( ! $ids ) {
			WP_CLI::success( 'No image attachments found.' );
			return;
		}

		/*
		 * Partition first, then work.
		 *
		 * --regenerate was previously DECLARED and never READ: the command rebuilt every
		 * attachment either way. On the 2,000-post import in §9 that is the difference
		 * between topping up a handful of missing sizes and re-encoding the entire media
		 * library on a 2 GB origin the proposal already warns will swap during image work
		 * (§9.3) — and it made an interrupted run expensive to resume, so an operator's
		 * natural reaction (re-run it) was the worst available option.
		 */
		$missing = array();
		$intact  = array();
		$gone    = array();
		$specs   = pgds_image_sizes();
		$wanted  = array_keys( $specs );

		foreach ( $ids as $id ) {
			// Partitioned on the SAME path the work loop will read, so an attachment can
			// never be counted as processable and then fail for lack of a source file.
			$file = pgds_original_upload_path( $id );
			if ( ! $file ) {
				$gone[] = $id;
				continue;
			}
			$meta  = wp_get_attachment_metadata( $id );
			$sizes = isset( $meta['sizes'] ) && is_array( $meta['sizes'] ) ? $meta['sizes'] : array();

			/*
			 * A registered size is legitimately absent when the SOURCE is smaller than the
			 * target, because core does not upscale. Treating that as missing would make
			 * the command re-encode those attachments on every single run, forever. So a
			 * size only counts as missing when the original is actually big enough for it.
			 */
			$lacking = false;
			foreach ( $wanted as $size ) {
				if ( isset( $sizes[ $size ] ) ) {
					continue;
				}
				$spec = $specs[ $size ];
				$w    = isset( $meta['width'] ) ? (int) $meta['width'] : 0;
				$h    = isset( $meta['height'] ) ? (int) $meta['height'] : 0;
				if ( $w >= $spec[0] && $h >= $spec[1] ) {
					$lacking = true;
					break;
				}
			}

			if ( $lacking ) {
				$missing[] = $id;
			} else {
				$intact[] = $id;
			}
		}

		$targets = $regenerate ? array_merge( $missing, $intact ) : $missing;

		WP_CLI::log(
			sprintf(
				'Attachments: %d total, %d missing a variant, %d complete, %d with no file on disk.',
				count( $ids ),
				count( $missing ),
				count( $intact ),
				count( $gone )
			)
		);
		if ( $gone ) {
			WP_CLI::warning( sprintf( '%d attachment(s) have no file on disk and were skipped: %s', count( $gone ), implode( ', ', array_slice( $gone, 0, 20 ) ) ) );
		}

		if ( ! $targets ) {
			WP_CLI::success( 'Nothing to do. Pass --regenerate to rebuild variants that are already present.' );
			return;
		}

		if ( $dry_run ) {
			WP_CLI::success( sprintf( 'Dry run: would process %d attachment(s).', count( $targets ) ) );
			return;
		}

		// wp_generate_attachment_metadata() lives in wp-admin/includes/image.php, which is
		// NOT loaded for a plain `wp` invocation. yt_sync() requires it for the same reason.
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$done      = 0;
		$processed = 0;
		$failed    = array();
		$progress = \WP_CLI\Utils\make_progress_bar( $regenerate ? 'Rebuilding all variants' : 'Filling missing variants', count( $targets ) );

		foreach ( $targets as $id ) {
			/*
			 * Source from the PRESERVED ORIGINAL, not from get_attached_file().
			 *
			 * Because pgds_webp_output_format() converts the generated full size,
			 * `_wp_attached_file` ends up pointing at the WebP, so get_attached_file()
			 * returns an already-lossy file. Re-encoding that produces generational loss,
			 * measured on this very command:
			 *
			 *   before: 2308 bytes  md5 38dff48e...
			 *   after : 2328 bytes  md5 7f32480f...   (same size, re-encoded from WebP)
			 *
			 * The bytes change on every pass and quality only ever degrades. Nothing warns
			 * about it and the images still render, so a few --regenerate runs over the
			 * 2,000-post library (§9) would quietly soften every photo on the site.
			 *
			 * Resolved by pgds_original_upload_path(), NOT by wp_get_original_image_path()
			 * alone: that helper depends on metadata['original_image'], and regenerating
			 * from the WebP DELETES that key, so after one bad pass the pointer back to the
			 * PNG is gone and the helper starts returning the WebP itself. Recovery has to
			 * work from a signal the conversion cannot destroy — see that function.
			 *
			 * Verified recovery on a flattened attachment: sourcing the PNG restored
			 * original_image=pgds-seed-18-3.png and reproduced the pristine variant
			 * (2308 bytes, md5 38dff48e) that the degraded chain had grown to 2328.
			 */
			$file = pgds_original_upload_path( $id );
			$meta = wp_generate_attachment_metadata( $id, $file );
			// wp_generate_attachment_metadata() returns WP_Error when the editor cannot
			// load the file (truncated upload, unsupported variant). Reported rather than
			// written, so a bad file cannot blank out working metadata.
			if ( is_wp_error( $meta ) || empty( $meta ) ) {
				$failed[] = $id;
			} else {
				wp_update_attachment_metadata( $id, $meta );
				++$done;
			}
			$progress->tick();
			/*
			 * Long -1 queries accumulate object cache entries; on the 2 GB origin this is
			 * the difference between finishing and being OOM-killed partway.
			 *
			 * Keyed on ITERATIONS, not successes. Keying it on $done meant a run where the
			 * image editor could not load its sources — a truncated rsync, or a host whose
			 * library lacks the WebP delegate — left $done at 0 for the whole loop, so the
			 * cache was never cleared on exactly the run that iterates every attachment and
			 * needs it most.
			 */
			++$processed;
			if ( 0 === $processed % 50 ) {
				\WP_CLI\Utils\wp_clear_object_cache();
			}
		}
		$progress->finish();

		if ( $failed ) {
			WP_CLI::warning( sprintf( '%d attachment(s) failed: %s', count( $failed ), implode( ', ', array_slice( $failed, 0, 20 ) ) ) );
		}
		WP_CLI::success( sprintf( 'Processed %d attachment(s).', $done ) );
	}

	/**
	 * Generate redirects.map for nginx from _pgds_old_url meta.
	 *
	 * ## OPTIONS
	 *
	 * --out=<path>
	 * : Output file (e.g. /etc/nginx/redirects.map).
	 */
	public function build_redirects( $args, $assoc_args ) {
		$out = $assoc_args['out'] ?? '';
		if ( ! $out ) {
			WP_CLI::error( 'Missing --out=<path>' );
		}

		$q = new WP_Query(
			array(
				'post_type'      => 'post',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'no_found_rows'  => true,
				'meta_query'     => array(
					array( 'key' => '_pgds_old_url', 'compare' => 'EXISTS' ),
				),
			)
		);

		/*
		 * The nginx map is keyed on $request_uri, which INCLUDES the query string.
		 * So the key must be path + query, not the path alone.
		 *
		 * Stripping the query with PHP_URL_PATH was a serious bug: legacy WordPress
		 * URLs are overwhelmingly of the form /?p=1234, and every one of them reduced
		 * to "/". That produced 23 duplicate "/" keys out of 24 records, and nginx
		 * refuses to start on a duplicate map key:
		 *     [emerg] conflicting parameter "/" in /etc/nginx/redirects.map:5
		 * which takes the whole origin down, not just the redirects. Worse, had it
		 * loaded, a "/" key would 301 the HOME PAGE to whichever post won.
		 *
		 * Rules enforced below, each of which was an observed failure mode:
		 *   1. Keep the query string.
		 *   2. Deduplicate keys; a repeat is reported rather than silently emitted.
		 *   3. Never emit a bare "/" key.
		 *   4. Quote keys, since ?, = and & are otherwise ambiguous to the parser.
		 *   5. Skip a rule whose old URL already equals the new permalink (a redirect
		 *      loop).
		 */
		$seen      = array();
		$lines     = array();
		$skipped   = array();

		foreach ( $q->posts as $p ) {
			$old = trim( (string) get_post_meta( $p->ID, '_pgds_old_url', true ) );
			if ( '' === $old ) {
				continue;
			}

			$parts = wp_parse_url( $old );
			$key   = ( $parts['path'] ?? '' );
			if ( ! empty( $parts['query'] ) ) {
				$key .= '?' . $parts['query'];
			}
			// wp_parse_url() returns no path for a bare "?p=1"; treat it as root-relative.
			if ( '' === $key ) {
				$key = $old;
			}
			if ( '' === $key || '/' === $key ) {
				$skipped[] = sprintf( 'post %d: refusing to redirect "/" (would break the home page)', $p->ID );
				continue;
			}

			/*
			 * MAP-FILE INJECTION GUARD.
			 *
			 * _pgds_old_url comes from the import JSON (§9) and is stored unsanitized, so it
			 * is attacker-influenced data that this command writes into a file nginx then
			 * loads as CONFIGURATION. Quoting the key is not sufficient, because the value
			 * may contain a quote of its own and nginx separates map entries with ';', not
			 * with newlines — so a single line can carry a second, complete rule.
			 *
			 * Demonstrated against this very command. An `_pgds_old_url` of
			 *   /legacy-a"  http://evil.test/;<LF>"/"  http://evil.test/pwned;
			 * produced, in the generated map:
			 *   "/legacy-a"  http://evil.test/;_"/"  http://evil.test/pwned;"   http://…
			 * i.e. an attacker-controlled rule for "/" that 301s THE HOME PAGE off-site.
			 * (The LF itself is neutralised — parse_url rewrites control characters to '_' —
			 * but the quote alone is enough, so relying on that is not a defence.)
			 *
			 * Fixed by whitelisting instead of escaping: a legacy URL is a path and optional
			 * query, and every character legal in those is in the set below (RFC 3986
			 * unreserved + sub-delims + ':@/?', minus the quote, backslash, and whitespace
			 * that give the map parser its structure). Anything else is refused and reported
			 * rather than escaped, because a rule nobody can read is worse than a missing
			 * one an operator is told about.
			 */
			if ( ! preg_match( '#^/[A-Za-z0-9._~!$&\'()*+,;=:@/?%-]*$#', $key ) ) {
				$skipped[] = sprintf(
					'post %d: old URL contains characters not allowed in an nginx map key; refused (%s)',
					$p->ID,
					// Rendered safe for the terminal: the point is to show WHICH post is bad.
					preg_replace( '/[^\x20-\x7E]/', '?', substr( $key, 0, 80 ) )
				);
				continue;
			}

			$new = get_permalink( $p );
			if ( ! $new ) {
				$skipped[] = sprintf( 'post %d: no permalink', $p->ID );
				continue;
			}

			// A rule pointing at itself is a redirect loop.
			$new_path = wp_parse_url( $new, PHP_URL_PATH );
			if ( $key === $new_path ) {
				$skipped[] = sprintf( 'post %d: old URL equals new permalink (%s)', $p->ID, $key );
				continue;
			}

			if ( isset( $seen[ $key ] ) ) {
				$skipped[] = sprintf( 'post %d: duplicate old URL "%s" (already mapped by post %d)', $p->ID, $key, $seen[ $key ] );
				continue;
			}
			/*
			 * Same whitelist on the VALUE side. $new is a get_permalink() result rather than
			 * raw import data, so it is far better constrained — but it still derives from
			 * post_name, and the value is written unquoted, where whitespace or ';' would
			 * terminate the entry early. Validated rather than trusted, since the cost of
			 * being wrong is the same nginx config file.
			 */
			if ( ! preg_match( '#^https?://[A-Za-z0-9._~!$&\'()*+,;=:@/?%\#-]+$#', $new ) ) {
				$skipped[] = sprintf( 'post %d: permalink is not a plain http(s) URL; refused', $p->ID );
				continue;
			}

			$seen[ $key ] = $p->ID;

			// Quoted key: ?, = and & are special to the map parser unquoted.
			$lines[] = sprintf( '"%s"   %s;', $key, $new );
		}

		$header  = '# Generated by: wp pgds build-redirects - ' . gmdate( 'c' ) . "\n";
		$header .= '# ' . count( $lines ) . " redirects. After updating: nginx -t && systemctl reload nginx\n";
		if ( $skipped ) {
			$header .= '# ' . count( $skipped ) . " rule(s) skipped - see the WP-CLI output for reasons.\n";
		}

		if ( false === file_put_contents( $out, $header . implode( "\n", $lines ) . "\n" ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			WP_CLI::error( sprintf( 'Could not write %s', $out ) );
		}

		foreach ( $skipped as $note ) {
			WP_CLI::warning( $note );
		}

		WP_CLI::success(
			sprintf(
				'Wrote %d redirects to %s (%d skipped). Run `nginx -t` before reloading.',
				count( $lines ),
				$out,
				count( $skipped )
			)
		);
	}

	/* ----------------------- helper ----------------------- */

	/**
	 * Validate a record. Return an error string or '' when valid.
	 *
	 * @param mixed $rec Record.
	 * @return string
	 */
	private function validate_record( $rec ) {
		if ( ! is_array( $rec ) ) {
			return 'not an object';
		}
		if ( empty( $rec['source_id'] ) ) {
			return 'missing source_id';
		}
		if ( empty( $rec['title'] ) ) {
			return 'missing title';
		}
		if ( empty( $rec['primary_cat'] ) ) {
			return 'missing primary_cat';
		}

		/*
		 * Categories must already exist in the target vocabulary (§4.1).
		 *
		 * Previously only "is it non-empty" was checked, and create_post() then called
		 * pgds_ensure_category() on whatever slug it was given — so an unmapped source
		 * category, or a typo, silently created a NEW category instead of failing. That
		 * defeats the §9.1 requirement for an explicit mapping table and it is invisible
		 * afterwards: the nav and the front-page blocks are built from the fixed set, so
		 * the posts land in a term no reader can navigate to.
		 *
		 * Reported through the normal error channel, so an unmapped category counts toward
		 * the 2% dry-run stop threshold (§9.2) and the operator sees it BEFORE importing
		 * 2,000 posts. This is precisely what "Fix the mapping and run again" refers to.
		 */
		$known = pgds_category_slugs();
		if ( ! in_array( (string) $rec['primary_cat'], $known, true ) ) {
			return sprintf( 'unknown primary_cat "%s" (not in the §4.1 category set — add a mapping)', (string) $rec['primary_cat'] );
		}
		foreach ( (array) ( $rec['cats'] ?? array() ) as $cslug ) {
			if ( ! in_array( (string) $cslug, $known, true ) ) {
				return sprintf( 'unknown category "%s" (not in the §4.1 category set — add a mapping)', (string) $cslug );
			}
		}

		return '';
	}

	/**
	 * Find a post by source_id.
	 *
	 * @param string $source_id Source ID.
	 * @return int|null
	 */
	private function find_by_source_id( $source_id ) {
		$q = new WP_Query(
			array(
				'post_type'      => 'post',
				'post_status'    => 'any',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'meta_key'       => '_pgds_source_id',
				'meta_value'     => (string) $source_id,
			)
		);
		return $q->posts ? (int) $q->posts[0] : null;
	}

	/**
	 * Create a post with meta, categories, and a featured image.
	 *
	 * @param array $rec Record.
	 * @return int|WP_Error
	 */
	private function create_post( $rec ) {
		// Category.
		$cat_ids = array();
		$primary_id = 0;
		if ( ! empty( $rec['primary_cat'] ) ) {
			$primary_id = pgds_ensure_category( $rec['primary_cat'], $rec['primary_cat'] );
			if ( $primary_id ) {
				$cat_ids[] = $primary_id;
			}
		}
		foreach ( (array) ( $rec['cats'] ?? array() ) as $cslug ) {
			$cid = pgds_ensure_category( $cslug, $cslug );
			if ( $cid ) {
				$cat_ids[] = $cid;
			}
		}

		$author_id = 0;
		if ( ! empty( $rec['author'] ) ) {
			$user = get_user_by( 'login', $rec['author'] ) ?: get_user_by( 'email', $rec['author'] );
			$author_id = $user ? $user->ID : 0;
		}

		$postarr = array(
			'post_title'   => wp_strip_all_tags( $rec['title'] ),
			'post_name'    => $rec['slug'] ?? sanitize_title( $rec['title'] ),
			'post_content' => $this->clean_body( $rec['body_html'] ?? '' ),
			'post_excerpt' => $rec['sapo'] ?? '',
			'post_status'  => 'publish',
			'post_type'    => 'post',
			'post_date'    => $rec['published_at'] ?? current_time( 'mysql' ),
			'post_author'  => $author_id,
			'post_category' => array_unique( $cat_ids ),
		);

		$post_id = wp_insert_post( $postarr, true );
		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		// Meta.
		update_post_meta( $post_id, '_pgds_source_id', (string) $rec['source_id'] );
		if ( ! empty( $rec['sapo'] ) ) {
			update_post_meta( $post_id, '_pgds_sapo', $rec['sapo'] );
		}
		if ( $primary_id ) {
			update_post_meta( $post_id, '_pgds_primary_cat', $primary_id );
		}
		if ( ! empty( $rec['source'] ) ) {
			update_post_meta( $post_id, '_pgds_source', $rec['source'] );
		}
		if ( ! empty( $rec['old_url'] ) ) {
			update_post_meta( $post_id, '_pgds_old_url', $rec['old_url'] );
		}
		if ( ! empty( $rec['youtube_url'] ) ) {
			$vid = pgds_extract_youtube_id( $rec['youtube_url'] );
			if ( $vid ) {
				update_post_meta( $post_id, '_pgds_youtube_id', $vid );
			}
		}

		// Tags.
		if ( ! empty( $rec['tags'] ) ) {
			wp_set_post_tags( $post_id, (array) $rec['tags'], false );
		}

		/*
		 * Media (§9.1, §13).
		 *
		 * Failures are RECORDED, not swallowed. sideload_featured() previously returned
		 * void on both its error paths, so a media URL that 404'd left the post created
		 * with no image and no trace: create_post() reported success, the import's error
		 * rate stayed at 0%, and §13's gate — "Media failure rate < 2%, failure list
		 * reviewed" — had nothing to review. On a 25-40 GB migration that is the
		 * difference between knowing 30 images are missing and discovering it from a
		 * reader.
		 *
		 * They are collected separately from record errors on purpose: a post whose photo
		 * failed still imported correctly, so it must not count as a failed RECORD (that
		 * would trip the §9.2 2% stop threshold for a recoverable problem). §13 tracks the
		 * two rates independently, and so does the summary.
		 */
		if ( ! empty( $rec['featured_image_url'] ) ) {
			$err = $this->sideload_featured( $post_id, $rec['featured_image_url'] );
			if ( $err ) {
				$this->media_failures[] = sprintf( 'post %d (%s) featured: %s — %s', $post_id, $rec['source_id'], $rec['featured_image_url'], $err );
			}
		}

		/*
		 * Gallery. Documented in this file's own schema block since the first version
		 * ("gallery (url[])") and never read: every gallery image in the source data was
		 * silently dropped. Attached to the post (not set as the thumbnail) so
		 * [gallery] shortcodes and the media library both resolve, which is what a
		 * photo-story post needs.
		 */
		foreach ( (array) ( $rec['gallery'] ?? array() ) as $gurl ) {
			if ( ! $gurl ) {
				continue;
			}
			$res = $this->sideload_attachment( $post_id, (string) $gurl );
			// Returns the attachment ID (int) on success, an error string on failure.
			if ( ! is_int( $res ) ) {
				$this->media_failures[] = sprintf( 'post %d (%s) gallery: %s — %s', $post_id, $rec['source_id'], $gurl, (string) $res );
			}
		}

		return $post_id;
	}

	/**
	 * Clean article-body HTML (remove inline styles and stray Word fonts).
	 *
	 * @param string $html HTML.
	 * @return string
	 */
	private function clean_body( $html ) {
		if ( '' === $html ) {
			return '';
		}
		$html = preg_replace( '/<(font|o:p)[^>]*>/i', '', $html );
		$html = preg_replace( '#</(font|o:p)>#i', '', $html );
		$html = preg_replace( '/\sstyle="[^"]*"/i', '', $html );
		$html = preg_replace( '/\sclass="Mso[^"]*"/i', '', $html );
		return wp_kses_post( $html );
	}

	/**
	 * Download an image and attach it to a post.
	 *
	 * Returns an error STRING rather than void so the caller can record it: §13 gates on
	 * "Media failure rate < 2%, failure list reviewed", which is impossible if a failed
	 * download is indistinguishable from a post that had no image.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $url     Image URL.
	 * @return int|string Attachment ID on success, error message on failure.
	 */
	private function sideload_attachment( $post_id, $url ) {
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		if ( ! wp_http_validate_url( $url ) ) {
			return 'not a valid http(s) URL';
		}

		$tmp = download_url( $url );
		if ( is_wp_error( $tmp ) ) {
			return 'download failed: ' . $tmp->get_error_message();
		}

		$name = basename( (string) wp_parse_url( $url, PHP_URL_PATH ) );
		if ( '' === $name ) {
			// media_handle_sideload() rejects an empty name; a URL like /photo/?id=1 has no
			// basename at all, which is common in legacy CMS media routes.
			$name = 'pgds-import-' . wp_generate_uuid4() . '.jpg';
		}

		$att_id = media_handle_sideload(
			array(
				'name'     => $name,
				'tmp_name' => $tmp,
			),
			$post_id
		);
		if ( is_wp_error( $att_id ) ) {
			// download_url() created the temp file; media_handle_sideload() only cleans it
			// up on success, so a failure here leaks it into /tmp on a 25-40 GB import.
			if ( file_exists( $tmp ) ) {
				wp_delete_file( $tmp );
			}
			return 'sideload failed: ' . $att_id->get_error_message();
		}

		return (int) $att_id;
	}

	/**
	 * Download an image and set it as the post's featured image.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $url     Image URL.
	 * @return string Empty on success, error message on failure.
	 */
	private function sideload_featured( $post_id, $url ) {
		$res = $this->sideload_attachment( $post_id, $url );
		if ( ! is_int( $res ) ) {
			return (string) $res;
		}
		set_post_thumbnail( $post_id, $res );
		return '';
	}
}

// Register each subcommand with a hyphenated name (WP-CLI does not convert _ to -).
// Match the commands in the proposal: import, media-variants, build-redirects, yt-sync.
WP_CLI::add_command( 'pgds import', array( 'PGDS_CLI_Command', 'import' ) );
WP_CLI::add_command( 'pgds media-variants', array( 'PGDS_CLI_Command', 'media_variants' ) );
WP_CLI::add_command( 'pgds build-redirects', array( 'PGDS_CLI_Command', 'build_redirects' ) );
WP_CLI::add_command( 'pgds yt-sync', array( 'PGDS_CLI_Command', 'yt_sync' ) );
