<?php
/**
 * Fleet Roster PDF, built on request from the Fleet Roster directory (the
 * `oyc_fleet_roster` option that Upload Roster replaces). Nothing is written to
 * disk: the members-only /fleet-roster-pdf/ link (inc/fleet-roster.php) streams
 * the result, so the PDF always matches the Fleet Roster page.
 *
 * A small self-contained PDF writer: Helvetica / Helvetica-Bold (built into
 * every PDF reader, so no font files), WinAnsi text, one table.
 *
 * @package Orienta_Yacht_Club
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Helvetica glyph widths (1/1000 em) for ASCII 32..126.
 *
 * @param bool $bold Bold face.
 * @return int[]
 */
function oyc_rpdf_widths( $bold ) {
	static $w = array();
	$k = $bold ? 'b' : 'r';
	if ( isset( $w[ $k ] ) ) {
		return $w[ $k ];
	}
	$reg  = '278,278,355,556,556,889,667,191,333,333,389,584,278,333,278,278,556,556,556,556,556,556,556,556,556,556,278,278,584,584,584,556,1015,667,667,722,722,667,611,778,722,278,500,667,556,833,722,778,667,778,722,667,611,722,667,944,667,667,611,278,278,278,469,556,333,556,556,500,556,556,278,556,556,222,222,500,222,833,556,556,556,556,333,500,278,556,500,722,500,500,500,334,260,334,584';
	$bld  = '278,333,474,556,556,889,722,238,333,333,389,584,278,333,278,278,556,556,556,556,556,556,556,556,556,556,333,333,584,584,584,611,975,722,722,722,722,667,611,778,722,278,556,722,611,833,722,778,667,778,722,667,611,722,667,944,667,667,611,333,278,333,584,556,333,556,611,556,611,556,333,611,611,278,278,556,278,889,611,611,611,611,389,556,333,611,556,778,556,556,500,389,280,389,584';
	$vals = array_map( 'intval', explode( ',', $bold ? $bld : $reg ) );
	$w[ $k ] = array_combine( range( 32, 126 ), $vals );
	return $w[ $k ];
}

/** UTF-8 to the PDF's WinAnsi (Windows-1252) bytes. */
function oyc_rpdf_enc( $s ) {
	$s = str_replace( array( "\r", "\n", "\t" ), ' ', (string) $s );
	if ( function_exists( 'iconv' ) ) {
		$out = @iconv( 'UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $s );
		if ( false !== $out ) {
			return $out;
		}
	}
	return preg_replace( '/[^\x20-\x7E]/', '?', $s );
}

/** Width in points of WinAnsi text. */
function oyc_rpdf_textw( $s, $size, $bold = false ) {
	$w = oyc_rpdf_widths( $bold );
	$t = 0;
	$n = strlen( $s );
	for ( $i = 0; $i < $n; $i++ ) {
		$c  = ord( $s[ $i ] );
		$t += isset( $w[ $c ] ) ? $w[ $c ] : 556;
	}
	return $t * $size / 1000;
}

/**
 * Wrap WinAnsi text to a width; long words are broken.
 *
 * @return string[]
 */
function oyc_rpdf_wrap( $s, $width, $size, $bold = false ) {
	$s = trim( preg_replace( '/ +/', ' ', $s ) );
	if ( '' === $s ) {
		return array();
	}
	$lines = array();
	$line  = '';
	foreach ( explode( ' ', $s ) as $word ) {
		$try = '' === $line ? $word : $line . ' ' . $word;
		if ( oyc_rpdf_textw( $try, $size, $bold ) <= $width ) {
			$line = $try;
			continue;
		}
		if ( '' !== $line ) {
			$lines[] = $line;
			$line    = '';
		}
		// Break a word that is wider than the column on its own; an email
		// address breaks before its @ first.
		$at = strpos( $word, '@' );
		if ( $at && oyc_rpdf_textw( $word, $size, $bold ) > $width && oyc_rpdf_textw( substr( $word, 0, $at ), $size, $bold ) <= $width ) {
			$lines[] = substr( $word, 0, $at );
			$word    = substr( $word, $at );
		}
		while ( oyc_rpdf_textw( $word, $size, $bold ) > $width && strlen( $word ) > 1 ) {
			$cut = strlen( $word ) - 1;
			while ( $cut > 1 && oyc_rpdf_textw( substr( $word, 0, $cut ), $size, $bold ) > $width ) {
				$cut--;
			}
			$lines[] = substr( $word, 0, $cut );
			$word    = substr( $word, $cut );
		}
		$line = $word;
	}
	if ( '' !== $line ) {
		$lines[] = $line;
	}
	return $lines;
}

/** PDF text operator for WinAnsi text at x,y. */
function oyc_rpdf_text( $x, $y, $s, $size, $bold = false ) {
	$s = str_replace( array( '\\', '(', ')' ), array( '\\\\', '\\(', '\\)' ), $s );
	return sprintf( "BT /%s %.2F Tf %.2F %.2F Td (%s) Tj ET\n", $bold ? 'F2' : 'F1', $size, $x, $y, $s );
}

/**
 * One table row per directory entry, as display text.
 *
 * @param array $roster Fleet Roster directory entries.
 * @return array[] Each: [ member lines, boat, phones, email, address ].
 */
function oyc_rpdf_rows( $roster ) {
	$pc   = function ( $s, $k = 'name' ) {
		return function_exists( 'oyc_proper_case' ) ? oyc_proper_case( $s, $k ) : $s;
	};
	$rows = array();
	foreach ( $roster as $m ) {
		if ( ! is_array( $m ) ) {
			continue;
		}
		$g      = fn( $k ) => isset( $m[ $k ] ) && ! is_array( $m[ $k ] ) ? trim( (string) $m[ $k ] ) : '';
		$name   = $pc( $g( 'name' ) );
		$spouse = $pc( $g( 'spouse' ) );
		$city   = $pc( $g( 'city' ), 'street' );
		$cityln = trim( $city . ( $g( 'state' ) ? ', ' . $g( 'state' ) : '' ) . ( $g( 'zip' ) ? ' ' . $g( 'zip' ) : '' ), ', ' );
		$street = $pc( str_replace( array( "\r\n", "\n" ), ', ', $g( 'street' ) ), 'street' );
		$phones = isset( $m['phones'] ) && is_array( $m['phones'] ) ? array_filter( array_map( 'trim', $m['phones'] ), 'strlen' ) : array();
		$rows[] = array(
			'name'     => $name,
			'spouse'   => $spouse,
			'assoc'    => 'Associate' === $g( 'category' ),
			'boat'     => trim( $g( 'boat' ) . ' ' . $g( 'boatLen' ) . ' ' . $g( 'boatType' ) ),
			'phones'   => array_values( $phones ),
			'email'    => $g( 'email' ),
			'street'   => $street,
			'cityln'   => $cityln,
		);
	}
	return $rows;
}

/**
 * Build the Fleet Roster PDF.
 *
 * @param array  $roster  Fleet Roster directory entries.
 * @param string $updated Date line, e.g. "November 22, 2026" ('' to leave out).
 * @return string PDF bytes.
 */
function oyc_roster_pdf_build( $roster, $updated = '' ) {
	$pw = 792; $ph = 612; $mx = 36; // Landscape US Letter, half-inch margins.
	$fs = 8.5; $lh = 10.5; $pad = 4;
	$cols = array(
		array( 'Member', 170 ),
		array( 'Boat', 140 ),
		array( 'Phone', 85 ),
		array( 'Email', 185 ),
		array( 'Address', 140 ),
	);
	$top    = $ph - $mx;
	$bottom = $mx + 22; // Room for the footer.
	$rows   = oyc_rpdf_rows( $roster );
	$pages  = array();
	$cur    = '';
	$y      = $top;

	$header = function () use ( &$cur, &$y, $cols, $mx, $fs, $lh, $pad ) {
		$h   = $lh + 2 * $pad;
		$cur .= sprintf( "0.11 0.23 0.37 rg %.2F %.2F %.2F %.2F re f\n", $mx, $y - $h, 720, $h );
		$cur .= "1 1 1 rg\n";
		$x = $mx;
		foreach ( $cols as $c ) {
			$cur .= oyc_rpdf_text( $x + $pad, $y - $pad - $fs + 1, $c[0], $fs, true );
			$x   += $c[1];
		}
		$cur .= "0 0 0 rg\n";
		$y   -= $h;
	};

	// Title block on page 1.
	$cur .= "0.11 0.23 0.37 rg\n";
	$cur .= oyc_rpdf_text( $mx, $y - 18, oyc_rpdf_enc( 'Orienta Yacht Club Fleet Roster' ), 18, true );
	$cur .= "0.35 0.35 0.35 rg\n";
	$sub  = ( $updated ? 'Updated ' . $updated . '  ·  ' : '' ) . count( $rows ) . ' members';
	$cur .= oyc_rpdf_text( $mx, $y - 33, oyc_rpdf_enc( $sub ), 9.5 );
	$cur .= "0 0 0 rg\n";
	$y   -= 46;
	$header();

	foreach ( $rows as $i => $r ) {
		$cells = array();
		$w0    = $cols[0][1] - 2 * $pad;
		$name  = oyc_rpdf_wrap( oyc_rpdf_enc( $r['name'] ), $w0, $fs, true );
		$sub   = trim( ( $r['spouse'] ? $r['spouse'] : '' ) . ( $r['assoc'] ? ( $r['spouse'] ? ' · ' : '' ) . 'Associate' : '' ) );
		$cells[0] = array( 'bold' => $name, 'reg' => oyc_rpdf_wrap( oyc_rpdf_enc( $sub ), $w0, $fs ) );
		$cells[1] = array( 'reg' => oyc_rpdf_wrap( oyc_rpdf_enc( $r['boat'] ), $cols[1][1] - 2 * $pad, $fs ) );
		$pl = array();
		foreach ( $r['phones'] as $p ) {
			$pl = array_merge( $pl, oyc_rpdf_wrap( oyc_rpdf_enc( $p ), $cols[2][1] - 2 * $pad, $fs ) );
		}
		$cells[2] = array( 'reg' => $pl );
		$cells[3] = array( 'reg' => oyc_rpdf_wrap( oyc_rpdf_enc( $r['email'] ), $cols[3][1] - 2 * $pad, $fs ) );
		$al = oyc_rpdf_wrap( oyc_rpdf_enc( $r['street'] ), $cols[4][1] - 2 * $pad, $fs );
		$cells[4] = array( 'reg' => array_merge( $al, oyc_rpdf_wrap( oyc_rpdf_enc( $r['cityln'] ), $cols[4][1] - 2 * $pad, $fs ) ) );

		$n = 1;
		foreach ( $cells as $c ) {
			$n = max( $n, count( $c['bold'] ?? array() ) + count( $c['reg'] ) );
		}
		$h = $n * $lh + 2 * $pad;
		if ( $y - $h < $bottom ) {
			$pages[] = $cur;
			$cur     = '';
			$y       = $top;
			$header();
		}
		if ( 1 === $i % 2 ) {
			$cur .= sprintf( "0.94 0.96 0.98 rg %.2F %.2F %.2F %.2F re f 0 0 0 rg\n", $mx, $y - $h, 720, $h );
		}
		$cur .= sprintf( "0.85 0.87 0.9 RG 0.5 w %.2F %.2F m %.2F %.2F l S\n", $mx, $y - $h, $mx + 720, $y - $h );
		$x = $mx;
		foreach ( $cells as $ci => $c ) {
			$ly = $y - $pad - $fs + 1;
			foreach ( $c['bold'] ?? array() as $t ) {
				$cur .= oyc_rpdf_text( $x + $pad, $ly, $t, $fs, true );
				$ly  -= $lh;
			}
			if ( 0 === $ci && $c['reg'] ) {
				$cur .= "0.35 0.35 0.35 rg\n";
			}
			foreach ( $c['reg'] as $t ) {
				$cur .= oyc_rpdf_text( $x + $pad, $ly, $t, $fs );
				$ly  -= $lh;
			}
			if ( 0 === $ci && $c['reg'] ) {
				$cur .= "0 0 0 rg\n";
			}
			$x += $cols[ $ci ][1];
		}
		$y -= $h;
	}
	if ( ! $rows ) {
		$cur .= oyc_rpdf_text( $mx + $pad, $y - 16, oyc_rpdf_enc( 'The roster has not been loaded yet.' ), 10 );
	}
	$pages[] = $cur;

	// Footers, now that the page count is known.
	$total = count( $pages );
	foreach ( $pages as $p => $c ) {
		$foot  = "0.45 0.45 0.45 rg\n";
		$foot .= oyc_rpdf_text( $mx, $mx, oyc_rpdf_enc( 'Orienta Yacht Club · Members only. Please do not share.' . ( $updated ? ' · Updated ' . $updated : '' ) ), 7.5 );
		$pg    = 'Page ' . ( $p + 1 ) . ' of ' . $total;
		$foot .= oyc_rpdf_text( $pw - $mx - oyc_rpdf_textw( $pg, 7.5 ), $mx, $pg, 7.5 );
		$pages[ $p ] = $c . $foot;
	}

	// Assemble: 1 catalog, 2 pages, 3-4 fonts, 5 info, then page/content pairs.
	$objs  = array();
	$kids  = array();
	foreach ( $pages as $p => $c ) {
		$po = 6 + 2 * $p;
		$kids[] = $po . ' 0 R';
		$data   = function_exists( 'gzcompress' ) ? gzcompress( $c ) : $c;
		$filter = function_exists( 'gzcompress' ) ? ' /Filter /FlateDecode' : '';
		$objs[ $po ]     = sprintf( '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %d %d] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents %d 0 R >>', $pw, $ph, $po + 1 );
		$objs[ $po + 1 ] = '<< /Length ' . strlen( $data ) . $filter . " >>\nstream\n" . $data . "\nendstream";
	}
	$objs[1] = '<< /Type /Catalog /Pages 2 0 R >>';
	$objs[2] = '<< /Type /Pages /Kids [' . implode( ' ', $kids ) . '] /Count ' . $total . ' >>';
	$objs[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
	$objs[4] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';
	$objs[5] = '<< /Title (Orienta Yacht Club Fleet Roster) /Producer (orientayachtclub.com) /CreationDate (D:' . gmdate( 'YmdHis' ) . "Z) >>";
	ksort( $objs );

	$out  = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
	$offs = array();
	foreach ( $objs as $n => $body ) {
		$offs[ $n ] = strlen( $out );
		$out       .= $n . " 0 obj\n" . $body . "\nendobj\n";
	}
	$xref = strlen( $out );
	$max  = max( array_keys( $objs ) );
	$out .= 'xref' . "\n0 " . ( $max + 1 ) . "\n0000000000 65535 f \n";
	for ( $n = 1; $n <= $max; $n++ ) {
		$out .= sprintf( "%010d 00000 n \n", $offs[ $n ] );
	}
	$out .= 'trailer' . "\n<< /Size " . ( $max + 1 ) . " /Root 1 0 R /Info 5 0 R >>\nstartxref\n" . $xref . "\n%%EOF\n";
	return $out;
}

/**
 * When the Fleet Roster directory was last replaced, as a display date
 * ('' if unknown).
 */
function oyc_roster_updated_label() {
	$ts = (int) get_option( 'oyc_fleet_roster_updated', 0 );
	return $ts ? wp_date( 'F j, Y', $ts ) : '';
}

/**
 * Build the PDF from the current Fleet Roster directory and save it in the
 * deny-all protected uploads folder (only /fleet-roster-pdf/ can serve it).
 * The previous generated copy is removed; the old 2025 edition is not touched.
 *
 * @return bool Saved.
 */
function oyc_roster_pdf_save() {
	try {
		$pdf = oyc_roster_pdf_build( oyc_get_roster(), oyc_roster_updated_label() );
		$up  = wp_upload_dir();
		$dir = $up['basedir'] . '/oyc-protected';
		if ( ! file_exists( $dir ) ) {
			wp_mkdir_p( $dir );
		}
		if ( ! file_exists( $dir . '/.htaccess' ) ) {
			file_put_contents( $dir . '/.htaccess', "<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nOrder allow,deny\nDeny from all\n</IfModule>\n" );
			file_put_contents( $dir . '/index.html', '' );
		}
		$dest = $dir . '/fleet-roster-' . wp_generate_password( 16, false, false ) . '.pdf';
		if ( false === file_put_contents( $dest, $pdf ) ) {
			return false;
		}
		$old = (string) get_option( 'oyc_roster_pdf_generated', '' );
		update_option( 'oyc_roster_pdf_generated', $dest, false );
		if ( $old && $old !== $dest && file_exists( $old ) && 0 === strpos( basename( $old ), 'fleet-roster-' ) ) {
			@unlink( $old );
		}
		return true;
	} catch ( \Throwable $e ) {
		return false;
	}
}
