<?php
/**
 * Video detail layout.
 *
 * @package pgds
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$post_id        = get_the_ID();
$video_id       = pgds_video_id( $post_id );
$video_term     = pgds_category_term( 'video' );
$primary_term   = pgds_primary_cat( $post_id );
$display_author = pgds_display_author( get_post() );
$source         = (string) get_post_meta( $post_id, '_pgds_source', true );
$sapo           = pgds_has_editorial_sapo( $post_id ) ? pgds_sapo( $post_id ) : '';

/**
 * Load only posts that still satisfy the canonical Video routing contract.
 *
 * @param int[]        $exclude Post IDs to omit.
 * @param int          $limit   Maximum results.
 * @param string|array $orderby WordPress orderby value.
 * @return WP_Post[]
 */
$load_videos = static function ( array $exclude, $limit, $orderby ) use ( $video_term ) {
	if ( ! $video_term instanceof WP_Term ) {
		return array();
	}

	$candidates = get_posts(
		array(
			'post_type'           => 'post',
			'post_status'         => 'publish',
			'posts_per_page'      => max( 20, $limit * 4 ),
			'post__not_in'        => array_map( 'absint', $exclude ),
			'category__in'        => array( (int) $video_term->term_id ),
			'meta_key'            => '_pgds_primary_cat',
			'meta_value'          => (int) $video_term->term_id,
			'ignore_sticky_posts' => true,
			'no_found_rows'       => true,
			'orderby'             => $orderby,
			'order'               => 'DESC',
		)
	);

	$candidates = array_values(
		array_filter(
			$candidates,
			static function ( $candidate ) {
				return 'video' === pgds_detail_layout( $candidate );
			}
		)
	);

	return array_slice( $candidates, 0, $limit );
};

$related_videos = $load_videos( array( $post_id ), 4, 'date' );
$hot_exclusions = array_merge( array( $post_id ), wp_list_pluck( $related_videos, 'ID' ) );
$hot_videos     = $load_videos(
	$hot_exclusions,
	5,
	array(
		'comment_count' => 'DESC',
		'date'          => 'DESC',
	)
);

$breadcrumbs = array(
	array(
		'label' => __( 'Trang chủ', 'pgds' ),
		'url'   => home_url( '/' ),
	),
);
if ( $primary_term instanceof WP_Term ) {
	if ( $primary_term->parent ) {
		$parent     = get_term( $primary_term->parent, 'category' );
		$parent_url = $parent instanceof WP_Term ? get_term_link( $parent ) : new WP_Error();
		if ( $parent instanceof WP_Term && ! is_wp_error( $parent_url ) ) {
			$breadcrumbs[] = array(
				'label' => $parent->name,
				'url'   => $parent_url,
			);
		}
	}
	$primary_url = get_term_link( $primary_term );
	if ( ! is_wp_error( $primary_url ) ) {
		$breadcrumbs[] = array(
			'label' => $primary_term->name,
			'url'   => $primary_url,
		);
	}
}
$breadcrumbs[] = array( 'label' => get_the_title() );
?>

<div class="pgds-video-crumb-bar crumb-bar">
	<div class="pgds-wrap wrap">
		<?php pgds_breadcrumbs( $breadcrumbs ); ?>
	</div>
</div>

<main id="pgds-main" class="pgds-video-detail" role="main">
	<div class="pgds-wrap">
		<div class="pgds-video-layout content-grid">
			<article <?php post_class( 'pgds-video-detail__main' ); ?>>
				<?php
				get_template_part(
					'template-parts/video-facade',
					null,
					array(
						'video_id'      => $video_id,
						'poster'        => (string) get_post_meta( $post_id, '_pgds_youtube_poster', true ),
						'poster_id'     => (int) get_post_meta( $post_id, '_pgds_youtube_poster_id', true ),
						'dur'           => (int) get_post_meta( $post_id, '_pgds_youtube_dur', true ),
						'title'         => (string) get_post_meta( $post_id, '_pgds_youtube_title', true ) ?: get_the_title(),
						'caption'       => (string) get_post_meta( $post_id, '_pgds_image_caption', true ),
						'fallback_post' => $post_id,
						'unavailable'   => false,
					)
				);
				?>

				<div class="video-info">
					<h1 class="video-title"><?php the_title(); ?></h1>
					<time class="video-time" datetime="<?php echo esc_attr( get_the_date( DATE_W3C, $post_id ) ); ?>">
						<?php echo esc_html( date_i18n( 'Y-m-d H:i:s', get_post_timestamp( $post_id ) ) ); ?>
					</time>

					<?php if ( $sapo ) : ?>
						<p class="video-sapo"><?php echo esc_html( $sapo ); ?></p>
					<?php endif; ?>

					<?php if ( $display_author || $source ) : ?>
						<p class="video-author">
							<?php if ( $display_author ) : ?>
								<span><?php echo esc_html( $display_author ); ?></span>
							<?php endif; ?>
							<?php if ( $source ) : ?>
								<span class="video-source"><?php printf( esc_html__( 'Nguồn: %s', 'pgds' ), esc_html( $source ) ); ?></span>
							<?php endif; ?>
						</p>
					<?php endif; ?>

					<?php comments_template(); ?>
				</div>

				<section class="video-hot" aria-labelledby="pgds-video-hot-title">
					<h2 class="video-hot-head" id="pgds-video-hot-title"><?php esc_html_e( 'VIDEO HOT', 'pgds' ); ?></h2>
					<?php if ( $hot_videos ) : ?>
						<?php foreach ( $hot_videos as $hot_video ) : ?>
							<?php
							$hot_id       = (int) $hot_video->ID;
							$hot_duration = pgds_format_duration( (int) get_post_meta( $hot_id, '_pgds_youtube_dur', true ) );
							$hot_sapo     = pgds_sapo( $hot_id );
							?>
							<article class="vh-item">
								<a class="thumb" href="<?php echo esc_url( get_permalink( $hot_id ) ); ?>" aria-label="<?php echo esc_attr( get_the_title( $hot_id ) ); ?>">
									<?php pgds_art( $hot_id, 'pgds-card', 'pgds-ratio-card' ); ?>
									<svg class="play-mini" viewBox="0 0 40 40" aria-hidden="true" focusable="false"><circle cx="20" cy="20" r="19" fill="#fff" opacity=".92"></circle><path d="M17 13l13 7-13 7z" fill="#452A21"></path></svg>
									<?php if ( $hot_duration ) : ?>
										<span class="dur"><?php echo esc_html( $hot_duration ); ?></span>
									<?php endif; ?>
								</a>
								<div class="vh-item__content">
									<h3><a href="<?php echo esc_url( get_permalink( $hot_id ) ); ?>"><?php echo esc_html( get_the_title( $hot_id ) ); ?></a></h3>
									<?php if ( $hot_sapo ) : ?>
										<p class="sapo"><?php echo esc_html( $hot_sapo ); ?></p>
									<?php endif; ?>
								</div>
							</article>
						<?php endforeach; ?>
					<?php else : ?>
						<p class="pgds-video-empty"><?php esc_html_e( 'Chưa có video nổi bật khác.', 'pgds' ); ?></p>
					<?php endif; ?>
				</section>
			</article>

			<aside class="pgds-video-sidebar" aria-labelledby="pgds-related-video-title">
				<section class="side-video-panel">
					<h2 class="panel-title" id="pgds-related-video-title"><?php esc_html_e( 'VIDEO CÙNG CHUYÊN MỤC', 'pgds' ); ?></h2>
					<?php if ( $related_videos ) : ?>
						<?php foreach ( $related_videos as $related_video ) : ?>
							<?php
							$related_id       = (int) $related_video->ID;
							$related_duration = pgds_format_duration( (int) get_post_meta( $related_id, '_pgds_youtube_dur', true ) );
							?>
							<article class="svp-item">
								<a class="thumb" href="<?php echo esc_url( get_permalink( $related_id ) ); ?>" aria-label="<?php echo esc_attr( get_the_title( $related_id ) ); ?>">
									<?php pgds_art( $related_id, 'pgds-thumb', 'pgds-ratio-card' ); ?>
									<svg class="play-mini" viewBox="0 0 40 40" aria-hidden="true" focusable="false"><circle cx="20" cy="20" r="19" fill="#fff" opacity=".92"></circle><path d="M17 13l13 7-13 7z" fill="#452A21"></path></svg>
									<?php if ( $related_duration ) : ?>
										<span class="dur"><?php echo esc_html( $related_duration ); ?></span>
									<?php endif; ?>
								</a>
								<h3><a href="<?php echo esc_url( get_permalink( $related_id ) ); ?>"><?php echo esc_html( get_the_title( $related_id ) ); ?></a></h3>
							</article>
						<?php endforeach; ?>
					<?php else : ?>
						<p class="pgds-video-empty pgds-video-empty--inverse"><?php esc_html_e( 'Chưa có video cùng chuyên mục.', 'pgds' ); ?></p>
					<?php endif; ?>
				</section>
			</aside>
		</div>
	</div>
</main>
