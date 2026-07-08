<!doctype html>
<?php /* oyc-rebuild v1.7.53 recovery — forces WP Pusher to overwrite this template */ ?>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>" />
	<meta name="viewport" content="width=device-width, initial-scale=1" />
	<link rel="profile" href="https://gmpg.org/xfn/11" />
	<link rel="icon" type="image/png" sizes="32x32" href="<?php echo esc_url( get_template_directory_uri() . '/assets/favicon-32.png' ); ?>" />
	<link rel="icon" type="image/png" sizes="192x192" href="<?php echo esc_url( get_template_directory_uri() . '/assets/favicon-192.png' ); ?>" />
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<a class="skip" href="#main"><?php esc_html_e( 'Skip to content', 'orienta-yacht-club' ); ?></a>

<header class="site-header">
	<div class="container header-inner">
		<a class="brand<?php echo oyc_has_real_logo() ? ' brand--has-logo' : ''; ?>" href="<?php echo esc_url( home_url( '/' ) ); ?>" aria-label="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>">
			<?php oyc_burgee( 'brand-mark' ); ?>
			<?php if ( ! oyc_has_real_logo() ) : ?>
			<span class="brand-text">
				<span class="brand-name"><?php bloginfo( 'name' ); ?></span>
				<?php $tagline = get_bloginfo( 'description' ); if ( $tagline ) : ?>
					<span class="brand-sub"><?php echo esc_html( $tagline ); ?></span>
				<?php endif; ?>
			</span>
			<?php endif; ?>
		</a>
		<nav class="primary-nav" aria-label="<?php esc_attr_e( 'Primary', 'orienta-yacht-club' ); ?>">
			<button class="nav-toggle" aria-expanded="false" aria-controls="nav-menu">
				<span class="sr-only"><?php esc_html_e( 'Menu', 'orienta-yacht-club' ); ?></span>
				<span class="bars" aria-hidden="true"></span>
			</button>
			<?php
			if ( has_nav_menu( 'primary' ) ) {
				wp_nav_menu( array(
					'theme_location' => 'primary',
					'container'      => false,
					'menu_id'        => 'nav-menu',
					'depth'          => 1,
				) );
			} else {
				?>
				<ul id="nav-menu">
					<li class="nav-home"><a href="<?php echo esc_url( home_url( '/' ) ); ?>" aria-label="<?php esc_attr_e( 'Home', 'orienta-yacht-club' ); ?>"><svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 9.5L12 3l9 6.5V20a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V9.5z"/><polyline points="9 21 9 12 15 12 15 21"/></svg></a></li>
					<li><a href="<?php echo esc_url( home_url( '/#about' ) ); ?>"><?php esc_html_e( 'About', 'orienta-yacht-club' ); ?></a></li>
					<li><a href="<?php echo esc_url( home_url( '/#membership' ) ); ?>"><?php esc_html_e( 'Membership', 'orienta-yacht-club' ); ?></a></li>
					<li><a href="<?php echo esc_url( home_url( '/#sailing' ) ); ?>"><?php esc_html_e( 'Boating', 'orienta-yacht-club' ); ?></a></li>
					<li><a href="<?php echo esc_url( home_url( '/#fishing' ) ); ?>"><?php esc_html_e( 'Fishing', 'orienta-yacht-club' ); ?></a></li>
					<li><a href="<?php echo esc_url( home_url( '/#visitors' ) ); ?>"><?php esc_html_e( 'Visitors', 'orienta-yacht-club' ); ?></a></li>
					<li><a href="<?php echo esc_url( home_url( '/calendar/' ) ); ?>"><?php esc_html_e( 'Calendar', 'orienta-yacht-club' ); ?></a></li>
					<li><a href="<?php echo esc_url( home_url( '/contact/' ) ); ?>"><?php esc_html_e( 'Contact', 'orienta-yacht-club' ); ?></a></li>
					<li><a class="cta" href="<?php echo esc_url( home_url( '/#membership' ) ); ?>"><?php esc_html_e( 'Join', 'orienta-yacht-club' ); ?></a></li>
					<li>
						<?php if ( is_user_logged_in() ) : ?>
							<a class="cta cta--login" href="<?php echo esc_url( home_url( '/members-area/' ) ); ?>">
								<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
								<?php echo esc_html( wp_get_current_user()->display_name ?: __( 'My Area', 'orienta-yacht-club' ) ); ?>
							</a>
						<?php else : ?>
							<a class="cta cta--login" href="<?php echo esc_url( wp_login_url( home_url( '/members-area/' ) ) ); ?>">
								<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>
								<?php esc_html_e( 'Member Login', 'orienta-yacht-club' ); ?>
							</a>
						<?php endif; ?>
					</li>
				</ul>
				<?php
			}
			?>
		</nav>
	</div>
</header>

<main id="main">
