<?php
/**
 * Template Name: Members Only
 * Requires login to view. Guests are redirected to the login page.
 *
 * @package Orienta_Yacht_Club
 */

if ( ! is_user_logged_in() ) {
	wp_redirect( wp_login_url( get_permalink() ) );
	exit;
}

get_header();
?>

<div class="page-hero">
	<div class="container">
		<h1 class="page-hero-title"><?php the_title(); ?></h1>
	</div>
</div>

<section class="section">
	<div class="container page-content members-content">
		<div class="members-badge">
			<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
			<?php esc_html_e( 'Members Only', 'orienta-yacht-club' ); ?>
		</div>
		<?php while ( have_posts() ) : the_post(); ?>
			<article <?php post_class(); ?>>
				<div class="entry-content">
					<?php the_content(); ?>
				</div>
			</article>
		<?php endwhile; ?>
	</div>
</section>

<?php get_footer(); ?>
