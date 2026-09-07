<?php
/**
 * Custom post types, taxonomy, and immutable category schema.
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
 * Immutable category tree used by navigation, routing, imports, and setup.
 *
 * The root and child order is part of the frontend contract. Descriptions are also
 * canonical configuration and are repaired by the reconciler when they drift.
 *
 * @return array<string,array{label:string,description:string,children:array<string,string>}>
 */

function pgds_category_tree() {
	return array(
		'tin-phat-su'      => array(
			'label'       => 'Tin Phật sự',
			'description' => 'Tin tức về hoạt động Phật sự của Giáo hội, các tự viện và cộng đồng Phật tử trên cả nước.',
			'children'    => array(),
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
			'children'    => array(),
		),
		'media'            => array(
			'label'       => 'Media',
			'description' => 'Video và E-magazine: những câu chuyện Phật giáo kể bằng hình ảnh và âm thanh.',
			'children'    => array(
				'video'     => 'Video',
				'emagazine' => 'E-magazine',
			),
		),
		'tot-doi-dep-dao'  => array(
			'label'       => 'Tốt đời – đẹp đạo',
			'description' => 'Những tấm gương và hoạt động thể hiện tinh thần từ bi qua việc làm cụ thể.',
			'children'    => array(),
		),
		'vietnam-buddhism' => array(
			'label'       => 'Vietnam Buddhism',
			'description' => 'Vietnamese Buddhism for international readers: heritage, practice, and community life.',
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
 * Category schema version stored after a successful migration.
 */
const PGDS_CATEGORY_SCHEMA_VERSION = 3;

/**
 * Legacy category slugs and their canonical destinations.
 *
 * @return array<string,string>
 */
function pgds_legacy_category_map() {
	return array(
		'tin-giao-hoi'          => 'tin-phat-su',
		'su-kien-le-hoi'        => 'tin-phat-su',
		'chua-am'               => 'phat-tich',
		'di-tich-danh-thang'    => 'phat-tich',
		'nguoi-tot-viec-tot'    => 'tot-doi-dep-dao',
		'thien-nguyen'          => 'tot-doi-dep-dao',
		'infographic-emagazine' => 'emagazine',
	);
}

/**
 * Flatten the canonical tree into term definitions.
 *
 * @return array<string,array{label:string,description:string,parent:string}>
 */
function pgds_category_definitions() {
	$definitions = array();

	foreach ( pgds_category_tree() as $slug => $node ) {
		$definitions[ $slug ] = array(
			'label'       => $node['label'],
			'description' => $node['description'] ?? '',
			'parent'      => '',
		);

		foreach ( (array) ( $node['children'] ?? array() ) as $child_slug => $child_label ) {
			$definitions[ $child_slug ] = array(
				'label'       => $child_label,
				'description' => '',
				'parent'      => $slug,
			);
		}
	}

	return $definitions;
}

/**
 * Return one canonical category term by slug.
 *
 * @param string $slug Canonical category slug.
 * @return WP_Term|null
 */
function pgds_category_term( $slug ) {
	if ( ! in_array( $slug, pgds_category_slugs(), true ) ) {
		return null;
	}

	$term = get_term_by( 'slug', $slug, 'category' );
	return $term instanceof WP_Term ? $term : null;
}

/**
 * Whether a theme-owned taxonomy migration is currently mutating categories.
 *
 * @param bool|null $enabled Enter on true or leave one nested level on false.
 * @return bool
 */
function pgds_category_migration_context( $enabled = null ) {
	static $depth = 0;

	if ( true === $enabled ) {
		++$depth;
	} elseif ( false === $enabled ) {
		$depth = max( 0, $depth - 1 );
	}

	return $depth > 0;
}

/**
 * Create or repair one canonical category.
 *
 * @param string $slug        Canonical slug.
 * @param string $label       Canonical display name.
 * @param int    $parent      Canonical parent term ID.
 * @param string $description Canonical description.
 * @param int    $term_id     Existing canonical term ID during an in-place legacy rename.
 * @return int|WP_Error
 */
function pgds_ensure_category( $slug, $label, $parent = 0, $description = '', $term_id = 0 ) {
	$existing = $term_id ? get_term( $term_id, 'category' ) : null;
	if ( ! $existing instanceof WP_Term ) {
		$existing = get_term_by( 'slug', $slug, 'category' );
	}

	if ( $existing instanceof WP_Term ) {
		$changes = array();
		if ( $label !== $existing->name ) {
			$changes['name'] = $label;
		}
		if ( $slug !== $existing->slug ) {
			$changes['slug'] = $slug;
		}
		if ( $parent !== (int) $existing->parent ) {
			$changes['parent'] = $parent;
		}
		if ( $description !== (string) $existing->description ) {
			$changes['description'] = $description;
		}

		if ( $changes ) {
			$result = wp_update_term( $existing->term_id, 'category', $changes );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
		}

		return (int) $existing->term_id;
	}

	$result = wp_insert_term(
		$label,
		'category',
		array(
			'slug'        => $slug,
			'parent'      => $parent,
			'description' => $description,
		)
	);

	return is_wp_error( $result ) ? $result : (int) $result['term_id'];
}

/**
 * Move posts and primary-category metadata from a legacy term.
 *
 * Target relationships are added before the legacy term is deleted. WordPress receives
 * the canonical target as the deletion fallback, preserving relationships even if a
 * concurrent write lands between the read and delete steps.
 *
 * @param WP_Term $legacy    Legacy term.
 * @param int     $target_id Canonical target term ID.
 * @return true|WP_Error
 */
function pgds_migrate_category_term( $legacy, $target_id ) {
	$target = get_term( $target_id, 'category' );
	if ( ! $target instanceof WP_Term || ! in_array( $target->slug, pgds_category_slugs(), true ) ) {
		return new WP_Error( 'pgds_category_migration_failed', 'The canonical migration target is invalid.' );
	}

	$post_ids = get_objects_in_term( $legacy->term_id, 'category' );
	if ( is_wp_error( $post_ids ) ) {
		return $post_ids;
	}
	$post_ids = array_map( 'intval', $post_ids );

	$primary_post_ids = get_posts(
		array(
			'post_type'      => 'any',
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'no_found_rows'  => true,
			'meta_key'       => '_pgds_primary_cat',
			'meta_value'     => (string) $legacy->term_id,
		)
	);

	foreach ( $post_ids as $post_id ) {
		$added = wp_set_object_terms( $post_id, array( $target_id ), 'category', true );
		if ( is_wp_error( $added ) || ! has_term( $target_id, 'category', $post_id ) ) {
			return new WP_Error( 'pgds_category_migration_failed', sprintf( 'Could not assign canonical category %1$d to post %2$d.', $target_id, $post_id ) );
		}
	}

	foreach ( $primary_post_ids as $post_id ) {
		if ( false === update_post_meta( $post_id, '_pgds_primary_cat', $target_id ) && $target_id !== (int) get_post_meta( $post_id, '_pgds_primary_cat', true ) ) {
			return new WP_Error( 'pgds_category_migration_failed', sprintf( 'Could not migrate the primary category on post %d.', $post_id ) );
		}
	}

	$deleted = wp_delete_term( $legacy->term_id, 'category', array( 'default' => $target_id ) );
	if ( is_wp_error( $deleted ) || false === $deleted || 0 === $deleted ) {
		return is_wp_error( $deleted ) ? $deleted : new WP_Error( 'pgds_category_migration_failed', sprintf( 'Could not retire category %s.', $legacy->slug ) );
	}

	return true;
}

/**
 * Retire one noncanonical category without dropping post relationships.
 *
 * Posts assigned only to the retired term receive the canonical default category. Posts
 * that already have a canonical category keep their existing assignments. Stale primary
 * metadata is removed because unknown terms have no reviewed one-to-one migration.
 *
 * @param WP_Term $term          Noncanonical category.
 * @param int     $default_id    Canonical default category term ID.
 * @param int[]   $canonical_ids Canonical category term IDs.
 * @return true|WP_Error
 */
function pgds_retire_noncanonical_category( $term, $default_id, $canonical_ids ) {
	$default = get_term( $default_id, 'category' );
	if ( ! $default instanceof WP_Term || ! in_array( $default->slug, pgds_category_slugs(), true ) ) {
		return new WP_Error( 'pgds_category_migration_failed', 'The canonical default category is invalid.' );
	}

	$post_ids = get_objects_in_term( $term->term_id, 'category' );
	if ( is_wp_error( $post_ids ) ) {
		return $post_ids;
	}

	foreach ( array_map( 'intval', $post_ids ) as $post_id ) {
		$assigned_ids = array_map( 'intval', wp_get_post_categories( $post_id ) );
		if ( ! array_intersect( $assigned_ids, $canonical_ids ) ) {
			$added = wp_set_object_terms( $post_id, array( $default_id ), 'category', true );
			if ( is_wp_error( $added ) || ! has_term( $default_id, 'category', $post_id ) ) {
				return new WP_Error( 'pgds_category_migration_failed', sprintf( 'Could not assign the canonical default category to post %d.', $post_id ) );
			}
		}
	}

	$primary_post_ids = get_posts(
		array(
			'post_type'      => 'any',
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'no_found_rows'  => true,
			'meta_key'       => '_pgds_primary_cat',
			'meta_value'     => (string) $term->term_id,
		)
	);
	foreach ( $primary_post_ids as $post_id ) {
		if ( ! delete_post_meta( $post_id, '_pgds_primary_cat', $term->term_id ) && '' !== (string) get_post_meta( $post_id, '_pgds_primary_cat', true ) ) {
			return new WP_Error( 'pgds_category_migration_failed', sprintf( 'Could not remove stale primary-category metadata from post %d.', $post_id ) );
		}
	}

	$deleted = wp_delete_term( $term->term_id, 'category', array( 'default' => $default_id ) );
	if ( is_wp_error( $deleted ) || false === $deleted || 0 === $deleted ) {
		return is_wp_error( $deleted ) ? $deleted : new WP_Error( 'pgds_category_migration_failed', sprintf( 'Could not retire noncanonical category %s.', $term->slug ) );
	}

	return true;
}

/**
 * Remove every category outside the reviewed canonical vocabulary.
 *
 * @param int   $default_id    Canonical default category term ID.
 * @param int[] $canonical_ids Canonical category term IDs.
 * @return true|WP_Error
 */
function pgds_retire_noncanonical_categories( $default_id, $canonical_ids ) {
	$terms = get_terms(
		array(
			'taxonomy'   => 'category',
			'hide_empty' => false,
		)
	);
	if ( is_wp_error( $terms ) ) {
		return $terms;
	}

	$canonical_slugs = pgds_category_slugs();
	foreach ( $terms as $term ) {
		if ( in_array( $term->slug, $canonical_slugs, true ) ) {
			continue;
		}
		$result = pgds_retire_noncanonical_category( $term, $default_id, $canonical_ids );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
	}

	return true;
}

function pgds_seed_categories() {
	if ( did_action( 'init' ) ) {
		pgds_lock_category_capabilities();
	}

	pgds_category_migration_context( true );
	$term_ids   = array();
	$stored_ids = get_option( 'pgds_category_term_ids', array() );
	$stored_ids = is_array( $stored_ids ) ? array_map( 'absint', $stored_ids ) : array();

	try {
		foreach ( pgds_category_definitions() as $slug => $definition ) {
			$parent_id   = $definition['parent'] ? ( $term_ids[ $definition['parent'] ] ?? 0 ) : 0;
			$existing_id = 0;
			$canonical   = get_term_by( 'slug', $slug, 'category' );

			if ( $canonical instanceof WP_Term ) {
				$existing_id = (int) $canonical->term_id;
			} elseif ( ! empty( $stored_ids[ $slug ] ) ) {
				$stored = get_term( $stored_ids[ $slug ], 'category' );
				if ( $stored instanceof WP_Term ) {
					$existing_id = (int) $stored->term_id;
				}
			}

			if ( ! $existing_id && 'emagazine' === $slug ) {
				$legacy = get_term_by( 'slug', 'infographic-emagazine', 'category' );
				if ( $legacy instanceof WP_Term ) {
					$existing_id = (int) $legacy->term_id;
				}
			}

			$result = pgds_ensure_category( $slug, $definition['label'], $parent_id, $definition['description'], $existing_id );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
			$term_ids[ $slug ] = $result;
		}

		$default_id = $term_ids['tin-phat-su'];
		if ( $default_id !== (int) get_option( 'default_category', 0 ) ) {
			$updated = update_option( 'default_category', $default_id );
			if ( ! $updated && $default_id !== (int) get_option( 'default_category', 0 ) ) {
				return new WP_Error( 'pgds_category_migration_failed', 'Could not set the canonical default category.' );
			}
		}

		foreach ( pgds_legacy_category_map() as $legacy_slug => $target_slug ) {
			$legacy = get_term_by( 'slug', $legacy_slug, 'category' );
			if ( ! $legacy instanceof WP_Term ) {
				continue;
			}

			$result = pgds_migrate_category_term( $legacy, $term_ids[ $target_slug ] );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
		}

		$result = pgds_retire_noncanonical_categories( $default_id, array_values( $term_ids ) );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$stored = update_option( 'pgds_category_term_ids', $term_ids, false );
		if ( ! $stored && $term_ids !== get_option( 'pgds_category_term_ids', array() ) ) {
			return new WP_Error( 'pgds_category_migration_failed', 'Could not record the canonical category term IDs.' );
		}

		if ( PGDS_CATEGORY_SCHEMA_VERSION !== (int) get_option( 'pgds_category_schema_version', 0 ) ) {
			$updated = update_option( 'pgds_category_schema_version', PGDS_CATEGORY_SCHEMA_VERSION, false );
			if ( ! $updated && PGDS_CATEGORY_SCHEMA_VERSION !== (int) get_option( 'pgds_category_schema_version', 0 ) ) {
				return new WP_Error( 'pgds_category_migration_failed', 'Could not record the category schema version.' );
			}
			flush_rewrite_rules( false );
		}

		return $term_ids;
	} finally {
		pgds_category_migration_context( false );
	}
}

/**
 * Return an immutable-taxonomy error for external structural mutations.
 *
 * @return WP_Error
 */
function pgds_category_locked_error() {
	return new WP_Error(
		'pgds_category_locked',
		__( 'The PGDS category structure is managed in code and cannot be changed.', 'pgds' ),
		array( 'status' => 403 )
	);
}

/**
 * Reject direct category creation before WordPress writes a term.
 *
 * @param string|WP_Error $term     Term name.
 * @param string          $taxonomy Taxonomy name.
 * @return string|WP_Error
 */
function pgds_lock_category_insert( $term, $taxonomy ) {
	if ( 'category' === $taxonomy && ! pgds_category_migration_context() ) {
		return pgds_category_locked_error();
	}

	return $term;
}
add_filter( 'pre_insert_term', 'pgds_lock_category_insert', 10, 2 );

/**
 * Block direct category updates at WordPress's earliest update action.
 *
 * Core has no error-returning pre-update filter. Throwing here prevents name, slug,
 * parent, and description mutations before any term row is changed. REST and admin
 * requests are rejected by capabilities before reaching this guard.
 *
 * @param int    $term_id  Term ID.
 * @param string $taxonomy Taxonomy name.
 * @throws RuntimeException When external code tries to update a category.
 */
function pgds_lock_category_update( $term_id, $taxonomy ) {
	unset( $term_id );
	if ( 'category' === $taxonomy && ! pgds_category_migration_context() ) {
		throw new RuntimeException( 'The PGDS category structure is managed in code and cannot be changed.' );
	}
}
add_action( 'edit_terms', 'pgds_lock_category_update', 1, 2 );

/**
 * Block category deletion through WordPress's public deletion functions.
 *
 * WordPress exposes no short-circuit filter in `wp_delete_term()`. Throwing from its
 * pre-delete action is the only core-level stop that runs before relationships or term
 * rows are changed; REST and admin requests are rejected earlier with normal errors.
 *
 * @param int    $term_id  Term ID.
 * @param string $taxonomy Taxonomy name.
 * @throws RuntimeException When external code tries to delete a category.
 */
function pgds_lock_category_delete( $term_id, $taxonomy ) {
	unset( $term_id );
	if ( 'category' === $taxonomy && ! pgds_category_migration_context() ) {
		throw new RuntimeException( 'The PGDS category structure is managed in code and cannot be changed.' );
	}
}
add_action( 'pre_delete_term', 'pgds_lock_category_delete', 10, 2 );

/**
 * Remove structural category capabilities while preserving term assignment.
 *
 * Core registers built-in taxonomies before themes load, so this is applied directly
 * during `init` instead of relying on the already-fired registration action.
 */
function pgds_lock_category_capabilities() {
	$taxonomy = get_taxonomy( 'category' );
	if ( ! $taxonomy instanceof WP_Taxonomy ) {
		return;
	}

	$taxonomy->cap->manage_terms = 'pgds_manage_locked_categories';
	$taxonomy->cap->edit_terms   = 'pgds_manage_locked_categories';
	$taxonomy->cap->delete_terms = 'pgds_manage_locked_categories';
	$taxonomy->cap->assign_terms = 'edit_posts';
	$taxonomy->cap->create_terms = 'pgds_manage_locked_categories';
}
add_action( 'init', 'pgds_lock_category_capabilities', 1 );

/**
 * Remove direct category-management pages from the Posts menu.
 */
function pgds_remove_category_admin_menu() {
	remove_submenu_page( 'edit.php', 'edit-tags.php?taxonomy=category' );
}
add_action( 'admin_menu', 'pgds_remove_category_admin_menu', 999 );

/**
 * Prevent direct access to category-management screens.
 */
function pgds_block_category_admin_screen() {
	global $pagenow;

	if ( 'edit-tags.php' !== $pagenow && 'term.php' !== $pagenow ) {
		return;
	}
	$taxonomy = isset( $_GET['taxonomy'] ) ? sanitize_key( wp_unslash( $_GET['taxonomy'] ) ) : '';
	if ( 'category' !== $taxonomy ) {
		return;
	}

	wp_die(
		esc_html__( 'The PGDS category structure is managed in code and cannot be changed.', 'pgds' ),
		esc_html__( 'Category structure locked', 'pgds' ),
		array( 'response' => 403 )
	);
}
add_action( 'admin_init', 'pgds_block_category_admin_screen' );


/**
 * Resolve a requested legacy category path before WordPress returns a 404.
 *
 * @return string Canonical destination slug or an empty string.
 */
function pgds_requested_legacy_category_slug() {
	$requested_slug = get_query_var( 'category_name' );
	if ( $requested_slug ) {
		$parts          = array_values( array_filter( explode( '/', trim( (string) $requested_slug, '/' ) ) ) );
		$requested_slug = (string) end( $parts );
	}

	if ( ! $requested_slug ) {
		$category_base = trim( (string) get_option( 'category_base', 'category' ), '/' );
		$category_base = $category_base ?: 'category';
		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
		$path        = trim( (string) wp_parse_url( $request_uri, PHP_URL_PATH ), '/' );
		$prefix      = $category_base . '/';
		if ( 0 === strpos( $path, $prefix ) ) {
			$parts          = array_values( array_filter( explode( '/', substr( $path, strlen( $prefix ) ) ) ) );
			$requested_slug = (string) end( $parts );
		}
	}

	$legacy_map = pgds_legacy_category_map();
	return $legacy_map[ $requested_slug ] ?? '';
}

/**
 * Permanently redirect retired category URLs to their canonical routes.
 */
function pgds_redirect_legacy_category() {
	if ( is_admin() || wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
		return;
	}

	$target_slug = pgds_requested_legacy_category_slug();
	if ( ! $target_slug ) {
		return;
	}

	$target = get_term_by( 'slug', $target_slug, 'category' );
	if ( ! $target instanceof WP_Term ) {
		return;
	}

	$url = get_term_link( $target );
	if ( is_wp_error( $url ) ) {
		return;
	}

	wp_safe_redirect( $url, 301, 'PGDS' );
	exit;
}
add_action( 'template_redirect', 'pgds_redirect_legacy_category', 1 );

/**
 * Automatically seed on theme activation.
 */
add_action( 'after_switch_theme', 'pgds_seed_categories' );
