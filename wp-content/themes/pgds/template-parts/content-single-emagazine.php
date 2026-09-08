<?php
/**
 * E-magazine long-form detail layout.
 *
 * @package pgds
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$post_id        = get_the_ID();
$sapo           = pgds_has_editorial_sapo( $post_id ) ? pgds_sapo( $post_id ) : '';
$display_author = pgds_display_author( get_post() );
$credit         = trim( (string) get_post_meta( $post_id, '_pgds_source', true ) );
$more           = pgds_emagazine_more_posts( $post_id );
$has_more       = ! empty( $more['emagazine'] ) || ! empty( $more['latest'] );
?>

<main id="pgds-main" class="pgds-emagazine" role="main">
	<article <?php post_class( 'pgds-emagazine__article' ); ?>>
		<header class="pgds-emagazine__head pgds-emagazine__measure">
			<h1 class="pgds-emagazine__title"><?php the_title(); ?></h1>
			<?php if ( $sapo ) : ?>
				<p class="pgds-emagazine__sapo"><?php echo esc_html( $sapo ); ?></p>
			<?php endif; ?>
		</header>

		<?php if ( has_post_thumbnail() ) : ?>
			<figure class="pgds-emagazine__cover">
				<?php
				the_post_thumbnail(
					'full',
					array(
						'fetchpriority' => 'high',
						'sizes'         => '100vw',
					)
				);
				?>
				<?php $caption = get_the_post_thumbnail_caption(); ?>
				<?php if ( $caption ) : ?>
					<figcaption class="pgds-wrap"><?php echo esc_html( $caption ); ?></figcaption>
				<?php endif; ?>
			</figure>
		<?php endif; ?>

		<div class="pgds-emagazine__body pgds-emagazine__measure">
			<?php the_content(); ?>
		</div>

		<?php if ( $display_author || $credit ) : ?>
			<footer class="pgds-emagazine__byline pgds-emagazine__measure">
				<?php if ( $display_author ) : ?>
					<div class="pgds-emagazine__author"><?php echo esc_html( $display_author ); ?></div>
				<?php endif; ?>
				<?php if ( $credit ) : ?>
					<div class="pgds-emagazine__credit"><?php echo esc_html( $credit ); ?></div>
				<?php endif; ?>
			</footer>
		<?php endif; ?>
	</article>

	<?php if ( ! post_password_required() && ( comments_open() || have_comments() ) ) : ?>
		<section class="pgds-emagazine__comments">
			<div class="pgds-emagazine__measure">
				<?php comments_template(); ?>
			</div>
		</section>
	<?php endif; ?>

	<?php if ( $has_more ) : ?>
		<section class="pgds-emagazine__more pgds-wrap" aria-labelledby="pgds-emagazine-more-title">
			<div class="pgds-emagazine__more-head">
				<h2 id="pgds-emagazine-more-title"><?php esc_html_e( 'Đọc thêm', 'pgds' ); ?></h2>
				<?php $emagazine_term = pgds_category_term( 'emagazine' ); ?>
				<?php $emagazine_url = $emagazine_term instanceof WP_Term ? get_term_link( $emagazine_term ) : new WP_Error(); ?>
				<?php if ( ! is_wp_error( $emagazine_url ) ) : ?>
					<a href="<?php echo esc_url( $emagazine_url ); ?>"><?php esc_html_e( 'Xem tất cả ›', 'pgds' ); ?></a>
				<?php endif; ?>
			</div>

			<?php if ( ! empty( $more['emagazine'] ) ) : ?>
				<div class="pgds-emagazine__more-grid">
					<?php foreach ( $more['emagazine'] as $related_post ) : ?>
						<article class="pgds-emagazine-card">
							<a class="pgds-emagazine-card__media" href="<?php echo esc_url( get_permalink( $related_post ) ); ?>" tabindex="-1" aria-hidden="true">
								<?php pgds_art( $related_post, 'pgds-card', 'pgds-ratio-card' ); ?>
							</a>
							<span class="pgds-emagazine-card__category"><?php esc_html_e( 'E-magazine', 'pgds' ); ?></span>
							<h3><a href="<?php echo esc_url( get_permalink( $related_post ) ); ?>"><?php echo esc_html( get_the_title( $related_post ) ); ?></a></h3>
							<div class="pgds-emagazine-card__meta"><?php echo esc_html( pgds_reader_time_ago( $related_post ) ); ?></div>
						</article>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>

			<?php if ( ! empty( $more['latest'] ) ) : ?>
				<ul class="pgds-emagazine__more-strip">
					<?php foreach ( $more['latest'] as $latest_post ) : ?>
						<?php $latest_term = pgds_primary_cat( $latest_post ); ?>
						<li>
							<a class="pgds-emagazine__strip-media" href="<?php echo esc_url( get_permalink( $latest_post ) ); ?>" tabindex="-1" aria-hidden="true">
								<?php pgds_art( $latest_post, 'pgds-thumb', 'pgds-ratio-card' ); ?>
							</a>
							<div>
								<h3><a href="<?php echo esc_url( get_permalink( $latest_post ) ); ?>"><?php echo esc_html( get_the_title( $latest_post ) ); ?></a></h3>
								<div class="pgds-emagazine-card__meta">
									<?php if ( $latest_term instanceof WP_Term ) : ?>
										<?php echo esc_html( pgds_category_display_label( $latest_term->slug, $latest_term->name ) ); ?> ·
									<?php endif; ?>
									<?php echo esc_html( pgds_reader_time_ago( $latest_post ) ); ?>
								</div>
							</div>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</section>
	<?php endif; ?>
</main>
