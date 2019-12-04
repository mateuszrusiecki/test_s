<?php
/**
 * WordPress Ajax Process Execution
 *
 * @package WordPress
 * @subpackage Administration
 *
 * @link https://codex.wordpress.org/AJAX_in_Plugins
 */


if ( 'OPTIONS' == $_SERVER['REQUEST_METHOD'] ) {
    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Headers: *");
    //header("Access-Control-Allow-Methods: *");
    die;
}

/**
 * Executing Ajax process.
 *
 * @since 2.1.0
 */
define( 'DOING_AJAX', true );
if ( ! defined( 'WP_ADMIN' ) ) {
	define( 'WP_ADMIN', true );
}

/** Load WordPress Bootstrap */
require_once( dirname( dirname( __FILE__ ) ) . '/wp-load.php' );

/** Allow for cross-domain requests (from the front end). */
header( 'Access-Control-Allow-Origin: *');
header( 'Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE' );


// Require an action parameter
if ( empty( $_REQUEST['action'] ) )
	wp_die( '0', 400 );

/** Load WordPress Administration APIs */
require_once( ABSPATH . 'wp-admin/includes/admin.php' );

/** Load Ajax Handlers for WordPress Core */
require_once( ABSPATH . 'wp-admin/includes/ajax-actions.php' );

@header( 'Content-Type: text/html; charset=' . get_option( 'blog_charset' ) );
@header( 'X-Robots-Tag: noindex' );

send_nosniff_header();
nocache_headers();

/** This action is documented in wp-admin/admin.php */
do_action( 'admin_init' );

add_action( 'wp_ajax_nopriv_heartbeat', 'wp_ajax_nopriv_heartbeat', 1 );

if ( is_user_logged_in() ) {
	// If no action is registered, return a Bad Request response.
	if ( ! has_action( 'wp_ajax_' . $_REQUEST['action'] ) ) {
		wp_die( '0', 400 );
	}

	/**
	 * Fires authenticated Ajax actions for logged-in users.
	 *
	 * The dynamic portion of the hook name, `$_REQUEST['action']`,
	 * refers to the name of the Ajax action callback being fired.
	 *
	 * @since 2.1.0
	 */
	do_action( 'wp_ajax_' . $_REQUEST['action'] );
} else {
	// If no action is registered, return a Bad Request response.
	if ( ! has_action( 'wp_ajax_nopriv_' . $_REQUEST['action'] ) ) {
		wp_die( '0', 400 );
	}

	/**
	 * Fires non-authenticated Ajax actions for logged-out users.
	 *
	 * The dynamic portion of the hook name, `$_REQUEST['action']`,
	 * refers to the name of the Ajax action callback being fired.
	 *
	 * @since 2.8.0
	 */
	do_action( 'wp_ajax_nopriv_' . $_REQUEST['action'] );
}
// Default status
wp_die( '0' );
