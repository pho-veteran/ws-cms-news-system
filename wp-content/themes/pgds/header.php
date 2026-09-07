<?php
/**
 * Header, logo, search, and canonical six-category navigation.
 *
 * @package pgds
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<a class="pgds-skip-link" href="#pgds-main"><?php esc_html_e( 'Bỏ qua tới nội dung', 'pgds' ); ?></a>

<div class="pgds-guide">
	<p><?php esc_html_e( 'Chuyên trang tin điện tử — tin tức, đời sống và văn hóa Phật giáo.', 'pgds' ); ?></p>
</div>

<header class="pgds-header" role="banner">
	<div class="pgds-wrap pgds-header__inner">
		<?php if ( has_custom_logo() ) : ?>
			<?php the_custom_logo(); ?>
		<?php else : ?>
			<a class="pgds-logo" href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home">
				<span class="pgds-logo__text">
					<?php bloginfo( 'name' ); ?>
				</span>
			</a>
		<?php endif; ?>

		<div class="pgds-header__right">
			<div class="pgds-header__date"><?php echo esc_html( pgds_date_full_vi() ); ?></div>

			<?php get_search_form(); ?>
		</div>
	</div>
</header>

<div class="pgds-nav-sentinel" data-pgds="nav-sentinel" aria-hidden="true"></div>

<nav class="pgds-nav" aria-label="<?php esc_attr_e( 'Chuyên mục', 'pgds' ); ?>" data-pgds="primary-nav">
	<div class="pgds-wrap pgds-nav__inner">
		<button class="pgds-nav__toggle" type="button"
			data-pgds="nav-toggle" aria-expanded="false" aria-controls="pgds-primary-surface">
			<?php pgds_icon( 'menu', array( 'class' => 'pgds-nav__toggle-icon', 'size' => 18 ) ); ?>
			<span><?php esc_html_e( 'Chuyên mục', 'pgds' ); ?></span>
		</button>

		<div class="pgds-nav__surface" id="pgds-primary-surface" data-pgds="nav-surface">
			<div class="pgds-nav__surface-head">
				<strong id="pgds-nav-surface-title"><?php esc_html_e( 'Chuyên mục', 'pgds' ); ?></strong>
				<button class="pgds-nav__close" type="button" data-pgds="nav-close">
					<span class="u-sr-only"><?php esc_html_e( 'Đóng menu chuyên mục', 'pgds' ); ?></span>
					<?php pgds_icon( 'close', array( 'size' => 20 ) ); ?>
				</button>
			</div>

			<div class="pgds-nav__search">
					<div class="pgds-nav__date"><?php echo esc_html( pgds_date_full_vi() ); ?></div>
					<?php get_search_form(); ?>
				</div>

			<div class="pgds-nav__rail" data-pgds="nav-rail">
				<?php
				pgds_primary_navigation();
				?>
			</div>
		</div>

		</div>
</nav>

<div class="pgds-nav-backdrop" data-pgds="nav-backdrop" aria-hidden="true"></div>
