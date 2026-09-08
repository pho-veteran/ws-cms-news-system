<?php
/**
 * Comments template.
 *
 * @package pgds
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( post_password_required() ) {
	return;
}

$english           = pgds_is_english_reader_request();
$comments_open     = comments_open();
$comments_per_page = pgds_comments_per_page();
$comment_page      = max( 1, (int) get_query_var( 'cpage' ) );
$reader_comments   = get_comments(
	array(
		'post_id'      => get_queried_object_id(),
		'source'       => 'comment',
		'status'       => 'approve',
		'orderby'      => 'comment_date_gmt',
		'order'        => 'DESC',
		'hierarchical' => false,
	)
);
$has_comments      = ! empty( $reader_comments );
$comment_pages     = $has_comments
	? pgds_comment_page_count( $reader_comments )
	: 0;
$comment_page      = $comment_pages ? min( $comment_page, $comment_pages ) : 1;

if ( ! $has_comments && ! $comments_open ) {
	return;
}
?>

<section class="pgds-comments" id="comments" aria-labelledby="pgds-comments-title">

	<h2 class="pgds-comments__title related-head" id="pgds-comments-title">
		<span><?php echo esc_html( $english ? 'Comments' : 'Bình luận' ); ?></span>
		<?php if ( $has_comments ) : ?>
			<span class="pgds-comments__count" aria-label="<?php echo esc_attr( $english ? 'Published comments' : 'Bình luận đã đăng' ); ?>">
				<?php echo esc_html( number_format_i18n( count( $reader_comments ) ) ); ?>
			</span>
		<?php endif; ?>
	</h2>

	<?php if ( $has_comments ) : ?>
		<ol class="pgds-comments__list">
			<?php
			wp_list_comments( array(
				'style'             => 'ol',
				'callback'          => 'pgds_comment_card',
				'page'              => $comment_page,
				'per_page'          => $comments_per_page,
				'reverse_top_level' => false,
				'max_depth'         => 3,
			), $reader_comments );
			?>
		</ol>
		<?php if ( $comment_pages > 1 ) : ?>
			<nav class="pgds-comments__pagination" aria-label="<?php echo esc_attr( $english ? 'Comment pages' : 'Các trang bình luận' ); ?>">
				<?php
				echo wp_kses_post(
					paginate_comments_links(
						array(
							'echo'      => false,
							'total'     => $comment_pages,
							'current'   => $comment_page,
							'type'      => 'list',
							'prev_text' => $english ? 'Previous' : 'Trước',
							'next_text' => $english ? 'Next' : 'Sau',
						)
					)
				);
				?>
			</nav>
		<?php endif; ?>
	<?php endif; ?>

	<?php if ( $comments_open ) : ?>
		<div class="pgds-comments__form-wrap comment-box">
			<h3 class="pgds-comments__form-title"><?php echo esc_html( $english ? 'Join the conversation' : 'Tham gia thảo luận' ); ?></h3>
			<p class="pgds-comments__form-note">
				<?php echo esc_html( $english ? 'Your comment will appear immediately after submission.' : 'Bình luận sẽ hiển thị ngay sau khi gửi.' ); ?>
			</p>
			<?php
			$req      = get_option( 'require_name_email' );
			$asterisk = $req ? ' <span class="required" aria-hidden="true">*</span>' : '';
			comment_form( array(
				'title_reply'          => '',
				'title_reply_before'   => '',
				'title_reply_after'    => '',
				'comment_notes_before' => '',
				'comment_notes_after'  => '',
				'label_submit'         => $english ? 'Post comment' : 'Gửi bình luận',
				'cancel_reply_link'    => $english ? 'Cancel reply' : 'Hủy trả lời',
				'comment_field'        => '<p class="comment-form-comment"><label class="screen-reader-text" for="comment">' . esc_html( $english ? 'Comment' : 'Bình luận' ) . '</label><textarea id="comment" name="comment" cols="45" rows="4" maxlength="65525" required aria-required="true" autocomplete="off" placeholder="' . esc_attr( $english ? 'Share your perspective…' : 'Chia sẻ góc nhìn của bạn…' ) . '"></textarea></p>',
					'fields'               => array(
						'author'  => '<p class="comment-form-author"><label for="author">' . esc_html( $english ? 'Name' : 'Tên' ) . $asterisk . '</label><input id="author" name="author" type="text" size="30" maxlength="245" autocomplete="name"' . ( $req ? ' required aria-required="true"' : '' ) . ' /></p>',
						'email'   => '<p class="comment-form-email"><label for="email">' . esc_html__( 'Email', 'pgds' ) . $asterisk . '</label><input id="email" name="email" type="email" size="30" maxlength="100" autocomplete="email"' . ( $req ? ' required aria-required="true"' : '' ) . ' /></p>',
						'url'     => '',
						'cookies' => '<p class="comment-form-cookies-consent"><input id="wp-comment-cookies-consent" name="wp-comment-cookies-consent" type="checkbox" value="yes" autocomplete="off" /><label for="wp-comment-cookies-consent">' . esc_html( $english ? 'Save my name and email for my next comment.' : 'Lưu tên và email cho lần bình luận tiếp theo.' ) . '</label></p>',
					),
				'class_container'      => 'pgds-comments__form',
				'class_form'           => 'pgds-comments__form-inner',
				'submit_button'        => '<button name="%1$s" type="submit" id="%2$s" class="%3$s pgds-comments__submit">%4$s</button>',
			) );
			?>
		</div>
	<?php elseif ( $has_comments ) : ?>
		<p class="pgds-comments__closed" role="status">
			<?php echo esc_html( $english ? 'Comments are closed.' : 'Bình luận đã đóng.' ); ?>
		</p>
	<?php endif; ?>

</section>
