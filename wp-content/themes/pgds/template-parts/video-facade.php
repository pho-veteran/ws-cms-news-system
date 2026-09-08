<?php
/**
 * Privacy-preserving YouTube facade. The iframe is created only after interaction.
 *
 * @param array $args Facade data.
 * @package pgds
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$video_id  = isset( $args['video_id'] ) ? pgds_validate_youtube_id( $args['video_id'] ) : '';
$poster    = isset( $args['poster'] ) ? (string) $args['poster'] : '';
$poster_id = isset( $args['poster_id'] ) ? absint( $args['poster_id'] ) : 0;
$duration  = isset( $args['dur'] ) ? pgds_format_duration( (int) $args['dur'] ) : '';
$title     = isset( $args['title'] ) ? (string) $args['title'] : '';
$caption   = isset( $args['caption'] ) ? (string) $args['caption'] : '';

if ( ! empty( $args['unavailable'] ) || ! $video_id ) {
	printf(
		'<p class="pgds-video__unavailable" role="status">%s</p>',
		esc_html__( 'Video không còn khả dụng.', 'pgds' )
	);
	return;
}

if ( ! $poster_id && '' === $poster ) {
	$fallback_id = isset( $args['fallback_post'] ) ? absint( $args['fallback_post'] ) : get_the_ID();
	if ( $fallback_id && has_post_thumbnail( $fallback_id ) ) {
		$poster_id = (int) get_post_thumbnail_id( $fallback_id );
	}
}
?>
<figure
	class="pgds-video video-player"
	data-pgds="youtube-facade"
	data-video-id="<?php echo esc_attr( $video_id ); ?>"
	data-video-title="<?php echo esc_attr( $title ); ?>"
>
	<?php if ( $poster_id ) : ?>
		<?php
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
			width="1280" height="720" loading="eager" decoding="async" fetchpriority="high"
			alt="<?php echo esc_attr( $title ); ?>">
	<?php else : ?>
		<div class="pgds-video__poster pgds-video__placeholder" aria-hidden="true">
			<svg viewBox="0 0 100 100" focusable="false"><path d="M50 85C25 72 20 50 20 50c14 9 22 4 22 4s4 18 8 22c8-4 12-22 12-22s8 5 22-4c0 0-4 26-34 35Z"></path></svg>
		</div>
	<?php endif; ?>

	<img class="pgds-video__watermark watermark" src="<?php echo esc_url( PGDS_LOGO_URI ); ?>"
		width="2048" height="357" alt="" aria-hidden="true" decoding="async">

	<button class="pgds-video__play play-btn pgds-play pgds-play--lg" type="button"
		aria-label="<?php echo esc_attr( sprintf( __( 'Phát video: %s', 'pgds' ), $title ) ); ?>">
		<?php pgds_play_svg(); ?>
	</button>

	<?php if ( $duration ) : ?>
		<span class="pgds-video__dur duration-badge"><?php echo esc_html( $duration ); ?></span>
	<?php endif; ?>

	<?php if ( $caption ) : ?>
		<figcaption class="pgds-video__caption"><?php echo esc_html( $caption ); ?></figcaption>
	<?php endif; ?>
</figure>
