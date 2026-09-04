<?php
/**
 * Nap Am (Sexagenary cycle element) lookup.
 *
 * @package pgds-lunar
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PGDS_Nap_Am {

	/**
	 * Complete 30-entry Nap Am table.
	 *
	 * Each entry covers two consecutive Can Chi pairs in the 60-element cycle.
	 *
	 * @var array<int, array{name: string, hanh: string}>
	 */
	private const TABLE = [
		[ 'name' => 'Hải trung kim',    'hanh' => 'Kim' ],   // 0: Giáp Tý, Ất Sửu
		[ 'name' => 'Lô trung hỏa',    'hanh' => 'Hỏa' ],   // 1: Bính Dần, Đinh Mão
		[ 'name' => 'Đại lâm mộc',     'hanh' => 'Mộc' ],   // 2: Mậu Thìn, Kỷ Tỵ
		[ 'name' => 'Lộ bàng thổ',     'hanh' => 'Thổ' ],   // 3: Canh Ngọ, Tân Mùi
		[ 'name' => 'Kiếm phong kim',  'hanh' => 'Kim' ],   // 4: Nhâm Thân, Quý Dậu
		[ 'name' => 'Sơn đầu hỏa',    'hanh' => 'Hỏa' ],   // 5: Giáp Tuất, Ất Hợi
		[ 'name' => 'Giản hạ thủy',    'hanh' => 'Thủy' ],  // 6: Bính Tý, Đinh Sửu
		[ 'name' => 'Thành đầu thổ',   'hanh' => 'Thổ' ],   // 7: Mậu Dần, Kỷ Mão
		[ 'name' => 'Bạch lạp kim',    'hanh' => 'Kim' ],   // 8: Canh Thìn, Tân Tỵ
		[ 'name' => 'Dương liễu mộc',  'hanh' => 'Mộc' ],   // 9: Nhâm Ngọ, Quý Mùi
		[ 'name' => 'Tuyền trung thủy', 'hanh' => 'Thủy' ], // 10: Giáp Thân, Ất Dậu
		[ 'name' => 'Ốc thượng thổ',   'hanh' => 'Thổ' ],   // 11: Bính Tuất, Đinh Hợi
		[ 'name' => 'Tích lịch hỏa',   'hanh' => 'Hỏa' ],   // 12: Mậu Tý, Kỷ Sửu
		[ 'name' => 'Tùng bách mộc',   'hanh' => 'Mộc' ],   // 13: Canh Dần, Tân Mão
		[ 'name' => 'Trường lưu thủy', 'hanh' => 'Thủy' ],  // 14: Nhâm Thìn, Quý Tỵ
		[ 'name' => 'Sa trung kim',    'hanh' => 'Kim' ],   // 15: Giáp Ngọ, Ất Mùi
		[ 'name' => 'Sơn hạ hỏa',     'hanh' => 'Hỏa' ],   // 16: Bính Thân, Đinh Dậu
		[ 'name' => 'Bình địa mộc',    'hanh' => 'Mộc' ],   // 17: Mậu Tuất, Kỷ Hợi
		[ 'name' => 'Bích thượng thổ', 'hanh' => 'Thổ' ],   // 18: Canh Tý, Tân Sửu
		[ 'name' => 'Kim bạc kim',     'hanh' => 'Kim' ],   // 19: Nhâm Dần, Quý Mão
		[ 'name' => 'Phú đăng hỏa',   'hanh' => 'Hỏa' ],   // 20: Giáp Thìn, Ất Tỵ
		[ 'name' => 'Thiên hà thủy',   'hanh' => 'Thủy' ],  // 21: Bính Ngọ, Đinh Mùi
		[ 'name' => 'Đại dịch thổ',    'hanh' => 'Thổ' ],   // 22: Mậu Thân, Kỷ Dậu
		[ 'name' => 'Thoa xuyến kim',  'hanh' => 'Kim' ],   // 23: Canh Tuất, Tân Hợi
		[ 'name' => 'Tang đố mộc',     'hanh' => 'Mộc' ],   // 24: Nhâm Tý, Quý Sửu
		[ 'name' => 'Đại khê thủy',    'hanh' => 'Thủy' ],  // 25: Giáp Dần, Ất Mão
		[ 'name' => 'Sa trung thổ',    'hanh' => 'Thổ' ],   // 26: Bính Thìn, Đinh Tỵ
		[ 'name' => 'Thiên thượng hỏa', 'hanh' => 'Hỏa' ],  // 27: Mậu Ngọ, Kỷ Mùi
		[ 'name' => 'Thạch lựu mộc',   'hanh' => 'Mộc' ],   // 28: Canh Thân, Tân Dậu
		[ 'name' => 'Đại hải thủy',    'hanh' => 'Thủy' ],  // 29: Nhâm Tuất, Quý Hợi
	];

	/**
	 * Look up the Nap Am entry for a Can Chi pair index.
	 *
	 * @param int $pair_index The 60-cycle index from PGDS_Can_Chi::day().
	 * @return array{name: string, hanh: string}
	 */
	public static function lookup( int $pair_index ): array {
		return self::TABLE[ intdiv( $pair_index, 2 ) % 30 ];
	}
}
