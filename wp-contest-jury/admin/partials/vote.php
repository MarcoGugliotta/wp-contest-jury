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

$gallery_id   = (int) $round['gallery_id'];
$is_anonymous = (bool) $round['anonymous'];
$entries      = WPCJ_CG_Reader::get_entries( $gallery_id );
$juror_id     = get_current_user_id();
$my_votes_raw = WPCJ_DB::get_votes_by_juror( $round_id, $juror_id );
$my_votes     = array_column( $my_votes_raw, null, 'entry_id' );
?>
<div class="wrap wpcj-wrap">
    <h1><?php printf( esc_html__( 'Voting: %s', 'wp-contest-jury' ), esc_html( $round['name'] ) ); ?></h1>

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
            $thumb_url = WPCJ_CG_Reader::get_thumbnail_url( $gallery_id, $entry['NamePic'] );
            $full_url  = WPCJ_CG_Reader::get_image_url( $gallery_id, $entry['NamePic'] );
            $voted     = $my_votes[ $entry_id ] ?? null;
            $score     = $voted ? (int) $voted['score'] : 0;
            $notes     = $voted ? esc_attr( $voted['notes'] ) : '';

            // Author data — loaded only when the round is transparent
            $author_fields = ( ! $is_anonymous )
                ? WPCJ_CG_Reader::get_author_data( $gallery_id, $entry_id )
                : array();
        ?>
        <div class="wpcj-entry-card" data-entry-id="<?php echo esc_attr( $entry_id ); ?>">
            <a href="<?php echo esc_url( $full_url ); ?>" target="_blank">
                <img src="<?php echo esc_url( $thumb_url ); ?>"
                     alt="<?php echo $is_anonymous ? esc_attr( sprintf( __( 'Entry #%d', 'wp-contest-jury' ), $entry_id ) ) : esc_attr( $entry['NamePic'] ); ?>">
            </a>

            <div class="wpcj-entry-meta">
                <?php if ( $is_anonymous ) : ?>
                    <span class="wpcj-entry-id">#<?php echo esc_html( $entry_id ); ?></span>
                <?php else : ?>
                    <span class="wpcj-entry-id">#<?php echo esc_html( $entry_id ); ?></span>
                    <?php if ( ! empty( $author_fields ) ) : ?>
                        <div class="wpcj-author-info">
                            <?php foreach ( $author_fields as $field ) : ?>
                                <?php if ( ! empty( $field['Short_Text'] ) ) : ?>
                                    <span class="wpcj-author-field"><?php echo esc_html( $field['Short_Text'] ); ?></span>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
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
