<?php
/**
 * Custom post type + taxonomy + seed category.
 *
 * CPT:  pgds_teaching (Buddha's teachings), pgds_lunar_note (lunar calendar)
 * Tax:  pgds_topic (topic, cross-cutting category)
 *
 * @package pgds
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Every canonical category slug, flattened.
 *
 * The import's target vocabulary. §9.1 requires "Source-category -> target-category
 * mapping (an explicit table)", and without a closed list there was nothing for a mapping
 * to be checked against: `import` called pgds_ensure_category() on whatever slug appeared
 * in the JSON, so a source category nobody had mapped — or a plain typo — silently CREATED
 * a new category. The nav, the front-page blocks (§4.4) and the three-column section (§9 of
 * the layout) are all built from this fixed set, so an extra term is invisible on the site
 * while quietly holding articles no reader can reach through navigation.
 *
 * Derived from pgds_category_tree() rather than duplicated, so the two cannot drift.
 *
 * @return string[] Slugs, parents and children together.
 */
function pgds_category_slugs() {
	$slugs = array();
	foreach ( pgds_category_tree() as $parent_slug => $node ) {
		$slugs[] = $parent_slug;
		foreach ( array_keys( (array) ( $node['children'] ?? array() ) ) as $child_slug ) {
			$slugs[] = $child_slug;
		}
	}
	return $slugs;
}

/**
 * Category tree matching the 7-item nav (proposal §4.1).
 * parent-slug => array( 'label', 'description', 'children' => array( child-slug => label ) ).
 *
 * Descriptions exist because category.php and archive.php both render
 * `.pgds-page-head__desc` from term_description(), and NO term had a description — so a
 * category landing page was a bare list of headlines under a heading, which reads as a
 * search result rather than a curated section. On a news site the section intro is what
 * tells a reader what they have arrived at, and it is also the text a search engine shows
 * for the section. Verified the styling was already correct and only the content was
 * missing: 490px measure (70ch), 24px below the heading, no overflow at 360px.
 *
 * Children are intentionally left without descriptions: they are narrow leaf topics whose
 * parent already explains the area, and a description per leaf would be filler.
 *
 * @return array
 */
function pgds_category_tree() {
	return array(
		'tin-phat-su'      => array(
			'label'       => 'Tin Phật sự',
			'description' => 'Tin tức về hoạt động Phật sự của Giáo hội, các tự viện và cộng đồng Phật tử trên cả nước.',
			'children'    => array(
				'tin-giao-hoi'   => 'Tin Giáo hội',
				'su-kien-le-hoi' => 'Sự kiện – Lễ hội',
			),
		),
		'song-an-lanh'     => array(
			'label'       => 'Sống an lành',
			'description' => 'Ăn chay, thiền tập và lối sống xanh — những thực hành giúp đời sống thường ngày trở nên nhẹ nhàng hơn.',
			'children'    => array(
				'am-thuc-chay'  => 'Ẩm thực chay',
				'loi-song-xanh' => 'Lối sống xanh',
			),
		),
		'phat-tich'        => array(
			'label'       => 'Phật tích',
			'description' => 'Chùa, am và các di tích — danh thắng Phật giáo: kiến trúc, lịch sử và giá trị văn hoá của từng ngôi cổ tự.',
			'children'    => array(
				'chua-am'            => 'Chùa – Am',
				'di-tich-danh-thang' => 'Di tích – Danh thắng',
			),
		),
		'media'            => array(
			'label'       => 'Media',
			'description' => 'Video, infographic và emagazine: những câu chuyện Phật giáo kể bằng hình ảnh và âm thanh.',
			'children'    => array(
				'video'                 => 'Video',
				'infographic-emagazine' => 'Infographic – Emagazine',
			),
		),
		'tot-doi-dep-dao'  => array(
			'label'       => 'Tốt đời – đẹp đạo',
			'description' => 'Người tốt việc tốt và các hoạt động thiện nguyện — tinh thần từ bi thể hiện qua việc làm cụ thể.',
			'children'    => array(
				'nguoi-tot-viec-tot' => 'Người tốt việc tốt',
				'thien-nguyen'       => 'Thiện nguyện',
			),
		),
		'vietnam-buddhism' => array(
			'label'       => 'Vietnam Buddhism',
			'description' => 'Vietnamese Buddhism for international readers: heritage, practice and community life.',
			'children'    => array(),
		),
	);
}

/**
 * Register CPTs + taxonomy.
 */
function pgds_register_cpt_tax() {
	// --- CPT: Buddha's teachings --------------------------------------------
	register_post_type(
		'pgds_teaching',
		array(
			'labels'       => array(
				'name'          => __( 'Lời Phật dạy', 'pgds' ),
				'singular_name' => __( 'Lời dạy', 'pgds' ),
				'add_new_item'  => __( 'Thêm lời dạy', 'pgds' ),
				'edit_item'     => __( 'Sửa lời dạy', 'pgds' ),
			),
			'public'       => true,
			'show_in_rest' => true,
			'menu_icon'    => 'dashicons-format-quote',
			'menu_position' => 21,
			'supports'     => array( 'title', 'editor', 'thumbnail' ),
			'has_archive'  => false,
			'rewrite'      => array( 'slug' => 'loi-phat-day' ),
		)
	);

	// --- CPT: Lunar calendar -------------------------------------------------
	register_post_type(
		'pgds_lunar_note',
		array(
			'labels'       => array(
				'name'          => __( 'Lịch Vạn Niên', 'pgds' ),
				'singular_name' => __( 'Ghi chú lịch', 'pgds' ),
			),
			'public'       => false,
			'show_ui'      => true,
			'show_in_rest' => true,
			'menu_icon'    => 'dashicons-calendar-alt',
			'menu_position' => 22,
			'supports'     => array( 'title', 'editor' ),
			'has_archive'  => false,
		)
	);

	// --- Taxonomy: topic -----------------------------------------------------
	register_taxonomy(
		'pgds_topic',
		array( 'post' ),
		array(
			'labels'            => array(
				'name'          => __( 'Chủ đề', 'pgds' ),
				'singular_name' => __( 'Chủ đề', 'pgds' ),
			),
			'public'            => true,
			'hierarchical'      => false,
			'show_in_rest'      => true,
			'show_admin_column' => true,
			'rewrite'           => array( 'slug' => 'chu-de' ),
		)
	);
}
add_action( 'init', 'pgds_register_cpt_tax' );

/**
 * Seed the category tree - runs once (idempotent, checks existence by slug).
 * Trigger via: wp eval "pgds_seed_categories();" or automatically on theme switch.
 *
 * @return array Created result (slug => term_id).
 */
function pgds_seed_categories() {
	$created = array();
	foreach ( pgds_category_tree() as $slug => $node ) {
		$parent_id        = pgds_ensure_category( $slug, $node['label'], 0, $node['description'] ?? '' );
		$created[ $slug ] = $parent_id;
		if ( ! empty( $node['children'] ) ) {
			foreach ( $node['children'] as $child_slug => $child_label ) {
				$created[ $child_slug ] = pgds_ensure_category( $child_slug, $child_label, $parent_id );
			}
		}
	}
	return $created;
}

/**
 * Create category if it doesn't exist, return term_id.
 *
 * Idempotent by slug. When the term already exists, a description is backfilled but an
 * existing one is never overwritten: re-running the seeder on a live site must not silently
 * replace copy an editor has since rewritten. Only the empty case is filled, which is what
 * makes this safe to run against production after adding descriptions to the tree.
 *
 * @param string $slug        Slug.
 * @param string $label       Display name.
 * @param int    $parent      Parent term.
 * @param string $description Optional term description (§4.1 section intro).
 * @return int
 */
function pgds_ensure_category( $slug, $label, $parent = 0, $description = '' ) {
	$existing = get_term_by( 'slug', $slug, 'category' );
	if ( $existing instanceof WP_Term ) {
		// Backfill only. '' means "the editor has not written one", not "the editor cleared it"
		// — the latter is indistinguishable, and erring toward filling an empty section intro
		// is better than leaving a category page with no explanation of itself.
		if ( '' !== $description && '' === trim( (string) $existing->description ) ) {
			wp_update_term( $existing->term_id, 'category', array( 'description' => $description ) );
		}
		return (int) $existing->term_id;
	}
	$res = wp_insert_term(
		$label,
		'category',
		array(
			'slug'        => $slug,
			'parent'      => $parent,
			'description' => $description,
		)
	);
	if ( is_wp_error( $res ) ) {
		return 0;
	}
	return (int) $res['term_id'];
}

/**
 * Automatically seed on theme activation.
 */
add_action( 'after_switch_theme', 'pgds_seed_categories' );
