<?php
/**
 * Focused reader shell for E-magazine details.
 *
 * @package pgds
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$post = get_queried_object();
$post = $post instanceof WP_Post ? $post : null;
$term = pgds_category_term( 'emagazine' );
$term_url = home_url( '/' );
if ( $term instanceof WP_Term ) {
	$candidate_url = get_term_link( $term );
	if ( ! is_wp_error( $candidate_url ) ) {
		$term_url = $candidate_url;
	}
}

if ( is_user_logged_in() ) {
	$edit_url = $post ? get_edit_post_link( $post->ID, 'raw' ) : '';
	if ( $edit_url ) {
		$action_url   = $edit_url;
		$action_label = __( 'Sửa bài', 'pgds' );
	} else {
		$action_url   = get_edit_profile_url( get_current_user_id() );
		$action_label = __( 'Tài khoản', 'pgds' );
	}
} else {
	$action_url   = wp_login_url( $post ? get_permalink( $post ) : home_url( '/' ) );
	$action_label = __( 'Đăng nhập', 'pgds' );
}
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<?php wp_head(); ?>
</head>
<body <?php body_class( 'pgds-emagazine-reader' ); ?>>
<?php wp_body_open(); ?>

<a class="pgds-skip-link" href="#pgds-main"><?php esc_html_e( 'Bỏ qua tới nội dung', 'pgds' ); ?></a>

<header class="pgds-emagazine-header" role="banner">
	<div class="pgds-wrap pgds-emagazine-header__inner">
		<a class="pgds-emagazine-header__logo" href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home">
			<img src="<?php echo esc_url( PGDS_LOGO_URI ); ?>" width="2048" height="357" alt="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>" decoding="async">
		</a>

		<a class="pgds-emagazine-header__wordmark" href="<?php echo esc_url( $term_url ); ?>">
			<span class="pgds-emagazine-header__initial" aria-hidden="true">E</span>magazine
		</a>

		<a class="pgds-emagazine-header__action" href="<?php echo esc_url( $action_url ); ?>">
			<?php pgds_icon( 'user', array( 'size' => 15 ) ); ?>
			<span><?php echo esc_html( $action_label ); ?></span>
		</a>
	</div>
</header>
