<?php
/**
 * Custom post type + taxonomy + seed category.
 *
 * CPT:  pgds_teaching (Loi Phat day), pgds_lunar_note (Lich Van Nien)
 * Tax:  pgds_topic (chu de cat ngang category)
 *
 * @package pgds
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Cay category khop nav 7 muc (proposal §4.1).
 * parent-slug => array( child-slug => label ), key 'label' cho chinh no.
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
 * Dang ky CPT + taxonomy.
 */
function pgds_register_cpt_tax() {
	// --- CPT: Loi Phat day -------------------------------------------------
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

	// --- CPT: Lich Van Nien ------------------------------------------------
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

	// --- Taxonomy: chu de --------------------------------------------------
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
 * Seed category tree - chay 1 lan (idempotent, kiem tra ton tai theo slug).
 * Kich hoat qua: wp eval "pgds_seed_categories();" hoac tu dong khi switch theme.
 *
 * @return array Ket qua tao (slug => term_id).
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
 * Tao category neu chua co, tra ve term_id.
 *
 * @param string $slug   Slug.
 * @param string $label  Ten hien thi.
 * @param int    $parent Term cha.
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
 * Tu dong seed khi kich hoat theme.
 */
add_action( 'after_switch_theme', 'pgds_seed_categories' );
