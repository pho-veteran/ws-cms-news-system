<?php
/**
 * Front page - 11 content zones (proposal §2.2).
 *
 * @package pgds
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/*
 * Paged requests (/page/2/, /page/3/ ...) hand off to index.php.
 *
 * The 11 blocks here are a CURATED front page: the lead comes from
 * _pgds_feature_rank, the photo panel from _pgds_photo_story, and the dedup pass
 * assumes one render per request (§4.4). None of that has a "page 2" — WordPress was
 * re-rendering the identical curated layout at /page/2/ and answering 200, which is
 * duplicate content under a second URL and offers the reader nothing new.
 *
 * index.php is the correct surface for that: a plain reverse-chronological list with
 * working pagination. /page/99/ already 404s via core's own handling, so only real
 * pages reach this.
 */
if ( is_paged() ) {
	include get_template_directory() . '/index.php';
	return;
}

get_header();

$B = pgds_home_blocks();

/**
 * Local helper: render 1 part for each post in an array.
 *
 * @param string $slug Part slug.
 * @param array  $posts Posts.
 * @param array  $extra Extra args.
 */
$render_each = static function ( $slug, $posts, $extra = array() ) {
	foreach ( (array) $posts as $p ) {
		if ( $p instanceof WP_Post ) {
			get_template_part( 'template-parts/' . $slug, null, array_merge( array( 'post' => $p ), $extra ) );
		}
	}
};
?>

<main id="pgds-main" class="pgds-wrap" role="main">

	<h1 class="u-sr-only"><?php bloginfo( 'name' ); ?> — <?php bloginfo( 'description' ); ?></h1>

	<!-- ============ (3) FEATURE GRID: Featured news ============ -->
	<section class="pgds-section pgds-feature-grid pgds-feature-grid--top" aria-label="<?php esc_attr_e( 'Tin nổi bật', 'pgds' ); ?>">
		<div class="pgds-feature-main">
			<?php if ( $B['lead'] ) : ?>
				<?php get_template_part( 'template-parts/card-lead', null, array( 'post' => $B['lead'], 'eager' => true ) ); ?>
			<?php endif; ?>

			<?php if ( ! empty( $B['secondary'] ) ) : ?>
				<div class="pgds-feature-secondary">
					<?php $render_each( 'card-secondary', $B['secondary'], array( 'variant' => 'compact' ) ); ?>
				</div>
			<?php endif; ?>
		</div>

		<!-- Photo news panel -->
		<?php if ( ! empty( $B['photo'] ) ) : $ph = $B['photo'][0]; ?>
			<aside class="pgds-photo-panel" aria-label="<?php esc_attr_e( 'Tin ảnh', 'pgds' ); ?>">
				<div class="pgds-photo-panel__head"><?php esc_html_e( 'Tin ảnh', 'pgds' ); ?></div>
				<a class="pgds-photo-panel__link" href="<?php echo esc_url( get_permalink( $ph ) ); ?>">
					<?php pgds_art( $ph, 'pgds-lead', 'pgds-ratio-lead' ); ?>
					<span class="pgds-photo-panel__cap"><?php echo esc_html( get_the_title( $ph ) ); ?></span>
				</a>
				<div class="pgds-photo-panel__dots" aria-hidden="true">
					<?php foreach ( $B['photo'] as $i => $x ) : ?>
						<span class="<?php echo 0 === $i ? 'is-active' : ''; ?>"></span>
					<?php endforeach; ?>
				</div>
			</aside>
		<?php endif; ?>
	</section>

	<!-- ============ (4) DIVIDER ============ -->
	<div class="pgds-divider" aria-hidden="true">
		<svg viewBox="0 0 1180 22" preserveAspectRatio="none"><path d="M0 0 Q 590 22 1180 0 L1180 22 L0 22 Z" fill="#EAE1CC"></path></svg>
	</div>

	<!-- ============ (5) MEDIA BLOCK ============ -->
	<?php if ( $B['media_feature'] || ! empty( $B['media_thumbs'] ) ) : ?>
	<section class="pgds-media-block" aria-labelledby="pgds-media-title">
		<div class="pgds-media-block__head">
			<div class="pgds-media-block__head-left">
				<span class="pgds-media-block__dot" aria-hidden="true"></span>
				<h2 class="pgds-media-block__title" id="pgds-media-title"><?php esc_html_e( 'Media', 'pgds' ); ?></h2>
			</div>
			<div class="pgds-media-block__tabs" role="tablist" aria-label="<?php esc_attr_e( 'Loại media', 'pgds' ); ?>">
				<button class="pgds-tab" role="tab" id="pgds-tab-video" aria-selected="true" aria-controls="pgds-panel-video"><?php esc_html_e( 'Video', 'pgds' ); ?></button>
				<button class="pgds-tab" role="tab" id="pgds-tab-ema" aria-selected="false" aria-controls="pgds-panel-ema" tabindex="-1"><?php esc_html_e( 'Emagazine', 'pgds' ); ?></button>
				<button class="pgds-tab" role="tab" id="pgds-tab-info" aria-selected="false" aria-controls="pgds-panel-info" tabindex="-1"><?php esc_html_e( 'Infographic', 'pgds' ); ?></button>
			</div>
		</div>

		<div class="pgds-tabpanel" role="tabpanel" id="pgds-panel-video" aria-labelledby="pgds-tab-video">
			<div class="pgds-media-layout">
				<?php if ( $B['media_feature'] ) : $mf = $B['media_feature']; ?>
					<a class="pgds-media-feature" href="<?php echo esc_url( get_permalink( $mf ) ); ?>">
						<?php pgds_art( $mf, 'pgds-lead', 'pgds-ratio-video' ); ?>
						<span class="pgds-play" aria-hidden="true"><?php pgds_play_svg(); ?></span>
						<span class="pgds-media-feature__overlay">
							<span class="pgds-media-feature__title"><?php echo esc_html( get_the_title( $mf ) ); ?></span>
						</span>
					</a>
				<?php endif; ?>

				<div class="pgds-media-right">
					<div class="pgds-grid-4">
						<?php foreach ( (array) $B['media_thumbs'] as $mt ) : ?>
							<a class="pgds-media-thumb" href="<?php echo esc_url( get_permalink( $mt ) ); ?>">
								<?php pgds_art( $mt, 'pgds-thumb', 'pgds-ratio-thumb' ); ?>
								<span class="pgds-play pgds-play--sm" aria-hidden="true"><?php pgds_play_svg(); ?></span>
								<span class="pgds-media-thumb__title"><?php echo esc_html( get_the_title( $mt ) ); ?></span>
							</a>
						<?php endforeach; ?>
					</div>

					<?php if ( ! empty( $B['media_bullets'] ) ) : ?>
						<ul class="pgds-media-bullets">
							<?php foreach ( $B['media_bullets'] as $mb ) : ?>
								<li>
									<?php pgds_icon( 'video', array( 'class' => 'pgds-media-bullets__icon', 'size' => 16 ) ); ?>
									<a href="<?php echo esc_url( get_permalink( $mb ) ); ?>"><?php echo esc_html( get_the_title( $mb ) ); ?></a>
								</li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
				</div>
			</div>
		</div>

		<?php
		/*
		 * Tabs 2 and 3 previously held "Nội dung Emagazine sẽ cập nhật." — but the tabs are
		 * fully operable (media-tabs.js implements the real WAI-ARIA pattern), so two of the
		 * three tabs on the page's largest interactive component were dead ends. Rendered
		 * with the same thumbnail grid as the video tab so the three panels are visually
		 * consistent, minus the play badge: these are articles, not videos, and a play
		 * affordance over an infographic would promise something that cannot happen.
		 *
		 * A panel with genuinely no posts keeps an explanatory line rather than collapsing
		 * to nothing, so the tab does not look broken.
		 */
		$pgds_media_panels = array(
			'ema'  => array(
				'id'    => 'pgds-panel-ema',
				'tab'   => 'pgds-tab-ema',
				'posts' => $B['media_tabs']['emagazine'] ?? array(),
				'empty' => __( 'Chưa có nội dung Emagazine.', 'pgds' ),
			),
			'info' => array(
				'id'    => 'pgds-panel-info',
				'tab'   => 'pgds-tab-info',
				'posts' => $B['media_tabs']['infographic'] ?? array(),
				'empty' => __( 'Chưa có nội dung Infographic.', 'pgds' ),
			),
		);
		foreach ( $pgds_media_panels as $pgds_panel ) :
			?>
			<div class="pgds-tabpanel" role="tabpanel" id="<?php echo esc_attr( $pgds_panel['id'] ); ?>" aria-labelledby="<?php echo esc_attr( $pgds_panel['tab'] ); ?>" hidden>
				<?php if ( ! empty( $pgds_panel['posts'] ) ) : ?>
					<div class="pgds-grid-4">
						<?php foreach ( $pgds_panel['posts'] as $pgds_mp ) : ?>
							<a class="pgds-media-thumb" href="<?php echo esc_url( get_permalink( $pgds_mp ) ); ?>">
								<?php pgds_art( $pgds_mp, 'pgds-thumb', 'pgds-ratio-thumb' ); ?>
								<span class="pgds-media-thumb__title"><?php echo esc_html( get_the_title( $pgds_mp ) ); ?></span>
							</a>
						<?php endforeach; ?>
					</div>
				<?php else : ?>
					<p class="pgds-tabpanel__empty"><?php echo esc_html( $pgds_panel['empty'] ); ?></p>
				<?php endif; ?>
			</div>
			<?php
		endforeach;
		?>
	</section>
	<?php endif; ?>

	<!-- ============ (6+7) CONTENT GRID 1: Buddhist affairs news + sidebar ============ -->
	<div class="pgds-content-grid">
		<div>
			<?php // Skip the whole section when it has nothing to show: a heading plus a "Xem thêm" link above empty space reads as a fault, not as a section. ?>
			<?php if ( ! empty( $B['phatsu_cards'] ) || ! empty( $B['phatsu_list'] ) ) : ?>
			<section class="pgds-section pgds-section--flush" aria-labelledby="pgds-phatsu-title">
				<div class="pgds-cat-head">
					<h2 id="pgds-phatsu-title"><?php esc_html_e( 'Tin Phật sự', 'pgds' ); ?></h2>
					<a class="pgds-cat-head__more" href="<?php echo esc_url( get_term_link( 'tin-phat-su', 'category' ) ); ?>">
						<?php esc_html_e( 'Xem thêm', 'pgds' ); ?><?php pgds_icon( 'chevron', array( 'size' => 14 ) ); ?>
					</a>
				</div>

				<?php if ( ! empty( $B['phatsu_cards'] ) ) : ?>
					<div class="pgds-grid-3">
						<?php foreach ( $B['phatsu_cards'] as $p ) : ?>
							<?php get_template_part( 'template-parts/card-secondary', null, array( 'post' => $p, 'variant' => 'full', 'bordered' => true ) ); ?>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>

				<?php if ( ! empty( $B['phatsu_list'] ) ) : ?>
					<div class="pgds-list">
						<?php $render_each( 'list-item', $B['phatsu_list'] ); ?>
					</div>
				<?php endif; ?>
			</section>
			<?php endif; ?>
		</div>

		<aside aria-label="<?php esc_attr_e( 'Thông tin bên lề', 'pgds' ); ?>">
			<?php get_template_part( 'template-parts/sidebar-popular', null, array( 'posts' => $B['popular'] ) ); ?>
			<?php get_template_part( 'template-parts/sidebar-lunar', null, array( 'post' => $B['lunar'] ) ); ?>
		</aside>
	</div>

	<!-- ============ (8) DIVIDER ============ -->
	<div class="pgds-rule" aria-hidden="true"></div>

	<!-- ============ (9) THREE-CATEGORY ============ -->
	<section class="pgds-section pgds-cat-triple" aria-label="<?php esc_attr_e( 'Chuyên mục nổi bật', 'pgds' ); ?>">
		<?php foreach ( $B['columns'] as $col ) : $data = $col['data']; ?>
			<div class="pgds-cat-col">
				<div class="pgds-cat-col__head">
					<span class="bar" aria-hidden="true"></span>
					<h3><a href="<?php echo esc_url( get_term_link( $col['slug'], 'category' ) ); ?>"><?php echo esc_html( $col['label'] ); ?></a></h3>
				</div>

				<?php if ( $data['feat'] instanceof WP_Post ) : $f = $data['feat']; ?>
					<div class="pgds-cat-col__feat">
						<?php // The image link duplicates the title link below it, so it is hidden from assistive tech and removed from the tab order rather than announced as a second unlabeled link. ?>
						<a class="pgds-cat-col__feat-media" href="<?php echo esc_url( get_permalink( $f ) ); ?>" tabindex="-1" aria-hidden="true">
							<?php pgds_art( $f, 'pgds-card', 'pgds-ratio-card' ); ?>
						</a>
						<h4><a href="<?php echo esc_url( get_permalink( $f ) ); ?>"><?php echo esc_html( get_the_title( $f ) ); ?></a></h4>
					</div>
				<?php endif; ?>

				<?php $render_each( 'card-mini', $data['mini'] ); ?>
			</div>
		<?php endforeach; ?>
	</section>

	<!-- ============ (10) CONTENT GRID 2: mixed + Vietnam Buddhism + teachings ============ -->
	<div class="pgds-content-grid">
		<div>
			<?php if ( ! empty( $B['mixed_list'] ) ) : ?>
				<section class="pgds-section" aria-label="<?php esc_attr_e( 'Tin mới', 'pgds' ); ?>">
					<div class="pgds-list pgds-list--flush">
						<?php $render_each( 'list-item', $B['mixed_list'] ); ?>
					</div>
				</section>
			<?php endif; ?>

			<?php if ( ! empty( $B['vn_list'] ) ) : ?>
				<section class="pgds-section" aria-labelledby="pgds-vn-title">
					<div class="pgds-cat-head">
						<h2 id="pgds-vn-title">Vietnam Buddhism</h2>
						<a class="pgds-cat-head__more" href="<?php echo esc_url( get_term_link( 'vietnam-buddhism', 'category' ) ); ?>">View more<?php pgds_icon( 'chevron', array( 'size' => 14 ) ); ?></a>
					</div>
					<ul class="pgds-compact">
						<?php foreach ( $B['vn_list'] as $p ) : ?>
							<li>
								<a href="<?php echo esc_url( get_permalink( $p ) ); ?>" tabindex="-1" aria-hidden="true">
									<?php pgds_art( $p, 'pgds-square', 'pgds-ratio-square' ); ?>
								</a>
								<div>
									<h4><a href="<?php echo esc_url( get_permalink( $p ) ); ?>"><?php echo esc_html( get_the_title( $p ) ); ?></a></h4>
									<div class="pgds-compact__meta"><?php echo esc_html( get_the_date( 'd/m/Y', $p ) ); ?></div>
								</div>
							</li>
						<?php endforeach; ?>
					</ul>
				</section>
			<?php endif; ?>
		</div>

		<aside aria-label="<?php esc_attr_e( 'Lời Phật dạy', 'pgds' ); ?>">
			<?php if ( ! empty( $B['teaching'] ) ) : ?>
				<section class="pgds-side-block" aria-labelledby="pgds-teaching-title">
					<h3 class="pgds-side-block__title" id="pgds-teaching-title"><?php esc_html_e( 'Lời Phật dạy', 'pgds' ); ?></h3>
					<ul class="pgds-teaching">
						<?php foreach ( $B['teaching'] as $t ) : ?>
							<li>
								<span class="pgds-teaching__icon"><?php pgds_icon( 'headphones', array( 'size' => 16 ) ); ?></span>
								<span><a href="<?php echo esc_url( get_permalink( $t ) ); ?>"><?php echo esc_html( get_the_title( $t ) ); ?></a></span>
							</li>
						<?php endforeach; ?>
					</ul>
				</section>
			<?php endif; ?>
		</aside>
	</div>

</main>

<?php
get_footer();
