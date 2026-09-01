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
 * parent-slug => array( child-slug => label ), key 'label' is for the parent itself.
 *
 * @return array
 */
function pgds_category_tree() {
	return array(
		'tin-phat-su'      => array(
			'label'    => 'Tin Phật sự',
			'children' => array(
				'tin-giao-hoi'   => 'Tin Giáo hội',
				'su-kien-le-hoi' => 'Sự kiện – Lễ hội',
			),
		),
		'song-an-lanh'     => array(
			'label'    => 'Sống an lành',
			'children' => array(
				'am-thuc-chay' => 'Ẩm thực chay',
				'loi-song-xanh' => 'Lối sống xanh',
			),
		),
		'phat-tich'        => array(
			'label'    => 'Phật tích',
			'children' => array(
				'chua-am'             => 'Chùa – Am',
				'di-tich-danh-thang' => 'Di tích – Danh thắng',
			),
		),
		'media'            => array(
			'label'    => 'Media',
			'children' => array(
				'video'                 => 'Video',
				'infographic-emagazine' => 'Infographic – Emagazine',
			),
		),
		'tot-doi-dep-dao'  => array(
			'label'    => 'Tốt đời – đẹp đạo',
			'children' => array(
				'nguoi-tot-viec-tot' => 'Người tốt việc tốt',
				'thien-nguyen'       => 'Thiện nguyện',
			),
		),
		'vietnam-buddhism' => array(
			'label'    => 'Vietnam Buddhism',
			'children' => array(),
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
		$parent_id = pgds_ensure_category( $slug, $node['label'], 0 );
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
 * @param string $slug   Slug.
 * @param string $label  Display name.
 * @param int    $parent Parent term.
 * @return int
 */
function pgds_ensure_category( $slug, $label, $parent = 0 ) {
	$existing = get_term_by( 'slug', $slug, 'category' );
	if ( $existing instanceof WP_Term ) {
		return (int) $existing->term_id;
	}
	$res = wp_insert_term(
		$label,
		'category',
		array(
			'slug'   => $slug,
			'parent' => $parent,
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
