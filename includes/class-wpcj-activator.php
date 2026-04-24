<?php
defined( 'ABSPATH' ) || exit;

/**
 * Handles plugin activation: creates DB tables and registers custom WP roles.
 *
 * dbDelta() is WordPress's schema migration utility - it runs CREATE TABLE
 * but is smart enough to ALTER the table if it already exists with fewer columns.
 * Think of it like Liquibase/Flyway but only for a single idempotent statement.
 */
class WPCJ_Activator {

    public static function activate(): void {
        require_once WPCJ_PLUGIN_DIR . 'includes/class-wpcj-roles.php';
        WPCJ_Roles::register();
        self::create_tables();
    }

    private static function create_tables(): void {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        // Jury voting rounds (initial evaluation, shortlist, winner selection)
        // Anonymity is a global plugin setting (WPCJ_Settings::show_author_name()), not per-round.
        $sql_rounds = "CREATE TABLE {$wpdb->prefix}jury_rounds (
            id          BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            name        VARCHAR(100) NOT NULL,
            gallery_id  BIGINT(20) UNSIGNED NOT NULL,
            round_type  VARCHAR(30) NOT NULL DEFAULT 'initial',
            status      ENUM('draft','open','closed') NOT NULL DEFAULT 'draft',
            created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_workflow (gallery_id, round_type)
        ) $charset;";

        // One row per (round, juror, entry) - the UNIQUE key enforces one vote per juror per entry per round
        $sql_votes = "CREATE TABLE {$wpdb->prefix}jury_votes (
            id         BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            round_id   BIGINT(20) UNSIGNED NOT NULL,
            juror_id   BIGINT(20) UNSIGNED NOT NULL,
            entry_id   BIGINT(20) UNSIGNED NOT NULL,
            score      TINYINT(1)          NOT NULL,
            notes      TEXT,
            voted_at   DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_vote  (round_id, juror_id, entry_id),
            KEY idx_round       (round_id),
            KEY idx_juror       (juror_id)
        ) $charset;";

        // Entries promoted to the next round (shortlisted / finalist)
        $sql_shortlist = "CREATE TABLE {$wpdb->prefix}jury_shortlist (
            id          BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            round_id    BIGINT(20) UNSIGNED NOT NULL,
            entry_id    BIGINT(20) UNSIGNED NOT NULL,
            gallery_id  BIGINT(20) UNSIGNED NOT NULL,
            promoted_by BIGINT(20) UNSIGNED NOT NULL,
            promoted_at DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_shortlist (round_id, entry_id)
        ) $charset;";

        // One row per (juror × round) once the juror finalises their votes.
        // Until this row exists, the juror is in "draft" state and can change votes freely.
        $sql_submissions = "CREATE TABLE {$wpdb->prefix}jury_submissions (
            id           BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            round_id     BIGINT(20) UNSIGNED NOT NULL,
            juror_id     BIGINT(20) UNSIGNED NOT NULL,
            submitted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_submission (round_id, juror_id)
        ) $charset;";

        dbDelta( $sql_rounds );
        dbDelta( $sql_votes );
        dbDelta( $sql_shortlist );
        dbDelta( $sql_submissions );

        // dbDelta does not reliably add new UNIQUE KEYs to existing tables.
        // Enforce uq_workflow manually if it is missing.
        self::ensure_unique_key(
            $wpdb->prefix . 'jury_rounds',
            'uq_workflow',
            '(gallery_id, round_type)'
        );

        update_option( 'wpcj_db_version', WPCJ_VERSION );
    }

    private static function ensure_unique_key( string $table, string $key_name, string $columns ): void {
        global $wpdb;
        $exists = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM information_schema.STATISTICS
             WHERE table_schema = DATABASE()
               AND table_name   = %s
               AND index_name   = %s",
            $table,
            $key_name
        ) );
        if ( ! $exists ) {
            $wpdb->query( "ALTER TABLE `$table` ADD UNIQUE KEY `$key_name` $columns" );
        }
    }
}
