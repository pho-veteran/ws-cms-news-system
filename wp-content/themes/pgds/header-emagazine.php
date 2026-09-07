<?php
/**
 * Header for E-magazine detail layout.
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

<header class="pgds-ema-header" role="banner">
	<div class="pgds-wrap pgds-ema-header__inner">
		<div class="pgds-ema-header__left">
			<?php if ( has_custom_logo() ) : ?>
				<?php the_custom_logo(); ?>
			<?php else : ?>
				<a class="pgds-logo pgds-logo--small" href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home">
					<span class="pgds-logo__text">
						<?php bloginfo( 'name' ); ?>
					</span>
				</a>
			<?php endif; ?>
		</div>
		<div class="pgds-ema-header__center">
			<div class="pgds-ema-wordmark">
				<span class="pgds-ema-wordmark__txt"><span class="pgds-ema-wordmark__e">E</span>-magazine</span>
			</div>
		</div>
		<div class="pgds-ema-header__right">
			<!-- Omitted as per requirement -->
		</div>
	</div>
</header>
