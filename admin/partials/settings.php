<?php
defined( 'ABSPATH' ) || exit;

if ( ! current_user_can( 'wpcj_manage_rounds' ) ) {
    wp_die( esc_html__( 'Access denied.', 'wp-contest-jury' ) );
}

$settings = WPCJ_Settings::get_all();

// Build gallery rows: CG PRO galleries merged with saved settings.
$saved_by_id = array();
foreach ( $settings['galleries'] as $g ) {
    $saved_by_id[ (int) $g['id'] ] = $g;
}

$cg_galleries = WPCJ_CG_Reader::get_cg_galleries();
if ( ! empty( $cg_galleries ) ) {
    $galleries = array();
    foreach ( $cg_galleries as $cg ) {
        $id    = (int) $cg['id'];
        $saved = $saved_by_id[ $id ] ?? array();
        $galleries[] = array(
            'id'          => $id,
            'label'       => $saved['label'] ?? $cg['name'],
            'round_types' => $saved['round_types'] ?? array(),
        );
    }
} else {
    $galleries = ! empty( $settings['galleries'] )
        ? $settings['galleries']
        : array( array( 'id' => '', 'label' => '', 'round_types' => array() ) );
}
?>
<div class="wrap wpcj-wrap">
    <h1><?php esc_html_e( 'Jury Plugin Settings', 'wp-contest-jury' ); ?></h1>

    <?php if ( isset( $_GET['saved'] ) ) : ?>
        <div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Settings saved.', 'wp-contest-jury' ); ?></p></div>
    <?php endif; ?>

    <form method="post" action="" id="wpcj-settings-form">
        <?php wp_nonce_field( 'wpcj_save_settings', 'wpcj_nonce' ); ?>
        <input type="hidden" name="wpcj_action" value="save_settings">

        <h2><?php esc_html_e( 'Contest Gallery PRO - Galleries', 'wp-contest-jury' ); ?></h2>
        <?php if ( ! empty( $cg_galleries ) ) : ?>
        <p class="description">
            <?php esc_html_e( 'Galleries are loaded automatically from Contest Gallery PRO. Set the label jurors will see for each gallery, and define the round types available for that gallery.', 'wp-contest-jury' ); ?>
        </p>
        <?php else : ?>
        <div class="notice notice-warning inline"><p>
            <?php esc_html_e( 'No galleries found in Contest Gallery PRO. Create at least one gallery in CG PRO first, then come back here.', 'wp-contest-jury' ); ?>
        </p></div>
        <?php endif; ?>

        <table class="widefat" id="wpcj-galleries-table">
            <thead>
                <tr>
                    <th><?php esc_html_e( 'Gallery ID', 'wp-contest-jury' ); ?></th>
                    <th><?php esc_html_e( 'Label', 'wp-contest-jury' ); ?></th>
                    <th>
                        <?php esc_html_e( 'Round Types', 'wp-contest-jury' ); ?>
                        <span class="description" style="font-weight:400"> — <?php esc_html_e( 'one per gallery per type (max 30 chars)', 'wp-contest-jury' ); ?></span>
                    </th>
                    <?php if ( empty( $cg_galleries ) ) : ?>
                    <th></th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody id="wpcj-gallery-rows">
                <?php foreach ( $galleries as $i => $g ) :
                    $round_types = $g['round_types'] ?? array();
                ?>
                <tr class="wpcj-gallery-row">
                    <td style="width:100px; vertical-align:top; padding-top:10px">
                        <?php if ( ! empty( $cg_galleries ) ) : ?>
                            <strong><?php echo esc_html( $g['id'] ); ?></strong>
                            <input type="hidden" name="galleries[<?php echo $i; ?>][id]" value="<?php echo esc_attr( $g['id'] ); ?>">
                        <?php else : ?>
                            <input type="number" name="galleries[<?php echo $i; ?>][id]"
                                   value="<?php echo esc_attr( $g['id'] ); ?>"
                                   min="1" class="small-text" placeholder="1">
                        <?php endif; ?>
                    </td>
                    <td style="width:200px; vertical-align:top; padding-top:10px">
                        <input type="text" name="galleries[<?php echo $i; ?>][label]"
                               value="<?php echo esc_attr( $g['label'] ); ?>"
                               class="regular-text" placeholder="<?php esc_attr_e( 'e.g. Single Photo', 'wp-contest-jury' ); ?>">
                    </td>
                    <td>
                        <div class="wpcj-round-types-list" id="wpcj-rt-<?php echo $i; ?>">
                            <?php if ( empty( $round_types ) ) : ?>
                                <div class="wpcj-rt-item">
                                    <input type="text" name="galleries[<?php echo $i; ?>][round_types][]"
                                           value="" maxlength="30" class="small-text"
                                           placeholder="<?php esc_attr_e( 'e.g. Initial', 'wp-contest-jury' ); ?>">
                                    <button type="button" class="button-link wpcj-remove-rt" title="<?php esc_attr_e( 'Remove', 'wp-contest-jury' ); ?>">&#10005;</button>
                                </div>
                            <?php else : ?>
                                <?php foreach ( $round_types as $rt ) : ?>
                                <div class="wpcj-rt-item">
                                    <input type="text" name="galleries[<?php echo $i; ?>][round_types][]"
                                           value="<?php echo esc_attr( $rt ); ?>" maxlength="30" class="small-text">
                                    <button type="button" class="button-link wpcj-remove-rt" title="<?php esc_attr_e( 'Remove', 'wp-contest-jury' ); ?>">&#10005;</button>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        <button type="button" class="button button-small wpcj-add-rt"
                                data-container="wpcj-rt-<?php echo $i; ?>"
                                data-gindex="<?php echo $i; ?>">
                            + <?php esc_html_e( 'Add type', 'wp-contest-jury' ); ?>
                        </button>
                    </td>
                    <?php if ( empty( $cg_galleries ) ) : ?>
                    <td style="vertical-align:top; padding-top:10px">
                        <button type="button" class="button wpcj-remove-gallery"><?php esc_html_e( 'Remove', 'wp-contest-jury' ); ?></button>
                    </td>
                    <?php endif; ?>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php if ( empty( $cg_galleries ) ) : ?>
        <p>
            <button type="button" class="button" id="wpcj-add-gallery">
                + <?php esc_html_e( 'Add Gallery', 'wp-contest-jury' ); ?>
            </button>
        </p>
        <?php endif; ?>

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

        <h2><?php esc_html_e( 'Jury Panel Page', 'wp-contest-jury' ); ?></h2>
        <table class="form-table">
            <tr>
                <th><label for="jury_page_id"><?php esc_html_e( 'Jury Page', 'wp-contest-jury' ); ?></label></th>
                <td>
                    <select id="jury_page_id" name="jury_page_id">
                        <option value="0"><?php esc_html_e( '— Select a page —', 'wp-contest-jury' ); ?></option>
                        <?php foreach ( get_pages() as $p ) : ?>
                            <option value="<?php echo esc_attr( $p->ID ); ?>" <?php selected( WPCJ_Settings::get_jury_page_id(), $p->ID ); ?>>
                                <?php echo esc_html( $p->post_title ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description"><?php esc_html_e( 'Page containing the [wpcj_jury_panel] shortcode. Jurors are sent here after login.', 'wp-contest-jury' ); ?></p>
                </td>
            </tr>
            <tr>
                <th><label for="welcome_message"><?php esc_html_e( 'Welcome Message', 'wp-contest-jury' ); ?></label></th>
                <td>
                    <textarea id="welcome_message" name="welcome_message" rows="4" class="large-text"><?php echo esc_textarea( WPCJ_Settings::get_welcome_message() ); ?></textarea>
                    <p class="description"><?php esc_html_e( 'Shown to jurors on the round selection screen after login. Leave empty to hide.', 'wp-contest-jury' ); ?></p>
                </td>
            </tr>
        </table>

        <h2><?php esc_html_e( 'Voting Rules', 'wp-contest-jury' ); ?></h2>
        <table class="form-table">
            <tr>
                <th><?php esc_html_e( 'Require all votes', 'wp-contest-jury' ); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="require_all_votes" value="1" <?php checked( WPCJ_Settings::require_all_votes() ); ?>>
                        <?php esc_html_e( 'Jurors must vote every entry before they can submit.', 'wp-contest-jury' ); ?>
                    </label>
                    <p class="description"><?php esc_html_e( 'When unchecked, jurors can submit at any time even with partial votes.', 'wp-contest-jury' ); ?></p>
                </td>
            </tr>
        </table>

        <?php submit_button( __( 'Save Settings', 'wp-contest-jury' ) ); ?>
    </form>
</div>

<script>
(function ($) {
    var rowIndex = <?php echo count( $galleries ); ?>;

    // ── Add / remove gallery rows (manual mode only) ────────────
    $('#wpcj-add-gallery').on('click', function () {
        var i   = rowIndex++;
        var row = '<tr class="wpcj-gallery-row">' +
            '<td style="vertical-align:top;padding-top:10px"><input type="number" name="galleries[' + i + '][id]" min="1" class="small-text" placeholder="1"></td>' +
            '<td style="vertical-align:top;padding-top:10px"><input type="text" name="galleries[' + i + '][label]" class="regular-text" placeholder="<?php echo esc_js( __( 'e.g. Single Photo', 'wp-contest-jury' ) ); ?>"></td>' +
            '<td>' +
                '<div class="wpcj-round-types-list" id="wpcj-rt-' + i + '">' +
                    '<div class="wpcj-rt-item">' +
                        '<input type="text" name="galleries[' + i + '][round_types][]" maxlength="30" class="small-text" placeholder="<?php echo esc_js( __( 'e.g. Initial', 'wp-contest-jury' ) ); ?>">' +
                        '<button type="button" class="button-link wpcj-remove-rt" title="Remove">&#10005;</button>' +
                    '</div>' +
                '</div>' +
                '<button type="button" class="button button-small wpcj-add-rt" data-container="wpcj-rt-' + i + '" data-gindex="' + i + '">+ <?php echo esc_js( __( 'Add type', 'wp-contest-jury' ) ); ?></button>' +
            '</td>' +
            '<td style="vertical-align:top;padding-top:10px"><button type="button" class="button wpcj-remove-gallery"><?php echo esc_js( __( 'Remove', 'wp-contest-jury' ) ); ?></button></td>' +
            '</tr>';
        $('#wpcj-gallery-rows').append(row);
    });

    $(document).on('click', '.wpcj-remove-gallery', function () {
        if ( $('.wpcj-gallery-row').length > 1 ) {
            $(this).closest('tr').remove();
        }
    });

    // ── Add / remove round type inputs ──────────────────────────
    $(document).on('click', '.wpcj-add-rt', function () {
        var container = $(this).data('container');
        var gindex    = $(this).data('gindex');
        var html = '<div class="wpcj-rt-item">' +
            '<input type="text" name="galleries[' + gindex + '][round_types][]" maxlength="30" class="small-text" placeholder="<?php echo esc_js( __( 'Type name', 'wp-contest-jury' ) ); ?>">' +
            '<button type="button" class="button-link wpcj-remove-rt" title="Remove">&#10005;</button>' +
            '</div>';
        $('#' + container).append(html);
        $('#' + container).find('.wpcj-rt-item:last input').focus();
    });

    $(document).on('click', '.wpcj-remove-rt', function () {
        var $list = $(this).closest('.wpcj-round-types-list');
        if ( $list.find('.wpcj-rt-item').length > 1 ) {
            $(this).closest('.wpcj-rt-item').remove();
        }
    });

}(jQuery));
</script>
