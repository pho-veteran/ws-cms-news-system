<?php
/**
 * Can Chi (Heavenly Stems & Earthly Branches) calculations.
 *
 * @package pgds-lunar
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PGDS_Can_Chi {

	private const THIEN_CAN = [
		'Giáp', 'Ất', 'Bính', 'Đinh', 'Mậu',
		'Kỷ', 'Canh', 'Tân', 'Nhâm', 'Quý',
	];

	private const DIA_CHI = [
		'Tý', 'Sửu', 'Dần', 'Mão', 'Thìn', 'Tỵ',
		'Ngọ', 'Mùi', 'Thân', 'Dậu', 'Tuất', 'Hợi',
	];

	/**
	 * Can Chi name for a lunar year.
	 *
	 * @param int $lunar_year The lunar year.
	 * @return string e.g. "Binh Ngo".
	 */
	public static function year( int $lunar_year ): string {
		$can = self::THIEN_CAN[ ( $lunar_year + 6 ) % 10 ];
		$chi = self::DIA_CHI[ ( $lunar_year + 8 ) % 12 ];

		return "$can $chi";
	}

	/**
	 * Can Chi for a given Julian Day Number.
	 *
	 * @param int $jdn Julian Day Number.
	 * @return array{can: string, chi: string, pair: string, chi_index: int, pair_index: int}
	 */
	public static function day( int $jdn ): array {
		$can_idx = ( $jdn + 9 ) % 10;
		$chi_idx = ( $jdn + 1 ) % 12;

		// Traditional 60-cycle position: unique n in [0,59] where
		// n % 10 == can_idx and n % 12 == chi_idx.
		$cycle60 = 0;
		for ( $n = $can_idx; $n < 60; $n += 10 ) {
			if ( $n % 12 === $chi_idx ) {
				$cycle60 = $n;
				break;
			}
		}

		return [
			'can'        => self::THIEN_CAN[ $can_idx ],
			'chi'        => self::DIA_CHI[ $chi_idx ],
			'pair'       => self::THIEN_CAN[ $can_idx ] . ' ' . self::DIA_CHI[ $chi_idx ],
			'chi_index'  => $chi_idx,
			'pair_index' => $cycle60,
		];
	}
}
