<?php
/**
 * Query the 11 front page blocks - deterministic, no transients (proposal §4.4).
 *
 * Principles:
 *  - Curated slots (featured/photo) take priority first.
 *  - Query categories in batches, excluding IDs already used (shared $used_ids).
 *  - Deterministic fallback when curated is empty: category's most recent post.
 *  - Explicit dedup order: curated -> media -> grid1 -> three-cat -> grid2 -> sidebar.
 *  - NO transients: the whole page is already FastCGI-cached; ~11 WP_Query with Redis is 50-150ms/MISS.
 *
 * @package pgds
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Tracks used IDs on the front page (per-request session).
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
 * Query posts by category slug, excluding IDs already used, mark and return.
 *
 * @param string|string[] $category_slug Category slug (or array).
 * @param int             $count         Number of posts.
 * @param array           $extra         Extra WP_Query args.
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
 * Query the curated (featured) slot by rank, fallback to most recent post.
 *
 * @param int      $rank_from Rank from.
 * @param int      $rank_to   Rank to.
 * @param int      $count     Number of posts needed (fallback fills the gap).
 * @param string   $fallback_cat Fallback slug (empty = whole site).
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

	// Fallback to fill the gap.
	if ( count( $posts ) < $count ) {
		$need   = $count - count( $posts );
		$posts  = array_merge( $posts, pgds_query_posts( $fallback_cat, $need ) );
	}
	return $posts;
}

/**
 * Most viewed posts (sidebar). Prioritizes comment_count, falls back to most recent.
 * Allowed to overlap with other content on the page (reference list).
 *
 * @param int $count Number of posts.
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
 * Category column (three-category): 1 feature + N mini.
 *
 * @param string $slug  Category slug.
 * @param int    $mini  Number of mini items.
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
 * Return all data for the 11 front page blocks.
 *
 * @return array
 */
function pgds_home_blocks() {
	PGDS_Used_Ids::reset();

	// (1) Curated slot: lead + 3 secondary.
	$lead      = pgds_query_featured( 1, 1, 1 );
	$secondary = pgds_query_featured( 2, 4, 3 );

	// (2) Photo story panel.
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

	// (3) Media block: 1 feature + 4 thumbs (video category), 3 bullets (infographic only).
	// Bullets query 'infographic-emagazine' SEPARATELY (don't query the parent 'media' to
	// avoid stealing video posts from the thumbs).
	$media_feature = pgds_query_posts( 'video', 1 );
	$media_thumbs  = pgds_query_posts( 'video', 4 );
	$media_bullets = pgds_query_posts( 'infographic-emagazine', 3 );

	// (4) Content grid 1: Buddhist Affairs News, 3 cards + 7 list.
	$phatsu_cards = pgds_query_posts( 'tin-phat-su', 3 );
	$phatsu_list  = pgds_query_posts( 'tin-phat-su', 7 );

	// (5) Three-category.
	$col_song  = pgds_query_cat_column( 'song-an-lanh', 2 );
	$col_phat  = pgds_query_cat_column( 'phat-tich', 2 );
	$col_tot   = pgds_query_cat_column( 'tot-doi-dep-dao', 2 );

	// (6) Content grid 2: Vietnam Buddhism first (dedicated block, to avoid
	// mixed_list "eating" it all), then mixed_list (catch-all for remaining categories).
	$vn_list    = pgds_query_posts( 'vietnam-buddhism', 3 );
	$mixed_list = pgds_query_posts( '', 5 );

	// (7) Sidebar: popular (may overlap), teaching CPT, lunar CPT.
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
