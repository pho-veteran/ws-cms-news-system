<?php
/**
 * Query 11 block trang chu - deterministic, khong transient (proposal §4.4).
 *
 * Nguyen tac:
 *  - Slot curated (featured/photo) uu tien truoc.
 *  - Query category theo batch, loai ID da dung ($used_ids chia se).
 *  - Fallback tat dinh khi curated trong: bai moi nhat cua category.
 *  - Thu tu dedup tuong minh: curated -> media -> grid1 -> three-cat -> grid2 -> sidebar.
 *  - KHONG transient: toan trang da FastCGI cache; ~11 WP_Query voi Redis 50-150ms/MISS.
 *
 * @package pgds
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Bo theo doi ID da dung tren trang chu (session per-request).
 */
class PGDS_Used_Ids {
	/** @var int[] */
	private static $ids = array();

	public static function reset() {
		self::$ids = array();
	}

	/** @return int[] */
	public static function all() {
		return self::$ids;
	}

	/**
	 * @param int[]|WP_Post[] $items
	 */
	public static function mark( $items ) {
		foreach ( $items as $it ) {
			$id = $it instanceof WP_Post ? $it->ID : (int) $it;
			if ( $id && ! in_array( $id, self::$ids, true ) ) {
				self::$ids[] = $id;
			}
		}
	}
}

/**
 * Query bai theo category slug, loai ID da dung, danh dau va tra ve.
 *
 * @param string|string[] $category_slug Slug category (hoac mang).
 * @param int             $count         So bai.
 * @param array           $extra         WP_Query args bo sung.
 * @return WP_Post[]
 */
function pgds_query_posts( $category_slug, $count, $extra = array() ) {
	$args = array(
		'post_type'           => 'post',
		'post_status'         => 'publish',
		'posts_per_page'      => $count,
		'ignore_sticky_posts' => true,
		'no_found_rows'       => true,
		'post__not_in'        => PGDS_Used_Ids::all(),
		'orderby'             => 'date',
		'order'               => 'DESC',
	);

	if ( ! empty( $category_slug ) ) {
		$args['category_name'] = is_array( $category_slug ) ? implode( ',', $category_slug ) : $category_slug;
	}

	$args  = array_merge( $args, $extra );
	$q     = new WP_Query( $args );
	$posts = $q->posts;
	PGDS_Used_Ids::mark( $posts );
	return $posts;
}

/**
 * Query slot curated (featured) theo rank, fallback bai moi nhat.
 *
 * @param int      $rank_from Rank tu.
 * @param int      $rank_to   Rank den.
 * @param int      $count     So bai can (fallback bu du).
 * @param string   $fallback_cat Slug fallback (rong = toan site).
 * @return WP_Post[]
 */
function pgds_query_featured( $rank_from, $rank_to, $count, $fallback_cat = '' ) {
	$q = new WP_Query(
		array(
			'post_type'      => 'post',
			'post_status'    => 'publish',
			'posts_per_page' => $count,
			'no_found_rows'  => true,
			'post__not_in'   => PGDS_Used_Ids::all(),
			'meta_key'       => '_pgds_feature_rank',
			'orderby'        => 'meta_value_num',
			'order'          => 'ASC',
			'meta_query'     => array(
				array(
					'key'     => '_pgds_is_featured',
					'value'   => '1',
				),
				array(
					'key'     => '_pgds_feature_rank',
					'value'   => array( $rank_from, $rank_to ),
					'type'    => 'NUMERIC',
					'compare' => 'BETWEEN',
				),
			),
		)
	);
	$posts = $q->posts;
	PGDS_Used_Ids::mark( $posts );

	// Fallback bu du.
	if ( count( $posts ) < $count ) {
		$need   = $count - count( $posts );
		$posts  = array_merge( $posts, pgds_query_posts( $fallback_cat, $need ) );
	}
	return $posts;
}

/**
 * Bai xem nhieu nhat (sidebar). Uu tien comment_count, fallback moi nhat.
 * Duoc phep trung voi noi dung khac tren trang (danh sach tham khao).
 *
 * @param int $count So bai.
 * @return WP_Post[]
 */
function pgds_query_popular( $count = 5 ) {
	$q = new WP_Query(
		array(
			'post_type'      => 'post',
			'post_status'    => 'publish',
			'posts_per_page' => $count,
			'no_found_rows'  => true,
			'orderby'        => array(
				'comment_count' => 'DESC',
				'date'          => 'DESC',
			),
		)
	);
	return $q->posts;
}

/**
 * Cot chuyen muc (three-category): 1 feat + N mini.
 *
 * @param string $slug  Category slug.
 * @param int    $mini  So mini.
 * @return array{feat: ?WP_Post, mini: WP_Post[]}
 */
function pgds_query_cat_column( $slug, $mini = 2 ) {
	$posts = pgds_query_posts( $slug, $mini + 1 );
	$feat  = array_shift( $posts );
	return array(
		'feat' => $feat,
		'mini' => $posts,
	);
}

/**
 * Tra ve toan bo du lieu 11 block trang chu.
 *
 * @return array
 */
function pgds_home_blocks() {
	PGDS_Used_Ids::reset();

	// (1) Slot curated: lead + 3 secondary.
	$lead      = pgds_query_featured( 1, 1, 1 );
	$secondary = pgds_query_featured( 2, 4, 3 );

	// (2) Panel Tin anh.
	$photo = new WP_Query(
		array(
			'post_type'      => 'post',
			'post_status'    => 'publish',
			'posts_per_page' => 5,
			'no_found_rows'  => true,
			'post__not_in'   => PGDS_Used_Ids::all(),
			'meta_query'     => array(
				array(
					'key'   => '_pgds_photo_story',
					'value' => '1',
				),
			),
		)
	);
	PGDS_Used_Ids::mark( $photo->posts );

	// (3) Media block: 1 feature + 4 thumb (cat video), 3 bullet (chi infographic).
	// Bullet query 'infographic-emagazine' RIENG (khong query cha 'media' de
	// tranh gianh bai video cua thumb).
	$media_feature = pgds_query_posts( 'video', 1 );
	$media_thumbs  = pgds_query_posts( 'video', 4 );
	$media_bullets = pgds_query_posts( 'infographic-emagazine', 3 );

	// (4) Content grid 1: Tin Phat su 3 card + 7 list.
	$phatsu_cards = pgds_query_posts( 'tin-phat-su', 3 );
	$phatsu_list  = pgds_query_posts( 'tin-phat-su', 7 );

	// (5) Three-category.
	$col_song  = pgds_query_cat_column( 'song-an-lanh', 2 );
	$col_phat  = pgds_query_cat_column( 'phat-tich', 2 );
	$col_tot   = pgds_query_cat_column( 'tot-doi-dep-dao', 2 );

	// (6) Content grid 2: Vietnam Buddhism truoc (block chuyen biet, tranh bi
	// mixed_list "an" het), roi mixed_list (catch-all moi category con lai).
	$vn_list    = pgds_query_posts( 'vietnam-buddhism', 3 );
	$mixed_list = pgds_query_posts( '', 5 );

	// (7) Sidebar: popular (co the trung), teaching CPT, lunar CPT.
	$popular  = pgds_query_popular( 5 );
	$teaching = get_posts(
		array(
			'post_type'      => 'pgds_teaching',
			'posts_per_page' => 4,
			'post_status'    => 'publish',
		)
	);
	$lunar = get_posts(
		array(
			'post_type'      => 'pgds_lunar_note',
			'posts_per_page' => 1,
			'post_status'    => 'publish',
		)
	);

	return array(
		'lead'          => $lead[0] ?? null,
		'secondary'     => $secondary,
		'photo'         => $photo->posts,
		'media_feature' => $media_feature[0] ?? null,
		'media_thumbs'  => $media_thumbs,
		'media_bullets' => $media_bullets,
		'phatsu_cards'  => $phatsu_cards,
		'phatsu_list'   => $phatsu_list,
		'columns'       => array(
			array( 'slug' => 'song-an-lanh', 'label' => 'Sống an lành', 'data' => $col_song ),
			array( 'slug' => 'phat-tich', 'label' => 'Phật tích', 'data' => $col_phat ),
			array( 'slug' => 'tot-doi-dep-dao', 'label' => 'Tốt đời – đẹp đạo', 'data' => $col_tot ),
		),
		'mixed_list'    => $mixed_list,
		'vn_list'       => $vn_list,
		'popular'       => $popular,
		'teaching'      => $teaching,
		'lunar'         => $lunar[0] ?? null,
	);
}
