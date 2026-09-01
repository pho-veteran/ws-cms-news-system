<?php
/**
 * Customizer: editorial and legal footer information.
 *
 * §13's go-live gate includes "Footer legal/editorial-office information has stakeholder
 * approval", and footer.php reads three options for it — `pgds_footer_legal`,
 * `pgds_editor_name`, `pgds_contact_email` — with a comment promising they are "Set via the
 * Customizer".
 *
 * No Customizer registration existed anywhere in the theme. So the options could only ever
 * be written with `wp option update`, and the site shipped
 * "[Cần bổ sung: Giấy phép hoạt động, ...]" and "Tổng biên tập: [Họ tên]" in the footer of
 * every page. The gate is explicitly *not* a technical decision — it needs a Vietnamese
 * legal/compliance sign-off — which means the person who has to satisfy it is precisely the
 * person who does not use WP-CLI. This is the same failure mode as the meta box that landed
 * in a hidden container: the data layer was correct and the editorial surface did not exist.
 *
 * Registered as a Customizer section rather than a Settings page so it inherits the live
 * preview: legal text is long, wraps, and sits in a four-column grid, so seeing it in place
 * before saving is worth more here than a plain form.
 *
 * @package pgds
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register the "Toà soạn" section and its controls.
 *
 * @param WP_Customize_Manager $wp_customize Customizer manager.
 */
function pgds_customize_register( $wp_customize ) {
	$wp_customize->add_section(
		'pgds_editorial',
		array(
			'title'       => __( 'Thông tin toà soạn', 'pgds' ),
			'priority'    => 30,
			'description' => __( 'Thông tin bắt buộc theo Nghị định 72/2013 và Luật Báo chí 2016: giấy phép hoạt động, cơ quan chủ quản, tổng biên tập, địa chỉ, điện thoại, email. Cần được duyệt trước khi phát hành.', 'pgds' ),
		)
	);

	/*
	 * Legal block. 'refresh' rather than postMessage: the value is multi-line HTML inside a
	 * grid column, so a partial refresh is what shows the real wrapping.
	 */
	$wp_customize->add_setting(
		'pgds_footer_legal',
		array(
			'default'           => '',
			'type'              => 'option',
			'capability'        => 'manage_options',
			// wp_kses_post, matching how footer.php renders it: editors legitimately need
			// <br> and <strong> in a licence block, but not scripts or iframes.
			'sanitize_callback' => 'wp_kses_post',
			'transport'         => 'refresh',
		)
	);
	$wp_customize->add_control(
		'pgds_footer_legal',
		array(
			'section'     => 'pgds_editorial',
			'label'       => __( 'Thông tin pháp lý (chân trang)', 'pgds' ),
			'description' => __( 'Cho phép <br> và <strong>. Ví dụ: Giấy phép số … do … cấp ngày … — Cơ quan chủ quản: … — Địa chỉ: … — Điện thoại: …', 'pgds' ),
			'type'        => 'textarea',
			'input_attrs' => array( 'rows' => 6 ),
		)
	);

	$wp_customize->add_setting(
		'pgds_editor_name',
		array(
			'default'           => '',
			'type'              => 'option',
			'capability'        => 'manage_options',
			'sanitize_callback' => 'sanitize_text_field',
			'transport'         => 'refresh',
		)
	);
	$wp_customize->add_control(
		'pgds_editor_name',
		array(
			'section'     => 'pgds_editorial',
			'label'       => __( 'Tổng biên tập', 'pgds' ),
			'description' => __( 'Hiển thị nguyên văn ở cột "Liên hệ", nên gồm cả chức danh. Ví dụ: Tổng biên tập: Nguyễn Văn A', 'pgds' ),
			'type'        => 'text',
		)
	);

	$wp_customize->add_setting(
		'pgds_contact_email',
		array(
			'default'           => '',
			'type'              => 'option',
			'capability'        => 'manage_options',
			'sanitize_callback' => 'sanitize_email',
			'transport'         => 'refresh',
		)
	);
	$wp_customize->add_control(
		'pgds_contact_email',
		array(
			'section' => 'pgds_editorial',
			'label'   => __( 'Email toà soạn', 'pgds' ),
			'type'    => 'email',
		)
	);
}
add_action( 'customize_register', 'pgds_customize_register' );

/**
 * Warn in the admin while the legal block is still a placeholder.
 *
 * §13 makes this a launch gate, and a placeholder in a footer is easy to miss — it is below
 * the fold on every page and reads like design filler. The notice links straight to the
 * control so the gap is actionable rather than merely reported, and it disappears the moment
 * the option holds real text.
 */
function pgds_editorial_notice() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$legal = (string) get_option( 'pgds_footer_legal', '' );
	// The default placeholder is recognisable by its bracket marker; an empty value is
	// equally unset.
	if ( '' !== trim( $legal ) && false === strpos( $legal, 'Cần bổ sung' ) ) {
		return;
	}

	printf(
		'<div class="notice notice-warning"><p><strong>%s</strong> %s <a href="%s">%s</a></p></div>',
		esc_html__( 'pgds:', 'pgds' ),
		esc_html__( 'Thông tin pháp lý ở chân trang chưa được điền. Đây là điều kiện bắt buộc trước khi phát hành (Nghị định 72/2013, Luật Báo chí 2016).', 'pgds' ),
		esc_url( admin_url( 'customize.php?autofocus[section]=pgds_editorial' ) ),
		esc_html__( 'Điền ngay', 'pgds' )
	);
}
add_action( 'admin_notices', 'pgds_editorial_notice' );
