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
?>

<main id="pgds-main" class="pgds-wrap" role="main">
	<?php
	$breadcrumbs = array(
		array(
			'label' => __( 'Trang chủ', 'pgds' ),
			'url'   => home_url( '/' ),
		),
	);
	if ( $term instanceof WP_Term ) {
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
	<div class="pgds-content-grid">
		<article <?php post_class( 'pgds-article pgds-article--video' ); ?>>
			<header class="pgds-article__header">
				<?php pgds_cat_label( $post_id ); ?>
				<h1 class="pgds-article__title"><?php the_title(); ?></h1>
				<?php $sapo = pgds_has_editorial_sapo( $post_id ) ? pgds_sapo( $post_id ) : ''; ?>
				<?php if ( $sapo ) : ?>
					<p class="pgds-article__sapo"><?php echo esc_html( $sapo ); ?></p>
				<?php endif; ?>
				<div class="pgds-article__meta">
					<span><?php echo esc_html( get_the_author() ); ?></span>
					<time datetime="<?php echo esc_attr( get_the_date( DATE_W3C ) ); ?>"><?php echo esc_html( pgds_time_ago( $post_id ) ); ?></time>
				</div>
			</header>

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
					'unavailable'   => false,
				)
			);
			?>

			<div class="pgds-article__body">
				<?php the_content(); ?>
			</div>
		</article>

		<?php get_sidebar(); ?>
	</div>
</main>
