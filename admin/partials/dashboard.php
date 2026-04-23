<?php
defined( 'ABSPATH' ) || exit;

$rounds = WPCJ_DB::get_rounds();
$open   = array_filter( $rounds, fn( $r ) => $r['status'] === 'open' );
?>
<div class="wrap wpcj-wrap">
    <h1><?php esc_html_e( 'Jury Panel - Dashboard', 'wp-contest-jury' ); ?></h1>

    <div class="wpcj-cards">
        <div class="wpcj-card">
            <h3><?php esc_html_e( 'Total Rounds', 'wp-contest-jury' ); ?></h3>
            <span class="wpcj-count"><?php echo count( $rounds ); ?></span>
        </div>
        <div class="wpcj-card">
            <h3><?php esc_html_e( 'Open Rounds', 'wp-contest-jury' ); ?></h3>
            <span class="wpcj-count wpcj-open"><?php echo count( $open ); ?></span>
        </div>
    </div>

    <?php if ( ! empty( $open ) ) : ?>
        <h2><?php esc_html_e( 'Currently Open', 'wp-contest-jury' ); ?></h2>
        <table class="widefat striped">
            <thead>
                <tr>
                    <th><?php esc_html_e( 'Round', 'wp-contest-jury' ); ?></th>
                    <th><?php esc_html_e( 'Gallery', 'wp-contest-jury' ); ?></th>
                    <th><?php esc_html_e( 'Action', 'wp-contest-jury' ); ?></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ( $open as $round ) : ?>
                <tr>
                    <td><?php echo esc_html( $round['name'] ); ?></td>
                    <td><?php echo esc_html( WPCJ_Settings::get_gallery_label( (int) $round['gallery_id'] ) ); ?></td>
                    <td>
                        <a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=wpcj-vote&round=' . $round['id'] ) ); ?>">
                            <?php esc_html_e( 'Go to Voting', 'wp-contest-jury' ); ?>
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php else : ?>
        <p><?php esc_html_e( 'No voting rounds are currently open.', 'wp-contest-jury' ); ?></p>
    <?php endif; ?>
</div>
