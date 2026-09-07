<?php
/**
 * Canonical primary navigation.
 *
 * @package pgds
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Return the canonical category represented by the current request.
 *
 * @return WP_Term|null
 */
function pgds_current_nav_category() {
	if ( is_category() ) {
		$term = get_queried_object();
		return $term instanceof WP_Term && in_array( $term->slug, pgds_category_slugs(), true ) ? $term : null;
	}

	if ( is_singular( 'post' ) ) {
		return pgds_primary_cat( get_queried_object_id() );
	}

	return null;
}

/**
 * Return exact-current and ancestor state for a canonical navigation item.
 *
 * @param string       $slug    Canonical category slug.
 * @param WP_Term|null $current Current request category.
 * @return array{current: bool, ancestor: bool}
 */
function pgds_category_nav_state( $slug, $current = null ) {
	$current = $current instanceof WP_Term ? $current : pgds_current_nav_category();
	if ( ! $current instanceof WP_Term ) {
		return array(
			'current'  => false,
			'ancestor' => false,
		);
	}

	$is_current  = $slug === $current->slug;
	$is_ancestor = false;
	if ( ! $is_current && $current->parent ) {
		$parent      = get_term( $current->parent, 'category' );
		$is_ancestor = $parent instanceof WP_Term && $slug === $parent->slug;
	}

	return array(
		'current'  => $is_current,
		'ancestor' => $is_ancestor,
	);
}

/**
 * Render a semantic breadcrumb trail.
 *
 * @param array<int,array{label:string,url?:string}> $items Breadcrumb items.
 * @param string                                    $label Accessible navigation label.
 */
function pgds_breadcrumbs( $items, $label = '' ) {
	$items = array_values(
		array_filter(
			(array) $items,
			static function ( $item ) {
				return is_array( $item ) && ! empty( $item['label'] );
			}
		)
	);
	if ( count( $items ) < 2 ) {
		return;
	}

	$label = $label ?: __( 'Đường dẫn trang', 'pgds' );
	?>
	<div class="crumb-bar">
		<div class="wrap">
			<nav class="pgds-breadcrumb breadcrumb" aria-label="<?php echo esc_attr( $label ); ?>">
				<ol class="pgds-breadcrumb__list">
					<?php foreach ( $items as $index => $item ) : ?>
						<li class="pgds-breadcrumb__item">
							<?php if ( ! empty( $item['url'] ) && $index < count( $items ) - 1 ) : ?>
								<a class="parent" href="<?php echo esc_url( $item['url'] ); ?>"><?php echo esc_html( $item['label'] ); ?></a>
							<?php else : ?>
								<span class="current" aria-current="page"><?php echo esc_html( $item['label'] ); ?></span>
							<?php endif; ?>
							<?php if ( $index < count( $items ) - 1 ) : ?>
								<span class="pgds-breadcrumb__separator sep" aria-hidden="true">›</span>
							<?php endif; ?>
						</li>
					<?php endforeach; ?>
				</ol>
			</nav>
		</div>
	</div>
	<?php
}

/**
 * Render the immutable six-category primary navigation.
 */
function pgds_primary_navigation() {
	$current      = pgds_current_nav_category();
	$is_frontpage = is_front_page();

	echo '<ul id="pgds-primary-menu" class="pgds-nav__list top">';
	printf(
		'<li class="pgds-navitem catitem%s"><a class="pgds-navitem__link top-link" href="%s"%s>%s</a></li>',
		$is_frontpage ? ' pgds-navitem--current active' : ' no-active',
		esc_url( home_url( '/' ) ),
		$is_frontpage ? ' aria-current="page"' : '',
		esc_html__( 'Trang chủ', 'pgds' )
	);

	foreach ( pgds_category_tree() as $slug => $node ) {
		$term = pgds_category_term( $slug );
		if ( ! $term instanceof WP_Term ) {
			continue;
		}

		$url = get_term_link( $term );
		if ( is_wp_error( $url ) ) {
			continue;
		}

		$state = pgds_category_nav_state( $slug, $current );
		$has   = ! empty( $node['children'] );
		$class = '';
		if ( $state['current'] ) {
			$class = ' pgds-navitem--current active';
		} elseif ( $state['ancestor'] ) {
			$class = ' pgds-navitem--current-ancestor active';
		} else {
			$class = ' no-active';
		}

		printf(
			'<li class="pgds-navitem catitem%s%s">',
			$has ? ' pgds-navitem--parent' : '',
			esc_attr( $class )
		);
		printf(
			'<a class="pgds-navitem__link top-link" href="%s"%s%s>%s</a>',
			esc_url( $url ),
			$has ? ' aria-haspopup="true"' : '',
			$state['current'] ? ' aria-current="page"' : '',
			esc_html( $node['label'] )
		);

		if ( $has ) {
			$submenu_id = 'pgds-submenu-' . sanitize_html_class( $slug );
			printf(
				'<button class="pgds-navitem__disc" type="button" data-pgds="submenu-toggle" aria-expanded="false" aria-controls="%s"><span class="u-sr-only">%s</span>%s</button>',
				esc_attr( $submenu_id ),
				esc_html__( 'Mở menu con', 'pgds' ),
				pgds_get_icon( 'chevron', array( 'class' => 'pgds-navitem__disc-icon', 'size' => 14 ) )
			);
			echo '<ul class="pgds-dropdown dropdown" id="' . esc_attr( $submenu_id ) . '">';

			foreach ( $node['children'] as $child_slug => $child_label ) {
				$child = pgds_category_term( $child_slug );
				if ( ! $child instanceof WP_Term ) {
					continue;
				}

				$child_url = get_term_link( $child );
				if ( is_wp_error( $child_url ) ) {
					continue;
				}

				$child_state = pgds_category_nav_state( $child_slug, $current );
				printf(
					'<li class="pgds-dropdown__item%s"><a class="pgds-dropdown__link" href="%s"%s>%s</a></li>',
					$child_state['current'] ? ' pgds-navitem--current' : '',
					esc_url( $child_url ),
					$child_state['current'] ? ' aria-current="page"' : '',
					esc_html( $child_label )
				);
			}
			echo '</ul>';
		}

		echo '</li>';
	}
	echo '</ul>';
}
