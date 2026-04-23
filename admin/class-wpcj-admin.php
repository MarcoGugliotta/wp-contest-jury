<?php
defined( 'ABSPATH' ) || exit;

/**
 * Registers wp-admin menus and dispatches page rendering.
 *
 * Menu structure:
 *   Jury Panel (top-level)
 *   ├── Dashboard       - overview / stats
 *   ├── Voting Rounds   - create/open/close rounds
 *   ├── Vote            - voting interface for jurors
 *   ├── Jurors          - assign roles to WP users (chief only)
 *   └── Settings        - configure gallery IDs and labels (chief only)
 */
class WPCJ_Admin {

    public function register_hooks(): void {
        add_action( 'admin_menu',            array( $this, 'register_menus' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
        add_action( 'admin_init',            array( $this, 'handle_form_submissions' ) );
        add_action( 'wp_ajax_wpcj_save_vote', array( $this, 'ajax_save_vote' ) );
    }

    public function register_menus(): void {
        add_menu_page(
            __( 'Jury Panel', 'wp-contest-jury' ),
            __( 'Jury Panel', 'wp-contest-jury' ),
            'wpcj_vote',
            'wpcj-dashboard',
            array( $this, 'page_dashboard' ),
            'dashicons-awards',
            30
        );

        add_submenu_page(
            'wpcj-dashboard',
            __( 'Dashboard', 'wp-contest-jury' ),
            __( 'Dashboard', 'wp-contest-jury' ),
            'wpcj_vote',
            'wpcj-dashboard',
            array( $this, 'page_dashboard' )
        );

        add_submenu_page(
            'wpcj-dashboard',
            __( 'Voting Rounds', 'wp-contest-jury' ),
            __( 'Rounds', 'wp-contest-jury' ),
            'wpcj_manage_rounds',
            'wpcj-rounds',
            array( $this, 'page_rounds' )
        );

        add_submenu_page(
            'wpcj-dashboard',
            __( 'Vote', 'wp-contest-jury' ),
            __( 'Vote', 'wp-contest-jury' ),
            'wpcj_vote',
            'wpcj-vote',
            array( $this, 'page_vote' )
        );

        add_submenu_page(
            'wpcj-dashboard',
            __( 'Jurors', 'wp-contest-jury' ),
            __( 'Jurors', 'wp-contest-jury' ),
            'wpcj_manage_jurors',
            'wpcj-jurors',
            array( $this, 'page_jurors' )
        );

        add_submenu_page(
            'wpcj-dashboard',
            __( 'Settings', 'wp-contest-jury' ),
            __( 'Settings', 'wp-contest-jury' ),
            'wpcj_manage_rounds',
            'wpcj-settings',
            array( $this, 'page_settings' )
        );
    }

    public function enqueue_assets( string $hook ): void {
        if ( strpos( $hook, 'wpcj-' ) === false ) {
            return;
        }
        wp_enqueue_style(
            'wpcj-admin',
            WPCJ_PLUGIN_URL . 'admin/css/wpcj-admin.css',
            array(),
            WPCJ_VERSION
        );
        wp_enqueue_script(
            'wpcj-admin',
            WPCJ_PLUGIN_URL . 'admin/js/wpcj-admin.js',
            array( 'jquery' ),
            WPCJ_VERSION,
            true
        );
        wp_localize_script( 'wpcj-admin', 'wpcjData', array(
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'wpcj_nonce' ),
        ) );
    }

    /**
     * Handles all synchronous POST form submissions from plugin pages.
     * AJAX calls are handled separately via add_action('wp_ajax_*').
     */
    public function handle_form_submissions(): void {
        // GET-based actions (links with nonces: open/close round, remove juror)
        if ( isset( $_GET['wpcj_action'] ) ) {
            $this->handle_get_actions( sanitize_key( $_GET['wpcj_action'] ) );
        }

        if ( ! isset( $_POST['wpcj_action'] ) ) {
            return;
        }
        $action = sanitize_key( $_POST['wpcj_action'] );

        if ( $action === 'save_settings' ) {
            check_admin_referer( 'wpcj_save_settings', 'wpcj_nonce' );
            if ( ! current_user_can( 'wpcj_manage_rounds' ) ) {
                wp_die( esc_html__( 'Access denied.', 'wp-contest-jury' ) );
            }
            WPCJ_Settings::save( $_POST );
            wp_redirect( admin_url( 'admin.php?page=wpcj-settings&saved=1' ) );
            exit;
        }

        if ( $action === 'create_round' ) {
            check_admin_referer( 'wpcj_create_round', 'wpcj_nonce' );
            if ( ! current_user_can( 'wpcj_manage_rounds' ) ) {
                wp_die( esc_html__( 'Access denied.', 'wp-contest-jury' ) );
            }
            $name       = sanitize_text_field( $_POST['round_name'] ?? '' );
            $gallery_id = absint( $_POST['gallery_id'] ?? 0 );
            $round_type = sanitize_key( $_POST['round_type'] ?? '' );
            if ( ! in_array( $round_type, array( WPCJ_DB::ROUND_INITIAL, WPCJ_DB::ROUND_SHORTLIST, WPCJ_DB::ROUND_FINAL, WPCJ_DB::ROUND_WINNER ), true ) ) {
                $round_type = WPCJ_DB::ROUND_INITIAL;
            }
            if ( $name && $gallery_id ) {
                $result = WPCJ_DB::insert_round( $name, $gallery_id, $round_type );
                if ( $result === false ) {
                    wp_redirect( admin_url( 'admin.php?page=wpcj-rounds&wpcj_error=duplicate_type' ) );
                    exit;
                }
            }
            wp_redirect( admin_url( 'admin.php?page=wpcj-rounds&wpcj_created=1' ) );
            exit;
        }

        if ( $action === 'add_juror' ) {
            check_admin_referer( 'wpcj_add_juror', 'wpcj_nonce' );
            if ( ! current_user_can( 'wpcj_manage_jurors' ) ) {
                wp_die( esc_html__( 'Access denied.', 'wp-contest-jury' ) );
            }
            $user_id = absint( $_POST['juror_user_id'] ?? 0 );
            $role    = in_array( $_POST['juror_role'] ?? '', array( 'jury_chief', 'jury_member' ), true )
                       ? $_POST['juror_role']
                       : 'jury_member';
            if ( $user_id ) {
                $user = get_user_by( 'id', $user_id );
                $user && $user->set_role( $role );
            }
            wp_redirect( admin_url( 'admin.php?page=wpcj-jurors' ) );
            exit;
        }
    }

    private function handle_get_actions( string $action ): void {
        if ( $action === 'open_round' ) {
            $round_id = absint( $_GET['round'] ?? 0 );
            check_admin_referer( 'wpcj_open_round_' . $round_id );
            if ( ! current_user_can( 'wpcj_manage_rounds' ) ) {
                wp_die( esc_html__( 'Access denied.', 'wp-contest-jury' ) );
            }
            if ( $round_id ) {
                WPCJ_DB::update_round_status( $round_id, 'open' );
            }
            wp_redirect( admin_url( 'admin.php?page=wpcj-rounds' ) );
            exit;
        }

        if ( $action === 'close_round' ) {
            $round_id = absint( $_GET['round'] ?? 0 );
            check_admin_referer( 'wpcj_close_round_' . $round_id );
            if ( ! current_user_can( 'wpcj_manage_rounds' ) ) {
                wp_die( esc_html__( 'Access denied.', 'wp-contest-jury' ) );
            }
            if ( $round_id ) {
                WPCJ_DB::update_round_status( $round_id, 'closed' );
            }
            wp_redirect( admin_url( 'admin.php?page=wpcj-rounds' ) );
            exit;
        }

        if ( $action === 'delete_round' ) {
            $round_id = absint( $_GET['round'] ?? 0 );
            check_admin_referer( 'wpcj_delete_round_' . $round_id );
            if ( ! current_user_can( 'wpcj_manage_rounds' ) ) {
                wp_die( esc_html__( 'Access denied.', 'wp-contest-jury' ) );
            }
            if ( $round_id ) {
                WPCJ_DB::delete_round( $round_id );
            }
            wp_redirect( admin_url( 'admin.php?page=wpcj-rounds' ) );
            exit;
        }

        if ( $action === 'remove_juror' ) {
            $user_id = absint( $_GET['user'] ?? 0 );
            check_admin_referer( 'wpcj_remove_juror_' . $user_id );
            if ( ! current_user_can( 'wpcj_manage_jurors' ) ) {
                wp_die( esc_html__( 'Access denied.', 'wp-contest-jury' ) );
            }
            if ( $user_id ) {
                $user = get_user_by( 'id', $user_id );
                if ( $user ) {
                    $user->set_role( 'subscriber' );
                }
            }
            wp_redirect( admin_url( 'admin.php?page=wpcj-jurors' ) );
            exit;
        }
    }

    // -------------------------------------------------------------------------
    // AJAX handlers
    // -------------------------------------------------------------------------

    public function ajax_save_vote(): void {
        check_ajax_referer( 'wpcj_nonce', 'nonce' );

        if ( ! current_user_can( 'wpcj_vote' ) ) {
            wp_send_json_error( array( 'message' => __( 'Access denied.', 'wp-contest-jury' ) ), 403 );
        }

        $round_id = absint( $_POST['round_id'] ?? 0 );
        $entry_id = absint( $_POST['entry_id'] ?? 0 );
        $score    = absint( $_POST['score']    ?? 0 );
        $notes    = sanitize_textarea_field( $_POST['notes'] ?? '' );

        if ( ! $round_id || ! $entry_id || $score < 1 || $score > 5 ) {
            wp_send_json_error( array( 'message' => __( 'Invalid data.', 'wp-contest-jury' ) ), 400 );
        }

        $round = WPCJ_DB::get_round( $round_id );
        if ( ! $round || $round['status'] !== 'open' ) {
            wp_send_json_error( array( 'message' => __( 'Round is not open.', 'wp-contest-jury' ) ), 400 );
        }

        $ok = WPCJ_DB::upsert_vote( $round_id, get_current_user_id(), $entry_id, $score, $notes );
        if ( $ok ) {
            wp_send_json_success();
        } else {
            wp_send_json_error( array( 'message' => __( 'Could not save vote.', 'wp-contest-jury' ) ), 500 );
        }
    }

    // -------------------------------------------------------------------------
    // Page renderers
    // -------------------------------------------------------------------------

    public function page_dashboard(): void {
        require WPCJ_PLUGIN_DIR . 'admin/partials/dashboard.php';
    }

    public function page_rounds(): void {
        require WPCJ_PLUGIN_DIR . 'admin/partials/rounds.php';
    }

    public function page_vote(): void {
        require WPCJ_PLUGIN_DIR . 'admin/partials/vote.php';
    }

    public function page_jurors(): void {
        require WPCJ_PLUGIN_DIR . 'admin/partials/jurors.php';
    }

    public function page_settings(): void {
        require WPCJ_PLUGIN_DIR . 'admin/partials/settings.php';
    }
}
