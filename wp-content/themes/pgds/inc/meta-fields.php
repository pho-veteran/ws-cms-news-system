<?php
/**
 * Custom meta box (no ACF - proposal §8).
 * 8 fields per proposal §4.3.
 *
 * @package pgds
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Field definitions.
 *
 * @return array
 */
function pgds_meta_fields() {
	return array(
		'_pgds_sapo'         => array(
			'label' => 'Sa-pô (tóm tắt hiển thị ở card + lead)',
			'type'  => 'textarea',
		),
		'_pgds_primary_cat'  => array(
			'label' => 'Chuyên mục chính (term_id) — dùng cho breadcrumb & schema',
			'type'  => 'category',
		),
		'_pgds_youtube_id'   => array(
			'label' => 'YouTube ID (1 video canonical/bài)',
			'type'  => 'text',
		),
		'_pgds_youtube_dur'  => array(
			'label' => 'Thời lượng video (giây) — tự điền qua cron',
			'type'  => 'number',
		),
		'_pgds_is_featured'  => array(
			'label' => 'Đưa lên khối Tin nổi bật (trang chủ)',
			'type'  => 'checkbox',
		),
		'_pgds_feature_rank' => array(
			'label' => 'Thứ tự slot nổi bật (1 = lead, 2–4 = secondary)',
			'type'  => 'number',
		),
		'_pgds_photo_story'  => array(
			'label' => 'Hiển thị ở panel "Tin ảnh"',
			'type'  => 'checkbox',
		),
		'_pgds_source'       => array(
			'label' => 'Nguồn tin',
			'type'  => 'text',
		),
	);
}

/**
 * Register meta for REST + sanitize.
 */
function pgds_register_meta() {
	$types = array(
		'_pgds_sapo'         => 'string',
		'_pgds_primary_cat'  => 'integer',
		'_pgds_youtube_id'   => 'string',
		'_pgds_youtube_dur'  => 'integer',
		// Written by `wp pgds yt-sync` from the YouTube API, never by hand — hence it is
		// registered here (so it is typed and REST-visible) but deliberately absent from
		// pgds_meta_fields(), which drives the editor meta box. An editable field would
		// invite an editor to type a title the next sync silently overwrites.
		'_pgds_youtube_title' => 'string',
		'_pgds_is_featured'  => 'boolean',
		'_pgds_feature_rank' => 'integer',
		'_pgds_photo_story'  => 'boolean',
		'_pgds_source'       => 'string',
	);
	foreach ( $types as $key => $type ) {
		register_post_meta(
			'post',
			$key,
			array(
				'type'          => $type,
				'single'        => true,
				'show_in_rest'  => true,
				'auth_callback' => function () {
					return current_user_can( 'edit_posts' );
				},
			)
		);
	}
}
add_action( 'init', 'pgds_register_meta' );

/**
 * Meta box.
 */
function pgds_add_meta_box() {
	/*
	 * Context is 'normal', NOT 'side'.
	 *
	 * With 'side' the block editor put this box inside `div#metaboxes.hidden`
	 * (`form.metabox-location-side`), which is `display: none`. Walking the DOM from
	 * the box up to <body> showed every ancestor at height 0 until `#wpwrap`:
	 *
	 *   [0] div#pgds_article_meta .postbox            h=0
	 *   [5] div#metaboxes .hidden                     h=0  display=NONE   <-- here
	 *
	 * So all eight editorial fields — sapo, primary category, YouTube ID, duration,
	 * featured, feature rank, photo story, source — were completely unreachable for
	 * anyone using the block editor, which is the default for posts. Every one is a
	 * field §14 expects an editor to set in a one-hour session, and §4.3/§4.4 make the
	 * front page depend on them: no `_pgds_is_featured` means no lead story.
	 *
	 * The block editor only renders 'normal' and 'advanced' meta boxes, in the drawer
	 * below the content. 'side' is silently dropped. This was invisible in testing
	 * until the admin screens were opened in a real browser as a real editor user —
	 * php -l, the front end, and the REST API all looked perfectly healthy.
	 */
	add_meta_box(
		'pgds_article_meta',
		__( 'Thông tin PGDS', 'pgds' ),
		'pgds_render_meta_box',
		'post',
		'normal',
		'high'
	);
}
add_action( 'add_meta_boxes', 'pgds_add_meta_box' );

/**
 * Render meta box.
 *
 * @param WP_Post $post Post.
 */
function pgds_render_meta_box( $post ) {
	wp_nonce_field( 'pgds_meta_save', 'pgds_meta_nonce' );
	echo '<div class="pgds-metabox">';
	foreach ( pgds_meta_fields() as $key => $field ) {
		$value = get_post_meta( $post->ID, $key, true );
		$id    = esc_attr( $key );
		echo '<p style="margin:0 0 12px;">';
		printf( '<label for="%s" style="display:block;font-weight:600;margin-bottom:4px;">%s</label>', $id, esc_html( $field['label'] ) );

		switch ( $field['type'] ) {
			case 'textarea':
				printf(
					'<textarea id="%s" name="%s" rows="3" style="width:100%%;">%s</textarea>',
					$id,
					$id,
					esc_textarea( (string) $value )
				);
				break;

			case 'checkbox':
				printf(
					'<input type="checkbox" id="%s" name="%s" value="1" %s> <span>Bật</span>',
					$id,
					$id,
					checked( $value, '1', false )
				);
				break;

			case 'number':
				printf(
					'<input type="number" id="%s" name="%s" value="%s" style="width:100%%;">',
					$id,
					$id,
					esc_attr( (string) $value )
				);
				break;

			case 'category':
				wp_dropdown_categories(
					array(
						'show_option_none' => '— Chọn chuyên mục chính —',
						'option_none_value' => 0,
						'hierarchical'     => 1,
						'hide_empty'       => 0,
						'name'             => $id,
						'id'               => $id,
						'selected'         => (int) $value,
					)
				);
				break;

			default: // text
				printf(
					'<input type="text" id="%s" name="%s" value="%s" style="width:100%%;">',
					$id,
					$id,
					esc_attr( (string) $value )
				);
		}
		echo '</p>';
	}
	echo '</div>';
}

/**
 * Save meta.
 *
 * @param int $post_id Post ID.
 */
function pgds_save_meta( $post_id ) {
	if ( ! isset( $_POST['pgds_meta_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['pgds_meta_nonce'] ), 'pgds_meta_save' ) ) {
		return;
	}
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	foreach ( pgds_meta_fields() as $key => $field ) {
		switch ( $field['type'] ) {
			case 'checkbox':
				update_post_meta( $post_id, $key, isset( $_POST[ $key ] ) ? '1' : '' );
				break;

			case 'number':
			case 'category':
				$val = isset( $_POST[ $key ] ) ? (int) $_POST[ $key ] : 0;
				update_post_meta( $post_id, $key, $val );
				break;

			case 'textarea':
				$val = isset( $_POST[ $key ] ) ? sanitize_textarea_field( wp_unslash( $_POST[ $key ] ) ) : '';
				update_post_meta( $post_id, $key, $val );
				break;

			default:
				$val = isset( $_POST[ $key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) : '';
				// YouTube ID: extract clean ID from URL if the user pasted the full URL.
				if ( '_pgds_youtube_id' === $key && '' !== $val ) {
					$val = pgds_extract_youtube_id( $val );
				}
				update_post_meta( $post_id, $key, $val );
		}
	}
}
add_action( 'save_post_post', 'pgds_save_meta' );

/**
 * Extract YouTube ID from a URL, or return as-is if already an ID.
 *
 * @param string $input URL or ID.
 * @return string
 */
function pgds_extract_youtube_id( $input ) {
	$input = trim( $input );
	// Already an ID (11 valid characters).
	if ( preg_match( '/^[A-Za-z0-9_-]{11}$/', $input ) ) {
		return $input;
	}
	// youtu.be/ID or youtube.com/watch?v=ID or /embed/ID
	if ( preg_match( '~(?:youtu\.be/|v=|/embed/|/shorts/)([A-Za-z0-9_-]{11})~', $input, $m ) ) {
		return $m[1];
	}
	return '';
}
