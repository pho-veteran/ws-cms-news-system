<?php
/**
 * Category archive: section navigation, featured stories, list, pagination, sidebar.
 *
 * @package pgds
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

$term       = get_queried_object();
$is_paged   = is_paged();
$children   = array();
$parent     = null;
$hero_posts = array();
$list_posts = array();

if ( $term instanceof WP_Term ) {
	$parent = $term->parent ? get_term( $term->parent, 'category' ) : $term;
	if ( is_wp_error( $parent ) ) {
		$parent = $term;
	}

	$children = get_terms(
		array(
			'taxonomy'   => 'category',
			'parent'     => $parent->term_id,
			'hide_empty' => false,
		)
	);
	if ( is_wp_error( $children ) ) {
		$children = array();
	}
}

if ( have_posts() ) {
	$archive_posts = $wp_query->posts;
	if ( $is_paged ) {
		$list_posts = $archive_posts;
	} else {
		$hero_posts = array_slice( $archive_posts, 0, 5 );
		$list_posts = array_slice( $archive_posts, 5 );
	}
}

?>
<main id="pgds-main" class="pgds-wrap pgds-category" role="main">
	<header class="pgds-category__head">
		<nav class="pgds-category__tabs" aria-label="<?php esc_attr_e( 'Chuyên mục con', 'pgds' ); ?>">
			<?php if ( $parent instanceof WP_Term ) : ?>
				<?php if ( $term->term_id === $parent->term_id ) : ?>
					<h1 class="pgds-category__title"><?php echo esc_html( $parent->name ); ?></h1>
				<?php else : ?>
					<a class="pgds-category__parent" href="<?php echo esc_url( get_term_link( $parent ) ); ?>"><?php echo esc_html( $parent->name ); ?></a>
				<?php endif; ?>

				<?php foreach ( $children as $child ) : ?>
					<span class="pgds-category__separator" aria-hidden="true">/</span>
					<?php if ( $term->term_id === $child->term_id ) : ?>
						<h1 class="pgds-category__child pgds-category__child--current"><?php echo esc_html( $child->name ); ?></h1>
					<?php else : ?>
						<a class="pgds-category__child" href="<?php echo esc_url( get_term_link( $child ) ); ?>"><?php echo esc_html( $child->name ); ?></a>
					<?php endif; ?>
				<?php endforeach; ?>
			<?php else : ?>
				<h1 class="pgds-category__title"><?php single_term_title(); ?></h1>
			<?php endif; ?>
		</nav>

		<?php if ( term_description() ) : ?>
			<div class="pgds-category__description"><?php echo wp_kses_post( term_description() ); ?></div>
		<?php endif; ?>
	</header>

	<?php if ( $hero_posts ) : ?>
		<section class="pgds-category-hero" aria-label="<?php esc_attr_e( 'Bài viết nổi bật', 'pgds' ); ?>">
			<?php
			$lead     = $hero_posts[0];
			$lead_url = get_permalink( $lead );
			?>
			<article class="pgds-category-hero__lead">
				<a class="pgds-category-hero__lead-media" href="<?php echo esc_url( $lead_url ); ?>" tabindex="-1" aria-hidden="true">
					<?php pgds_art( $lead, 'pgds-lead', 'pgds-ratio-video', true ); ?>
				</a>
				<h2 class="pgds-category-hero__lead-title">
					<a href="<?php echo esc_url( $lead_url ); ?>"><?php echo esc_html( get_the_title( $lead ) ); ?></a>
				</h2>
				<p class="pgds-category-hero__lead-sapo"><?php echo esc_html( wp_trim_words( pgds_sapo( $lead ), 45 ) ); ?></p>
			</article>

			<?php if ( count( $hero_posts ) > 1 ) : ?>
				<div class="pgds-category-hero__grid">
					<?php foreach ( array_slice( $hero_posts, 1 ) as $hero_post ) : ?>
						<?php $post_url = get_permalink( $hero_post ); ?>
						<article class="pgds-category-hero__card">
							<a class="pgds-category-hero__card-media" href="<?php echo esc_url( $post_url ); ?>" tabindex="-1" aria-hidden="true">
								<?php pgds_art( $hero_post, 'pgds-card', 'pgds-ratio-card' ); ?>
							</a>
							<h3 class="pgds-category-hero__card-title">
								<a href="<?php echo esc_url( $post_url ); ?>"><?php echo esc_html( get_the_title( $hero_post ) ); ?></a>
							</h3>
						</article>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</section>

		<div class="pgds-category__ornament" aria-hidden="true">
			<?php pgds_icon( 'sprout', array( 'size' => 22 ) ); ?>
		</div>
	<?php endif; ?>

	<div class="pgds-content-grid">
		<div class="pgds-category__archive">
			<?php if ( $list_posts ) : ?>
				<div class="pgds-list pgds-category__list">
					<?php foreach ( $list_posts as $list_post ) : ?>
						<?php get_template_part( 'template-parts/list-item', null, array( 'post' => $list_post ) ); ?>
					<?php endforeach; ?>
				</div>
			<?php elseif ( ! $hero_posts ) : ?>
				<section class="pgds-empty" aria-labelledby="pgds-category-empty-title">
					<h2 class="pgds-empty__title" id="pgds-category-empty-title"><?php esc_html_e( 'Chuyên mục đang được cập nhật', 'pgds' ); ?></h2>
					<p class="pgds-empty__body"><?php esc_html_e( 'Chưa có bài viết trong chuyên mục này. Mời bạn quay lại trang chủ để đọc những nội dung mới nhất.', 'pgds' ); ?></p>
					<ul class="pgds-empty__links">
						<li><a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Về trang chủ', 'pgds' ); ?></a></li>
					</ul>
				</section>
			<?php endif; ?>

			<?php
			/*
			 * Pagination sits OUTSIDE the list branch. The hero consumes up to five posts, so a
			 * site whose posts_per_page is five or lower leaves $list_posts empty on page 1 while
			 * the category still spans several pages — nesting the call inside the list branch
			 * would strand readers on page 1 with no way forward.
			 */
			the_posts_pagination(
				array(
					'mid_size'  => 1,
					'prev_text' => pgds_get_icon( 'chevron', array( 'class' => 'pgds-icon--flip', 'size' => 14 ) ) . __( 'Trước', 'pgds' ),
					'next_text' => __( 'Sau', 'pgds' ) . pgds_get_icon( 'chevron', array( 'size' => 14 ) ),
					'class'     => 'pgds-pagination',
				)
			);
			?>
		</div>

		<?php get_sidebar(); ?>
	</div>
</main>

<?php
get_footer();
