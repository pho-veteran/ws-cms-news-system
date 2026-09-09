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
 *
 * Pool size matches the longest month (31). Selection is deterministic
 * per calendar day but offset by month/year so the order is not fixed:
 * every day in a month gets a distinct quote.
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
		'"Im lặng là câu trả lời tốt nhất cho những ai không hiểu giá trị của lời nói chân thật." — Lời Phật dạy',
		'"Đừng bám víu vào quá khứ, đừng mơ tưởng đến tương lai, hãy an trú trong hiện tại." — Kinh Bốn Mươi Hai Chương',
		'"Người chiến thắng chính mình còn vinh quang hơn chiến thắng cả nghìn trận chiến." — Kinh Pháp Cú',
		'"Tâm như đất, chứa đựng mọi thứ; không chọn lọc, không từ chối." — Lời Phật dạy',
		'"Một ngọn đèn có thể thắp nghìn ngọn đèn khác mà không hề tắt đi." — Kinh Hoa Nghiêm',
		'"Khi bạn nhận ra mình đã phạm sai lầm, hãy lập tức sửa đổi — đó là bước đầu của sự thức tỉnh." — Lời Phật dạy',
		'"Thân người khó được, Phật pháp khó nghe, thiện tri thức khó gặp." — Kinh Niết Bàn',
		'"Nước trong thì cá không ở, tâm chấp thì trí không khai." — Thiền ngữ',
		'"Đi chậm không sao, chỉ sợ dừng lại." — Lời Phật dạy',
		'"Từ bi không phải là cảm xúc thương hại, mà là trí tuệ thấy rõ sự liên kết giữa muôn loài." — Thiền sư Thích Nhất Hạnh',
		'"Mỗi hơi thở là một cơ hội để bắt đầu lại." — Thiền ngữ',
		'"Không ai làm tổn thương bạn nhiều hơn chính những suy nghĩ thiếu chánh niệm của bạn." — Đức Phật',
		'"Cây мощн sinh ra từ hạt giống nhỏ; hành trình vạn dặm bắt đầu từ một bước chân." — Lời Phật dạy',
		'"Lòng biết ơn là ký ức của trái tim." — Thiền sư Thích Nhất Hạnh',
		'"Đừng tìm chân lý ở nơi xa — hãy quay về nhìn tâm mình." — Thiền ngữ',
		'"Sân hận như nắm than nóng, bạn định ném người khác nhưng chính mình bị bỏng." — Đức Phật',
		'"Một ngày không cười là một ngày lãng phí." — Lời Phật dạy',
		'"Tất cả những gì chúng ta là kết quả của những gì chúng ta nghĩ." — Kinh Pháp Cú',
		'"Hạnh phúc không có nghĩa là nhiều hơn, mà là cần ít hơn." — Thiền sư Thích Nhất Hạnh',
		'"Khi một cánh cửa đóng lại, cánh cửa khác sẽ mở ra — nhưng ta thường nhìn mãi cánh cửa đã đóng." — Lời Phật dạy',
		'"An trú trong hơi thở, bạn đã về nhà." — Thiền sư Thích Nhất Hạnh',
	];

	/**
	 * Get the quote for today.
	 *
	 * Deterministic and cache-friendly: same site date → same quote.
	 * Fisher-Yates seeded by year-month so each month has a different
	 * order; day-of-month indexes that order → 31 distinct quotes per month.
	 *
	 * @return string
	 */
	public static function today(): string {
		$now          = current_datetime();
		$day_of_month = (int) $now->format( 'j' );
		$month_key    = $now->format( 'Y-m' );

		$pool = self::shuffled_for_month( $month_key );

		return $pool[ $day_of_month - 1 ];
	}

	/**
	 * Seeded Fisher-Yates shuffle of the quote pool for a given Y-m key.
	 *
	 * Uses a local LCG so the global RNG is not polluted. Same month key
	 * always yields the same order (stable under object cache).
	 *
	 * @param string $month_key Site-local year-month, e.g. "2026-09".
	 * @return string[]
	 */
	private static function shuffled_for_month( string $month_key ): array {
		$pool = self::$QUOTES;
		$n    = count( $pool );
		$seed = crc32( $month_key );

		for ( $i = $n - 1; $i > 0; $i-- ) {
			// LCG (Numerical Recipes constants), masked to 31-bit.
			$seed = (int) ( ( $seed * 1103515245 + 12345 ) & 0x7fffffff );
			$j    = $seed % ( $i + 1 );
			$tmp  = $pool[ $i ];
			$pool[ $i ] = $pool[ $j ];
			$pool[ $j ] = $tmp;
		}

		return $pool;
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
