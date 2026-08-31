<?php
/**
 * Admin UX: cot bai viet (noi bat, video, chuyen muc chinh), bo loc.
 *
 * @package pgds
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Them cot.
 *
 * @param array $cols Cot.
 * @return array
 */
function pgds_admin_columns( $cols ) {
	$new = array();
	foreach ( $cols as $key => $label ) {
		$new[ $key ] = $label;
		if ( 'title' === $key ) {
			$new['pgds_flags'] = __( 'PGDS', 'pgds' );
		}
	}
	return $new;
}
add_filter( 'manage_post_posts_columns', 'pgds_admin_columns' );

/**
 * Noi dung cot.
 *
 * @param string $col     Cot.
 * @param int    $post_id Post ID.
 */
function pgds_admin_column_content( $col, $post_id ) {
	if ( 'pgds_flags' !== $col ) {
		return;
	}
	$flags = array();
	if ( '1' === get_post_meta( $post_id, '_pgds_is_featured', true ) ) {
		$rank    = (int) get_post_meta( $post_id, '_pgds_feature_rank', true );
		$flags[] = '★ Nổi bật' . ( $rank ? " (#{$rank})" : '' );
	}
	if ( '1' === get_post_meta( $post_id, '_pgds_photo_story', true ) ) {
		$flags[] = '📷 Tin ảnh';
	}
	if ( get_post_meta( $post_id, '_pgds_youtube_id', true ) ) {
		$flags[] = '▶ Video';
	}
	echo $flags ? esc_html( implode( ' · ', $flags ) ) : '—';
}
add_action( 'manage_post_posts_custom_column', 'pgds_admin_column_content', 10, 2 );

/**
 * Cho phep sap xep theo feature_rank.
 */
function pgds_admin_sortable( $cols ) {
	$cols['pgds_flags'] = 'pgds_flags';
	return $cols;
}
add_filter( 'manage_edit-post_sortable_columns', 'pgds_admin_sortable' );

/**
 * Bo loc "chi bai noi bat" tren danh sach bai.
 */
function pgds_admin_filter_ui() {
	global $typenow;
	if ( 'post' !== $typenow ) {
		return;
	}
	$current = isset( $_GET['pgds_filter'] ) ? sanitize_key( $_GET['pgds_filter'] ) : '';
	?>
	<select name="pgds_filter">
		<option value=""><?php esc_html_e( '— Lọc PGDS —', 'pgds' ); ?></option>
		<option value="featured" <?php selected( $current, 'featured' ); ?>><?php esc_html_e( 'Tin nổi bật', 'pgds' ); ?></option>
		<option value="photo" <?php selected( $current, 'photo' ); ?>><?php esc_html_e( 'Tin ảnh', 'pgds' ); ?></option>
		<option value="video" <?php selected( $current, 'video' ); ?>><?php esc_html_e( 'Có video', 'pgds' ); ?></option>
	</select>
	<?php
}
add_action( 'restrict_manage_posts', 'pgds_admin_filter_ui' );

/**
 * Ap dung bo loc.
 *
 * @param WP_Query $query Query.
 */
function pgds_admin_filter_apply( $query ) {
	global $pagenow;
	if ( ! is_admin() || 'edit.php' !== $pagenow || ! $query->is_main_query() ) {
		return;
	}
	if ( empty( $_GET['pgds_filter'] ) ) {
		return;
	}
	$filter = sanitize_key( $_GET['pgds_filter'] );
	$map    = array(
		'featured' => '_pgds_is_featured',
		'photo'    => '_pgds_photo_story',
		'video'    => '_pgds_youtube_id',
	);
	if ( isset( $map[ $filter ] ) ) {
		$mq = ( 'video' === $filter )
			? array( array( 'key' => $map[ $filter ], 'compare' => 'EXISTS' ) )
			: array( array( 'key' => $map[ $filter ], 'value' => '1' ) );
		$query->set( 'meta_query', $mq );
	}
}
add_action( 'pre_get_posts', 'pgds_admin_filter_apply' );
