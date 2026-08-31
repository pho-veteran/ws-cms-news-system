<?php
/**
 * 404 - goi y bai moi + o tim kiem.
 *
 * @package pgds
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
?>
<main id="pgds-main" class="pgds-wrap" role="main" style="padding-top:32px;">
	<div class="pgds-cat-head">
		<h1><?php esc_html_e( 'Không tìm thấy trang', 'pgds' ); ?></h1>
	</div>

	<p style="margin:16px 0 24px;color:var(--color-text-muted);">
		<?php esc_html_e( 'Trang bạn tìm không tồn tại hoặc đã được di chuyển. Thử tìm kiếm hoặc xem các bài mới bên dưới.', 'pgds' ); ?>
	</p>

	<form class="pgds-search" role="search" method="get" action="<?php echo esc_url( home_url( '/' ) ); ?>"
		style="width:100%;max-width:520px;margin-bottom:32px;">
		<label class="u-sr-only" for="pgds-s404"><?php esc_html_e( 'Tìm kiếm', 'pgds' ); ?></label>
		<svg class="pgds-search__icon" viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"></circle><path d="M16 16l4.5 4.5"></path></svg>
		<input class="pgds-search__input" type="search" id="pgds-s404" name="s" placeholder="<?php esc_attr_e( 'Nhập từ khoá…', 'pgds' ); ?>">
	</form>

	<div class="pgds-cat-head"><h2><?php esc_html_e( 'Bài mới nhất', 'pgds' ); ?></h2></div>
	<div class="pgds-grid-3">
		<?php
		$recent = get_posts( array( 'posts_per_page' => 6, 'post_status' => 'publish' ) );
		foreach ( $recent as $p ) {
			get_template_part( 'template-parts/card-secondary', null, array( 'post' => $p, 'variant' => 'full', 'bordered' => true ) );
		}
		?>
	</div>
</main>

<?php
get_footer();
