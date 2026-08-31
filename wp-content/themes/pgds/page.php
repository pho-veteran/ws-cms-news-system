<?php
/**
 * Static page (About, Contact, Policy...).
 *
 * @package pgds
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
pgds_breadcrumb();

while ( have_posts() ) :
	the_post();
	?>
	<main id="pgds-main" class="pgds-wrap" role="main">
		<article <?php post_class( 'pgds-article pgds-article--standalone' ); ?>>
			<header class="pgds-article__header">
				<h1 class="pgds-article__title"><?php the_title(); ?></h1>
			</header>

			<?php if ( has_post_thumbnail() ) : ?>
				<figure class="pgds-article__figure">
					<?php the_post_thumbnail( 'pgds-lead' ); ?>
				</figure>
			<?php endif; ?>

			<div class="pgds-article__body">
				<?php the_content(); ?>
			</div>
		</article>
	</main>
	<?php
endwhile;

get_footer();
