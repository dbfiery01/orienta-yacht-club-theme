<?php
/**
 * Fleet Roster PDF, built on request from the Fleet Roster directory (the
 * `oyc_fleet_roster` option that Upload Roster replaces). Nothing is written to
 * disk: the members-only /fleet-roster-pdf/ link (inc/fleet-roster.php) streams
 * the result, so the PDF always matches the Fleet Roster page.
 *
 * Layout: the club's Fleet Roster booklet (5.5 x 8.5 in). The fixed pages
 * (cover, club info, officers, committees, past commodores, dock assignments,
 * fees, work dock rules, memorials, notes) are copied from
 * assets/fleet-roster/booklet-static.json, made from the hand-made booklet by
 * tools/build-roster-booklet.py. The fleet list and the Regular and Associate
 * member pages are drawn here from the roster, in the booklet's fonts and
 * positions (TeX Gyre Bonum, Times, Helvetica). Page numbers and the cover's
 * Updated date are redrawn.
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

/** PDF text operator for WinAnsi text at x,y. */
function oyc_rpdf_text( $x, $y, $s, $size, $bold = false, $font = '' ) {
	$s = str_replace( array( '\\', '(', ')' ), array( '\\\\', '\\(', '\\)' ), $s );
	return sprintf( "BT /%s %.2F Tf %.2F %.2F Td (%s) Tj ET\n", $font ? $font : ( $bold ? 'F2' : 'F1' ), $size, $x, $y, $s );
}

/**
 * Booklet font (TeX Gyre Bonum) metrics and a text writer for it.
 */
class OYC_Rpdf_Bonum {
	public $m;        // face => metrics.
	public $used = array(); // face => [ gid => unicode ].

	public function __construct( $metrics ) {
		$this->m = $metrics;
	}

	/** Glyph ids for UTF-8 text in a face. */
	public function gids( $face, $s ) {
		$out  = array();
		$cmap = $this->m[ $face ]['cmap'];
		$s    = str_replace( array( "\r", "\n", "\t" ), ' ', (string) $s );
		foreach ( preg_split( '//u', $s, -1, PREG_SPLIT_NO_EMPTY ) as $ch ) {
			$u = mb_ord( $ch, 'UTF-8' );
			$g = isset( $cmap[ (string) $u ] ) ? $cmap[ (string) $u ] : ( isset( $cmap['63'] ) ? $cmap['63'] : 0 );
			$this->used[ $face ][ $g ] = isset( $cmap[ (string) $u ] ) ? $u : 63;
			$out[] = $g;
		}
		return $out;
	}

	/** Width in points. */
	public function width( $face, $s, $size ) {
		$w = 0;
		foreach ( $this->gids( $face, $s ) as $g ) {
			$w += $this->m[ $face ]['widths'][ $g ] ?? 500;
		}
		return $w * $size / 1000;
	}

	/**
	 * PDF ops to draw text; squeezed horizontally if wider than $maxw.
	 *
	 * @return array{0:string,1:float} Ops and drawn width.
	 */
	public function text( $face, $x, $y, $s, $size, $maxw = 0 ) {
		if ( '' === trim( (string) $s ) ) {
			return array( '', 0 );
		}
		$hex = '';
		foreach ( $this->gids( $face, $s ) as $g ) {
			$hex .= sprintf( '%04X', $g );
		}
		$w  = $this->width( $face, $s, $size );
		$tz = ( $maxw > 0 && $w > $maxw ) ? max( 70, floor( 100 * $maxw / $w ) ) : 100;
		$id = array( 'regular' => 'B1', 'bold' => 'B2', 'italic' => 'B3' )[ $face ];
		return array( sprintf( "BT /%s %.2F Tf %d Tz %.2F %.2F Td <%s> Tj ET\n", $id, $size, $tz, $x, $y, $hex ), $w * $tz / 100 );
	}
}

/**
 * Roster entries as booklet records.
 *
 * @param array $roster Fleet Roster directory entries.
 * @return array[]
 */
function oyc_rpdf_members( $roster ) {
	$pc  = function ( $s, $k = 'name' ) {
		return function_exists( 'oyc_proper_case' ) ? oyc_proper_case( $s, $k ) : $s;
	};
	$out = array();
	foreach ( $roster as $m ) {
		if ( ! is_array( $m ) ) {
			continue;
		}
		$g      = fn( $k ) => isset( $m[ $k ] ) && ! is_array( $m[ $k ] ) ? trim( (string) $m[ $k ] ) : '';
		if ( function_exists( 'oyc_roster_inactive_types' ) && in_array( strtolower( $g( 'category' ) ), oyc_roster_inactive_types(), true ) ) {
			continue; // e.g. marked Deceased on the profile since the last upload.
		}
		$city   = $pc( $g( 'city' ), 'street' );
		$phones = isset( $m['phones'] ) && is_array( $m['phones'] ) ? array_values( array_filter( array_map( 'trim', $m['phones'] ), 'strlen' ) ) : array();
		$out[]  = array(
			'name'    => $pc( $g( 'name' ) ),
			'spouse'  => $pc( $g( 'spouse' ) ),
			'assoc'   => 'associate' === strtolower( $g( 'category' ) ),
			'street'  => $pc( trim( preg_replace( '/\s*[\r\n]+\s*/', ' ', $g( 'street' ) ) ), 'street' ),
			'cityln'  => trim( $city . ( $g( 'state' ) ? ', ' . $g( 'state' ) : '' ) . ( $g( 'zip' ) ? ' ' . $g( 'zip' ) : '' ), ', ' ),
			'email'   => $g( 'email' ),
			'phones'  => $phones,
			'since'   => $g( 'since' ),
			'boat'    => $g( 'boat' ),
			'size'    => rtrim( $g( 'boatLen' ), "'" ),
			'type'    => $g( 'boatType' ),
		);
	}
	return $out;
}

/** Load a theme asset under assets/fleet-roster/. */
function oyc_rpdf_asset( $file ) {
	$path = get_template_directory() . '/assets/fleet-roster/' . $file;
	return file_exists( $path ) ? file_get_contents( $path ) : false;
}

/**
 * Build the Fleet Roster booklet PDF.
 *
 * @param array $roster  Fleet Roster directory entries.
 * @param int   $updated Timestamp of the roster upload (0 = today).
 * @return string PDF bytes.
 */
function oyc_roster_pdf_build( $roster, $updated = 0 ) {
	$static  = json_decode( (string) oyc_rpdf_asset( 'booklet-static.json' ), true );
	$metrics = json_decode( (string) oyc_rpdf_asset( 'fonts.json' ), true );
	if ( ! is_array( $static ) || ! is_array( $metrics ) || count( $static['pages'] ) < 10 ) {
		throw new RuntimeException( 'Fleet Roster booklet assets missing' );
	}
	$updated = $updated ? $updated : time();
	$bon     = new OYC_Rpdf_Bonum( $metrics );
	$members = oyc_rpdf_members( $roster );
	$times   = function ( $font, $x, $y, $s, $size ) {
		return oyc_rpdf_text( $x, $y, oyc_rpdf_enc( $s ), $size, false, $font );
	};

	// ── Fleet list: Regular members' boats A–Z (ties by member last name, the
	// roster's order). Associates' boats are not in the club fleet list.
	$fleet = array();
	foreach ( $members as $i => $m ) {
		if ( '' !== $m['boat'] && ! $m['assoc'] ) {
			$fleet[] = array( $m['boat'], $m['name'], $i );
		}
	}
	usort( $fleet, fn( $a, $b ) => strcasecmp( $a[0], $b[0] ) ?: $a[2] - $b[2] );
	$fleet_pages = array();
	foreach ( array_chunk( $fleet, 42 ) ?: array( array() ) as $pi => $chunk ) {
		$c  = $times( 'T2', 122.6, 567.4, 'ORIENTA YACHT CLUB FLEET', 10 );
		if ( $pi > 0 ) {
			$c .= $times( 'T1', 177.2, 557.4, 'Continued', 10 );
		}
		$c .= $times( 'T2', 36, 545.9, 'NAME', 10 ) . $times( 'T2', 227, 545.9, 'OWNER', 10 );
		$y  = 527.4;
		foreach ( $chunk as $row ) {
			$c .= oyc_rpdf_text( 36, $y, oyc_rpdf_enc( $row[0] ), 10 );
			$c .= oyc_rpdf_text( 204.6, $y, oyc_rpdf_enc( $row[1] ), 10 );
			$y -= 12;
		}
		$fleet_pages[] = $c;
	}

	// ── Regular members: 8 on the first page (under the heading), 9 after.
	$regular = array_values( array_filter( $members, fn( $m ) => ! $m['assoc'] ) );
	$reg_pages = array();
	$i = 0;
	$n = count( $regular );
	do {
		$first = ! $reg_pages;
		$c     = $first ? $bon->text( 'bold', 76.7, 563.8, 'REGULAR OYC MEMBERSHIP ROSTER', 12 )[0] : '';
		$y     = $first ? 506.1 : 565.8;
		for ( $k = 0; $k < ( $first ? 8 : 9 ) && $i < $n; $k++, $i++ ) {
			$m = $regular[ $i ];
			list( $ops, $w ) = $bon->text( 'bold', 36, $y, $m['name'], 10, 166 );
			$c .= $ops;
			if ( $m['spouse'] ) {
				$c .= $bon->text( 'regular', 36 + $w + 3.2, $y, '(' . $m['spouse'] . ')', 10, max( 20, 166 - $w - 3.2 ) )[0];
			}
			$c .= $bon->text( 'regular', 36, $y - 12, $m['street'], 10, 166 )[0];
			$c .= $bon->text( 'regular', 36, $y - 24, $m['cityln'], 10, 166 )[0];
			if ( $m['boat'] ) {
				// Booklet spacing: boat (italic), size and type 6.4 pt apart.
				list( $ops, $w ) = $bon->text( 'italic', 36, $y - 36, $m['boat'], 10, 110 );
				$c .= $ops;
				$x  = 36 + $w + 6.4;
			} else {
				$x = 36;
			}
			if ( '' !== $m['size'] ) {
				list( $ops, $w ) = $bon->text( 'regular', $x, $y - 36, $m['size'], 10 );
				$c .= $ops;
				$x += $w + 6.4;
			}
			if ( '' !== $m['type'] ) {
				$c .= $bon->text( 'regular', $x, $y - 36, $m['type'], 10, max( 20, 202 - $x ) )[0];
			}
			$c .= $bon->text( 'regular', 206, $y, $m['email'], 10, 156 )[0];
			if ( $m['since'] ) {
				$c .= $bon->text( 'italic', 206, $y - 12, 'Member Since ' . $m['since'], 10, 156 )[0];
			}
			$c .= $bon->text( 'regular', 206, $y - 24, $m['phones'][0] ?? '', 10, 156 )[0];
			$c .= $bon->text( 'regular', 206, $y - 36, $m['phones'][1] ?? ( isset( $m['phones'][0] ) ? 'No Alt #' : '' ), 10, 156 )[0];
			$y -= 60;
		}
		$reg_pages[] = $c;
	} while ( $i < $n );

	// ── Associate members: two columns of 7, left column first.
	$assoc = array_values( array_filter( $members, fn( $m ) => $m['assoc'] ) );
	$as_pages = array();
	foreach ( array_chunk( $assoc, 14 ) ?: array( array() ) as $pi => $chunk ) {
		$c = $times( 'T2', 130.8, 566.6, 'ASSOCIATE MEMBERS', 12 );
		if ( $pi > 0 ) {
			$c .= $times( 'T1', 173, 556.6, 'Continued', 12 );
		}
		foreach ( $chunk as $k => $m ) {
			$x  = $k < 7 ? 36 : 206;
			$y  = 529.8 - ( $k % 7 ) * 72;
			$c .= $bon->text( 'bold', $x, $y, $m['name'], 10, 164 )[0];
			$c .= $bon->text( 'regular', $x, $y - 12, $m['street'], 10, 164 )[0];
			$c .= $bon->text( 'regular', $x, $y - 24, $m['cityln'], 10, 164 )[0];
			$c .= $bon->text( 'regular', $x, $y - 36, $m['email'], 10, 164 )[0];
			$c .= $bon->text( 'regular', $x, $y - 48, $m['phones'][0] ?? '', 10, 164 )[0];
		}
		$as_pages[] = $c;
	}

	// ── Page order: fixed 1-5, fleet, fixed dock + fees, Regular, Associate, fixed back pages.
	$sp    = $static['pages'];
	$order = array();
	foreach ( array_slice( $sp, 0, 5 ) as $k => $p ) {
		$order[] = array( 'static' => $k );
	}
	foreach ( $fleet_pages as $c ) {
		$order[] = array( 'gen' => $c );
	}
	$order[] = array( 'static' => 5 );
	$order[] = array( 'static' => 6 );
	foreach ( array_merge( $reg_pages, $as_pages ) as $c ) {
		$order[] = array( 'gen' => $c );
	}
	// Keep the back pages on the same sides of the spread as the printed
	// booklet (Work Dock Rules on a left-hand, even page): pad with an empty
	// "Associate Members, Continued" page, as the hand-made booklet does.
	if ( 0 === count( $order ) % 2 ) {
		$order[] = array( 'gen' => $times( 'T2', 130.8, 566.6, 'ASSOCIATE MEMBERS', 12 ) . $times( 'T1', 173, 556.6, 'Continued', 12 ) );
	}
	for ( $k = 7; $k < count( $sp ); $k++ ) {
		$order[] = array( 'static' => $k );
	}

	// ── Objects. 1 catalog, 2 pages, 3 info, 4 Helvetica, 5 Times-Roman,
	// 6 Times-Bold, 7.. Bonum fonts (5 objects each), then the copied objects
	// (renumbered), then the forms and pages.
	$objs   = array();
	$objs[4] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
	$objs[5] = '<< /Type /Font /Subtype /Type1 /BaseFont /Times-Roman /Encoding /WinAnsiEncoding >>';
	$objs[6] = '<< /Type /Font /Subtype /Type1 /BaseFont /Times-Bold /Encoding /WinAnsiEncoding >>';
	$base    = 22;
	$shift   = function ( $txt ) use ( $base ) {
		return preg_replace_callback( '/\b(\d+) 0 R\b/', fn( $mm ) => ( (int) $mm[1] + $base ) . ' 0 R', $txt );
	};
	$max = 0;
	foreach ( $static['objects'] as $num => $o ) {
		$id  = (int) $num + $base;
		$max = max( $max, $id );
		$objs[ $id ] = isset( $o['stream'] )
			? $shift( $o['dict'] ) . "\nstream\n" . base64_decode( $o['stream'] ) . "\nendstream"
			: $shift( $o['dict'] );
	}
	$next = $max + 1;
	$fonts = '/F1 4 0 R /T1 5 0 R /T2 6 0 R /B1 7 0 R /B2 12 0 R /B3 17 0 R';
	$kids  = array();
	$total = count( $order );
	foreach ( $order as $pn => $pg ) {
		$num = $pn + 1;
		$xo  = '';
		$c   = '';
		if ( isset( $pg['static'] ) ) {
			$s    = $sp[ $pg['static'] ];
			$form = $next++;
			$data = base64_decode( $s['stream'] );
			$objs[ $form ] = sprintf( '<< /Type /XObject /Subtype /Form /BBox [%s] /Resources %s /Filter /FlateDecode /Length %d >>', implode( ' ', $s['bbox'] ), $shift( $s['resources'] ), strlen( $data ) ) . "\nstream\n" . $data . "\nendstream";
			$xo = '/XObject << /P ' . $form . ' 0 R >>';
			$c  = "q /P Do Q\n";
			if ( 0 === $pn ) {
				// Cover: replace the edition date with this upload's date.
				$c .= "1 1 1 rg 228 41 130 16 re f 0.137 0.122 0.125 rg\n";
				$x  = 232.4;
				$last = ' ';
				foreach ( preg_split( '//u', strtoupper( wp_date( 'F j, Y', $updated ) ), -1, PREG_SPLIT_NO_EMPTY ) as $ci => $ch ) {
					$prev = $last;
					$big  = ' ' === $prev || ! ctype_alpha( $ch );
					$size = $big ? 12 : 9.2;
					$enc  = oyc_rpdf_enc( $ch );
					$c   .= oyc_rpdf_text( $x, 45.7, $enc, $size );
					$x   += oyc_rpdf_textw( $enc, $size );
					$last = $ch;
				}
			} else {
				$c .= "1 1 1 rg 184 22 28 13 re f 0 0 0 rg\n";
			}
		} else {
			$c = $pg['gen'];
		}
		if ( $pn > 0 ) {
			$c .= oyc_rpdf_text( 198 - 2 * strlen( (string) $num ), 25.7, (string) $num, 8, false, 'T1' );
		}
		$cid  = $next++;
		$pid  = $next++;
		$data = function_exists( 'gzcompress' ) ? gzcompress( $c ) : $c;
		$objs[ $cid ] = '<< /Length ' . strlen( $data ) . ( function_exists( 'gzcompress' ) ? ' /Filter /FlateDecode' : '' ) . " >>\nstream\n" . $data . "\nendstream";
		$objs[ $pid ] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 396 612] /Resources << /Font << ' . $fonts . ' >> ' . $xo . ' >> /Contents ' . $cid . ' 0 R >>';
		$kids[] = $pid . ' 0 R';
	}

	// Bonum fonts (after the pages, so every glyph used is known).
	foreach ( array( 'regular' => 7, 'bold' => 12, 'italic' => 17 ) as $face => $o ) {
		$fm   = $metrics[ $face ];
		$file = (string) oyc_rpdf_asset( $fm['file'] );
		$name = 'TeXGyreBonum-' . ucfirst( $face );
		$objs[ $o ]     = '<< /Type /Font /Subtype /Type0 /BaseFont /' . $name . ' /Encoding /Identity-H /DescendantFonts [' . ( $o + 1 ) . ' 0 R] /ToUnicode ' . ( $o + 4 ) . ' 0 R >>';
		$objs[ $o + 1 ] = '<< /Type /Font /Subtype /CIDFontType0 /BaseFont /' . $name . ' /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> /FontDescriptor ' . ( $o + 2 ) . ' 0 R /DW 500 /W [0 [' . implode( ' ', $fm['widths'] ) . ']] >>';
		$objs[ $o + 2 ] = sprintf(
			'<< /Type /FontDescriptor /FontName /%s /Flags %d /FontBBox [%s] /ItalicAngle %s /Ascent %d /Descent %d /CapHeight %d /StemV 80 /FontFile3 %d 0 R >>',
			$name, 'italic' === $face ? 98 : 34, implode( ' ', $fm['bbox'] ), $fm['italicAngle'], $fm['ascent'], $fm['descent'], $fm['capHeight'], $o + 3
		);
		$objs[ $o + 3 ] = '<< /Subtype /OpenType /Length ' . strlen( $file ) . " >>\nstream\n" . $file . "\nendstream";
		$map = '';
		$cnt = 0;
		$chunks = array();
		foreach ( $bon->used[ $face ] ?? array() as $g => $u ) {
			$chunks[] = sprintf( '<%04X> <%04X>', $g, $u );
		}
		foreach ( array_chunk( $chunks, 100 ) as $ch ) {
			$map .= count( $ch ) . " beginbfchar\n" . implode( "\n", $ch ) . "\nendbfchar\n";
		}
		$cmap = "/CIDInit /ProcSet findresource begin\n12 dict begin\nbegincmap\n/CIDSystemInfo << /Registry (Adobe) /Ordering (UCS) /Supplement 0 >> def\n/CMapName /Adobe-Identity-UCS def\n/CMapType 2 def\n1 begincodespacerange\n<0000> <FFFF>\nendcodespacerange\n" . $map . "endcmap\nCMapName currentdict /CMap defineresource pop\nend\nend";
		$objs[ $o + 4 ] = '<< /Length ' . strlen( $cmap ) . " >>\nstream\n" . $cmap . "\nendstream";
	}
	$objs[1] = '<< /Type /Catalog /Pages 2 0 R >>';
	$objs[2] = '<< /Type /Pages /Kids [' . implode( ' ', $kids ) . '] /Count ' . $total . ' >>';
	$objs[3] = '<< /Title (OYC Fleet Roster ' . wp_date( 'Y', $updated ) . ') /Author (Orienta Yacht Club) /Producer (orientayachtclub.com) /CreationDate (D:' . gmdate( 'YmdHis' ) . "Z) >>";
	ksort( $objs );

	$out  = "%PDF-1.7\n%\xE2\xE3\xCF\xD3\n";
	$offs = array();
	foreach ( $objs as $num => $body ) {
		$offs[ $num ] = strlen( $out );
		$out         .= $num . " 0 obj\n" . $body . "\nendobj\n";
	}
	$xref = strlen( $out );
	$max  = max( array_keys( $objs ) );
	$out .= "xref\n0 " . ( $max + 1 ) . "\n0000000000 65535 f \n";
	for ( $num = 1; $num <= $max; $num++ ) {
		$out .= isset( $offs[ $num ] ) ? sprintf( "%010d 00000 n \n", $offs[ $num ] ) : "0000000000 65535 f \n";
	}
	$out .= "trailer\n<< /Size " . ( $max + 1 ) . " /Root 1 0 R /Info 3 0 R >>\nstartxref\n" . $xref . "\n%%EOF\n";
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
		$pdf = oyc_roster_pdf_build( oyc_get_roster(), (int) get_option( 'oyc_fleet_roster_updated', 0 ) );
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
