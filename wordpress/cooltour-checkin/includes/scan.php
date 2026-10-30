<?php
/**
 * Scannen: Event wählen, Karte prüfen, Check-in speichern.
 */

defined( 'ABSPATH' ) || exit;

/** Das Event, das die Security gerade kontrolliert (pro Benutzer), oder null. */
function ctc_get_active_event( $user_id = 0 ) {
	$user_id = $user_id ? $user_id : get_current_user_id();
	$event   = get_user_meta( $user_id, 'ctc_active_event', true );
	if ( ! is_array( $event ) || empty( $event['key'] ) || ( time() - (int) $event['set_at'] ) > CTC_EVENT_TTL ) {
		return null;
	}
	return $event;
}

function ctc_set_active_event( $name, $post_id = 0, $user_id = 0 ) {
	$user_id = $user_id ? $user_id : get_current_user_id();
	$date    = current_time( 'Y-m-d' );
	if ( $post_id ) {
		$start = get_post_meta( $post_id, '_EventStartDate', true );
		if ( $start ) {
			$date = substr( $start, 0, 10 );
		}
		$name = get_the_title( $post_id );
		$key  = 'post-' . $post_id;
	} else {
		$name = sanitize_text_field( $name );
		$key  = $date . '-' . sanitize_title( $name );
	}
	$event = array(
		'key'     => $key,
		'name'    => $name,
		'post_id' => (int) $post_id,
		'date'    => $date,
		'set_at'  => time(),
	);
	update_user_meta( $user_id, 'ctc_active_event', $event );
	return $event;
}

/**
 * Events aus dem Veranstaltungskalender (The Events Calendar), die gestern, heute oder morgen beginnen.
 * Gestern, weil eine Partynacht über Mitternacht geht.
 */
function ctc_calendar_events_nearby() {
	if ( ! post_type_exists( 'tribe_events' ) ) {
		return array();
	}
	$from = wp_date( 'Y-m-d 00:00:00', time() - DAY_IN_SECONDS );
	$to   = wp_date( 'Y-m-d 23:59:59', time() + DAY_IN_SECONDS );
	return get_posts(
		array(
			'post_type'        => 'tribe_events',
			'post_status'      => 'publish',
			'posts_per_page'   => 20,
			'meta_key'         => '_EventStartDate',
			'orderby'          => 'meta_value',
			'order'            => 'ASC',
			'suppress_filters' => true,
			'meta_query'       => array(
				array(
					'key'     => '_EventStartDate',
					'value'   => array( $from, $to ),
					'compare' => 'BETWEEN',
					'type'    => 'DATETIME',
				),
			),
		)
	);
}

/**
 * Prüft eine gescannte Karte und speichert den Check-in für das aktive Event.
 * $raw ist der QR-Inhalt (Adresse oder „Nummer.Code“); $nummer erlaubt die Eingabe von Hand.
 */
function ctc_process_scan( $raw, $nummer = '' ) {
	global $wpdb;
	$card = '' !== $nummer ? ctc_get_card_by_nummer( sanitize_text_field( $nummer ) ) : ctc_find_card_by_token( $raw );
	if ( ! $card ) {
		return array(
			'status' => 'rot',
			'title'  => 'Karte unbekannt',
			'reason' => '' !== $nummer ? 'Keine Karte mit dieser Nummer.' : 'Dieser QR-Code gehört zu keiner Clubkarte.',
		);
	}

	list( $ok, $reason ) = ctc_card_validity( $card );
	$event  = ctc_get_active_event();
	$result = array(
		'status' => $ok ? 'gruen' : 'rot',
		'title'  => $ok ? 'Gültig – Einlass' : 'Kein Einlass',
		'reason' => $reason,
		'card'   => array(
			'id'          => (int) $card->id,
			'nummer'      => $card->nummer,
			'name'        => $card->name,
			'telefon'     => $card->telefon,
			'email'       => $card->email,
			'kartenart'   => $card->kartenart,
			'kategorie'   => $card->kategorie,
			'gueltig_bis' => ctc_format_date( $card->gueltig_bis ),
			'notiz'       => (string) $card->notiz,
		),
		'event'  => $event ? $event['name'] : null,
	);

	if ( $ok && $event ) {
		$t        = ctc_table( 'checkins' );
		$existing = $wpdb->get_var( $wpdb->prepare( "SELECT scanned_at FROM $t WHERE card_id = %d AND event_key = %s", $card->id, $event['key'] ) );
		if ( $existing ) {
			$result['status'] = 'orange';
			$result['title']  = 'Schon eingecheckt';
			$result['reason'] = 'Bereits um ' . mysql2date( 'H:i', $existing ) . ' Uhr gescannt.';
		} else {
			$wpdb->insert(
				$t,
				array(
					'card_id'       => $card->id,
					'event_key'     => $event['key'],
					'event_name'    => $event['name'],
					'event_post_id' => $event['post_id'],
					'event_date'    => $event['date'],
					'scanned_at'    => current_time( 'mysql' ),
					'scanned_by'    => get_current_user_id(),
				)
			);
		}
	} elseif ( $ok ) {
		$result['reason'] = 'Gültig. Achtung: kein Event gewählt, der Besuch wird nicht gespeichert.';
	}
	$result['besuche'] = ctc_card_visit_count( $card->id );
	return $result;
}

function ctc_event_guest_count( $event_key ) {
	global $wpdb;
	$t = ctc_table( 'checkins' );
	return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $t WHERE event_key = %s", $event_key ) );
}

add_action(
	'rest_api_init',
	function () {
		register_rest_route(
			'cooltour-checkin/v1',
			'/scan',
			array(
				'methods'             => 'POST',
				'permission_callback' => function () {
					return current_user_can( 'ctc_scan' );
				},
				'args'                => array(
					'qr'     => array(
						'type'    => 'string',
						'default' => '',
					),
					'nummer' => array(
						'type'    => 'string',
						'default' => '',
					),
				),
				'callback'            => function ( WP_REST_Request $req ) {
					$result = ctc_process_scan( (string) $req['qr'], trim( (string) $req['nummer'] ) );
					$event  = ctc_get_active_event();
					$result['gaeste'] = $event ? ctc_event_guest_count( $event['key'] ) : 0;
					return rest_ensure_response( $result );
				},
			)
		);
	}
);
