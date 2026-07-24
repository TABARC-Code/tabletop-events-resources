<?php
/**
 * Plugin Name:       Tabletop Events Calendar — Resources
 * Plugin URI:        https://github.com/TABARC-Code/tabletop-events-resources
 * Description:       Shops and organisers list terrain, tables, and spare dice/minis available to borrow for a session. Requires the Tabletop Events Calendar plugin.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Requires Plugins:  tabletop-events-calendar
 * Author:            TABARC-Code
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       tabletop-events-resources
 *
 * Anchored on organiser identity the same way as [tabletop_organiser_events]
 * and the Reviews plugin's organiser widget: any one event ID belonging
 * to the organiser, matched against the core plugin's own
 * _tec_organiser_email. No dependency on the Venues & Organisers
 * plugin — this stands alone, same "core plugin only" rule every
 * companion plugin in this family follows.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // No direct access.
}

define( 'TRES_VERSION', '1.0.0' );
define( 'TRES_PLUGIN_FILE', __FILE__ );
define( 'TRES_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'TRES_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'TRES_POST_TYPE', 'tres_resource' );

spl_autoload_register(
	function ( $class ) {
		if ( strpos( $class, 'TRES_' ) !== 0 ) {
			return;
		}
		$slug = strtolower( str_replace( '_', '-', $class ) );
		$path = TRES_PLUGIN_DIR . 'includes/class-' . $slug . '.php';
		if ( file_exists( $path ) ) {
			require_once $path;
		}
	}
);

function tres_init() {
	if ( ! tres_dependency_met() ) {
		add_action( 'admin_notices', 'tres_missing_dependency_notice' );
		return;
	}

	load_plugin_textdomain( 'tabletop-events-resources', false, dirname( plugin_basename( TRES_PLUGIN_FILE ) ) . '/languages' );

	TRES_Post_Type::instance();
	TRES_Rest::instance();
	TRES_Manage::instance();
	TRES_Shortcode_Resources::instance();

	tres_maybe_upgrade();
}
add_action( 'plugins_loaded', 'tres_init', 20 );

function tres_dependency_met() {
	return defined( 'TEC_POST_TYPE' ) && class_exists( 'TEC_Admin' );
}

function tres_missing_dependency_notice() {
	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}
	echo '<div class="notice notice-error"><p>' .
		esc_html__( 'Tabletop Events Calendar — Resources requires the Tabletop Events Calendar plugin to be installed and active.', 'tabletop-events-resources' ) .
		'</p></div>';
}

/**
 * Deferred to 'init', same reasoning as every other plugin in this
 * family — flush_rewrite_rules() needs $wp_rewrite, which doesn't
 * exist yet on plugins_loaded.
 */
function tres_maybe_upgrade() {
	add_action( 'init', 'tres_run_upgrade', 20 );
}
function tres_run_upgrade() {
	$installed = get_option( 'tres_plugin_version' );
	if ( $installed === TRES_VERSION ) {
		return;
	}
	flush_rewrite_rules();
	update_option( 'tres_plugin_version', TRES_VERSION, false );
}

function tres_activate() {
	if ( tres_dependency_met() ) {
		require_once TRES_PLUGIN_DIR . 'includes/class-tres-post-type.php';
		TRES_Post_Type::instance()->register_post_type();
	}
	require_once TRES_PLUGIN_DIR . 'includes/class-tres-manage.php';
	TRES_Manage::instance()->add_rewrite_rules();
	flush_rewrite_rules();
	update_option( 'tres_plugin_version', TRES_VERSION, false );
}
register_activation_hook( TRES_PLUGIN_FILE, 'tres_activate' );

function tres_deactivate() {
	flush_rewrite_rules();
}
register_deactivation_hook( TRES_PLUGIN_FILE, 'tres_deactivate' );
