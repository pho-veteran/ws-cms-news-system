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
		file_put_contents( $logfile, implode( "\n", $errors ) );

		WP_CLI::log( '----------------------------------------' );
		WP_CLI::log( sprintf( 'Created: %d | Skipped (existing): %d | Errors: %d/%d (%.2f%%)', $created, $skipped, count( $errors ), $total, $fail_rate * 100 ) );
		WP_CLI::log( "Error log: {$logfile}" );

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
		$chunks = array_chunk( $vids, 50 );
		foreach ( $chunks as $chunk ) {
			$url  = add_query_arg(
				array(
					'part' => 'contentDetails',
					'id'   => implode( ',', $chunk ),
					'key'  => $api_key,
				),
				'https://www.googleapis.com/youtube/v3/videos'
			);
			$resp = wp_remote_get( $url, array( 'timeout' => 15 ) );
			if ( is_wp_error( $resp ) ) {
				WP_CLI::warning( 'Data API error: ' . $resp->get_error_message() );
				continue;
			}
			$body = json_decode( wp_remote_retrieve_body( $resp ), true );
			foreach ( ( $body['items'] ?? array() ) as $item ) {
				$vid = $item['id'] ?? '';
				$iso = $item['contentDetails']['duration'] ?? '';
				if ( $vid && $iso && isset( $ids[ $vid ] ) ) {
					update_post_meta( $ids[ $vid ], '_pgds_youtube_dur', $this->iso8601_to_seconds( $iso ) );
				}
			}
		}
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
	 * Regenerate image variants + WebP.
	 *
	 * ## OPTIONS
	 *
	 * [--regenerate]
	 * : Regenerate all attachments.
	 */
	public function media_variants( $args, $assoc_args ) {
		WP_CLI::log( 'Run with nice -n 19 and reduce pm.max_children to 2 for this operation (proposal §9.3).' );
		$q = new WP_Query(
			array(
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'post_mime_type' => 'image',
				'posts_per_page' => -1,
				'fields'         => 'ids',
			)
		);
		$ids = $q->posts;
		$progress = \WP_CLI\Utils\make_progress_bar( 'Regenerate', count( $ids ) );
		foreach ( $ids as $id ) {
			$file = get_attached_file( $id );
			if ( $file && file_exists( $file ) ) {
				$meta = wp_generate_attachment_metadata( $id, $file );
				wp_update_attachment_metadata( $id, $meta );
			}
			$progress->tick();
		}
		$progress->finish();
		WP_CLI::success( 'Regeneration complete for ' . count( $ids ) . ' images.' );
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

		// Featured image (sideload).
		if ( ! empty( $rec['featured_image_url'] ) ) {
			$this->sideload_featured( $post_id, $rec['featured_image_url'] );
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
	 * Download and attach a featured image.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $url     Image URL.
	 */
	private function sideload_featured( $post_id, $url ) {
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$tmp = download_url( $url );
		if ( is_wp_error( $tmp ) ) {
			return;
		}
		$file_array = array(
			'name'     => basename( wp_parse_url( $url, PHP_URL_PATH ) ),
			'tmp_name' => $tmp,
		);
		$att_id = media_handle_sideload( $file_array, $post_id );
		if ( is_wp_error( $att_id ) ) {
			@unlink( $tmp );
			return;
		}
		set_post_thumbnail( $post_id, $att_id );
	}
}

// Register each subcommand with a hyphenated name (WP-CLI does not convert _ to -).
// Match the commands in the proposal: import, media-variants, build-redirects, yt-sync.
WP_CLI::add_command( 'pgds import', array( 'PGDS_CLI_Command', 'import' ) );
WP_CLI::add_command( 'pgds media-variants', array( 'PGDS_CLI_Command', 'media_variants' ) );
WP_CLI::add_command( 'pgds build-redirects', array( 'PGDS_CLI_Command', 'build_redirects' ) );
WP_CLI::add_command( 'pgds yt-sync', array( 'PGDS_CLI_Command', 'yt_sync' ) );
