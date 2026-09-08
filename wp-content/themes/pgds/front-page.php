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
		<?php if ( ! empty( $B['photo'] ) ) : ?>
			<aside class="pgds-photo-panel" aria-label="<?php esc_attr_e( 'Tin ảnh', 'pgds' ); ?>" data-pgds="photo-slider">
				<div class="pgds-photo-panel__head"><?php esc_html_e( 'Tin ảnh', 'pgds' ); ?></div>
				<div class="pgds-photo-panel__track">
					<?php foreach ( $B['photo'] as $i => $ph ) : ?>
						<a class="pgds-photo-panel__slide<?php echo 0 !== $i ? ' is-hidden' : ''; ?>"
						   href="<?php echo esc_url( get_permalink( $ph ) ); ?>"
						   <?php echo 0 !== $i ? 'aria-hidden="true" tabindex="-1"' : ''; ?>>
							<?php pgds_art( $ph, 'pgds-lead', 'pgds-ratio-lead' ); ?>
							<span class="pgds-photo-panel__cap"><?php echo esc_html( get_the_title( $ph ) ); ?></span>
						</a>
					<?php endforeach; ?>
				</div>
				<?php
				/*
				 * The dots were clickable <span>s inside an aria-hidden container: operable
				 * with a mouse, invisible and unreachable for keyboard and screen-reader
				 * users. They are real buttons now, with a named target and a full-size hit
				 * area behind the small visual dot.
				 */
				?>
				<div class="pgds-photo-panel__dots">
					<?php foreach ( $B['photo'] as $i => $x ) : ?>
						<button class="pgds-photo-panel__dot<?php echo 0 === $i ? ' is-active' : ''; ?>"
							type="button"
							<?php echo 0 === $i ? 'aria-current="true"' : ''; ?>>
							<span class="u-sr-only">
								<?php
								/* translators: %d: slide number in the photo panel. */
								printf( esc_html__( 'Xem tin ảnh %d', 'pgds' ), (int) $i + 1 );
								?>
							</span>
						</button>
					<?php endforeach; ?>
				</div>
			</aside>
		<?php endif; ?>
	</section>

	<!-- ============ (4) DIVIDER ============ -->
	<?php
	/*
	 * fill uses the token, not a literal.
	 *
	 * This was `fill="#EAE1CC"` — the same value the `paper-deep` token holds, duplicated
	 * where no token change can reach it. §3 requires "All production colors use the tokens
	 * below; components must not use hard-coded colors", and the practical failure is that
	 * retheming would leave this divider stranded at the old colour while the six other
	 * users of --pgds-paper-deep moved.
	 */
	?>
	<div class="pgds-divider" aria-hidden="true">
		<svg viewBox="0 0 1180 22" preserveAspectRatio="none"><path d="M0 0 Q 590 22 1180 0 L1180 22 L0 22 Z" fill="var(--pgds-paper-deep)"></path></svg>
	</div>

	<!-- ============ (5) MEDIA BLOCK ============ -->
	<?php
	$pgds_media_panels = array(
		'video' => array(
			'id'      => 'pgds-panel-video',
			'tab'     => 'pgds-tab-video',
			'label'   => __( 'Video', 'pgds' ),
			'feature' => $B['media_feature'],
			'thumbs'  => $B['media_thumbs'],
			'bullets' => $B['media_bullets'],
			'play'    => true,
			'empty'   => __( 'Chưa có nội dung Video.', 'pgds' ),
		),
		'ema'   => array(
			'id'      => 'pgds-panel-ema',
			'tab'     => 'pgds-tab-ema',
			'label'   => __( 'E-magazine', 'pgds' ),
			'feature' => $B['media_tabs']['emagazine']['feature'] ?? null,
			'thumbs'  => $B['media_tabs']['emagazine']['thumbs'] ?? array(),
			'bullets' => $B['media_tabs']['emagazine']['bullets'] ?? array(),
			'play'    => false,
			'empty'   => __( 'Chưa có nội dung E-magazine.', 'pgds' ),
		),
	);
	$pgds_has_media = array_filter(
		$pgds_media_panels,
		static function ( $panel ) {
			return $panel['feature'] instanceof WP_Post || ! empty( $panel['thumbs'] ) || ! empty( $panel['bullets'] );
		}
	);
	?>
	<?php if ( $pgds_has_media ) : ?>
	<section class="pgds-media-block" aria-labelledby="pgds-media-title">
		<div class="pgds-media-block__head">
			<div class="pgds-media-block__head-left">
				<span class="pgds-media-block__dot" aria-hidden="true"></span>
				<h2 class="pgds-media-block__title" id="pgds-media-title"><?php esc_html_e( 'Media', 'pgds' ); ?></h2>
			</div>
			<div class="pgds-media-block__tabs" role="tablist" aria-label="<?php esc_attr_e( 'Loại media', 'pgds' ); ?>">
				<?php foreach ( $pgds_media_panels as $pgds_panel_key => $pgds_panel ) : ?>
					<button class="pgds-tab" role="tab"
						id="<?php echo esc_attr( $pgds_panel['tab'] ); ?>"
						aria-selected="<?php echo esc_attr( 'video' === $pgds_panel_key ? 'true' : 'false' ); ?>"
						aria-controls="<?php echo esc_attr( $pgds_panel['id'] ); ?>"
						<?php echo 'video' !== $pgds_panel_key ? 'tabindex="-1"' : ''; ?>><?php echo esc_html( $pgds_panel['label'] ); ?></button>
				<?php endforeach; ?>
			</div>
		</div>

		<?php foreach ( $pgds_media_panels as $pgds_panel_key => $pgds_panel ) : ?>
			<?php
			$pgds_panel_feature  = $pgds_panel['feature'] instanceof WP_Post ? $pgds_panel['feature'] : null;
			$pgds_panel_thumbs   = array_filter( (array) $pgds_panel['thumbs'], static fn( $post ) => $post instanceof WP_Post );
			$pgds_panel_bullets  = array_filter( (array) $pgds_panel['bullets'], static fn( $post ) => $post instanceof WP_Post );
			$pgds_panel_has_right = $pgds_panel_thumbs || $pgds_panel_bullets;
			$pgds_layout_classes  = array( 'pgds-media-layout' );
			if ( ! $pgds_panel_feature || ! $pgds_panel_has_right ) {
				$pgds_layout_classes[] = 'pgds-media-layout--single';
			}
			?>
			<div class="pgds-tabpanel" role="tabpanel"
				id="<?php echo esc_attr( $pgds_panel['id'] ); ?>"
				aria-labelledby="<?php echo esc_attr( $pgds_panel['tab'] ); ?>"
				<?php echo 'video' !== $pgds_panel_key ? 'hidden' : ''; ?>>
				<?php if ( $pgds_panel_feature || $pgds_panel_has_right ) : ?>
				<div class="<?php echo esc_attr( implode( ' ', $pgds_layout_classes ) ); ?>">
				<?php if ( $pgds_panel_feature ) : ?>
					<a class="pgds-media-feature" href="<?php echo esc_url( get_permalink( $pgds_panel_feature ) ); ?>">
						<?php pgds_art( $pgds_panel_feature, 'pgds-lead', 'pgds-ratio-video' ); ?>
						<?php if ( $pgds_panel['play'] ) : ?>
							<span class="pgds-play" aria-hidden="true"><?php pgds_play_svg(); ?></span>
						<?php endif; ?>
						<span class="pgds-media-feature__overlay">
							<span class="pgds-media-feature__title"><?php echo esc_html( get_the_title( $pgds_panel_feature ) ); ?></span>
						</span>
					</a>
				<?php endif; ?>

				<?php if ( $pgds_panel_has_right ) : ?>
				<div class="pgds-media-right">
					<?php if ( $pgds_panel_thumbs ) : ?>
						<div class="pgds-grid-4">
						<?php foreach ( $pgds_panel_thumbs as $mt ) : ?>
							<a class="pgds-media-thumb" href="<?php echo esc_url( get_permalink( $mt ) ); ?>">
								<?php pgds_art( $mt, 'pgds-thumb', 'pgds-ratio-thumb' ); ?>
								<?php if ( $pgds_panel['play'] ) : ?>
									<span class="pgds-play pgds-play--sm" aria-hidden="true"><?php pgds_play_svg(); ?></span>
								<?php endif; ?>
								<span class="pgds-media-thumb__title"><?php echo esc_html( get_the_title( $mt ) ); ?></span>
							</a>
						<?php endforeach; ?>
						</div>
					<?php endif; ?>

					<?php if ( $pgds_panel_bullets ) : ?>
						<ul class="pgds-media-bullets">
							<?php foreach ( $pgds_panel_bullets as $mb ) : ?>
								<li>
									<span class="pgds-media-bullets__dot" aria-hidden="true"></span>
									<a href="<?php echo esc_url( get_permalink( $mb ) ); ?>"><?php echo esc_html( get_the_title( $mb ) ); ?></a>
								</li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
				</div>
				<?php endif; ?>
			</div>
				<?php else : ?>
					<p class="pgds-tabpanel__empty"><?php echo esc_html( $pgds_panel['empty'] ); ?></p>
				<?php endif; ?>
			</div>
		<?php endforeach; ?>
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
						<h2 id="pgds-vn-title"><?php esc_html_e( 'Vietnam Buddhism', 'pgds' ); ?></h2>
						<a class="pgds-cat-head__more" href="<?php echo esc_url( get_term_link( 'vietnam-buddhism', 'category' ) ); ?>"><?php esc_html_e( 'View more', 'pgds' ); ?><?php pgds_icon( 'chevron', array( 'size' => 14 ) ); ?></a>
					</div>
					<ul class="pgds-compact">
						<?php foreach ( $B['vn_list'] as $p ) : ?>
							<li>
								<a href="<?php echo esc_url( get_permalink( $p ) ); ?>" tabindex="-1" aria-hidden="true">
									<?php pgds_art( $p, 'pgds-square', 'pgds-ratio-square' ); ?>
								</a>
								<div>
									<h3><a href="<?php echo esc_url( get_permalink( $p ) ); ?>"><?php echo esc_html( get_the_title( $p ) ); ?></a></h3>
									<div class="pgds-compact__meta"><?php echo esc_html( pgds_time_ago( $p ) ); ?></div>
								</div>
							</li>
						<?php endforeach; ?>
					</ul>
				</section>
			<?php endif; ?>
		</div>

		<?php
		/*
		 * The guard wraps the <aside>, not the other way round.
		 *
		 * With the conditional INSIDE the element, a site that has no pgds_teaching posts
		 * still emitted `<aside aria-label="Lời Phật dạy">` containing nothing. Measured on
		 * the live site, whose origin database has 0 teachings:
		 *
		 *   {"label":"Lời Phật dạy","w":320,"h":0,"kids":0,"text":0}
		 *
		 * i.e. a complementary ARIA landmark that a screen reader announces by name and then
		 * has nothing to read out — the same defect class as an empty navigation landmark. It also leaves a zero-height grid child, so the column's spacing
		 * comes from an element with no content.
		 *
		 * Found only by auditing the PRODUCTION site: locally the seed data always supplies
		 * teachings, so the empty branch never rendered in any earlier audit.
		 */
		if ( ! empty( $B['teaching'] ) ) :
			?>
			<aside aria-label="<?php esc_attr_e( 'Lời Phật dạy', 'pgds' ); ?>">
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
			</aside>
			<?php
		endif;
		?>
	</div>

</main>

<?php
get_footer();
