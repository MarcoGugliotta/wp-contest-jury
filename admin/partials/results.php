<?php
defined( 'ABSPATH' ) || exit;

if ( ! current_user_can( 'wpcj_manage_rounds' ) ) {
    wp_die( esc_html__( 'Access denied.', 'wp-contest-jury' ) );
}

$round_id = absint( $_GET['round'] ?? 0 );
$round    = $round_id ? WPCJ_DB::get_round( $round_id ) : null;

if ( ! $round ) {
    wp_die( esc_html__( 'Round not found.', 'wp-contest-jury' ) );
}

$gallery_id      = (int) $round['gallery_id'];
$results         = WPCJ_DB::get_round_results( $round_id );
$entry_ids       = array_map( fn( $r ) => (int) $r['entry_id'], $results );
$entries_raw     = WPCJ_CG_Reader::get_entries_by_ids( $gallery_id, $entry_ids );
$entries_by_id   = array_column( $entries_raw, null, 'id' );
$identity_ids    = WPCJ_CG_Reader::get_identity_field_ids( $gallery_id );
$gallery_label   = WPCJ_Settings::get_gallery_label( $gallery_id );
$back_url        = admin_url( 'admin.php?page=wpcj-rounds' );
?>
<div class="wrap wpcj-wrap">
    <h1>
        <?php printf(
            esc_html__( 'Results: %1$s — %2$s', 'wp-contest-jury' ),
            esc_html( $round['name'] ),
            esc_html( $gallery_label )
        ); ?>
    </h1>

    <p>
        <a href="<?php echo esc_url( $back_url ); ?>" class="button">
            &larr; <?php esc_html_e( 'Back to Rounds', 'wp-contest-jury' ); ?>
        </a>
    </p>

    <p>
        <strong><?php esc_html_e( 'Type:', 'wp-contest-jury' ); ?></strong>
        <?php echo esc_html( $round['round_type'] ); ?>
        &nbsp;&nbsp;
        <strong><?php esc_html_e( 'Status:', 'wp-contest-jury' ); ?></strong>
        <span class="wpcj-status wpcj-status-<?php echo esc_attr( $round['status'] ); ?>">
            <?php echo esc_html( $round['status'] ); ?>
        </span>
    </p>

    <?php if ( empty( $results ) ) : ?>
        <p><?php esc_html_e( 'No votes recorded for this round yet.', 'wp-contest-jury' ); ?></p>
    <?php else : ?>
        <table class="widefat striped wpcj-results-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th><?php esc_html_e( 'Entry', 'wp-contest-jury' ); ?></th>
                    <th><?php esc_html_e( 'Title', 'wp-contest-jury' ); ?></th>
                    <th><?php esc_html_e( 'Author', 'wp-contest-jury' ); ?></th>
                    <th><?php esc_html_e( 'Avg Score', 'wp-contest-jury' ); ?></th>
                    <th><?php esc_html_e( 'Votes', 'wp-contest-jury' ); ?></th>
                    <th><?php esc_html_e( 'Total', 'wp-contest-jury' ); ?></th>
                </tr>
            </thead>
            <tbody>
            <?php $rank = 1; foreach ( $results as $row ) :
                $entry_id  = (int) $row['entry_id'];
                $entry     = $entries_by_id[ $entry_id ] ?? null;
                $avg       = number_format( (float) $row['avg_score'], 2 );

                $title = '';
                if ( $entry ) {
                    $fields = WPCJ_CG_Reader::get_entry_fields( $entry_id, $identity_ids );
                    foreach ( $fields as $f ) {
                        if ( $f['Short_Text'] !== '' ) { $title = $f['Short_Text']; break; }
                    }
                }

                $author = '';
                if ( $entry && (int) $entry['WpUserId'] > 0 ) {
                    $wp_user = WPCJ_CG_Reader::get_wp_user_info( (int) $entry['WpUserId'] );
                    if ( $wp_user ) {
                        $author = trim( $wp_user['last_name'] . ' ' . $wp_user['first_name'] );
                    }
                }
            ?>
                <tr>
                    <td><strong><?php echo esc_html( $rank++ ); ?></strong></td>
                    <td>#<?php echo esc_html( $entry_id ); ?></td>
                    <td><?php echo esc_html( $title ); ?></td>
                    <td><?php echo esc_html( $author ); ?></td>
                    <td><strong><?php echo esc_html( $avg ); ?></strong> / 5</td>
                    <td><?php echo esc_html( $row['vote_count'] ); ?></td>
                    <td><?php echo esc_html( $row['total_score'] ); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
