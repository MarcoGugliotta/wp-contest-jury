<?php
defined( 'ABSPATH' ) || exit;

/**
 * Main orchestrator class. Loaded on every request.
 */
class WPCJ_Plugin {

    private static ?WPCJ_Plugin $instance = null;

    public static function get_instance(): self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->load_dependencies();
    }

    private function load_dependencies(): void {
        require_once WPCJ_PLUGIN_DIR . 'includes/class-wpcj-roles.php';
        require_once WPCJ_PLUGIN_DIR . 'includes/class-wpcj-settings.php';
        require_once WPCJ_PLUGIN_DIR . 'includes/class-wpcj-db.php';
        require_once WPCJ_PLUGIN_DIR . 'includes/class-wpcj-cg-reader.php';
        require_once WPCJ_PLUGIN_DIR . 'admin/class-wpcj-admin.php';
        require_once WPCJ_PLUGIN_DIR . 'includes/class-wpcj-frontend.php';
    }

    public function run(): void {
        add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );

        if ( is_admin() ) {
            $admin = new WPCJ_Admin();
            $admin->register_hooks();
        }

        $frontend = new WPCJ_Frontend();
        $frontend->register_hooks();
    }

    public function load_textdomain(): void {
        load_plugin_textdomain(
            'wp-contest-jury',
            false,
            dirname( plugin_basename( WPCJ_PLUGIN_FILE ) ) . '/languages'
        );
    }
}
