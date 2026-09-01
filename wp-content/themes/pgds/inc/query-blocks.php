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
 * Query posts by category, then top up from the rest of the site if the category
 * could not supply enough.
 *
 * Why a top-up exists: the front page requests far more posts across its 11 blocks
 * than any single category holds, and the dedup pass (§4.4) means earlier blocks
 * consume posts first. Without a top-up, later blocks render empty or near-empty,
 * which on a 1fr + 320px layout leaves a tall void beside a full sidebar. A short
 * block is an editorial failure, not a neutral outcome, so it is filled with the
 * newest unused posts from anywhere on the site.
 *
 * The top-up is still deterministic (newest-first, excluding used IDs) so the page
 * is stable between FastCGI cache fills.
 *
 * @param string|string[] $category_slug Category slug (or array).
 * @param int             $count         Number of posts.
 * @param array           $extra         Extra WP_Query args.
 * @param bool            $top_up        Backfill from any category when short.
 * @return WP_Post[]
 */
function pgds_query_posts_filled( $category_slug, $count, $extra = array(), $top_up = true ) {
	$posts = pgds_query_posts( $category_slug, $count, $extra );

	if ( $top_up && count( $posts ) < $count && ! empty( $category_slug ) ) {
		$need = $count - count( $posts );
		// '' = no category restriction. Used IDs (including the ones just marked)
		// are excluded inside pgds_query_posts(), so this cannot duplicate.
		$posts = array_merge( $posts, pgds_query_posts( '', $need, $extra ) );
	}

	return $posts;
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
 * Most read posts (sidebar). Prioritises comment_count, falls back to most recent.
 *
 * Participates in the §4.4 deduplication pass, which lists the sidebar as its final step
 * ("curated slots -> media block -> content grid 1 -> three-category block -> content
 * grid 2 -> sidebar") under the rule "a post must not appear twice".
 *
 * This query used to opt out, with the comment "Allowed to overlap with other content on
 * the page (reference list)". Measured on the rendered front page, that made the widget
 * completely redundant: of 40 distinct posts on the page, exactly 5 appeared in more than
 * one region, and ALL FIVE were the popular widget's own entries —
 *
 *   an-chay-dung-cach-fixture-2        [feature-grid, popular]
 *   dai-le-phat-dan-pl-2570            [feature-grid, popular]
 *   khoa-an-cu-kiet-ha-3-mien          [feature-grid, popular]
 *   toan-canh-dai-le-phat-dan-video    [feature-grid, popular]
 *   dai-le-phat-dan-pl-2570-fixture-1  [phatsu, popular]
 *
 * i.e. 5 of 5 slots repeated a headline already visible higher up the same screen. Every
 * other region was clean. A "most read" box that shows only what the reader has just
 * scrolled past costs a sidebar slot and surfaces nothing.
 *
 * Excluding used IDs would normally shorten the list, so the query asks for extra rows and
 * filters, then tops up: the widget still renders exactly $count items.
 *
 * @param int  $count Number of posts.
 * @param bool $dedup Exclude posts already used on this request. Front page passes true;
 *                    single/category sidebars have no competing blocks, so the tracker is
 *                    empty there and the flag makes no difference.
 * @return WP_Post[]
 */
function pgds_query_popular( $count = 5, $dedup = true ) {
	$used = $dedup ? PGDS_Used_Ids::all() : array();

	$base = array(
		'post_type'      => 'post',
		'post_status'    => 'publish',
		'no_found_rows'  => true,
		'orderby'        => array(
			'comment_count' => 'DESC',
			'date'          => 'DESC',
		),
	);

	$q = new WP_Query(
		array_merge(
			$base,
			array(
				'posts_per_page' => $count,
				'post__not_in'   => $used,
			)
		)
	);
	$posts = $q->posts;

	/*
	 * Top up when the front page has consumed so many posts that fewer than $count remain
	 * unused. A short "most read" list reads as a fault rather than as an honest ranking,
	 * and on a small site the exclusion set can easily exceed the post count — so the
	 * fallback allows repeats rather than rendering three items in a five-item box. Ranking
	 * order is preserved: the deduplicated rows come first.
	 */
	if ( count( $posts ) < $count ) {
		$have = wp_list_pluck( $posts, 'ID' );
		$fill = new WP_Query(
			array_merge(
				$base,
				array(
					'posts_per_page' => $count - count( $posts ),
					'post__not_in'   => $have,
				)
			)
		);
		$posts = array_merge( $posts, $fill->posts );
	}

	PGDS_Used_Ids::mark( $posts );
	return $posts;
}

/**
 * Category column (three-category): 1 feature + N mini.
 *
 * @param string $slug  Category slug.
 * @param int    $mini  Number of mini items.
 * @return array{feat: ?WP_Post, mini: WP_Post[]}
 */
function pgds_query_cat_column( $slug, $mini = 2 ) {
	$posts = pgds_query_posts_filled( $slug, $mini + 1 );
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
	// The media block is topped up from the wider 'media' parent rather than the whole
	// site: a text news post in a video thumbnail grid (with a play badge over it)
	// would misrepresent the content. If 'media' cannot fill it, the grid adapts its
	// column count instead.
	$media_feature = pgds_query_posts( 'video', 1 );
	$media_thumbs  = pgds_query_posts( 'video', 4 );
	if ( count( $media_thumbs ) < 4 ) {
		$media_thumbs = array_merge(
			$media_thumbs,
			pgds_query_posts( 'media', 4 - count( $media_thumbs ) )
		);
	}
	$media_bullets = pgds_query_posts( 'infographic-emagazine', 3 );

	// ---------------------------------------------------------------------------
	// PASS 1 - every category-scoped block takes only posts from its OWN category.
	//
	// Ordering matters here (§4.4). Letting an early block top up from the whole
	// site would consume the posts a later, narrower block depends on: filling
	// grid 1 site-wide emptied the three-category row completely, which reads as
	// broken rather than merely short. So category ownership is resolved first and
	// the site-wide backfill happens in pass 2, over what is genuinely left over.
	// ---------------------------------------------------------------------------
	$phatsu_cards = pgds_query_posts( 'tin-phat-su', 3 );
	$phatsu_list  = pgds_query_posts( 'tin-phat-su', 7 );

	$col_song = pgds_query_cat_column( 'song-an-lanh', 2 );
	$col_phat = pgds_query_cat_column( 'phat-tich', 2 );
	$col_tot  = pgds_query_cat_column( 'tot-doi-dep-dao', 2 );

	$vn_list = pgds_query_posts( 'vietnam-buddhism', 3 );

	// ---------------------------------------------------------------------------
	// PASS 2 - backfill the blocks whose emptiness is most visible, from whatever
	// remains. Grid 1 sits beside the tall popular + calendar sidebar, so a short
	// main column there leaves the largest void on the page; it is filled first.
	// ---------------------------------------------------------------------------
	if ( count( $phatsu_cards ) < 3 ) {
		$phatsu_cards = array_merge(
			$phatsu_cards,
			pgds_query_posts( '', 3 - count( $phatsu_cards ) )
		);
	}
	if ( count( $phatsu_list ) < 7 ) {
		$phatsu_list = array_merge(
			$phatsu_list,
			pgds_query_posts( '', 7 - count( $phatsu_list ) )
		);
	}

	// mixed_list is the catch-all tail: it takes what nothing else claimed, so it
	// is queried last and is allowed to come up short without leaving a hole.
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
