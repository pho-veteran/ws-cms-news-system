<?php
/**
 * Sidebar poster below the perpetual calendar.
 *
 * Image and optional link come from Appearance → Customize → Poster bên lề
 * (inc/customizer.php). Renders nothing until an image is chosen, so an unconfigured site
 * shows no empty frame.
 *
 * @package pgds
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$pgds_poster_id = absint( get_option( 'pgds_sidebar_poster_id', 0 ) );
if ( ! $pgds_poster_id || ! wp_attachment_is_image( $pgds_poster_id ) ) {
	return;
}

$pgds_poster_url = (string) get_option( 'pgds_sidebar_poster_url', '' );

// A linked image is the link's only content, so it needs a non-empty alt to give the link
// an accessible name. Fall back to the attachment title when the editor left alt blank.
$pgds_poster_alt = trim( (string) get_post_meta( $pgds_poster_id, '_wp_attachment_image_alt', true ) );
if ( '' === $pgds_poster_alt ) {
	$pgds_poster_alt = $pgds_poster_url ? trim( (string) get_the_title( $pgds_poster_id ) ) : '';
}

$pgds_poster_img = wp_get_attachment_image(
	$pgds_poster_id,
	'large',
	false,
	array(
		'class'    => 'pgds-poster__img',
		'alt'      => $pgds_poster_alt,
		'loading'  => 'lazy',
		'decoding' => 'async',
		'sizes'    => '(max-width: 880px) 100vw, 320px',
	)
);
if ( ! $pgds_poster_img ) {
	return;
}

$pgds_poster_external = false;
if ( $pgds_poster_url ) {
	$pgds_poster_host     = wp_parse_url( $pgds_poster_url, PHP_URL_HOST );
	$pgds_poster_external = $pgds_poster_host && wp_parse_url( home_url(), PHP_URL_HOST ) !== $pgds_poster_host;
}
?>
<div class="pgds-poster">
	<?php if ( $pgds_poster_url ) : ?>
		<a class="pgds-poster__link" href="<?php echo esc_url( $pgds_poster_url ); ?>"<?php echo $pgds_poster_external ? ' target="_blank" rel="noopener noreferrer"' : ''; ?>>
			<?php echo $pgds_poster_img; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core-generated <img>. ?>
		</a>
	<?php else : ?>
		<?php echo $pgds_poster_img; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core-generated <img>. ?>
	<?php endif; ?>
</div>
