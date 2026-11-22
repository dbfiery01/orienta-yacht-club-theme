<?php
/**
 * Officer "Upload roster": one club roster file (.xlsx or .csv) fills
 *   - each matched account's card fields (member number, membership type,
 *     dues paid through, member since) and club fields (locker, dinghy rack);
 *   - optionally the members-only Fleet Roster directory (spouse, boat, phones,
 *     address), leaving out Expelled / Deceased / Resigned members.
 *
 * Rows are matched to accounts by Email, then Username, then First + Last name
 * (only when exactly one account has that name). Blank cells never erase saved
 * values. A test run reports everything and saves nothing.
 *
 * PRIVACY: the roster holds member PII. It is parsed from the upload's temp
 * file and stored only in user meta and the `oyc_fleet_roster` option (see
 * inc/fleet-roster.php); nothing is written to disk or the theme.
 *
 * @package Orienta_Yacht_Club
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Officer-editable club fields shown on the Members page: meta key => label.
 */
function oyc_member_club_fields() {
	return array(
		'oyc_locker'      => __( 'Locker', 'orienta-yacht-club' ),
		'oyc_dinghy_rack' => __( 'Dinghy Rack', 'orienta-yacht-club' ),
	);
}

/**
 * Find the website account for a roster member: exact email, then the same
 * Gmail inbox written differently (dan.smith@ vs dansmith@), then a unique
 * first + last name, then a unique last name whose first name is a short form
 * of the other (Dan / Daniel). Returns the user ID or 0.
 *
 * @param string $email Roster email.
 * @param string $first Roster first name.
 * @param string $last  Roster last name.
 * @param bool   $reset Rebuild the account index (after accounts change).
 */
function oyc_roster_match_account( $email, $first, $last, $reset = false ) {
	static $idx = null;
	$norm = fn( $v ) => preg_replace( '/[^a-z0-9]/', '', strtolower( remove_accents( trim( (string) $v ) ) ) );
	if ( $reset || null === $idx ) {
		$idx = array( 'email' => array(), 'key' => array(), 'name' => array(), 'last' => array() );
		foreach ( get_users( array( 'fields' => array( 'ID', 'user_email' ) ) ) as $a ) {
			$id = (int) $a->ID;
			$em = strtolower( trim( (string) $a->user_email ) );
			$fn = $norm( get_user_meta( $id, 'first_name', true ) );
			$ln = $norm( get_user_meta( $id, 'last_name', true ) );
			if ( '' !== $em ) {
				$idx['email'][ $em ] = $id;
				$idx['key'][ function_exists( 'oyc_email_key' ) ? oyc_email_key( $em ) : $em ][] = $id;
			}
			if ( '' !== $ln ) {
				$idx['name'][ $fn . '|' . $ln ][] = $id;
				$idx['last'][ $ln ][]             = array( $id, $fn );
			}
		}
	}
	if ( null === $email ) {
		return 0; // Reset only.
	}
	$em = strtolower( trim( (string) $email ) );
	if ( '' !== $em ) {
		if ( isset( $idx['email'][ $em ] ) ) {
			return $idx['email'][ $em ];
		}
		$ids = $idx['key'][ function_exists( 'oyc_email_key' ) ? oyc_email_key( $em ) : $em ] ?? array();
		if ( 1 === count( $ids ) ) {
			return $ids[0];
		}
	}
	$fn = $norm( $first );
	$ln = $norm( $last );
	if ( '' === $ln || '' === $fn ) {
		return 0;
	}
	$ids = $idx['name'][ $fn . '|' . $ln ] ?? array();
	if ( 1 === count( $ids ) ) {
		return $ids[0];
	}
	if ( $ids ) {
		return 0; // Two accounts with the same name: don't guess.
	}
	$near = array();
	foreach ( $idx['last'][ $ln ] ?? array() as $pair ) {
		list( $id, $afn ) = $pair;
		if ( '' !== $afn && ( 0 === strpos( $afn, $fn ) || 0 === strpos( $fn, $afn ) ) ) {
			$near[] = $id;
		}
	}
	return 1 === count( $near ) ? $near[0] : 0;
}

/**
 * Split a directory "First Last" (or "Last, First") name.
 *
 * @return array{0:string,1:string} First, last.
 */
function oyc_roster_split_name( $name, $last = '' ) {
	$name = trim( (string) $name );
	$last = trim( (string) $last );
	if ( false !== strpos( $name, ',' ) ) {
		list( $l, $f ) = array_map( 'trim', explode( ',', $name, 2 ) );
		return array( $f, $l );
	}
	if ( '' !== $last && strlen( $name ) > strlen( $last ) && 0 === strcasecmp( substr( $name, -strlen( $last ) ), $last ) ) {
		return array( trim( substr( $name, 0, -strlen( $last ) ) ), $last );
	}
	$parts = preg_split( '/\s+/', $name );
	$l     = (string) array_pop( $parts );
	return array( implode( ' ', $parts ), $l );
}

/**
 * Membership types that are left out of the Fleet Roster directory.
 */
function oyc_roster_inactive_types() {
	return array( 'expelled', 'deceased', 'resigned', 'inactive', 'former', 'removed' );
}

/**
 * Normalise a heading cell: lower case, punctuation to spaces.
 */
function oyc_roster_norm_heading( $cell ) {
	$h = strtolower( preg_replace( '/[^a-z0-9#]+/i', ' ', (string) $cell ) );
	return trim( preg_replace( '/\s+/', ' ', $h ) );
}

/**
 * Map a whole heading row to field names: column index => field.
 * Context matters: "Member" is the name when another column holds the number,
 * and "Type" is the boat type when a "Member Type" column exists.
 *
 * @param array $header Heading cells.
 * @return array
 */
function oyc_roster_map_header( $header ) {
	$aliases = array(
		'email'                 => array( 'email', 'e mail', 'email address' ),
		'login'                 => array( 'username', 'user name', 'login', 'user login' ),
		'first'                 => array( 'first', 'first name', 'firstname', 'given name' ),
		'last'                  => array( 'last', 'last name', 'lastname', 'surname', 'family name' ),
		'name'                  => array( 'name', 'full name', 'member name' ),
		'oyc_member_number'     => array( 'member number', 'member no', 'member num', 'member id', 'number', 'no', 'membership number', 'membership no', 'oyc member no', 'oyc member number', 'oyc member', 'oyc no', 'oyc number', 'member #', 'oyc member #', '#' ),
		'oyc_membership_type'   => array( 'membership type', 'member type', 'membership', 'class', 'category' ),
		'oyc_dues_paid_through' => array( 'dues paid through', 'paid through', 'valid through', 'expires', 'expiry', 'expiration', 'dues year', 'season', 'year' ),
		'oyc_member_since'      => array( 'member since', 'joined', 'since', 'year joined' ),
		'oyc_locker'            => array( 'locker', 'locker no', 'locker number', 'locker #' ),
		'oyc_dinghy_rack'       => array( 'dinghy rack', 'dinghy', 'rack', 'dinghy kayak rack' ),
		'spouse'                => array( 'spouse', 'partner', 'spouse partner' ),
		'boat'                  => array( 'boat name', 'boat', 'vessel', 'vessel name' ),
		'boatLen'               => array( 'size', 'length', 'loa', 'boat size', 'boat length' ),
		'boatType'              => array( 'boat type', 'vessel type' ),
		'phone'                 => array( 'phone', 'home phone', 'phone 1', 'mobile', 'cell' ),
		'phone2'                => array( 'alt phone', 'alternate phone', 'phone 2', 'other phone' ),
		'street'                => array( 'billing street', 'street', 'address', 'street address', 'address 1' ),
		'city'                  => array( 'billing city', 'city', 'town' ),
		'state'                 => array( 'billing state', 'state' ),
		'zip'                   => array( 'billing zip', 'zip', 'zip code', 'postal code', 'postcode' ),
	);

	$norm = array_map( 'oyc_roster_norm_heading', (array) $header );
	$cols = array();
	$todo = array(); // ambiguous headings, settled after the first pass.
	foreach ( $norm as $i => $h ) {
		if ( '' === $h ) {
			continue;
		}
		if ( in_array( $h, array( 'member', 'type' ), true ) ) {
			$todo[ $i ] = $h;
			continue;
		}
		foreach ( $aliases as $field => $list ) {
			if ( in_array( $h, $list, true ) && ! in_array( $field, $cols, true ) ) {
				$cols[ $i ] = $field;
				break;
			}
		}
	}
	foreach ( $todo as $i => $h ) {
		if ( 'member' === $h ) {
			$f = in_array( 'oyc_member_number', $cols, true ) ? 'name' : 'oyc_member_number';
		} else {
			$f = in_array( 'oyc_membership_type', $cols, true ) ? 'boatType' : 'oyc_membership_type';
		}
		if ( ! in_array( $f, $cols, true ) ) {
			$cols[ $i ] = $f;
		}
	}
	ksort( $cols );
	return $cols;
}

/**
 * Read every row of an uploaded .xlsx (first worksheet) or .csv file.
 *
 * @param string $path Temp file path.
 * @param string $name Original file name (for the extension).
 * @return array|WP_Error List of rows (arrays of strings, column-indexed).
 */
function oyc_roster_read_rows( $path, $name ) {
	if ( preg_match( '/\.xlsx$/i', (string) $name ) ) {
		return oyc_roster_read_xlsx( $path );
	}
	$fh = fopen( $path, 'r' );
	if ( ! $fh ) {
		return new WP_Error( 'read', __( 'The file could not be read.', 'orienta-yacht-club' ) );
	}
	$rows = array();
	while ( ( $row = fgetcsv( $fh, 0, ',', '"', '\\' ) ) !== false ) {
		$rows[] = array_map( 'strval', (array) $row );
	}
	fclose( $fh );
	if ( isset( $rows[0][0] ) ) {
		$rows[0][0] = preg_replace( '/^\xEF\xBB\xBF/', '', $rows[0][0] ); // Excel BOM
	}
	return $rows;
}

/**
 * Minimal .xlsx reader: shared strings + first worksheet, values only.
 */
function oyc_roster_read_xlsx( $path ) {
	$fail = new WP_Error( 'xlsx', __( 'That Excel file could not be read. Please save it as CSV (File → Save As → CSV) and try again.', 'orienta-yacht-club' ) );
	if ( ! class_exists( 'ZipArchive' ) || ! function_exists( 'simplexml_load_string' ) ) {
		return $fail;
	}
	$zip = new ZipArchive();
	if ( true !== $zip->open( $path ) ) {
		return $fail;
	}
	$xml = function ( $entry ) use ( $zip ) {
		$s = $zip->getFromName( $entry );
		return false === $s ? null : simplexml_load_string( $s, 'SimpleXMLElement', LIBXML_NONET );
	};

	// Shared strings.
	$strings = array();
	$ss      = $xml( 'xl/sharedStrings.xml' );
	if ( $ss ) {
		foreach ( $ss->si as $si ) {
			if ( isset( $si->t ) ) {
				$strings[] = (string) $si->t;
			} else {
				$t = '';
				foreach ( $si->r as $run ) {
					$t .= (string) $run->t;
				}
				$strings[] = $t;
			}
		}
	}

	// First worksheet, via the workbook's relationships.
	$sheet_path = 'xl/worksheets/sheet1.xml';
	$wb         = $xml( 'xl/workbook.xml' );
	$rels       = $xml( 'xl/_rels/workbook.xml.rels' );
	if ( $wb && $rels && isset( $wb->sheets->sheet[0] ) ) {
		$rid = (string) $wb->sheets->sheet[0]->attributes( 'http://schemas.openxmlformats.org/officeDocument/2006/relationships' )->id;
		foreach ( $rels->Relationship as $rel ) {
			if ( (string) $rel['Id'] === $rid ) {
				$target     = ltrim( (string) $rel['Target'], '/' );
				$sheet_path = 0 === strpos( $target, 'xl/' ) ? $target : 'xl/' . $target;
				break;
			}
		}
	}
	$sheet = $xml( $sheet_path );
	$zip->close();
	if ( ! $sheet || ! isset( $sheet->sheetData ) ) {
		return $fail;
	}

	$rows = array();
	foreach ( $sheet->sheetData->row as $r ) {
		$row = array();
		foreach ( $r->c as $c ) {
			$ref = (string) $c['r'];
			$col = 0;
			if ( preg_match( '/^([A-Z]+)/', $ref, $m ) ) {
				foreach ( str_split( $m[1] ) as $ch ) {
					$col = $col * 26 + ( ord( $ch ) - 64 );
				}
				$col--;
			} else {
				$col = count( $row );
			}
			$type = (string) $c['t'];
			if ( 's' === $type ) {
				$val = $strings[ (int) $c->v ] ?? '';
			} elseif ( 'inlineStr' === $type ) {
				$val = (string) $c->is->t;
			} else {
				$val = (string) $c->v;
				if ( is_numeric( $val ) && false !== strpos( $val, '.' ) && (float) $val === floor( (float) $val ) ) {
					$val = (string) (int) $val; // 39.0 -> 39
				}
			}
			$row[ $col ] = $val;
		}
		if ( $row ) {
			$max = max( array_keys( $row ) );
			$row = array_replace( array_fill( 0, $max + 1, '' ), $row );
		}
		$rn = (int) $r['r'];
		if ( $rn > 0 ) {
			while ( count( $rows ) < $rn - 1 ) {
				$rows[] = array(); // keep spreadsheet row numbers for the report.
			}
		}
		$rows[] = $row;
	}
	return $rows;
}

/**
 * Find the heading row (within the first 10 rows) and map its columns.
 *
 * @return array{0:int,1:array} Heading row index and column map, or [-1, []].
 */
function oyc_roster_find_header( $rows ) {
	foreach ( array_slice( $rows, 0, 10, true ) as $i => $row ) {
		$cols = oyc_roster_map_header( $row );
		$data = array_intersect( array( 'oyc_member_number', 'oyc_membership_type', 'oyc_dues_paid_through', 'oyc_member_since', 'oyc_locker', 'oyc_dinghy_rack', 'boat', 'spouse' ), $cols );
		$key  = array_intersect( array( 'email', 'login', 'first', 'name' ), $cols );
		if ( $data && $key ) {
			return array( $i, $cols );
		}
	}
	return array( -1, array() );
}

/**
 * Turn one parsed roster row into a Fleet Roster directory entry.
 */
function oyc_roster_directory_entry( $r ) {
	$name = trim( ( $r['first'] ?? '' ) . ' ' . ( $r['last'] ?? '' ) );
	if ( '' === $name ) {
		$name = (string) ( $r['name'] ?? '' );
	}
	$len = (string) ( $r['boatLen'] ?? '' );
	if ( '' !== $len && is_numeric( $len ) ) {
		$len .= "'";
	}
	$phones = array_values( array_filter( array( $r['phone'] ?? '', $r['phone2'] ?? '' ), 'strlen' ) );
	$entry  = array(
		'name'     => $name,
		'last'     => (string) ( $r['last'] ?? '' ),
		'spouse'   => (string) ( $r['spouse'] ?? '' ),
		'email'    => (string) ( $r['email'] ?? '' ),
		'phones'   => $phones,
		'street'   => (string) ( $r['street'] ?? '' ),
		'city'     => (string) ( $r['city'] ?? '' ),
		'state'    => (string) ( $r['state'] ?? '' ),
		'zip'      => (string) ( $r['zip'] ?? '' ),
		'boat'     => (string) ( $r['boat'] ?? '' ),
		'boatLen'  => $len,
		'boatType' => (string) ( $r['boatType'] ?? '' ),
		'category' => (string) ( $r['oyc_membership_type'] ?? '' ),
		// Kept for the officer export only (the directory page does not show them),
		// so members without a website account keep these values too.
		'since'    => (string) ( $r['oyc_member_since'] ?? '' ),
		'locker'   => (string) ( $r['oyc_locker'] ?? '' ),
		'dinghy'   => (string) ( $r['oyc_dinghy_rack'] ?? '' ),
		'number'   => (string) ( $r['oyc_member_number'] ?? '' ),
		'dues'     => (string) ( $r['oyc_dues_paid_through'] ?? '' ),
		'listed'   => (string) ( $r['name'] ?? '' ), // roster's own "Member" text, e.g. "Harnett, Pat".
	);
	$street = sanitize_textarea_field( $entry['street'] ); // keep a 2-line street (Apt on line 2).
	$entry  = array_map( fn( $v ) => is_array( $v ) ? array_map( 'sanitize_text_field', $v ) : sanitize_text_field( $v ), $entry );
	$entry['street'] = $street;
	return $entry;
}

/**
 * Import a roster file.
 *
 * @param string $path          Uploaded temp file.
 * @param string $name          Original file name.
 * @param bool   $dry_run       Report only.
 * @param bool   $do_directory  Also replace the Fleet Roster directory.
 * @return array
 */
function oyc_roster_import( $path, $name, $dry_run = true, $do_directory = false ) {
	$out = array(
		'updated'      => 0,
		'unchanged'    => 0,
		'unmatched'    => array(),
		'duplicates'   => array(),
		'inactive'     => array(), // Expelled/Deceased rows that still have an account.
		'emails'       => array(), // Account emails changed to the roster's.
		'email_conflicts' => array(), // Roster email already used by another account.
		'directory'    => null,    // count written (or that would be).
		'dir_skipped'  => 0,
		'pdf'          => null,    // true/false once the PDF was rebuilt.
		'error'        => '',
	);

	// Profile changes made by this upload must not be copied back into the
	// directory it is replacing (see oyc_roster_sync_queue()).
	$GLOBALS['oyc_roster_importing'] = ! $dry_run;
	$rows = oyc_roster_read_rows( $path, $name );
	if ( is_wp_error( $rows ) ) {
		$out['error'] = $rows->get_error_message();
		return $out;
	}
	list( $hrow, $cols ) = oyc_roster_find_header( $rows );
	if ( $hrow < 0 ) {
		$out['error'] = __( 'No heading row was found. The file needs a row of column headings with an Email or Name column and at least one of Member No., Member Type, Member Since, Locker, Dinghy Rack or Boat Name.', 'orienta-yacht-club' );
		return $out;
	}

	$profile_fields = array_merge( array_keys( oyc_card_fields() ), array( 'oyc_member_since' ), array_keys( oyc_member_club_fields() ) );
	$seen_num       = array();
	oyc_roster_match_account( null, '', '', true ); // Fresh account index.
	$directory      = array();
	$inactive_types = oyc_roster_inactive_types();

	foreach ( array_slice( $rows, $hrow + 1, null, true ) as $idx => $row ) {
		$line = $idx + 1; // spreadsheet row number.
		$r    = array();
		foreach ( $cols as $i => $f ) {
			$r[ $f ] = trim( (string) ( $row[ $i ] ?? '' ) );
		}
		if ( ! array_filter( $r, 'strlen' ) ) {
			continue;
		}
		if ( empty( $r['first'] ) && empty( $r['last'] ) && ! empty( $r['name'] ) ) {
			$n = $r['name'];
			if ( false !== strpos( $n, ',' ) ) { // "Smith, John"
				list( $l, $fn ) = array_map( 'trim', explode( ',', $n, 2 ) );
				$r['first'] = $fn;
				$r['last']  = $l;
			} else {
				$parts      = preg_split( '/\s+/', $n );
				$r['last']  = array_pop( $parts );
				$r['first'] = implode( ' ', $parts );
			}
		}
		$label = trim( ( $r['first'] ?? '' ) . ' ' . ( $r['last'] ?? '' ) );
		if ( '' === $label ) {
			$label = $r['email'] ?? ( $r['login'] ?? __( '(no name)', 'orienta-yacht-club' ) );
		}
		$is_inactive = in_array( strtolower( $r['oyc_membership_type'] ?? '' ), $inactive_types, true );

		// Directory entry.
		if ( $is_inactive ) {
			$out['dir_skipped']++;
		} elseif ( '' !== $label && ( ! empty( $r['first'] ) || ! empty( $r['last'] ) ) ) {
			$directory[] = oyc_roster_directory_entry( $r );
		}

		// Account match: email (or the same Gmail inbox), login, then name.
		$em   = is_email( $r['email'] ?? '' ) ? $r['email'] : '';
		$uid  = '' !== $em ? oyc_roster_match_account( $em, '', '' ) : 0;
		$user = $uid ? get_userdata( $uid ) : null;
		if ( ! $user && ! empty( $r['login'] ) ) {
			$user = get_user_by( 'login', $r['login'] ) ?: null;
		}
		if ( ! $user ) {
			$uid  = oyc_roster_match_account( '', $r['first'] ?? '', $r['last'] ?? '' );
			$user = $uid ? get_userdata( $uid ) : null;
		}
		if ( ! $user || ! oyc_officer_can_manage_user( $user->ID ) ) {
			$out['unmatched'][] = sprintf( __( 'Row %1$d: %2$s', 'orienta-yacht-club' ), $line, $label );
			continue;
		}
		if ( $is_inactive ) {
			$out['inactive'][] = sprintf( __( 'Row %1$d: %2$s (%3$s) still has a website account: %4$s', 'orienta-yacht-club' ), $line, $label, $r['oyc_membership_type'], $user->user_login );
		}

		$changed = false;
		foreach ( $profile_fields as $f ) {
			if ( ! isset( $r[ $f ] ) || '' === $r[ $f ] ) {
				continue;
			}
			$val = in_array( $f, array_keys( oyc_card_fields() ), true ) ? oyc_card_clean_field( $f, $r[ $f ] ) : sanitize_text_field( $r[ $f ] );
			if ( '' === $val ) {
				continue;
			}
			if ( 'oyc_member_number' === $f ) {
				if ( isset( $seen_num[ $val ] ) && $seen_num[ $val ] !== $user->ID ) {
					$out['duplicates'][] = sprintf( __( 'Row %1$d: member number %2$s is also on an earlier row', 'orienta-yacht-club' ), $line, $val );
				}
				$seen_num[ $val ] = $user->ID;
			}
			if ( (string) get_user_meta( $user->ID, $f, true ) !== $val ) {
				$changed = true;
				if ( ! $dry_run ) {
					update_user_meta( $user->ID, $f, $val );
				}
			}
		}
		// The roster is the record: a matched account whose email differs
		// (dan.smith@ vs dansmith@, or a match by name) takes the roster email,
		// unless another account already uses that address.
		$roster_email = isset( $r['email'] ) ? sanitize_email( $r['email'] ) : '';
		if ( '' !== $roster_email && is_email( $roster_email ) && 0 !== strcasecmp( $roster_email, $user->user_email ) ) {
			$owner = get_user_by( 'email', $roster_email );
			if ( $owner && (int) $owner->ID !== (int) $user->ID ) {
				$out['email_conflicts'][] = sprintf( __( 'Row %1$d: %2$s: %3$s is already used by account %4$s, so %5$s kept %6$s', 'orienta-yacht-club' ), $line, $label, $roster_email, $owner->user_login, $user->user_login, $user->user_email );
			} else {
				$out['emails'][] = sprintf( __( 'Row %1$d: %2$s: %3$s → %4$s', 'orienta-yacht-club' ), $line, $label, $user->user_email, $roster_email );
				$changed         = true;
				if ( ! $dry_run ) {
					// Officer-driven change: no "your email changed" notice.
					add_filter( 'send_email_change_email', '__return_false', 99 );
					$res = wp_update_user( array( 'ID' => $user->ID, 'user_email' => $roster_email ) );
					remove_filter( 'send_email_change_email', '__return_false', 99 );
					if ( is_wp_error( $res ) ) {
						array_pop( $out['emails'] );
						$out['email_conflicts'][] = sprintf( __( 'Row %1$d: %2$s: email not changed: %3$s', 'orienta-yacht-club' ), $line, $label, $res->get_error_message() );
					}
				}
			}
		}

		$out[ $changed ? 'updated' : 'unchanged' ]++;
	}

	if ( $do_directory ) {
		$has_dir_cols = (bool) array_intersect( array( 'boat', 'spouse', 'phone', 'street' ), $cols );
		if ( ! $has_dir_cols ) {
			$out['directory'] = 0;
		} else {
			usort( $directory, fn( $a, $b ) => strcasecmp( $a['last'] . ' ' . $a['name'], $b['last'] . ' ' . $b['name'] ) );
			$directory        = array_map( function ( $e ) { unset( $e['last'] ); return $e; }, $directory );
			$out['directory'] = count( $directory );
			if ( ! $dry_run && $directory ) {
				// Keep the previous directory so it can be restored by hand.
				update_option( 'oyc_fleet_roster_prev', get_option( 'oyc_fleet_roster', '' ), false );
				update_option( 'oyc_fleet_roster', wp_json_encode( $directory ), false );
				update_option( 'oyc_fleet_roster_updated', time(), false );
				// Rebuild the members-only Fleet Roster PDF from the new directory.
				// A PDF problem never stops the upload (profiles and cards are
				// already saved above).
				$out['pdf'] = function_exists( 'oyc_roster_pdf_save' ) ? oyc_roster_pdf_save() : false;
			}
		}
	}

	$GLOBALS['oyc_roster_importing'] = false;
	if ( ! $dry_run && function_exists( 'oyc_audit_log' ) ) {
		oyc_audit_log( 'user.roster_import', sprintf( 'Roster upload: %d accounts updated, %d unchanged, %d rows unmatched%s', $out['updated'], $out['unchanged'], count( $out['unmatched'] ), null !== $out['directory'] ? sprintf( '; Fleet Roster directory replaced (%d members)', $out['directory'] ) : '' ), 0 );
	}
	return $out;
}

/* ─────────────────────────────────────────────────────────────────────────────
 * Officer: export the roster (CSV that opens in Excel and re-imports as-is)
 * ───────────────────────────────────────────────────────────────────────────── */

/**
 * Export URL for the officer Members page button.
 */
function oyc_roster_export_url() {
	return wp_nonce_url( add_query_arg( 'oyc_roster_export', '1', home_url( '/' ) ), 'oyc_roster_export' );
}

/**
 * Build the export rows: every Fleet Roster directory member, merged with
 * their website account (matched by email). Account values win over the
 * directory's copy, so officer edits on the Members page show up.
 *
 * @return array{0:array,1:array} Header row and data rows.
 */
function oyc_roster_export_rows() {
	$header = array( 'Member', 'First Name', 'Last Name', 'Phone', 'Alt Phone', 'Billing Street', 'Billing City', 'Billing State', 'Billing ZIP', 'Email', 'Member Type', 'Spouse', 'Boat Name', 'Size', 'Type', 'Member Since', 'Locker', 'Dinghy Rack', 'OYC Member No.', 'Dues Paid Through', 'Website Username', 'Account Status' );

	$meta = function ( $u, $k ) {
		return $u ? (string) get_user_meta( $u->ID, $k, true ) : '';
	};
	$state = function ( $u ) {
		if ( ! $u ) {
			return '';
		}
		$s = function_exists( 'oyc_user_approval_state' ) ? oyc_user_approval_state( $u->ID ) : 'approved';
		return ucfirst( $s );
	};
	$row = function ( $first, $last, $d, $u ) use ( $meta, $state ) {
		$first  = '' !== $first ? $first : $meta( $u, 'first_name' );
		$last   = '' !== $last ? $last : $meta( $u, 'last_name' );
		$phones = isset( $d['phones'] ) && is_array( $d['phones'] ) ? array_values( $d['phones'] ) : array();
		if ( ! $phones && $u ) {
			$phones = array_values( array_filter( array( $meta( $u, 'oyc_phone' ), $meta( $u, 'billing_phone' ) ), 'strlen' ) );
		}
		$pick = function ( $dk, $mk ) use ( $d, $u, $meta ) {
			$v = isset( $d[ $dk ] ) ? (string) $d[ $dk ] : '';
			return '' !== $v ? $v : $meta( $u, $mk );
		};
		$both = function ( $dk, $mk ) use ( $d, $u, $meta ) {
			$v = $meta( $u, $mk );
			return '' !== $v ? $v : (string) ( $d[ $dk ] ?? '' );
		};
		$type = $both( 'category', 'oyc_membership_type' );
		return array(
			'' !== (string) ( $d['listed'] ?? '' ) ? (string) $d['listed'] : trim( $last . ( '' !== $first ? ', ' . $first : '' ), ', ' ),
			$first,
			$last,
			$phones[0] ?? '',
			$phones[1] ?? '',
			$pick( 'street', 'billing_address_1' ),
			$pick( 'city', 'billing_city' ),
			$pick( 'state', 'billing_state' ),
			$pick( 'zip', 'billing_postcode' ),
			'' !== (string) ( $d['email'] ?? '' ) ? (string) $d['email'] : ( $u ? $u->user_email : '' ),
			$type,
			(string) ( $d['spouse'] ?? '' ),
			(string) ( $d['boat'] ?? '' ),
			rtrim( (string) ( $d['boatLen'] ?? '' ), "'" ),
			(string) ( $d['boatType'] ?? '' ),
			$both( 'since', 'oyc_member_since' ),
			$both( 'locker', 'oyc_locker' ),
			$both( 'dinghy', 'oyc_dinghy_rack' ),
			$both( 'number', 'oyc_member_number' ),
			$both( 'dues', 'oyc_dues_paid_through' ),
			$u ? $u->user_login : '',
			$u ? $state( $u ) : 'No account',
		);
	};

	// The roster is the Fleet Roster directory (last uploaded roster); accounts
	// that are not on it (admins, pending sign-ups, spam) are left out.
	$rows = array();
	foreach ( ( function_exists( 'oyc_get_roster' ) ? oyc_get_roster() : array() ) as $d ) {
		if ( ! is_array( $d ) ) {
			continue;
		}
		list( $first, $last ) = oyc_roster_split_name( $d['name'] ?? '', $d['last'] ?? '' );
		$uid = oyc_roster_match_account( (string) ( $d['email'] ?? '' ), $first, $last );
		$u   = null;
		if ( $uid && oyc_officer_can_manage_user( $uid ) ) {
			$u = get_userdata( $uid ) ?: null;
		}
		$rows[] = $row( $first, $last, $d, $u );
	}
	usort( $rows, fn( $a, $b ) => strcasecmp( $a[0], $b[0] ) );
	return array( $header, $rows );
}

add_action( 'template_redirect', function () {
	if ( empty( $_GET['oyc_roster_export'] ) ) {
		return;
	}
	if ( ! is_user_logged_in() || ! current_user_can( 'oyc_manage_members' ) ) {
		wp_die( esc_html__( 'You do not have permission to export the roster.', 'orienta-yacht-club' ), '', array( 'response' => 403 ) );
	}
	check_admin_referer( 'oyc_roster_export' );

	list( $header, $rows ) = oyc_roster_export_rows();
	if ( function_exists( 'oyc_audit_log' ) ) {
		oyc_audit_log( 'user.roster_export', sprintf( 'Exported the roster (%d rows)', count( $rows ) ), 0 );
	}

	nocache_headers();
	header( 'Content-Type: text/csv; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename=OYC-Roster-' . current_time( 'Y-m-d' ) . '.csv' );
	header( 'X-Robots-Tag: noindex' );
	$out = fopen( 'php://output', 'w' );
	fwrite( $out, "\xEF\xBB\xBF" ); // Excel reads UTF-8 with a BOM.
	fputcsv( $out, $header, ',', '"', '\\' );
	foreach ( $rows as $r ) {
		// Stop Excel treating a leading = + - @ as a formula.
		$r = array_map( fn( $v ) => preg_match( '/^[=+\-@]/', (string) $v ) ? "'" . $v : $v, $r );
		fputcsv( $out, $r, ',', '"', '\\' );
	}
	fclose( $out );
	exit;
}, 1 );

/* ─────────────────────────────────────────────────────────────────────────────
 * Not Registered members: roster entries with no website account, and enrolling them
 * ───────────────────────────────────────────────────────────────────────────── */

/**
 * Fleet Roster directory entries not matched to any account by email or
 * first + last name, sorted by last name. Optional search text filters them.
 */
function oyc_roster_unregistered( $search = '' ) {
	if ( ! function_exists( 'oyc_get_roster' ) ) {
		return array();
	}
	$q   = strtolower( trim( (string) $search ) );
	$out = array();
	foreach ( oyc_get_roster() as $e ) {
		if ( ! is_array( $e ) ) {
			continue;
		}
		$em = strtolower( trim( (string) ( $e['email'] ?? '' ) ) );
		list( $first, $last ) = oyc_roster_split_name( $e['name'] ?? '', $e['last'] ?? '' );
		if ( oyc_roster_match_account( $em, $first, $last ) ) {
			continue;
		}
		if ( '' !== $q ) {
			$hay = strtolower( implode( ' ', array( $e['name'] ?? '', $e['listed'] ?? '', $em, $e['number'] ?? '', $e['boat'] ?? '' ) ) );
			if ( false === strpos( $hay, $q ) ) {
				continue;
			}
		}
		$out[] = $e;
	}
	usort( $out, fn( $a, $b ) => strcasecmp( ( $a['last'] ?? '' ) . ( $a['name'] ?? '' ), ( $b['last'] ?? '' ) . ( $b['name'] ?? '' ) ) );
	return $out;
}

/**
 * A free username like "jsmith", "jsmith2", ...
 */
function oyc_roster_new_login( $first, $last, $email ) {
	$base = sanitize_user( strtolower( remove_accents( substr( (string) $first, 0, 1 ) . preg_replace( '/[^a-z]/i', '', (string) $last ) ) ), true );
	if ( strlen( $base ) < 3 ) {
		$base = sanitize_user( strtok( (string) $email, '@' ), true );
	}
	$login = $base;
	for ( $i = 2; username_exists( $login ); $i++ ) {
		$login = $base . $i;
	}
	return $login;
}

/**
 * Create website accounts for Not Registered roster members.
 *
 * Each gets a Subscriber account, approved (no officer confirm step, no email
 * validation), with name, phone, address and the club fields from the roster.
 * Members without a usable email, or whose email is already on an account, are
 * skipped. With $send_email each new member gets WordPress's set-password email;
 * without it nobody is emailed (they can use "Lost your password?" later).
 *
 * @return array{created:array,skipped:array}
 */
function oyc_roster_enroll( $dry_run = true, $send_email = false ) {
	$out  = array( 'created' => array(), 'skipped' => array() );
	$seen = array(); // emails used earlier in this run (e.g. a couple sharing one address).
	foreach ( oyc_roster_unregistered() as $e ) {
		$name  = trim( (string) ( $e['name'] ?? '' ) );
		$last  = trim( (string) ( $e['last'] ?? '' ) );
		$first = trim( '' !== $last && strtolower( substr( $name, -strlen( $last ) ) ) === strtolower( $last ) ? substr( $name, 0, -strlen( $last ) ) : $name );
		if ( '' === $last ) {
			$parts = preg_split( '/\s+/', $name );
			$last  = (string) array_pop( $parts );
			$first = implode( ' ', $parts );
		}
		$label = oyc_proper_case( $name );
		$email = sanitize_email( (string) ( $e['email'] ?? '' ) );
		if ( '' === $email || ! is_email( $email ) ) {
			$out['skipped'][] = sprintf( __( '%s: no email address on the roster', 'orienta-yacht-club' ), $label );
			continue;
		}
		if ( email_exists( $email ) || oyc_roster_match_account( $email, $first, $last ) || isset( $seen[ strtolower( $email ) ] ) ) {
			$out['skipped'][] = sprintf( __( '%1$s: %2$s is already used by another account', 'orienta-yacht-club' ), $label, $email );
			continue;
		}
		$seen[ strtolower( $email ) ] = true;
		$login = oyc_roster_new_login( $first, $last, $email );
		if ( $dry_run ) {
			$out['created'][] = sprintf( '%1$s (%2$s, username %3$s)', $label, $email, $login );
			continue;
		}
		$uid = wp_insert_user( array(
			'user_login'   => $login,
			'user_email'   => $email,
			'user_pass'    => wp_generate_password( 24, true, true ),
			'first_name'   => oyc_proper_case( $first ),
			'last_name'    => oyc_proper_case( $last ),
			'display_name' => $label,
			'role'         => 'subscriber',
		) );
		if ( is_wp_error( $uid ) ) {
			$out['skipped'][] = sprintf( __( '%1$s: %2$s', 'orienta-yacht-club' ), $label, $uid->get_error_message() );
			continue;
		}
		$phones = isset( $e['phones'] ) && is_array( $e['phones'] ) ? array_values( $e['phones'] ) : array();
		$meta   = array(
			'active'                 => '1',              // approved: no officer Confirm step.
			'_wpmem_user_confirmed'  => (string) time(),  // email treated as confirmed.
			'oyc_enrolled_from_roster' => current_time( 'mysql' ),
			'oyc_member_number'      => (string) ( $e['number'] ?? '' ),
			'oyc_membership_type'    => (string) ( $e['category'] ?? '' ),
			'oyc_member_since'       => (string) ( $e['since'] ?? '' ),
			'oyc_dues_paid_through'  => (string) ( $e['dues'] ?? '' ),
			'oyc_locker'             => (string) ( $e['locker'] ?? '' ),
			'oyc_dinghy_rack'        => (string) ( $e['dinghy'] ?? '' ),
			'oyc_phone'              => (string) ( $phones[0] ?? '' ),
			'billing_phone'          => (string) ( $phones[1] ?? '' ),
			'billing_address_1'      => (string) ( $e['street'] ?? '' ),
			'billing_city'           => (string) ( $e['city'] ?? '' ),
			'billing_state'          => (string) ( $e['state'] ?? '' ),
			'billing_postcode'       => (string) ( $e['zip'] ?? '' ),
		);
		foreach ( $meta as $k => $v ) {
			if ( '' !== $v ) {
				update_user_meta( $uid, $k, $v );
			}
		}
		if ( $send_email ) {
			wp_send_new_user_notifications( $uid, 'user' );
		}
		if ( function_exists( 'oyc_audit_log' ) ) {
			oyc_audit_log( 'user.enroll', sprintf( 'Enrolled roster member "%s" (%s) as %s%s', $label, $email, $login, $send_email ? ', set-password email sent' : '' ), $uid );
		}
		$out['created'][] = sprintf( '%1$s (%2$s, username %3$s)', $label, $email, $login );
	}
	return $out;
}
