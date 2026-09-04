<?php
/**
 * Plugin Name: PGDS Lunar Calendar
 * Description: Auto-computed Vietnamese lunar calendar for the pgds theme sidebar.
 * Version:     1.0.0
 * Text Domain: pgds-lunar
 *
 * @package pgds-lunar
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'PGDS_LUNAR_DIR', plugin_dir_path( __FILE__ ) );

// phpcs:disable -- UTF-8 string literals are intentional.
/**
 * Daily Buddhist quotes for sidebar rotation.
 */
class PGDS_Lunar_Quotes {

	/**
	 * @var string[]
	 */
	private static array $QUOTES = [
		'"Tâm bình thì thế giới bình." — Lời Phật dạy',
		'"Gieo nhân nào, gặt quả nấy." — Kinh Nhân Quả',
		'"Từ bi là sức mạnh vĩ đại nhất." — Đức Phật',
		'"Hãy tự mình thắp đuốc lên mà đi." — Kinh Di Giáo',
		'"Chiến thắng vạn quân không bằng tự thắng mình." — Kinh Pháp Cú',
		'"Hận thù không thể chấm dứt bằng hận thù, chỉ có tình thương mới xóa bỏ được hận thù." — Kinh Pháp Cú',
		'"An lạc từ tâm, không từ ngoại cảnh." — Lời Phật dạy',
		'"Buông xả không có nghĩa là từ bỏ, mà là không còn chấp giữ." — Thiền sư Thích Nhất Hạnh',
		'"Mỗi ngày là một cơ hội để gieo trồng hạt giống thiện lành." — Lời Phật dạy',
		'"Sống trong hiện tại là cách tu tập đơn giản nhất." — Thiền sư Thích Nhất Hạnh',
	];

	/**
	 * Get the quote for today.
	 *
	 * @return string
	 */
	public static function today(): string {
		$day_of_year = (int) gmdate( 'z' );
		return self::$QUOTES[ $day_of_year % count( self::$QUOTES ) ];
	}
}
// phpcs:enable

/**
 * Load all class files on plugins_loaded.
 */
add_action(
	'plugins_loaded',
	static function (): void {
		require_once PGDS_LUNAR_DIR . 'includes/class-lunar-converter.php';
		require_once PGDS_LUNAR_DIR . 'includes/class-can-chi.php';
		require_once PGDS_LUNAR_DIR . 'includes/class-nap-am.php';
		require_once PGDS_LUNAR_DIR . 'includes/class-hoang-dao.php';
		require_once PGDS_LUNAR_DIR . 'includes/class-rest-controller.php';
	}
);

/**
 * Register the REST API route.
 */
add_action( 'rest_api_init', [ 'PGDS_Lunar_REST', 'register' ] );

/**
 * Get today's lunar calendar data.
 *
 * Chains the converter, Can Chi, Nap Am, and Hoang Dao classes
 * to produce a complete data array for the sidebar widget.
 * Result is cached in object cache keyed by date.
 *
 * @return array{
 *     lunar_day: string,
 *     lunar_sub: string,
 *     menh: string,
 *     gio: string,
 *     quote: string,
 *     greg_day: string,
 *     greg_sub: string,
 * }
 */
function pgds_lunar_get_today(): array {
	$now      = current_datetime();
	$date_key = $now->format( 'Y-m-d' );

	$cached = wp_cache_get( $date_key, 'pgds_lunar' );
	if ( false !== $cached ) {
		return $cached;
	}

	$year  = (int) $now->format( 'Y' );
	$month = (int) $now->format( 'm' );
	$day   = (int) $now->format( 'd' );

	// Solar-to-lunar conversion.
	$lunar = PGDS_Lunar_Converter::solar_to_lunar( $day, $month, $year );

	// Can Chi of the year.
	$year_can_chi = PGDS_Can_Chi::year( $lunar['year'] );

	// Can Chi of the day.
	$day_can_chi = PGDS_Can_Chi::day( $lunar['jdn'] );

	// Nap Am (element) of the day.
	$nap_am = PGDS_Nap_Am::lookup( $day_can_chi['pair_index'] );

	// Hoang Dao (auspicious hours).
	$hoang_dao = PGDS_Hoang_Dao::for_day_chi( $day_can_chi['chi_index'] );

	// Lunar sub label: "Thang X <Can Chi year>".
	$lunar_sub = sprintf(
		'Tháng %d %s',
		$lunar['month'],
		$year_can_chi
	);

	// Gregorian sub label: "Thang MM nam YYYY".
	$greg_sub = sprintf(
		'Tháng %02d năm %d',
		$month,
		$year
	);

	$data = [
		'lunar_day' => (string) $lunar['day'],
		'lunar_sub' => $lunar_sub,
		'menh'      => $nap_am['name'],
		'gio'       => $hoang_dao,
		'quote'     => PGDS_Lunar_Quotes::today(),
		'greg_day'  => (string) $day,
		'greg_sub'  => $greg_sub,
	];

	wp_cache_set( $date_key, $data, 'pgds_lunar' );

	return $data;
}
