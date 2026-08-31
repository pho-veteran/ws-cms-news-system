<?php
/**
 * Sidebar block "Lich Van Nien".
 *
 * Duong lich: luon tinh tu ngay hien tai.
 * Am lich + menh + gio hoang dao + trich dan: lay tu CPT pgds_lunar_note (neu co),
 * doc qua custom field. Neu khong co -> chi hien duong lich + trich dan mac dinh.
 * (Chuyen doi am lich day du la scope RUN - proposal §10.3.)
 *
 * @param array $args { post: ?WP_Post }
 * @package pgds
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$note = $args['post'] ?? null;

$greg_day   = date_i18n( 'd' );
$greg_sub   = date_i18n( 'Tháng m \n\ă\m Y' );

$lunar_day = $note ? get_post_meta( $note->ID, '_pgds_lunar_day', true ) : '';
$lunar_sub = $note ? get_post_meta( $note->ID, '_pgds_lunar_sub', true ) : '';
$menh      = $note ? get_post_meta( $note->ID, '_pgds_menh', true ) : '';
$gio       = $note ? get_post_meta( $note->ID, '_pgds_gio', true ) : '';
$quote     = $note ? get_post_meta( $note->ID, '_pgds_quote', true ) : '';

if ( ! $quote ) {
	$quote = __( '"Tâm bình thì thế giới bình." — Lời Phật dạy', 'pgds' );
}
?>
<section class="pgds-side-block pgds-side-block--flush" aria-labelledby="pgds-lunar-title">
	<div class="pgds-lunar">
		<div class="pgds-lunar__header" id="pgds-lunar-title"><?php esc_html_e( 'Lịch Vạn Niên', 'pgds' ); ?></div>
		<div class="pgds-lunar__body">
			<div class="pgds-lunar__col">
				<div class="pgds-lunar__label"><?php esc_html_e( 'Dương Lịch', 'pgds' ); ?></div>
				<div class="pgds-lunar__num"><?php echo esc_html( $greg_day ); ?></div>
				<div class="pgds-lunar__sub"><?php echo esc_html( $greg_sub ); ?></div>
			</div>
			<div class="pgds-lunar__col pgds-lunar__col--lunar">
				<div class="pgds-lunar__label"><?php esc_html_e( 'Âm Lịch', 'pgds' ); ?></div>
				<div class="pgds-lunar__num"><?php echo esc_html( $lunar_day ? $lunar_day : '—' ); ?></div>
				<div class="pgds-lunar__sub"><?php echo esc_html( $lunar_sub ? $lunar_sub : __( 'Cập nhật thủ công', 'pgds' ) ); ?></div>
			</div>
		</div>
		<?php if ( $menh || $gio ) : ?>
			<div class="pgds-lunar__details">
				<?php if ( $menh ) : ?>
					<div><b><?php esc_html_e( 'Mệnh ngày:', 'pgds' ); ?></b> <?php echo esc_html( $menh ); ?></div>
				<?php endif; ?>
				<?php if ( $gio ) : ?>
					<div><b><?php esc_html_e( 'Giờ hoàng đạo:', 'pgds' ); ?></b> <?php echo esc_html( $gio ); ?></div>
				<?php endif; ?>
			</div>
		<?php endif; ?>
		<div class="pgds-lunar__quote"><?php echo esc_html( $quote ); ?></div>
	</div>
</section>
