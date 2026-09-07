<?php
/**
 * Standard Article detail layout.
 *
 * @package pgds
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$post_id        = get_the_ID();
$term           = pgds_primary_cat( $post_id );
$source         = get_post_meta( $post_id, '_pgds_source', true );
$display_author = pgds_display_author( get_post() );
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
				'label' => pgds_category_display_label( $term->slug, $term->name ),
				'url'   => $term_url,
			);
		}
	}
	$breadcrumbs[] = array( 'label' => get_the_title() );
	pgds_breadcrumbs( $breadcrumbs );
	?>
	<div class="pgds-content-grid article-layout">
		<article <?php post_class( 'pgds-article' ); ?>>
			<header class="pgds-article__header article-head">
				<h1 class="pgds-article__title"><?php the_title(); ?></h1>

				<time class="pgds-article__date publish-time" datetime="<?php echo esc_attr( get_the_date( DATE_W3C ) ); ?>">
					<?php echo esc_html( pgds_reader_time_ago( $post_id ) ); ?>
				</time>

				<?php $sapo = pgds_has_editorial_sapo( $post_id ) ? pgds_sapo( $post_id ) : ''; ?>
				<?php if ( $sapo ) : ?>
					<p class="pgds-article__sapo article-sapo"><?php echo esc_html( $sapo ); ?></p>
				<?php endif; ?>
			</header>

			<?php if ( has_post_thumbnail() ) : ?>
				<figure class="pgds-article__figure article-cover">
					<?php the_post_thumbnail( 'pgds-lead', array( 'fetchpriority' => 'high' ) ); ?>
					<?php $caption = get_the_post_thumbnail_caption(); ?>
					<?php if ( $caption ) : ?>
						<figcaption><?php echo esc_html( $caption ); ?></figcaption>
					<?php endif; ?>
				</figure>
			<?php endif; ?>

			<div class="pgds-article__body article-body">
				<?php the_content(); ?>
			</div>

			<?php if ( $display_author ) : ?>
				<p class="pgds-article__author article-author"><?php echo esc_html( $display_author ); ?></p>
			<?php endif; ?>

			<?php if ( $source ) : ?>
				<p class="pgds-article__source"><?php printf( esc_html__( 'Nguồn: %s', 'pgds' ), esc_html( $source ) ); ?></p>
			<?php endif; ?>

			<?php if ( has_tag() ) : ?>
				<div class="pgds-article__tags tag-list">
					<?php foreach ( get_the_tags() as $tag ) : ?>
						<a href="<?php echo esc_url( get_tag_link( $tag ) ); ?>"><?php echo esc_html( $tag->name ); ?></a>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>

			<?php comments_template(); ?>

			<?php
			$related = array();
			if ( $term instanceof WP_Term ) {
				$related = get_posts(
					array(
						'meta_key'       => '_pgds_primary_cat',
						'meta_value'     => $term->term_id,
						'posts_per_page' => 3,
						'post__not_in'   => array( $post_id ),
						'post_status'    => 'publish',
					)
				);
				if ( empty( $related ) ) {
					$related = get_posts(
						array(
							'category__in'   => array( $term->term_id ),
							'posts_per_page' => 3,
							'post__not_in'   => array( $post_id ),
							'post_status'    => 'publish',
						)
					);
				}
			}
			?>
			<?php if ( $related || pgds_is_english_reader_request() ) : ?>
				<section class="pgds-section pgds-section--spaced" aria-labelledby="pgds-related-title" style="margin-top:34px;">
					<div class="related-head"><h2 id="pgds-related-title"><?php esc_html_e( 'Cùng chuyên mục', 'pgds' ); ?></h2></div>
					<?php if ( $related ) : ?>
						<div class="pgds-grid-3 related-grid">
							<?php foreach ( $related as $related_post ) : ?>
								<?php get_template_part(
									'template-parts/card-secondary',
									null,
									array( 'post' => $related_post, 'variant' => 'related', 'tag' => 'h4' )
								); ?>
							<?php endforeach; ?>
						</div>
					<?php else : ?>
						<p><?php esc_html_e( 'Chưa có bài viết liên quan.', 'pgds' ); ?></p>
					<?php endif; ?>
				</section>
			<?php endif; ?>
		</article>

		<?php get_sidebar(); ?>
	</div>
</main>
