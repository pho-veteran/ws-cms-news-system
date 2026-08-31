<?php
/**
 * Template tags - shared display helpers.
 *
 * @package pgds
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Print the .pgds-art image frame. Has thumbnail -> img (srcset, width/height from WP);
 * no thumbnail -> gradient placeholder (no layout shift thanks to ratio class).
 *
 * @param int|WP_Post $post        Post.
 * @param string      $size        Registered image size.
 * @param string      $ratio_class Ratio class (e.g. 'pgds-ratio-card').
 * @param bool        $eager       LCP: eager + high fetchpriority.
 */
function pgds_art( $post, $size = 'pgds-card', $ratio_class = 'pgds-ratio-card', $eager = false ) {
	$post = get_post( $post );
	echo '<div class="pgds-art ' . esc_attr( $ratio_class ) . '">';
	if ( $post && has_post_thumbnail( $post ) ) {
		$attr = array(
			'loading'  => $eager ? 'eager' : 'lazy',
			'decoding' => 'async',
		);
		if ( $eager ) {
			$attr['fetchpriority'] = 'high';
		}
		echo get_the_post_thumbnail( $post, $size, $attr );
	}
	echo '</div>';
}

/**
 * Sapo: _pgds_sapo meta, falls back to excerpt.
 *
 * @param int|WP_Post $post Post.
 * @return string
 */
function pgds_sapo( $post ) {
	$post = get_post( $post );
	if ( ! $post ) {
		return '';
	}
	$sapo = get_post_meta( $post->ID, '_pgds_sapo', true );
	if ( $sapo ) {
		return $sapo;
	}
	return wp_strip_all_tags( get_the_excerpt( $post ) );
}

/**
 * Primary category: _pgds_primary_cat meta, falls back to the first category.
 *
 * @param int|WP_Post $post Post.
 * @return WP_Term|null
 */
function pgds_primary_cat( $post ) {
	$post = get_post( $post );
	if ( ! $post ) {
		return null;
	}
	$id = (int) get_post_meta( $post->ID, '_pgds_primary_cat', true );
	if ( $id ) {
		$term = get_term( $id, 'category' );
		if ( $term instanceof WP_Term ) {
			return $term;
		}
	}
	$cats = get_the_category( $post->ID );
	return $cats ? $cats[0] : null;
}

/**
 * Top-level category slug (used to set data-cat for label coloring).
 *
 * @param WP_Term|null $term Term.
 * @return string
 */
function pgds_top_cat_slug( $term ) {
	if ( ! $term instanceof WP_Term ) {
		return '';
	}
	while ( $term->parent ) {
		$parent = get_term( $term->parent, 'category' );
		if ( ! $parent instanceof WP_Term ) {
			break;
		}
		$term = $parent;
	}
	return $term->slug;
}

/**
 * Print the colored category label.
 *
 * @param int|WP_Post $post Post.
 */
function pgds_cat_label( $post ) {
	$term = pgds_primary_cat( $post );
	if ( ! $term ) {
		return;
	}
	printf(
		'<a class="pgds-cat-label" data-cat="%s" href="%s">%s</a>',
		esc_attr( pgds_top_cat_slug( $term ) ),
		esc_url( get_term_link( $term ) ),
		esc_html( $term->name )
	);
}

/**
 * Vietnamese weekday name for a timestamp.
 *
 * Built from the numeric day of week rather than date_i18n( 'l' ) on purpose: the
 * site renders Vietnamese to readers, but the WordPress locale is frequently en_US
 * (no vi language pack installed), which would print "Tuesday" in the header of a
 * Vietnamese newspaper. Deriving the name here makes the output correct regardless
 * of which locale or language pack the install happens to have.
 *
 * @param int $timestamp Unix timestamp.
 * @return string
 */
function pgds_weekday_vi( $timestamp ) {
	// 'w': 0 = Sunday .. 6 = Saturday.
	$names = array(
		'Chủ nhật',
		'Thứ Hai',
		'Thứ Ba',
		'Thứ Tư',
		'Thứ Năm',
		'Thứ Sáu',
		'Thứ Bảy',
	);
	$index = (int) date_i18n( 'w', $timestamp );
	return $names[ $index ] ?? '';
}

/**
 * Vietnamese "Thứ Ba, 01/09/2026" for the header date.
 *
 * @param int|null $timestamp Unix timestamp; defaults to now (site timezone).
 * @return string
 */
function pgds_date_full_vi( $timestamp = null ) {
	$timestamp = $timestamp ?? current_datetime()->getTimestamp();
	return pgds_weekday_vi( $timestamp ) . ', ' . date_i18n( 'd/m/Y', $timestamp );
}

/**
 * Vietnamese "Tháng 09 năm 2026" for the calendar widget.
 *
 * Every literal letter is backslash-escaped. Unescaped 'T', 'h', 'n' and 'g' are
 * date() format characters (timezone, 12-hour, month, timezone offset), which is
 * what produced output like "+0702á92 09 năm 2026".
 *
 * @param int|null $timestamp Unix timestamp; defaults to now (site timezone).
 * @return string
 */
function pgds_month_year_vi( $timestamp = null ) {
	$timestamp = $timestamp ?? current_datetime()->getTimestamp();
	return date_i18n( '\T\h\á\n\g m \n\ă\m Y', $timestamp );
}

/**
 * Relative published time in Vietnamese (e.g. "2 giờ trước"), falling back to a date.
 *
 * human_time_diff() is not used because it returns English under an en_US locale,
 * which yielded mixed-language output like "7 hours trước".
 *
 * @param int|WP_Post $post Post.
 * @return string
 */
function pgds_time_ago( $post ) {
	$post = get_post( $post );
	if ( ! $post ) {
		return '';
	}
	$ts   = get_post_timestamp( $post );
	$diff = time() - $ts;

	// Future-dated (scheduled) posts: show the date rather than a negative diff.
	if ( $diff < 0 ) {
		return get_the_date( 'd/m/Y', $post );
	}

	if ( $diff < MINUTE_IN_SECONDS ) {
		return 'Vừa xong';
	}
	if ( $diff < HOUR_IN_SECONDS ) {
		return (int) floor( $diff / MINUTE_IN_SECONDS ) . ' phút trước';
	}
	if ( $diff < DAY_IN_SECONDS ) {
		return (int) floor( $diff / HOUR_IN_SECONDS ) . ' giờ trước';
	}
	if ( $diff < 2 * DAY_IN_SECONDS ) {
		return 'Hôm qua';
	}
	if ( $diff < 7 * DAY_IN_SECONDS ) {
		return (int) floor( $diff / DAY_IN_SECONDS ) . ' ngày trước';
	}
	return get_the_date( 'd/m/Y', $post );
}

/**
 * Estimated reading time in minutes, from the word count.
 *
 * Counts words by splitting on whitespace rather than with str_word_count(), which
 * only recognises ASCII letters and therefore treats Vietnamese diacritics as word
 * boundaries — "Phật giáo" counted as four words, not two, systematically
 * overstating the count on this site.
 *
 * @param int|WP_Post $post Post.
 * @return int Minutes, minimum 1.
 */
function pgds_reading_time( $post ) {
	$post = get_post( $post );
	if ( ! $post instanceof WP_Post ) {
		return 1;
	}

	$text  = trim( wp_strip_all_tags( $post->post_content ) );
	$words = '' === $text ? 0 : count( preg_split( '/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY ) );

	// 200 wpm is the conventional estimate for adult prose reading.
	return max( 1, (int) ceil( $words / 200 ) );
}

/**
 * The post's canonical YouTube ID.
 *
 * @param int|WP_Post $post Post.
 * @return string
 */
function pgds_video_id( $post ) {
	$post = get_post( $post );
	return $post ? (string) get_post_meta( $post->ID, '_pgds_youtube_id', true ) : '';
}

/**
 * Format duration in seconds -> "MM:SS" or "H:MM:SS".
 *
 * @param int $seconds Seconds.
 * @return string
 */
function pgds_format_duration( $seconds ) {
	$seconds = (int) $seconds;
	if ( $seconds <= 0 ) {
		return '';
	}
	$h = floor( $seconds / 3600 );
	$m = floor( ( $seconds % 3600 ) / 60 );
	$s = $seconds % 60;
	if ( $h > 0 ) {
		return sprintf( '%d:%02d:%02d', $h, $m, $s );
	}
	return sprintf( '%d:%02d', $m, $s );
}

/**
 * Play button SVG (shared).
 */
function pgds_play_svg() {
	echo '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M8 5v14l11-7z"/></svg>';
}

/**
 * Breadcrumb (Home > Category > Title).
 *
 * @param int|WP_Post $post Post (optional).
 */
function pgds_breadcrumb( $post = null ) {
	$items = array();
	$items[] = array( 'label' => __( 'Trang chủ', 'pgds' ), 'url' => home_url( '/' ) );

	if ( is_singular( 'post' ) ) {
		$term = pgds_primary_cat( $post ?: get_post() );
		if ( $term ) {
			$items[] = array( 'label' => $term->name, 'url' => get_term_link( $term ) );
		}
		$items[] = array( 'label' => get_the_title( $post ?: get_post() ), 'url' => '' );
	} elseif ( is_category() || is_tax() || is_tag() ) {
		$items[] = array( 'label' => single_term_title( '', false ), 'url' => '' );
	} elseif ( is_search() ) {
		$items[] = array( 'label' => __( 'Tìm kiếm', 'pgds' ), 'url' => '' );
	} elseif ( is_page() ) {
		$items[] = array( 'label' => get_the_title(), 'url' => '' );
	}

	echo '<nav class="pgds-breadcrumb" aria-label="' . esc_attr__( 'Đường dẫn', 'pgds' ) . '"><div class="pgds-wrap"><ol>';
	foreach ( $items as $it ) {
		if ( $it['url'] ) {
			printf( '<li><a href="%s">%s</a></li>', esc_url( $it['url'] ), esc_html( $it['label'] ) );
		} else {
			printf( '<li aria-current="page">%s</li>', esc_html( $it['label'] ) );
		}
	}
	echo '</ol></div></nav>';
}
