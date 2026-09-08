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
 * Keep WordPress's canonical URLs and comment-link helpers aligned with the
 * theme-owned flat discussion stream.
 *
 * The template paginates an explicitly ordered comment collection, but core
 * still consults these options when it validates `/comment-page-N/` URLs and
 * builds pagination links. Leaving the site-level defaults in place makes core
 * redirect page two back to the article even though the template can render it.
 *
 * @param mixed $value Stored option value.
 * @return bool
 */
function pgds_enable_comment_pagination( $value ) {
	return true;
}
add_filter( 'option_page_comments', 'pgds_enable_comment_pagination' );

/**
 * Use the same page size in WordPress core and the theme template.
 *
 * @param mixed $value Stored option value.
 * @return int
 */
function pgds_filter_comments_per_page( $value ) {
	return pgds_comments_per_page();
}
add_filter( 'option_comments_per_page', 'pgds_filter_comments_per_page' );

/**
 * Keep core page numbering in natural order. The template has already sorted its
 * flat collection newest first, so core's first slice is the newest eight items.
 *
 * @param mixed $value Stored option value.
 * @return string
 */
function pgds_filter_default_comments_page( $value ) {
	return 'oldest';
}
add_filter( 'option_default_comments_page', 'pgds_filter_default_comments_page' );

/**
 * Count reader-comment pages without depending on the global page_comments option.
 * Reader comments are intentionally presented as one flat discussion stream.
 *
 * @param WP_Comment[] $comments Comment collection.
 * @return int
 */
function pgds_comment_page_count( $comments ) {
	return max( 1, (int) ceil( count( (array) $comments ) / pgds_comments_per_page() ) );
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

			<?php if ( current_user_can( 'edit_comment', $comment->comment_ID ) ) : ?>
				<footer class="pgds-comment__actions">
					<?php
					printf(
						'<a class="pgds-comment__manage" href="%1$s">%2$s</a>',
						esc_url( get_edit_comment_link( $comment ) ),
						esc_html( $english ? 'Manage' : 'Quản lý' )
					);
					?>
				</footer>
			<?php endif; ?>
		</article>
	<?php
}
