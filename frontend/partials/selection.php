<?php
defined( 'ABSPATH' ) || exit;

$juror_id    = get_current_user_id();
$all_rounds  = WPCJ_DB::get_rounds();
$submissions = WPCJ_DB::get_submissions_by_juror( $juror_id );
$panel_url   = get_permalink( WPCJ_Settings::get_jury_page_id() ) ?: get_permalink();
$welcome     = WPCJ_Settings::get_welcome_message();

// Show only rounds that are open or closed-with-submission (juror can review)
$visible = array_filter( $all_rounds, function ( $r ) use ( $submissions ) {
    return $r['status'] === 'open' || isset( $submissions[ (int) $r['id'] ] );
} );
?>
<div class="wpcj-panel wpcj-selection-wrap">

    <?php if ( $welcome !== '' ) : ?>
        <div class="wpcj-welcome-message">
            <?php echo nl2br( esc_html( $welcome ) ); ?>
        </div>
    <?php endif; ?>

    <h2 class="wpcj-section-title"><?php esc_html_e( 'Voting Rounds', 'wp-contest-jury' ); ?></h2>

    <?php if ( empty( $visible ) ) : ?>
        <p class="wpcj-empty"><?php esc_html_e( 'No voting rounds are open at the moment. Check back later.', 'wp-contest-jury' ); ?></p>
    <?php else : ?>
        <div class="wpcj-round-cards">
            <?php foreach ( $visible as $r ) :
                $rid       = (int) $r['id'];
                $submitted = isset( $submissions[ $rid ] );
                $is_closed = $r['status'] === 'closed';
                $votes     = WPCJ_DB::get_votes_by_juror( $rid, $juror_id );
                $entries   = WPCJ_CG_Reader::get_entries( (int) $r['gallery_id'] );
                $total     = count( $entries );
                $voted     = count( $votes );
                $pct       = $total > 0 ? round( $voted / $total * 100 ) : 0;
                $round_url = add_query_arg( 'jury_round', $rid, $panel_url );

                if ( $submitted || $is_closed ) {
                    $state_label = __( 'Submitted', 'wp-contest-jury' );
                    $state_class = 'wpcj-state--submitted';
                    $btn_label   = __( 'View Votes', 'wp-contest-jury' );
                    $btn_class   = 'wpcj-btn wpcj-btn--secondary';
                } elseif ( $voted > 0 ) {
                    $state_label = __( 'In Progress', 'wp-contest-jury' );
                    $state_class = 'wpcj-state--progress';
                    $btn_label   = __( 'Continue Voting', 'wp-contest-jury' );
                    $btn_class   = 'wpcj-btn wpcj-btn--primary';
                } else {
                    $state_label = __( 'Not Started', 'wp-contest-jury' );
                    $state_class = 'wpcj-state--new';
                    $btn_label   = __( 'Start Voting', 'wp-contest-jury' );
                    $btn_class   = 'wpcj-btn wpcj-btn--primary';
                }
            ?>
            <div class="wpcj-round-card">
                <div class="wpcj-round-card__header">
                    <span class="wpcj-round-card__gallery"><?php echo esc_html( WPCJ_Settings::get_gallery_label( (int) $r['gallery_id'] ) ); ?></span>
                    <span class="wpcj-round-state <?php echo esc_attr( $state_class ); ?>"><?php echo esc_html( $state_label ); ?></span>
                </div>
                <h3 class="wpcj-round-card__name"><?php echo esc_html( $r['name'] ); ?></h3>
                <div class="wpcj-round-card__progress">
                    <div class="wpcj-progress-bar">
                        <div class="wpcj-progress-bar__fill" style="width: <?php echo esc_attr( $pct ); ?>%"></div>
                    </div>
                    <span class="wpcj-progress-label">
                        <?php printf( esc_html__( '%1$d / %2$d voted', 'wp-contest-jury' ), $voted, $total ); ?>
                    </span>
                </div>
                <a href="<?php echo esc_url( $round_url ); ?>" class="<?php echo esc_attr( $btn_class ); ?>">
                    <?php echo esc_html( $btn_label ); ?>
                </a>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <p class="wpcj-logout-link">
        <a href="<?php echo esc_url( wp_logout_url( $panel_url ) ); ?>">
            <?php esc_html_e( 'Sign out', 'wp-contest-jury' ); ?>
        </a>
    </p>
</div>
