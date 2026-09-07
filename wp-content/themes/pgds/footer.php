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
<footer class="pgds-footer block" role="contentinfo">
	<div class="pgds-wrap pgds-footer__grid footer-grid wrap">
		<div>
			<h4><?php bloginfo( 'name' ); ?></h4>
			<p class="pgds-footer__intro">
				<?php echo esc_html( __( 'Chuyên trang tin điện tử - tin tức, đời sống và văn hóa Phật giáo', 'pgds' ) ); ?>
			</p>
			<?php
			/*
			 * REQUIRED before go-live (Decree 72/2013 + Press Law 2016):
			 * license number, governing body, Editor-in-Chief, address, phone, email.
			 * Set via the Customizer/'pgds_footer_legal' option so editors can update it.
			 */
			$footer_legal = get_option( 'pgds_footer_legal', '' );
			if ( $footer_legal && false === strpos( $footer_legal, '[Cần bổ sung' ) ) :
			?>
				<p class="pgds-footer__legal">
					<?php echo wp_kses_post( $footer_legal ); ?>
				</p>
			<?php endif; ?>
		</div>

		<?php $footer_categories = pgds_category_tree(); ?>
		<?php foreach ( array_chunk( $footer_categories, 3, true ) as $category_group ) : ?>
			<div>
				<h4><?php esc_html_e( 'Chuyên mục', 'pgds' ); ?></h4>
				<ul>
					<?php foreach ( $category_group as $slug => $node ) : ?>
						<?php $term = pgds_category_term( $slug ); ?>
						<?php if ( $term instanceof WP_Term ) : ?>
							<?php $term_url = get_term_link( $term ); ?>
							<?php if ( ! is_wp_error( $term_url ) ) : ?>
								<li><a href="<?php echo esc_url( $term_url ); ?>"><?php echo esc_html( pgds_category_display_label( $slug, $node['label'] ) ); ?></a></li>
							<?php endif; ?>
						<?php endif; ?>
					<?php endforeach; ?>
				</ul>
			</div>
		<?php endforeach; ?>

		<div>
			<h4><?php esc_html_e( 'Liên hệ', 'pgds' ); ?></h4>
			<ul>
				<?php
				$editor_name = get_option( 'pgds_editor_name', __( 'Tổng biên tập: [Họ tên]', 'pgds' ) );
				if ( $editor_name && false === strpos( $editor_name, '[Cần bổ sung' ) ) :
				?>
					<li><?php echo esc_html( $editor_name ); ?></li>
				<?php endif; ?>
				<?php
				$contact_email = get_option( 'pgds_contact_email', 'toasoan@phatgiaovadoisong.vn' );
				if ( $contact_email && false === strpos( $contact_email, '[Cần bổ sung' ) ) :
				?>
					<li><?php echo esc_html( $contact_email ); ?></li>
				<?php endif; ?>
			</ul>
		</div>
	</div>

	<div class="pgds-footer__bottom footer-bottom">
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
