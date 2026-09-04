<?php
/**
 * Hoang Dao (auspicious hours) lookup.
 *
 * @package pgds-lunar
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PGDS_Hoang_Dao {

	/**
	 * Dia Chi names with their hour ranges.
	 *
	 * @var array<int, array{name: string, hours: string}>
	 */
	private const DIA_CHI_HOURS = [
		0  => [ 'name' => 'Tý',   'hours' => '23h-1h' ],
		1  => [ 'name' => 'Sửu',  'hours' => '1h-3h' ],
		2  => [ 'name' => 'Dần',  'hours' => '3h-5h' ],
		3  => [ 'name' => 'Mão',  'hours' => '5h-7h' ],
		4  => [ 'name' => 'Thìn', 'hours' => '7h-9h' ],
		5  => [ 'name' => 'Tỵ',   'hours' => '9h-11h' ],
		6  => [ 'name' => 'Ngọ',  'hours' => '11h-13h' ],
		7  => [ 'name' => 'Mùi',  'hours' => '13h-15h' ],
		8  => [ 'name' => 'Thân', 'hours' => '15h-17h' ],
		9  => [ 'name' => 'Dậu',  'hours' => '17h-19h' ],
		10 => [ 'name' => 'Tuất', 'hours' => '19h-21h' ],
		11 => [ 'name' => 'Hợi',  'hours' => '21h-23h' ],
	];

	/**
	 * Auspicious hour indices for each day Dia Chi.
	 *
	 * Key: Dia Chi index of the day (0-11).
	 * Value: array of Dia Chi indices that are auspicious.
	 *
	 * @var array<int, int[]>
	 */
	private const HOANG_DAO = [
		0  => [ 0, 1, 3, 6, 8, 9 ],     // Ty: Ty, Suu, Mao, Ngo, Than, Dau
		1  => [ 2, 3, 5, 8, 10, 11 ],    // Suu: Dan, Mao, Ty, Than, Tuat, Hoi
		2  => [ 0, 1, 4, 5, 7, 10 ],     // Dan: Ty, Suu, Thin, Ty, Mui, Tuat
		3  => [ 0, 2, 3, 6, 7, 9 ],      // Mao: Ty, Dan, Mao, Ngo, Mui, Dau
		4  => [ 2, 4, 5, 8, 9, 11 ],     // Thin: Dan, Thin, Ty, Than, Dau, Hoi
		5  => [ 0, 1, 4, 7, 10, 11 ],    // Ty: Ty, Suu, Thin, Mui, Tuat, Hoi
		6  => [ 0, 1, 3, 6, 8, 9 ],      // Ngo: Ty, Suu, Mao, Ngo, Than, Dau
		7  => [ 2, 3, 5, 8, 10, 11 ],    // Mui: Dan, Mao, Ty, Than, Tuat, Hoi
		8  => [ 0, 1, 4, 5, 7, 10 ],     // Than: Ty, Suu, Thin, Ty, Mui, Tuat
		9  => [ 0, 2, 3, 6, 7, 9 ],      // Dau: Ty, Dan, Mao, Ngo, Mui, Dau
		10 => [ 2, 4, 5, 8, 9, 11 ],     // Tuat: Dan, Thin, Ty, Than, Dau, Hoi
		11 => [ 0, 1, 4, 7, 10, 11 ],    // Hoi: Ty, Suu, Thin, Mui, Tuat, Hoi
	];

	/**
	 * Get the auspicious hours string for a given day's Dia Chi index.
	 *
	 * @param int $chi_index Dia Chi index (0-11) from Can Chi day calculation.
	 * @return string e.g. "Dan (3h-5h), Thin (7h-9h), Ty (9h-11h)"
	 */
	public static function for_day_chi( int $chi_index ): string {
		$indices = self::HOANG_DAO[ $chi_index ] ?? [];
		$parts   = [];

		foreach ( $indices as $idx ) {
			$info    = self::DIA_CHI_HOURS[ $idx ];
			$parts[] = $info['name'] . ' (' . $info['hours'] . ')';
		}

		return implode( ', ', $parts );
	}
}
