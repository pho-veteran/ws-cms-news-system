<?php
/**
 * Lunar calendar converter using the Ho Ngoc Duc algorithm.
 *
 * Encoding per table entry (hex integer):
 *   - Bits 0-3  : leap month index (0 = no leap month that year).
 *   - Bits 4-15 : day counts for regular months 1-12
 *                  (bit 15 = month 1, bit 4 = month 12; 1 = 30 days, 0 = 29 days).
 *   - Bit 16    : leap month day count (1 = 30 days, 0 = 29 days).
 *
 * @package pgds-lunar
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PGDS_Lunar_Converter {

	/**
	 * Lunar info for years 1900-2100 (201 entries).
	 *
	 * @var int[]
	 */
	private static array $LUNAR_INFO = [
		// 1900-1909
		0x04bd8, 0x04ae0, 0x0a570, 0x054d5, 0x0d260, 0x0d950, 0x16554, 0x056a0, 0x09ad0, 0x055d2,
		// 1910-1919
		0x04ae0, 0x0a5b6, 0x0a4d0, 0x0d250, 0x1d255, 0x0b540, 0x0d6a0, 0x0ada2, 0x095b0, 0x14977,
		// 1920-1929
		0x04970, 0x0a4b0, 0x0b4b5, 0x06a50, 0x06d40, 0x1ab54, 0x02b60, 0x09570, 0x052f2, 0x04970,
		// 1930-1939
		0x06566, 0x0d4a0, 0x0ea50, 0x06e95, 0x05ad0, 0x02b60, 0x186e3, 0x092e0, 0x1c8d7, 0x0c950,
		// 1940-1949
		0x0d4a0, 0x1d8a6, 0x0b550, 0x056a0, 0x1a5b4, 0x025d0, 0x092d0, 0x0d2b2, 0x0a950, 0x0b557,
		// 1950-1959
		0x06ca0, 0x0b550, 0x15355, 0x04da0, 0x0a5b0, 0x14573, 0x052b0, 0x0a9a8, 0x0e950, 0x06aa0,
		// 1960-1969
		0x0aea6, 0x0ab50, 0x04b60, 0x0aae4, 0x0a570, 0x05260, 0x0f263, 0x0d950, 0x05b57, 0x056a0,
		// 1970-1979
		0x096d0, 0x04dd5, 0x04ad0, 0x0a4d0, 0x0d4d4, 0x0d250, 0x0d558, 0x0b540, 0x0b6a0, 0x195a6,
		// 1980-1989
		0x095b0, 0x049b0, 0x0a974, 0x0a4b0, 0x0b27a, 0x06a50, 0x06d40, 0x0af46, 0x0ab60, 0x09570,
		// 1990-1999
		0x04af5, 0x04970, 0x064b0, 0x074a3, 0x0ea50, 0x06b58, 0x05ac0, 0x0ab60, 0x096d5, 0x092e0,
		// 2000-2009
		0x0c960, 0x0d954, 0x0d4a0, 0x0da50, 0x07552, 0x056a0, 0x0abb7, 0x025d0, 0x092d0, 0x0cab5,
		// 2010-2019
		0x0a950, 0x0b4a0, 0x0baa4, 0x0ad50, 0x055d9, 0x04ba0, 0x0a5b0, 0x15176, 0x052b0, 0x0a930,
		// 2020-2029
		0x07954, 0x06aa0, 0x0ad50, 0x05b52, 0x04b60, 0x0a6e6, 0x0a4e0, 0x0d260, 0x0ea65, 0x0d530,
		// 2030-2039
		0x05aa0, 0x076a3, 0x096d0, 0x04afb, 0x04ad0, 0x0a4d0, 0x1d0b6, 0x0d250, 0x0d520, 0x0dd45,
		// 2040-2049
		0x0b5a0, 0x056d0, 0x055b2, 0x049b0, 0x0a577, 0x0a4b0, 0x0aa50, 0x1b255, 0x06d20, 0x0ada0,
		// 2050-2059
		0x14b63, 0x09370, 0x049f8, 0x04970, 0x064b0, 0x168a6, 0x0ea50, 0x06aa0, 0x1a6c4, 0x0aae0,
		// 2060-2069
		0x092e0, 0x0d2e3, 0x0c960, 0x0d557, 0x0d4a0, 0x0da50, 0x05d55, 0x056a0, 0x0a6d0, 0x055d4,
		// 2070-2079
		0x052d0, 0x0a9b8, 0x0a950, 0x0b4a0, 0x0b6a6, 0x0ad50, 0x055a0, 0x0aba4, 0x0a5b0, 0x052b0,
		// 2080-2089
		0x0b273, 0x06930, 0x07337, 0x06aa0, 0x0ad50, 0x14b55, 0x04b60, 0x0a570, 0x054e4, 0x0d160,
		// 2090-2099
		0x0e968, 0x0d520, 0x0daa0, 0x16aa6, 0x056d0, 0x04ae0, 0x0a9d4, 0x0a4d0, 0x0d150, 0x0f252,
		// 2100
		0x0d520,
	];

	/**
	 * Julian Day Number from a Gregorian date.
	 *
	 * @param int $d Day.
	 * @param int $m Month.
	 * @param int $y Year.
	 * @return int Julian Day Number.
	 */
	public static function jdn( int $d, int $m, int $y ): int {
		$a  = intdiv( 14 - $m, 12 );
		$y1 = $y + 4800 - $a;
		$m1 = $m + 12 * $a - 3;

		return $d
			+ intdiv( 153 * $m1 + 2, 5 )
			+ 365 * $y1
			+ intdiv( $y1, 4 )
			- intdiv( $y1, 100 )
			+ intdiv( $y1, 400 )
			- 32045;
	}

	/**
	 * Days in a regular lunar month (1-12).
	 *
	 * Checks the bit at position (16 - month) within the year info.
	 *
	 * @param int $year_info Encoded year info.
	 * @param int $month     Regular month number (1-12).
	 * @return int 29 or 30.
	 */
	public static function month_days( int $year_info, int $month ): int {
		return ( $year_info & ( 0x10000 >> $month ) ) ? 30 : 29;
	}

	/**
	 * Days in the leap month (0 if no leap that year).
	 *
	 * The leap month's day count is stored in bit 16.
	 *
	 * @param int $year_info Encoded year info.
	 * @return int 29, 30, or 0 if no leap month.
	 */
	public static function leap_month_days( int $year_info ): int {
		if ( 0 === ( $year_info & 0xf ) ) {
			return 0;
		}

		return ( $year_info & 0x10000 ) ? 30 : 29;
	}

	/**
	 * Leap month index for a given year (0 = no leap).
	 *
	 * @param int $year_info Encoded year info.
	 * @return int Leap month number (1-12), or 0.
	 */
	public static function leap_month( int $year_info ): int {
		return $year_info & 0xf;
	}

	/**
	 * Total days in a lunar year.
	 *
	 * Base is 348 (12 x 29). Each set bit in positions 4-15 adds one day
	 * to the corresponding month. Leap month days added separately from bit 16.
	 *
	 * @param int $year_info Encoded year info.
	 * @return int Total days (353-385).
	 */
	public static function lunar_year_days( int $year_info ): int {
		$sum = 348;

		for ( $i = 0x8000; $i > 0x8; $i >>= 1 ) {
			$sum += ( $year_info & $i ) ? 1 : 0;
		}

		$sum += self::leap_month_days( $year_info );

		return $sum;
	}

	/**
	 * Convert a solar (Gregorian) date to a Vietnamese lunar date.
	 *
	 * Uses the reference Ho Ngoc Duc algorithm: walk years from 1900,
	 * then walk months with leap-month insertion.
	 *
	 * @param int $d Day.
	 * @param int $m Month.
	 * @param int $y Year.
	 * @return array{day: int, month: int, year: int, is_leap: bool, jdn: int}
	 */
	public static function solar_to_lunar( int $d, int $m, int $y ): array {
		$jdn_val = self::jdn( $d, $m, $y );
		$tet_jdn = self::jdn( 31, 1, 1900 ); // Tet 1900 = Jan 31, 1900.

		$offset = $jdn_val - $tet_jdn;

		// --- Walk years from 1900 until offset is consumed. ---
		$temp       = 0;
		$lunar_year = 1900;

		for ( $lunar_year = 1900; $lunar_year < 2101 && $offset > 0; $lunar_year++ ) {
			$temp    = self::lunar_year_days( self::$LUNAR_INFO[ $lunar_year - 1900 ] );
			$offset -= $temp;
		}

		if ( $offset < 0 ) {
			$offset += $temp;
			$lunar_year--;
		}

		$year_info = self::$LUNAR_INFO[ $lunar_year - 1900 ];
		$leap      = self::leap_month( $year_info );
		$is_leap   = false;

		// --- Walk months with leap-month insertion. ---
		$i = 1;

		for ( $i = 1; $i < 14 && $offset > 0; $i++ ) {
			if ( $leap > 0 && $i === $leap + 1 && ! $is_leap ) {
				// Insert the leap month before advancing past leap+1.
				$i--;
				$is_leap = true;
				$temp    = self::leap_month_days( $year_info );
			} else {
				$temp = self::month_days( $year_info, $i );
			}

			if ( $is_leap && $i === $leap + 1 ) {
				$is_leap = false;
			}

			$offset -= $temp;
		}

		// Edge case: offset exactly 0 at the leap-month boundary.
		if ( 0 === $offset && $leap > 0 && $i === $leap + 1 ) {
			if ( $is_leap ) {
				$is_leap = false;
			} else {
				$is_leap = true;
				$i--;
			}
		}

		if ( $offset < 0 ) {
			$offset += $temp;
			$i--;
		}

		$lunar_month = $i;
		$lunar_day   = $offset + 1;

		return [
			'day'     => $lunar_day,
			'month'   => $lunar_month,
			'year'    => $lunar_year,
			'is_leap' => $is_leap,
			'jdn'     => $jdn_val,
		];
	}
}
