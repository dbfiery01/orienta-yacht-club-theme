<?php
/**
 * Fallback template used for the blog/archive when the front page is set to "Latest posts".
 *
 * @package Orienta_Yacht_Club
 */

get_header();
?>

<section class="section">
	<div class="container">
		<?php if ( have_posts() ) : ?>

			<?php while ( have_posts() ) : the_post(); ?>
				<article <?php post_class( 'entry' ); ?>>
					<h2><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
					<div class="entry-meta" style="font-size:0.85rem;color:rgba(4,22,42,0.55);margin-bottom:0.6rem;">
						<?php echo esc_html( get_the_date() ); ?>
					</div>
					<div class="entry-summary">
						<?php the_excerpt(); ?>
					</div>
				</article>
			<?php endwhile; ?>

			<?php the_posts_pagination(); ?>

		<?php else : ?>
			<p><?php esc_html_e( 'Nothing to see here yet.', 'orienta-yacht-club' ); ?></p>
		<?php endif; ?>
	</div>
</section>

<?php
get_footer();
