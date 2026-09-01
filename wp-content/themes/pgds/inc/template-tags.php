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
 * Is the sapo EDITORIAL, or auto-generated from the body?
 *
 * pgds_sapo() falls back to get_the_excerpt(), which WordPress generates from the first ~55
 * words of post_content when no manual excerpt exists. That fallback is right in a card, a
 * list item or a schema description — those show the sapo INSTEAD of the body, so a
 * generated summary is exactly what is wanted.
 *
 * It is wrong on a single article, where the sapo is printed directly above the full text:
 * the lead paragraph then repeats, word for word, the sentence immediately beneath it.
 * Observed on the pgds_teaching route once those posts got real bodies — the lead read
 * "Tứ Vô Lượng Tâm là bốn tâm rộng lớn không bờ bến: Từ là mong người khác được an vui,
 * Bi là vui trước…" and the first body paragraph began with the same words.
 *
 * A sapo is editorial when someone typed it: the `_pgds_sapo` meta field (§4.3), or a
 * hand-written post_excerpt. Anything else is a machine summary and single.php skips it.
 *
 * @param int|WP_Post $post Post.
 * @return bool
 */
function pgds_has_editorial_sapo( $post ) {
	$post = get_post( $post );
	if ( ! $post ) {
		return false;
	}
	if ( '' !== trim( (string) get_post_meta( $post->ID, '_pgds_sapo', true ) ) ) {
		return true;
	}
	// post_excerpt is empty unless an editor wrote one; the auto-generated summary is
	// produced by get_the_excerpt() at render time and never stored.
	return '' !== trim( (string) $post->post_excerpt );
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

	/*
	 * Every branch must add at least one item beyond "Trang chủ".
	 *
	 * The guard used to be is_singular( 'post' ), which is narrower than the set of routes
	 * that call this function. pgds_teaching singles (5 published, publicly queryable),
	 * author archives and date archives all fell through every branch and rendered a
	 * breadcrumb containing exactly one item — a <nav aria-label="Đường dẫn"> whose entire
	 * content is a link to the page the reader is already able to reach from the masthead.
	 * That is worse than no breadcrumb: it is an announced landmark with nothing in it.
	 */
	if ( is_singular( 'post' ) ) {
		$term = pgds_primary_cat( $post ?: get_post() );
		if ( $term ) {
			$items[] = array( 'label' => $term->name, 'url' => get_term_link( $term ) );
		}
		$items[] = array( 'label' => get_the_title( $post ?: get_post() ), 'url' => '' );
	} elseif ( is_singular() && ! is_page() && ! is_attachment() ) {
		/*
		 * Any other single: pgds_teaching today, and anything added later without having to
		 * remember to touch this function. The post type's own label is the intermediate
		 * crumb ("Lời Phật dạy"), linked to its archive when it has one and plain text when
		 * it does not — pgds_teaching is has_archive => false, so it is plain text rather
		 * than a link to a 404.
		 *
		 * `! is_page()` matters: is_singular() is TRUE for pages, so without it this branch
		 * shadowed the is_page() branch below and rendered WordPress's internal English
		 * label as a section — "Trang chủ › Pages › Giới thiệu" on a Vietnamese news site.
		 * Attachments are excluded for the same reason: core 301s them to the file, so the
		 * crumb would describe a page nobody can land on.
		 */
		$queried = $post ?: get_post();
		$type    = $queried ? get_post_type_object( $queried->post_type ) : null;
		if ( $type ) {
			$archive = get_post_type_archive_link( $type->name );
			$items[] = array(
				'label' => $type->labels->name,
				'url'   => $archive ? $archive : '',
			);
		}
		$items[] = array( 'label' => get_the_title( $queried ), 'url' => '' );
	} elseif ( is_category() || is_tax() || is_tag() ) {
		$items[] = array( 'label' => single_term_title( '', false ), 'url' => '' );
	} elseif ( is_author() ) {
		/* translators: %s: author display name */
		$items[] = array( 'label' => sprintf( __( 'Tác giả: %s', 'pgds' ), get_the_author() ), 'url' => '' );
	} elseif ( is_year() || is_month() || is_day() ) {
		// Reuses the archive title so the crumb and the <h1> cannot disagree, and so the
		// Vietnamese month formatting (pgds_month_year_vi) is not duplicated here.
		$items[] = array( 'label' => wp_strip_all_tags( get_the_archive_title() ), 'url' => '' );
	} elseif ( is_post_type_archive() ) {
		$items[] = array( 'label' => wp_strip_all_tags( post_type_archive_title( '', false ) ), 'url' => '' );
	} elseif ( is_search() ) {
		$items[] = array( 'label' => __( 'Tìm kiếm', 'pgds' ), 'url' => '' );
		/*
		 * No is_404() branch on purpose. 404.php does not call this function, and it should
		 * not: the page already carries "Không tìm thấy trang" as its <h1>, so the crumb
		 * would only repeat it — and a breadcrumb asserts a position in the hierarchy, which
		 * a URL that does not exist has none of. The 404 offers search and recent posts as
		 * the way forward instead. (An is_404() branch was written here and removed once it
		 * became clear it could never execute.)
		 */
	} elseif ( is_page() ) {
		/*
		 * Ancestors first, so a nested page shows its real position rather than jumping from
		 * the home page straight to itself — but PUBLISHED ancestors only.
		 *
		 * get_post_ancestors() walks post_parent straight out of the database with no
		 * post_status filter, and get_the_title() performs no read-capability check: for a
		 * private post it simply prefixes private_title_format and hands back the title.
		 * Reproduced anonymously on a published child of a private parent:
		 *
		 *   Trang chủ › Private: Tài liệu mật › Con của trang mật
		 *
		 * So the title of unpublished editorial planning was disclosed to every visitor,
		 * along with a link that 404s for them. That is a leak of content, not a display
		 * bug, and escaping does not help — the problem is which posts were included.
		 *
		 * Skipping a non-public ancestor keeps the crumb at two items ("Trang chủ › <page>"),
		 * which still satisfies the guard below.
		 */
		foreach ( array_reverse( get_post_ancestors( get_the_ID() ) ) as $ancestor_id ) {
			if ( 'publish' !== get_post_status( $ancestor_id ) ) {
				continue;
			}
			$items[] = array(
				'label' => get_the_title( $ancestor_id ),
				'url'   => get_permalink( $ancestor_id ),
			);
		}
		$items[] = array( 'label' => get_the_title(), 'url' => '' );
	}

	/*
	 * A single-item breadcrumb is not a breadcrumb. If no branch matched — a route this
	 * function has not been taught about — render nothing rather than an empty landmark.
	 */
	if ( count( $items ) < 2 ) {
		return;
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
