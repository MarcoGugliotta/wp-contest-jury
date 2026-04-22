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
                    <th><?php esc_html_e( 'Voting Mode', 'wp-contest-jury' ); ?></th>
                    <td>
                        <fieldset>
                            <label>
                                <input type="radio" name="anonymous" value="1" checked>
                                <strong><?php esc_html_e( 'Anonymous', 'wp-contest-jury' ); ?></strong>
                                &mdash; <?php esc_html_e( 'jurors do not see the author\'s name or identity.', 'wp-contest-jury' ); ?>
                            </label>
                            <br>
                            <label>
                                <input type="radio" name="anonymous" value="0">
                                <strong><?php esc_html_e( 'Transparent', 'wp-contest-jury' ); ?></strong>
                                &mdash; <?php esc_html_e( 'author information is visible alongside the entry.', 'wp-contest-jury' ); ?>
                            </label>
                        </fieldset>
                    </td>
                </tr>
            </table>
            <?php submit_button( __( 'Create Round', 'wp-contest-jury' ) ); ?>
        </form>

    <?php endif; ?>

    <h2><?php esc_html_e( 'All Rounds', 'wp-contest-jury' ); ?></h2>
    <?php if ( empty( $rounds ) ) : ?>
        <p><?php esc_html_e( 'No rounds yet.', 'wp-contest-jury' ); ?></p>
    <?php else : ?>
        <table class="widefat striped">
            <thead>
                <tr>
                    <th>ID</th>
                    <th><?php esc_html_e( 'Name', 'wp-contest-jury' ); ?></th>
                    <th><?php esc_html_e( 'Gallery', 'wp-contest-jury' ); ?></th>
                    <th><?php esc_html_e( 'Mode', 'wp-contest-jury' ); ?></th>
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
                    <td>
                        <?php if ( $round['anonymous'] ) : ?>
                            <span class="wpcj-mode wpcj-mode-anon" title="<?php esc_attr_e( 'Jurors do not see the author', 'wp-contest-jury' ); ?>">
                                &#128065; <?php esc_html_e( 'Anonymous', 'wp-contest-jury' ); ?>
                            </span>
                        <?php else : ?>
                            <span class="wpcj-mode wpcj-mode-transp" title="<?php esc_attr_e( 'Author info is visible to jurors', 'wp-contest-jury' ); ?>">
                                &#128100; <?php esc_html_e( 'Transparent', 'wp-contest-jury' ); ?>
                            </span>
                        <?php endif; ?>
                    </td>
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
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
