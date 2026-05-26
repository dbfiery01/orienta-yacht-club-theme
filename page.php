<?php
/**
 * Default template for individual pages.
 *
 * @package Orienta_Yacht_Club
 */

get_header();
?>

<div class="page-hero">
	<div class="container">
		<h1 class="page-hero-title"><?php the_title(); ?></h1>
	</div>
</div>

<section class="section">
	<div class="container page-content">
		<?php while ( have_posts() ) : the_post(); ?>
			<article <?php post_class(); ?>>
				<div class="entry-content">
					<?php the_content(); ?>
				</div>
			</article>
		<?php endwhile; ?>
	</div>
</section>

<?php
get_footer();
