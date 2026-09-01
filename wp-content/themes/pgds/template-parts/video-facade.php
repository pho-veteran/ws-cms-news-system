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
 * Fall back to the post's featured image when no synced poster exists.
 *
 * The poster comes from `wp pgds yt-sync`, which needs network access and (for duration)
 * an API key. Until it has run — which includes the entire §9 import window, and any post
 * whose sync failed — `_pgds_youtube_poster` is empty and the facade rendered an EMPTY
 * placeholder div: on this install all 9 video posts had a featured image and every one
 * showed a blank cream box as the largest element on the article, with the caption
 * "Nguồn: …" floating over nothing. It also cost the page its LCP image entirely.
 *
 * The featured image is the right fallback rather than hotlinking i.ytimg.com: §6.2
 * requires the poster be served locally, and the editorial thumbnail is already the image
 * chosen to represent this story. Sized 'pgds-lead' to match the 16:9 facade box.
 */
$poster_id = isset( $args['poster_id'] ) ? (int) $args['poster_id'] : 0;
if ( ! $poster_id && '' === $poster ) {
	$fallback_id = $args['fallback_post'] ?? get_the_ID();
	if ( $fallback_id && has_post_thumbnail( $fallback_id ) ) {
		$poster_id = (int) get_post_thumbnail_id( $fallback_id );
	}
}

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
	<?php if ( $poster_id ) : ?>
		<?php
		/*
		 * Rendered through wp_get_attachment_image() so the poster gets the attachment's
		 * REAL width/height plus srcset. The previous hardcoded width="1280" height="720"
		 * did not match any registered size (pgds-lead is 960x600), so the declared aspect
		 * ratio was wrong and the browser reserved the wrong box.
		 *
		 * Not lazy-loaded: on a video article this is the LCP element, above the fold.
		 */
		echo wp_get_attachment_image(
			$poster_id,
			'pgds-lead',
			false,
			array(
				'class'         => 'pgds-video__poster',
				'alt'           => $title,
				'decoding'      => 'async',
				'loading'       => 'eager',
				'fetchpriority' => 'high',
			)
		);
		?>
	<?php elseif ( $poster ) : ?>
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
