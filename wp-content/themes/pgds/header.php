<?php
/**
 * Header + logo + search + 7-item nav.
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

<nav class="pgds-nav" aria-label="<?php esc_attr_e( 'Chuyên mục', 'pgds' ); ?>">
	<div class="pgds-wrap pgds-nav__inner">
		<button class="pgds-nav__toggle" type="button"
			data-pgds="nav-toggle" aria-expanded="false" aria-controls="pgds-primary-menu">
			<?php pgds_icon( 'menu', array( 'class' => 'pgds-nav__toggle-icon pgds-nav__toggle-icon--menu', 'size' => 18 ) ); ?>
			<?php pgds_icon( 'close', array( 'class' => 'pgds-nav__toggle-icon pgds-nav__toggle-icon--close', 'size' => 18 ) ); ?>
			<span><?php esc_html_e( 'Chuyên mục', 'pgds' ); ?></span>
		</button>

		<?php
		wp_nav_menu(
			array(
				'theme_location' => 'primary',
				'container'      => false,
				'menu_id'        => 'pgds-primary-menu',
				'menu_class'     => 'pgds-nav__list',
				'fallback_cb'    => 'pgds_nav_fallback',
				'walker'         => new PGDS_Nav_Walker(),
				'depth'          => 2,
			)
		);
		?>
	</div>
</nav>
