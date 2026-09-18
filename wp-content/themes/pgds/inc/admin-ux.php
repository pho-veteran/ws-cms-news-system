<?php
/**
 * Admin UX: post list column (featured, video, primary category), filter.
 *
 * @package pgds
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Replace the generic Posts entry with four top-level editorial workflows.
 */
function pgds_register_editorial_surface_menus() {
	global $menu, $submenu;

	foreach ( $menu as $index => $item ) {
		if ( isset( $item[2] ) && 'edit.php' === $item[2] ) {
			unset( $menu[ $index ] );
		}
	}
	unset( $submenu['edit.php'] );

	$position = 5;
	foreach ( pgds_editorial_surfaces() as $surface => $definition ) {
		$menu[ (string) $position ] = array(
			esc_html( $definition['label'] ),
			'edit_posts',
			'edit.php?post_type=post&pgds_surface=' . rawurlencode( $surface ),
			esc_html( $definition['label'] ),
			'menu-top menu-icon-post',
			'menu-posts-' . $surface,
			$definition['icon'],
		);
		++$position;
	}
}
add_action( 'admin_menu', 'pgds_register_editorial_surface_menus', 998 );

/**
 * Keep the matching top-level workflow highlighted on native post screens.
 *
 * @param string $parent_file Current parent menu file.
 * @return string
 */
function pgds_editorial_parent_file( $parent_file ) {
	global $pagenow;

	if ( in_array( $pagenow, array( 'edit.php', 'post.php', 'post-new.php' ), true ) ) {
		$post_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0;
		$post_type = $post_id ? get_post_type( $post_id ) : ( isset( $_GET['post_type'] ) ? sanitize_key( wp_unslash( $_GET['post_type'] ) ) : 'post' );
		if ( 'post' !== $post_type ) {
			return $parent_file;
		}
		$surface = pgds_current_editorial_surface( $post_id );

		return 'edit.php?post_type=post&pgds_surface=' . rawurlencode( $surface );
	}

	return $parent_file;
}
add_filter( 'parent_file', 'pgds_editorial_parent_file' );

/**
 * Add the current surface to edit links.
 *
 * @param string $link    Edit URL.
 * @param int    $post_id Post ID.
 * @param string $context Link context.
 * @return string
 */
function pgds_editorial_edit_post_link( $link, $post_id, $context ) {
	unset( $context );

	if ( ! $link || 'post' !== get_post_type( $post_id ) ) {
		return $link;
	}

	$surface = pgds_requested_editorial_surface();
	if ( ! $surface ) {
		$surface = pgds_get_editorial_classification( $post_id )['surface'];
	}

	return add_query_arg( 'pgds_surface', $surface, $link );
}
add_filter( 'get_edit_post_link', 'pgds_editorial_edit_post_link', 10, 3 );

/**
 * Preserve the workflow on row actions that return to the post list.
 *
 * @param array   $actions Row actions.
 * @param WP_Post $post    Post object.
 * @return array
 */
function pgds_editorial_post_row_actions( $actions, $post ) {
	if ( ! $post instanceof WP_Post || 'post' !== $post->post_type ) {
		return $actions;
	}

	$surface = pgds_requested_editorial_surface();
	if ( ! $surface ) {
		$surface = pgds_get_editorial_classification( $post->ID )['surface'];
	}

	foreach ( array( 'edit', 'trash', 'untrash' ) as $action ) {
		if ( ! empty( $actions[ $action ] ) ) {
			$actions[ $action ] = preg_replace_callback(
				'/href=([\'\"])([^\'\"]+)\1/',
				static function ( $matches ) use ( $surface ) {
					$url = html_entity_decode( $matches[2], ENT_QUOTES, get_bloginfo( 'charset' ) );

					return 'href=' . $matches[1] . esc_url( add_query_arg( 'pgds_surface', $surface, $url ) ) . $matches[1];
				},
				$actions[ $action ]
			);
		}
	}

	return $actions;
}
add_filter( 'post_row_actions', 'pgds_editorial_post_row_actions', 20, 2 );

/**
 * Preserve the current workflow on post-save redirects.
 *
 * @param string $location Redirect URL.
 * @param int    $post_id Post ID.
 * @return string
 */
function pgds_editorial_redirect_post_location( $location, $post_id ) {
	if ( 'post' !== get_post_type( $post_id ) ) {
		return $location;
	}

	$surface = pgds_requested_editorial_surface();
	if ( ! $surface ) {
		$surface = pgds_get_editorial_classification( $post_id )['surface'];
	}

	return add_query_arg( 'pgds_surface', $surface, $location );
}
add_filter( 'redirect_post_location', 'pgds_editorial_redirect_post_location', 20, 2 );

/**
 * Preserve the workflow in native post-list and Add New links.
 *
 * @param string      $url     Complete admin URL.
 * @param string      $path    Requested admin path.
 * @param int|null    $blog_id Site ID.
 * @return string
 */
function pgds_editorial_admin_url( $url, $path, $blog_id ) {
	unset( $blog_id );

	global $pagenow;
	if ( ! in_array( $pagenow, array( 'edit.php', 'post.php', 'post-new.php' ), true ) ) {
		return $url;
	}

	$surface = pgds_requested_editorial_surface();
	if ( ! $surface && isset( $_GET['post'] ) ) {
		$post_id = absint( $_GET['post'] );
		if ( $post_id && 'post' === get_post_type( $post_id ) ) {
			$surface = pgds_get_editorial_classification( $post_id )['surface'];
		}
	}
	if ( ! $surface ) {
		return $url;
	}

	$path_parts = wp_parse_url( $path );
	$filename   = isset( $path_parts['path'] ) ? basename( $path_parts['path'] ) : '';
	if ( ! in_array( $filename, array( 'edit.php', 'post-new.php' ), true ) ) {
		return $url;
	}

	$query_args = array();
	if ( ! empty( $path_parts['query'] ) ) {
		parse_str( $path_parts['query'], $query_args );
	}
	if ( isset( $query_args['post_type'] ) && 'post' !== $query_args['post_type'] ) {
		return $url;
	}
	if ( isset( $query_args['pgds_surface'] ) && pgds_sanitize_editorial_surface( $query_args['pgds_surface'] ) ) {
		return $url;
	}

	return add_query_arg(
		array(
			'post_type'    => 'post',
			'pgds_surface' => $surface,
		),
		$url
	);
}
add_filter( 'admin_url', 'pgds_editorial_admin_url', 20, 3 );

/**
 * Preserve the workflow while opening a post preview.
 *
 * @param string  $preview_link Preview URL.
 * @param WP_Post $post         Post object.
 * @return string
 */
function pgds_editorial_preview_post_link( $preview_link, $post ) {
	if ( ! $preview_link || ! $post instanceof WP_Post || 'post' !== $post->post_type ) {
		return $preview_link;
	}

	$surface = pgds_requested_editorial_surface();
	if ( ! $surface ) {
		$surface = pgds_get_editorial_classification( $post->ID )['surface'];
	}

	return add_query_arg( 'pgds_surface', $surface, $preview_link );
}
add_filter( 'preview_post_link', 'pgds_editorial_preview_post_link', 10, 2 );

/**
 * Restore the originating surface after Gutenberg trashes or restores a post.
 *
 * Gutenberg returns to the post type's generic collection URL. The affected
 * post IDs remain in the notice query string, so the canonical surface can be
 * recovered without storing workflow context as metadata.
 */
function pgds_editorial_trash_redirect_context() {
	if ( pgds_requested_editorial_surface() || empty( $_GET['ids'] ) ) {
		return;
	}

	$post_type = isset( $_GET['post_type'] ) ? sanitize_key( wp_unslash( $_GET['post_type'] ) ) : 'post';
	if ( 'post' !== $post_type ) {
		return;
	}

	$ids = array_values( array_filter( array_map( 'absint', explode( ',', wp_unslash( $_GET['ids'] ) ) ) ) );
	if ( ! $ids || 'post' !== get_post_type( $ids[0] ) ) {
		return;
	}

	$notice_args = array();
	foreach ( array( 'trashed', 'untrashed', 'deleted' ) as $key ) {
		if ( isset( $_GET[ $key ] ) ) {
			$notice_args[ $key ] = absint( $_GET[ $key ] );
		}
	}
	$notice_args['ids'] = implode( ',', $ids );

	$surface = pgds_get_editorial_classification( $ids[0] )['surface'];
	wp_safe_redirect( pgds_editorial_list_url( $surface, $notice_args ) );
	exit;
}
add_action( 'load-edit.php', 'pgds_editorial_trash_redirect_context', 1 );

/**
 * Build the SQL predicate for posts with a valid primary/category pair.
 *
 * @param int[] $term_ids Allowed primary term IDs.
 * @return string
 */
function pgds_editorial_valid_pair_sql( array $term_ids ) {
	global $wpdb;

	$term_ids = array_values( array_filter( array_map( 'absint', $term_ids ) ) );
	if ( ! $term_ids ) {
		return '0 = 1';
	}

	$ids = implode( ',', $term_ids );

	return "EXISTS (
		SELECT 1
		FROM {$wpdb->postmeta} pgds_surface_meta
		INNER JOIN {$wpdb->term_relationships} pgds_surface_rel
			ON pgds_surface_rel.object_id = pgds_surface_meta.post_id
		INNER JOIN {$wpdb->term_taxonomy} pgds_surface_tax
			ON pgds_surface_tax.term_taxonomy_id = pgds_surface_rel.term_taxonomy_id
		WHERE pgds_surface_meta.post_id = {$wpdb->posts}.ID
			AND pgds_surface_meta.meta_key = '_pgds_primary_cat'
			AND pgds_surface_tax.taxonomy = 'category'
			AND pgds_surface_tax.term_id IN ({$ids})
			AND CAST(pgds_surface_meta.meta_value AS UNSIGNED) = pgds_surface_tax.term_id
	)";
}

/**
 * Resolve canonical category IDs for surface list predicates.
 *
 * @param string[] $slugs Category slugs.
 * @return int[]
 */
function pgds_editorial_term_ids( array $slugs ) {
	$ids = array();
	foreach ( $slugs as $slug ) {
		$term = pgds_category_term( $slug );
		if ( $term instanceof WP_Term ) {
			$ids[] = (int) $term->term_id;
		}
	}

	return $ids;
}

/**
 * Mark the native list query with the active editorial surface.
 *
 * @param WP_Query $query Query.
 */
function pgds_admin_surface_query( $query ) {
	global $pagenow;

	$request_post_type = isset( $_GET['post_type'] ) ? sanitize_key( wp_unslash( $_GET['post_type'] ) ) : 'post';
	if ( ! is_admin() || 'edit.php' !== $pagenow || ! $query->is_main_query() || 'post' !== $request_post_type ) {
		return;
	}

	$query->set( 'pgds_surface', pgds_current_editorial_surface() );
	if ( isset( $_GET['pgds_classification'] ) && 'needs-review' === sanitize_key( wp_unslash( $_GET['pgds_classification'] ) ) ) {
		$query->set( 'pgds_classification', 'needs-review' );
	}
}
add_action( 'pre_get_posts', 'pgds_admin_surface_query', 5 );

/**
 * Append surface classification to the native Posts-list WHERE clause.
 *
 * @param string   $where Query WHERE clause.
 * @param WP_Query $query Query.
 * @return string
 */
function pgds_admin_surface_where( $where, $query ) {
	$surface = pgds_sanitize_editorial_surface( $query->get( 'pgds_surface' ) );
	if ( ! $surface ) {
		return $where;
	}

	$definitions = pgds_editorial_surfaces();
	if ( 'article' === $surface ) {
		$specialized_ids = array();
		foreach ( array( 'emagazine', 'video', 'vietnam-buddhism' ) as $specialized ) {
			$specialized_ids = array_merge( $specialized_ids, pgds_editorial_term_ids( $definitions[ $specialized ]['primary_slugs'] ) );
		}
		$where .= ' AND NOT (' . pgds_editorial_valid_pair_sql( $specialized_ids ) . ')';
	} else {
		$surface_ids = pgds_editorial_term_ids( $definitions[ $surface ]['primary_slugs'] );
		$where      .= ' AND (' . pgds_editorial_valid_pair_sql( $surface_ids ) . ')';
	}

	if ( 'needs-review' === $query->get( 'pgds_classification' ) ) {
		$valid_ids = array();
		foreach ( $definitions as $definition ) {
			$valid_ids = array_merge( $valid_ids, pgds_editorial_term_ids( $definition['primary_slugs'] ) );
		}
		$where .= ' AND NOT (' . pgds_editorial_valid_pair_sql( $valid_ids ) . ')';
	}

	return $where;
}
add_filter( 'posts_where', 'pgds_admin_surface_where', 10, 2 );

/**
 * Add a focused view for legacy posts in the Article workflow.
 *
 * @param array $views Native list views.
 * @return array
 */
function pgds_article_classification_view( $views ) {
	if ( 'article' !== pgds_current_editorial_surface() ) {
		return $views;
	}

	$current = isset( $_GET['pgds_classification'] ) ? sanitize_key( wp_unslash( $_GET['pgds_classification'] ) ) : '';
	$views['pgds_needs_classification'] = sprintf(
		'<a href="%s"%s>%s <span class="count">(0)</span></a>',
		esc_url( pgds_editorial_list_url( 'article', array( 'pgds_classification' => 'needs-review' ) ) ),
		'needs-review' === $current ? ' class="current" aria-current="page"' : '',
		esc_html__( 'Cần phân loại', 'pgds' )
	);

	return $views;
}
add_filter( 'views_edit-post', 'pgds_article_classification_view' );

/**
 * Count posts in one native list view without dropping the surface predicate.
 *
 * @param string $surface Editorial surface.
 * @param string $view    Native view key.
 * @return int|null
 */
function pgds_editorial_view_count( $surface, $view ) {
	$all_statuses = array_keys( get_post_stati( array( 'show_in_admin_all_list' => true ) ) );
	$args         = array(
		'post_type'           => 'post',
		'post_status'         => $all_statuses,
		'posts_per_page'      => 1,
		'fields'              => 'ids',
		'ignore_sticky_posts' => true,
		'pgds_surface'        => $surface,
	);

	if ( 'mine' === $view ) {
		$args['author'] = get_current_user_id();
	} elseif ( 'sticky' === $view ) {
		$sticky_ids = array_map( 'absint', (array) get_option( 'sticky_posts', array() ) );
		if ( ! $sticky_ids ) {
			return 0;
		}
		$args['post__in'] = $sticky_ids;
	} elseif ( 'pgds_needs_classification' === $view ) {
		$args['pgds_classification'] = 'needs-review';
	} elseif ( 'all' !== $view ) {
		$statuses = get_post_stati();
		if ( ! isset( $statuses[ $view ] ) ) {
			return null;
		}
		$args['post_status'] = $view;
	}

	$query = new WP_Query( $args );

	return (int) $query->found_posts;
}

/**
 * Keep native view counts scoped to the current editorial surface.
 *
 * @param array $views Native list views.
 * @return array
 */
function pgds_editorial_surface_views( $views ) {
	$surface = pgds_current_editorial_surface();
	foreach ( $views as $view => $markup ) {
		$markup = preg_replace_callback(
			'/href="([^"]+)"/',
			static function ( $matches ) use ( $surface ) {
				$url = html_entity_decode( $matches[1], ENT_QUOTES, get_bloginfo( 'charset' ) );

				return 'href="' . esc_url( add_query_arg( 'pgds_surface', $surface, $url ) ) . '"';
			},
			$markup
		);

		$count = pgds_editorial_view_count( $surface, $view );
		if ( null === $count ) {
			$views[ $view ] = $markup;
			continue;
		}

		$views[ $view ] = preg_replace(
			'/<span class="count">\([^<]*\)<\/span>/',
			'<span class="count">(' . esc_html( number_format_i18n( $count ) ) . ')</span>',
			$markup
		);
	}

	return $views;
}
add_filter( 'views_edit-post', 'pgds_editorial_surface_views', 20 );

/**
 * Give native post screens surface-specific titles and empty states.
 *
 * @param object $labels Post labels.
 * @return object
 */
function pgds_editorial_post_type_labels( $labels ) {
	if ( ! is_admin() ) {
		return $labels;
	}

	$post_id    = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0;
	$surface    = pgds_current_editorial_surface( $post_id );
	$definition = pgds_editorial_surfaces()[ $surface ];
	$english    = 'vietnam-buddhism' === $surface;

	$labels->name               = $definition['label'];
	$labels->singular_name      = $definition['singular'];
	$labels->menu_name          = $definition['label'];
	$labels->all_items          = $english ? 'All Vietnam Buddhism articles' : 'Tất cả ' . $definition['label'];
	$labels->add_new            = $english ? 'Add new' : 'Thêm mới';
	$labels->add_new_item       = $english ? 'Add Vietnam Buddhism article' : 'Thêm ' . $definition['singular'];
	$labels->edit_item          = $english ? 'Edit Vietnam Buddhism article' : 'Sửa ' . $definition['singular'];
	$labels->new_item           = $english ? 'New Vietnam Buddhism article' : $definition['label'] . ' mới';
	$labels->view_item          = $english ? 'View article' : 'Xem ' . $definition['singular'];
	$labels->search_items       = $english ? 'Search Vietnam Buddhism' : 'Tìm ' . $definition['label'];
	$labels->not_found          = $english ? 'No Vietnam Buddhism articles found.' : 'Không tìm thấy ' . $definition['singular'] . '.';
	$labels->not_found_in_trash = $english ? 'No Vietnam Buddhism articles found in Trash.' : 'Không có ' . $definition['singular'] . ' trong Thùng rác.';

	return $labels;
}
add_filter( 'post_type_labels_post', 'pgds_editorial_post_type_labels' );

/**
 * Add column.
 *
 * @param array $cols Columns.
 * @return array
 */
function pgds_admin_columns( $cols ) {
	$new = array();
	foreach ( $cols as $key => $label ) {
		$new[ $key ] = $label;
		if ( 'title' === $key ) {
			$new['pgds_flags'] = __( 'PGDS', 'pgds' );
		}
	}
	return $new;
}
add_filter( 'manage_post_posts_columns', 'pgds_admin_columns' );

/**
 * Column content.
 *
 * @param string $col     Column.
 * @param int    $post_id Post ID.
 */
function pgds_admin_column_content( $col, $post_id ) {
	if ( 'pgds_flags' !== $col ) {
		return;
	}
	$flags = array();
	$surface = pgds_current_editorial_surface( $post_id );
	$english = 'vietnam-buddhism' === $surface;
	$classification = pgds_get_editorial_classification( $post_id );
	if ( ! $classification['valid'] ) {
		$flags[] = $english ? '⚠ Needs classification' : '⚠ Cần phân loại';
	}
	if ( '1' === get_post_meta( $post_id, '_pgds_is_featured', true ) ) {
		$rank    = (int) get_post_meta( $post_id, '_pgds_feature_rank', true );
		$flags[] = ( $english ? '★ Featured' : '★ Nổi bật' ) . ( $rank ? " (#{$rank})" : '' );
	}
	if ( '1' === get_post_meta( $post_id, '_pgds_photo_story', true ) ) {
		$flags[] = $english ? '📷 Photo story' : '📷 Tin ảnh';
	}
	if ( '1' === get_post_meta( $post_id, '_pgds_is_popular', true ) ) {
		$popular_rank = (int) get_post_meta( $post_id, '_pgds_popular_rank', true );
		$flags[]      = ( $english ? '🔥 Most read' : '🔥 Đọc nhiều' ) . ( $popular_rank ? " (#{$popular_rank})" : '' );
	}
	if ( get_post_meta( $post_id, '_pgds_youtube_id', true ) ) {
		$flags[] = '▶ Video';
	}
	echo $flags ? esc_html( implode( ' · ', $flags ) ) : '—';
}
add_action( 'manage_post_posts_custom_column', 'pgds_admin_column_content', 10, 2 );

/**
 * "Featured posts only" filter on the post list.
 */
function pgds_admin_filter_ui() {
	global $typenow;
	if ( 'post' !== $typenow ) {
		return;
	}
	$current = isset( $_GET['pgds_filter'] ) ? sanitize_key( wp_unslash( $_GET['pgds_filter'] ) ) : '';
	$surface = pgds_current_editorial_surface();
	$english = 'vietnam-buddhism' === $surface;
	?>
	<input type="hidden" name="pgds_surface" value="<?php echo esc_attr( $surface ); ?>">
	<select name="pgds_filter">
		<option value=""><?php echo esc_html( $english ? '— PGDS filter —' : '— Lọc PGDS —' ); ?></option>
		<option value="featured" <?php selected( $current, 'featured' ); ?>><?php echo esc_html( $english ? 'Featured' : 'Tin nổi bật' ); ?></option>
		<option value="photo" <?php selected( $current, 'photo' ); ?>><?php echo esc_html( $english ? 'Photo story' : 'Tin ảnh' ); ?></option>
		<option value="video" <?php selected( $current, 'video' ); ?>><?php echo esc_html( $english ? 'Has video' : 'Có video' ); ?></option>
	</select>
	<?php
}
add_action( 'restrict_manage_posts', 'pgds_admin_filter_ui' );

/**
 * Apply the filter.
 *
 * @param WP_Query $query Query.
 */
function pgds_admin_filter_apply( $query ) {
	global $pagenow;
	if ( ! is_admin() || 'edit.php' !== $pagenow || ! $query->is_main_query() ) {
		return;
	}
	if ( empty( $_GET['pgds_filter'] ) ) {
		return;
	}
	$filter = sanitize_key( wp_unslash( $_GET['pgds_filter'] ) );
	$map    = array(
		'featured' => '_pgds_is_featured',
		'photo'    => '_pgds_photo_story',
		'video'    => '_pgds_youtube_id',
	);
	if ( isset( $map[ $filter ] ) ) {
		$new_meta_query = ( 'video' === $filter )
			? array( array( 'key' => $map[ $filter ], 'compare' => 'EXISTS' ) )
			: array( array( 'key' => $map[ $filter ], 'value' => '1' ) );
		$existing_meta_query = $query->get( 'meta_query' );
		if ( empty( $existing_meta_query ) ) {
			$query->set( 'meta_query', $new_meta_query );
		} else {
			$query->set(
				'meta_query',
				array(
					'relation' => 'AND',
					$existing_meta_query,
					$new_meta_query,
				)
			);
		}
	}
}
add_action( 'pre_get_posts', 'pgds_admin_filter_apply' );

/**
 * Keep native author, category, and tag links inside the active list workflow.
 */
function pgds_editorial_list_link_context() {
	$screen = get_current_screen();
	if ( ! $screen || 'edit-post' !== $screen->id ) {
		return;
	}

	$surface = pgds_current_editorial_surface();
	?>
	<script>
	( function () {
		'use strict';
		var surface = <?php echo wp_json_encode( $surface ); ?>;
		var links = document.querySelectorAll( '#the-list a[href^="edit.php?"], .notice a[href^="edit.php?"]' );

		links.forEach( function ( link ) {
			var url = new URL( link.getAttribute( 'href' ), window.location.href );
			var postType = url.searchParams.get( 'post_type' );
			if ( url.pathname.endsWith( '/wp-admin/edit.php' ) && ( ! postType || 'post' === postType ) ) {
				url.searchParams.set( 'post_type', 'post' );
				url.searchParams.set( 'pgds_surface', surface );
				link.href = url.href;
			}
		} );
	}() );
	</script>
	<?php
}
add_action( 'admin_footer-edit.php', 'pgds_editorial_list_link_context' );

/* ===========================================================================
 * Make the PGDS meta box findable in the block editor.
 *
 * THE PROBLEM (measured, not theoretical): posts use the block editor, and the
 * block editor collects every classic `add_meta_box()` into a collapsed "Meta
 * Boxes" drawer pinned to the bottom of the screen. Checked as a freshly created
 * editor user on first visit:
 *
 *     drawerExpanded: "false"      boxVisible: false
 *
 * So all eight editorial fields — sapo, primary category, YouTube ID, duration,
 * featured, feature rank, photo story, source — are invisible with no hint they
 * exist. Every one of them is something Proposal 01 §14 expects an editor to set
 * within a one-hour training session, and §4.3/§4.4 make the front page depend on
 * them: no `_pgds_is_featured` means no lead story.
 *
 * WHY NOT THE OBVIOUS FIXES
 *   - Disabling the block editor for posts: §1 states "Gutenberg is used only for
 *     post content", i.e. it is wanted for the body. Removing it to surface a
 *     sidebar would trade a real feature for a workaround.
 *   - A native Gutenberg sidebar panel (PluginDocumentSettingPanel) is the proper
 *     long-term answer, but it requires @wordpress/* packages and a JS build step
 *     that CLAUDE.md explicitly forbids ("do not import any @wordpress/* package").
 *
 * WHAT THIS DOES INSTEAD: a small vanilla-JS nudge that expands the drawer on
 * first load and labels it clearly. No framework, no build step, no new
 * dependency — consistent with the theme's ES2020-only rule.
 * ======================================================================== */

/**
 * Expand the block editor's meta-box drawer and label it for editors.
 *
 * @param string $hook Current admin page.
 */
function pgds_admin_editor_hints( $hook ) {
	if ( 'post.php' !== $hook && 'post-new.php' !== $hook ) {
		return;
	}

	$screen = get_current_screen();
	if ( ! $screen || 'post' !== $screen->post_type ) {
		return;
	}
	// The classic editor renders normal-context boxes directly; only the block editor needs drawer hints.
	if ( method_exists( $screen, 'is_block_editor' ) && ! $screen->is_block_editor() ) {
		return;
	}

	$post_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0;
	$surface = pgds_current_editorial_surface( $post_id );
	$label   = 'vietnam-buddhism' === $surface ? 'Editorial details' : __( 'Nội dung và hiển thị PGDS', 'pgds' );
	?>
	<style>
		/* Give the drawer handle the weight of a real section heading: by default it
		   reads as chrome, so editors skim past it. */
		.edit-post-meta-boxes-area__clear + *,
		.edit-post-layout__metaboxes .edit-post-meta-boxes-area,
		.editor-meta-boxes-area {
			border-top: 2px solid #A9812F;
		}
		#pgds_article_meta .hndle,
		#pgds_article_meta > .postbox-header > h2 {
			font-weight: 600;
		}
		/* Keep labels, controls, and their helper text visually associated in the wide drawer. */
		#pgds_article_meta .pgds-metabox {
			max-width: 680px;
		}
		#pgds_article_meta .pgds-metabox__group {
			border: 1px solid #dcdcde;
			margin: 0 0 16px;
			padding: 12px 16px 4px;
		}
		#pgds_article_meta .pgds-metabox__group legend {
			font-size: 14px;
			font-weight: 600;
			padding: 0 6px;
		}
		#pgds_article_meta .pgds-metabox__group-toggle {
			background: transparent;
			border: 0;
			color: inherit;
			cursor: pointer;
			font: inherit;
			padding: 0;
		}
		#pgds_article_meta .pgds-metabox__group-toggle::after {
			content: '▸';
			margin-left: 6px;
		}
		#pgds_article_meta .pgds-metabox__group-toggle[aria-expanded="true"]::after {
			content: '▾';
		}
		#pgds_article_meta .pgds-metabox__group-help {
			color: #50575e;
			margin: 0 0 14px;
		}
		#pgds_article_meta .pgds-metabox__field {
			margin: 0 0 14px;
		}
		#pgds_article_meta .pgds-metabox__label {
			display: block;
			font-weight: 600;
			margin-bottom: 5px;
		}
		#pgds_article_meta .pgds-metabox__choice {
			display: inline-block;
			margin-bottom: 2px;
		}
		#pgds_article_meta .pgds-metabox__readonly {
			display: block;
			padding: 4px 0;
		}
		#pgds_article_meta .notice.inline {
			margin: 0 0 12px;
		}
		#pgds_article_meta .pgds-metabox__save-feedback {
			scroll-margin-top: 32px;
		}
		#pgds_article_meta .pgds-metabox__feature-rank-state {
			margin-top: 5px;
		}
		#pgds_article_meta .pgds-metabox__classification {
			background: #f6f7f7;
			border-left: 4px solid #A9812F;
			margin-bottom: 16px;
			padding: 12px 16px;
		}
		#pgds_article_meta .pgds-metabox__article-category,
		#pgds_article_meta .pgds-metabox__surface-confirm {
			margin-top: 12px;
		}
		#pgds_article_meta .pgds-metabox__emagazine-checklist {
			background: #fff8e5;
			border-left: 4px solid #A9812F;
			margin-bottom: 16px;
			padding: 12px 16px;
		}
		#pgds_article_meta .pgds-metabox__emagazine-checklist p {
			color: #50575e;
			margin: 4px 0 8px;
		}
		#pgds_article_meta .pgds-metabox__emagazine-checklist ul {
			margin: 0;
		}
		#pgds_article_meta .pgds-metabox__emagazine-checklist li {
			margin: 4px 0;
		}
		#pgds_article_meta .pgds-metabox__emagazine-checklist .is-complete {
			color: #1e6b3a;
		}
		#pgds_article_meta [hidden] {
			display: none !important;
		}
		/* The block editor persists the drawer height separately from its expanded
		   attribute. Give the expanded area a floor so every field remains reachable;
		   editors can still drag the handle to resize it. */
		.edit-post-layout__metaboxes:not(:empty),
		.edit-post-meta-boxes-area,
		.editor-meta-boxes-area {
			min-height: 320px;
			overflow: auto;
		}
	</style>
	<script>
	( function () {
		'use strict';
		var KEY = 'pgdsMetaDrawerOpened';
		var BRIDGE_KEY = 'pgdsMetaFeedbackBridgeInstalled';
		var currentSurface = <?php echo wp_json_encode( $surface ); ?>;
		var featureRankEnabledText = <?php echo wp_json_encode( 'vietnam-buddhism' === $surface ? 'Choose a position from 1 to 4 for a Featured article.' : 'Chọn vị trí từ 1 đến 4 cho bài Tin nổi bật.' ); ?>;
		var featureRankDisabledText = <?php echo wp_json_encode( 'vietnam-buddhism' === $surface ? 'Choose a position from 1 to 4 only when Featured is enabled.' : 'Chỉ cần chọn vị trí từ 1 đến 4 khi bật Tin nổi bật.' ); ?>;
		var popularRankEnabledText = <?php echo wp_json_encode( 'vietnam-buddhism' === $surface ? 'Choose a position from 1 to 4 for a Most read article.' : 'Chọn vị trí từ 1 đến 4 cho bài Đọc nhiều.' ); ?>;
		var popularRankDisabledText = <?php echo wp_json_encode( 'vietnam-buddhism' === $surface ? 'Choose a position from 1 to 4 only when Most read is enabled.' : 'Chỉ cần chọn vị trí từ 1 đến 4 khi bật Đọc nhiều.' ); ?>;
		var tries = 0;
		var bridgeTries = 0;

		function preserveEditorContextLinks() {
			var links = document.querySelectorAll( 'a[href*="/wp-admin/edit.php"], a[href^="edit.php"], a[href*="/wp-admin/post-new.php"], a[href^="post-new.php"]' );
			links.forEach( function ( link ) {
				if ( link.closest( '#adminmenu' ) ) {
					return;
				}
				var url = new URL( link.href, window.location.href );
				var postType = url.searchParams.get( 'post_type' );
				var isPostRoute = url.pathname.endsWith( '/wp-admin/edit.php' ) || url.pathname.endsWith( '/wp-admin/post-new.php' );
				if ( isPostRoute && ( ! postType || 'post' === postType ) ) {
					url.searchParams.set( 'post_type', 'post' );
					url.searchParams.set( 'pgds_surface', currentSurface );
					link.href = url.href;
				}
			} );
		}

		function setCategoryPanelVisibility( surface ) {
			var panels = document.querySelectorAll( '#edit-post\\:document .components-panel__body' );
			panels.forEach( function ( panel ) {
				var toggle = panel.querySelector( ':scope > .components-panel__body-title > button' );
				var label = toggle ? ( toggle.textContent || '' ).trim() : '';
				if ( 'Categories' === label || 'Chuyên mục' === label ) {
					panel.hidden = 'article' !== surface;
					panel.setAttribute( 'data-pgds', 'core-category-panel' );
				}
			} );
		}

		function installEditorContextObserver() {
			if ( window.pgdsEditorContextObserver || ! window.MutationObserver || ! document.body ) {
				return;
			}

			window.pgdsEditorContextObserver = new MutationObserver( function () {
				var select = document.querySelector( '[data-pgds="surface-select"]' );
				preserveEditorContextLinks();
				setCategoryPanelVisibility( select ? select.value : currentSurface );
			} );
			window.pgdsEditorContextObserver.observe( document.body, { childList: true, subtree: true } );
		}

		function urlMatches( first, second ) {
			if ( ! first || ! second ) {
				return false;
			}

			try {
				return new URL( first, window.location.href ).href === new URL( second, window.location.href ).href;
			} catch ( e ) {
				return false;
			}
		}

		function mirrorFeedback( markup ) {
			var metaBox = document.getElementById( 'pgds_article_meta' );
			if ( ! metaBox ) {
				return;
			}

			var host = metaBox.querySelector( '.inside' ) || metaBox;
			var template = document.createElement( 'template' );
			template.innerHTML = markup;
			var feedback = template.content.querySelectorAll( '.pgds-meta-feedback' );
			var existing = host.querySelectorAll( '.pgds-meta-feedback' );
			var i;

			for ( i = 0; i < existing.length; i++ ) {
				existing[ i ].remove();
			}

			if ( ! feedback.length ) {
				return;
			}

			var fragment = document.createDocumentFragment();
			for ( i = 0; i < feedback.length; i++ ) {
				fragment.appendChild( feedback[ i ].cloneNode( true ) );
			}

			var firstNotice = host.querySelector( '.notice' );
			host.insertBefore( fragment, firstNotice || host.firstChild );
			var notice = host.querySelector( '.pgds-meta-feedback' );
			if ( notice ) {
				notice.classList.add( 'pgds-metabox__save-feedback' );
				notice.setAttribute( 'role', 'alert' );
				notice.setAttribute( 'tabindex', '-1' );
				metaBox.scrollIntoView( { behavior: 'smooth', block: 'start' } );
				notice.focus( { preventScroll: true } );
			}
		}

		function installFeedbackBridge() {
			if ( window[ BRIDGE_KEY ] ) {
				return;
			}

			if ( ! window.wp || ! window.wp.apiFetch || 'function' !== typeof window.wp.apiFetch.use ) {
				bridgeTries++;
				if ( bridgeTries < 40 ) {
					window.setTimeout( installFeedbackBridge, 250 );
				}
				return;
			}

			window[ BRIDGE_KEY ] = true;
			window.wp.apiFetch.use( function ( options, next ) {
				var result = next( options );
				var isMetaBoxUpdate =
					'POST' === String( options.method || 'GET' ).toUpperCase() &&
					urlMatches( options.url, window._wpMetaBoxUrl );

				if ( ! isMetaBoxUpdate || ! result || 'function' !== typeof result.then ) {
					return result;
				}

				return result.then( function ( response ) {
					if ( ! response || 'function' !== typeof response.clone ) {
						return response;
					}

					return response.clone().text().then(
						function ( markup ) {
							mirrorFeedback( markup );
							return response;
						},
						function () {
							return response;
						}
					);
				} );
			} );
		}

		function updateFeatureRankControl() {
			var featured = document.getElementById( '_pgds_is_featured' );
			var rank = document.getElementById( '_pgds_feature_rank' );
			var state = rank ? rank.closest( '.pgds-metabox__field' ).querySelector( '.pgds-metabox__feature-rank-state' ) : null;
			if ( ! featured || ! rank ) {
				return;
			}

			var enabled = featured.checked;
			rank.setAttribute( 'aria-disabled', enabled ? 'false' : 'true' );
			if ( state ) {
				state.textContent = enabled ? featureRankEnabledText : featureRankDisabledText;
			}
		}

		function bindFeatureRankControl() {
			var featured = document.getElementById( '_pgds_is_featured' );
			if ( ! featured || featured.dataset.pgdsBound ) {
				return;
			}

			featured.dataset.pgdsBound = '1';
			featured.addEventListener( 'change', updateFeatureRankControl );
			updateFeatureRankControl();
		}

		function updatePopularRankControl() {
			var popular = document.getElementById( '_pgds_is_popular' );
			var rank = document.getElementById( '_pgds_popular_rank' );
			var state = rank ? rank.closest( '.pgds-metabox__field' ).querySelector( '.pgds-metabox__feature-rank-state' ) : null;
			if ( ! popular || ! rank ) {
				return;
			}

			var enabled = popular.checked;
			rank.setAttribute( 'aria-disabled', enabled ? 'false' : 'true' );
			if ( state ) {
				state.textContent = enabled ? popularRankEnabledText : popularRankDisabledText;
			}
		}

		function bindPopularRankControl() {
			var popular = document.getElementById( '_pgds_is_popular' );
			if ( ! popular || popular.dataset.pgdsBound ) {
				return;
			}

			popular.dataset.pgdsBound = '1';
			popular.addEventListener( 'change', updatePopularRankControl );
			updatePopularRankControl();
		}

		function bindGroupToggles() {
			var toggles = document.querySelectorAll( '#pgds_article_meta .pgds-metabox__group-toggle' );
			for ( var i = 0; i < toggles.length; i++ ) {
				if ( toggles[ i ].dataset.pgdsBound ) {
					continue;
				}

				toggles[ i ].dataset.pgdsBound = '1';
				toggles[ i ].addEventListener( 'click', function () {
					var content = document.getElementById( this.getAttribute( 'aria-controls' ) );
					var expanded = this.getAttribute( 'aria-expanded' ) === 'true';
					this.setAttribute( 'aria-expanded', expanded ? 'false' : 'true' );
					if ( content ) {
						content.hidden = expanded;
					}
				} );
			}
		}

		function bindClassificationControl() {
			var select = document.querySelector( '[data-pgds="surface-select"]' );
			var articleCategory = document.querySelector( '[data-pgds="article-category"]' );
			var articleSelect = document.getElementById( 'pgds_article_primary_slug' );
			var confirmWrap = document.querySelector( '[data-pgds="surface-confirm"]' );
			var confirm = document.getElementById( 'pgds_surface_change_confirm' );
			var originalSurface = document.querySelector( '[data-pgds="original-surface"]' );
			var originalPrimary = document.querySelector( '[data-pgds="original-primary"]' );
			var syncedPrimary = Number( originalPrimary ? originalPrimary.value : 0 );
			var videoGroup = document.querySelector( '[data-pgds-group="video"]' );
			var youtube = document.getElementById( '_pgds_youtube_id' );
			if ( ! select || select.dataset.pgdsBound ) {
				return;
			}

			select.dataset.pgdsBound = '1';

			function marker( enabled ) {
				var existing = document.querySelector( '[data-pgds="dynamic-video-marker"]' );
				if ( enabled && ! existing ) {
					existing = document.createElement( 'input' );
					existing.type = 'hidden';
					existing.name = 'pgds_meta_groups[]';
					existing.value = 'video';
					existing.setAttribute( 'data-pgds', 'dynamic-video-marker' );
					select.form ? select.form.appendChild( existing ) : select.parentNode.appendChild( existing );
				} else if ( ! enabled && existing ) {
					existing.remove();
				}
			}

			function targetTermId() {
				if ( select.value === 'article' ) {
					var articleOption = articleSelect && articleSelect.options[ articleSelect.selectedIndex ];
					return articleOption ? Number( articleOption.dataset.termId || 0 ) : 0;
				}
				var option = select.options[ select.selectedIndex ];
				return option ? Number( option.dataset.termId || 0 ) : 0;
			}

			function syncEditorRecord() {
				var original = originalSurface ? originalSurface.value : '';
				var changing = original && original !== select.value;
				if ( changing && confirm && ! confirm.checked ) {
					return;
				}
				if ( ! window.wp || ! window.wp.data ) {
					return;
				}

				var editorSelect = window.wp.data.select( 'core/editor' );
				var editorDispatch = window.wp.data.dispatch( 'core/editor' );
				var target = targetTermId();
				if (
					! editorSelect ||
					! editorDispatch ||
					! target ||
					'function' !== typeof editorSelect.getCurrentPostType ||
					'post' !== editorSelect.getCurrentPostType()
				) {
					return;
				}

				var categories = editorSelect.getEditedPostAttribute( 'categories' ) || [];
				var previous = syncedPrimary;
				var currentMeta = Object.assign( {}, editorSelect.getEditedPostAttribute( 'meta' ) || {} );
				if ( previous === target && categories.map( Number ).indexOf( target ) !== -1 && Number( currentMeta._pgds_primary_cat || 0 ) === target ) {
					return;
				}
				categories = categories.map( Number ).filter( function ( id ) {
					return id && id !== previous && id !== target;
				} );
				categories.push( target );

				var meta = currentMeta;
				meta._pgds_primary_cat = target;
				editorDispatch.editPost( { categories: categories, meta: meta } );
				syncedPrimary = target;
			}

			function update() {
				var isArticle = select.value === 'article';
				var isVideo = select.value === 'video';
				var original = originalSurface ? originalSurface.value : '';
				var changing = original && original !== select.value;
				if ( articleCategory ) {
					articleCategory.hidden = ! isArticle;
				}
				if ( confirmWrap ) {
					confirmWrap.hidden = ! changing;
				}
				if ( videoGroup ) {
					videoGroup.hidden = ! isVideo;
				}
				setCategoryPanelVisibility( select.value );
				marker( isVideo );

				if ( window.wp && window.wp.data ) {
					var dispatch = window.wp.data.dispatch( 'core/editor' );
					if ( dispatch && dispatch.lockPostSaving && dispatch.unlockPostSaving ) {
						if ( changing && confirm && ! confirm.checked ) {
							dispatch.lockPostSaving( 'pgds-surface-confirmation' );
						} else {
							dispatch.unlockPostSaving( 'pgds-surface-confirmation' );
						}
					}
				}

				syncEditorRecord();
			}

			select.addEventListener( 'change', update );
			if ( articleSelect ) {
				articleSelect.addEventListener( 'change', syncEditorRecord );
			}
			if ( confirm ) {
				confirm.addEventListener( 'change', update );
			}
			if ( youtube ) {
				youtube.addEventListener( 'change', function () {
					if ( ! window.wp || ! window.wp.data ) {
						return;
					}
					var editorSelect = window.wp.data.select( 'core/editor' );
					var editorDispatch = window.wp.data.dispatch( 'core/editor' );
					if (
						! editorSelect ||
						! editorDispatch ||
						'function' !== typeof editorSelect.getCurrentPostType ||
						'post' !== editorSelect.getCurrentPostType()
					) {
						return;
					}
					var meta = Object.assign( {}, editorSelect.getEditedPostAttribute( 'meta' ) || {} );
					meta._pgds_youtube_id = youtube.value;
					editorDispatch.editPost( { meta: meta } );
				} );
			}
			update();
		}

		function bindEmagazineChecklist() {
			var checklist = document.querySelector( '[data-pgds="emagazine-checklist"]' );
			if ( ! checklist || checklist.dataset.pgdsBound ) {
				return;
			}

			checklist.dataset.pgdsBound = '1';
			var labels = {
				sapo: 'Sa-pô',
				cover: 'Ảnh bìa',
				caption: 'Chú thích ảnh bìa',
				author: 'Tác giả hiển thị',
				credit: 'Nguồn / ghi công ảnh',
				chapter: 'Ít nhất một tiêu đề chương'
			};

			function setState( key, complete ) {
				var item = checklist.querySelector( '[data-pgds-check="' + key + '"]' );
				if ( ! item ) {
					return;
				}
				item.classList.toggle( 'is-complete', complete );
				item.classList.toggle( 'is-missing', ! complete );
				item.innerHTML = '<span aria-hidden="true">' + ( complete ? '✓' : '○' ) + '</span> ' + labels[ key ];
			}

			function value( selector ) {
				var field = document.querySelector( selector );
				return field ? String( field.value || '' ).trim() : '';
			}

			function update() {
				setState( 'sapo', Boolean( value( '#_pgds_sapo' ) ) );
				setState( 'author', Boolean( value( '#_pgds_display_author' ) ) );
				setState( 'credit', Boolean( value( '#_pgds_source' ) ) );

				if ( ! window.wp || ! window.wp.data ) {
					return;
				}
				var editor = window.wp.data.select( 'core/editor' );
				var core = window.wp.data.select( 'core' );
				if ( ! editor ) {
					return;
				}
				var featuredId = Number( editor.getEditedPostAttribute( 'featured_media' ) || 0 );
				var content = String( editor.getEditedPostContent ? editor.getEditedPostContent() : editor.getEditedPostAttribute( 'content' ) || '' );
				var media = featuredId && core && core.getMedia ? core.getMedia( featuredId ) : null;
				var caption = media && media.caption ? String( media.caption.raw || media.caption.rendered || '' ).replace( /<[^>]+>/g, '' ).trim() : '';
				setState( 'cover', featuredId > 0 );
				setState( 'caption', Boolean( featuredId && caption ) );
				setState( 'chapter', content.indexOf( 'pgds-emagazine-chapter' ) !== -1 );
			}

			checklist.closest( '#pgds_article_meta' ).addEventListener( 'input', update );
			checklist.closest( '#pgds_article_meta' ).addEventListener( 'change', update );
			if ( window.wp && window.wp.data && window.wp.data.subscribe ) {
				window.wp.data.subscribe( update );
			}
			update();
		}

		function findToggle() {
			var buttons = document.querySelectorAll( 'button' );
			for ( var i = 0; i < buttons.length; i++ ) {
				var text = ( buttons[ i ].textContent || '' ).toLowerCase();
				if ( text.indexOf( 'meta box' ) !== -1 || text.indexOf( 'khối meta' ) !== -1 ) {
					return buttons[ i ];
				}
			}
			return null;
		}

		function run() {
			installFeedbackBridge();
			preserveEditorContextLinks();
			setCategoryPanelVisibility( currentSurface );
			installEditorContextObserver();
			bindFeatureRankControl();
			bindPopularRankControl();
			bindGroupToggles();
			bindClassificationControl();
			bindEmagazineChecklist();
			tries++;
			var toggle = findToggle();
			if ( ! toggle ) {
				// The editor mounts asynchronously; give it ~10s, then stop rather than
				// polling forever in a tab the user left open.
				if ( tries < 40 ) {
					window.setTimeout( run, 250 );
				}
				return;
			}

			// Rename the generic "Meta Boxes" handle to say what is actually inside.
			var labelNode = toggle.querySelector( 'span' ) || toggle;
			if ( labelNode && labelNode.textContent.indexOf( 'PGDS' ) === -1 ) {
				labelNode.textContent = <?php echo wp_json_encode( $label ); ?>;
			}

			// Expand once per session. Not on every load: an editor who deliberately
			// collapses it should stay collapsed while they work.
			var opened = false;
			try {
				opened = window.sessionStorage.getItem( KEY ) === '1';
			} catch ( e ) {
				// Private mode or blocked storage: fall back to expanding every load,
				// which is the safer failure for discoverability.
				opened = false;
			}

			if ( ! opened && toggle.getAttribute( 'aria-expanded' ) === 'false' ) {
				toggle.click();
				try {
					window.sessionStorage.setItem( KEY, '1' );
				} catch ( e ) {}
			}
		}

		if ( document.readyState === 'loading' ) {
			document.addEventListener( 'DOMContentLoaded', run );
		} else {
			run();
		}
		window.setTimeout( preserveEditorContextLinks, 500 );
		window.setTimeout( preserveEditorContextLinks, 1500 );
	}() );
	</script>
	<?php
}
add_action( 'admin_enqueue_scripts', 'pgds_admin_editor_hints' );
