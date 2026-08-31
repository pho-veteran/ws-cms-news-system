<?php
/**
 * Final fallback (WordPress requires an index.php).
 * Used for the posts page or any case that doesn't match another template.
 *
 * @package pgds
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
?>
<main id="pgds-main" class="pgds-wrap" role="main">
	<div class="pgds-page-head">
		<h1><?php is_home() ? single_post_title() : esc_html_e( 'Tin tức', 'pgds' ); ?></h1>
	</div>

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
						'prev_text' => pgds_get_icon( 'chevron', array( 'class' => 'pgds-icon--flip', 'size' => 14 ) ) . __( 'Trước', 'pgds' ),
						'next_text' => __( 'Sau', 'pgds' ) . pgds_get_icon( 'chevron', array( 'size' => 14 ) ),
						'class'     => 'pgds-pagination',
					)
				);
				?>
			<?php else : ?>
				<p><?php esc_html_e( 'Chưa có bài viết.', 'pgds' ); ?></p>
			<?php endif; ?>
		</div>
		<?php get_sidebar(); ?>
	</div>
</main>
<?php
get_footer();
