<?php
defined( 'ABSPATH' ) || exit;

if ( ! current_user_can( 'wpcj_manage_rounds' ) ) {
    wp_die( esc_html__( 'Access denied.', 'wp-contest-jury' ) );
}

$rounds    = WPCJ_DB::get_rounds();
$galleries = WPCJ_Settings::get_galleries();
?>
<div class="wrap wpcj-wrap">
    <h1><?php esc_html_e( 'Voting Rounds', 'wp-contest-jury' ); ?></h1>

    <?php if ( isset( $_GET['wpcj_error'] ) && $_GET['wpcj_error'] === 'duplicate_type' ) : ?>
        <div class="notice notice-error is-dismissible">
            <p><?php esc_html_e( 'A round of this type already exists for the selected gallery. Each gallery can have only one Initial, Shortlist, Final, and Winner round.', 'wp-contest-jury' ); ?></p>
        </div>
    <?php endif; ?>
    <?php if ( isset( $_GET['wpcj_created'] ) ) : ?>
        <div class="notice notice-success is-dismissible">
            <p><?php esc_html_e( 'Round created.', 'wp-contest-jury' ); ?></p>
        </div>
    <?php endif; ?>

    <?php if ( empty( $galleries ) ) : ?>
        <div class="notice notice-warning">
            <p>
                <?php esc_html_e( 'No galleries configured yet.', 'wp-contest-jury' ); ?>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=wpcj-settings' ) ); ?>">
                    <?php esc_html_e( 'Go to Settings →', 'wp-contest-jury' ); ?>
                </a>
            </p>
        </div>
    <?php else : ?>

        <h2><?php esc_html_e( 'Create New Round', 'wp-contest-jury' ); ?></h2>
        <form method="post" action="">
            <?php wp_nonce_field( 'wpcj_create_round', 'wpcj_nonce' ); ?>
            <input type="hidden" name="wpcj_action" value="create_round">
            <table class="form-table">
                <tr>
                    <th><label for="round_name"><?php esc_html_e( 'Round Name', 'wp-contest-jury' ); ?></label></th>
                    <td><input type="text" id="round_name" name="round_name" class="regular-text" required></td>
                </tr>
                <tr>
                    <th><label for="gallery_id"><?php esc_html_e( 'Gallery', 'wp-contest-jury' ); ?></label></th>
                    <td>
                        <select id="gallery_id" name="gallery_id">
                            <?php foreach ( $galleries as $gid => $label ) : ?>
                                <option value="<?php echo esc_attr( $gid ); ?>"><?php echo esc_html( $label ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th><label for="round_type"><?php esc_html_e( 'Round Type', 'wp-contest-jury' ); ?></label></th>
                    <td>
                        <select id="round_type" name="round_type">
                            <option value="<?php echo esc_attr( WPCJ_DB::ROUND_INITIAL ); ?>"><?php esc_html_e( 'Initial', 'wp-contest-jury' ); ?></option>
                            <option value="<?php echo esc_attr( WPCJ_DB::ROUND_SHORTLIST ); ?>"><?php esc_html_e( 'Shortlist', 'wp-contest-jury' ); ?></option>
                            <option value="<?php echo esc_attr( WPCJ_DB::ROUND_FINAL ); ?>"><?php esc_html_e( 'Final', 'wp-contest-jury' ); ?></option>
                            <option value="<?php echo esc_attr( WPCJ_DB::ROUND_WINNER ); ?>"><?php esc_html_e( 'Winner', 'wp-contest-jury' ); ?></option>
                        </select>
                        <p class="description"><?php esc_html_e( 'Each gallery can have one round per type.', 'wp-contest-jury' ); ?></p>
                    </td>
                </tr>
            </table>
            <?php submit_button( __( 'Create Round', 'wp-contest-jury' ) ); ?>
        </form>

    <?php endif; ?>

    <h2><?php esc_html_e( 'All Rounds', 'wp-contest-jury' ); ?></h2>

    <p class="wpcj-notice <?php echo WPCJ_Settings::show_author_name() ? 'wpcj-notice-transp' : 'wpcj-notice-anon'; ?>">
        <?php if ( WPCJ_Settings::show_author_name() ) : ?>
            &#128100; <?php esc_html_e( 'Voting mode: author name visible to jurors (configured in Settings).', 'wp-contest-jury' ); ?>
        <?php else : ?>
            &#128065; <?php esc_html_e( 'Voting mode: anonymous - jurors do not see the author\'s name (configured in Settings).', 'wp-contest-jury' ); ?>
        <?php endif; ?>
    </p>

    <?php if ( empty( $rounds ) ) : ?>
        <p><?php esc_html_e( 'No rounds yet.', 'wp-contest-jury' ); ?></p>
    <?php else : ?>
        <table class="widefat striped">
            <thead>
                <tr>
                    <th>ID</th>
                    <th><?php esc_html_e( 'Name', 'wp-contest-jury' ); ?></th>
                    <th><?php esc_html_e( 'Gallery', 'wp-contest-jury' ); ?></th>
                    <th><?php esc_html_e( 'Type', 'wp-contest-jury' ); ?></th>
                    <th><?php esc_html_e( 'Status', 'wp-contest-jury' ); ?></th>
                    <th><?php esc_html_e( 'Actions', 'wp-contest-jury' ); ?></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ( $rounds as $round ) : ?>
                <tr>
                    <td><?php echo esc_html( $round['id'] ); ?></td>
                    <td><?php echo esc_html( $round['name'] ); ?></td>
                    <td><?php echo esc_html( WPCJ_Settings::get_gallery_label( (int) $round['gallery_id'] ) ); ?></td>
                    <td><span class="wpcj-round-type"><?php echo esc_html( $round['round_type'] ); ?></span></td>
                    <td><span class="wpcj-status wpcj-status-<?php echo esc_attr( $round['status'] ); ?>"><?php echo esc_html( $round['status'] ); ?></span></td>
                    <td>
                        <?php if ( $round['status'] === 'draft' ) : ?>
                            <a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=wpcj-rounds&wpcj_action=open_round&round=' . $round['id'] ), 'wpcj_open_round_' . $round['id'] ) ); ?>">
                                <?php esc_html_e( 'Open', 'wp-contest-jury' ); ?>
                            </a>
                        <?php elseif ( $round['status'] === 'open' ) : ?>
                            <a class="button button-secondary" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=wpcj-rounds&wpcj_action=close_round&round=' . $round['id'] ), 'wpcj_close_round_' . $round['id'] ) ); ?>">
                                <?php esc_html_e( 'Close', 'wp-contest-jury' ); ?>
                            </a>
                        <?php endif; ?>
                        <?php if ( $round['status'] === 'closed' ) : ?>
                            <a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=wpcj-rounds&wpcj_action=results&round=' . $round['id'] ) ); ?>">
                                <?php esc_html_e( 'Results', 'wp-contest-jury' ); ?>
                            </a>
                        <?php endif; ?>
                        <?php if ( $round['status'] !== 'open' ) : ?>
                            <a class="button wpcj-btn-delete"
                               href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=wpcj-rounds&wpcj_action=delete_round&round=' . $round['id'] ), 'wpcj_delete_round_' . $round['id'] ) ); ?>"
                               onclick="return confirm('<?php esc_attr_e( 'Delete this round and all its votes? This cannot be undone.', 'wp-contest-jury' ); ?>')">
                                <?php esc_html_e( 'Delete', 'wp-contest-jury' ); ?>
                            </a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
