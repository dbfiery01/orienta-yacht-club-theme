<?php
/**
 * Orienta Yacht Club — Customizer settings.
 *
 * Adds a single panel ("OYC Site Content") with one section per page block,
 * so non-technical members can edit copy via Appearance → Customize.
 *
 * Default values come from inc/defaults.php (single source of truth shared
 * with the front-end).
 *
 * @package Orienta_Yacht_Club
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function oyc_customize_register( $wp_customize ) {

	$defaults = oyc_defaults();

	$wp_customize->add_panel( 'oyc_content', array(
		'title'       => __( 'OYC Site Content', 'orienta-yacht-club' ),
		'description' => __( 'Edit the copy that appears on the homepage.', 'orienta-yacht-club' ),
		'priority'    => 30,
	) );

	// Helper: register a setting + control. Default value pulled from the central map.
	$add = function ( $id, $label, $section, $type = 'text' ) use ( $wp_customize, $defaults ) {
		$default = isset( $defaults[ $id ] ) ? $defaults[ $id ] : '';

		$sanitizer = 'sanitize_text_field';
		$control_t = 'text';
		if ( 'textarea' === $type ) {
			$sanitizer = 'sanitize_textarea_field';
			$control_t = 'textarea';
		} elseif ( 'url' === $type ) {
			$sanitizer = 'esc_url_raw';
			$control_t = 'url';
		} elseif ( 'html' === $type ) {
			$sanitizer = 'wp_kses_post';
			$control_t = 'textarea';
		}

		$wp_customize->add_setting( $id, array(
			'default'           => $default,
			'sanitize_callback' => $sanitizer,
			'transport'         => 'refresh',
		) );
		$wp_customize->add_control( $id, array(
			'label'   => $label,
			'section' => $section,
			'type'    => $control_t,
		) );
	};

	/* ===== HERO ===== */
	$wp_customize->add_section( 'oyc_hero', array(
		'title' => __( 'Hero', 'orienta-yacht-club' ),
		'panel' => 'oyc_content',
	) );
	$add( 'oyc_hero_eyebrow',   __( 'Eyebrow',                 'orienta-yacht-club' ), 'oyc_hero' );
	$add( 'oyc_hero_headline',  __( 'Headline',                'orienta-yacht-club' ), 'oyc_hero' );
	$add( 'oyc_hero_lede',      __( 'Lede',                    'orienta-yacht-club' ), 'oyc_hero', 'textarea' );
	$add( 'oyc_hero_cta1_text', __( 'Primary Button — Text',   'orienta-yacht-club' ), 'oyc_hero' );
	$add( 'oyc_hero_cta1_url',  __( 'Primary Button — Link',   'orienta-yacht-club' ), 'oyc_hero', 'url' );
	$add( 'oyc_hero_cta2_text', __( 'Secondary Button — Text', 'orienta-yacht-club' ), 'oyc_hero' );
	$add( 'oyc_hero_cta2_url',  __( 'Secondary Button — Link', 'orienta-yacht-club' ), 'oyc_hero', 'url' );

	// Hero slideshow images (leave blank to use the bundled theme photos)
	for ( $i = 1; $i <= 6; $i++ ) {
		$wp_customize->add_setting( "oyc_hero_slide{$i}", array(
			'default'           => '',
			'sanitize_callback' => 'esc_url_raw',
		) );
		$wp_customize->add_control( new WP_Customize_Image_Control( $wp_customize, "oyc_hero_slide{$i}", array(
			'label'   => sprintf( __( 'Slide %d Image', 'orienta-yacht-club' ), $i ),
			'section' => 'oyc_hero',
		) ) );
	}

	// Section & footer background images
	$wp_customize->add_section( 'oyc_images', array(
		'title' => __( 'Section Images', 'orienta-yacht-club' ),
		'panel' => 'oyc_content',
	) );
	foreach ( array(
		'oyc_img_sailing'  => 'Sailing & Racing — Background',
		'oyc_img_fishing'  => 'Fishing — Background',
		'oyc_img_visitors' => 'Visitors — Background',
		'oyc_img_footer'   => 'Footer — Background',
	) as $key => $label ) {
		$wp_customize->add_setting( $key, array(
			'default'           => '',
			'sanitize_callback' => 'esc_url_raw',
		) );
		$wp_customize->add_control( new WP_Customize_Image_Control( $wp_customize, $key, array(
			'label'   => __( $label, 'orienta-yacht-club' ),
			'section' => 'oyc_images',
		) ) );
	}

	/* ===== ABOUT ===== */
	$wp_customize->add_section( 'oyc_about', array(
		'title' => __( 'About', 'orienta-yacht-club' ),
		'panel' => 'oyc_content',
	) );
	$add( 'oyc_about_kicker',   __( 'Kicker',      'orienta-yacht-club' ), 'oyc_about' );
	$add( 'oyc_about_headline', __( 'Headline',    'orienta-yacht-club' ), 'oyc_about' );
	$add( 'oyc_about_p1',       __( 'Paragraph 1', 'orienta-yacht-club' ), 'oyc_about', 'textarea' );
	$add( 'oyc_about_p2',       __( 'Paragraph 2', 'orienta-yacht-club' ), 'oyc_about', 'textarea' );

	for ( $i = 1; $i <= 4; $i++ ) {
		$add( "oyc_fact{$i}_value", "Fact {$i} — Big text",   'oyc_about' );
		$add( "oyc_fact{$i}_label", "Fact {$i} — Small text", 'oyc_about' );
	}
	for ( $i = 1; $i <= 3; $i++ ) {
		$add( "oyc_card{$i}_title", "Card {$i} — Title", 'oyc_about' );
		$add( "oyc_card{$i}_body",  "Card {$i} — Body",  'oyc_about', 'textarea' );
	}

	/* ===== MEMBERSHIP ===== */
	$wp_customize->add_section( 'oyc_membership', array(
		'title' => __( 'Membership', 'orienta-yacht-club' ),
		'panel' => 'oyc_content',
	) );
	$add( 'oyc_mem_kicker',   __( 'Kicker',   'orienta-yacht-club' ), 'oyc_membership' );
	$add( 'oyc_mem_headline', __( 'Headline', 'orienta-yacht-club' ), 'oyc_membership' );
	$add( 'oyc_mem_lede',     __( 'Lede',     'orienta-yacht-club' ), 'oyc_membership', 'textarea' );
	for ( $i = 1; $i <= 3; $i++ ) {
		$add( "oyc_mem_tile{$i}_title", "Tile {$i} — Title", 'oyc_membership' );
		$add( "oyc_mem_tile{$i}_body",  "Tile {$i} — Body",  'oyc_membership', 'textarea' );
	}

	/* ===== SAILING ===== */
	$wp_customize->add_section( 'oyc_sailing', array(
		'title' => __( 'Sailing & Racing', 'orienta-yacht-club' ),
		'panel' => 'oyc_content',
	) );
	$add( 'oyc_sail_kicker',   __( 'Kicker',                    'orienta-yacht-club' ), 'oyc_sailing' );
	$add( 'oyc_sail_headline', __( 'Headline',                  'orienta-yacht-club' ), 'oyc_sailing' );
	$add( 'oyc_sail_body',     __( 'Body (basic HTML allowed)', 'orienta-yacht-club' ), 'oyc_sailing', 'html' );
	$add( 'oyc_sail_bullets',  __( 'Bullets (one per line)',    'orienta-yacht-club' ), 'oyc_sailing', 'textarea' );

	/* ===== FISHING ===== */
	$wp_customize->add_section( 'oyc_fishing', array(
		'title' => __( 'Fishing', 'orienta-yacht-club' ),
		'panel' => 'oyc_content',
	) );
	$add( 'oyc_fish_kicker',   __( 'Kicker',                    'orienta-yacht-club' ), 'oyc_fishing' );
	$add( 'oyc_fish_headline', __( 'Headline',                  'orienta-yacht-club' ), 'oyc_fishing' );
	$add( 'oyc_fish_body',     __( 'Body (basic HTML allowed)', 'orienta-yacht-club' ), 'oyc_fishing', 'html' );
	$add( 'oyc_fish_bullets',  __( 'Bullets (one per line)',    'orienta-yacht-club' ), 'oyc_fishing', 'textarea' );

	/* ===== VISITORS ===== */
	$wp_customize->add_section( 'oyc_visitors', array(
		'title' => __( 'Visitors', 'orienta-yacht-club' ),
		'panel' => 'oyc_content',
	) );
	$add( 'oyc_vis_kicker',   __( 'Kicker',   'orienta-yacht-club' ), 'oyc_visitors' );
	$add( 'oyc_vis_headline', __( 'Headline', 'orienta-yacht-club' ), 'oyc_visitors' );
	$add( 'oyc_vis_lede',     __( 'Lede',     'orienta-yacht-club' ), 'oyc_visitors', 'textarea' );
	for ( $i = 1; $i <= 3; $i++ ) {
		$add( "oyc_vis_tile{$i}_title", "Tile {$i} — Title", 'oyc_visitors' );
		$add( "oyc_vis_tile{$i}_body",  "Tile {$i} — Body",  'oyc_visitors', 'textarea' );
	}

	/* ===== CONTACT ===== */
	$wp_customize->add_section( 'oyc_contact', array(
		'title' => __( 'Contact', 'orienta-yacht-club' ),
		'panel' => 'oyc_content',
	) );
	$add( 'oyc_con_kicker',         __( 'Kicker',                       'orienta-yacht-club' ), 'oyc_contact' );
	$add( 'oyc_con_headline',       __( 'Headline',                     'orienta-yacht-club' ), 'oyc_contact' );
	$add( 'oyc_con_body',           __( 'Body',                         'orienta-yacht-club' ), 'oyc_contact', 'textarea' );
	$add( 'oyc_con_address',        __( 'Address (line breaks allowed)', 'orienta-yacht-club' ), 'oyc_contact', 'textarea' );
	$add( 'oyc_con_phone',          __( 'Phone',                        'orienta-yacht-club' ), 'oyc_contact' );
	$add( 'oyc_con_email',          __( 'Email',                        'orienta-yacht-club' ), 'oyc_contact' );
	$add( 'oyc_con_form_shortcode', __( 'Contact Form Shortcode',       'orienta-yacht-club' ), 'oyc_contact' );
}
add_action( 'customize_register', 'oyc_customize_register' );
