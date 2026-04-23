<?php
defined( 'ABSPATH' ) || exit;

if ( ! current_user_can( 'wpcj_manage_jurors' ) ) {
    wp_die( esc_html__( 'Access denied.', 'wp-contest-jury' ) );
}

$jurors = get_users( array( 'role__in' => array( 'jury_chief', 'jury_member' ) ) );
$all_users = get_users( array( 'role__not_in' => array( 'jury_chief', 'jury_member', 'administrator' ) ) );
?>
<div class="wrap wpcj-wrap">
    <h1><?php esc_html_e( 'Juror Management', 'wp-contest-jury' ); ?></h1>

    <h2><?php esc_html_e( 'Current Jurors', 'wp-contest-jury' ); ?></h2>
    <?php if ( empty( $jurors ) ) : ?>
        <p><?php esc_html_e( 'No jurors assigned yet.', 'wp-contest-jury' ); ?></p>
    <?php else : ?>
        <table class="widefat striped">
            <thead>
                <tr>
                    <th><?php esc_html_e( 'Name', 'wp-contest-jury' ); ?></th>
                    <th><?php esc_html_e( 'Email', 'wp-contest-jury' ); ?></th>
                    <th><?php esc_html_e( 'Role', 'wp-contest-jury' ); ?></th>
                    <th><?php esc_html_e( 'Actions', 'wp-contest-jury' ); ?></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ( $jurors as $user ) :
                $is_chief = in_array( 'jury_chief', (array) $user->roles, true );
            ?>
                <tr>
                    <td><?php echo esc_html( $user->display_name ); ?></td>
                    <td><?php echo esc_html( $user->user_email ); ?></td>
                    <td><?php echo $is_chief ? esc_html__( 'Jury Chief', 'wp-contest-jury' ) : esc_html__( 'Jury Member', 'wp-contest-jury' ); ?></td>
                    <td>
                        <a class="button button-link-delete"
                           href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=wpcj-jurors&wpcj_action=remove_juror&user=' . $user->ID ), 'wpcj_remove_juror_' . $user->ID ) ); ?>">
                            <?php esc_html_e( 'Remove', 'wp-contest-jury' ); ?>
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <h2><?php esc_html_e( 'Add Juror', 'wp-contest-jury' ); ?></h2>
    <form method="post" action="">
        <?php wp_nonce_field( 'wpcj_add_juror', 'wpcj_nonce' ); ?>
        <input type="hidden" name="wpcj_action" value="add_juror">
        <table class="form-table">
            <tr>
                <th><label for="juror_user_id"><?php esc_html_e( 'WordPress User', 'wp-contest-jury' ); ?></label></th>
                <td>
                    <select id="juror_user_id" name="juror_user_id" required>
                        <option value=""><?php esc_html_e( '- Select user -', 'wp-contest-jury' ); ?></option>
                        <?php foreach ( $all_users as $u ) : ?>
                            <option value="<?php echo esc_attr( $u->ID ); ?>">
                                <?php echo esc_html( $u->display_name . ' (' . $u->user_email . ')' ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label for="juror_role"><?php esc_html_e( 'Role', 'wp-contest-jury' ); ?></label></th>
                <td>
                    <select id="juror_role" name="juror_role">
                        <option value="jury_member"><?php esc_html_e( 'Jury Member', 'wp-contest-jury' ); ?></option>
                        <option value="jury_chief"><?php esc_html_e( 'Jury Chief', 'wp-contest-jury' ); ?></option>
                    </select>
                </td>
            </tr>
        </table>
        <?php submit_button( __( 'Add Juror', 'wp-contest-jury' ) ); ?>
    </form>
</div>
