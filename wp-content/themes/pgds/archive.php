<?php
/**
 * Generic archive: tag, taxonomy, author, date.
 * (proposal §3.3: fallback for dedicated templates that are deferred).
 *
 * @package pgds
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
pgds_breadcrumb();
?>
<main id="pgds-main" class="pgds-wrap" role="main">

	<div class="pgds-page-head">
		<h1><?php the_archive_title(); ?></h1>
	</div>
	<?php if ( get_the_archive_description() ) : ?>
		<div class="pgds-page-head__desc"><?php the_archive_description(); ?></div>
	<?php endif; ?>

	<div class="pgds-content-grid">
		<div>
			<?php if ( have_posts() ) : ?>
				<div class="pgds-list pgds-list--flush">
					<?php
					while ( have_posts() ) :
						the_post();
						get_template_part( 'template-parts/list-item', null, array( 'post' => get_post() ) );
					endwhile;
					?>
				</div>

				<?php
				the_posts_pagination(
					array(
						'mid_size'  => 1,
						'prev_text' => pgds_get_icon( 'chevron', array( 'class' => 'pgds-icon--flip', 'size' => 14 ) ) . __( 'Trước', 'pgds' ),
						'next_text' => __( 'Sau', 'pgds' ) . pgds_get_icon( 'chevron', array( 'size' => 14 ) ),
						'class'     => 'pgds-pagination',
					)
				);
				?>
			<?php else : ?>
				<p><?php esc_html_e( 'Không có bài viết.', 'pgds' ); ?></p>
			<?php endif; ?>
		</div>

		<?php get_sidebar(); ?>
	</div>
</main>

<?php
get_footer();
