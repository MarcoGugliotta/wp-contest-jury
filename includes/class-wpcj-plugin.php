<?php
defined( 'ABSPATH' ) || exit;

/**
 * Main orchestrator class. Loaded on every request.
 *
 * Think of this as the Spring ApplicationContext: it wires all the
 * sub-components together and registers them with WordPress's event system
 * (hooks ≈ Spring application events / listeners).
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
    }

    /**
     * Register all WordPress hooks. Called once from the main plugin file.
     * Hooks are WordPress's event bus - add_action() ≈ addEventListener().
     */
    public function run(): void {
        add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );

        if ( is_admin() ) {
            $admin = new WPCJ_Admin();
            $admin->register_hooks();
        }
    }

    public function load_textdomain(): void {
        load_plugin_textdomain(
            'wp-contest-jury',
            false,
            dirname( plugin_basename( WPCJ_PLUGIN_FILE ) ) . '/languages'
        );
    }
}
