<?php
/**
 * Front page - 11 content zones (proposal §2.2).
 *
 * @package pgds
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
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
	<section class="pgds-section pgds-feature-grid" aria-label="<?php esc_attr_e( 'Tin nổi bật', 'pgds' ); ?>" style="margin-top:22px;">
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
				<a href="<?php echo esc_url( get_permalink( $ph ) ); ?>" style="position:relative;flex:1;display:flex;">
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
								<span class="pgds-play" aria-hidden="true" style="width:26px;height:26px;"><?php pgds_play_svg(); ?></span>
								<span class="pgds-media-thumb__title"><?php echo esc_html( get_the_title( $mt ) ); ?></span>
							</a>
						<?php endforeach; ?>
					</div>

					<?php if ( ! empty( $B['media_bullets'] ) ) : ?>
						<ul class="pgds-media-bullets">
							<?php foreach ( $B['media_bullets'] as $mb ) : ?>
								<li>
									<svg class="pgds-media-bullets__icon" viewBox="0 0 24 24" aria-hidden="true"><rect x="2" y="6" width="15" height="12" rx="2" fill="#E8C468"></rect><path d="M17 10l5-3v10l-5-3z" fill="#E8C468"></path></svg>
									<a href="<?php echo esc_url( get_permalink( $mb ) ); ?>"><?php echo esc_html( get_the_title( $mb ) ); ?></a>
								</li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
				</div>
			</div>
		</div>

		<div class="pgds-tabpanel" role="tabpanel" id="pgds-panel-ema" aria-labelledby="pgds-tab-ema" hidden>
			<p style="color:var(--pgds-gilt-pale);"><?php esc_html_e( 'Nội dung Emagazine sẽ cập nhật.', 'pgds' ); ?></p>
		</div>
		<div class="pgds-tabpanel" role="tabpanel" id="pgds-panel-info" aria-labelledby="pgds-tab-info" hidden>
			<p style="color:var(--pgds-gilt-pale);"><?php esc_html_e( 'Nội dung Infographic sẽ cập nhật.', 'pgds' ); ?></p>
		</div>
	</section>
	<?php endif; ?>

	<!-- ============ (6+7) CONTENT GRID 1: Buddhist affairs news + sidebar ============ -->
	<div class="pgds-content-grid">
		<div>
			<section class="pgds-section" aria-labelledby="pgds-phatsu-title" style="margin-bottom:0;">
				<div class="pgds-cat-head">
					<h2 id="pgds-phatsu-title"><?php esc_html_e( 'Tin Phật sự', 'pgds' ); ?></h2>
					<a class="pgds-cat-head__more" href="<?php echo esc_url( get_term_link( 'tin-phat-su', 'category' ) ); ?>"><?php esc_html_e( 'Xem thêm ›', 'pgds' ); ?></a>
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
						<a href="<?php echo esc_url( get_permalink( $f ) ); ?>">
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
					<div class="pgds-list" style="margin-top:0;">
						<?php $render_each( 'list-item', $B['mixed_list'] ); ?>
					</div>
				</section>
			<?php endif; ?>

			<?php if ( ! empty( $B['vn_list'] ) ) : ?>
				<section class="pgds-section" aria-labelledby="pgds-vn-title">
					<div class="pgds-cat-head">
						<h2 id="pgds-vn-title">Vietnam Buddhism</h2>
						<a class="pgds-cat-head__more" href="<?php echo esc_url( get_term_link( 'vietnam-buddhism', 'category' ) ); ?>">View more ›</a>
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
								<span class="pgds-teaching__icon" aria-hidden="true">🎧</span>
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
