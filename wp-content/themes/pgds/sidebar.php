<?php
/**
 * Sidebar dung chung (single, category, archive).
 * Popular + Lich Van Nien.
 *
 * @package pgds
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$popular = pgds_query_popular( 5 );
$lunar   = get_posts(
	array(
		'post_type'      => 'pgds_lunar_note',
		'posts_per_page' => 1,
		'post_status'    => 'publish',
	)
);
?>
<aside class="pgds-sidebar" aria-label="<?php esc_attr_e( 'Thông tin bên lề', 'pgds' ); ?>">
	<?php
	get_template_part( 'template-parts/sidebar-popular', null, array( 'posts' => $popular ) );
	get_template_part( 'template-parts/sidebar-lunar', null, array( 'post' => $lunar[0] ?? null ) );
	?>
</aside>
