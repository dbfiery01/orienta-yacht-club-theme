<?php
/**
 * OYC Fun — child theme of orienta-yacht-club-theme.
 * Loads the parent's full stylesheet first; the child style.css (enqueued by
 * the parent as "oyc-style") layers a light refresh on top. Keeps the parent's
 * Open Sans fonts — no extra webfonts.
 *
 * @package OYC_Fun
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

// Parent base styles (parent enqueues get_stylesheet_uri(), which in a child
// theme points at THIS theme, so load the parent's CSS explicitly first).
add_action( 'wp_enqueue_scripts', function () {
	wp_enqueue_style(
		'oyc-parent-style',
		get_template_directory_uri() . '/style.css',
		array(),
		'fun-base'
	);
}, 5 );

// Cache-bust the child stylesheet by its file mtime on every deploy.
add_action( 'wp_enqueue_scripts', function () {
	global $wp_styles;
	if ( isset( $wp_styles->registered['oyc-style'] ) ) {
		$f = get_stylesheet_directory() . '/style.css';
		if ( file_exists( $f ) ) {
			$wp_styles->registered['oyc-style']->ver = (string) filemtime( $f );
		}
	}
}, 99 );

// Photo carousel (home-* + header-* images + club videos) below the page title.
// Static, auto-advances every 10s, with left/right arrows.
add_action( 'wp_footer', function () {
	// No photo reel on these pages (per request) — they get the tighter layout.
	if ( is_page( array( 'videos', 'members-area', 'photo-gallery', 'live-video-streaming' ) ) ) {
		return;
	}
	// Collect home-* and header-* photos from the parent theme, plus any
	// header-* in the child theme (e.g. header-racing.jpg). Keyed by filename.
	$sets = array();
	$pdir = get_template_directory() . '/assets/photos/';
	$puri = get_template_directory_uri() . '/assets/photos/';
	foreach ( (array) glob( $pdir . '{home,header}*.{jpg,jpeg,png,webp}', GLOB_BRACE ) as $f ) {
		$sets[ basename( $f ) ] = $puri . basename( $f );
	}
	$cdir = get_stylesheet_directory() . '/assets/photos/';
	$curi = get_stylesheet_directory_uri() . '/assets/photos/';
	if ( $cdir !== $pdir ) {
		foreach ( (array) glob( $cdir . 'header*.{jpg,jpeg,png,webp}', GLOB_BRACE ) as $f ) {
			$sets[ basename( $f ) ] = $curi . basename( $f );
		}
	}
	if ( ! $sets ) { return; }
	ksort( $sets );
	$cells = '';
	foreach ( $sets as $name => $url ) {
		$cells .= '<span class="oyc-carousel__cell" data-img="' . esc_attr( $name ) . '" style="background-image:url(' . esc_url( $url ) . ')"></span>';
	}
	// Club videos (YouTube) — thumbnails link to the videos page, with a play badge.
	$videos = array( '-cYW29F4Qn4', 'zNLmKy_COpE', 'y9SNwmwNHkY', 'T7UzxJq4wQU' );
	$vurl   = esc_url( home_url( '/videos/' ) );
	foreach ( $videos as $vid ) {
		$thumb = 'https://img.youtube.com/vi/' . $vid . '/hqdefault.jpg';
		$cells .= '<a class="oyc-carousel__cell oyc-carousel__cell--video" data-img="video" href="' . $vurl . '" style="background-image:url(' . esc_url( $thumb ) . ')"><span class="oyc-carousel__play" aria-hidden="true"></span></a>';
	}
	// Track duplicated so the auto-advance can loop seamlessly.
	echo '<div class="oyc-carousel">'
		. '<button type="button" class="oyc-carousel__nav oyc-carousel__nav--prev" aria-label="Previous photos">&#8249;</button>'
		. '<div class="oyc-carousel__viewport"><div class="oyc-carousel__track">' . $cells . $cells . $cells . '</div></div>'
		. '<button type="button" class="oyc-carousel__nav oyc-carousel__nav--next" aria-label="Next photos">&#8250;</button>'
		. '</div>';
	echo '<script>(function(){var c=document.querySelector(".oyc-carousel");if(!c)return;var home=document.body.classList.contains("home");if(home){var h=document.querySelector(".site-header,header");if(!h||!h.parentNode){if(c.parentNode)c.parentNode.removeChild(c);return;}h.parentNode.insertBefore(c,h.nextSibling);var mt=function(){c.style.marginTop=(h.offsetHeight||88)+"px";};mt();window.addEventListener("resize",mt);document.body.classList.add("oyc-home-reel-top");}else{var ph=document.querySelector(".page-hero");if(!ph||!ph.parentNode){if(c.parentNode)c.parentNode.removeChild(c);return;}ph.parentNode.insertBefore(c,ph.nextSibling);}var vp=c.querySelector(".oyc-carousel__viewport");var t=c.querySelector(".oyc-carousel__track");var n=t.children.length/3;var i=n;function w(){return t.children[0].offsetWidth;}function center(){return (vp.clientWidth-w())/2;}function mark(){for(var k=0;k<t.children.length;k++){t.children[k].classList.toggle("is-active",(k%n)===(((i%n)+n)%n));}}function set(anim){t.style.transition=anim?"transform .6s ease":"none";t.style.transform="translateX("+(center()-i*w())+"px)";mark();}function next(){i++;set(true);if(i>=2*n){setTimeout(function(){i-=n;set(false);},650);}}function prev(){if(i<=n){i+=n;set(false);void t.offsetWidth;}i--;set(true);}var tm;function arm(){clearInterval(tm);tm=setInterval(next,10000);}var pk=(document.body.className.match(/oyc-page-([a-z0-9-]+)/)||[])[1]||"home";var photos=[];for(var k=0;k<n;k++){if(!t.children[k].classList.contains("oyc-carousel__cell--video"))photos.push(k);}var startPref={membership:"header-facility",boating:"header-fishing"};var want=startPref[pk]||"";var fi=-1;if(want){for(var k=0;k<n;k++){if((t.children[k].getAttribute("data-img")||"").indexOf(want)>-1){fi=k;break;}}}var startIdx;if(fi>-1){startIdx=fi;}else{var hh=0;for(var x=0;x<pk.length;x++){hh=(hh*31+pk.charCodeAt(x))>>>0;}startIdx=photos.length?photos[hh%photos.length]:0;}i=n+startIdx;c.querySelector(".oyc-carousel__nav--next").addEventListener("click",function(){next();arm();});c.querySelector(".oyc-carousel__nav--prev").addEventListener("click",function(){prev();arm();});var photoList=[],imgToIdx={};for(var k=0;k<n;k++){var ch=t.children[k];if(ch.classList.contains("oyc-carousel__cell--video"))continue;var raw=ch.getAttribute("data-bg-image")||ch.style.backgroundImage||"";imgToIdx[ch.getAttribute("data-img")]=photoList.length;photoList.push(raw.indexOf("url(")===0?raw:"url(\""+raw+"\")");}var zwrap=null,zimg=null,zi=0;function showZoom(){if(zimg)zimg.style.backgroundImage=photoList[zi];}function unzoom(){if(!zwrap)return;if(zwrap.parentNode)zwrap.parentNode.removeChild(zwrap);zwrap=null;zimg=null;arm();}function zstep(d){if(!photoList.length)return;zi=((zi+d)%photoList.length+photoList.length)%photoList.length;showZoom();}function openZoom(idx){unzoom();clearInterval(tm);zi=((idx%photoList.length)+photoList.length)%photoList.length;zwrap=document.createElement("div");zwrap.className="oyc-carousel-backdrop";var pv=document.createElement("button");pv.type="button";pv.className="oyc-zoom-nav oyc-zoom-prev";pv.setAttribute("aria-label","Previous photo");pv.innerHTML="&#8249;";var nx=document.createElement("button");nx.type="button";nx.className="oyc-zoom-nav oyc-zoom-next";nx.setAttribute("aria-label","Next photo");nx.innerHTML="&#8250;";zimg=document.createElement("div");zimg.className="oyc-carousel-zoom";pv.addEventListener("click",function(e){e.stopPropagation();zstep(-1);});nx.addEventListener("click",function(e){e.stopPropagation();zstep(1);});zwrap.appendChild(pv);zwrap.appendChild(zimg);zwrap.appendChild(nx);zwrap.addEventListener("click",unzoom);document.body.appendChild(zwrap);showZoom();}[].forEach.call(t.children,function(cell){if(cell.classList.contains("oyc-carousel__cell--video"))return;cell.addEventListener("click",function(e){e.preventDefault();e.stopPropagation();openZoom(imgToIdx[cell.getAttribute("data-img")]||0);});});document.addEventListener("keydown",function(e){if(!zwrap)return;if(e.key==="Escape")unzoom();else if(e.key==="ArrowLeft")zstep(-1);else if(e.key==="ArrowRight")zstep(1);});set(false);window.addEventListener("resize",function(){set(false);});arm();})();</script>';
} );
