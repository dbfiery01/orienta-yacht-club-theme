<?php
/**
 * Orienta Yacht Club — single source of truth for default content.
 *
 * Both the front-end (`oyc_get()`) and the Customizer registration
 * read from this map, so the live site, the Customizer preview,
 * and the post-Publish state always stay in sync.
 *
 * @package Orienta_Yacht_Club
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function oyc_defaults() {
	static $d = null;
	if ( $d !== null ) {
		return $d;
	}

	$d = array(
		/* Hero */
		'oyc_hero_eyebrow'   => 'Founded 1907 · Long Island Sound',
		'oyc_hero_headline'  => 'A storied harbor, a generous welcome.',
		'oyc_hero_lede'      => 'The Orienta Yacht Club has anchored the East Basin of Mamaroneck Harbor for more than a century — a deep, well-protected home for sailors, anglers, and families who love the water.',
		'oyc_hero_cta1_text' => 'Become a Member',
		'oyc_hero_cta1_url'  => '/membership-application/',
		'oyc_hero_cta2_text' => 'Visiting Boaters',
		'oyc_hero_cta2_url'  => '/#visitors',

		/* About */
		'oyc_about_kicker'   => 'About the Club',
		'oyc_about_headline' => 'One of the oldest clubs on the Sound.',
		'oyc_about_p1'       => "Since 1907, Orienta has been a gathering place for boating families on Long Island Sound. Our clubhouse sits at the head of Mamaroneck's East Basin, with a deep-water channel that gives members year-round access to some of the finest cruising grounds on the East Coast.",
		'oyc_about_p2'       => 'Today the club balances heritage with a relaxed, no formal dress code, family-first culture. We are first and foremost a working yacht club — but the dock is just as likely to be lined with kids learning to sail, weekend racers heading out to the start line, or members grilling on the porch.',

		'oyc_fact1_value' => '1907',
		'oyc_fact1_label' => 'Year founded',
		'oyc_fact2_value' => 'East Basin',
		'oyc_fact2_label' => 'Mamaroneck Harbor',
		'oyc_fact3_value' => 'Deep water',
		'oyc_fact3_label' => 'Year-round channel',
		'oyc_fact4_value' => 'Member-run',
		'oyc_fact4_label' => 'Volunteer officers & flag',

		'oyc_card1_title' => 'Clubhouse & Dining',
		'oyc_card1_body'  => 'A classic harbor-front clubhouse with porches over the basin, a working bar, and member dining throughout the season.',
		'oyc_card2_title' => 'Docks & Moorings',
		'oyc_card2_body'  => "Member docks, launch service, and moorings inside the basin's protected waters, with easy access to the Sound.",
		'oyc_card3_title' => 'Heritage',
		'oyc_card3_body'  => 'A continuous flag and burgee tradition stretching back over a century — preserved by the members who sail under it.',

		/* Membership */
		'oyc_mem_kicker'      => 'Membership',
		'oyc_mem_headline'    => 'Join a club built around the water — and the people on it.',
		'oyc_mem_lede'        => "Orienta welcomes new members through a sponsorship process. Whether you sail, fish, or simply love being near the harbor, there's a place for your family at the club.",
		'oyc_mem_tile1_title' => 'Regular',
		'oyc_mem_tile1_body'  => 'Full access to the clubhouse, docks, launch, dining, racing, and social calendar for members and their families.',
		'oyc_mem_tile2_title' => 'Junior & Young Adult',
		'oyc_mem_tile2_body'  => 'Reduced-fee categories that bring the next generation into the club and onto the water.',
		'oyc_mem_tile3_title' => 'Social',
		'oyc_mem_tile3_body'  => 'Categories for members who live farther afield or who want to enjoy the clubhouse and events without a boat.',
		'oyc_mem_tile4_title' => 'Fees',
		'oyc_mem_tile4_body'  => 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation.',

		/* Sailing */
		'oyc_sail_kicker'   => 'Boating',
		'oyc_sail_headline' => "From the Governor's Cup to Mamaroneck Frostbiters.",
		'oyc_sail_body'     => "Orienta has a deep racing tradition on Western Long Island Sound, anchored by the historic <a href=\"https://www.yachtscoring.com/emenu/50084\" target=\"_blank\" rel=\"noopener\"><strong>Governor's Cup</strong></a> regatta — a fun PHRF and IRC navigation event held each summer. Members also race through the <a href=\"https://www.yralis.org/\" target=\"_blank\" rel=\"noopener\">Yacht Racing Association of Long Island Sound (YRALIS)</a>, and right in our back yard the <a href=\"https://www.mamaroneckfrostbite.org/\" target=\"_blank\" rel=\"noopener\">Mamaroneck Frostbiters Association</a> runs one of the largest and longest-standing frostbite programs in America.",
		'oyc_sail_bullets'  => "Governor's Cup — PHRF Spinnaker, Non-Spin & IRC\nYRALIS fleet racing & distance events | https://www.yralis.org\nMamaroneck Frostbiters (fall/winter) | https://www.mamaroneckfrostbite.org\nCruising fleet rendezvous on the Sound",

		/* Fishing */
		'oyc_fish_kicker'   => 'Fishing',
		'oyc_fish_headline' => 'An active angling community.',
		'oyc_fish_body'     => "Mamaroneck's protected basin and quick access to the Sound make Orienta an excellent home port for anglers. The fishing committee runs a calendar of tournaments and informal trips through the season — striped bass, bluefish, fluke, and blackfish all in reach.",
		'oyc_fish_bullets'  => "Member tournaments and weigh-ins\nFamily fishing days\nLocal knowledge from longtime captains",

		/* Visitors */
		'oyc_vis_kicker'      => 'Visitors & Reciprocal Clubs',
		'oyc_vis_headline'    => 'Cruising into Mamaroneck?',
		'oyc_vis_lede'        => 'Members of recognized reciprocal clubs are warmly welcomed at Orienta. The harbor offers protected anchorage, mooring availability through the launch, and a short walk to the village of Mamaroneck.',
		'oyc_vis_tile1_title' => 'Approach',
		'oyc_vis_tile1_body'  => 'Enter Mamaroneck Harbor and bear into the East Basin — Orienta sits at the head of the basin with a deep, well-marked channel.',
		'oyc_vis_tile2_title' => 'Launch & Moorings',
		'oyc_vis_tile2_body'  => "Hail the launch on the club's working channel during operating hours. Reciprocal guests should call ahead to confirm availability.",
		'oyc_vis_tile3_title' => 'Mamaroneck Harbor resources',
		'oyc_vis_tile3_body'  => 'Showers, Wi-Fi, ice, and member dining on a seasonal schedule. Mamaroneck village, train, and provisioning are minutes away.',

		/* Contact */
		'oyc_con_kicker'         => 'Contact',
		'oyc_con_headline'       => 'Get in touch.',
		'oyc_con_body'           => 'For membership inquiries, reciprocal visits, event reservations, or general questions, the club office is the right starting point.',
		'oyc_con_address'        => "Orienta Yacht Club\nMamaroneck Harbor, East Basin\nMamaroneck, NY",
		'oyc_con_phone'          => '(914) 698-9858',
		'oyc_con_email'          => 'office@orientayc.org',
		'oyc_con_form_shortcode' => '',
	);

	return $d;
}
