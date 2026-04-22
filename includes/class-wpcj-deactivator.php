<?php
defined( 'ABSPATH' ) || exit;

/**
 * Handles plugin deactivation.
 *
 * Tables and data are intentionally preserved on deactivation — only removed
 * on full uninstall (uninstall.php). This matches standard WP plugin behaviour.
 */
class WPCJ_Deactivator {

    public static function deactivate(): void {
        // Flush rewrite rules in case we registered any custom endpoints
        flush_rewrite_rules();
    }
}
