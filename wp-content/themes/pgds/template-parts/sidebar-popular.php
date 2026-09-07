<?php
/**
 * Sidebar block "Most read".
 *
 * @param array $args { posts: WP_Post[] }
 * @package pgds
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$posts      = array_values( array_filter( (array) ( $args['posts'] ?? array() ), static fn( $post ) => $post instanceof WP_Post ) );
$heading_id = sanitize_html_class( (string) ( $args['heading_id'] ?? wp_unique_id( 'pgds-popular-' ) ) );
$tag        = $args['heading_tag'] ?? 'h3';
$tag        = in_array( $tag, array( 'h2', 'h3', 'h4', 'h5', 'h6' ), true ) ? $tag : 'h3';
?>
<section class="pgds-side-block side-block block" aria-labelledby="<?php echo esc_attr( $heading_id ); ?>">
	<<?php echo esc_html( $tag ); ?> class="pgds-side-block__title side-title" id="<?php echo esc_attr( $heading_id ); ?>"><?php esc_html_e( 'Đọc nhiều', 'pgds' ); ?></<?php echo esc_html( $tag ); ?>>
	<?php if ( $posts ) : ?>
		<?php foreach ( $posts as $p ) : $url = get_permalink( $p ); ?>
		<div class="pgds-rank rank-item">
			<a class="pgds-rank__media art rank-thumb" href="<?php echo esc_url( $url ); ?>" tabindex="-1" aria-hidden="true">
				<?php pgds_art( $p, 'pgds-rank', 'pgds-ratio-card' ); ?>
			</a>
			<h4>
				<a href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( get_the_title( $p ) ); ?></a>
			</h4>
		</div>
	<?php endforeach; ?>
	<?php else : ?>
		<p class="pgds-side-block__empty"><?php esc_html_e( 'Chưa có bài viết nổi bật.', 'pgds' ); ?></p>
	<?php endif; ?>
</section>
