<?php
/**
 * WP-CLI: import 2.000 bai (idempotent), sinh bien the anh, build redirects.
 * Chi nap khi chay duoi WP-CLI.
 *
 * Lenh (proposal §9.2):
 *   wp pgds import --file=data.json --batch=200 --dry-run
 *   wp pgds import --file=data.json --batch=200
 *   wp pgds media-variants --regenerate
 *   wp pgds build-redirects --out=/etc/nginx/redirects.map
 *
 * Schema data.json (mang object):
 *   source_id (bat buoc, khoa chong trung), title, slug, sapo, body_html,
 *   primary_cat (slug), cats (slug[]), tags (string[]), author (login/email),
 *   published_at (Y-m-d H:i:s), featured_image_url, gallery (url[]),
 *   youtube_url, source, old_url (de build redirect)
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
 * Lenh PGDS.
 */
class PGDS_CLI_Command {

	/**
	 * Import bai tu file JSON. Idempotent theo _pgds_source_id.
	 *
	 * ## OPTIONS
	 *
	 * --file=<path>
	 * : Duong dan file JSON.
	 *
	 * [--batch=<n>]
	 * : So bai moi lo. Mac dinh 200.
	 *
	 * [--dry-run]
	 * : Chi validate, khong ghi DB. Nguong dung: loi > 2%.
	 *
	 * @param array $args       Positional.
	 * @param array $assoc_args Flags.
	 */
	public function import( $args, $assoc_args ) {
		$file    = $assoc_args['file'] ?? '';
		$batch   = (int) ( $assoc_args['batch'] ?? 200 );
		$dry_run = isset( $assoc_args['dry-run'] );

		if ( ! $file || ! is_readable( $file ) ) {
			WP_CLI::error( "Khong doc duoc file: {$file}" );
		}

		$raw  = file_get_contents( $file );
		$data = json_decode( $raw, true );
		if ( ! is_array( $data ) ) {
			WP_CLI::error( 'JSON khong hop le hoac khong phai mang.' );
		}

		$total   = count( $data );
		$errors  = array();
		$created = 0;
		$skipped = 0;
		$logfile = trailingslashit( sys_get_temp_dir() ) . 'pgds-import-' . gmdate( 'Ymd-His' ) . '.log';

		WP_CLI::log( sprintf( '%s %d ban ghi (batch=%d)%s', $dry_run ? '[DRY-RUN]' : '[IMPORT]', $total, $batch, $dry_run ? '' : '' ) );

		$progress = \WP_CLI\Utils\make_progress_bar( 'Đang xử lý', $total );

		foreach ( $data as $i => $rec ) {
			$err = $this->validate_record( $rec );
			if ( $err ) {
				$errors[] = "#{$i}: {$err}";
				$progress->tick();
				continue;
			}

			// Idempotent: da ton tai?
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
				$created++; // dem nhu se tao
			}

			$progress->tick();

			// Nghi giua batch de khong day 2GB RAM vao swap.
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
		WP_CLI::log( sprintf( 'Tạo: %d | Bỏ qua (đã có): %d | Lỗi: %d/%d (%.2f%%)', $created, $skipped, count( $errors ), $total, $fail_rate * 100 ) );
		WP_CLI::log( "Log lỗi: {$logfile}" );

		// Nguong dung 2% (proposal §9.2).
		if ( $fail_rate > 0.02 ) {
			WP_CLI::error( sprintf( 'Tỉ lệ lỗi %.2f%% > 2%% — DỪNG. Sửa mapping rồi chạy lại. Không import tiếp.', $fail_rate * 100 ) );
		}

		if ( $dry_run ) {
			WP_CLI::success( 'Dry-run đạt. Có thể chạy import thật.' );
		} else {
			WP_CLI::success( "Import xong. Đã tạo {$created} bài." );
		}
	}

	/**
	 * Regenerate bien the anh + WebP.
	 *
	 * ## OPTIONS
	 *
	 * [--regenerate]
	 * : Regenerate toan bo attachment.
	 */
	public function media_variants( $args, $assoc_args ) {
		WP_CLI::log( 'Chạy nice -n 19 + giảm pm.max_children xuống 2 trong lúc này (proposal §9.3).' );
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
		WP_CLI::success( 'Regenerate xong ' . count( $ids ) . ' ảnh.' );
	}

	/**
	 * Sinh redirects.map cho nginx tu meta _pgds_old_url.
	 *
	 * ## OPTIONS
	 *
	 * --out=<path>
	 * : File dich (vd /etc/nginx/redirects.map).
	 */
	public function build_redirects( $args, $assoc_args ) {
		$out = $assoc_args['out'] ?? '';
		if ( ! $out ) {
			WP_CLI::error( 'Thiếu --out=<path>' );
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

		$lines = array();
		foreach ( $q->posts as $p ) {
			$old = get_post_meta( $p->ID, '_pgds_old_url', true );
			if ( ! $old ) {
				continue;
			}
			// Chi lay path phan tuong doi.
			$old_path = wp_parse_url( $old, PHP_URL_PATH );
			if ( ! $old_path ) {
				$old_path = $old;
			}
			$new = get_permalink( $p );
			$lines[] = sprintf( '%s   %s;', $old_path, $new );
		}

		$header  = "# Sinh boi: wp pgds build-redirects — " . gmdate( 'c' ) . "\n";
		$header .= "# " . count( $lines ) . " redirect. Sau khi cap nhat: nginx -t && systemctl reload nginx\n";
		file_put_contents( $out, $header . implode( "\n", $lines ) . "\n" );

		WP_CLI::success( sprintf( 'Đã ghi %d redirect vào %s', count( $lines ), $out ) );
	}

	/* ----------------------- helper ----------------------- */

	/**
	 * Validate ban ghi. Tra ve chuoi loi hoac '' neu OK.
	 *
	 * @param mixed $rec Record.
	 * @return string
	 */
	private function validate_record( $rec ) {
		if ( ! is_array( $rec ) ) {
			return 'không phải object';
		}
		if ( empty( $rec['source_id'] ) ) {
			return 'thiếu source_id';
		}
		if ( empty( $rec['title'] ) ) {
			return 'thiếu title';
		}
		if ( empty( $rec['primary_cat'] ) ) {
			return 'thiếu primary_cat';
		}
		return '';
	}

	/**
	 * Tim post theo source_id.
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
	 * Tao post + meta + category + featured image.
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
	 * Lam sach HTML than bai (bo inline style, font rac tu Word).
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
	 * Tai anh dai dien ve va gan.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $url     URL anh.
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

WP_CLI::add_command( 'pgds', 'PGDS_CLI_Command' );
