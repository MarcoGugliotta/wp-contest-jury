<?php
defined( 'ABSPATH' ) || exit;

/**
 * Manages the two custom WordPress roles for the jury system.
 *
 * WP roles are stored in the options table (not in code), so add_role() is
 * idempotent only if called via the activation hook - calling it on every
 * page load would silently reset capabilities each time. That's why we call
 * this only from WPCJ_Activator::activate().
 *
 * Capabilities follow the principle of least privilege:
 *   jury_chief   - full jury admin (manage rounds, see all votes, export)
 *   jury_member  - vote only; cannot see other jurors' votes or author data
 */
class WPCJ_Roles {

    const JURY_CHIEF  = 'jury_chief';
    const JURY_MEMBER = 'jury_member';

    /**
     * All capabilities owned by jury_chief (and granted to administrator).
     * Defined once here so register() and remove() stay in sync.
     */
    private static function chief_caps(): array {
        return array(
            'wpcj_manage_rounds'   => true,
            'wpcj_view_all_votes'  => true,
            'wpcj_promote_entries' => true,
            'wpcj_export_data'     => true,
            'wpcj_manage_jurors'   => true,
            'wpcj_vote'            => true,
        );
    }

    public static function register(): void {
        // Remove first so capabilities are always reset to the defined set on activation
        remove_role( self::JURY_CHIEF );
        remove_role( self::JURY_MEMBER );

        add_role(
            self::JURY_CHIEF,
            __( 'Jury Chief', 'wp-contest-jury' ),
            array_merge( array( 'read' => true ), self::chief_caps() )
        );

        add_role(
            self::JURY_MEMBER,
            __( 'Jury Member', 'wp-contest-jury' ),
            array( 'read' => true, 'wpcj_vote' => true )
        );

        // Administrators must also have all jury capabilities so the admin
        // user who installs the plugin can access the jury panel immediately.
        // WP roles/caps are stored in the DB, so this persists across requests.
        $administrator = get_role( 'administrator' );
        if ( $administrator ) {
            foreach ( self::chief_caps() as $cap => $grant ) {
                $administrator->add_cap( $cap, $grant );
            }
        }
    }

    public static function remove(): void {
        remove_role( self::JURY_CHIEF );
        remove_role( self::JURY_MEMBER );

        // Clean up caps added to administrator on activation
        $administrator = get_role( 'administrator' );
        if ( $administrator ) {
            foreach ( array_keys( self::chief_caps() ) as $cap ) {
                $administrator->remove_cap( $cap );
            }
        }
    }

    public static function current_user_can_vote(): bool {
        return current_user_can( 'wpcj_vote' );
    }

    public static function current_user_is_chief(): bool {
        return current_user_can( 'wpcj_manage_rounds' );
    }
}
