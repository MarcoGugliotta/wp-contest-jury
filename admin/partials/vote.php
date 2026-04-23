<?php
defined( 'ABSPATH' ) || exit;

if ( ! current_user_can( 'wpcj_vote' ) ) {
    wp_die( esc_html__( 'Access denied.', 'wp-contest-jury' ) );
}

$round_id = isset( $_GET['round'] ) ? absint( $_GET['round'] ) : 0;
$round    = $round_id ? WPCJ_DB::get_round( $round_id ) : null;

if ( ! $round || $round['status'] !== 'open' ) : ?>
    <div class="wrap wpcj-wrap">
        <h1><?php esc_html_e( 'Vote', 'wp-contest-jury' ); ?></h1>
        <p><?php esc_html_e( 'No open voting round selected. Please go to the Dashboard and select an open round.', 'wp-contest-jury' ); ?></p>
    </div>
    <?php return;
endif;

$gallery_id        = (int) $round['gallery_id'];
$is_anonymous      = ! WPCJ_Settings::show_author_name();
$entries           = WPCJ_CG_Reader::get_entries( $gallery_id );
$identity_field_ids = WPCJ_CG_Reader::get_identity_field_ids( $gallery_id );
$field_labels       = WPCJ_CG_Reader::get_field_labels( $gallery_id );
$juror_id        = get_current_user_id();
$my_votes_raw    = WPCJ_DB::get_votes_by_juror( $round_id, $juror_id );
$my_votes        = array_column( $my_votes_raw, null, 'entry_id' );
?>
<div class="wrap wpcj-wrap">
    <h1><?php
        $gallery_label = WPCJ_Settings::get_gallery_label( $gallery_id );
        printf(
            esc_html__( 'Voting: %1$s — %2$s', 'wp-contest-jury' ),
            esc_html( $round['name'] ),
            esc_html( $gallery_label )
        );
    ?></h1>

    <?php if ( $is_anonymous ) : ?>
        <p class="wpcj-notice wpcj-notice-anon">
            &#128065; <?php esc_html_e( 'Anonymous round — author information is hidden. Evaluate the work alone.', 'wp-contest-jury' ); ?>
        </p>
    <?php else : ?>
        <p class="wpcj-notice wpcj-notice-transp">
            &#128100; <?php esc_html_e( 'Transparent round — author information is visible below each entry.', 'wp-contest-jury' ); ?>
        </p>
    <?php endif; ?>

    <div class="wpcj-entries-grid">
        <?php foreach ( $entries as $entry ) :
            $entry_id  = (int) $entry['id'];
            $thumb_url = WPCJ_CG_Reader::get_thumbnail_url( $gallery_id, (int) $entry['Timestamp'], $entry['NamePic'], $entry['ImgType'] );
            $full_url  = WPCJ_CG_Reader::get_image_url( $gallery_id, (int) $entry['Timestamp'], $entry['NamePic'], $entry['ImgType'] );
            $voted     = $my_votes[ $entry_id ] ?? null;
            $score     = $voted ? (int) $voted['score'] : 0;
            $notes     = $voted ? esc_attr( $voted['notes'] ) : '';

            $author_name = '';
            if ( ! $is_anonymous ) {
                $wp_user_id = (int) ( $entry['WpUserId'] ?? 0 );
                $author     = WPCJ_CG_Reader::get_wp_user_info( $wp_user_id );
                if ( $author ) {
                    $author_name = trim( $author['last_name'] . ' ' . $author['first_name'] );
                }
            }

            // Form fields excluding author identity fields (field_name / field_surname)
            $fields    = WPCJ_CG_Reader::get_entry_fields( $entry_id, $identity_field_ids );
            $title     = '';
            $title_fid = null;
            foreach ( $fields as $f ) {
                if ( $title === '' && $f['Short_Text'] !== '' ) {
                    $title     = $f['Short_Text'];
                    $title_fid = (int) $f['f_input_id'];
                }
            }
        ?>
        <div class="wpcj-entry-card" data-entry-id="<?php echo esc_attr( $entry_id ); ?>">
            <a href="<?php echo esc_url( $full_url ); ?>" target="_blank">
                <img src="<?php echo esc_url( $thumb_url ); ?>"
                     alt="<?php echo $is_anonymous ? esc_attr( sprintf( __( 'Entry #%d', 'wp-contest-jury' ), $entry_id ) ) : esc_attr( $entry['NamePic'] ); ?>">
            </a>

            <div class="wpcj-entry-meta">
                <span class="wpcj-entry-id">#<?php echo esc_html( $entry_id ); ?></span>
                <?php if ( $title !== '' ) : ?>
                    <div class="wpcj-entry-title"><?php echo esc_html( $title ); ?></div>
                <?php endif; ?>
                <?php if ( ! $is_anonymous && $author_name !== '' ) : ?>
                    <div class="wpcj-author-info">
                        <span class="wpcj-author-name"><?php echo esc_html( $author_name ); ?></span>
                    </div>
                <?php endif; ?>
                <?php if ( ! empty( $fields ) ) : ?>
                    <details class="wpcj-entry-details">
                        <summary><?php esc_html_e( 'Details', 'wp-contest-jury' ); ?></summary>
                        <div class="wpcj-entry-details-body">
                            <?php foreach ( $fields as $f ) : ?>
                                <?php if ( (int) $f['f_input_id'] === $title_fid ) : continue; endif; ?>
                                <?php
                                $fid   = (int) $f['f_input_id'];
                                $label = $field_labels[ $fid ] ?? '';
                                ?>
                                <?php if ( $f['Short_Text'] !== '' ) : ?>
                                    <p class="wpcj-field-text">
                                        <?php if ( $label !== '' ) : ?><span class="wpcj-field-label"><?php echo esc_html( $label ); ?>:</span> <?php endif; ?>
                                        <?php echo esc_html( $f['Short_Text'] ); ?>
                                    </p>
                                <?php endif; ?>
                                <?php if ( $f['Long_Text'] !== '' ) : ?>
                                    <p class="wpcj-field-long">
                                        <?php if ( $label !== '' ) : ?><span class="wpcj-field-label"><?php echo esc_html( $label ); ?>:</span> <?php endif; ?>
                                        <?php echo nl2br( esc_html( $f['Long_Text'] ) ); ?>
                                    </p>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    </details>
                <?php endif; ?>
            </div>

            <div class="wpcj-vote-controls">
                <div class="wpcj-stars" data-score="<?php echo esc_attr( $score ); ?>">
                    <?php for ( $s = 1; $s <= 5; $s++ ) : ?>
                        <button class="wpcj-star <?php echo $s <= $score ? 'active' : ''; ?>"
                                data-value="<?php echo $s; ?>">&#9733;</button>
                    <?php endfor; ?>
                </div>
                <textarea class="wpcj-notes" placeholder="<?php esc_attr_e( 'Notes (optional)', 'wp-contest-jury' ); ?>"><?php echo $notes; ?></textarea>
                <button class="button button-primary wpcj-save-vote"
                        data-round="<?php echo esc_attr( $round_id ); ?>"
                        data-entry="<?php echo esc_attr( $entry_id ); ?>">
                    <?php echo $score > 0 ? esc_html__( 'Update', 'wp-contest-jury' ) : esc_html__( 'Save Vote', 'wp-contest-jury' ); ?>
                </button>
                <?php if ( $score > 0 ) : ?>
                    <span class="wpcj-saved-indicator"><?php esc_html_e( 'Saved', 'wp-contest-jury' ); ?> &#10003;</span>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
