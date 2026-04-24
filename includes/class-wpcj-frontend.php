<?php
defined( 'ABSPATH' ) || exit;

/**
 * Registers the [wpcj_jury_panel] shortcode and all frontend hooks.
 *
 * Routing logic (pure PHP, no JS router):
 *   Not logged in               → login.php
 *   Logged in, no round param   → selection.php
 *   Logged in, round open, not submitted → voting.php  ($readonly = false)
 *   Logged in, round submitted or closed → voting.php  ($readonly = true)
 */
class WPCJ_Frontend {

    public function register_hooks(): void {
        add_shortcode( 'wpcj_jury_panel', array( $this, 'render_panel' ) );
        add_action( 'wp_enqueue_scripts',       array( $this, 'enqueue_assets' ) );
        add_action( 'wp_ajax_wpcj_submit_round', array( $this, 'ajax_submit_round' ) );
        add_filter( 'login_redirect',            array( $this, 'login_redirect' ), 10, 3 );
    }

    public function enqueue_assets(): void {
        $page_id = WPCJ_Settings::get_jury_page_id();
        if ( ! $page_id || ! is_page( $page_id ) ) {
            return;
        }
        wp_enqueue_style(
            'wpcj-frontend',
            WPCJ_PLUGIN_URL . 'frontend/css/wpcj-frontend.css',
            array(),
            WPCJ_VERSION
        );
        wp_enqueue_script(
            'wpcj-frontend',
            WPCJ_PLUGIN_URL . 'frontend/js/wpcj-frontend.js',
            array( 'jquery' ),
            WPCJ_VERSION,
            true
        );
        wp_localize_script( 'wpcj-frontend', 'wpcjFrontend', array(
            'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
            'nonce'      => wp_create_nonce( 'wpcj_nonce' ),
            'panelUrl'   => $page_id ? get_permalink( $page_id ) : home_url(),
            'i18n'       => array(
                'submitConfirm'  => __( 'Submit your votes? This action cannot be undone.', 'wp-contest-jury' ),
                'submitSuccess'  => __( 'Votes submitted successfully!', 'wp-contest-jury' ),
                'submitError'    => __( 'Could not submit. Please try again.', 'wp-contest-jury' ),
                'voteMissing'    => __( 'You must vote all entries before submitting.', 'wp-contest-jury' ),
                'saving'         => __( 'Saving…', 'wp-contest-jury' ),
                'saved'          => __( 'Saved', 'wp-contest-jury' ),
            ),
        ) );
    }

    /** After login, redirect jury users to the jury panel page instead of wp-admin. */
    public function login_redirect( string $redirect_to, string $requested, $user ): string {
        if ( ! ( $user instanceof WP_User ) ) {
            return $redirect_to;
        }
        if ( $user->has_cap( 'wpcj_vote' ) || $user->has_cap( 'wpcj_manage_rounds' ) ) {
            $page_id = WPCJ_Settings::get_jury_page_id();
            if ( $page_id ) {
                return get_permalink( $page_id );
            }
        }
        return $redirect_to;
    }

    public function render_panel( array $atts = array() ): string {
        ob_start();

        if ( ! is_user_logged_in() ) {
            require WPCJ_PLUGIN_DIR . 'frontend/partials/login.php';
            return ob_get_clean();
        }

        if ( ! current_user_can( 'wpcj_vote' ) ) {
            echo '<p class="wpcj-access-denied">' . esc_html__( 'Access denied. You need a jury member account to view this page.', 'wp-contest-jury' ) . '</p>';
            return ob_get_clean();
        }

        $round_id = absint( $_GET['jury_round'] ?? 0 );

        if ( ! $round_id ) {
            require WPCJ_PLUGIN_DIR . 'frontend/partials/selection.php';
            return ob_get_clean();
        }

        $round    = WPCJ_DB::get_round( $round_id );
        $juror_id = get_current_user_id();

        if ( ! $round ) {
            echo '<p class="wpcj-error">' . esc_html__( 'Round not found.', 'wp-contest-jury' ) . '</p>';
            return ob_get_clean();
        }

        $submitted = WPCJ_DB::get_submission( $round_id, $juror_id );
        $readonly  = $submitted || $round['status'] !== 'open';

        require WPCJ_PLUGIN_DIR . 'frontend/partials/voting.php';
        return ob_get_clean();
    }

    public function ajax_submit_round(): void {
        check_ajax_referer( 'wpcj_nonce', 'nonce' );

        if ( ! current_user_can( 'wpcj_vote' ) ) {
            wp_send_json_error( array( 'message' => __( 'Access denied.', 'wp-contest-jury' ) ), 403 );
        }

        $round_id = absint( $_POST['round_id'] ?? 0 );
        $juror_id = get_current_user_id();
        $round    = $round_id ? WPCJ_DB::get_round( $round_id ) : null;

        if ( ! $round || $round['status'] !== 'open' ) {
            wp_send_json_error( array( 'message' => __( 'Round is not available.', 'wp-contest-jury' ) ), 400 );
        }

        if ( WPCJ_Settings::require_all_votes() ) {
            $entries     = WPCJ_CG_Reader::get_entries( (int) $round['gallery_id'] );
            $votes       = WPCJ_DB::get_votes_by_juror( $round_id, $juror_id );
            if ( count( $votes ) < count( $entries ) ) {
                wp_send_json_error( array( 'message' => __( 'You must vote all entries before submitting.', 'wp-contest-jury' ) ), 400 );
            }
        }

        if ( WPCJ_DB::submit_round( $round_id, $juror_id ) ) {
            wp_send_json_success( array( 'message' => __( 'Votes submitted.', 'wp-contest-jury' ) ) );
        } else {
            wp_send_json_error( array( 'message' => __( 'Already submitted or error occurred.', 'wp-contest-jury' ) ), 400 );
        }
    }
}
