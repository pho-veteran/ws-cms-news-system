<?php
/**
 * YouTube facade - doesn't load the iframe until interacted with (proposal §6.2).
 * Poster is stored locally (disk instance), not hotlinked.
 *
 * @param array $args {
 *   video_id:    string,
 *   poster:      string (local poster image URL),
 *   dur:         int    (seconds),
 *   title:       string,
 *   caption:     string,
 *   unavailable: bool   (private / removed / age-restricted -> hide the facade)
 * }
 * @package pgds
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$vid = $args['video_id'] ?? '';
if ( '' === $vid ) {
	return;
}
$poster  = $args['poster'] ?? '';
$dur     = isset( $args['dur'] ) ? (int) $args['dur'] : 0;
$title   = $args['title'] ?? '';
$caption = $args['caption'] ?? '';
$dur_str = pgds_format_duration( $dur );

/*
 * Proposal §6.3: a private, removed, or age-restricted video must hide the facade and
 * show explanatory text instead. Rendering the play button anyway would promise a
 * video that cannot play. inc/seo-schema.php already suppresses VideoObject on the
 * same flag; this is the display half of that rule.
 */
if ( ! empty( $args['unavailable'] ) ) {
	printf(
		'<p class="pgds-video__unavailable">%s</p>',
		esc_html__( 'Video không còn khả dụng', 'pgds' )
	);
	return;
}
?>
<figure class="pgds-video" data-pgds="youtube-facade" data-video-id="<?php echo esc_attr( $vid ); ?>">
	<?php if ( $poster ) : ?>
		<img class="pgds-video__poster" src="<?php echo esc_url( $poster ); ?>"
			width="1280" height="720" loading="lazy" decoding="async"
			alt="<?php echo esc_attr( $title ); ?>">
	<?php else : ?>
		<div class="pgds-video__poster pgds-art pgds-ratio-video" aria-hidden="true"></div>
	<?php endif; ?>
	<button class="pgds-video__play" type="button"
		aria-label="<?php echo esc_attr( sprintf( __( 'Phát video: %s', 'pgds' ), $title ) ); ?>">
		<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M8 5v14l11-7z"/></svg>
	</button>
	<?php if ( $dur_str ) : ?>
		<span class="pgds-video__dur"><?php echo esc_html( $dur_str ); ?></span>
	<?php endif; ?>
	<?php if ( $caption ) : ?>
		<figcaption class="pgds-video__caption"><?php echo esc_html( $caption ); ?></figcaption>
	<?php endif; ?>
</figure>
