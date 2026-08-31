<?php
// Diagnostic: dem so bai moi block trang chu tra ve.
$b = pgds_home_blocks();
$keys = array( 'lead', 'secondary', 'photo', 'media_feature', 'media_thumbs', 'media_bullets', 'phatsu_cards', 'phatsu_list', 'mixed_list', 'vn_list', 'popular', 'teaching' );
foreach ( $keys as $k ) {
	$v = $b[ $k ];
	$n = is_array( $v ) ? count( $v ) : ( $v ? 1 : 0 );
	WP_CLI::log( $k . '=' . $n );
}
foreach ( $b['columns'] as $c ) {
	$d = $c['data'];
	WP_CLI::log( 'col:' . $c['slug'] . ' feat=' . ( $d['feat'] ? 1 : 0 ) . ' mini=' . count( $d['mini'] ) );
}
