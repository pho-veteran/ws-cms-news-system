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

$has_comments  = have_comments();
$comments_open = comments_open();

if ( ! $has_comments && ! $comments_open ) {
	return;
}
?>

<section class="pgds-comments" id="comments" aria-labelledby="pgds-comments-title">

	<h2 class="pgds-comments__title related-head" id="pgds-comments-title">
		<?php esc_html_e( 'Bình luận', 'pgds' ); ?>
	</h2>

	<?php if ( $has_comments ) : ?>
		<ol class="pgds-comments__list">
			<?php
			wp_list_comments( array(
				'style'       => 'ol',
				'short_ping'  => true,
				'avatar_size' => 40,
			) );
			?>
		</ol>
		<?php the_comments_navigation(); ?>
	<?php endif; ?>

	<?php if ( $comments_open ) : ?>
		<div class="pgds-comments__form-wrap comment-box">
			<?php
			$req      = get_option( 'require_name_email' );
			$asterisk = $req ? ' <span class="required" aria-hidden="true">*</span>' : '';
			comment_form( array(
				'title_reply'          => '',
				'title_reply_before'   => '',
				'title_reply_after'    => '',
				'comment_notes_before' => '',
				'comment_notes_after'  => '',
				'label_submit'         => __( 'Gửi bình luận', 'pgds' ),
				'comment_field'        => '<p class="comment-form-comment"><label class="screen-reader-text" for="comment">' . esc_html__( 'Bình luận', 'pgds' ) . '</label><textarea id="comment" name="comment" cols="45" rows="4" maxlength="65525" required aria-required="true" autocomplete="off" placeholder="' . esc_attr__( 'Viết bình luận của bạn…', 'pgds' ) . '"></textarea></p>',
					'fields'               => array(
						'author'  => '<p class="comment-form-author"><label for="author">' . esc_html__( 'Tên', 'pgds' ) . $asterisk . '</label><input id="author" name="author" type="text" size="30" maxlength="245" autocomplete="name"' . ( $req ? ' required aria-required="true"' : '' ) . ' /></p>',
						'email'   => '<p class="comment-form-email"><label for="email">' . esc_html__( 'Email', 'pgds' ) . $asterisk . '</label><input id="email" name="email" type="email" size="30" maxlength="100" autocomplete="email"' . ( $req ? ' required aria-required="true"' : '' ) . ' /></p>',
						'url'     => '',
						'cookies' => '<p class="comment-form-cookies-consent"><input id="wp-comment-cookies-consent" name="wp-comment-cookies-consent" type="checkbox" value="yes" autocomplete="off" /><label for="wp-comment-cookies-consent">' . esc_html__( 'Lưu tên, email và website trong trình duyệt cho lần bình luận tiếp theo.', 'pgds' ) . '</label></p>',
					),
				'class_container'      => 'pgds-comments__form',
				'class_form'           => 'pgds-comments__form-inner',
				'submit_button'        => '<button name="%1$s" type="submit" id="%2$s" class="%3$s pgds-comments__submit">%4$s</button>',
			) );
			?>
		</div>
	<?php elseif ( $has_comments ) : ?>
		<p class="pgds-comments__closed" role="status">
			<?php esc_html_e( 'Bình luận đã đóng.', 'pgds' ); ?>
		</p>
	<?php endif; ?>

</section>
