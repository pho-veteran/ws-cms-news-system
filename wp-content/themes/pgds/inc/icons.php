<?php
/**
 * Inline SVG icon set.
 *
 * A single authored set drawn on a 24x24 grid with a consistent 1.75 stroke and
 * round caps/joins, so icons read as one family. Emoji and typographic glyphs are
 * deliberately not used as icons: they render in the reader's system emoji font,
 * ignore currentColor, size inconsistently across platforms, and are announced as
 * words by screen readers.
 *
 * Icons are inlined rather than sprited because the set is small and inlining keeps
 * them stylable with currentColor and free of an extra request.
 *
 * @package pgds
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Return the path markup for one icon, or '' when the name is unknown.
 *
 * @param string $name Icon name.
 * @return string
 */
function pgds_icon_paths( $name ) {
	$icons = array(
		// Headphones - the "Lời Phật dạy" audio teachings list.
		'headphones' => '<path d="M4 15v-3a8 8 0 0 1 16 0v3"/><path d="M4 15a2 2 0 0 1 2-2h1v6H6a2 2 0 0 1-2-2z"/><path d="M20 15a2 2 0 0 0-2-2h-1v6h1a2 2 0 0 0 2-2z"/>',

		// Play - video affordances.
		'play'       => '<path d="M8 5.5v13l11-6.5z"/>',

		// Video camera - media bullet list.
		'video'      => '<rect x="2.5" y="6.5" width="13" height="11" rx="2"/><path d="M15.5 11l6-3.5v9l-6-3.5z"/>',

		// Image stack - the photo-story panel.
		'images'     => '<rect x="3" y="7" width="14" height="11" rx="2"/><path d="M7 4h12a2 2 0 0 1 2 2v9"/><circle cx="8" cy="11" r="1.5"/><path d="M3 15.5l3.5-3 4 3.5"/>',

		// Search - header and search forms.
		'search'     => '<circle cx="11" cy="11" r="7"/><path d="M16.5 16.5L21 21"/>',

		// Chevron right - "read more" affordances and pagination.
		'chevron'    => '<path d="M9 5l7 7-7 7"/>',

		// Clock - published time metadata.
		'clock'      => '<circle cx="12" cy="12" r="8.5"/><path d="M12 7.5V12l3 2"/>',

		// Menu - the mobile navigation toggle.
		'menu'       => '<path d="M3.5 6.5h17M3.5 12h17M3.5 17.5h17"/>',

		// Close - dismissing the mobile navigation.
		'close'      => '<path d="M6 6l12 12M18 6L6 18"/>',

		// Calendar - the perpetual calendar block.
		'calendar'   => '<rect x="3.5" y="5.5" width="17" height="15" rx="2"/><path d="M3.5 10h17M8 3.5v4M16 3.5v4"/>',

		// Sprout - the "Sống an lành" mindful-living section.
		'sprout'     => '<path d="M12 20v-7"/><path d="M12 13c0-3.5-2.5-6-6-6 0 3.5 2.5 6 6 6z"/><path d="M12 13c0-3 2-5.5 5.5-5.5 0 3-2.5 5.5-5.5 5.5z"/>',
	);

	return $icons[ $name ] ?? '';
}

/**
 * Return an inline SVG icon.
 *
 * Decorative by default (aria-hidden, focusable="false") because icons here sit
 * beside a text label. Pass $label to make the icon itself the accessible name,
 * for the rare case where no adjacent text exists.
 *
 * @param string $name  Icon name from pgds_icon_paths().
 * @param array  $args  { class: string, size: int, label: string }.
 * @return string
 */
function pgds_get_icon( $name, $args = array() ) {
	$paths = pgds_icon_paths( $name );
	if ( '' === $paths ) {
		return '';
	}

	// sanitize_html_class() per token, not just esc_attr() on the whole string. This
	// function's return value is concatenated into the_posts_pagination()'s
	// prev_text/next_text, which core embeds WITHOUT escaping or KSES — so the markup
	// this returns must be trustworthy on its own rather than relying on every caller
	// passing a literal.
	$class = '';
	if ( isset( $args['class'] ) ) {
		$class = implode(
			' ',
			array_filter( array_map( 'sanitize_html_class', preg_split( '/\s+/', (string) $args['class'] ) ) )
		);
	}
	$size  = isset( $args['size'] ) ? (int) $args['size'] : 20;
	$label = isset( $args['label'] ) ? (string) $args['label'] : '';

	// 'play' is a solid glyph; the rest are strokes. Mixing fill and stroke on one
	// element would either hollow the triangle or thicken the outlines.
	$is_solid = 'play' === $name;

	$a11y = $label
		? sprintf( 'role="img" aria-label="%s"', esc_attr( $label ) )
		: 'aria-hidden="true" focusable="false"';

	return sprintf(
		'<svg class="pgds-icon%s" width="%d" height="%d" viewBox="0 0 24 24" fill="%s" stroke="%s" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" %s>%s</svg>',
		$class ? ' ' . esc_attr( $class ) : '',
		$size,
		$size,
		$is_solid ? 'currentColor' : 'none',
		$is_solid ? 'none' : 'currentColor',
		$a11y,
		$paths
	);
}

/**
 * Print an inline SVG icon.
 *
 * @param string $name Icon name.
 * @param array  $args See pgds_get_icon().
 */
function pgds_icon( $name, $args = array() ) {
	// The markup is assembled from a fixed internal path table with escaped
	// attributes, so it is safe to emit; wp_kses_post() would strip SVG elements.
	echo pgds_get_icon( $name, $args ); // phpcs:ignore WordPress.Security.EscapingOutput.OutputNotEscaped
}
