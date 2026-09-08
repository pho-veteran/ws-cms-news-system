<?php
/**
 * Reader comments: approval policy and theme-owned presentation.
 *
 * @package pgds
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Publish ordinary reader comments immediately.
 *
 * Explicit spam/trash decisions made by WordPress or an anti-spam integration are
 * preserved. Pingbacks and trackbacks keep WordPress's normal moderation behavior.
 *
 * @param int|string|WP_Error $approved    Proposed approval status.
 * @param array               $commentdata Submitted comment data.
 * @return int|string|WP_Error
 */
function pgds_auto_approve_reader_comment( $approved, $commentdata ) {
	if ( is_wp_error( $approved ) || in_array( $approved, array( 'spam', 'trash' ), true ) ) {
		return $approved;
	}

	$comment_type = (string) ( $commentdata['comment_type'] ?? 'comment' );
	if ( '' !== $comment_type && 'comment' !== $comment_type ) {
		return $approved;
	}

	return 1;
}
add_filter( 'pre_comment_approved', 'pgds_auto_approve_reader_comment', 20, 2 );

/**
 * Number of top-level comments shown on one reader page.
 *
 * @return int
 */
function pgds_comments_per_page() {
	return 8;
}

/**
 * Count reader-comment pages without depending on the global page_comments option.
 * Replies stay with their top-level thread and do not consume a separate page slot.
 *
 * @param WP_Comment[] $comments Comment collection.
 * @return int
 */
function pgds_comment_page_count( $comments ) {
	$top_level = array_filter(
		(array) $comments,
		static function ( $comment ) {
			return $comment instanceof WP_Comment && 0 === (int) $comment->comment_parent;
		}
	);

	return max( 1, (int) ceil( count( $top_level ) / pgds_comments_per_page() ) );
}

/**
 * Render one comment using the theme's editorial card language.
 *
 * @param WP_Comment $comment Comment object.
 * @param array      $args    Walker arguments.
 * @param int        $depth   Nesting depth.
 * @return void
 */
function pgds_comment_card( $comment, $args, $depth ) {
	$english       = pgds_is_english_reader_request();
	$author        = get_comment_author( $comment );
	$permalink     = get_comment_link( $comment );
	$published_iso = get_comment_date( 'c', $comment );
	$published     = $english
		? sprintf( '%1$s at %2$s', get_comment_date( 'F j, Y', $comment ), get_comment_time( 'g:i a', false, true, $comment ) )
		: sprintf( '%1$s lúc %2$s', get_comment_date( 'd/m/Y', $comment ), get_comment_time( 'H:i', false, true, $comment ) );
	?>
	<div <?php comment_class( 'pgds-comment' ); ?> id="li-comment-<?php comment_ID(); ?>" role="listitem">
		<article class="pgds-comment__card" id="comment-<?php comment_ID(); ?>">
			<header class="pgds-comment__header">
				<div class="pgds-comment__identity">
					<strong class="pgds-comment__author"><?php echo wp_kses_post( get_comment_author_link( $comment ) ); ?></strong>
					<a class="pgds-comment__date" href="<?php echo esc_url( $permalink ); ?>">
						<time datetime="<?php echo esc_attr( $published_iso ); ?>"><?php echo esc_html( $published ); ?></time>
					</a>
				</div>
			</header>

			<?php if ( '0' === (string) $comment->comment_approved ) : ?>
				<p class="pgds-comment__notice" role="status">
					<?php echo esc_html( $english ? 'Your comment is awaiting moderation.' : 'Bình luận của bạn đang chờ duyệt.' ); ?>
				</p>
			<?php endif; ?>

			<div class="pgds-comment__content">
				<?php comment_text( $comment ); ?>
			</div>

			<footer class="pgds-comment__actions">
				<?php
				if ( comments_open( $comment->comment_post_ID ) && get_option( 'thread_comments' ) && $depth < $args['max_depth'] ) {
					printf(
						'<a class="pgds-comment__reply" href="#respond" data-pgds="comment-reply" data-comment-id="%1$d" data-comment-author="%2$s" aria-label="%3$s">%4$s</a>',
						(int) $comment->comment_ID,
						esc_attr( $author ),
						esc_attr( sprintf( $english ? 'Reply to %s' : 'Trả lời %s', $author ) ),
						esc_html( $english ? 'Reply' : 'Trả lời' )
					);
				}
				if ( current_user_can( 'edit_comment', $comment->comment_ID ) ) {
					printf(
						'<a class="pgds-comment__manage" href="%1$s">%2$s</a>',
						esc_url( get_edit_comment_link( $comment ) ),
						esc_html( $english ? 'Manage' : 'Quản lý' )
					);
				}
				?>
			</footer>
		</article>
	<?php
}
