<?php
/**
 * Lead card (horizontal) - top story on the front page.
 *
 * @param array $args { post: WP_Post, eager: bool }
 * @package pgds
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$p = $args['post'] ?? null;
if ( ! $p instanceof WP_Post ) {
	return;
}
$eager = ! empty( $args['eager'] );
$url   = get_permalink( $p );
?>
<article class="pgds-lead">
	<a class="pgds-lead__media" href="<?php echo esc_url( $url ); ?>" tabindex="-1" aria-hidden="true">
		<?php pgds_art( $p, 'pgds-lead', 'pgds-ratio-lead', $eager ); ?>
	</a>
	<div class="pgds-lead__body">
		<?php pgds_cat_label( $p ); ?>
		<h2 class="pgds-lead__title">
			<a href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( get_the_title( $p ) ); ?></a>
		</h2>
		<p class="pgds-lead__sapo"><?php echo esc_html( wp_trim_words( pgds_sapo( $p ), 34 ) ); ?></p>
		<div class="pgds-lead__meta"><?php echo esc_html( pgds_time_ago( $p ) ); ?></div>
	</div>
</article>
