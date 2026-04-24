<?php
defined( 'ABSPATH' ) || exit;
// Variables provided by WPCJ_Frontend::render_panel():
//   $round, $round_id, $juror_id, $readonly (bool)

$gallery_id    = (int) $round['gallery_id'];
$panel_url     = get_permalink( WPCJ_Settings::get_jury_page_id() ) ?: get_permalink();
$is_anonymous  = ! WPCJ_Settings::show_author_name();
$require_all   = WPCJ_Settings::require_all_votes();

// Allowed filter values
$filter  = in_array( $_GET['jury_filter'] ?? '', array( 'all', 'unvoted', 'voted' ), true )
           ? sanitize_key( $_GET['jury_filter'] )
           : 'all';
$per_page = 20;
$paged    = max( 1, absint( $_GET['jury_paged'] ?? 1 ) );

// Load all entries + votes for this juror
$all_entries   = WPCJ_CG_Reader::get_entries( $gallery_id );
$my_votes_raw  = WPCJ_DB::get_votes_by_juror( $round_id, $juror_id );
$my_votes      = array_column( $my_votes_raw, null, 'entry_id' );
$total_entries = count( $all_entries );
$voted_count   = count( $my_votes );

// Apply filter
$filtered = array_values( array_filter( $all_entries, function ( $e ) use ( $filter, $my_votes ) {
    $voted = isset( $my_votes[ (int) $e['id'] ] );
    if ( $filter === 'unvoted' ) return ! $voted;
    if ( $filter === 'voted' )   return $voted;
    return true;
} ) );

$total_filtered = count( $filtered );
$total_pages    = max( 1, (int) ceil( $total_filtered / $per_page ) );
$paged          = min( $paged, $total_pages );
$page_entries   = array_slice( $filtered, ( $paged - 1 ) * $per_page, $per_page );

$identity_ids = WPCJ_CG_Reader::get_identity_field_ids( $gallery_id );
$field_labels = WPCJ_CG_Reader::get_field_labels( $gallery_id );
$can_submit   = ! $require_all || ( $voted_count >= $total_entries );
$progress_pct = $total_entries > 0 ? round( $voted_count / $total_entries * 100 ) : 0;

// URL helper
$url_base = remove_query_arg( array( 'jury_paged', 'jury_filter' ), $panel_url );
$url_base = add_query_arg( 'jury_round', $round_id, $url_base );
?>
<div class="wpcj-panel wpcj-voting-wrap"
     data-total="<?php echo esc_attr( $total_entries ); ?>"
     data-voted="<?php echo esc_attr( $voted_count ); ?>"
     data-round="<?php echo esc_attr( $round_id ); ?>"
     data-readonly="<?php echo $readonly ? '1' : '0'; ?>"
     data-require-all="<?php echo $require_all ? '1' : '0'; ?>">

    <!-- Header bar -->
    <div class="wpcj-voting-header">
        <div class="wpcj-voting-header__left">
            <a href="<?php echo esc_url( $panel_url ); ?>" class="wpcj-back-link">
                &larr; <?php esc_html_e( 'All Rounds', 'wp-contest-jury' ); ?>
            </a>
            <span class="wpcj-round-name">
                <?php echo esc_html( $round['name'] ); ?> &mdash;
                <?php echo esc_html( WPCJ_Settings::get_gallery_label( $gallery_id ) ); ?>
            </span>
        </div>
        <div class="wpcj-voting-header__right">
            <?php if ( ! $readonly ) : ?>
                <button class="wpcj-btn wpcj-btn--submit <?php echo $can_submit ? '' : 'wpcj-btn--disabled'; ?>"
                        id="wpcj-submit-btn"
                        data-round="<?php echo esc_attr( $round_id ); ?>"
                        <?php echo ! $can_submit ? 'disabled' : ''; ?>>
                    <?php esc_html_e( 'Submit Votes', 'wp-contest-jury' ); ?>
                </button>
            <?php else : ?>
                <span class="wpcj-readonly-badge"><?php esc_html_e( 'Read-only', 'wp-contest-jury' ); ?></span>
            <?php endif; ?>
        </div>
    </div>

    <!-- Progress -->
    <div class="wpcj-progress-wrap">
        <div class="wpcj-progress-bar">
            <div class="wpcj-progress-bar__fill" id="wpcj-progress-fill" style="width:<?php echo esc_attr( $progress_pct ); ?>%"></div>
        </div>
        <span class="wpcj-progress-label" id="wpcj-progress-label">
            <?php printf( esc_html__( 'Voted: %1$d / %2$d', 'wp-contest-jury' ), $voted_count, $total_entries ); ?>
        </span>
    </div>

    <!-- Filter tabs -->
    <div class="wpcj-filter-tabs">
        <?php
        $tabs = array(
            'all'     => array( 'label' => __( 'All', 'wp-contest-jury' ),     'count' => $total_entries ),
            'unvoted' => array( 'label' => __( 'To vote', 'wp-contest-jury' ), 'count' => $total_entries - $voted_count ),
            'voted'   => array( 'label' => __( 'Voted', 'wp-contest-jury' ),   'count' => $voted_count ),
        );
        foreach ( $tabs as $key => $tab ) :
            $tab_url = add_query_arg( array( 'jury_filter' => $key, 'jury_paged' => 1 ), $url_base );
        ?>
            <a href="<?php echo esc_url( $tab_url ); ?>"
               class="wpcj-filter-tab <?php echo $filter === $key ? 'active' : ''; ?>"
               data-filter="<?php echo esc_attr( $key ); ?>">
                <?php echo esc_html( $tab['label'] ); ?>
                <span class="wpcj-tab-count">(<?php echo esc_html( $tab['count'] ); ?>)</span>
            </a>
        <?php endforeach; ?>
    </div>

    <!-- Entry grid -->
    <?php if ( empty( $page_entries ) ) : ?>
        <p class="wpcj-empty"><?php esc_html_e( 'No entries to show.', 'wp-contest-jury' ); ?></p>
    <?php else : ?>
    <div class="wpcj-entries-front">
        <?php foreach ( $page_entries as $entry ) :
            $entry_id   = (int) $entry['id'];
            $vote       = $my_votes[ $entry_id ] ?? null;
            $score      = $vote ? (int) $vote['score'] : 0;
            $notes      = $vote ? $vote['notes'] : '';
            $is_voted   = $score > 0;
            $thumb_url  = WPCJ_CG_Reader::get_thumbnail_url( $gallery_id, (int) $entry['Timestamp'], $entry['NamePic'], $entry['ImgType'] );
            $full_url   = WPCJ_CG_Reader::get_image_url( $gallery_id, (int) $entry['Timestamp'], $entry['NamePic'], $entry['ImgType'] );

            $fields = WPCJ_CG_Reader::get_entry_fields( $entry_id, $identity_ids );
            $title  = '';
            foreach ( $fields as $f ) {
                if ( $f['Short_Text'] !== '' ) { $title = $f['Short_Text']; break; }
            }

            $author = '';
            if ( ! $is_anonymous && (int) ( $entry['WpUserId'] ?? 0 ) > 0 ) {
                $wp_user = WPCJ_CG_Reader::get_wp_user_info( (int) $entry['WpUserId'] );
                if ( $wp_user ) {
                    $author = trim( $wp_user['last_name'] . ' ' . $wp_user['first_name'] );
                }
            }
        ?>
        <div class="wpcj-entry-front <?php echo $is_voted ? 'is-voted' : 'is-unvoted'; ?>"
             data-entry-id="<?php echo esc_attr( $entry_id ); ?>"
             data-round-id="<?php echo esc_attr( $round_id ); ?>">

            <a href="<?php echo esc_url( $full_url ); ?>" target="_blank" class="wpcj-entry-front__thumb-link">
                <?php if ( $is_voted ) : ?>
                    <span class="wpcj-voted-badge" title="<?php esc_attr_e( 'Voted', 'wp-contest-jury' ); ?>">&#10003;</span>
                <?php endif; ?>
                <span class="wpcj-score-badge <?php echo $is_voted ? '' : 'wpcj-score-badge--hidden'; ?>"><?php echo $is_voted ? esc_html( $score . '/5' ) : ''; ?></span>
                <img src="<?php echo esc_url( $thumb_url ); ?>"
                     alt="<?php echo $is_anonymous ? esc_attr( sprintf( '#%d', $entry_id ) ) : esc_attr( $entry['NamePic'] ); ?>"
                     loading="lazy">
            </a>

            <div class="wpcj-entry-front__meta">
                <span class="wpcj-entry-front__id">#<?php echo esc_html( $entry_id ); ?></span>
                <?php if ( $title !== '' ) : ?>
                    <span class="wpcj-entry-front__title"><?php echo esc_html( $title ); ?></span>
                <?php endif; ?>
                <?php if ( $author !== '' ) : ?>
                    <span class="wpcj-entry-front__author"><?php echo esc_html( $author ); ?></span>
                <?php endif; ?>
            </div>

            <div class="wpcj-entry-front__vote">
                <div class="wpcj-stars-front" data-score="<?php echo esc_attr( $score ); ?>">
                    <?php for ( $s = 1; $s <= 5; $s++ ) : ?>
                        <button class="wpcj-star-front <?php echo $s <= $score ? 'active' : ''; ?>"
                                data-value="<?php echo $s; ?>"
                                <?php echo $readonly ? 'disabled' : ''; ?>>
                            &#9733;
                        </button>
                    <?php endfor; ?>
                </div>
                <?php if ( ! $readonly ) : ?>
                    <textarea class="wpcj-notes-front"
                              placeholder="<?php esc_attr_e( 'Notes (optional)', 'wp-contest-jury' ); ?>"
                              rows="2"><?php echo esc_textarea( $notes ); ?></textarea>
                    <span class="wpcj-save-indicator" aria-live="polite"></span>
                <?php elseif ( $notes !== '' ) : ?>
                    <p class="wpcj-notes-readonly"><?php echo esc_html( $notes ); ?></p>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Pagination -->
    <?php if ( $total_pages > 1 ) : ?>
    <nav class="wpcj-pagination">
        <?php if ( $paged > 1 ) : ?>
            <a href="<?php echo esc_url( add_query_arg( array( 'jury_paged' => $paged - 1, 'jury_filter' => $filter ), $url_base ) ); ?>"
               class="wpcj-page-btn">&larr;</a>
        <?php endif; ?>

        <?php for ( $i = 1; $i <= $total_pages; $i++ ) :
            // Show first, last, current ±2, and ellipsis elsewhere
            $show = ( $i === 1 || $i === $total_pages || abs( $i - $paged ) <= 2 );
            if ( ! $show ) {
                if ( $i === 2 || $i === $total_pages - 1 ) echo '<span class="wpcj-page-ellipsis">&hellip;</span>';
                continue;
            }
        ?>
            <a href="<?php echo esc_url( add_query_arg( array( 'jury_paged' => $i, 'jury_filter' => $filter ), $url_base ) ); ?>"
               class="wpcj-page-btn <?php echo $i === $paged ? 'active' : ''; ?>">
               <?php echo esc_html( $i ); ?>
            </a>
        <?php endfor; ?>

        <?php if ( $paged < $total_pages ) : ?>
            <a href="<?php echo esc_url( add_query_arg( array( 'jury_paged' => $paged + 1, 'jury_filter' => $filter ), $url_base ) ); ?>"
               class="wpcj-page-btn">&rarr;</a>
        <?php endif; ?>
    </nav>
    <?php endif; ?>
    <?php endif; ?>

</div>
