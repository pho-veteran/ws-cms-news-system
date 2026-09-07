<?php
/**
 * Secondary card - used for the 3-card grid and other secondary cards.
 *
 * @param array $args {
 *   post:    WP_Post,
 *   variant: 'full'|'compact'  (full = image+title+sapo+meta; compact = image+title),
 *   bordered:bool  (adds a divider on the right)
 * }
 * @package pgds
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$p = $args['post'] ?? null;
if ( ! $p instanceof WP_Post ) {
	return;
}
$variant  = $args['variant'] ?? 'full';
$variant  = in_array( $variant, array( 'full', 'compact' ), true ) ? $variant : 'full';
$tag      = $args['tag'] ?? 'h3';
$tag      = in_array( $tag, array( 'h2', 'h3', 'h4', 'h5', 'h6' ), true ) ? $tag : 'h3';
$bordered = ! empty( $args['bordered'] );
$url      = get_permalink( $p );
$vid      = pgds_video_id( $p );

$classes = array( 'pgds-card' );
if ( $bordered ) {
	$classes[] = 'pgds-card--bordered';
}
if ( 'compact' === $variant ) {
	$classes[] = 'pgds-card--compact';
}
?>
<article class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>">
	<a class="pgds-card__media" href="<?php echo esc_url( $url ); ?>" tabindex="-1" aria-hidden="true">
		<?php
		pgds_art( $p, 'pgds-card', 'pgds-ratio-card' );
		if ( $vid ) {
			echo '<span class="pgds-play">';
			pgds_play_svg();
			echo '</span>';
		}
		?>
	</a>
	<<?php echo esc_html( $tag ); ?> class="pgds-card__title">
		<a href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( get_the_title( $p ) ); ?></a>
	</<?php echo esc_html( $tag ); ?>>
	<?php if ( 'full' === $variant ) : ?>
		<p class="pgds-card__sapo"><?php echo esc_html( wp_trim_words( pgds_sapo( $p ), 26 ) ); ?></p>
		<div class="pgds-card__meta"><?php echo esc_html( pgds_time_ago( $p ) ); ?></div>
	<?php endif; ?>
</article>
