<?php
/**
 * Search form.
 *
 * One definition used by the header, the search page, and 404, so the three cannot
 * drift apart. `get_search_form()` picks this file up automatically, which also means
 * core and plugin call sites render the themed form rather than the WordPress default.
 *
 * A unique id per instance is required: several forms can appear on one page (header
 * plus in-page), and duplicate ids would break every label's `for` association.
 *
 * @package pgds
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$pgds_variant = $args['variant'] ?? 'default';
$pgds_label   = $args['label'] ?? __( 'Tìm kiếm tin tức', 'pgds' );
$pgds_hint    = $args['placeholder'] ?? __( 'Tìm kiếm tin tức…', 'pgds' );

// wp_unique_id() is per-request, so two forms on one page never collide.
$pgds_id = wp_unique_id( 'pgds-search-' );
?>
<form class="pgds-search<?php echo 'default' === $pgds_variant ? '' : ' pgds-search--' . esc_attr( $pgds_variant ); ?>"
	role="search" method="get" action="<?php echo esc_url( home_url( '/' ) ); ?>">
	<label class="u-sr-only" for="<?php echo esc_attr( $pgds_id ); ?>"><?php echo esc_html( $pgds_label ); ?></label>
	<?php pgds_icon( 'search', array( 'class' => 'pgds-search__icon', 'size' => 16 ) ); ?>
	<input class="pgds-search__input" type="search" id="<?php echo esc_attr( $pgds_id ); ?>" name="s"
		value="<?php echo esc_attr( get_search_query() ); ?>"
		placeholder="<?php echo esc_attr( $pgds_hint ); ?>">
	<?php // A submit control is required for keyboard and screen-reader users: relying on the implicit Enter submit leaves the form with no operable button (proposal §2.3). ?>
	<button class="pgds-search__submit" type="submit">
		<span class="u-sr-only"><?php esc_html_e( 'Tìm', 'pgds' ); ?></span>
		<?php pgds_icon( 'chevron', array( 'size' => 14 ) ); ?>
	</button>
</form>
