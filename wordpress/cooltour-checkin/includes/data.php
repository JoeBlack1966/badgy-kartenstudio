<?php
/**
 * Datenbank: Karten und Check-ins.
 */

defined( 'ABSPATH' ) || exit;

function ctc_table( $name ) {
	global $wpdb;
	return $wpdb->prefix . 'ctc_' . $name;
}

function ctc_install_tables() {
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	$charset = $wpdb->get_charset_collate();
	$cards   = ctc_table( 'cards' );
	$checks  = ctc_table( 'checkins' );

	dbDelta(
		"CREATE TABLE $cards (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		nummer varchar(40) NOT NULL,
		code varchar(40) NOT NULL DEFAULT '',
		kartenart varchar(40) NOT NULL DEFAULT '',
		kategorie varchar(60) NOT NULL DEFAULT '',
		name varchar(190) NOT NULL DEFAULT '',
		telefon varchar(60) NOT NULL DEFAULT '',
		email varchar(190) NOT NULL DEFAULT '',
		gueltig_ab date DEFAULT NULL,
		gueltig_bis date DEFAULT NULL,
		gesperrt tinyint(1) NOT NULL DEFAULT 0,
		notiz text NULL,
		created_at datetime NOT NULL,
		updated_at datetime NOT NULL,
		PRIMARY KEY  (id),
		UNIQUE KEY nummer (nummer)
		) $charset;"
	);
	dbDelta(
		"CREATE TABLE $checks (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		card_id bigint(20) unsigned NOT NULL,
		event_key varchar(190) NOT NULL,
		event_name varchar(190) NOT NULL DEFAULT '',
		event_post_id bigint(20) unsigned NOT NULL DEFAULT 0,
		event_date date NOT NULL,
		scanned_at datetime NOT NULL,
		scanned_by bigint(20) unsigned NOT NULL DEFAULT 0,
		PRIMARY KEY  (id),
		UNIQUE KEY card_event (card_id,event_key),
		KEY event_key (event_key)
		) $charset;"
	);
	update_option( 'ctc_db_version', CTC_DB_VERSION );
}

/** Zerlegt „M-0042.K7X2P9Q4“ (oder die ganze URL) in Nummer und Code. */
function ctc_parse_token( $raw ) {
	$raw = trim( rawurldecode( (string) $raw ) );
	if ( preg_match( '~(?:/' . CTC_CARD_PATH . '/|[?&]ctc_token=)([A-Za-z0-9._-]+)~', $raw, $m ) ) {
		$raw = $m[1];
	}
	$raw = rtrim( $raw, '/' );
	$dot = strrpos( $raw, '.' );
	if ( false === $dot ) {
		return array( $raw, '' );
	}
	return array( substr( $raw, 0, $dot ), substr( $raw, $dot + 1 ) );
}

function ctc_get_card_by_nummer( $nummer ) {
	global $wpdb;
	$t = ctc_table( 'cards' );
	return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $t WHERE nummer = %s", $nummer ) );
}

function ctc_get_card( $id ) {
	global $wpdb;
	$t = ctc_table( 'cards' );
	return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $t WHERE id = %d", $id ) );
}

/** Karte zum QR-Inhalt, nur wenn der Geheimcode stimmt. */
function ctc_find_card_by_token( $raw ) {
	list( $nummer, $code ) = ctc_parse_token( $raw );
	if ( '' === $nummer || '' === $code ) {
		return null;
	}
	$card = ctc_get_card_by_nummer( $nummer );
	if ( ! $card || '' === $card->code || ! hash_equals( strtoupper( $card->code ), strtoupper( $code ) ) ) {
		return null;
	}
	return $card;
}

/** Datum aus 31.10.2026, 31.10.26, 2026-10-31 oder 12/2027 → Y-m-d, sonst null. */
function ctc_parse_date( $value ) {
	$value = trim( (string) $value );
	if ( preg_match( '/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $value, $m ) ) {
		list( , $y, $mo, $d ) = $m;
	} elseif ( preg_match( '/^(\d{1,2})\.(\d{1,2})\.(\d{2,4})$/', $value, $m ) ) {
		list( , $d, $mo, $y ) = $m;
		if ( strlen( $y ) === 2 ) {
			$y = '20' . $y;
		}
	} elseif ( preg_match( '/^(\d{1,2})[\/.](\d{4})$/', $value, $m ) ) {
		// „12/2027“ = bis Ende Dezember 2027
		list( , $mo, $y ) = $m;
		$d = checkdate( (int) $mo, 1, (int) $y ) ? (int) gmdate( 't', gmmktime( 0, 0, 0, (int) $mo, 1, (int) $y ) ) : 0;
	} else {
		return null;
	}
	return checkdate( (int) $mo, (int) $d, (int) $y ) ? sprintf( '%04d-%02d-%02d', $y, $mo, $d ) : null;
}

function ctc_format_date( $ymd ) {
	return $ymd ? date_i18n( 'd.m.Y', strtotime( $ymd ) ) : '';
}

/** Gültigkeit einer Karte heute: array( ok, Grund ). */
function ctc_card_validity( $card ) {
	$today = current_time( 'Y-m-d' );
	if ( (int) $card->gesperrt ) {
		return array( false, 'Karte gesperrt' );
	}
	if ( $card->gueltig_ab && $card->gueltig_ab > $today ) {
		return array( false, 'Erst gültig ab ' . ctc_format_date( $card->gueltig_ab ) );
	}
	if ( $card->gueltig_bis && $card->gueltig_bis < $today ) {
		return array( false, 'Abgelaufen am ' . ctc_format_date( $card->gueltig_bis ) );
	}
	return array( true, 'Gültig' );
}

function ctc_card_visit_count( $card_id ) {
	global $wpdb;
	$t = ctc_table( 'checkins' );
	return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $t WHERE card_id = %d", $card_id ) );
}

/**
 * Liest die Kartenliste aus dem Kartenstudio (CSV mit ; , oder Tab, erste Zeile = Spaltennamen).
 * Gibt die Zeilen als Arrays mit den internen Feldnamen zurück.
 */
function ctc_parse_card_csv( $text ) {
	$text = preg_replace( '/^\xEF\xBB\xBF/', '', (string) $text );
	$text = str_replace( array( "\r\n", "\r" ), "\n", $text );
	$lines = array_values( array_filter( explode( "\n", $text ), 'strlen' ) );
	if ( count( $lines ) < 2 ) {
		return array();
	}
	$first = $lines[0];
	$delim = ';';
	foreach ( array( "\t", ';', ',' ) as $d ) {
		if ( substr_count( $first, $d ) > substr_count( $first, $delim ) ) {
			$delim = $d;
		}
	}
	$aliases = array(
		'nummer'      => array( 'nummer', 'nr', 'nr.', 'kartennummer', 'mitgliedsnr', 'mitgliedsnr.', 'mitgliedsnummer' ),
		'code'        => array( 'code', 'kartencode', 'token', 'qr', 'qr-code', 'link' ),
		'kartenart'   => array( 'kartenart', 'karte', 'vorlage', 'typ', 'art' ),
		'kategorie'   => array( 'kategorie', 'mitgliedschaft' ),
		'name'        => array( 'name', 'vorname nachname', 'mitglied' ),
		'telefon'     => array( 'telefon', 'tel', 'tel.', 'handy', 'mobil' ),
		'email'       => array( 'e-mail', 'email', 'mail' ),
		'gueltig_ab'  => array( 'gültig ab', 'gueltig ab', 'gültig_ab', 'ab' ),
		'gueltig_bis' => array( 'gültig bis', 'gueltig bis', 'gültig_bis', 'bis' ),
	);
	$map    = array();
	$header = str_getcsv( $first, $delim );
	foreach ( $header as $i => $h ) {
		$h = mb_strtolower( trim( $h ) );
		foreach ( $aliases as $field => $names ) {
			if ( in_array( $h, $names, true ) && ! in_array( $field, $map, true ) ) {
				$map[ $i ] = $field;
			}
		}
	}
	$rows = array();
	foreach ( array_slice( $lines, 1 ) as $line ) {
		$cells = str_getcsv( $line, $delim );
		$row   = array();
		foreach ( $map as $i => $field ) {
			$row[ $field ] = isset( $cells[ $i ] ) ? trim( $cells[ $i ] ) : '';
		}
		if ( ! empty( $row['code'] ) && ( false !== strpos( $row['code'], '/' ) || false !== strpos( $row['code'], '.' ) ) ) {
			// Ganze QR-Adresse oder „Nummer.Code“ eingefügt.
			list( $n, $c ) = ctc_parse_token( $row['code'] );
			$row['code']   = $c;
			if ( empty( $row['nummer'] ) ) {
				$row['nummer'] = $n;
			}
		}
		if ( ! empty( $row['nummer'] ) ) {
			$rows[] = $row;
		}
	}
	return $rows;
}

/** Legt Karten an oder aktualisiert sie (Schlüssel: Nummer). */
function ctc_import_cards( array $rows ) {
	global $wpdb;
	$t      = ctc_table( 'cards' );
	$now    = current_time( 'mysql' );
	$result = array(
		'neu'       => 0,
		'geaendert' => 0,
		'ohne_code' => 0,
	);
	foreach ( $rows as $row ) {
		$data = array();
		foreach ( array( 'kartenart', 'kategorie', 'name', 'telefon', 'email' ) as $f ) {
			if ( isset( $row[ $f ] ) ) {
				$data[ $f ] = sanitize_text_field( $row[ $f ] );
			}
		}
		if ( isset( $data['email'] ) ) {
			$data['email'] = sanitize_email( $data['email'] );
		}
		foreach ( array( 'gueltig_ab', 'gueltig_bis' ) as $f ) {
			if ( isset( $row[ $f ] ) ) {
				$data[ $f ] = ctc_parse_date( $row[ $f ] );
			}
		}
		if ( ! empty( $row['code'] ) ) {
			$data['code'] = strtoupper( preg_replace( '/[^A-Za-z0-9]/', '', $row['code'] ) );
		}
		$nummer   = sanitize_text_field( $row['nummer'] );
		$existing = ctc_get_card_by_nummer( $nummer );
		$data['updated_at'] = $now;
		if ( $existing ) {
			$wpdb->update( $t, $data, array( 'id' => $existing->id ) );
			$result['geaendert']++;
			$code = isset( $data['code'] ) ? $data['code'] : $existing->code;
		} else {
			$data['nummer']     = $nummer;
			$data['created_at'] = $now;
			$wpdb->insert( $t, $data );
			$result['neu']++;
			$code = isset( $data['code'] ) ? $data['code'] : '';
		}
		if ( '' === $code ) {
			$result['ohne_code']++;
		}
	}
	return $result;
}

/** Alle Events mit Anzahl Gäste, neueste zuerst. */
function ctc_get_events( $limit = 200 ) {
	global $wpdb;
	$t = ctc_table( 'checkins' );
	return $wpdb->get_results(
		$wpdb->prepare(
			"SELECT event_key, MAX(event_name) AS event_name, MAX(event_date) AS event_date, COUNT(*) AS gaeste, MIN(scanned_at) AS erster, MAX(scanned_at) AS letzter
			FROM $t GROUP BY event_key ORDER BY event_date DESC, letzter DESC LIMIT %d",
			$limit
		)
	);
}

/** Gäste eines Events mit Kartendaten. */
function ctc_get_event_guests( $event_key ) {
	global $wpdb;
	$t = ctc_table( 'checkins' );
	$c = ctc_table( 'cards' );
	return $wpdb->get_results(
		$wpdb->prepare(
			"SELECT k.*, c.nummer, c.name, c.kartenart, c.kategorie, c.telefon, c.email
			FROM $t k LEFT JOIN $c c ON c.id = k.card_id
			WHERE k.event_key = %s ORDER BY k.scanned_at ASC",
			$event_key
		)
	);
}

/** Besuche einer Karte. */
function ctc_get_card_visits( $card_id ) {
	global $wpdb;
	$t = ctc_table( 'checkins' );
	return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $t WHERE card_id = %d ORDER BY scanned_at DESC", $card_id ) );
}
