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
 * Translate WordPress current-menu classes into the theme's nav state.
 *
 * The nav used to style `:first-child` as if it were active, which left
 * "Trang chủ" highlighted on every archive. WordPress already knows which item
 * matches the request, so read that instead of guessing from DOM position.
 *
 * @param array $classes Menu item classes from WordPress.
 * @return array { class: string, current: bool }
 */
function pgds_nav_state_from_classes( $classes ) {
	$exact = array( 'current-menu-item', 'current_page_item' );
	foreach ( $exact as $needle ) {
		if ( in_array( $needle, $classes, true ) ) {
			return array(
				'class'   => ' pgds-navitem--current',
				'current' => true,
			);
		}
	}

	$ancestor = array( 'current-menu-parent', 'current-menu-ancestor', 'current_page_parent', 'current_page_ancestor' );
	foreach ( $ancestor as $needle ) {
		if ( in_array( $needle, $classes, true ) ) {
			return array(
				'class'   => ' pgds-navitem--current-ancestor',
				'current' => false,
			);
		}
	}

	return array(
		'class'   => '',
		'current' => false,
	);
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

	public function start_el( &$output, $item, $depth = 0, $args = null, $id = 0 ) {
		$has_children     = in_array( 'menu-item-has-children', (array) $item->classes, true );
		$this->current_id = $item->ID;
		$state            = pgds_nav_state_from_classes( (array) $item->classes );

		if ( 0 === $depth ) {
			$output .= sprintf(
				'<li class="pgds-navitem%s%s">',
				$has_children ? ' pgds-navitem--parent' : '',
				esc_attr( $state['class'] )
			);
			$output .= sprintf(
				'<a class="pgds-navitem__link" href="%s"%s%s>%s</a>',
				esc_url( $item->url ),
				$has_children ? ' aria-haspopup="true"' : '',
				$state['current'] ? ' aria-current="page"' : '',
				esc_html( $item->title )
			);
			if ( $has_children ) {
				$sid = 'pgds-submenu-' . $item->ID;
				$output .= sprintf(
					'<button class="pgds-navitem__disc" type="button" data-pgds="submenu-toggle" aria-expanded="false" aria-controls="%s"><span class="u-sr-only">%s</span>%s</button>',
					esc_attr( $sid ),
					esc_html__( 'Mở menu con', 'pgds' ),
					// Icon rather than a '▾' glyph: the glyph inherited ink-on-brown at
					// 1.58:1 and rendered in the reader's system font.
					pgds_get_icon( 'chevron', array( 'class' => 'pgds-navitem__disc-icon', 'size' => 14 ) )
				);
			}
		} else {
			$output .= sprintf(
				'<li class="pgds-dropdown__item%s">',
				esc_attr( $state['class'] )
			);
			$output .= sprintf(
				'<a class="pgds-dropdown__link" href="%s"%s>%s</a>',
				esc_url( $item->url ),
				$state['current'] ? ' aria-current="page"' : '',
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

	// The fallback has no menu items, so current state is derived from the request.
	$queried      = is_category() ? get_queried_object() : null;
	$current_slug = $queried instanceof WP_Term ? $queried->slug : '';

	echo '<ul id="' . esc_attr( $menu_id ) . '" class="' . esc_attr( $menu_class ) . '">';

	// Front page first.
	printf(
		'<li class="pgds-navitem%s"><a class="pgds-navitem__link" href="%s"%s>%s</a></li>',
		is_front_page() ? ' pgds-navitem--current' : '',
		esc_url( home_url( '/' ) ),
		is_front_page() ? ' aria-current="page"' : '',
		esc_html__( 'Trang chủ', 'pgds' )
	);

	$i = 0;
	foreach ( pgds_category_tree() as $slug => $node ) {
		$i++;
		$term = get_term_by( 'slug', $slug, 'category' );
		$url  = $term instanceof WP_Term ? get_term_link( $term ) : '#';
		$has  = ! empty( $node['children'] );

		$is_current  = $current_slug && $current_slug === $slug;
		$is_ancestor = ! $is_current && $current_slug && $has
			&& array_key_exists( $current_slug, (array) $node['children'] );

		$state_class = '';
		if ( $is_current ) {
			$state_class = ' pgds-navitem--current';
		} elseif ( $is_ancestor ) {
			$state_class = ' pgds-navitem--current-ancestor';
		}

		printf(
			'<li class="pgds-navitem%s%s">',
			$has ? ' pgds-navitem--parent' : '',
			esc_attr( $state_class )
		);
		printf(
			'<a class="pgds-navitem__link" href="%s"%s%s>%s</a>',
			esc_url( $url ),
			$has ? ' aria-haspopup="true"' : '',
			$is_current ? ' aria-current="page"' : '',
			esc_html( $node['label'] )
		);

		if ( $has ) {
			$sid = 'pgds-submenu-fb-' . $i;
			printf(
				'<button class="pgds-navitem__disc" type="button" data-pgds="submenu-toggle" aria-expanded="false" aria-controls="%s"><span class="u-sr-only">%s</span>%s</button>',
				esc_attr( $sid ),
				esc_html__( 'Mở menu con', 'pgds' ),
				pgds_get_icon( 'chevron', array( 'class' => 'pgds-navitem__disc-icon', 'size' => 14 ) )
			);
			echo '<ul class="pgds-dropdown" id="' . esc_attr( $sid ) . '">';
			foreach ( $node['children'] as $cslug => $clabel ) {
				$cterm = get_term_by( 'slug', $cslug, 'category' );
				$curl  = $cterm instanceof WP_Term ? get_term_link( $cterm ) : '#';
				$child_current = $current_slug === $cslug;
				printf(
					'<li class="pgds-dropdown__item%s"><a class="pgds-dropdown__link" href="%s"%s>%s</a></li>',
					$child_current ? ' pgds-navitem--current' : '',
					esc_url( $curl ),
					$child_current ? ' aria-current="page"' : '',
					esc_html( $clabel )
				);
			}
			echo '</ul>';
		}
		echo '</li>';
	}

	echo '</ul>';
}
