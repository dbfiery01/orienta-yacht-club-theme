<?php
/**
 * Template Name: Wind
 *
 * Thin wrapper for the reusable Wind & Radar map component
 * (oyc_wind_map_html() in inc/wind-map.php) — the full Leaflet wind / radar /
 * temp / precip / waves map, self-contained and CSS-scoped under #oycwm so the
 * same component also embeds as a card on /weather/ (no iframe). Add ?card=1 to
 * hide the standalone top bar & disclaimer.
 *
 * Auto-renders for a Page with slug "wind" (page-{slug} hierarchy),
 * or assign this template to any Page.
 *
 * @package Orienta_Yacht_Club
 */

if ( ! headers_sent() ) { nocache_headers(); }
$oyc_embed = isset( $_GET['card'] ); // standalone by default; ?card=1 = embedded look
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Wind &amp; Radar — Mamaroneck Harbor · Orienta Yacht Club</title>
<style>html,body{margin:0;padding:0;background:#eef3f8}</style>
</head>
<body>
<?php echo oyc_wind_map_html( $oyc_embed ); ?>
</body>
</html>
