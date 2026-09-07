<?php
/**
 * Standard Article detail layout.
 *
 * @package pgds
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$post_id = get_the_ID();
$term    = pgds_primary_cat( $post_id );
$source  = get_post_meta( $post_id, '_pgds_source', true );
?>

<main id="pgds-main" class="pgds-wrap" role="main">
	<?php
	$breadcrumbs = array(
		array(
			'label' => __( 'Trang chủ', 'pgds' ),
			'url'   => home_url( '/' ),
		),
	);
	if ( $term instanceof WP_Term ) {
		$term_url = get_term_link( $term );
		if ( ! is_wp_error( $term_url ) ) {
			$breadcrumbs[] = array(
				'label' => $term->name,
				'url'   => $term_url,
			);
		}
	}
	$breadcrumbs[] = array( 'label' => get_the_title() );
	pgds_breadcrumbs( $breadcrumbs );
	?>
	<div class="pgds-content-grid">
		<article <?php post_class( 'pgds-article' ); ?>>
			<header class="pgds-article__header">
				<?php pgds_cat_label( $post_id ); ?>
				<h1 class="pgds-article__title"><?php the_title(); ?></h1>

				<?php $sapo = pgds_has_editorial_sapo( $post_id ) ? pgds_sapo( $post_id ) : ''; ?>
				<?php if ( $sapo ) : ?>
					<p class="pgds-article__sapo"><?php echo esc_html( $sapo ); ?></p>
				<?php endif; ?>

				<div class="pgds-article__meta">
					<span><?php echo esc_html( get_the_author() ); ?></span>
					<span><?php echo esc_html( get_the_date( 'd/m/Y H:i' ) ); ?></span>
					<span>
						<?php
						printf(
							/* translators: %s: estimated reading time in minutes */
							esc_html__( '%s phút đọc', 'pgds' ),
							esc_html( number_format_i18n( pgds_reading_time( $post_id ) ) )
						);
						?>
					</span>
				</div>
			</header>

			<?php if ( has_post_thumbnail() ) : ?>
				<figure class="pgds-article__figure">
					<?php the_post_thumbnail( 'pgds-lead', array( 'fetchpriority' => 'high' ) ); ?>
					<?php $caption = get_the_post_thumbnail_caption(); ?>
					<?php if ( $caption ) : ?>
						<figcaption><?php echo esc_html( $caption ); ?></figcaption>
					<?php endif; ?>
				</figure>
			<?php endif; ?>

			<div class="pgds-article__body">
				<?php the_content(); ?>
			</div>

			<p class="pgds-article__author"><?php echo esc_html( pgds_display_author( get_post() ) ); ?></p>

			<?php if ( $source ) : ?>
				<p class="pgds-article__source"><?php printf( esc_html__( 'Nguồn: %s', 'pgds' ), esc_html( $source ) ); ?></p>
			<?php endif; ?>

			<?php if ( has_tag() ) : ?>
				<div class="pgds-article__tags">
					<?php foreach ( get_the_tags() as $tag ) : ?>
						<a href="<?php echo esc_url( get_tag_link( $tag ) ); ?>">#<?php echo esc_html( $tag->name ); ?></a>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>

			<?php comments_template(); ?>

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
				<section class="pgds-section pgds-section--spaced" aria-labelledby="pgds-related-title">
					<div class="pgds-cat-head"><h2 id="pgds-related-title"><?php esc_html_e( 'Cùng chuyên mục', 'pgds' ); ?></h2></div>
					<div class="pgds-grid-3">
						<?php foreach ( $related as $related_post ) : ?>
							<?php get_template_part( 'template-parts/card-secondary', null, array( 'post' => $related_post, 'variant' => 'full', 'bordered' => true ) ); ?>
						<?php endforeach; ?>
					</div>
				</section>
			<?php endif; ?>

			<nav class="pgds-post-nav" aria-label="<?php esc_attr_e( 'Điều hướng bài viết', 'pgds' ); ?>">
				<?php $previous = get_previous_post(); ?>
				<?php $next = get_next_post(); ?>
				<?php if ( $previous ) : ?>
					<a href="<?php echo esc_url( get_permalink( $previous ) ); ?>">
						<span class="pgds-post-nav__dir"><?php esc_html_e( 'Bài trước', 'pgds' ); ?></span>
						<span class="pgds-post-nav__title"><?php echo esc_html( get_the_title( $previous ) ); ?></span>
					</a>
				<?php endif; ?>
				<?php if ( $next ) : ?>
					<a class="pgds-prevnext__next" href="<?php echo esc_url( get_permalink( $next ) ); ?>">
						<span class="pgds-post-nav__dir"><?php esc_html_e( 'Bài sau', 'pgds' ); ?></span>
						<span class="pgds-post-nav__title"><?php echo esc_html( get_the_title( $next ) ); ?></span>
					</a>
				<?php endif; ?>
			</nav>
		</article>

		<?php get_sidebar(); ?>
	</div>
</main>
