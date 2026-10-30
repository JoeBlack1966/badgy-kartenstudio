<?php
/**
 * Plugin Name: Cooltour Check-in
 * Description: Einlasskontrolle mit dem QR-Code der Cooltourszene-Clubkarten. Die Security scannt am Handy, die Übersicht zeigt, wer bei welchem Event war.
 * Version: 1.0.0
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: Cooltourszene
 * Text Domain: cooltour-checkin
 */

defined( 'ABSPATH' ) || exit;

define( 'CTC_VERSION', '1.0.0' );
define( 'CTC_DB_VERSION', '1' );
define( 'CTC_FILE', __FILE__ );
define( 'CTC_URL', plugin_dir_url( __FILE__ ) );

// Adresse im QR-Code: https://<seite>/clubkarte/<Nummer>.<Code>
define( 'CTC_CARD_PATH', 'clubkarte' );
// Scan-Seite für die Security: https://<seite>/einlass/
define( 'CTC_DOOR_PATH', 'einlass' );
// So lange gilt ein gewähltes Event, danach muss die Security neu wählen.
define( 'CTC_EVENT_TTL', 20 * HOUR_IN_SECONDS );

require_once __DIR__ . '/includes/data.php';
require_once __DIR__ . '/includes/scan.php';
require_once __DIR__ . '/includes/door.php';
require_once __DIR__ . '/includes/admin.php';

register_activation_hook( __FILE__, 'ctc_activate' );
register_deactivation_hook( __FILE__, 'ctc_deactivate' );

function ctc_activate() {
	ctc_install_tables();
	ctc_install_roles();
	ctc_add_rewrite_rules();
	flush_rewrite_rules();
}

function ctc_deactivate() {
	flush_rewrite_rules();
}

function ctc_install_roles() {
	add_role(
		'ctc_security',
		'Security (Einlass)',
		array(
			'read'     => true,
			'ctc_scan' => true,
		)
	);
	$admin = get_role( 'administrator' );
	if ( $admin ) {
		$admin->add_cap( 'ctc_scan' );
		$admin->add_cap( 'ctc_manage' );
	}
}

add_action(
	'plugins_loaded',
	function () {
		if ( get_option( 'ctc_db_version' ) !== CTC_DB_VERSION ) {
			ctc_install_tables();
			ctc_install_roles();
		}
	}
);

add_action( 'init', 'ctc_add_rewrite_rules' );

function ctc_add_rewrite_rules() {
	add_rewrite_rule( '^' . CTC_CARD_PATH . '/([A-Za-z0-9._-]+)/?$', 'index.php?ctc_token=$matches[1]', 'top' );
	add_rewrite_rule( '^' . CTC_DOOR_PATH . '/?$', 'index.php?ctc_door=1', 'top' );
}

add_filter(
	'query_vars',
	function ( $vars ) {
		$vars[] = 'ctc_token';
		$vars[] = 'ctc_door';
		return $vars;
	}
);

add_action(
	'template_redirect',
	function () {
		$token = get_query_var( 'ctc_token' );
		if ( $token ) {
			ctc_render_card_page( (string) $token );
			exit;
		}
		if ( get_query_var( 'ctc_door' ) ) {
			ctc_render_door_page();
			exit;
		}
	}
);

/** Security-Leute landen nach dem Login direkt auf der Scan-Seite und sehen keine Admin-Leiste. */
add_filter(
	'login_redirect',
	function ( $redirect_to, $requested, $user ) {
		if ( $user instanceof WP_User && in_array( 'ctc_security', (array) $user->roles, true ) && ( ! $requested || false !== strpos( $requested, 'wp-admin' ) ) ) {
			return ctc_door_url();
		}
		return $redirect_to;
	},
	10,
	3
);

add_filter(
	'show_admin_bar',
	function ( $show ) {
		$user = wp_get_current_user();
		return in_array( 'ctc_security', (array) $user->roles, true ) ? false : $show;
	}
);

function ctc_door_url() {
	return get_option( 'permalink_structure' ) ? home_url( '/' . CTC_DOOR_PATH . '/' ) : add_query_arg( 'ctc_door', '1', home_url( '/' ) );
}

function ctc_card_url_example() {
	return get_option( 'permalink_structure' ) ? home_url( '/' . CTC_CARD_PATH . '/{{Nummer}}.{{Code}}' ) : home_url( '/?ctc_token={{Nummer}}.{{Code}}' );
}
