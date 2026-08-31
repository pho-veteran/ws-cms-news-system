<?php
/**
 * 404 - names the problem, offers search, then the newest posts as a way forward.
 *
 * @package pgds
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
?>
<main id="pgds-main" class="pgds-wrap pgds-404" role="main">
	<div class="pgds-page-head">
		<h1><?php esc_html_e( 'Không tìm thấy trang', 'pgds' ); ?></h1>
		<p class="pgds-page-head__meta">
			<?php esc_html_e( 'Trang bạn tìm không tồn tại hoặc đã được di chuyển.', 'pgds' ); ?>
		</p>
	</div>

	<div class="pgds-404__search">
		<?php
		get_search_form(
			array(
				'variant'     => 'block',
				'label'       => __( 'Tìm kiếm bài viết', 'pgds' ),
				'placeholder' => __( 'Nhập từ khoá…', 'pgds' ),
			)
		);
		?>
	</div>

	<?php
	$pgds_recent = get_posts(
		array(
			'posts_per_page' => 6,
			'post_status'    => 'publish',
		)
	);

	if ( $pgds_recent ) :
		?>
		<section class="pgds-section" aria-labelledby="pgds-404-recent">
			<div class="pgds-cat-head">
				<h2 id="pgds-404-recent"><?php esc_html_e( 'Bài mới nhất', 'pgds' ); ?></h2>
			</div>
			<div class="pgds-grid-3">
				<?php
				foreach ( $pgds_recent as $pgds_post ) {
					get_template_part(
						'template-parts/card-secondary',
						null,
						array(
							'post'     => $pgds_post,
							'variant'  => 'full',
							'bordered' => true,
						)
					);
				}
				?>
			</div>
		</section>
	<?php endif; ?>
</main>

<?php
get_footer();
