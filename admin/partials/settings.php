<?php
defined( 'ABSPATH' ) || exit;

if ( ! current_user_can( 'wpcj_manage_rounds' ) ) {
    wp_die( esc_html__( 'Access denied.', 'wp-contest-jury' ) );
}

$settings  = WPCJ_Settings::get_all();
$galleries = $settings['galleries'];
?>
<div class="wrap wpcj-wrap">
    <h1><?php esc_html_e( 'Jury Plugin Settings', 'wp-contest-jury' ); ?></h1>

    <?php if ( isset( $_GET['saved'] ) ) : ?>
        <div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Settings saved.', 'wp-contest-jury' ); ?></p></div>
    <?php endif; ?>

    <form method="post" action="" id="wpcj-settings-form">
        <?php wp_nonce_field( 'wpcj_save_settings', 'wpcj_nonce' ); ?>
        <input type="hidden" name="wpcj_action" value="save_settings">

        <h2><?php esc_html_e( 'Contest Gallery PRO — Galleries', 'wp-contest-jury' ); ?></h2>
        <p class="description">
            <?php esc_html_e( 'Add the Contest Gallery PRO gallery IDs you want to use in this contest. The label is what jurors will see (e.g. "Single Photo", "Photo Series").', 'wp-contest-jury' ); ?>
        </p>

        <table class="widefat" id="wpcj-galleries-table">
            <thead>
                <tr>
                    <th><?php esc_html_e( 'Gallery ID (from CG PRO)', 'wp-contest-jury' ); ?></th>
                    <th><?php esc_html_e( 'Label', 'wp-contest-jury' ); ?></th>
                    <th></th>
                </tr>
            </thead>
            <tbody id="wpcj-gallery-rows">
                <?php if ( empty( $galleries ) ) :
                    // Render one empty row so the admin has something to fill in
                    $galleries = array( array( 'id' => '', 'label' => '' ) );
                endif; ?>
                <?php foreach ( $galleries as $i => $g ) : ?>
                <tr class="wpcj-gallery-row">
                    <td>
                        <input type="number" name="galleries[<?php echo $i; ?>][id]"
                               value="<?php echo esc_attr( $g['id'] ); ?>"
                               min="1" class="small-text" placeholder="e.g. 1">
                    </td>
                    <td>
                        <input type="text" name="galleries[<?php echo $i; ?>][label]"
                               value="<?php echo esc_attr( $g['label'] ); ?>"
                               class="regular-text" placeholder="<?php esc_attr_e( 'e.g. Single Photo', 'wp-contest-jury' ); ?>">
                    </td>
                    <td>
                        <button type="button" class="button wpcj-remove-gallery"><?php esc_html_e( 'Remove', 'wp-contest-jury' ); ?></button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <p>
            <button type="button" class="button" id="wpcj-add-gallery">
                + <?php esc_html_e( 'Add Gallery', 'wp-contest-jury' ); ?>
            </button>
        </p>

        <h2><?php esc_html_e( 'Voting Privacy', 'wp-contest-jury' ); ?></h2>
        <table class="form-table">
            <tr>
                <th><?php esc_html_e( 'Show author name to jurors', 'wp-contest-jury' ); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="show_author_name" value="1"
                               <?php checked( WPCJ_Settings::show_author_name() ); ?>>
                        <?php esc_html_e( 'Jurors can see the name and surname of the entry\'s author during voting.', 'wp-contest-jury' ); ?>
                    </label>
                    <p class="description">
                        <?php esc_html_e( 'When unchecked (default), voting is fully anonymous: jurors see only the entry ID and the image.', 'wp-contest-jury' ); ?>
                    </p>
                </td>
            </tr>
        </table>

        <?php submit_button( __( 'Save Settings', 'wp-contest-jury' ) ); ?>
    </form>
</div>

<script>
(function ($) {
    var rowIndex = <?php echo count( $galleries ); ?>;

    $('#wpcj-add-gallery').on('click', function () {
        var row = '<tr class="wpcj-gallery-row">' +
            '<td><input type="number" name="galleries[' + rowIndex + '][id]" min="1" class="small-text" placeholder="e.g. 1"></td>' +
            '<td><input type="text" name="galleries[' + rowIndex + '][label]" class="regular-text" placeholder="<?php echo esc_js( __( 'e.g. Single Photo', 'wp-contest-jury' ) ); ?>"></td>' +
            '<td><button type="button" class="button wpcj-remove-gallery"><?php echo esc_js( __( 'Remove', 'wp-contest-jury' ) ); ?></button></td>' +
            '</tr>';
        $('#wpcj-gallery-rows').append(row);
        rowIndex++;
    });

    $(document).on('click', '.wpcj-remove-gallery', function () {
        var $rows = $('.wpcj-gallery-row');
        if ($rows.length > 1) {
            $(this).closest('tr').remove();
        }
    });
}(jQuery));
</script>
