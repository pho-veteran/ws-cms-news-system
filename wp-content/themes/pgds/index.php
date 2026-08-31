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
		<?php
		/*
		 * The previous form was:
		 *   is_home() ? single_post_title() : esc_html_e( 'Tin tức', 'pgds' )
		 * which printed NOTHING on the home branch. single_post_title() echoes only
		 * when its $display argument is true, and using it as a ternary operand
		 * discards the value; esc_html_e() in the other branch does echo, so the bug
		 * only appeared on one path. Result: an empty <h1> on /page/2/, i.e. a page
		 * with no accessible name at all.
		 *
		 * On a paged front page "Tin mới nhất" is also more useful than the site title,
		 * which the masthead already shows.
		 */
		$pgds_index_title = __( 'Tin tức', 'pgds' );
		if ( is_home() && is_paged() ) {
			$pgds_index_title = __( 'Tin mới nhất', 'pgds' );
		} elseif ( is_home() && ! is_front_page() ) {
			// A dedicated posts page: use its real title.
			$pgds_posts_page = get_option( 'page_for_posts' );
			if ( $pgds_posts_page ) {
				$pgds_index_title = get_the_title( $pgds_posts_page );
			}
		}
		?>
		<h1><?php echo esc_html( $pgds_index_title ); ?></h1>
		<?php if ( is_paged() ) : ?>
			<p class="pgds-page-head__meta">
				<?php
				printf(
					/* translators: %s: page number */
					esc_html__( 'Trang %s', 'pgds' ),
					esc_html( number_format_i18n( max( 1, (int) get_query_var( 'paged' ) ) ) )
				);
				?>
			</p>
		<?php endif; ?>
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
