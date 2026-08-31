<?php
/**
 * Card secondary - dung cho grid 3 card va cac the phu.
 *
 * @param array $args {
 *   post:    WP_Post,
 *   variant: 'full'|'compact'  (full = anh+title+sapo+meta; compact = anh+title),
 *   bordered:bool  (them vach ngan ben phai)
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
	<h3 class="pgds-card__title">
		<a href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( get_the_title( $p ) ); ?></a>
	</h3>
	<?php if ( 'full' === $variant ) : ?>
		<p class="pgds-card__sapo"><?php echo esc_html( wp_trim_words( pgds_sapo( $p ), 26 ) ); ?></p>
		<div class="pgds-card__meta"><?php echo esc_html( pgds_time_ago( $p ) ); ?></div>
	<?php endif; ?>
</article>
