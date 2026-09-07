<?php
/**
 * E-magazine detail layout.
 *
 * @package pgds
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$post_id = get_the_ID();
$term    = pgds_primary_cat( $post_id );
?>

<main id="pgds-main" class="pgds-ema" role="main">
	<header class="pgds-ema-head">
		<div class="pgds-wrap pgds-wrap--narrow">
			<h1 class="pgds-ema-title"><?php the_title(); ?></h1>
			<?php $sapo = pgds_has_editorial_sapo( $post_id ) ? pgds_sapo( $post_id ) : ''; ?>
			<?php if ( $sapo ) : ?>
				<p class="pgds-ema-sapo"><?php echo esc_html( $sapo ); ?></p>
			<?php endif; ?>
		</div>
	</header>

	<?php if ( has_post_thumbnail() ) : ?>
		<div class="pgds-ema-cover">
			<?php pgds_art( $post_id, 'full', 'pgds-ratio-16-9', true ); ?>
			<?php $caption = get_the_post_thumbnail_caption(); ?>
			<?php if ( $caption ) : ?>
				<div class="pgds-ema-cover-cap"><?php echo esc_html( $caption ); ?></div>
			<?php endif; ?>
		</div>
	<?php endif; ?>

	<div class="pgds-wrap pgds-wrap--narrow">
		<div class="pgds-ema-body">
			<?php the_content(); ?>
		</div>

		<div class="pgds-ema-author-end">
			<p class="pgds-ema-author-end__name"><?php echo esc_html( pgds_display_author( get_post() ) ); ?></p>
		</div>
	</div>

	<div class="pgds-ema-comments">
		<div class="pgds-wrap pgds-wrap--narrow">
			<?php comments_template(); ?>
		</div>
	</div>

	<?php
	$related = $term instanceof WP_Term
		? get_posts(
			array(
				'category'       => $term->term_id,
				'posts_per_page' => 4,
				'post__not_in'   => array( $post_id ),
				'post_status'    => 'publish',
			)
		)
		: array();
	?>
	<?php if ( $related ) : ?>
		<div class="pgds-ema-more">
			<div class="pgds-wrap">
				<div class="pgds-ema-more__head">
					<h2 class="pgds-ema-more__title"><?php esc_html_e( 'Cùng chuyên mục', 'pgds' ); ?></h2>
					<?php if ( $term instanceof WP_Term ) : ?>
						<a href="<?php echo esc_url( get_term_link( $term ) ); ?>" class="pgds-ema-more__link"><?php esc_html_e( 'Xem thêm ›', 'pgds' ); ?></a>
					<?php endif; ?>
				</div>
				<div class="pgds-ema-more__grid">
					<?php foreach ( $related as $related_post ) : ?>
						<?php get_template_part( 'template-parts/card-mini', null, array( 'post' => $related_post, 'hide_excerpt' => true ) ); ?>
					<?php endforeach; ?>
				</div>
			</div>
		</div>
	<?php endif; ?>
</main>
