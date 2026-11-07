<?php
/**
 * Editorial roles: Trưởng BBT (administrator) publishes, Phóng viên submits for review.
 *
 * Newsroom feedback asked for two account levels so that publishing is a deliberate,
 * single-owner step:
 *
 *   - Trưởng ban biên tập — the administrator account. Full control of the CMS and the
 *     ONLY account allowed to publish.
 *   - Phóng viên — writes, edits and submits articles in the "Chờ duyệt" (pending) state,
 *     but cannot publish.
 *
 * The publish restriction is applied at runtime through `user_has_cap` rather than by
 * rewriting the stored editor/author roles, so it needs no migration, cannot drift from
 * this file, and disappears cleanly if the theme is switched off.
 *
 * WordPress already turns the editor's primary button into "Gửi duyệt" (Submit for
 * Review) for any user without `publish_posts`, and core converts a publish request from
 * such a user to `pending`; this module only has to remove the capability. Moderating
 * comments and managing one's own media stay available (pgds_map_editorial_meta_caps()).
 *
 * A post is accepted into "Chờ duyệt", and kept there, only while the title, sapo, body
 * and category are all present (the feedback's step 2). The block editor is checked over
 * REST, the classic form in pgds_save_meta(), and every other path (Quick Edit, Bulk Edit,
 * XML-RPC) by pgds_enforce_pending_completeness(), which sends the post back to draft.
 *
 * @package pgds
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/*
 * Bump when pgds_reporter_capabilities() changes so the stored role is rebuilt once.
 */
if ( ! defined( 'PGDS_ROLES_VERSION' ) ) {
	define( 'PGDS_ROLES_VERSION', '1' );
}

/**
 * Capabilities of the Phóng viên role.
 *
 * Contributor-equivalent plus `upload_files`: a reporter has to attach photos to their own
 * articles, and core's Contributor cannot upload media at all.
 *
 * Deliberately absent: publish_posts, edit_published_posts, delete_published_posts,
 * edit_others_posts, manage_categories.
 *
 * @return array<string,bool>
 */
function pgds_reporter_capabilities() {
	return array(
		'read'         => true,
		'level_0'      => true,
		'level_1'      => true,
		'edit_posts'   => true,
		'delete_posts' => true,
		'upload_files' => true,
	);
}

/**
 * Create or refresh the Phóng viên role once per PGDS_ROLES_VERSION.
 *
 * Roles live in the database, so this is version-gated: one autoloaded option read per
 * request, and a write only when the definition changes.
 */
function pgds_sync_editorial_roles() {
	if ( PGDS_ROLES_VERSION === get_option( 'pgds_roles_version' ) && get_role( 'pgds_reporter' ) ) {
		return;
	}

	// Re-adding replaces the capability set; users keep the role slug in their user meta.
	remove_role( 'pgds_reporter' );
	add_role( 'pgds_reporter', 'Phóng viên', pgds_reporter_capabilities() );
	update_option( 'pgds_roles_version', PGDS_ROLES_VERSION, true );
}
add_action( 'init', 'pgds_sync_editorial_roles', 1 );

/**
 * Capabilities that publish content or change content that is already live.
 *
 * Editing a published article changes what readers see without going back through review,
 * so it is treated as publishing.
 *
 * @return string[]
 */
function pgds_publishing_capabilities() {
	return array(
		'publish_posts',
		'publish_pages',
		'edit_published_posts',
		'edit_published_pages',
		'delete_published_posts',
		'delete_published_pages',
	);
}

/**
 * Whether a capability set belongs to the Trưởng BBT (administrator) account.
 *
 * @param array<string,bool> $allcaps Capabilities of the user.
 * @return bool
 */
function pgds_caps_can_publish( array $allcaps ) {
	return ! empty( $allcaps['manage_options'] );
}

/**
 * Remove publishing rights from every account except the administrator.
 *
 * @param array<string,bool> $allcaps All capabilities of the user.
 * @return array<string,bool>
 */
function pgds_restrict_publishing_caps( $allcaps ) {
	if ( ! is_array( $allcaps ) || pgds_caps_can_publish( $allcaps ) ) {
		return $allcaps;
	}

	foreach ( pgds_publishing_capabilities() as $cap ) {
		$allcaps[ $cap ] = false;
	}

	return $allcaps;
}
add_filter( 'user_has_cap', 'pgds_restrict_publishing_caps', 10, 1 );

/**
 * Keep two everyday tasks that core ties to `edit_published_*` but that do not change an
 * article readers see.
 *
 *   - Moderating a reader comment: core maps `edit_comment` to `edit_post` on the comment's
 *     article, which for a live article needs `edit_published_posts`. Comment moderators
 *     (`moderate_comments`) keep approving, marking spam and trashing comments.
 *   - A user's own media: core treats an unattached upload as published, so editing its alt
 *     text or caption, cropping or deleting it needs `edit/delete_published_posts`. The
 *     uploader (`upload_files`) keeps managing their own files.
 *
 * @param string[] $caps    Primitive capabilities required.
 * @param string   $cap     Meta capability being checked.
 * @param int      $user_id User ID.
 * @param array    $args    Extra arguments (object ID first).
 * @return string[]
 */
function pgds_map_editorial_meta_caps( $caps, $cap, $user_id, $args ) {
	if ( 'edit_comment' === $cap ) {
		return pgds_swap_required_caps( $caps, array( 'edit_published_posts', 'edit_published_pages' ), 'moderate_comments' );
	}

	if ( ( 'edit_post' === $cap || 'delete_post' === $cap ) && ! empty( $args[0] ) ) {
		$post = get_post( $args[0] );
		if ( $post instanceof WP_Post && 'attachment' === $post->post_type && (int) $post->post_author === (int) $user_id ) {
			return pgds_swap_required_caps( $caps, array( 'edit_published_posts', 'delete_published_posts' ), 'upload_files' );
		}
	}

	return $caps;
}
add_filter( 'map_meta_cap', 'pgds_map_editorial_meta_caps', 10, 4 );

/**
 * Replace some required primitive capabilities with another one.
 *
 * @param string[] $caps        Required capabilities.
 * @param string[] $replace     Capabilities to replace.
 * @param string   $replacement Capability required instead.
 * @return string[]
 */
function pgds_swap_required_caps( $caps, array $replace, $replacement ) {
	if ( ! is_array( $caps ) || ! array_intersect( $caps, $replace ) ) {
		return $caps;
	}

	$caps   = array_diff( $caps, $replace );
	$caps[] = $replacement;

	return array_values( array_unique( $caps ) );
}

/* ===========================================================================
 * Submission for review: title, sapo, body and category are required.
 * ======================================================================== */

/**
 * Does post content carry anything a reader would see?
 *
 * An empty paragraph block still serializes to `<!-- wp:paragraph --><p></p>…`, so the
 * check strips markup and comments; media-only bodies (an image or an embed) count.
 *
 * @param string $content Raw post content.
 * @return bool
 */
function pgds_content_has_body( $content ) {
	$content = (string) $content;
	if ( '' !== trim( html_entity_decode( wp_strip_all_tags( $content ), ENT_QUOTES, 'UTF-8' ), " \t\n\r\0\x0B\xC2\xA0" ) ) {
		return true;
	}

	return 1 === preg_match( '/<(img|iframe|video|audio)\b|<!--\s*wp:(image|embed|video|gallery|audio|core-embed\/[a-z0-9-]+)\b/i', $content );
}

/**
 * Validate the parts a submission for review must contain.
 *
 * @param array $values {
 *     @type string $title        Post title.
 *     @type string $content      Post content.
 *     @type string $sapo         `_pgds_sapo` value.
 *     @type string $excerpt      Hand-written excerpt (accepted as a sapo, as pgds_has_editorial_sapo() does).
 *     @type int    $primary_id   `_pgds_primary_cat` value.
 *     @type int[]  $assigned_ids Assigned category term IDs.
 * }
 * @return string[] Missing-part codes, in the order an editor reads the form.
 */
function pgds_pending_submission_errors( array $values ) {
	$errors = array();

	if ( '' === trim( (string) ( $values['title'] ?? '' ) ) ) {
		$errors[] = 'pgds_pending_missing_title';
	}

	$sapo    = trim( (string) ( $values['sapo'] ?? '' ) );
	$excerpt = trim( (string) ( $values['excerpt'] ?? '' ) );
	if ( '' === $sapo && '' === $excerpt ) {
		$errors[] = 'pgds_pending_missing_sapo';
	}

	if ( ! pgds_content_has_body( (string) ( $values['content'] ?? '' ) ) ) {
		$errors[] = 'pgds_pending_missing_content';
	}

	$classification = pgds_classify_editorial_values(
		absint( $values['primary_id'] ?? 0 ),
		array_map( 'intval', (array) ( $values['assigned_ids'] ?? array() ) )
	);
	if ( ! $classification['valid'] ) {
		$errors[] = 'pgds_pending_missing_category';
	}

	return $errors;
}

/**
 * Validate a stored post as a submission for review.
 *
 * @param int $post_id Post ID.
 * @return string[] Missing-part codes.
 */
function pgds_pending_submission_errors_for_post( $post_id ) {
	$post = get_post( $post_id );
	if ( ! $post instanceof WP_Post ) {
		return array();
	}

	$assigned_ids = wp_get_post_categories( $post->ID, array( 'fields' => 'ids' ) );

	return pgds_pending_submission_errors(
		array(
			'title'        => $post->post_title,
			'content'      => $post->post_content,
			'sapo'         => (string) get_post_meta( $post->ID, '_pgds_sapo', true ),
			'excerpt'      => $post->post_excerpt,
			'primary_id'   => (int) get_post_meta( $post->ID, '_pgds_primary_cat', true ),
			'assigned_ids' => is_array( $assigned_ids ) ? $assigned_ids : array(),
		)
	);
}

/**
 * One editor-facing sentence listing everything that is missing.
 *
 * @param string[] $codes   Missing-part codes.
 * @param string   $surface Editorial surface (Vietnam Buddhism uses English labels).
 * @param bool     $editing True when the post is already waiting for review (an edit, not a submission).
 * @return string
 */
function pgds_pending_submission_message( array $codes, $surface = '', $editing = false ) {
	$english = 'vietnam-buddhism' === $surface;
	$labels  = $english
		? array(
			'pgds_pending_missing_title'    => 'title',
			'pgds_pending_missing_sapo'     => 'summary (sapo)',
			'pgds_pending_missing_content'  => 'body',
			'pgds_pending_missing_category' => 'category',
		)
		: array(
			'pgds_pending_missing_title'    => 'tít (tiêu đề)',
			'pgds_pending_missing_sapo'     => 'sa-pô',
			'pgds_pending_missing_content'  => 'nội dung bài',
			'pgds_pending_missing_category' => 'chuyên mục',
		);

	$missing = array();
	foreach ( $codes as $code ) {
		if ( isset( $labels[ $code ] ) ) {
			$missing[] = $labels[ $code ];
		}
	}

	$list = implode( ', ', $missing );

	if ( $editing ) {
		return $english
			? sprintf( 'Not saved. A post waiting for review must keep its title, summary, body and category. Missing: %s. Add them, or switch the post to draft first.', $list )
			: sprintf( 'Chưa lưu được. Bài đang chờ duyệt phải giữ đủ tít, sa-pô, nội dung bài và chuyên mục. Còn thiếu: %s. Hãy bổ sung, hoặc chuyển bài về bản nháp trước.', $list );
	}

	return $english
		? sprintf( 'The article was not submitted for review. Fill in the title, summary and body, and choose a category. Missing: %s.', $list )
		: sprintf( 'Bài chưa được gửi chờ duyệt. Cần nhập đầy đủ tít, sa-pô, nội dung bài và chọn chuyên mục. Còn thiếu: %s.', $list );
}

/**
 * Reject an incomplete submission for review made through the block editor (REST).
 *
 * Runs after pgds_rest_validate_article_meta() (priority 10), so the meta it reads has
 * already been normalized. The sapo field lives in the PGDS meta box, which the block
 * editor saves in a second request after this one; admin-ux.php mirrors it into the REST
 * payload so the value typed on screen is the one validated here.
 *
 * @param stdClass|WP_Error $prepared_post Prepared post.
 * @param WP_REST_Request   $request       REST request.
 * @return stdClass|WP_Error
 */
function pgds_rest_validate_pending_submission( $prepared_post, $request ) {
	if ( is_wp_error( $prepared_post ) ) {
		return $prepared_post;
	}

	// Autosaves run through the same filter. Rejecting them would raise an error while the
	// reporter is still typing; the explicit save that follows is validated instead.
	if ( false !== strpos( (string) $request->get_route(), '/autosaves' ) ) {
		return $prepared_post;
	}

	$post_id  = absint( $request['id'] );
	$existing = $post_id ? get_post( $post_id ) : null;
	$existing = $existing instanceof WP_Post ? $existing : null;

	// The status the post will have after this save: the requested one, else the current one.
	// An edit to a post already waiting for review must keep it complete too, otherwise a
	// reporter could submit a full article and then empty it while it sits in the queue.
	$status = $request->has_param( 'status' )
		? sanitize_key( (string) $request->get_param( 'status' ) )
		: ( $existing ? $existing->post_status : '' );
	if ( 'pending' !== $status ) {
		return $prepared_post;
	}
	$editing = $existing && 'pending' === $existing->post_status;

	$meta = $request->has_param( 'meta' ) ? $request->get_param( 'meta' ) : array();
	$meta = is_array( $meta ) ? $meta : array();

	if ( $request->has_param( 'categories' ) ) {
		$assigned_ids = array_values( array_filter( array_map( 'absint', (array) $request->get_param( 'categories' ) ) ) );
	} else {
		$assigned_ids = $existing ? wp_get_post_categories( $existing->ID, array( 'fields' => 'ids' ) ) : array();
	}

	$values = array(
		'title'        => isset( $prepared_post->post_title ) ? $prepared_post->post_title : ( $existing ? $existing->post_title : '' ),
		'content'      => isset( $prepared_post->post_content ) ? $prepared_post->post_content : ( $existing ? $existing->post_content : '' ),
		'excerpt'      => isset( $prepared_post->post_excerpt ) ? $prepared_post->post_excerpt : ( $existing ? $existing->post_excerpt : '' ),
		'sapo'         => array_key_exists( '_pgds_sapo', $meta ) ? (string) $meta['_pgds_sapo'] : (string) pgds_rest_existing_meta( $post_id, '_pgds_sapo', '' ),
		'primary_id'   => array_key_exists( '_pgds_primary_cat', $meta ) ? absint( $meta['_pgds_primary_cat'] ) : absint( pgds_rest_existing_meta( $post_id, '_pgds_primary_cat', 0 ) ),
		'assigned_ids' => is_array( $assigned_ids ) ? $assigned_ids : array(),
	);

	$codes = pgds_pending_submission_errors( $values );
	if ( ! $codes ) {
		return $prepared_post;
	}

	$surface = $post_id ? pgds_get_editorial_classification( $post_id )['surface'] : pgds_requested_editorial_surface();

	return new WP_Error(
		'pgds_pending_incomplete',
		pgds_pending_submission_message( $codes, $surface, $editing ),
		array(
			'status'  => 400,
			'missing' => $codes,
		)
	);
}
add_filter( 'rest_pre_insert_post', 'pgds_rest_validate_pending_submission', 20, 2 );

/**
 * Send an incomplete post back to draft whatever path put it in "Chờ duyệt".
 *
 * The REST check above and the meta-box check in pgds_save_meta() cover the two editors,
 * but other paths save posts without either: Quick Edit and Bulk Edit on the posts list,
 * XML-RPC, plugins calling wp_update_post(). This runs after pgds_save_meta() (priority 10)
 * so the classic form is judged on the values it has just stored, and it re-reads the
 * status because an earlier callback may already have reverted the post.
 *
 * Limited to logged-in accounts that cannot publish: the rule is about what a reporter
 * hands to the Trưởng BBT, not about system writes (cron, WP-CLI) or the reviewer.
 *
 * @param int          $post_id     Post ID.
 * @param WP_Post      $post        Post object.
 * @param bool         $update      Whether this is an update.
 * @param WP_Post|null $post_before Post before the save.
 */
function pgds_enforce_pending_completeness( $post_id, $post, $update, $post_before ) {
	unset( $update, $post_before );

	if ( ! $post instanceof WP_Post || 'post' !== $post->post_type ) {
		return;
	}
	if ( 'pending' !== get_post_status( $post_id ) ) {
		return;
	}
	if ( ! get_current_user_id() || current_user_can( 'publish_posts' ) ) {
		return;
	}
	if ( ! pgds_pending_submission_errors_for_post( $post_id ) ) {
		return;
	}

	remove_action( 'wp_after_insert_post', 'pgds_enforce_pending_completeness', 20 );
	wp_update_post(
		array(
			'ID'          => $post_id,
			'post_status' => 'draft',
		)
	);
	add_action( 'wp_after_insert_post', 'pgds_enforce_pending_completeness', 20, 4 );
}
add_action( 'wp_after_insert_post', 'pgds_enforce_pending_completeness', 20, 4 );

/* ===========================================================================
 * Review queue for the Trưởng BBT.
 * ======================================================================== */

/**
 * Show a pending-count bubble on each editorial menu for the account that publishes.
 */
function pgds_pending_menu_bubbles() {
	global $menu;

	if ( ! current_user_can( 'publish_posts' ) || ! function_exists( 'pgds_editorial_view_count' ) || ! is_array( $menu ) ) {
		return;
	}

	foreach ( $menu as $index => $item ) {
		$hook_id = isset( $item[5] ) ? (string) $item[5] : '';
		if ( 0 !== strpos( $hook_id, 'menu-posts-' ) ) {
			continue;
		}

		$surface = pgds_sanitize_editorial_surface( substr( $hook_id, strlen( 'menu-posts-' ) ) );
		if ( ! $surface ) {
			continue;
		}

		$count = (int) pgds_editorial_view_count( $surface, 'pending' );
		if ( $count < 1 ) {
			continue;
		}

		$menu[ $index ][0] .= sprintf(
			' <span class="awaiting-mod count-%1$d"><span class="pending-count" aria-hidden="true">%2$s</span><span class="screen-reader-text">%3$s</span></span>',
			$count,
			esc_html( number_format_i18n( $count ) ),
			esc_html( sprintf( '%d bài chờ duyệt', $count ) )
		);
	}
}
add_action( 'admin_menu', 'pgds_pending_menu_bubbles', 999 );

/**
 * Register the "Bài chờ duyệt" dashboard widget for the account that publishes.
 */
function pgds_register_pending_dashboard_widget() {
	if ( ! current_user_can( 'publish_posts' ) ) {
		return;
	}

	wp_add_dashboard_widget( 'pgds_pending_review', 'Bài chờ duyệt', 'pgds_render_pending_dashboard_widget' );
}
add_action( 'wp_dashboard_setup', 'pgds_register_pending_dashboard_widget' );

/**
 * Render the latest submissions waiting for the Trưởng BBT, across every workflow.
 */
function pgds_render_pending_dashboard_widget() {
	$query = new WP_Query(
		array(
			'post_type'           => 'post',
			'post_status'         => 'pending',
			'posts_per_page'      => 10,
			'orderby'             => 'modified',
			'order'               => 'DESC',
			'ignore_sticky_posts' => true,
			'no_found_rows'       => false,
		)
	);

	if ( ! $query->have_posts() ) {
		echo '<p>' . esc_html( 'Không có bài nào đang chờ duyệt.' ) . '</p>';
		return;
	}

	$surfaces = pgds_editorial_surfaces();

	echo '<ul class="pgds-pending-list">';
	foreach ( $query->posts as $pending ) {
		$surface   = pgds_get_editorial_classification( $pending->ID )['surface'];
		$label     = $surfaces[ $surface ]['label'] ?? '';
		$title     = '' !== trim( $pending->post_title ) ? $pending->post_title : '(Chưa có tít)';
		$edit_link = get_edit_post_link( $pending->ID, 'raw' );
		$author    = get_the_author_meta( 'display_name', (int) $pending->post_author );

		echo '<li style="margin-bottom:10px">';
		if ( $edit_link ) {
			printf( '<a href="%s"><strong>%s</strong></a>', esc_url( $edit_link ), esc_html( $title ) );
		} else {
			printf( '<strong>%s</strong>', esc_html( $title ) );
		}
		printf(
			'<br><span class="description">%s · %s · %s</span>',
			esc_html( $label ),
			esc_html( $author ? $author : '—' ),
			esc_html( get_the_modified_date( 'H:i d/m/Y', $pending ) )
		);
		echo '</li>';
	}
	echo '</ul>';

	if ( $query->found_posts > count( $query->posts ) ) {
		printf(
			'<p>%s</p>',
			esc_html( sprintf( 'Còn %d bài khác đang chờ duyệt — xem trong từng mục Bài viết / E-magazine / Video / Vietnam Buddhism.', $query->found_posts - count( $query->posts ) ) )
		);
	}
}
