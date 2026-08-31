<?php
/**
 * Template tags - helper hien thi dung chung.
 *
 * @package pgds
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * In khung anh .pgds-art. Co thumbnail -> img (srcset, width/height tu WP);
 * khong co -> gradient placeholder (khong layout shift nho ratio class).
 *
 * @param int|WP_Post $post        Post.
 * @param string      $size        Image size da dang ky.
 * @param string      $ratio_class Class ty le (vd 'pgds-ratio-card').
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
 * Sa-po: meta _pgds_sapo, fallback excerpt.
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
 * Chuyen muc chinh: meta _pgds_primary_cat, fallback category dau tien.
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
 * Slug cap 1 cua chuyen muc (de gan data-cat lay mau nhan).
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
 * In nhan chuyen muc mau.
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
 * Thoi gian dang dang dang doc (vd "2 giờ trước").
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
	if ( $diff < DAY_IN_SECONDS ) {
		return sprintf(
			/* translators: %s human time diff */
			__( '%s trước', 'pgds' ),
			human_time_diff( $ts )
		);
	}
	return get_the_date( 'd/m/Y', $post );
}

/**
 * Thoi gian doc uoc tinh (phut) tu so tu.
 *
 * @param int|WP_Post $post Post.
 * @return int
 */
function pgds_reading_time( $post ) {
	$post  = get_post( $post );
	$words = str_word_count( wp_strip_all_tags( $post->post_content ) );
	return max( 1, (int) ceil( $words / 200 ) );
}

/**
 * YouTube ID canonical cua bai.
 *
 * @param int|WP_Post $post Post.
 * @return string
 */
function pgds_video_id( $post ) {
	$post = get_post( $post );
	return $post ? (string) get_post_meta( $post->ID, '_pgds_youtube_id', true ) : '';
}

/**
 * Dinh dang thoi luong giay -> "MM:SS" hoac "H:MM:SS".
 *
 * @param int $seconds Giay.
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
 * SVG nut play (dung chung).
 */
function pgds_play_svg() {
	echo '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M8 5v14l11-7z"/></svg>';
}

/**
 * Breadcrumb (Trang chu > Chuyen muc > Tieu de).
 *
 * @param int|WP_Post $post Post (tuy chon).
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
