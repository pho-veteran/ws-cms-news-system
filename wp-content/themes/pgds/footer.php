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
				<?php echo esc_html( __( 'Chuyên trang tin điện tử — tin tức, đời sống và văn hóa Phật giáo.', 'pgds' ) ); ?>
			</p>
			<?php
			$contact = pgds_footer_contact_defaults();
			foreach ( $contact as $option => $default ) {
				$value = trim( (string) get_option( $option, $default ) );
				if ( '' !== $value && ! str_contains( $value, '[Cần bổ sung' ) && ! str_contains( $value, '[Họ tên]' ) ) {
					$contact[ $option ] = $value;
				}
			}
			$phone = preg_replace( '/[^0-9+]/', '', $contact['pgds_contact_phone'] );
			?>
			<ul class="pgds-footer__contacts">
				<li><?php echo esc_html( $contact['pgds_editor_name'] ); ?></li>
				<li><?php echo esc_html( sprintf( __( 'Phó Tổng biên tập: %s', 'pgds' ), $contact['pgds_deputy_editor_name'] ) ); ?></li>
				<li><?php echo esc_html( sprintf( __( 'Địa chỉ: %s', 'pgds' ), $contact['pgds_contact_address'] ) ); ?></li>
				<li><?php esc_html_e( 'Hotline:', 'pgds' ); ?> <a href="<?php echo esc_url( 'tel:' . $phone ); ?>"><?php echo esc_html( $contact['pgds_contact_phone'] ); ?></a></li>
				<li><?php esc_html_e( 'Email:', 'pgds' ); ?> <a href="<?php echo esc_url( 'mailto:' . sanitize_email( $contact['pgds_contact_email'] ) ); ?>"><?php echo esc_html( $contact['pgds_contact_email'] ); ?></a></li>
			</ul>
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

		<?php
		$social_links = array();
		foreach ( pgds_footer_social_networks() as $network => $label ) {
			$url = pgds_sanitize_footer_social_url( get_option( 'pgds_social_' . $network, '' ) );
			if ( $url ) {
				$social_links[ $network ] = array( $label, $url );
			}
		}
		?>
		<?php if ( $social_links ) : ?>
			<div>
				<h4><?php esc_html_e( 'Theo dõi chúng tôi', 'pgds' ); ?></h4>
				<ul class="pgds-footer__social">
					<?php foreach ( $social_links as $network => list( $label, $url ) ) : ?>
						<li>
							<a href="<?php echo esc_url( $url ); ?>" aria-label="<?php echo esc_attr( $label ); ?>">
								<?php pgds_icon( $network, array( 'size' => 20 ) ); ?>
							</a>
						</li>
					<?php endforeach; ?>
				</ul>
			</div>
		<?php endif; ?>
	</div>

	<div class="pgds-footer__bottom footer-bottom">
		<?php
		printf(
			/* translators: %1$s year, %2$s site name */
			esc_html__( '© %1$s %2$s', 'pgds' ),
			esc_html( date_i18n( 'Y' ) ),
			esc_html( get_bloginfo( 'name' ) )
		);
		?>
	</div>
</footer>

<?php wp_footer(); ?>
</body>
</html>
