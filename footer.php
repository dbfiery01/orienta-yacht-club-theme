</main>

<footer class="site-footer">
	<div class="footer-wave" aria-hidden="true"></div>
	<div class="container footer-inner">

		<!-- Brand + description -->
		<div class="footer-brand-col">
			<?php if ( oyc_has_real_logo() ) : ?>
				<?php oyc_burgee( 'brand-mark' ); ?>
			<?php else : ?>
				<p class="footer-club-name"><?php bloginfo( 'name' ); ?></p>
			<?php endif; ?>
			<p class="footer-desc">Located in the scenic and well-protected East Basin of Mamaroneck Harbor, easily accessible through a well-marked, deep-water channel. Mamaroneck is a gateway to great fishing, sailboat racing, cruising, and just plain &#8220;messing about in boats.&#8221;</p>
		</div>

		<!-- Nav columns -->
		<nav class="footer-nav-cols" aria-label="<?php esc_attr_e( 'Footer', 'orienta-yacht-club' ); ?>">
			<div class="footer-nav-col">
				<h4><?php esc_html_e( 'The Club', 'orienta-yacht-club' ); ?></h4>
				<ul>
					<li><a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Home', 'orienta-yacht-club' ); ?></a></li>
					<li><a href="<?php echo esc_url( home_url( '/#about' ) ); ?>"><?php esc_html_e( 'About Us', 'orienta-yacht-club' ); ?></a></li>
					<li><a href="<?php echo esc_url( home_url( '/our-history/' ) ); ?>"><?php esc_html_e( 'Our History', 'orienta-yacht-club' ); ?></a></li>
					<li><a href="<?php echo esc_url( home_url( '/officers-trustees/' ) ); ?>"><?php esc_html_e( 'Officers &amp; Trustees', 'orienta-yacht-club' ); ?></a></li>
					<li><a href="<?php echo esc_url( home_url( '/calendar/' ) ); ?>"><?php esc_html_e( 'Calendar', 'orienta-yacht-club' ); ?></a></li>
					<li><a href="https://dockwa.com/explore/destination/3gcrvl-orienta-yacht-club" target="_blank" rel="noopener"><?php esc_html_e( 'Reservations', 'orienta-yacht-club' ); ?></a></li>
					<li><a href="<?php echo esc_url( home_url( '/#contact' ) ); ?>"><?php esc_html_e( 'Contact', 'orienta-yacht-club' ); ?></a></li>
				</ul>
			</div>
			<div class="footer-nav-col">
				<h4><?php esc_html_e( 'Activities', 'orienta-yacht-club' ); ?></h4>
				<ul>
					<li><a href="<?php echo esc_url( home_url( '/facilities/' ) ); ?>"><?php esc_html_e( 'Facilities', 'orienta-yacht-club' ); ?></a></li>
					<li><a href="<?php echo esc_url( home_url( '/social-events/' ) ); ?>"><?php esc_html_e( 'Social Events', 'orienta-yacht-club' ); ?></a></li>
					<li><a href="<?php echo esc_url( home_url( '/#fishing' ) ); ?>"><?php esc_html_e( 'Fishing', 'orienta-yacht-club' ); ?></a></li>
					<li><a href="<?php echo esc_url( home_url( '/#sailing' ) ); ?>"><?php esc_html_e( 'Racing', 'orienta-yacht-club' ); ?></a></li>
					<li><a href="<?php echo esc_url( home_url( '/#membership' ) ); ?>"><?php esc_html_e( 'Membership', 'orienta-yacht-club' ); ?></a></li>
					<li><a href="<?php echo esc_url( home_url( '/additional-information/' ) ); ?>"><?php esc_html_e( 'Additional Information', 'orienta-yacht-club' ); ?></a></li>
					<li><a href="https://orientayachtclub.com/members-log/" target="_blank" rel="noopener"><?php esc_html_e( 'Members Login', 'orienta-yacht-club' ); ?></a></li>
				</ul>
			</div>
			<div class="footer-nav-col">
				<h4><?php esc_html_e( 'Resources', 'orienta-yacht-club' ); ?></h4>
				<ul>
					<li><a href="<?php echo esc_url( home_url( '/mamaroneck-harbor/' ) ); ?>"><?php esc_html_e( 'Mamaroneck Harbor', 'orienta-yacht-club' ); ?></a></li>
					<li><a href="<?php echo esc_url( 'https://lisicos.uconn.edu/' ); ?>"><?php esc_html_e( 'My Sound', 'orienta-yacht-club' ); ?></a></li>
					<li><a href="<?php echo esc_url( home_url( '/storm-warnings/' ) ); ?>"><?php esc_html_e( 'Storm Warnings', 'orienta-yacht-club' ); ?></a></li>
					<li><a href="<?php echo esc_url( home_url( '/oyc-resources/' ) ); ?>"><?php esc_html_e( 'OYC Resources', 'orienta-yacht-club' ); ?></a></li>
					<li><a href="<?php echo esc_url( home_url( '/mamaroneck-harbor/' ) ); ?>"><?php esc_html_e( 'Mamaroneck Harbor', 'orienta-yacht-club' ); ?></a></li>
					<li><a href="<?php echo esc_url( home_url( '/reciprocity-list/' ) ); ?>"><?php esc_html_e( 'Reciprocity List', 'orienta-yacht-club' ); ?></a></li>
					<li><a href="<?php echo esc_url( home_url( '/#membership' ) ); ?>"><?php esc_html_e( 'Membership', 'orienta-yacht-club' ); ?></a></li>
				</ul>
			</div>
		</nav>

	</div><!-- .footer-inner -->

	<div class="footer-bottom">
		<div class="container">
			<p class="copyright">&copy; <?php echo esc_html( date( 'Y' ) ); ?> <?php bloginfo( 'name' ); ?>. <?php esc_html_e( 'All Rights Reserved.', 'orienta-yacht-club' ); ?></p>
		</div>
	</div>
</footer>

<?php wp_footer(); ?>
</body>
</html>
