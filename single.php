<?php
/**
 * Default template for individual blog posts.
 *
 * @package Orienta_Yacht_Club
 */

get_header();
?>

<section class="section">
	<div class="container" style="max-width:780px;">
		<?php while ( have_posts() ) : the_post(); ?>
			<article <?php post_class(); ?>>
				<p class="kicker"><?php echo esc_html( get_the_date() ); ?></p>
				<h1><?php the_title(); ?></h1>
				<div class="entry-content">
					<?php the_content(); ?>
				</div>
			</article>
		<?php endwhile; ?>
	</div>
</section>

<?php
get_footer();
