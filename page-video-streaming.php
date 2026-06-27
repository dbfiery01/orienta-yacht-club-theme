<?php
/**
 * Template Name: Video Streaming
 * Members-only placeholder for the upcoming video streaming page. Guests are
 * redirected to login. Any content added in the editor renders below the
 * placeholder, so this can be fleshed out into the real feature later.
 *
 * @package Orienta_Yacht_Club
 */

// Members only — send guests to login, returning here afterward.
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
	<div class="container page-content">

		<div class="coming-soon">
			<span class="coming-soon__badge"><?php esc_html_e( 'Members Only', 'orienta-yacht-club' ); ?></span>
			<h2 class="coming-soon__title"><?php esc_html_e( 'Video Streaming — Coming Soon', 'orienta-yacht-club' ); ?></h2>
			<p class="coming-soon__text"><?php esc_html_e( 'Members-only video streaming is on its way. Soon you’ll be able to watch club videos, race coverage, and event recordings right here. Check back shortly!', 'orienta-yacht-club' ); ?></p>
		</div>

		<?php
		// Render any content added via the editor (so the page can grow into the
		// real feature without a template change).
		while ( have_posts() ) :
			the_post();
			if ( '' !== trim( get_the_content() ) ) :
				?>
				<div class="entry-content"><?php the_content(); ?></div>
				<?php
			endif;
		endwhile;
		?>

	</div>
</section>

<?php get_footer(); ?>
