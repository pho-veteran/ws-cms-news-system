<?php
/**
 * Editorial surfaces built on the shared post data model.
 *
 * @package pgds
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Return the immutable editorial-surface registry.
 *
 * Category slugs are configuration; term IDs are resolved at runtime so the
 * workflow remains portable between WordPress installations.
 *
 * @return array<string,array<string,mixed>>
 */
function pgds_editorial_surfaces() {
	return array(
		'article'            => array(
			'label'         => 'Bài viết',
			'singular'      => 'bài viết',
			'icon'          => 'dashicons-media-document',
			'primary_slugs' => array(
				'tin-phat-su',
				'song-an-lanh',
				'am-thuc-chay',
				'loi-song-xanh',
				'phat-tich',
				'tot-doi-dep-dao',
			),
			'fixed_primary' => '',
			'language'      => 'vi',
		),
		'emagazine'         => array(
			'label'         => 'E-magazine',
			'singular'      => 'E-magazine',
			'icon'          => 'dashicons-book-alt',
			'primary_slugs' => array( 'emagazine' ),
			'fixed_primary' => 'emagazine',
			'language'      => 'vi',
		),
		'video'             => array(
			'label'         => 'Video',
			'singular'      => 'video',
			'icon'          => 'dashicons-video-alt3',
			'primary_slugs' => array( 'video' ),
			'fixed_primary' => 'video',
			'language'      => 'vi',
		),
		'vietnam-buddhism' => array(
			'label'         => 'Vietnam Buddhism',
			'singular'      => 'Vietnam Buddhism article',
			'icon'          => 'dashicons-translation',
			'primary_slugs' => array( 'vietnam-buddhism' ),
			'fixed_primary' => 'vietnam-buddhism',
			'language'      => 'en',
		),
	);
}

/**
 * Sanitize a surface key against the registry.
 *
 * @param mixed $surface Candidate surface.
 * @return string Empty when invalid.
 */
function pgds_sanitize_editorial_surface( $surface ) {
	$surface = is_scalar( $surface ) ? sanitize_key( (string) $surface ) : '';

	return isset( pgds_editorial_surfaces()[ $surface ] ) ? $surface : '';
}

/**
 * Resolve one surface from a canonical primary-category slug.
 *
 * @param string $slug Category slug.
 * @return string Empty when the slug does not define a surface.
 */
function pgds_editorial_surface_from_slug( $slug ) {
	foreach ( pgds_editorial_surfaces() as $surface => $definition ) {
		if ( in_array( $slug, $definition['primary_slugs'], true ) ) {
			return $surface;
		}
	}

	return '';
}

/**
 * Classify explicit primary/category values without reading a post.
 *
 * @param int   $primary_id  Primary category term ID.
 * @param int[] $assigned_ids Assigned category term IDs.
 * @return array{surface:string,valid:bool,reason:string,primary_id:int,primary_slug:string}
 */
function pgds_classify_editorial_values( $primary_id, array $assigned_ids ) {
	$primary_id   = absint( $primary_id );
	$assigned_ids = array_map( 'intval', $assigned_ids );
	$term         = $primary_id ? get_term( $primary_id, 'category' ) : null;

	if ( ! $primary_id ) {
		return array(
			'surface'      => 'article',
			'valid'        => false,
			'reason'       => 'missing_primary',
			'primary_id'   => 0,
			'primary_slug' => '',
		);
	}

	if ( ! $term instanceof WP_Term || ! in_array( $primary_id, $assigned_ids, true ) ) {
		return array(
			'surface'      => 'article',
			'valid'        => false,
			'reason'       => 'invalid_primary',
			'primary_id'   => $primary_id,
			'primary_slug' => $term instanceof WP_Term ? $term->slug : '',
		);
	}

	$surface = pgds_editorial_surface_from_slug( $term->slug );
	if ( ! $surface ) {
		return array(
			'surface'      => 'article',
			'valid'        => false,
			'reason'       => 'unsupported_primary',
			'primary_id'   => $primary_id,
			'primary_slug' => $term->slug,
		);
	}

	return array(
		'surface'      => $surface,
		'valid'        => true,
		'reason'       => '',
		'primary_id'   => $primary_id,
		'primary_slug' => $term->slug,
	);
}

/**
 * Classify a post for the admin workflow.
 *
 * Invalid and legacy classifications intentionally fall back to Article so no
 * post disappears from every editorial list.
 *
 * @param int $post_id Post ID.
 * @return array{surface:string,valid:bool,reason:string,primary_id:int,primary_slug:string}
 */
function pgds_get_editorial_classification( $post_id ) {
	$post_id      = absint( $post_id );
	$primary_id   = (int) get_post_meta( $post_id, '_pgds_primary_cat', true );
	$assigned_ids = wp_get_post_categories( $post_id, array( 'fields' => 'ids' ) );
	$assigned_ids = is_array( $assigned_ids ) ? array_map( 'intval', $assigned_ids ) : array();

	return pgds_classify_editorial_values( $primary_id, $assigned_ids );
}

/**
 * Read the allowlisted surface carried by the current admin request.
 *
 * @return string Empty when absent or invalid.
 */
function pgds_requested_editorial_surface() {
	if ( isset( $_GET['pgds_surface'] ) ) {
		return pgds_sanitize_editorial_surface( wp_unslash( $_GET['pgds_surface'] ) );
	}
	if ( isset( $_POST['pgds_surface'] ) ) {
		return pgds_sanitize_editorial_surface( wp_unslash( $_POST['pgds_surface'] ) );
	}

	return '';
}

/**
 * Resolve the active editor surface from request context or stored data.
 *
 * @param int $post_id Optional post ID.
 * @return string
 */
function pgds_current_editorial_surface( $post_id = 0 ) {
	$requested = pgds_requested_editorial_surface();
	if ( $requested ) {
		return $requested;
	}

	if ( $post_id ) {
		return pgds_get_editorial_classification( $post_id )['surface'];
	}

	return 'article';
}

/**
 * Build a native Posts-list URL for a surface.
 *
 * @param string $surface Surface key.
 * @param array  $args    Additional query arguments.
 * @return string
 */
function pgds_editorial_list_url( $surface, array $args = array() ) {
	$surface = pgds_sanitize_editorial_surface( $surface );
	$surface = $surface ? $surface : 'article';

	return add_query_arg(
		array_merge(
			array(
				'post_type'    => 'post',
				'pgds_surface' => $surface,
			),
			$args
		),
		admin_url( 'edit.php' )
	);
}

/**
 * Build a native Add Post URL for a surface.
 *
 * @param string $surface Surface key.
 * @return string
 */
function pgds_editorial_new_url( $surface ) {
	$surface = pgds_sanitize_editorial_surface( $surface );
	$surface = $surface ? $surface : 'article';

	return add_query_arg(
		array(
			'post_type'    => 'post',
			'pgds_surface' => $surface,
		),
		admin_url( 'post-new.php' )
	);
}

/**
 * Resolve the target category slug for a classification submission.
 *
 * @param string $surface     Surface key.
 * @param string $article_slug Article primary slug when selecting Article.
 * @return string|WP_Error
 */
function pgds_editorial_target_slug( $surface, $article_slug = '' ) {
	$surface     = pgds_sanitize_editorial_surface( $surface );
	$definitions = pgds_editorial_surfaces();

	if ( ! $surface ) {
		return new WP_Error( 'pgds_invalid_editorial_surface' );
	}

	if ( 'article' !== $surface ) {
		return $definitions[ $surface ]['fixed_primary'];
	}

	$article_slug = sanitize_title( $article_slug );
	if ( ! in_array( $article_slug, $definitions['article']['primary_slugs'], true ) ) {
		return new WP_Error( 'pgds_article_category_required' );
	}

	return $article_slug;
}

/**
 * Atomically apply the workflow classification as far as WordPress APIs allow.
 *
 * The previous category set and primary metadata are restored if either write
 * fails. Secondary categories are preserved; only the previous primary is
 * replaced when the surface changes.
 *
 * @param int    $post_id      Post ID.
 * @param string $surface      Surface key.
 * @param string $article_slug Article primary slug when selecting Article.
 * @return int|WP_Error Target term ID or error.
 */
function pgds_apply_editorial_classification( $post_id, $surface, $article_slug = '' ) {
	$post_id = absint( $post_id );
	$post    = get_post( $post_id );
	if ( ! $post instanceof WP_Post || 'post' !== $post->post_type ) {
		return new WP_Error( 'pgds_invalid_editorial_post' );
	}

	$target_slug = pgds_editorial_target_slug( $surface, $article_slug );
	if ( is_wp_error( $target_slug ) ) {
		return $target_slug;
	}

	$target = pgds_category_term( $target_slug );
	if ( ! $target instanceof WP_Term ) {
		return new WP_Error( 'pgds_missing_editorial_category' );
	}

	$previous_categories = wp_get_post_categories( $post_id, array( 'fields' => 'ids' ) );
	$previous_categories = is_array( $previous_categories ) ? array_map( 'intval', $previous_categories ) : array();
	$previous_primary    = (int) get_post_meta( $post_id, '_pgds_primary_cat', true );
	$next_categories     = $previous_categories;

	if ( $previous_primary && $previous_primary !== (int) $target->term_id ) {
		$next_categories = array_values( array_diff( $next_categories, array( $previous_primary ) ) );
	}
	$next_categories[] = (int) $target->term_id;
	$next_categories   = array_values( array_unique( array_map( 'intval', $next_categories ) ) );

	$set_result = wp_set_post_categories( $post_id, $next_categories, false );
	if ( is_wp_error( $set_result ) ) {
		return new WP_Error( 'pgds_classification_write_failed' );
	}

	$updated = update_post_meta( $post_id, '_pgds_primary_cat', (int) $target->term_id );
	if ( false === $updated && (int) get_post_meta( $post_id, '_pgds_primary_cat', true ) !== (int) $target->term_id ) {
		wp_set_post_categories( $post_id, $previous_categories, false );
		if ( $previous_primary ) {
			update_post_meta( $post_id, '_pgds_primary_cat', $previous_primary );
		} else {
			delete_post_meta( $post_id, '_pgds_primary_cat' );
		}

		return new WP_Error( 'pgds_classification_write_failed' );
	}

	return (int) $target->term_id;
}

/**
 * Pre-classify specialized auto-drafts created through their Add New route.
 *
 * @param int     $post_id Post ID.
 * @param WP_Post $post    Inserted post.
 * @param bool    $update  Whether this was an update.
 */
function pgds_classify_specialized_auto_draft( $post_id, $post, $update ) {
	if ( $update || ! $post instanceof WP_Post || 'post' !== $post->post_type || 'auto-draft' !== $post->post_status ) {
		return;
	}

	$surface     = pgds_requested_editorial_surface();
	$definitions = pgds_editorial_surfaces();
	if ( ! $surface || empty( $definitions[ $surface ]['fixed_primary'] ) || ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	pgds_apply_editorial_classification( $post_id, $surface );
}
add_action( 'wp_after_insert_post', 'pgds_classify_specialized_auto_draft', 5, 3 );

/**
 * Register the editor-only grouping for E-magazine block patterns.
 */
function pgds_register_editorial_pattern_category() {
	if ( function_exists( 'register_block_pattern_category' ) ) {
		register_block_pattern_category(
			'pgds-emagazine',
			array( 'label' => __( 'PGDS E-magazine', 'pgds' ) )
		);
	}
}
add_action( 'init', 'pgds_register_editorial_pattern_category' );
