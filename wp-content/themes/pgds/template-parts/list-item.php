<?php
/**
 * List item (list-clean) - horizontal listing, image left + content right.
 *
 * @param array $args { post: WP_Post }
 * @package pgds
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$p = $args['post'] ?? null;
if ( ! $p instanceof WP_Post ) {
	return;
}
$url  = get_permalink( $p );
$term = pgds_primary_cat( $p );
?>
<article class="pgds-list__item">
	<a class="pgds-list__media" href="<?php echo esc_url( $url ); ?>" tabindex="-1" aria-hidden="true">
		<?php pgds_art( $p, 'pgds-list', 'pgds-ratio-card' ); ?>
	</a>
	<div class="pgds-list__body">
		<h3 class="pgds-list__title">
			<a href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( get_the_title( $p ) ); ?></a>
		</h3>
		<div class="pgds-list__meta">
			<?php if ( $term ) : ?>
				<span class="cat"><?php echo esc_html( $term->name ); ?></span> ·
			<?php endif; ?>
			<?php echo esc_html( pgds_time_ago( $p ) ); ?>
		</div>
		<p class="pgds-list__sapo"><?php echo esc_html( wp_trim_words( pgds_sapo( $p ), 28 ) ); ?></p>
	</div>
</article>
