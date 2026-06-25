<?php
/**
 * One-time Formidable entry importer (Contact Us submissions).
 *
 * Formidable Forms LITE cannot import entries (Pro-only), so this admin-only
 * tool reads a CSV from wp-content/uploads/ and creates entries via the
 * FrmEntry::create() API. It is DRY-RUN by default — it reports the detected
 * columns, the form fields, the proposed column->field mapping and the row
 * count, and creates nothing — until you explicitly add &mode=commit&confirm=YES.
 *
 * Usage (must be logged in as an administrator):
 *   /wp-admin/?oyc_import_entries=1                              -> dry-run report
 *   /wp-admin/?oyc_import_entries=1&file=<name.csv>              -> pick a specific CSV
 *   /wp-admin/?oyc_import_entries=1&form=<form_key_or_id>        -> pick a specific form
 *   /wp-admin/?oyc_import_entries=1&mode=commit&confirm=YES      -> create the entries
 *
 * DELETE this file (and the require in functions.php) once the import is done.
 *
 * @package Orienta_Yacht_Club
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Normalise a header / field label for fuzzy matching.
 */
function oyc_imp_norm( $s ) {
	$s = strtolower( (string) $s );
	$s = preg_replace( '/[^a-z0-9]+/', '', $s );
	return $s;
}

/**
 * Columns that are entry metadata, not form-field values.
 */
function oyc_imp_meta_cols() {
	return array(
		'id'         => 'skip',
		'key'        => 'item_key',
		'itemkey'    => 'item_key',
		'entrykey'   => 'item_key',
		'createdat'  => 'created_at',
		'created'    => 'created_at',
		'date'       => 'created_at',
		'datecreated'=> 'created_at',
		'entrydate'  => 'created_at',
		'timestamp'  => 'created_at',
		'updatedat'  => 'skip',
		'updated'    => 'skip',
		'ip'         => 'ip',
		'userid'     => 'skip',
		'user'       => 'skip',
		'createdby'  => 'skip',
		'postid'     => 'skip',
		'parentid'   => 'skip',
		'isdraft'    => 'skip',
	);
}

/**
 * Find the CSV to import in the uploads tree (newest match wins).
 */
function oyc_imp_find_csv( $prefer = '' ) {
	$up   = wp_upload_dir();
	$base = trailingslashit( $up['basedir'] );
	$found = array();

	$it = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator( $base, FilesystemIterator::SKIP_DOTS )
	);
	foreach ( $it as $file ) {
		if ( ! $file->isFile() ) {
			continue;
		}
		if ( strtolower( $file->getExtension() ) !== 'csv' ) {
			continue;
		}
		$found[ $file->getPathname() ] = $file->getMTime();
	}
	if ( ! $found ) {
		return '';
	}

	// Prefer an explicit filename match.
	if ( $prefer ) {
		foreach ( $found as $path => $mt ) {
			if ( stripos( basename( $path ), $prefer ) !== false ) {
				return $path;
			}
		}
	}
	// Otherwise prefer a contact/formidable-looking name, else the newest.
	$best = '';
	$best_mt = -1;
	foreach ( $found as $path => $mt ) {
		$name = strtolower( basename( $path ) );
		$score = $mt;
		if ( strpos( $name, 'formidable' ) !== false || strpos( $name, 'contact' ) !== false || strpos( $name, 'entries' ) !== false ) {
			$score += 1000000000; // strongly prefer
		}
		if ( $score > $best_mt ) {
			$best_mt = $score;
			$best    = $path;
		}
	}
	return $best;
}

/**
 * Parse a CSV file into [headers, rows].
 */
function oyc_imp_parse_csv( $path ) {
	$rows = array();
	$headers = array();
	if ( ( $fh = fopen( $path, 'r' ) ) === false ) {
		return array( $headers, $rows );
	}
	$first = true;
	while ( ( $data = fgetcsv( $fh, 0, ',' ) ) !== false ) {
		if ( $first ) {
			// Strip a UTF-8 BOM from the first header cell.
			if ( isset( $data[0] ) ) {
				$data[0] = preg_replace( '/^\xEF\xBB\xBF/', '', $data[0] );
			}
			$headers = array_map( 'trim', $data );
			$first = false;
			continue;
		}
		// Skip fully empty lines.
		$nonempty = array_filter( $data, function ( $v ) { return trim( (string) $v ) !== ''; } );
		if ( ! $nonempty ) {
			continue;
		}
		$rows[] = $data;
	}
	fclose( $fh );
	return array( $headers, $rows );
}

/**
 * Resolve the target Formidable form.
 */
function oyc_imp_get_form( $prefer = '' ) {
	global $wpdb;
	$forms = $wpdb->get_results( "SELECT id, name, form_key, parent_form_id, status FROM {$wpdb->prefix}frm_forms WHERE ( parent_form_id IS NULL OR parent_form_id = 0 )" );
	if ( ! $forms ) {
		return null;
	}
	if ( $prefer !== '' ) {
		foreach ( $forms as $f ) {
			if ( (string) $f->id === (string) $prefer || $f->form_key === $prefer ) {
				return $f;
			}
		}
		foreach ( $forms as $f ) {
			if ( stripos( $f->name, $prefer ) !== false ) {
				return $f;
			}
		}
	}
	// Default: a form whose name looks like "contact".
	foreach ( $forms as $f ) {
		if ( stripos( $f->name, 'contact' ) !== false ) {
			return $f;
		}
	}
	return $forms[0];
}

/**
 * Get the form's fields as id => {name, key, type}.
 */
function oyc_imp_get_fields( $form_id ) {
	global $wpdb;
	$rows = $wpdb->get_results( $wpdb->prepare(
		"SELECT id, name, field_key, type FROM {$wpdb->prefix}frm_fields WHERE form_id = %d ORDER BY field_order ASC",
		$form_id
	) );
	$out = array();
	foreach ( (array) $rows as $r ) {
		// Skip layout/non-data field types.
		if ( in_array( $r->type, array( 'divider', 'html', 'captcha', 'break', 'end_divider', 'form', 'summary' ), true ) ) {
			continue;
		}
		$out[ $r->id ] = $r;
	}
	return $out;
}

/**
 * Build a column-index => field-id (or meta token) mapping.
 */
function oyc_imp_build_map( $headers, $fields ) {
	$meta_cols = oyc_imp_meta_cols();
	$map = array(); // col index => array('type'=>'field'|'meta'|'none', 'field_id'=>, 'label'=>, 'meta'=>)

	// Pre-index fields by normalised name and key.
	$by_norm = array();
	foreach ( $fields as $fid => $f ) {
		$by_norm[ oyc_imp_norm( $f->name ) ]      = $fid;
		$by_norm[ oyc_imp_norm( $f->field_key ) ] = $fid;
	}

	foreach ( $headers as $i => $h ) {
		$n = oyc_imp_norm( $h );
		if ( $n === '' ) {
			$map[ $i ] = array( 'type' => 'none', 'label' => $h );
			continue;
		}
		if ( isset( $meta_cols[ $n ] ) ) {
			$map[ $i ] = array( 'type' => 'meta', 'meta' => $meta_cols[ $n ], 'label' => $h );
			continue;
		}
		if ( isset( $by_norm[ $n ] ) ) {
			$fid = $by_norm[ $n ];
			$map[ $i ] = array( 'type' => 'field', 'field_id' => $fid, 'label' => $h, 'field' => $fields[ $fid ] );
			continue;
		}
		// Try a contains match against field names as a last resort.
		$hit = '';
		foreach ( $fields as $fid => $f ) {
			$fn = oyc_imp_norm( $f->name );
			if ( $fn !== '' && ( strpos( $fn, $n ) !== false || strpos( $n, $fn ) !== false ) ) {
				$hit = $fid;
				break;
			}
		}
		if ( $hit ) {
			$map[ $i ] = array( 'type' => 'field', 'field_id' => $hit, 'label' => $h, 'field' => $fields[ $hit ], 'fuzzy' => true );
		} else {
			$map[ $i ] = array( 'type' => 'none', 'label' => $h );
		}
	}
	return $map;
}

/**
 * HTML-escape helper.
 */
function oyc_imp_e( $s ) {
	return esc_html( (string) $s );
}

add_action( 'admin_init', function () {
	if ( empty( $_GET['oyc_import_entries'] ) ) {
		return;
	}
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	if ( ! class_exists( 'FrmEntry' ) || ! class_exists( 'FrmForm' ) || ! class_exists( 'FrmField' ) ) {
		wp_die( 'Formidable Forms does not appear to be active on this site.' );
	}

	$commit = ( isset( $_GET['mode'] ) && $_GET['mode'] === 'commit'
		&& isset( $_GET['confirm'] ) && $_GET['confirm'] === 'YES' );

	$prefer_file = isset( $_GET['file'] ) ? sanitize_text_field( wp_unslash( $_GET['file'] ) ) : '';
	$prefer_form = isset( $_GET['form'] ) ? sanitize_text_field( wp_unslash( $_GET['form'] ) ) : '';

	// --- Locate inputs ---------------------------------------------------
	$csv = oyc_imp_find_csv( $prefer_file );
	if ( ! $csv ) {
		wp_die( 'No CSV file found under wp-content/uploads/. Upload it to the Media Library first.' );
	}
	list( $headers, $rows ) = oyc_imp_parse_csv( $csv );
	if ( ! $headers ) {
		wp_die( 'Could not read headers from: ' . oyc_imp_e( $csv ) );
	}

	$form = oyc_imp_get_form( $prefer_form );
	if ( ! $form ) {
		wp_die( 'No Formidable form found.' );
	}
	$fields = oyc_imp_get_fields( $form->id );
	$map    = oyc_imp_build_map( $headers, $fields );

	global $wpdb;
	$existing = (int) $wpdb->get_var( $wpdb->prepare(
		"SELECT COUNT(*) FROM {$wpdb->prefix}frm_items WHERE form_id = %d",
		$form->id
	) );

	// --- Report header ---------------------------------------------------
	$out  = '<div style="font:14px/1.5 -apple-system,Segoe UI,Roboto,sans-serif;max-width:980px;margin:24px auto;color:#1a2a3a">';
	$out .= '<h1 style="font-size:20px">OYC Formidable Entry Importer</h1>';
	$out .= '<p><strong>Mode:</strong> ' . ( $commit ? '<span style="color:#b00">COMMIT (creating entries)</span>' : '<span style="color:#070">DRY RUN (nothing will be created)</span>' ) . '</p>';
	$out .= '<table style="border-collapse:collapse;margin:8px 0"><tbody>';
	$out .= '<tr><td style="padding:2px 12px 2px 0;color:#667">CSV file</td><td><code>' . oyc_imp_e( str_replace( ABSPATH, '', $csv ) ) . '</code></td></tr>';
	$out .= '<tr><td style="padding:2px 12px 2px 0;color:#667">Data rows</td><td><strong>' . count( $rows ) . '</strong></td></tr>';
	$out .= '<tr><td style="padding:2px 12px 2px 0;color:#667">Target form</td><td><strong>' . oyc_imp_e( $form->name ) . '</strong> (id ' . (int) $form->id . ', key <code>' . oyc_imp_e( $form->form_key ) . '</code>)</td></tr>';
	$out .= '<tr><td style="padding:2px 12px 2px 0;color:#667">Existing entries</td><td>' . $existing . '</td></tr>';
	$out .= '</tbody></table>';

	// --- Mapping table ---------------------------------------------------
	$out .= '<h2 style="font-size:16px;margin-top:18px">Column &rarr; Field mapping</h2>';
	$out .= '<table style="border-collapse:collapse;width:100%;font-size:13px">';
	$out .= '<tr style="background:#eef3f8;text-align:left"><th style="padding:6px 8px;border:1px solid #d8e2ec">CSV column</th><th style="padding:6px 8px;border:1px solid #d8e2ec">Maps to</th><th style="padding:6px 8px;border:1px solid #d8e2ec">Sample value (row 1)</th></tr>';
	$unmapped = 0;
	foreach ( $headers as $i => $h ) {
		$m = $map[ $i ];
		$sample = isset( $rows[0][ $i ] ) ? $rows[0][ $i ] : '';
		if ( mb_strlen( $sample ) > 60 ) {
			$sample = mb_substr( $sample, 0, 60 ) . '…';
		}
		if ( $m['type'] === 'field' ) {
			$dest = 'Field: <strong>' . oyc_imp_e( $m['field']->name ) . '</strong> (id ' . (int) $m['field_id'] . ')';
			if ( ! empty( $m['fuzzy'] ) ) {
				$dest .= ' <span style="color:#a60">~fuzzy</span>';
			}
			$bg = '';
		} elseif ( $m['type'] === 'meta' ) {
			$dest = $m['meta'] === 'skip' ? '<span style="color:#999">(ignored metadata)</span>' : 'Entry ' . oyc_imp_e( $m['meta'] );
			$bg = '';
		} else {
			$dest = '<span style="color:#b00">— NOT MAPPED —</span>';
			$bg = ' background:#fdecec;';
			$unmapped++;
		}
		$out .= '<tr style="' . $bg . '"><td style="padding:5px 8px;border:1px solid #e3e9ef">' . oyc_imp_e( $h ) . '</td><td style="padding:5px 8px;border:1px solid #e3e9ef">' . $dest . '</td><td style="padding:5px 8px;border:1px solid #e3e9ef;color:#556">' . oyc_imp_e( $sample ) . '</td></tr>';
	}
	$out .= '</table>';

	// --- All form fields (for reference) ---------------------------------
	$out .= '<h2 style="font-size:16px;margin-top:18px">All fields on this form</h2><ul>';
	foreach ( $fields as $fid => $f ) {
		$out .= '<li>id ' . (int) $fid . ' — <strong>' . oyc_imp_e( $f->name ) . '</strong> <code>' . oyc_imp_e( $f->field_key ) . '</code> <span style="color:#889">(' . oyc_imp_e( $f->type ) . ')</span></li>';
	}
	$out .= '</ul>';

	// --- Dry run stops here ---------------------------------------------
	if ( ! $commit ) {
		$commit_url = add_query_arg( array( 'oyc_import_entries' => 1, 'mode' => 'commit', 'confirm' => 'YES', 'file' => rawurlencode( $prefer_file ), 'form' => rawurlencode( $prefer_form ) ), admin_url() );
		$out .= '<p style="margin-top:20px;padding:12px;background:#eef7ee;border:1px solid #bcdcbc;border-radius:6px">';
		$out .= 'This was a dry run — <strong>nothing was created</strong>. ';
		if ( $unmapped ) {
			$out .= '<span style="color:#b00">' . $unmapped . ' column(s) are not mapped (highlighted above) and will be skipped.</span> ';
		}
		$out .= 'Review the mapping above. To create the entries, re-run with <code>&amp;mode=commit&amp;confirm=YES</code>.';
		$out .= '</p>';
		$out .= '</div>';
		wp_die( $out, 'OYC Importer — Dry Run', array( 'response' => 200 ) );
	}

	// --- COMMIT ----------------------------------------------------------
	$created = 0;
	$errors  = array();
	foreach ( $rows as $ri => $row ) {
		$item_meta = array();
		$created_at = '';
		$item_key   = '';
		foreach ( $headers as $i => $h ) {
			$m = $map[ $i ];
			$val = isset( $row[ $i ] ) ? $row[ $i ] : '';
			if ( $m['type'] === 'field' ) {
				$item_meta[ $m['field_id'] ] = $val;
			} elseif ( $m['type'] === 'meta' ) {
				if ( $m['meta'] === 'created_at' && trim( $val ) !== '' ) {
					$ts = strtotime( $val );
					if ( $ts ) {
						$created_at = gmdate( 'Y-m-d H:i:s', $ts );
					}
				} elseif ( $m['meta'] === 'item_key' && trim( $val ) !== '' ) {
					$item_key = sanitize_title( $val );
				}
			}
		}

		$entry = array(
			'form_id'   => $form->id,
			'item_key'  => $item_key,
			'item_meta' => $item_meta,
			'frm_user_id' => 0,
		);
		$id = FrmEntry::create( $entry );
		if ( $id ) {
			$created++;
			if ( $created_at ) {
				$wpdb->update(
					$wpdb->prefix . 'frm_items',
					array( 'created_at' => $created_at, 'updated_at' => $created_at ),
					array( 'id' => $id )
				);
			}
		} else {
			$errors[] = 'Row ' . ( $ri + 2 ) . ' failed.';
		}
	}

	$out .= '<p style="margin-top:20px;padding:12px;background:#eef7ee;border:1px solid #bcdcbc;border-radius:6px">';
	$out .= '<strong>Created ' . $created . ' of ' . count( $rows ) . ' entries.</strong>';
	if ( $errors ) {
		$out .= '<br>' . count( $errors ) . ' failed: ' . oyc_imp_e( implode( ' ', array_slice( $errors, 0, 10 ) ) );
	}
	$out .= '<br>New entry total for this form: ' . ( $existing + $created ) . '.';
	$out .= '<br><strong>Now delete <code>inc/entry-import.php</code> and its require line, then redeploy.</strong>';
	$out .= '</p></div>';
	wp_die( $out, 'OYC Importer — Done', array( 'response' => 200 ) );
} );
