<?php
/**
 * Backend: Übersicht „wer war bei welchem Event“, Kartenliste, Import aus dem Kartenstudio, Excel-Export.
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'admin_menu',
	function () {
		add_menu_page( 'Check-in', 'Check-in', 'ctc_manage', 'ctc-events', 'ctc_admin_events', 'dashicons-id-alt', 26 );
		add_submenu_page( 'ctc-events', 'Events', 'Events', 'ctc_manage', 'ctc-events', 'ctc_admin_events' );
		add_submenu_page( 'ctc-events', 'Karten', 'Karten', 'ctc_manage', 'ctc-cards', 'ctc_admin_cards' );
		add_submenu_page( 'ctc-events', 'Karten importieren', 'Karten importieren', 'ctc_manage', 'ctc-import', 'ctc_admin_import' );
		add_submenu_page( 'ctc-events', 'Anleitung', 'Anleitung', 'ctc_manage', 'ctc-help', 'ctc_admin_help' );
	}
);

function ctc_admin_url( $page, $args = array() ) {
	return add_query_arg( $args, admin_url( 'admin.php?page=' . $page ) );
}

function ctc_export_url( $what, $args = array() ) {
	return wp_nonce_url( add_query_arg( array_merge( array( 'action' => 'ctc_export', 'what' => $what ), $args ), admin_url( 'admin-post.php' ) ), 'ctc_export' );
}

function ctc_admin_notice() {
	if ( isset( $_GET['ctc_msg'] ) ) {
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( sanitize_text_field( wp_unslash( $_GET['ctc_msg'] ) ) ) . '</p></div>';
	}
}

/** Liste der Events, oder die Gäste eines Events. */
function ctc_admin_events() {
	echo '<div class="wrap">';
	ctc_admin_notice();
	$key = isset( $_GET['event'] ) ? sanitize_text_field( wp_unslash( $_GET['event'] ) ) : '';
	if ( '' !== $key ) {
		$guests = ctc_get_event_guests( $key );
		$name   = $guests ? $guests[0]->event_name : $key;
		$date   = $guests ? ctc_format_date( $guests[0]->event_date ) : '';
		echo '<h1 class="wp-heading-inline">' . esc_html( $name ) . ' <small>' . esc_html( $date ) . '</small></h1> ';
		echo '<a class="page-title-action" href="' . esc_url( ctc_export_url( 'event', array( 'event' => $key ) ) ) . '">Als Excel (CSV) herunterladen</a>';
		echo '<p><a href="' . esc_url( ctc_admin_url( 'ctc-events' ) ) . '">← Alle Events</a> · ' . count( $guests ) . ' Gäste</p>';
		echo '<table class="widefat striped"><thead><tr><th>Uhrzeit</th><th>Name</th><th>Nummer</th><th>Karte</th><th>Telefon</th><th>E-Mail</th><th>Gescannt von</th></tr></thead><tbody>';
		foreach ( $guests as $g ) {
			$by = $g->scanned_by ? get_userdata( $g->scanned_by ) : null;
			echo '<tr><td>' . esc_html( mysql2date( 'd.m. H:i', $g->scanned_at ) ) . '</td>';
			echo '<td><a href="' . esc_url( ctc_admin_url( 'ctc-cards', array( 'card' => $g->card_id ) ) ) . '">' . esc_html( $g->name ? $g->name : '(gelöschte Karte)' ) . '</a></td>';
			echo '<td>' . esc_html( $g->nummer ) . '</td><td>' . esc_html( trim( $g->kartenart . ' ' . $g->kategorie ) ) . '</td>';
			echo '<td>' . esc_html( $g->telefon ) . '</td><td>' . esc_html( $g->email ) . '</td>';
			echo '<td>' . esc_html( $by ? $by->display_name : '' ) . '</td></tr>';
		}
		if ( ! $guests ) {
			echo '<tr><td colspan="7">Noch niemand eingecheckt.</td></tr>';
		}
		echo '</tbody></table></div>';
		return;
	}

	$events = ctc_get_events();
	echo '<h1 class="wp-heading-inline">Wer war bei welchem Event?</h1> ';
	echo '<a class="page-title-action" href="' . esc_url( ctc_export_url( 'all' ) ) . '">Alle Check-ins als Excel (CSV)</a>';
	echo '<p>Scan-Seite für die Security: <a href="' . esc_url( ctc_door_url() ) . '" target="_blank">' . esc_html( ctc_door_url() ) . '</a></p>';
	echo '<table class="widefat striped"><thead><tr><th>Datum</th><th>Event</th><th>Gäste</th><th>Erster Scan</th><th>Letzter Scan</th></tr></thead><tbody>';
	foreach ( $events as $e ) {
		echo '<tr><td>' . esc_html( ctc_format_date( $e->event_date ) ) . '</td>';
		echo '<td><strong><a href="' . esc_url( ctc_admin_url( 'ctc-events', array( 'event' => $e->event_key ) ) ) . '">' . esc_html( $e->event_name ) . '</a></strong></td>';
		echo '<td>' . (int) $e->gaeste . '</td><td>' . esc_html( mysql2date( 'H:i', $e->erster ) ) . '</td><td>' . esc_html( mysql2date( 'H:i', $e->letzter ) ) . '</td></tr>';
	}
	if ( ! $events ) {
		echo '<tr><td colspan="5">Noch keine Check-ins. Sobald die Security Karten scannt, erscheinen die Events hier.</td></tr>';
	}
	echo '</tbody></table></div>';
}

/** Kartenliste mit Suche, oder Detail einer Karte mit allen Besuchen. */
function ctc_admin_cards() {
	global $wpdb;
	echo '<div class="wrap">';
	ctc_admin_notice();
	$id = isset( $_GET['card'] ) ? absint( $_GET['card'] ) : 0;
	if ( $id ) {
		$card = ctc_get_card( $id );
		if ( ! $card ) {
			echo '<h1>Karte nicht gefunden</h1><p><a href="' . esc_url( ctc_admin_url( 'ctc-cards' ) ) . '">← Alle Karten</a></p></div>';
			return;
		}
		list( $ok, $reason ) = ctc_card_validity( $card );
		$visits              = ctc_get_card_visits( $id );
		echo '<h1>' . esc_html( $card->name ) . ' <small>' . esc_html( $card->nummer ) . '</small></h1>';
		echo '<p><a href="' . esc_url( ctc_admin_url( 'ctc-cards' ) ) . '">← Alle Karten</a></p>';
		echo '<table class="form-table"><tbody>';
		$rows = array(
			'Karte'      => trim( $card->kartenart . ' ' . $card->kategorie ),
			'Telefon'    => $card->telefon,
			'E-Mail'     => $card->email,
			'Gültig'     => trim( ctc_format_date( $card->gueltig_ab ) . ' – ' . ctc_format_date( $card->gueltig_bis ), ' –' ),
			'Status'     => $reason,
			'QR-Code'    => $card->code ? 'hinterlegt' : 'fehlt – Kartenliste aus dem Kartenstudio importieren',
		);
		foreach ( $rows as $k => $v ) {
			echo '<tr><th>' . esc_html( $k ) . '</th><td>' . esc_html( $v ) . '</td></tr>';
		}
		echo '</tbody></table>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'ctc_card_' . $id );
		echo '<input type="hidden" name="action" value="ctc_card"><input type="hidden" name="card" value="' . (int) $id . '">';
		echo '<p><label for="notiz"><strong>Notiz für die Security</strong> (wird beim Scannen angezeigt)</label><br><input type="text" class="regular-text" name="notiz" id="notiz" value="' . esc_attr( $card->notiz ) . '"></p>';
		echo '<p><label><input type="checkbox" name="gesperrt" value="1" ' . checked( (int) $card->gesperrt, 1, false ) . '> Karte sperren (z. B. verloren oder Hausverbot)</label></p>';
		submit_button( 'Speichern', 'primary', 'save', false );
		echo ' ';
		submit_button( 'Karte löschen', 'delete', 'delete', false, array( 'onclick' => "return confirm('Karte und alle Besuche löschen?')" ) );
		echo '</form>';
		echo '<h2>Besuche (' . count( $visits ) . ')</h2><table class="widefat striped"><thead><tr><th>Datum</th><th>Event</th><th>Uhrzeit</th></tr></thead><tbody>';
		foreach ( $visits as $v ) {
			echo '<tr><td>' . esc_html( ctc_format_date( $v->event_date ) ) . '</td><td><a href="' . esc_url( ctc_admin_url( 'ctc-events', array( 'event' => $v->event_key ) ) ) . '">' . esc_html( $v->event_name ) . '</a></td><td>' . esc_html( mysql2date( 'H:i', $v->scanned_at ) ) . '</td></tr>';
		}
		if ( ! $visits ) {
			echo '<tr><td colspan="3">Noch keine Besuche.</td></tr>';
		}
		echo '</tbody></table></div>';
		return;
	}

	$s     = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
	$c     = ctc_table( 'cards' );
	$k     = ctc_table( 'checkins' );
	$where = '';
	if ( '' !== $s ) {
		$like  = '%' . $wpdb->esc_like( $s ) . '%';
		$where = $wpdb->prepare( 'WHERE c.name LIKE %s OR c.nummer LIKE %s OR c.email LIKE %s OR c.telefon LIKE %s', $like, $like, $like, $like );
	}
	$cards = $wpdb->get_results( "SELECT c.*, COUNT(k.id) AS besuche, MAX(k.scanned_at) AS zuletzt FROM $c c LEFT JOIN $k k ON k.card_id = c.id $where GROUP BY c.id ORDER BY c.nummer ASC LIMIT 1000" );

	echo '<h1 class="wp-heading-inline">Karten</h1> ';
	echo '<a class="page-title-action" href="' . esc_url( ctc_admin_url( 'ctc-import' ) ) . '">Karten importieren</a> ';
	echo '<a class="page-title-action" href="' . esc_url( ctc_export_url( 'cards' ) ) . '">Als Excel (CSV)</a>';
	echo '<form method="get"><input type="hidden" name="page" value="ctc-cards"><p class="search-box"><input type="search" name="s" value="' . esc_attr( $s ) . '" placeholder="Name, Nummer, Mail …"> <button class="button">Suchen</button></p></form>';
	echo '<table class="widefat striped"><thead><tr><th>Nummer</th><th>Name</th><th>Karte</th><th>Gültig bis</th><th>Status</th><th>Besuche</th><th>Zuletzt da</th></tr></thead><tbody>';
	foreach ( $cards as $card ) {
		list( $ok, $reason ) = ctc_card_validity( $card );
		echo '<tr><td>' . esc_html( $card->nummer ) . '</td>';
		echo '<td><strong><a href="' . esc_url( ctc_admin_url( 'ctc-cards', array( 'card' => $card->id ) ) ) . '">' . esc_html( $card->name ? $card->name : '(ohne Namen)' ) . '</a></strong></td>';
		echo '<td>' . esc_html( trim( $card->kartenart . ' ' . $card->kategorie ) ) . '</td><td>' . esc_html( ctc_format_date( $card->gueltig_bis ) ) . '</td>';
		echo '<td' . ( $ok ? '' : ' style="color:#b32d2e"' ) . '>' . esc_html( $card->code ? $reason : 'QR-Code fehlt' ) . '</td>';
		echo '<td>' . (int) $card->besuche . '</td><td>' . esc_html( $card->zuletzt ? mysql2date( 'd.m.Y', $card->zuletzt ) : '' ) . '</td></tr>';
	}
	if ( ! $cards ) {
		echo '<tr><td colspan="7">Keine Karten. Exportiere die Kartenliste im Kartenstudio und importiere sie hier.</td></tr>';
	}
	echo '</tbody></table></div>';
}

function ctc_admin_import() {
	echo '<div class="wrap"><h1>Karten importieren</h1>';
	ctc_admin_notice();
	echo '<p>Im Kartenstudio im Tab <strong>Liste</strong> auf <strong>„Für Webseite exportieren“</strong> klicken und die Datei hier hochladen, oder die Tabelle aus Excel kopieren und einfügen.</p>';
	echo '<p>Spalten: Nummer, Code, Kartenart, Kategorie, Name, Telefon, E-Mail, Gültig ab, Gültig bis. Vorhandene Karten (gleiche Nummer) werden aktualisiert, Besuche bleiben erhalten.</p>';
	echo '<form method="post" enctype="multipart/form-data" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
	wp_nonce_field( 'ctc_import' );
	echo '<input type="hidden" name="action" value="ctc_import">';
	echo '<p><input type="file" name="datei" accept=".csv,.txt,text/csv"></p>';
	echo '<p><textarea name="text" rows="10" class="large-text code" placeholder="… oder hier einfügen"></textarea></p>';
	submit_button( 'Importieren' );
	echo '</form></div>';
}

function ctc_admin_help() {
	?>
	<div class="wrap">
		<h1>Check-in: So funktioniert es</h1>
		<ol>
			<li><strong>Karten vorbereiten:</strong> Im Kartenstudio bekommt jede Karte eine Nummer und einen Geheimcode. Der QR-Code auf der Karte enthält nur die Adresse<br><code><?php echo esc_html( ctc_card_url_example() ); ?></code><br>und keine Kontaktdaten.</li>
			<li><strong>Kartenliste hochladen:</strong> Nach dem Drucken im Kartenstudio „Für Webseite exportieren“ und unter <a href="<?php echo esc_url( ctc_admin_url( 'ctc-import' ) ); ?>">Karten importieren</a> hochladen.</li>
			<li><strong>Security anlegen:</strong> Unter <a href="<?php echo esc_url( admin_url( 'user-new.php' ) ); ?>">Benutzer → Neu hinzufügen</a> mit der Rolle <em>Security (Einlass)</em>. Diese Benutzer sehen nur die Scan-Seite.</li>
			<li><strong>Beim Event:</strong> Die Security öffnet am Handy <a href="<?php echo esc_url( ctc_door_url() ); ?>"><?php echo esc_html( ctc_door_url() ); ?></a>, meldet sich an, wählt das Event und scannt. Grün = Einlass, Orange = schon drin, Rot = ungültig/gesperrt. Es geht auch mit der normalen Handy-Kamera: Der QR-Code öffnet die Karte, und wer als Security angemeldet ist, sieht die Daten und der Besuch wird gespeichert.</li>
			<li><strong>Übersicht:</strong> Unter <a href="<?php echo esc_url( ctc_admin_url( 'ctc-events' ) ); ?>">Check-in → Events</a> steht pro Event, wer da war, mit Export für Excel.</li>
		</ol>
		<h2>Datenschutz</h2>
		<p>Wer die Karte ohne Anmeldung scannt, sieht keine persönlichen Daten. Name, Telefon und E-Mail sehen nur angemeldete Security-Leute und Administratoren. Mitglieder sollten bei der Kartenausgabe darüber informiert werden, dass ihre Besuche erfasst werden.</p>
	</div>
	<?php
}

add_action(
	'admin_post_ctc_import',
	function () {
		if ( ! current_user_can( 'ctc_manage' ) ) {
			wp_die( 'Kein Zugriff' );
		}
		check_admin_referer( 'ctc_import' );
		$text = isset( $_POST['text'] ) ? wp_unslash( $_POST['text'] ) : '';
		if ( ! empty( $_FILES['datei']['tmp_name'] ) && is_uploaded_file( $_FILES['datei']['tmp_name'] ) ) {
			$text = file_get_contents( $_FILES['datei']['tmp_name'] );
		}
		if ( ! mb_check_encoding( $text, 'UTF-8' ) ) {
			$text = mb_convert_encoding( $text, 'UTF-8', 'Windows-1252' );
		}
		$rows = ctc_parse_card_csv( $text );
		if ( ! $rows ) {
			$msg = 'Keine Karten gefunden. Die erste Zeile muss die Spaltennamen enthalten (mindestens „Nummer“).';
		} else {
			$r   = ctc_import_cards( $rows );
			$msg = sprintf( '%d neue Karten, %d aktualisiert.', $r['neu'], $r['geaendert'] );
			if ( $r['ohne_code'] ) {
				$msg .= sprintf( ' Achtung: %d Karten ohne Code – deren QR-Code funktioniert nicht, nur die Eingabe der Nummer.', $r['ohne_code'] );
			}
		}
		wp_safe_redirect( ctc_admin_url( $rows ? 'ctc-cards' : 'ctc-import', array( 'ctc_msg' => rawurlencode( $msg ) ) ) );
		exit;
	}
);

add_action(
	'admin_post_ctc_card',
	function () {
		global $wpdb;
		$id = isset( $_POST['card'] ) ? absint( $_POST['card'] ) : 0;
		if ( ! current_user_can( 'ctc_manage' ) || ! $id ) {
			wp_die( 'Kein Zugriff' );
		}
		check_admin_referer( 'ctc_card_' . $id );
		if ( isset( $_POST['delete'] ) ) {
			$wpdb->delete( ctc_table( 'checkins' ), array( 'card_id' => $id ) );
			$wpdb->delete( ctc_table( 'cards' ), array( 'id' => $id ) );
			wp_safe_redirect( ctc_admin_url( 'ctc-cards', array( 'ctc_msg' => rawurlencode( 'Karte gelöscht.' ) ) ) );
			exit;
		}
		$wpdb->update(
			ctc_table( 'cards' ),
			array(
				'notiz'      => isset( $_POST['notiz'] ) ? sanitize_text_field( wp_unslash( $_POST['notiz'] ) ) : '',
				'gesperrt'   => empty( $_POST['gesperrt'] ) ? 0 : 1,
				'updated_at' => current_time( 'mysql' ),
			),
			array( 'id' => $id )
		);
		wp_safe_redirect( ctc_admin_url( 'ctc-cards', array( 'card' => $id, 'ctc_msg' => rawurlencode( 'Gespeichert.' ) ) ) );
		exit;
	}
);

/** CSV für Excel: UTF-8 mit BOM, Semikolon. */
add_action(
	'admin_post_ctc_export',
	function () {
		global $wpdb;
		if ( ! current_user_can( 'ctc_manage' ) ) {
			wp_die( 'Kein Zugriff' );
		}
		check_admin_referer( 'ctc_export' );
		$what = isset( $_GET['what'] ) ? sanitize_key( $_GET['what'] ) : 'all';
		$c    = ctc_table( 'cards' );
		$k    = ctc_table( 'checkins' );
		if ( 'cards' === $what ) {
			$file   = 'karten';
			$header = array( 'Nummer', 'Kartenart', 'Kategorie', 'Name', 'Telefon', 'E-Mail', 'Gültig ab', 'Gültig bis', 'Gesperrt', 'Besuche' );
			$data   = $wpdb->get_results( "SELECT c.nummer, c.kartenart, c.kategorie, c.name, c.telefon, c.email, c.gueltig_ab, c.gueltig_bis, c.gesperrt, COUNT(k.id) AS besuche FROM $c c LEFT JOIN $k k ON k.card_id = c.id GROUP BY c.id ORDER BY c.nummer", ARRAY_A );
		} else {
			$where  = '';
			$file   = 'checkins';
			if ( 'event' === $what && isset( $_GET['event'] ) ) {
				$key   = sanitize_text_field( wp_unslash( $_GET['event'] ) );
				$where = $wpdb->prepare( 'WHERE k.event_key = %s', $key );
				$file  = 'event-' . sanitize_file_name( $key );
			}
			$header = array( 'Event-Datum', 'Event', 'Uhrzeit', 'Nummer', 'Name', 'Kartenart', 'Kategorie', 'Telefon', 'E-Mail' );
			$data   = $wpdb->get_results( "SELECT k.event_date, k.event_name, k.scanned_at, c.nummer, c.name, c.kartenart, c.kategorie, c.telefon, c.email FROM $k k LEFT JOIN $c c ON c.id = k.card_id $where ORDER BY k.event_date DESC, k.scanned_at ASC", ARRAY_A );
		}
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $file . '-' . current_time( 'Y-m-d' ) . '.csv"' );
		$out = fopen( 'php://output', 'w' );
		fwrite( $out, "\xEF\xBB\xBF" );
		fputcsv( $out, $header, ';' );
		foreach ( $data as $row ) {
			foreach ( $row as $f => $v ) {
				if ( in_array( $f, array( 'event_date', 'gueltig_ab', 'gueltig_bis' ), true ) ) {
					$row[ $f ] = ctc_format_date( $v );
				} elseif ( 'scanned_at' === $f ) {
					$row[ $f ] = mysql2date( 'd.m.Y H:i', $v );
				} elseif ( 'gesperrt' === $f ) {
					$row[ $f ] = $v ? 'ja' : '';
				} elseif ( is_string( $v ) && preg_match( '/^[=+\-@]/', $v ) ) {
					$row[ $f ] = "'" . $v; // Keine Formeln in Excel
				}
			}
			fputcsv( $out, array_values( $row ), ';' );
		}
		fclose( $out );
		exit;
	}
);
