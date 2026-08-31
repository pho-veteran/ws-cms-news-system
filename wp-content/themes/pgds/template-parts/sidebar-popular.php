<?php
/**
 * Sidebar block "Doc nhieu nhat".
 *
 * @param array $args { posts: WP_Post[] }
 * @package pgds
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$posts = $args['posts'] ?? array();
if ( empty( $posts ) ) {
	return;
}
?>
<section class="pgds-side-block" aria-labelledby="pgds-popular-title">
	<h3 class="pgds-side-block__title" id="pgds-popular-title"><?php esc_html_e( 'Đọc nhiều nhất', 'pgds' ); ?></h3>
	<?php $i = 0; foreach ( $posts as $p ) : $i++; $url = get_permalink( $p ); ?>
		<div class="pgds-rank">
			<span class="pgds-rank__num" aria-hidden="true"><?php echo esc_html( (string) $i ); ?></span>
			<a class="pgds-rank__media" href="<?php echo esc_url( $url ); ?>" tabindex="-1" aria-hidden="true">
				<?php pgds_art( $p, 'pgds-rank', 'pgds-ratio-card' ); ?>
			</a>
			<h4>
				<a href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( get_the_title( $p ) ); ?></a>
			</h4>
		</div>
	<?php endforeach; ?>
</section>
