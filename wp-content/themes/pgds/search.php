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

// found_posts carries the total across all pages, unlike post_count which is only
// this page's slice. `global` is required: inside a template file $wp_query is not
// in scope automatically.
global $wp_query;
$pgds_found = (int) $wp_query->found_posts;

// An empty `s=` makes WordPress match everything, so /?s= rendered the entire archive
// under a "Kết quả tìm kiếm: """ heading. Treat a blank query as a prompt instead of
// as a search that happened to match all content.
$pgds_query    = trim( (string) get_search_query() );
$pgds_is_blank = '' === $pgds_query;
?>
<main id="pgds-main" class="pgds-wrap" role="main">

	<div class="pgds-page-head">
		<h1>
			<?php
			if ( $pgds_is_blank ) {
				esc_html_e( 'Tìm kiếm', 'pgds' );
			} else {
				printf(
					/* translators: %s: search query */
					esc_html__( 'Kết quả tìm kiếm: %s', 'pgds' ),
					'“' . esc_html( $pgds_query ) . '”'
				);
			}
			?>
		</h1>
		<?php // Report the match count: without it the reader cannot tell a broad result set from a narrow one, or whether paging further is worthwhile. ?>
		<p class="pgds-page-head__meta">
			<?php
			if ( $pgds_is_blank ) {
				esc_html_e( 'Nhập từ khoá để tìm bài viết.', 'pgds' );
			} else {
				printf(
					/* translators: %s: number of results found */
					esc_html( _n( 'Tìm thấy %s bài viết', 'Tìm thấy %s bài viết', $pgds_found, 'pgds' ) ),
					esc_html( number_format_i18n( $pgds_found ) )
				);
			}
			?>
		</p>
	</div>

	<div class="pgds-content-grid">
		<div>
			<div class="pgds-search-refine">
				<?php
				get_search_form(
					array(
						'variant'     => 'block',
						'label'       => __( 'Tìm kiếm lại', 'pgds' ),
						'placeholder' => __( 'Nhập từ khoá…', 'pgds' ),
					)
				);
				?>
			</div>

			<?php if ( have_posts() && ! $pgds_is_blank ) : ?>
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
				<?php // An empty state names what happened and offers a way forward, rather than leaving the reader at a dead end (craft floor: errors name the problem and the recovery). "No query yet" and "query matched nothing" are different situations and get different copy. ?>
				<div class="pgds-empty">
					<h2 class="pgds-empty__title">
						<?php
						if ( $pgds_is_blank ) {
							esc_html_e( 'Bạn muốn tìm gì?', 'pgds' );
						} else {
							esc_html_e( 'Không tìm thấy bài viết nào', 'pgds' );
						}
						?>
					</h2>
					<p class="pgds-empty__body">
						<?php
						if ( $pgds_is_blank ) {
							esc_html_e( 'Dùng ô tìm kiếm phía trên, hoặc chọn một chuyên mục bên dưới.', 'pgds' );
						} else {
							esc_html_e( 'Thử từ khoá ngắn hơn, bỏ dấu, hoặc chọn một chuyên mục bên dưới.', 'pgds' );
						}
						?>
					</p>
					<ul class="pgds-empty__links">
						<?php
						foreach ( pgds_category_tree() as $pgds_slug => $pgds_cat ) :
							$pgds_term = get_term_by( 'slug', $pgds_slug, 'category' );
							if ( ! $pgds_term instanceof WP_Term ) {
								continue;
							}
							?>
							<li>
								<a href="<?php echo esc_url( get_term_link( $pgds_term ) ); ?>">
									<?php echo esc_html( $pgds_term->name ); ?>
								</a>
							</li>
						<?php endforeach; ?>
					</ul>
				</div>
			<?php endif; ?>
		</div>

		<?php get_sidebar(); ?>
	</div>
</main>

<?php
get_footer();
