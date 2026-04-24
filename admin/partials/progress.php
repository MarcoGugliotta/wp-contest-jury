<?php
defined( 'ABSPATH' ) || exit;

if ( ! current_user_can( 'wpcj_manage_rounds' ) ) {
    wp_die( esc_html__( 'Access denied.', 'wp-contest-jury' ) );
}

$open_rounds = array_values(
    array_filter( WPCJ_DB::get_rounds(), fn( $r ) => $r['status'] === 'open' )
);

$jurors = get_users( array(
    'role__in' => array( 'jury_member', 'jury_chief' ),
    'orderby'  => 'display_name',
    'order'    => 'ASC',
) );

$av_colors = array( '#1a73e8', '#0f9d58', '#f4511e', '#ab47bc', '#00acc1', '#e91e63', '#fb8c00', '#43a047' );
?>
<div class="wrap wpcj-wrap">
    <h1><?php esc_html_e( 'Voting Progress', 'wp-contest-jury' ); ?></h1>

    <?php if ( empty( $jurors ) ) : ?>
        <div class="notice notice-warning inline">
            <p><?php esc_html_e( 'No jury members found. Assign jury roles in Jurors.', 'wp-contest-jury' ); ?></p>
        </div>
    <?php endif; ?>

    <?php if ( empty( $open_rounds ) ) : ?>
        <div class="notice notice-info inline">
            <p>
                <?php esc_html_e( 'No voting rounds are currently open.', 'wp-contest-jury' ); ?>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=wpcj-rounds' ) ); ?>">
                    <?php esc_html_e( 'Go to Rounds →', 'wp-contest-jury' ); ?>
                </a>
            </p>
        </div>
    <?php else : ?>

    <?php foreach ( $open_rounds as $round ) :
        $round_id    = (int) $round['id'];
        $gallery_id  = (int) $round['gallery_id'];
        $entries     = WPCJ_CG_Reader::get_entries( $gallery_id );
        $total       = count( $entries );
        $vote_counts = WPCJ_DB::get_vote_counts_by_round( $round_id );
        $submissions = WPCJ_DB::get_submissions_by_round( $round_id );

        $submitted_count  = count( $submissions );
        $juror_count      = count( $jurors );
        $overall_voted    = array_sum( $vote_counts );
        $overall_possible = $total * $juror_count;
        $overall_pct      = $overall_possible > 0 ? round( $overall_voted / $overall_possible * 100 ) : 0;
    ?>
    <div class="wpcj-prog-card">

        <!-- Round header -->
        <div class="wpcj-prog-card__head">
            <div>
                <div class="wpcj-prog-card__name"><?php echo esc_html( $round['name'] ); ?></div>
                <div class="wpcj-prog-card__sub">
                    <?php echo esc_html( WPCJ_Settings::get_gallery_label( $gallery_id ) ); ?>
                    &middot; <span class="wpcj-round-type"><?php echo esc_html( $round['round_type'] ); ?></span>
                    &middot; <?php echo esc_html( $total ); ?> <?php esc_html_e( 'entries', 'wp-contest-jury' ); ?>
                </div>
            </div>
            <div class="wpcj-prog-card__head-right">
                <span class="wpcj-prog-submitted-pill">
                    <?php echo esc_html( $submitted_count ); ?> / <?php echo esc_html( $juror_count ); ?>
                    <?php esc_html_e( 'submitted', 'wp-contest-jury' ); ?>
                </span>
            </div>
        </div>

        <!-- Overall progress -->
        <div class="wpcj-prog-overall">
            <div class="wpcj-admin-bar">
                <div class="wpcj-admin-bar__fill" style="width:<?php echo esc_attr( $overall_pct ); ?>%"></div>
            </div>
            <span class="wpcj-prog-overall__label">
                <?php
                printf(
                    /* translators: 1: votes cast, 2: total possible, 3: percentage */
                    esc_html__( '%1$d / %2$d votes cast — %3$d%% overall', 'wp-contest-jury' ),
                    $overall_voted,
                    $overall_possible,
                    $overall_pct
                );
                ?>
            </span>
        </div>

        <!-- Juror table -->
        <?php if ( empty( $jurors ) ) : ?>
            <p style="padding:16px 22px; color:#646970; margin:0;">
                <?php esc_html_e( 'No jurors assigned yet.', 'wp-contest-jury' ); ?>
            </p>
        <?php else : ?>
        <table class="widefat wpcj-prog-table">
            <thead>
                <tr>
                    <th><?php esc_html_e( 'Juror', 'wp-contest-jury' ); ?></th>
                    <th><?php esc_html_e( 'Progress', 'wp-contest-jury' ); ?></th>
                    <th><?php esc_html_e( 'Votes', 'wp-contest-jury' ); ?></th>
                    <th><?php esc_html_e( 'Status', 'wp-contest-jury' ); ?></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ( $jurors as $juror ) :
                $jid         = $juror->ID;
                $jvotes      = (int) ( $vote_counts[ $jid ] ?? 0 );
                $is_submitted = isset( $submissions[ $jid ] );
                $jpct        = $total > 0 ? round( $jvotes / $total * 100 ) : 0;

                if ( $is_submitted )   { $st_key = 'submitted'; $st_label = __( 'Submitted', 'wp-contest-jury' ); }
                elseif ( $jvotes > 0 ) { $st_key = 'progress';  $st_label = __( 'In Progress', 'wp-contest-jury' ); }
                else                   { $st_key = 'new';        $st_label = __( 'Not Started', 'wp-contest-jury' ); }

                $name_parts = explode( ' ', trim( $juror->display_name ) );
                $initials   = strtoupper(
                    substr( $name_parts[0], 0, 1 ) .
                    ( count( $name_parts ) > 1 ? substr( end( $name_parts ), 0, 1 ) : '' )
                );
                $av_color = $av_colors[ $jid % count( $av_colors ) ];
            ?>
                <tr>
                    <td>
                        <div class="wpcj-juror-cell">
                            <span class="wpcj-juror-av" style="background:<?php echo esc_attr( $av_color ); ?>">
                                <?php echo esc_html( $initials ); ?>
                            </span>
                            <?php echo esc_html( $juror->display_name ); ?>
                        </div>
                    </td>
                    <td class="wpcj-juror-bar-cell">
                        <div class="wpcj-admin-bar wpcj-admin-bar--sm">
                            <div class="wpcj-admin-bar__fill <?php echo $is_submitted ? 'wpcj-admin-bar__fill--done' : ''; ?>"
                                 style="width:<?php echo esc_attr( $jpct ); ?>%"></div>
                        </div>
                    </td>
                    <td class="wpcj-juror-count">
                        <strong><?php echo esc_html( $jvotes ); ?></strong>
                        <span style="color:#646970">/ <?php echo esc_html( $total ); ?></span>
                    </td>
                    <td>
                        <span class="wpcj-prog-badge wpcj-prog-badge--<?php echo esc_attr( $st_key ); ?>">
                            <?php echo esc_html( $st_label ); ?>
                        </span>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>

    </div>
    <?php endforeach; ?>
    <?php endif; ?>
</div>
