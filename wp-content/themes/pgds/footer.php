<?php
/**
 * Footer with 4 columns + editorial legal info (proposal §2.3, §5.4 original table).
 *
 * @package pgds
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<footer class="pgds-footer" role="contentinfo">
	<div class="pgds-wrap pgds-footer__grid">
		<div>
			<h4><?php bloginfo( 'name' ); ?></h4>
			<p class="pgds-footer__intro">
				<?php echo esc_html( get_bloginfo( 'description' ) ); ?>
			</p>
			<p class="pgds-footer__legal">
				<?php
				/*
				 * REQUIRED before go-live (Decree 72/2013 + Press Law 2016):
				 * license number, governing body, Editor-in-Chief, address, phone, email.
				 * Set via the Customizer/'pgds_footer_legal' option so editors can update it.
				 */
				echo wp_kses_post( get_option( 'pgds_footer_legal', __( '[Cần bổ sung: Giấy phép hoạt động, Cơ quan chủ quản, Tổng biên tập, Địa chỉ, Điện thoại, Email]', 'pgds' ) ) );
				?>
			</p>
		</div>

		<div>
			<h4><?php esc_html_e( 'Chuyên mục', 'pgds' ); ?></h4>
			<ul>
				<li><a href="<?php echo esc_url( get_term_link( 'tin-phat-su', 'category' ) ); ?>"><?php esc_html_e( 'Tin Phật sự', 'pgds' ); ?></a></li>
				<li><a href="<?php echo esc_url( get_term_link( 'song-an-lanh', 'category' ) ); ?>"><?php esc_html_e( 'Sống an lành', 'pgds' ); ?></a></li>
				<li><a href="<?php echo esc_url( get_term_link( 'phat-tich', 'category' ) ); ?>"><?php esc_html_e( 'Phật tích', 'pgds' ); ?></a></li>
			</ul>
		</div>

		<div>
			<h4><?php esc_html_e( 'Chuyên mục', 'pgds' ); ?></h4>
			<ul>
				<li><a href="<?php echo esc_url( get_term_link( 'media', 'category' ) ); ?>"><?php esc_html_e( 'Media', 'pgds' ); ?></a></li>
				<li><a href="<?php echo esc_url( get_term_link( 'tot-doi-dep-dao', 'category' ) ); ?>"><?php esc_html_e( 'Tốt đời – đẹp đạo', 'pgds' ); ?></a></li>
				<li><a href="<?php echo esc_url( get_term_link( 'vietnam-buddhism', 'category' ) ); ?>"><?php esc_html_e( 'Phật giáo Việt Nam', 'pgds' ); ?></a></li>
			</ul>
		</div>

		<div>
			<h4><?php esc_html_e( 'Liên hệ', 'pgds' ); ?></h4>
			<ul>
				<li><?php echo esc_html( get_option( 'pgds_editor_name', __( 'Tổng biên tập: [Họ tên]', 'pgds' ) ) ); ?></li>
				<li><?php echo esc_html( get_option( 'pgds_contact_email', 'toasoan@phatgiaovadoisong.vn' ) ); ?></li>
			</ul>
		</div>
	</div>

	<div class="pgds-footer__bottom">
		<?php
		printf(
			/* translators: %1$s year, %2$s site name */
			esc_html__( '© %1$s %2$s — Bản quyền thuộc về toà soạn.', 'pgds' ),
			esc_html( date_i18n( 'Y' ) ),
			esc_html( get_bloginfo( 'name' ) )
		);
		?>
	</div>
</footer>

<?php wp_footer(); ?>
</body>
</html>
