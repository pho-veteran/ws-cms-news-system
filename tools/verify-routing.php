<?php
/**
 * Verify canonical navigation and single-post detail routing.
 *
 * Run inside WordPress:
 * wp eval-file /var/www/html/.pgds-tools/verify-routing.php
 */

if ( ! defined( 'ABSPATH' ) || ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	exit;
}

$failures = array();
$posts    = array();

$assert = static function ( $condition, $message ) use ( &$failures ) {
	if ( ! $condition ) {
		$failures[] = $message;
	}
};

$canonical = pgds_seed_categories();
if ( is_wp_error( $canonical ) ) {
	WP_CLI::error( 'Canonical category reconciliation failed: ' . $canonical->get_error_message() );
}

$create_post = static function ( $title, $category_slugs, $primary_slug = '', $video_id = '', $unavailable = false ) use ( &$posts, $canonical ) {
	$category_ids = array();
	foreach ( (array) $category_slugs as $slug ) {
		if ( isset( $canonical[ $slug ] ) ) {
			$category_ids[] = (int) $canonical[ $slug ];
		}
	}

	$post_id = wp_insert_post(
		array(
			'post_type'     => 'post',
			'post_status'   => 'publish',
			'post_title'    => $title,
			'post_content'  => 'Routing verification content.',
			'post_category' => $category_ids,
		),
		true
	);
	if ( is_wp_error( $post_id ) ) {
		return $post_id;
	}

	$posts[] = (int) $post_id;
	if ( $primary_slug && isset( $canonical[ $primary_slug ] ) ) {
		update_post_meta( $post_id, '_pgds_primary_cat', (int) $canonical[ $primary_slug ] );
	}
	if ( '' !== $video_id ) {
		update_post_meta( $post_id, '_pgds_youtube_id', $video_id );
	}
	if ( $unavailable ) {
		update_post_meta( $post_id, '_pgds_video_unavailable', '1' );
	}

	return (int) $post_id;
};

try {
	$cases = array(
		array( 'Article without primary', array( 'tin-phat-su' ), '', '', false, 'article' ),
		array( 'Article with video metadata', array( 'tin-phat-su' ), 'tin-phat-su', 'dQw4w9WgXcQ', false, 'article' ),
		array( 'E-magazine detail', array( 'emagazine' ), 'emagazine', '', false, 'emagazine' ),
		array( 'Vietnam Buddhism detail', array( 'vietnam-buddhism' ), 'vietnam-buddhism', '', false, 'vietnam-buddhism' ),
		array( 'Vietnam Buddhism membership only', array( 'vietnam-buddhism' ), '', '', false, 'article' ),
		array( 'Valid video detail', array( 'video' ), 'video', 'dQw4w9WgXcQ', false, 'video' ),
		array( 'Invalid video ID', array( 'video' ), 'video', 'invalid', false, 'article' ),
		array( 'Unavailable video', array( 'video' ), 'video', 'dQw4w9WgXcQ', true, 'article' ),
	);

	$fixture_ids = array();

	foreach ( $cases as $case ) {
		list( $title, $categories, $primary, $video_id, $unavailable, $expected ) = $case;
		$post_id = $create_post( $title, $categories, $primary, $video_id, $unavailable );
		$assert( ! is_wp_error( $post_id ), sprintf( 'Could not create fixture "%s".', $title ) );
		if ( ! is_wp_error( $post_id ) ) {
			$fixture_ids[ $title ] = $post_id;
			$assert( $expected === pgds_detail_layout( $post_id ), sprintf( 'Fixture "%s" did not resolve to "%s".', $title, $expected ) );
		}
	}

	$unassigned = $create_post( 'Unassigned primary', array( 'tin-phat-su' ) );
	if ( ! is_wp_error( $unassigned ) ) {
		update_post_meta( $unassigned, '_pgds_primary_cat', (int) $canonical['video'] );
		update_post_meta( $unassigned, '_pgds_youtube_id', 'dQw4w9WgXcQ' );
		$assert( 'article' === pgds_detail_layout( $unassigned ), 'An unassigned primary category activated Video.' );
	}

	$stale = $create_post( 'Stale primary', array( 'tin-phat-su' ) );
	if ( ! is_wp_error( $stale ) ) {
		update_post_meta( $stale, '_pgds_primary_cat', 2147483647 );
		$assert( 'article' === pgds_detail_layout( $stale ), 'A stale primary category activated a specialized layout.' );
	}

	$child = get_term( (int) $canonical['am-thuc-chay'], 'category' );
	$root  = get_term( (int) $canonical['song-an-lanh'], 'category' );
	$state = pgds_category_nav_state( 'am-thuc-chay', $child );
	$assert( $state['current'] && ! $state['ancestor'], 'A child category was not marked exact-current.' );
	$state = pgds_category_nav_state( 'song-an-lanh', $child );
	$assert( ! $state['current'] && $state['ancestor'], 'A child category did not mark its canonical root as ancestor.' );
	$state = pgds_category_nav_state( 'song-an-lanh', $root );
	$assert( $state['current'] && ! $state['ancestor'], 'A root category was not marked exact-current.' );
	$state = pgds_category_nav_state( 'media', $child );
	$assert( ! $state['current'] && ! $state['ancestor'], 'An unrelated root received active navigation state.' );

	ob_start();
	pgds_primary_navigation();
	$navigation = (string) ob_get_clean();
	$assert( 7 === preg_match_all( '/<li class="pgds-navitem(?:\s[^\"]*)?">/', $navigation ), 'Primary navigation does not contain Home plus six category roots.' );
	$assert( 2 === substr_count( $navigation, 'data-pgds="submenu-toggle"' ), 'Primary navigation does not contain exactly two dropdown disclosures.' );
	$assert( 2 === substr_count( $navigation, 'class="pgds-dropdown dropdown"' ), 'Primary navigation does not contain exactly two dropdown lists.' );
	$assert( false !== strpos( $navigation, '>Trang chủ</a>' ), 'Primary navigation is missing the Home item.' );
	$assert( false === strpos( $navigation, 'aria-current="page"' ), 'A non-category CLI request received aria-current.' );

	$locale_before = get_locale();
	$english_id    = $fixture_ids['Vietnam Buddhism detail'] ?? 0;
	$article_id    = $fixture_ids['Article without primary'] ?? 0;
	$membership_id = $fixture_ids['Vietnam Buddhism membership only'] ?? 0;

	global $wp_query;
	$original_query = $wp_query;
	$wp_query       = new WP_Query(
		array(
			'p'         => $english_id,
			'post_type' => 'post',
		)
	);
	$assert( pgds_is_english_reader_request(), 'A valid Vietnam Buddhism primary category did not activate English presentation.' );
	$assert( 'lang="en"' === apply_filters( 'language_attributes', 'lang="vi"' ), 'English detail did not switch the document language attribute.' );
	$assert( 'Home' === __( 'Trang chủ', 'pgds' ), 'Theme-owned detail UI did not translate to English.' );
	$assert( 'Leave a Reply' === __( 'Leave a Reply' ), 'Core-owned comment UI did not remain in source English.' );
	$assert( 'Vietnam Buddhism' === pgds_category_display_label( 'vietnam-buddhism', 'Phật giáo Việt Nam' ), 'Canonical category presentation label did not translate to English.' );
	$assert( $locale_before === get_locale(), 'English detail presentation changed the WordPress locale.' );

	$wp_query = new WP_Query(
		array(
			'cat' => (int) $canonical['vietnam-buddhism'],
		)
	);
	$wp_query->is_category = true;
	$wp_query->is_archive  = true;
	$assert( pgds_is_english_reader_request(), 'The Vietnam Buddhism category archive did not activate English presentation.' );
	$assert( 'lang="en"' === apply_filters( 'language_attributes', 'lang="vi"' ), 'English category archive did not switch the document language attribute.' );
	$assert( 'Subsections' === __( 'Chuyên mục con', 'pgds' ), 'Category archive UI did not translate to English.' );
	$assert( 'Vietnam Buddhism' === pgds_category_display_label( 'vietnam-buddhism', 'Phật giáo Việt Nam' ), 'Category archive display label did not translate to English.' );
	$assert( $locale_before === get_locale(), 'English category presentation changed the WordPress locale.' );

	$wp_query = new WP_Query(
		array(
			'cat' => (int) $canonical['tin-phat-su'],
		)
	);
	$wp_query->is_category = true;
	$wp_query->is_archive  = true;
	$assert( ! pgds_is_english_reader_request(), 'An unrelated category archive activated English presentation.' );
	$assert( 'Chuyên mục con' === __( 'Chuyên mục con', 'pgds' ), 'English archive labels leaked into another category.' );
	$assert( 'lang="vi"' === apply_filters( 'language_attributes', 'lang="vi"' ), 'English document language leaked into another category.' );

	$wp_query = new WP_Query(
		array(
			'p'         => $membership_id,
			'post_type' => 'post',
		)
	);
	$assert( ! pgds_is_english_reader_request(), 'Vietnam Buddhism membership without a validated primary category activated English presentation.' );

	$wp_query = new WP_Query(
		array(
			'p'         => $article_id,
			'post_type' => 'post',
		)
	);
	$assert( ! pgds_is_english_reader_request(), 'A standard Article detail activated English presentation.' );
	$assert( 'Trang chủ' === __( 'Trang chủ', 'pgds' ), 'English theme labels leaked into a standard Article detail.' );
	$assert( 'lang="vi"' === apply_filters( 'language_attributes', 'lang="vi"' ), 'English document language leaked into a standard Article detail.' );

	$_POST['comment_post_ID'] = (string) $english_id;
	$wp_query                 = $original_query;
	$assert( pgds_is_english_reader_request(), 'Native comment submission context did not activate English presentation.' );
	unset( $_POST['comment_post_ID'] );
	$wp_query = $original_query;

	$positions = array();
	foreach ( array_keys( pgds_category_tree() ) as $slug ) {
		$label              = pgds_category_tree()[ $slug ]['label'];
		$positions[ $slug ] = strpos( $navigation, '>' . esc_html( $label ) . '</a>' );
		$assert( false !== $positions[ $slug ], sprintf( 'Navigation is missing root "%s".', $slug ) );
	}
	$previous = -1;
	foreach ( array_keys( pgds_category_tree() ) as $slug ) {
		if ( false !== $positions[ $slug ] ) {
			$assert( $positions[ $slug ] > $previous, 'Primary navigation root order is not canonical.' );
			$previous = $positions[ $slug ];
		}
	}

	$assert( '' === pgds_validate_youtube_id( 'short' ), 'The YouTube validator accepted a short ID.' );
	$assert( '' === pgds_validate_youtube_id( 'invalid/id!' ), 'The YouTube validator accepted invalid characters.' );
	$assert( 'dQw4w9WgXcQ' === pgds_validate_youtube_id( ' dQw4w9WgXcQ ' ), 'The YouTube validator rejected a canonical ID.' );
} finally {
	unset( $_POST['comment_post_ID'] );
	if ( isset( $original_query ) ) {
		$wp_query = $original_query;
	}
	foreach ( $posts as $post_id ) {
		wp_delete_post( $post_id, true );
	}
}

if ( $failures ) {
	foreach ( $failures as $failure ) {
		WP_CLI::warning( $failure );
	}
	WP_CLI::error( sprintf( 'Routing verification failed with %d error(s).', count( $failures ) ) );
}

WP_CLI::success( 'Canonical navigation and detail routing verification passed.' );
