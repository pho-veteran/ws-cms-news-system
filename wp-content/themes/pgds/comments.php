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
?>

<section class="pgds-comments" id="comments" aria-labelledby="pgds-comments-title">

	<?php if ( have_comments() || comments_open() ) : ?>
		<h2 class="pgds-comments__title" id="pgds-comments-title">
			<?php esc_html_e( 'Bình luận', 'pgds' ); ?>
		</h2>
	<?php endif; ?>

	<?php if ( have_comments() ) : ?>
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

	<?php if ( comments_open() ) : ?>
		<div class="pgds-comments__form-wrap">
			<?php
			comment_form( array(
				'title_reply'          => '',
				'title_reply_before'   => '',
				'title_reply_after'    => '',
				'comment_notes_before' => '',
				'comment_notes_after'  => '',
				'label_submit'         => __( 'Gửi bình luận', 'pgds' ),
				'comment_field'        => '<p class="comment-form-comment"><textarea id="comment" name="comment" cols="45" rows="4" maxlength="65525" required placeholder="' . esc_attr__( 'Viết bình luận của bạn…', 'pgds' ) . '"></textarea></p>',
				'class_container'      => 'pgds-comments__form',
				'class_form'           => 'pgds-comments__form-inner',
				'submit_button'        => '<input name="%1$s" type="submit" id="%2$s" class="%3$s pgds-comments__submit" value="%4$s" />',
			) );
			?>
		</div>
	<?php elseif ( get_comments_number() && ! comments_open() ) : ?>
		<p class="pgds-comments__closed">
			<?php esc_html_e( 'Bình luận đã đóng.', 'pgds' ); ?>
		</p>
	<?php endif; ?>

</section>
