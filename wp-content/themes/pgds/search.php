<?php
/**
 * Search results.
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

	<div class="pgds-cat-head" style="margin-top:20px;">
		<h1>
			<?php
			printf(
				/* translators: %s search query */
				esc_html__( 'Kết quả tìm kiếm: %s', 'pgds' ),
				'“' . esc_html( get_search_query() ) . '”'
			);
			?>
		</h1>
	</div>

	<div class="pgds-content-grid">
		<div>
			<form class="pgds-search" role="search" method="get" action="<?php echo esc_url( home_url( '/' ) ); ?>"
				style="width:100%;max-width:520px;margin-bottom:24px;">
				<label class="u-sr-only" for="pgds-s2"><?php esc_html_e( 'Tìm kiếm', 'pgds' ); ?></label>
				<svg class="pgds-search__icon" viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"></circle><path d="M16 16l4.5 4.5"></path></svg>
				<input class="pgds-search__input" type="search" id="pgds-s2" name="s"
					value="<?php echo esc_attr( get_search_query() ); ?>"
					placeholder="<?php esc_attr_e( 'Nhập từ khoá…', 'pgds' ); ?>">
			</form>

			<?php if ( have_posts() ) : ?>
				<div class="pgds-list" style="margin-top:0;">
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
						'prev_text' => __( '‹ Trước', 'pgds' ),
						'next_text' => __( 'Sau ›', 'pgds' ),
						'class'     => 'pgds-pagination',
					)
				);
				?>
			<?php else : ?>
				<p><?php esc_html_e( 'Không tìm thấy kết quả phù hợp. Thử từ khoá khác.', 'pgds' ); ?></p>
			<?php endif; ?>
		</div>

		<?php get_sidebar(); ?>
	</div>
</main>

<?php
get_footer();
