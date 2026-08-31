<?php
/**
 * Header + logo + search + nav 7 muc.
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

<header class="pgds-header" role="banner">
	<div class="pgds-wrap pgds-header__inner">
		<?php if ( has_custom_logo() ) : ?>
			<?php the_custom_logo(); ?>
		<?php else : ?>
			<a class="pgds-logo" href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home">
				<span class="pgds-display" style="font-size:22px;color:var(--color-brand-strong);">
					<?php bloginfo( 'name' ); ?>
				</span>
			</a>
		<?php endif; ?>

		<div class="pgds-header__right">
			<div class="pgds-header__date"><?php echo esc_html( date_i18n( 'l, d/m/Y' ) ); ?></div>

			<form class="pgds-search" role="search" method="get" action="<?php echo esc_url( home_url( '/' ) ); ?>">
				<label class="u-sr-only" for="pgds-s"><?php esc_html_e( 'Tìm kiếm tin tức', 'pgds' ); ?></label>
				<svg class="pgds-search__icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
					<circle cx="11" cy="11" r="7"></circle><path d="M16 16l4.5 4.5"></path>
				</svg>
				<input class="pgds-search__input" type="search" id="pgds-s" name="s"
					value="<?php echo esc_attr( get_search_query() ); ?>"
					placeholder="<?php esc_attr_e( 'Tìm kiếm tin tức…', 'pgds' ); ?>">
			</form>
		</div>
	</div>
</header>

<nav class="pgds-nav" aria-label="<?php esc_attr_e( 'Chuyên mục', 'pgds' ); ?>">
	<div class="pgds-wrap pgds-nav__inner">
		<button class="pgds-nav__toggle" type="button"
			data-pgds="nav-toggle" aria-expanded="false" aria-controls="pgds-primary-menu">
			<svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
				<path d="M3 6h18M3 12h18M3 18h18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
			</svg>
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
