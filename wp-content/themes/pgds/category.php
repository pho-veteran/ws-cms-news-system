<?php
/**
 * Category archive: lead + 3-grid + list, pagination, sidebar.
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

	<div class="pgds-page-head">
		<h1><?php single_term_title(); ?></h1>
	</div>
	<?php if ( term_description() ) : ?>
		<div class="pgds-page-head__desc"><?php echo wp_kses_post( term_description() ); ?></div>
	<?php endif; ?>

	<?php
	// Sub-nav for child categories (if any).
	$children = get_terms(
		array(
			'taxonomy'   => 'category',
			'parent'     => $term->term_id,
			'hide_empty' => false,
		)
	);
	if ( ! is_wp_error( $children ) && $children ) :
		?>
		<nav class="pgds-subnav" aria-label="<?php esc_attr_e( 'Chuyên mục con', 'pgds' ); ?>">
			<?php foreach ( $children as $c ) : ?>
				<a href="<?php echo esc_url( get_term_link( $c ) ); ?>"
					class="pgds-subnav__item">
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
						// First post: horizontal lead.
						get_template_part( 'template-parts/card-lead', null, array( 'post' => get_post(), 'eager' => true ) );
						echo '<div class="pgds-grid-3 pgds-grid-3--spaced">';
						$grid_open = true;
					} elseif ( $idx >= 2 && $idx <= 4 ) {
						// Next 3 posts: grid card.
						get_template_part( 'template-parts/card-secondary', null, array( 'post' => get_post(), 'variant' => 'full', 'bordered' => true ) );
						if ( 4 === $idx ) {
							echo '</div>';
							$grid_open = false;
							echo '<div class="pgds-list">';
						}
					} else {
						// Remaining posts: list.
						if ( 5 === $idx && ! $grid_open ) {
							// list already opened above
						}
						get_template_part( 'template-parts/list-item', null, array( 'post' => get_post() ) );
					}
				endwhile;

				if ( $grid_open ) {
					echo '</div>';
				}
				if ( $idx >= 5 ) {
					echo '</div>'; // close .pgds-list
				}
				?>

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
				<p><?php esc_html_e( 'Chưa có bài viết trong chuyên mục này.', 'pgds' ); ?></p>
			<?php endif; ?>
		</div>

		<?php get_sidebar(); ?>
	</div>
</main>

<?php
get_footer();
