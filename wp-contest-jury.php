<?php
/**
 * Plugin Name: WP Contest Jury
 * Plugin URI:  https://github.com/mgugliotta/wp-contest-jury
 * Description: Jury management panel for photo contests. Reads entries from Contest Gallery PRO (read-only) and manages all jury/voting data in its own tables. Fully configurable - works with any Contest Gallery PRO installation.
 * Version:     0.2.0
 * Author:      Marco Gugliotta
 * Text Domain: wp-contest-jury
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 8.0
 */

defined( 'ABSPATH' ) || exit;

define( 'WPCJ_VERSION',     '0.2.0' );
define( 'WPCJ_PLUGIN_FILE', __FILE__ );
define( 'WPCJ_PLUGIN_DIR',  plugin_dir_path( __FILE__ ) );
define( 'WPCJ_PLUGIN_URL',  plugin_dir_url( __FILE__ ) );

require_once WPCJ_PLUGIN_DIR . 'includes/class-wpcj-activator.php';
require_once WPCJ_PLUGIN_DIR . 'includes/class-wpcj-deactivator.php';
require_once WPCJ_PLUGIN_DIR . 'includes/class-wpcj-plugin.php';

register_activation_hook( __FILE__,   array( 'WPCJ_Activator',   'activate' ) );
register_deactivation_hook( __FILE__, array( 'WPCJ_Deactivator', 'deactivate' ) );

WPCJ_Plugin::get_instance()->run();
