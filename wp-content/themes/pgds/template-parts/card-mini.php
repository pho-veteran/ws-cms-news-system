<?php
/**
 * Mini card - small thumb + title (three-category mini, compact list).
 *
 * @param array $args { post: WP_Post, tag: 'h5'|'h4' }
 * @package pgds
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$p = $args['post'] ?? null;
if ( ! $p instanceof WP_Post ) {
	return;
}
$tag = $args['tag'] ?? 'h5';
$url = get_permalink( $p );
?>
<div class="pgds-mini">
	<a class="pgds-mini__media" href="<?php echo esc_url( $url ); ?>" tabindex="-1" aria-hidden="true">
		<?php pgds_art( $p, 'pgds-mini', 'pgds-ratio-mini' ); ?>
	</a>
	<<?php echo esc_html( $tag ); ?>>
		<a href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( get_the_title( $p ) ); ?></a>
	</<?php echo esc_html( $tag ); ?>>
</div>
