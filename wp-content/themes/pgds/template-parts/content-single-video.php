<?php
/**
 * Video detail entry point.
 *
 * @package pgds
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$post_id = get_the_ID();
$video   = pgds_video_id( $post_id );
$term    = pgds_primary_cat( $post_id );

// 1. Query Sidebar Related Videos (up to 4, excluding current post)
$rel_query_args = array(
	'post_type'           => 'post',
	'post_status'         => 'publish',
	'posts_per_page'      => 4,
	'post__not_in'        => array( $post_id ),
	'ignore_sticky_posts' => true,
	'no_found_rows'       => true,
	'orderby'             => 'date',
	'order'               => 'DESC',
);
if ( $term instanceof WP_Term ) {
	$rel_query_args['cat'] = $term->term_id;
}
$rel_query      = new WP_Query( $rel_query_args );
$related_videos = $rel_query->posts;

if ( count( $related_videos ) < 4 ) {
	$used_rel_ids = array_merge( array( $post_id ), wp_list_pluck( $related_videos, 'ID' ) );
	$topup_rel    = new WP_Query(
		array(
			'post_type'           => 'post',
			'post_status'         => 'publish',
			'posts_per_page'      => 4 - count( $related_videos ),
			'post__not_in'        => $used_rel_ids,
			'category_name'       => 'video',
			'ignore_sticky_posts' => true,
			'no_found_rows'       => true,
			'orderby'             => 'date',
			'order'               => 'DESC',
		)
	);
	$related_videos = array_merge( $related_videos, $topup_rel->posts );
}

// 2. Query Video Hot (up to 5, excluding current post and sidebar related videos)
$used_for_hot = array_merge( array( $post_id ), wp_list_pluck( $related_videos, 'ID' ) );
$hot_query    = new WP_Query(
	array(
		'post_type'           => 'post',
		'post_status'         => 'publish',
		'posts_per_page'      => 5,
		'post__not_in'        => $used_for_hot,
		'category_name'       => 'video',
		'ignore_sticky_posts' => true,
		'no_found_rows'       => true,
		'orderby'             => array(
			'comment_count' => 'DESC',
			'date'          => 'DESC',
		),
	)
);
$hot_videos = $hot_query->posts;

if ( count( $hot_videos ) < 5 ) {
	$used_for_hot_topup = array_merge( $used_for_hot, wp_list_pluck( $hot_videos, 'ID' ) );
	$topup_hot          = new WP_Query(
		array(
			'post_type'           => 'post',
			'post_status'         => 'publish',
			'posts_per_page'      => 5 - count( $hot_videos ),
			'post__not_in'        => $used_for_hot_topup,
			'ignore_sticky_posts' => true,
			'no_found_rows'       => true,
			'orderby'             => 'date',
			'order'               => 'DESC',
		)
	);
	$hot_videos = array_merge( $hot_videos, $topup_hot->posts );
}
?>

<?php
$breadcrumbs = array(
	array(
		'label' => __( 'Trang chủ', 'pgds' ),
		'url'   => home_url( '/' ),
	),
);
if ( $term instanceof WP_Term ) {
	if ( $term->parent ) {
		$parent = get_term( $term->parent, 'category' );
		if ( $parent instanceof WP_Term && ! is_wp_error( get_term_link( $parent ) ) ) {
			$breadcrumbs[] = array(
				'label' => $parent->name,
				'url'   => get_term_link( $parent ),
			);
		}
	}
	$term_url = get_term_link( $term );
	if ( ! is_wp_error( $term_url ) ) {
		$breadcrumbs[] = array(
			'label' => $term->name,
			'url'   => $term_url,
		);
	}
}
$breadcrumbs[] = array( 'label' => get_the_title() );
pgds_breadcrumbs( $breadcrumbs );
?>

<main id="pgds-main" class="pgds-wrap" role="main">

	<div class="content-grid pgds-content-grid">
		<article <?php post_class( 'pgds-article pgds-article--video' ); ?>>
			<?php
			get_template_part(
				'template-parts/video-facade',
				null,
				array(
					'video_id'      => $video,
					'poster'        => (string) get_post_meta( $post_id, '_pgds_youtube_poster', true ),
					'poster_id'     => (int) get_post_meta( $post_id, '_pgds_youtube_poster_id', true ),
					'dur'           => (int) get_post_meta( $post_id, '_pgds_youtube_dur', true ),
					'title'         => (string) get_post_meta( $post_id, '_pgds_youtube_title', true ),
					'caption'       => (string) get_post_meta( $post_id, '_pgds_image_caption', true ),
					'fallback_post' => $post_id,
					'unavailable'   => ( '1' === (string) get_post_meta( $post_id, '_pgds_video_unavailable', true ) ),
				)
			);
			?>

			<div class="video-info">
				<h1 class="video-title"><?php the_title(); ?></h1>
				<div class="video-time">
					<time datetime="<?php echo esc_attr( get_the_date( DATE_W3C, $post_id ) ); ?>"><?php echo esc_html( date_i18n( 'Y-m-d H:i:s', get_post_timestamp( $post_id ) ) ); ?></time>
				</div>
				<?php $sapo = pgds_sapo( $post_id ); ?>
				<?php if ( $sapo ) : ?>
					<p class="video-sapo"><?php echo esc_html( $sapo ); ?></p>
				<?php endif; ?>
				<?php
				$author = pgds_display_author( $post_id );
				if ( ! $author ) {
					$post_obj = get_post( $post_id );
					if ( $post_obj && ! empty( $post_obj->post_author ) ) {
						$author = get_the_author_meta( 'display_name', $post_obj->post_author );
					}
				}
				?>
				<?php if ( $author ) : ?>
					<div class="video-author"><?php echo esc_html( $author ); ?></div>
				<?php endif; ?>
			</div>

			<?php if ( trim( get_the_content() ) !== '' && ! pgds_has_editorial_sapo( $post_id ) ) : ?>
				<div class="pgds-article__body">
					<?php the_content(); ?>
				</div>
			<?php endif; ?>

			<?php comments_template(); ?>

			<?php if ( ! empty( $hot_videos ) ) : ?>
				<section class="video-hot">
					<h2 class="video-hot-head"><?php esc_html_e( 'VIDEO HOT', 'pgds' ); ?></h2>
					<?php foreach ( $hot_videos as $hot_post ) : ?>
						<?php
						$hot_id    = $hot_post->ID;
						$hot_dur   = (int) get_post_meta( $hot_id, '_pgds_youtube_dur', true );
						$hot_dur_s = pgds_format_duration( $hot_dur );
						$hot_sapo  = pgds_sapo( $hot_id );
						?>
						<div class="vh-item">
							<a href="<?php echo esc_url( get_permalink( $hot_id ) ); ?>" class="thumb art">
								<?php pgds_art( $hot_id, 'pgds-card', 'pgds-ratio-card' ); ?>
								<svg class="play-mini" viewBox="0 0 40 40" aria-hidden="true" focusable="false"><circle cx="20" cy="20" r="19" fill="#fff" opacity=".92"></circle><path d="M17 13l13 7-13 7z" fill="#452A21"></path></svg>
								<?php if ( $hot_dur_s ) : ?>
									<span class="dur"><?php echo esc_html( $hot_dur_s ); ?></span>
								<?php endif; ?>
							</a>
							<div>
								<h3><a href="<?php echo esc_url( get_permalink( $hot_id ) ); ?>"><?php echo esc_html( get_the_title( $hot_id ) ); ?></a></h3>
								<div class="meta">
									<time datetime="<?php echo esc_attr( get_the_date( DATE_W3C, $hot_id ) ); ?>"><?php echo esc_html( date_i18n( 'l, d/m/Y | H:i', get_post_timestamp( $hot_id ) ) ); ?></time>
								</div>
								<?php if ( $hot_sapo ) : ?>
									<p class="sapo"><?php echo esc_html( $hot_sapo ); ?></p>
								<?php endif; ?>
							</div>
						</div>
					<?php endforeach; ?>
				</section>
			<?php endif; ?>
		</article>

		<aside class="pgds-sidebar" role="complementary">
			<?php if ( ! empty( $related_videos ) ) : ?>
				<div class="side-video-panel">
					<div class="panel-title"><?php esc_html_e( 'VIDEO CÙNG CHUYÊN MỤC', 'pgds' ); ?></div>
					<?php foreach ( $related_videos as $rel_post ) : ?>
						<?php
						$rel_id    = $rel_post->ID;
						$rel_dur   = (int) get_post_meta( $rel_id, '_pgds_youtube_dur', true );
						$rel_dur_s = pgds_format_duration( $rel_dur );
						?>
						<div class="svp-item">
							<a href="<?php echo esc_url( get_permalink( $rel_id ) ); ?>" class="thumb art">
								<?php pgds_art( $rel_id, 'pgds-thumb', 'pgds-ratio-card' ); ?>
								<svg class="play-mini" viewBox="0 0 40 40" aria-hidden="true" focusable="false"><circle cx="20" cy="20" r="19" fill="#fff" opacity=".92"></circle><path d="M17 13l13 7-13 7z" fill="#452A21"></path></svg>
								<?php if ( $rel_dur_s ) : ?>
									<span class="dur"><?php echo esc_html( $rel_dur_s ); ?></span>
								<?php endif; ?>
							</a>
							<h4><a href="<?php echo esc_url( get_permalink( $rel_id ) ); ?>"><?php echo esc_html( get_the_title( $rel_id ) ); ?></a></h4>
						</div>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</aside>
	</div>
</main>
