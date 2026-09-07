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
 * Validate a primary category against the canonical vocabulary and post assignment.
 *
 * @param mixed      $value        Candidate term ID.
 * @param int        $post_id      Post ID. Zero checks only the canonical vocabulary.
 * @param int[]|null $assigned_ids Optional assigned category IDs; when supplied, validates assignment.
 * @return int Valid term ID or zero.
 */
function pgds_validate_primary_category_id( $value, $post_id = 0, $assigned_ids = null ) {
	$term_id = absint( $value );
	if ( ! $term_id ) {
		return 0;
	}

	$term = get_term( $term_id, 'category' );
	if ( ! $term instanceof WP_Term || ! in_array( $term->slug, pgds_category_slugs(), true ) ) {
		return 0;
	}

	if ( $post_id && null === $assigned_ids ) {
		$assigned_ids = wp_get_post_categories( $post_id );
	}
	if ( null !== $assigned_ids && ! in_array( $term_id, array_map( 'intval', (array) $assigned_ids ), true ) ) {
		return 0;
	}

	return $term_id;
}

/**
 * Sanitize primary-category meta written through REST or other meta APIs.
 *
 * Normal writes validate the closed vocabulary here and validate assignment after the
 * post write. The reconciler may preserve a legacy ID temporarily so it can migrate it.
 *
 * @param mixed  $value          Candidate term ID.
 * @param string $meta_key       Registered meta key.
 * @param string $object_type    Registered object type.
 * @param string $object_subtype Registered object subtype.
 * @return int
 */

function pgds_sanitize_primary_category_meta( $value, $meta_key = '', $object_type = '', $object_subtype = '' ) {
	unset( $meta_key, $object_type, $object_subtype );

	if ( pgds_category_migration_context() ) {
		return absint( $value );
	}

	return pgds_validate_primary_category_id( $value );
}

/**
 * Remove stale primary-category metadata after category assignments change.
 *
 * @param int $post_id Post ID.
 */
function pgds_reconcile_post_primary_category( $post_id ) {
	if ( pgds_category_migration_context() || 'post' !== get_post_type( $post_id ) ) {
		return;
	}

	$stored = (int) get_post_meta( $post_id, '_pgds_primary_cat', true );
	if ( $stored && ! pgds_validate_primary_category_id( $stored, $post_id ) ) {
		delete_post_meta( $post_id, '_pgds_primary_cat' );
	}
}
add_action( 'set_object_terms', 'pgds_reconcile_post_primary_category', 20, 1 );
add_action( 'wp_after_insert_post', 'pgds_reconcile_post_primary_category', 20, 1 );
add_action( 'added_post_meta', 'pgds_validate_saved_primary_category_meta', 20, 4 );
add_action( 'updated_post_meta', 'pgds_validate_saved_primary_category_meta', 20, 4 );

/**
 * Enforce assignment after a primary-category meta write.
 *
 * @param int    $meta_id    Metadata row ID.
 * @param int    $post_id    Post ID.
 * @param string $meta_key   Metadata key.
 * @param mixed  $meta_value Metadata value.
 */
function pgds_validate_saved_primary_category_meta( $meta_id, $post_id, $meta_key, $meta_value ) {
	unset( $meta_id );
	if ( '_pgds_primary_cat' !== $meta_key || pgds_category_migration_context() ) {
		return;
	}
	if ( ! pgds_validate_primary_category_id( $meta_value, $post_id ) ) {
		delete_post_meta( $post_id, '_pgds_primary_cat' );
	}
}

/**
 * Return an assigned canonical primary category with a deterministic fallback.
 *
 * @param int|WP_Post $post Post.
 * @return WP_Term|null
 */
function pgds_primary_cat( $post ) {
	$post = get_post( $post );
	if ( ! $post || 'post' !== $post->post_type ) {
		return null;
	}

	$assigned_ids = array_map( 'intval', wp_get_post_categories( $post->ID ) );
	$primary_id   = pgds_validate_primary_category_id( get_post_meta( $post->ID, '_pgds_primary_cat', true ), $post->ID, $assigned_ids );
	if ( $primary_id ) {
		$term = get_term( $primary_id, 'category' );
		return $term instanceof WP_Term ? $term : null;
	}

	$canonical_order = array_flip( pgds_category_slugs() );
	$candidates      = array();
	foreach ( $assigned_ids as $assigned_id ) {
		$term = get_term( $assigned_id, 'category' );
		if ( $term instanceof WP_Term && isset( $canonical_order[ $term->slug ] ) ) {
			$candidates[] = $term;
		}
	}

	usort(
		$candidates,
		static function ( $left, $right ) use ( $canonical_order ) {
			return $canonical_order[ $left->slug ] <=> $canonical_order[ $right->slug ];
		}
	);

	return $candidates[0] ?? null;
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
 * Relative published time in Vietnamese (e.g. "2 giờ trước").
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

	$diff = current_time( 'timestamp', true ) - get_post_timestamp( $post );

	// Scheduled posts need a calendar date instead of a negative relative time.
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
	if ( $diff < 30 * DAY_IN_SECONDS ) {
		return (int) floor( $diff / DAY_IN_SECONDS ) . ' ngày trước';
	}
	if ( $diff < YEAR_IN_SECONDS ) {
		return (int) floor( $diff / ( 30 * DAY_IN_SECONDS ) ) . ' tháng trước';
	}

	return (int) floor( $diff / YEAR_IN_SECONDS ) . ' năm trước';
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
 * Validate a canonical YouTube video ID.
 *
 * @param mixed $value Candidate ID.
 * @return string Valid ID or an empty string.
 */
function pgds_validate_youtube_id( $value ) {
	$value = trim( (string) $value );
	return preg_match( '/^[A-Za-z0-9_-]{11}$/', $value ) ? $value : '';
}

/**
 * Return the post's syntactically valid canonical YouTube ID.
 *
 * @param int|WP_Post $post Post.
 * @return string
 */
function pgds_video_id( $post ) {
	$post = get_post( $post );
	return $post ? pgds_validate_youtube_id( get_post_meta( $post->ID, '_pgds_youtube_id', true ) ) : '';
}

/**
 * Return the closed detail-layout policy value for a post.
 *
 * Specialized layouts require an explicitly stored, valid, assigned primary category.
 * Deterministic category fallback is intentionally not used for layout classification.
 *
 * @param int|WP_Post $post Post.
 * @return string article|emagazine|video|vietnam-buddhism
 */
function pgds_detail_layout( $post ) {
	$post = get_post( $post );
	if ( ! $post instanceof WP_Post || 'post' !== $post->post_type ) {
		return 'article';
	}

	$primary_id = pgds_validate_primary_category_id(
		get_post_meta( $post->ID, '_pgds_primary_cat', true ),
		$post->ID
	);
	if ( ! $primary_id ) {
		return 'article';
	}

	$primary = get_term( $primary_id, 'category' );
	if ( ! $primary instanceof WP_Term ) {
		return 'article';
	}

	if ( 'emagazine' === $primary->slug ) {
		return 'emagazine';
	}
	if ( 'vietnam-buddhism' === $primary->slug ) {
		return 'vietnam-buddhism';
	}
	if (
		'video' === $primary->slug &&
		pgds_video_id( $post ) &&
		'1' !== (string) get_post_meta( $post->ID, '_pgds_video_unavailable', true )
	) {
		return 'video';
	}

	return 'article';
}

/**
 * Editorial author name: custom meta first, then WP user fallback.
 *
 * @param int|WP_Post $post Post.
 * @return string
 */
function pgds_display_author( $post ): string {
	$post = get_post( $post );
	if ( ! $post ) {
		return '';
	}
	$custom = get_post_meta( $post->ID, '_pgds_display_author', true );
	if ( '' !== trim( (string) $custom ) ) {
		return $custom;
	}
	$author = get_userdata( $post->post_author );
	return $author ? $author->display_name : '';
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
