<?php
defined( 'ABSPATH' ) || exit;

/**
 * Data-access layer for the jury plugin's own tables.
 *
 * All writes go through here. $wpdb is WordPress's DB abstraction - think of
 * it as a lightweight JDBC wrapper with built-in query preparation.
 * $wpdb->prepare() is mandatory for any query with user-supplied values
 * (prevents SQL injection the same way PreparedStatement does in Java).
 */
class WPCJ_DB {

    const ROUND_INITIAL   = 'initial';
    const ROUND_SHORTLIST = 'shortlist';
    const ROUND_FINAL     = 'final';
    const ROUND_WINNER    = 'winner';

    // -------------------------------------------------------------------------
    // Rounds
    // -------------------------------------------------------------------------

    public static function get_rounds(): array {
        global $wpdb;
        return $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}jury_rounds ORDER BY id ASC",
            ARRAY_A
        ) ?: array();
    }

    public static function get_round( int $round_id ): ?array {
        global $wpdb;
        $row = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}jury_rounds WHERE id = %d", $round_id ),
            ARRAY_A
        );
        return $row ?: null;
    }

    public static function insert_round( string $name, int $gallery_id, string $round_type ): int|false {
        global $wpdb;
        $ok = $wpdb->insert(
            $wpdb->prefix . 'jury_rounds',
            array( 'name' => $name, 'gallery_id' => $gallery_id, 'round_type' => $round_type, 'status' => 'draft' ),
            array( '%s', '%d', '%s', '%s' )
        );
        return $ok ? $wpdb->insert_id : false;
    }

    /**
     * Deletes a round and all associated votes and shortlist entries.
     * Only allowed for draft and closed rounds — open rounds cannot be deleted.
     */
    public static function delete_round( int $round_id ): bool {
        global $wpdb;
        $round = self::get_round( $round_id );
        if ( ! $round || $round['status'] === 'open' ) {
            return false;
        }
        $wpdb->delete( $wpdb->prefix . 'jury_shortlist', array( 'round_id' => $round_id ), array( '%d' ) );
        $wpdb->delete( $wpdb->prefix . 'jury_votes',     array( 'round_id' => $round_id ), array( '%d' ) );
        return (bool) $wpdb->delete( $wpdb->prefix . 'jury_rounds', array( 'id' => $round_id ), array( '%d' ) );
    }

    public static function update_round_status( int $round_id, string $status ): bool {
        global $wpdb;
        return (bool) $wpdb->update(
            $wpdb->prefix . 'jury_rounds',
            array( 'status' => $status ),
            array( 'id'     => $round_id ),
            array( '%s' ),
            array( '%d' )
        );
    }

    // -------------------------------------------------------------------------
    // Votes
    // -------------------------------------------------------------------------

    public static function delete_vote( int $round_id, int $juror_id, int $entry_id ): bool {
        global $wpdb;
        return (bool) $wpdb->delete(
            $wpdb->prefix . 'jury_votes',
            array( 'round_id' => $round_id, 'juror_id' => $juror_id, 'entry_id' => $entry_id ),
            array( '%d', '%d', '%d' )
        );
    }

    /**
     * Upsert a vote. Uses INSERT … ON DUPLICATE KEY UPDATE so the juror can
     * change their score until the round is closed.
     */
    public static function upsert_vote( int $round_id, int $juror_id, int $entry_id, int $score, string $notes = '' ): bool {
        global $wpdb;
        $table = $wpdb->prefix . 'jury_votes';
        $sql   = $wpdb->prepare(
            "INSERT INTO `$table` (round_id, juror_id, entry_id, score, notes, voted_at)
             VALUES (%d, %d, %d, %d, %s, NOW())
             ON DUPLICATE KEY UPDATE score = VALUES(score), notes = VALUES(notes), voted_at = NOW()",
            $round_id, $juror_id, $entry_id, $score, $notes
        );
        return (bool) $wpdb->query( $sql );
    }

    /**
     * Returns all votes cast by a specific juror in a round.
     * Jurors NEVER see other jurors' votes - this is enforced at the query level.
     */
    public static function get_votes_by_juror( int $round_id, int $juror_id ): array {
        global $wpdb;
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT entry_id, score, notes, voted_at
                 FROM {$wpdb->prefix}jury_votes
                 WHERE round_id = %d AND juror_id = %d",
                $round_id, $juror_id
            ),
            ARRAY_A
        ) ?: array();
    }

    /**
     * Aggregated scores per entry for a round - jury chief only.
     */
    public static function get_round_results( int $round_id ): array {
        global $wpdb;
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT entry_id,
                        COUNT(*)       AS vote_count,
                        SUM(score)     AS total_score,
                        AVG(score)     AS avg_score
                 FROM {$wpdb->prefix}jury_votes
                 WHERE round_id = %d
                 GROUP BY entry_id
                 ORDER BY avg_score DESC",
                $round_id
            ),
            ARRAY_A
        ) ?: array();
    }

    // -------------------------------------------------------------------------
    // Submissions  (juror finalises their votes for a round)
    // -------------------------------------------------------------------------

    public static function submit_round( int $round_id, int $juror_id ): bool {
        global $wpdb;
        $ok = $wpdb->insert(
            $wpdb->prefix . 'jury_submissions',
            array( 'round_id' => $round_id, 'juror_id' => $juror_id ),
            array( '%d', '%d' )
        );
        return $ok !== false;
    }

    public static function get_submission( int $round_id, int $juror_id ): ?array {
        global $wpdb;
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}jury_submissions WHERE round_id = %d AND juror_id = %d",
                $round_id, $juror_id
            ),
            ARRAY_A
        );
        return $row ?: null;
    }

    /** Returns all submissions for a juror, keyed by round_id. */
    public static function get_submissions_by_juror( int $juror_id ): array {
        global $wpdb;
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}jury_submissions WHERE juror_id = %d",
                $juror_id
            ),
            ARRAY_A
        ) ?: array();
        return array_column( $rows, null, 'round_id' );
    }

    // -------------------------------------------------------------------------
    // Shortlist
    // -------------------------------------------------------------------------

    public static function get_shortlist( int $round_id ): array {
        global $wpdb;
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}jury_shortlist WHERE round_id = %d",
                $round_id
            ),
            ARRAY_A
        ) ?: array();
    }

    public static function add_to_shortlist( int $round_id, int $entry_id, int $gallery_id, int $promoted_by ): bool {
        global $wpdb;
        $ok = $wpdb->insert(
            $wpdb->prefix . 'jury_shortlist',
            array(
                'round_id'    => $round_id,
                'entry_id'    => $entry_id,
                'gallery_id'  => $gallery_id,
                'promoted_by' => $promoted_by,
            ),
            array( '%d', '%d', '%d', '%d' )
        );
        return $ok !== false;
    }

    public static function remove_from_shortlist( int $round_id, int $entry_id ): bool {
        global $wpdb;
        return (bool) $wpdb->delete(
            $wpdb->prefix . 'jury_shortlist',
            array( 'round_id' => $round_id, 'entry_id' => $entry_id ),
            array( '%d', '%d' )
        );
    }
}
