<?php
/**
 * Seiten für das Handy der Security: /einlass/ (Scanner) und /clubkarte/<Nummer>.<Code> (Ziel des QR-Codes).
 */

defined( 'ABSPATH' ) || exit;

function ctc_page_start( $title ) {
	nocache_headers();
	header( 'Content-Type: text/html; charset=utf-8' );
	header( 'X-Robots-Tag: noindex, nofollow' );
	?><!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="robots" content="noindex, nofollow">
<meta name="theme-color" content="#0b0a09">
<title><?php echo esc_html( $title ); ?> · Cooltourszene</title>
<style>
:root{--bg:#0b0a09;--panel:#1a1714;--line:#332d26;--text:#f4efe6;--muted:#a89f92;--gold:#d4af37;--gruen:#1f9d55;--orange:#e08a00;--rot:#d64040}
*{box-sizing:border-box}
html,body{margin:0;background:var(--bg);color:var(--text);font:16px/1.45 system-ui,-apple-system,"Segoe UI",Roboto,sans-serif}
main{max-width:560px;margin:0 auto;padding:16px 16px 40px}
h1{font-size:20px;margin:4px 0 2px;letter-spacing:.04em}
.brand{color:var(--gold);font-weight:800;letter-spacing:.18em;font-size:13px;text-transform:uppercase}
.muted{color:var(--muted);font-size:14px}
.box{background:var(--panel);border:1px solid var(--line);border-radius:14px;padding:16px;margin:14px 0}
a{color:var(--gold)}
button,.btn{display:inline-block;border:0;border-radius:10px;padding:12px 16px;font:600 16px system-ui,sans-serif;background:var(--gold);color:#1a1405;text-decoration:none;cursor:pointer}
.btn.ghost,button.ghost{background:transparent;color:var(--text);border:1px solid var(--line)}
input[type=text],select{width:100%;padding:12px;border-radius:10px;border:1px solid var(--line);background:#0f0d0b;color:var(--text);font-size:16px}
label{display:block;margin:10px 0 6px;font-weight:600}
.row{display:flex;gap:8px;align-items:center}
.row>*{flex:1}
.row>button{flex:0 0 auto}
.event{display:flex;justify-content:space-between;align-items:center;gap:10px}
.count{font-size:28px;font-weight:800;color:var(--gold);text-align:right;line-height:1}
.count small{display:block;font-size:12px;color:var(--muted);font-weight:500}
.cam{position:relative;border-radius:14px;overflow:hidden;background:#000;aspect-ratio:1/1}
.cam video{width:100%;height:100%;object-fit:cover;display:block}
.cam .frame{position:absolute;inset:18%;border:3px solid rgba(212,175,55,.85);border-radius:16px}
.cam .hint{position:absolute;left:0;right:0;bottom:10px;text-align:center;font-size:14px;color:#fff;text-shadow:0 1px 3px #000}
.result{border-radius:14px;padding:16px;margin:14px 0;color:#fff}
.result.gruen{background:var(--gruen)}.result.orange{background:var(--orange)}.result.rot{background:var(--rot)}
.result .t{font-size:26px;font-weight:800;margin:0 0 2px}
.result .r{margin:0 0 10px;opacity:.95}
.result dl{margin:0;display:grid;grid-template-columns:auto 1fr;gap:4px 12px;background:rgba(0,0,0,.18);border-radius:10px;padding:10px 12px}
.result dt{opacity:.8}.result dd{margin:0;font-weight:600;word-break:break-word}
.result dd a{color:#fff}
.name{font-size:22px;font-weight:800;margin:6px 0 10px}
ul.list{list-style:none;margin:0;padding:0}
ul.list li{display:flex;justify-content:space-between;gap:10px;padding:8px 0;border-top:1px solid var(--line)}
ul.list li:first-child{border-top:0}
.hidden{display:none}
</style>
</head>
<body><main>
<div class="brand">Cooltourszene</div>
	<?php
}

function ctc_page_end() {
	echo "</main></body></html>";
}

/** Ziel des QR-Codes auf der Karte. Ohne Security-Login werden keine Daten gezeigt. */
function ctc_render_card_page( $token ) {
	if ( ! current_user_can( 'ctc_scan' ) ) {
		ctc_page_start( 'Clubkarte' );
		?>
		<h1>Clubkarte</h1>
		<div class="box">
			<p>Diese Clubkarte wird beim Einlass von unserer Security gescannt.</p>
			<p class="muted">Die Mitgliedsdaten sind nur für angemeldete Mitarbeiter sichtbar.</p>
		</div>
		<p><a class="btn ghost" href="<?php echo esc_url( wp_login_url( home_url( add_query_arg( array() ) ) ) ); ?>">Security-Login</a>
		<a class="btn ghost" href="<?php echo esc_url( home_url( '/' ) ); ?>">Zur Webseite</a></p>
		<?php
		ctc_page_end();
		return;
	}

	$result = ctc_process_scan( $token );
	$event  = ctc_get_active_event();
	ctc_page_start( 'Karte prüfen' );
	echo '<h1>' . esc_html( $event ? $event['name'] : 'Kein Event gewählt' ) . '</h1>';
	ctc_render_result( $result );
	echo '<p><a class="btn" href="' . esc_url( ctc_door_url() ) . '">Zur Scan-Seite</a></p>';
	ctc_page_end();
}

function ctc_render_result( array $r ) {
	echo '<div class="result ' . esc_attr( $r['status'] ) . '">';
	echo '<p class="t">' . esc_html( $r['title'] ) . '</p>';
	echo '<p class="r">' . esc_html( $r['reason'] ) . '</p>';
	if ( ! empty( $r['card'] ) ) {
		$c = $r['card'];
		echo '<div class="name">' . esc_html( $c['name'] ? $c['name'] : '(ohne Namen)' ) . '</div><dl>';
		$rows = array(
			'Karte'      => trim( $c['kartenart'] . ' ' . $c['kategorie'] ),
			'Nummer'     => $c['nummer'],
			'Gültig bis' => $c['gueltig_bis'],
			'Besuche'    => (string) $r['besuche'],
			'Notiz'      => $c['notiz'],
		);
		foreach ( $rows as $k => $v ) {
			if ( '' !== $v ) {
				echo '<dt>' . esc_html( $k ) . '</dt><dd>' . esc_html( $v ) . '</dd>';
			}
		}
		if ( $c['telefon'] ) {
			echo '<dt>Telefon</dt><dd><a href="tel:' . esc_attr( preg_replace( '/[^0-9+]/', '', $c['telefon'] ) ) . '">' . esc_html( $c['telefon'] ) . '</a></dd>';
		}
		if ( $c['email'] ) {
			echo '<dt>E-Mail</dt><dd><a href="mailto:' . esc_attr( $c['email'] ) . '">' . esc_html( $c['email'] ) . '</a></dd>';
		}
		echo '</dl>';
	}
	echo '</div>';
}

/** Scan-Seite der Security. */
function ctc_render_door_page() {
	if ( ! is_user_logged_in() ) {
		auth_redirect();
	}
	if ( ! current_user_can( 'ctc_scan' ) ) {
		wp_die( 'Diese Seite ist nur für die Security.', 'Kein Zugriff', array( 'response' => 403 ) );
	}

	if ( 'POST' === $_SERVER['REQUEST_METHOD'] && isset( $_POST['ctc_event_nonce'] ) && wp_verify_nonce( sanitize_key( $_POST['ctc_event_nonce'] ), 'ctc_event' ) ) {
		$post_id = isset( $_POST['event_post'] ) ? absint( $_POST['event_post'] ) : 0;
		$name    = isset( $_POST['event_name'] ) ? sanitize_text_field( wp_unslash( $_POST['event_name'] ) ) : '';
		if ( $post_id && 'tribe_events' === get_post_type( $post_id ) ) {
			ctc_set_active_event( '', $post_id );
		} elseif ( '' !== $name ) {
			ctc_set_active_event( $name );
		}
		wp_safe_redirect( ctc_door_url() );
		exit;
	}

	$event  = ctc_get_active_event();
	$change = isset( $_GET['wechseln'] );
	ctc_page_start( 'Einlass' );

	if ( ! $event || $change ) {
		ctc_render_event_picker( $event );
		ctc_page_end();
		return;
	}

	$guests = ctc_get_event_guests( $event['key'] );
	?>
	<div class="box event">
		<div>
			<div class="muted">Einlass für</div>
			<h1><?php echo esc_html( $event['name'] ); ?></h1>
			<a href="<?php echo esc_url( add_query_arg( 'wechseln', '1', ctc_door_url() ) ); ?>">Event wechseln</a>
		</div>
		<div class="count"><span id="ctc-count"><?php echo count( $guests ); ?></span><small>Gäste</small></div>
	</div>

	<div class="cam" id="ctc-cam">
		<video id="ctc-video" playsinline muted></video>
		<div class="frame"></div>
		<div class="hint" id="ctc-hint">Kamera wird gestartet …</div>
	</div>
	<p class="muted hidden" id="ctc-nocam"></p>

	<div id="ctc-result"></div>

	<form class="box" id="ctc-manual">
		<label for="ctc-nummer">QR-Code kaputt? Kartennummer eingeben</label>
		<div class="row"><input type="text" id="ctc-nummer" placeholder="z. B. M-0042" autocomplete="off"><button type="submit">Prüfen</button></div>
	</form>

	<div class="box">
		<strong>Zuletzt eingecheckt</strong>
		<ul class="list" id="ctc-last">
		<?php foreach ( array_slice( array_reverse( $guests ), 0, 8 ) as $g ) : ?>
			<li><span><?php echo esc_html( $g->name ? $g->name : $g->nummer ); ?></span><span class="muted"><?php echo esc_html( mysql2date( 'H:i', $g->scanned_at ) ); ?></span></li>
		<?php endforeach; ?>
		</ul>
	</div>
	<p class="muted">Angemeldet als <?php echo esc_html( wp_get_current_user()->display_name ); ?> · <a href="<?php echo esc_url( wp_logout_url( ctc_door_url() ) ); ?>">Abmelden</a></p>

	<script src="<?php echo esc_url( CTC_URL . 'assets/jsQR.js?ver=1.4.0' ); ?>"></script>
	<script>
	window.CTC = <?php echo wp_json_encode( array( 'rest' => esc_url_raw( rest_url( 'cooltour-checkin/v1/scan' ) ), 'nonce' => wp_create_nonce( 'wp_rest' ) ) ); ?>;
	</script>
	<script src="<?php echo esc_url( CTC_URL . 'assets/scanner.js?ver=' . CTC_VERSION ); ?>"></script>
	<?php
	ctc_page_end();
}

function ctc_render_event_picker( $current ) {
	$events = ctc_calendar_events_nearby();
	?>
	<h1>Welches Event?</h1>
	<p class="muted">Wähle das Event, bei dem du heute den Einlass machst. Alle Scans werden diesem Event zugeordnet.</p>
	<form method="post" class="box">
		<?php wp_nonce_field( 'ctc_event', 'ctc_event_nonce' ); ?>
		<?php if ( $events ) : ?>
			<label for="event_post">Aus dem Veranstaltungskalender</label>
			<select name="event_post" id="event_post">
				<option value="">– bitte wählen –</option>
				<?php foreach ( $events as $e ) : ?>
					<option value="<?php echo (int) $e->ID; ?>" <?php selected( $current && (int) $current['post_id'] === $e->ID ); ?>>
						<?php echo esc_html( wp_date( 'd.m.', strtotime( get_post_meta( $e->ID, '_EventStartDate', true ) ) ) . ' ' . get_the_title( $e ) ); ?>
					</option>
				<?php endforeach; ?>
			</select>
			<label for="event_name">… oder Name eingeben</label>
		<?php else : ?>
			<label for="event_name">Name des Events</label>
		<?php endif; ?>
		<input type="text" name="event_name" id="event_name" placeholder="z. B. Halloween Rocknight" value="<?php echo esc_attr( $current && ! $current['post_id'] ? $current['name'] : '' ); ?>">
		<p><button type="submit">Einlass starten</button>
		<?php if ( $current ) : ?>
			<a class="btn ghost" href="<?php echo esc_url( ctc_door_url() ); ?>">Abbrechen</a>
		<?php endif; ?></p>
	</form>
	<?php
}
