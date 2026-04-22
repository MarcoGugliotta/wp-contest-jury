<?php
// Only runs when the admin clicks "Delete" on the Plugins page
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

global $wpdb;

$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}jury_shortlist" );
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}jury_votes" );
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}jury_rounds" );

delete_option( 'wpcj_db_version' );

require_once plugin_dir_path( __FILE__ ) . 'includes/class-wpcj-roles.php';
WPCJ_Roles::remove();
