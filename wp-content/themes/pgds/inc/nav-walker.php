<?php
/**
 * Nav walker + fallback.
 * Dropdown opens via :hover / :focus-within (desktop) and via a disclosure button
 * with aria-expanded (mobile, controlled by nav-mobile JS).
 *
 * @package pgds
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Walker for the main menu.
 */
class PGDS_Nav_Walker extends Walker_Nav_Menu {

	/** @var int Current item ID (used to set aria-controls for the submenu) */
	private $current_id = 0;

	/**
	 * Open a submenu level.
	 *
	 * @param string   $output Output.
	 * @param int      $depth  Depth.
	 * @param stdClass $args   Args.
	 */
	public function start_lvl( &$output, $depth = 0, $args = null ) {
		$sid     = 'pgds-submenu-' . $this->current_id;
		$output .= sprintf( '<ul class="pgds-dropdown" id="%s">', esc_attr( $sid ) );
	}

	/**
	 * Close a submenu level.
	 */
	public function end_lvl( &$output, $depth = 0, $args = null ) {
		$output .= '</ul>';
	}

	/**
	 * Open one item.
	 *
	 * @param string   $output Output.
	 * @param WP_Post  $item   Item.
	 * @param int      $depth  Depth.
	 * @param stdClass $args   Args.
	 * @param int      $id     ID.
	 */
	public function start_el( &$output, $item, $depth = 0, $args = null, $id = 0 ) {
		$has_children = in_array( 'menu-item-has-children', (array) $item->classes, true );
		$this->current_id = $item->ID;

		if ( 0 === $depth ) {
			$output .= sprintf(
				'<li class="pgds-navitem%s">',
				$has_children ? ' pgds-navitem--parent' : ''
			);
			$output .= sprintf(
				'<a class="pgds-navitem__link" href="%s"%s>%s</a>',
				esc_url( $item->url ),
				$has_children ? ' aria-haspopup="true"' : '',
				esc_html( $item->title )
			);
			if ( $has_children ) {
				$sid     = 'pgds-submenu-' . $item->ID;
				$output .= sprintf(
					'<button class="pgds-navitem__disc" type="button" data-pgds="submenu-toggle" aria-expanded="false" aria-controls="%s"><span class="u-sr-only">%s</span><span aria-hidden="true">▾</span></button>',
					esc_attr( $sid ),
					esc_html__( 'Mở menu con', 'pgds' )
				);
			}
		} else {
			$output .= '<li>';
			$output .= sprintf(
				'<a href="%s">%s</a>',
				esc_url( $item->url ),
				esc_html( $item->title )
			);
		}
	}

	/**
	 * Close one item.
	 */
	public function end_el( &$output, $item, $depth = 0, $args = null ) {
		$output .= '</li>';
	}
}

/**
 * Fallback when no menu is assigned: use the category tree (proposal §4.1)
 * so the site works right after theme activation.
 *
 * @param array $args Args from wp_nav_menu.
 */
function pgds_nav_fallback( $args ) {
	if ( ! function_exists( 'pgds_category_tree' ) ) {
		return;
	}
	$menu_class = isset( $args['menu_class'] ) ? $args['menu_class'] : 'pgds-nav__list';
	$menu_id    = isset( $args['menu_id'] ) ? $args['menu_id'] : 'pgds-primary-menu';

	echo '<ul id="' . esc_attr( $menu_id ) . '" class="' . esc_attr( $menu_class ) . '">';

	// Front page first.
	printf(
		'<li class="pgds-navitem"><a class="pgds-navitem__link" href="%s">%s</a></li>',
		esc_url( home_url( '/' ) ),
		esc_html__( 'Trang chủ', 'pgds' )
	);

	$i = 0;
	foreach ( pgds_category_tree() as $slug => $node ) {
		$i++;
		$term = get_term_by( 'slug', $slug, 'category' );
		$url  = $term instanceof WP_Term ? get_term_link( $term ) : '#';
		$has  = ! empty( $node['children'] );

		printf(
			'<li class="pgds-navitem%s">',
			$has ? ' pgds-navitem--parent' : ''
		);
		printf(
			'<a class="pgds-navitem__link" href="%s"%s>%s</a>',
			esc_url( $url ),
			$has ? ' aria-haspopup="true"' : '',
			esc_html( $node['label'] )
		);

		if ( $has ) {
			$sid = 'pgds-submenu-fb-' . $i;
			printf(
				'<button class="pgds-navitem__disc" type="button" data-pgds="submenu-toggle" aria-expanded="false" aria-controls="%s"><span class="u-sr-only">%s</span><span aria-hidden="true">▾</span></button>',
				esc_attr( $sid ),
				esc_html__( 'Mở menu con', 'pgds' )
			);
			echo '<ul class="pgds-dropdown" id="' . esc_attr( $sid ) . '">';
			foreach ( $node['children'] as $cslug => $clabel ) {
				$cterm = get_term_by( 'slug', $cslug, 'category' );
				$curl  = $cterm instanceof WP_Term ? get_term_link( $cterm ) : '#';
				printf( '<li><a href="%s">%s</a></li>', esc_url( $curl ), esc_html( $clabel ) );
			}
			echo '</ul>';
		}
		echo '</li>';
	}

	echo '</ul>';
}
