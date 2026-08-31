<?php
/**
 * Single post. If it has a canonical video -> facade at the top of the post.
 *
 * @package pgds
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

while ( have_posts() ) :
	the_post();
	$post_id = get_the_ID();
	$term    = pgds_primary_cat( $post_id );
	$vid     = pgds_video_id( $post_id );
	$source  = get_post_meta( $post_id, '_pgds_source', true );
	$dur     = (int) get_post_meta( $post_id, '_pgds_youtube_dur', true );

	pgds_breadcrumb( get_post() );
	?>

	<main id="pgds-main" class="pgds-wrap" role="main">
		<div class="pgds-content-grid">
			<article <?php post_class( 'pgds-article' ); ?>>
				<header class="pgds-article__header">
					<?php pgds_cat_label( $post_id ); ?>
					<h1 class="pgds-article__title"><?php the_title(); ?></h1>

					<?php $sapo = pgds_sapo( $post_id ); ?>
					<?php if ( $sapo ) : ?>
						<p class="pgds-article__sapo"><?php echo esc_html( $sapo ); ?></p>
					<?php endif; ?>

					<div class="pgds-article__meta">
						<span><?php echo esc_html( get_the_author() ); ?></span>
						<span><?php echo esc_html( get_the_date( 'd/m/Y H:i' ) ); ?></span>
						<span><?php printf( esc_html__( '%d phút đọc', 'pgds' ), pgds_reading_time( $post_id ) ); ?></span>
					</div>
				</header>

				<?php if ( $vid ) : ?>
					<?php
					$poster = get_post_meta( $post_id, '_pgds_youtube_poster', true );
					get_template_part(
						'template-parts/video-facade',
						null,
						array(
							'video_id' => $vid,
							'poster'   => $poster ? $poster : '',
							'dur'      => $dur,
							'title'    => get_the_title(),
							'caption'  => $source ? ( 'Nguồn: ' . $source ) : '',
						)
					);
					?>
				<?php elseif ( has_post_thumbnail() ) : ?>
					<figure class="pgds-article__figure">
						<?php the_post_thumbnail( 'pgds-lead', array( 'fetchpriority' => 'high' ) ); ?>
						<?php $cap = get_the_post_thumbnail_caption(); ?>
						<?php if ( $cap ) : ?>
							<figcaption><?php echo esc_html( $cap ); ?></figcaption>
						<?php endif; ?>
					</figure>
				<?php endif; ?>

				<div class="pgds-article__body">
					<?php the_content(); ?>
				</div>

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

				<!-- Related posts -->
				<?php
				$related = array();
				if ( $term ) {
					$related = get_posts(
						array(
							'category'       => $term->term_id,
							'posts_per_page' => 4,
							'post__not_in'   => array( $post_id ),
							'post_status'    => 'publish',
						)
					);
				}
				if ( $related ) :
					?>
					<section class="pgds-section" aria-labelledby="pgds-related-title" style="margin-top:32px;">
						<div class="pgds-cat-head"><h2 id="pgds-related-title"><?php esc_html_e( 'Bài liên quan', 'pgds' ); ?></h2></div>
						<div class="pgds-grid-3">
							<?php foreach ( $related as $r ) : ?>
								<?php get_template_part( 'template-parts/card-secondary', null, array( 'post' => $r, 'variant' => 'full', 'bordered' => true ) ); ?>
							<?php endforeach; ?>
						</div>
					</section>
				<?php endif; ?>

				<!-- Prev / Next -->
				<nav class="pgds-post-nav" aria-label="<?php esc_attr_e( 'Điều hướng bài viết', 'pgds' ); ?>">
					<?php
					$prev = get_previous_post();
					$next = get_next_post();
					if ( $prev ) :
						?>
						<a href="<?php echo esc_url( get_permalink( $prev ) ); ?>">
							<span class="pgds-post-nav__dir"><?php esc_html_e( 'Bài trước', 'pgds' ); ?></span>
							<span class="pgds-post-nav__title"><?php echo esc_html( get_the_title( $prev ) ); ?></span>
						</a>
					<?php endif; ?>
					<?php if ( $next ) : ?>
						<a href="<?php echo esc_url( get_permalink( $next ) ); ?>" style="text-align:right;">
							<span class="pgds-post-nav__dir"><?php esc_html_e( 'Bài sau', 'pgds' ); ?></span>
							<span class="pgds-post-nav__title"><?php echo esc_html( get_the_title( $next ) ); ?></span>
						</a>
					<?php endif; ?>
				</nav>
			</article>

			<?php get_sidebar(); ?>
		</div>
	</main>

	<?php
endwhile;

get_footer();
