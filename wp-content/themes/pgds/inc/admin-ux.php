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
	if ( '1' === get_post_meta( $post_id, '_pgds_is_featured', true ) ) {
		$rank    = (int) get_post_meta( $post_id, '_pgds_feature_rank', true );
		$flags[] = '★ Nổi bật' . ( $rank ? " (#{$rank})" : '' );
	}
	if ( '1' === get_post_meta( $post_id, '_pgds_photo_story', true ) ) {
		$flags[] = '📷 Tin ảnh';
	}
	if ( get_post_meta( $post_id, '_pgds_youtube_id', true ) ) {
		$flags[] = '▶ Video';
	}
	echo $flags ? esc_html( implode( ' · ', $flags ) ) : '—';
}
add_action( 'manage_post_posts_custom_column', 'pgds_admin_column_content', 10, 2 );

/**
 * Allow sorting by feature_rank.
 */
function pgds_admin_sortable( $cols ) {
	$cols['pgds_flags'] = 'pgds_flags';
	return $cols;
}
add_filter( 'manage_edit-post_sortable_columns', 'pgds_admin_sortable' );

/**
 * "Featured posts only" filter on the post list.
 */
function pgds_admin_filter_ui() {
	global $typenow;
	if ( 'post' !== $typenow ) {
		return;
	}
	$current = isset( $_GET['pgds_filter'] ) ? sanitize_key( $_GET['pgds_filter'] ) : '';
	?>
	<select name="pgds_filter">
		<option value=""><?php esc_html_e( '— Lọc PGDS —', 'pgds' ); ?></option>
		<option value="featured" <?php selected( $current, 'featured' ); ?>><?php esc_html_e( 'Tin nổi bật', 'pgds' ); ?></option>
		<option value="photo" <?php selected( $current, 'photo' ); ?>><?php esc_html_e( 'Tin ảnh', 'pgds' ); ?></option>
		<option value="video" <?php selected( $current, 'video' ); ?>><?php esc_html_e( 'Có video', 'pgds' ); ?></option>
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
	$filter = sanitize_key( $_GET['pgds_filter'] );
	$map    = array(
		'featured' => '_pgds_is_featured',
		'photo'    => '_pgds_photo_story',
		'video'    => '_pgds_youtube_id',
	);
	if ( isset( $map[ $filter ] ) ) {
		$mq = ( 'video' === $filter )
			? array( array( 'key' => $map[ $filter ], 'compare' => 'EXISTS' ) )
			: array( array( 'key' => $map[ $filter ], 'value' => '1' ) );
		$query->set( 'meta_query', $mq );
	}
}
add_action( 'pre_get_posts', 'pgds_admin_filter_apply' );

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

	$label = __( 'Nội dung và hiển thị PGDS', 'pgds' );
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
		var tries = 0;
		var bridgeTries = 0;

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
			var state = document.querySelector( '.pgds-metabox__feature-rank-state' );
			if ( ! featured || ! rank ) {
				return;
			}

			var enabled = featured.checked;
			rank.setAttribute( 'aria-disabled', enabled ? 'false' : 'true' );
			if ( state ) {
				state.textContent = enabled
					? 'Chọn vị trí từ 1 đến 4 cho bài Tin nổi bật.'
					: 'Chỉ cần chọn vị trí từ 1 đến 4 khi bật Tin nổi bật.';
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
			bindFeatureRankControl();
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
	}() );
	</script>
	<?php
}
add_action( 'admin_enqueue_scripts', 'pgds_admin_editor_hints' );
