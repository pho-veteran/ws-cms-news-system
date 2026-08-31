<?php
/**
 * Category archive: lead + grid 3 + list, phan trang, sidebar.
 *
 * @package pgds
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

$term = get_queried_object();
pgds_breadcrumb();
?>
<main id="pgds-main" class="pgds-wrap" role="main">

	<div class="pgds-cat-head" style="margin-top:20px;">
		<h1><?php single_term_title(); ?></h1>
	</div>
	<?php if ( term_description() ) : ?>
		<p class="pgds-list__sapo" style="margin-bottom:16px;"><?php echo wp_kses_post( term_description() ); ?></p>
	<?php endif; ?>

	<?php
	// Sub-nav category con (neu co).
	$children = get_terms(
		array(
			'taxonomy'   => 'category',
			'parent'     => $term->term_id,
			'hide_empty' => false,
		)
	);
	if ( ! is_wp_error( $children ) && $children ) :
		?>
		<nav class="pgds-subnav" aria-label="<?php esc_attr_e( 'Chuyên mục con', 'pgds' ); ?>" style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:20px;">
			<?php foreach ( $children as $c ) : ?>
				<a href="<?php echo esc_url( get_term_link( $c ) ); ?>"
					style="font-size:12.5px;padding:5px 13px;border:1px solid var(--color-border);border-radius:999px;">
					<?php echo esc_html( $c->name ); ?>
				</a>
			<?php endforeach; ?>
		</nav>
	<?php endif; ?>

	<div class="pgds-content-grid">
		<div>
			<?php if ( have_posts() ) : ?>
				<?php
				$idx = 0;
				$grid_open = false;
				while ( have_posts() ) :
					the_post();
					$idx++;

					if ( 1 === $idx ) {
						// Bai dau: lead horizontal.
						get_template_part( 'template-parts/card-lead', null, array( 'post' => get_post(), 'eager' => true ) );
						echo '<div class="pgds-grid-3" style="margin-top:20px;">';
						$grid_open = true;
					} elseif ( $idx >= 2 && $idx <= 4 ) {
						// 3 bai tiep: grid card.
						get_template_part( 'template-parts/card-secondary', null, array( 'post' => get_post(), 'variant' => 'full', 'bordered' => true ) );
						if ( 4 === $idx ) {
							echo '</div>';
							$grid_open = false;
							echo '<div class="pgds-list">';
						}
					} else {
						// Con lai: list.
						if ( 5 === $idx && ! $grid_open ) {
							// da mo list o tren
						}
						get_template_part( 'template-parts/list-item', null, array( 'post' => get_post() ) );
					}
				endwhile;

				if ( $grid_open ) {
					echo '</div>';
				}
				if ( $idx >= 5 ) {
					echo '</div>'; // dong .pgds-list
				}
				?>

				<?php
				the_posts_pagination(
					array(
						'mid_size'  => 1,
						'prev_text' => __( '‹ Trước', 'pgds' ),
						'next_text' => __( 'Sau ›', 'pgds' ),
						'class'     => 'pgds-pagination',
					)
				);
				?>
			<?php else : ?>
				<p><?php esc_html_e( 'Chưa có bài viết trong chuyên mục này.', 'pgds' ); ?></p>
			<?php endif; ?>
		</div>

		<?php get_sidebar(); ?>
	</div>
</main>

<?php
get_footer();
