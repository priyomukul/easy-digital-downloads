<?php

$_SERVER['SERVER_PROTOCOL'] = 'HTTP/1.1';
$_SERVER['SERVER_NAME'] = '';
$PHP_SELF = $GLOBALS['PHP_SELF'] = $_SERVER['PHP_SELF'] = '/index.php';

define( 'EDD_USE_PHP_SESSIONS', false );
define( 'WP_USE_THEMES', false );
define( 'EDD_DOING_TESTS', true );

require_once dirname( dirname( __FILE__ ) ) . '/vendor/autoload.php';

$_tests_dir = getenv('WP_TESTS_DIR');
if ( !$_tests_dir ) $_tests_dir = '/tmp/wordpress-tests-lib';

require_once $_tests_dir . '/includes/functions.php';

function _manually_load_plugin() {
	require dirname( __FILE__ ) . '/../easy-digital-downloads.php';
}
tests_add_filter( 'muplugins_loaded', '_manually_load_plugin' );

require $_tests_dir . '/includes/bootstrap.php';

activate_plugin( 'easy-digital-downloads/easy-digital-downloads.php' );

echo "Installing Easy Digital Downloads...\n";

// Install Easy Digital Downloads
edd_install();

global $current_user, $edd_options;

$edd_options = get_option( 'edd_settings' );

$current_user = new WP_User(1);
$current_user->set_role('administrator');
wp_update_user( array( 'ID' => 1, 'first_name' => 'Admin', 'last_name' => 'User' ) );
add_filter( 'edd_log_email_errors', '__return_false' );

function _disable_reqs( $status = false, $args = array(), $url = '') {
	return new WP_Error( 'no_reqs_in_unit_tests', __( 'HTTP Requests disabled for unit tests', 'easy-digital-downloads' ) );
}
add_filter( 'pre_http_request', '_disable_reqs' );

// Include helpers
require_once 'helpers/shims.php';
require_once 'helpers/class-helper-download.php';
require_once 'helpers/class-helper-payment.php';
require_once 'helpers/class-helper-discount.php';
require_once 'helpers/class-edd-unittestcase.php';
