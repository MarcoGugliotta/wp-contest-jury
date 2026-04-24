<?php defined( 'ABSPATH' ) || exit; ?>
<div class="wpcj-panel wpcj-login-wrap">
    <div class="wpcj-login-card">
        <h2 class="wpcj-login-title"><?php esc_html_e( 'Jury Panel', 'wp-contest-jury' ); ?></h2>
        <p class="wpcj-login-subtitle"><?php esc_html_e( 'Sign in with your jury account to continue.', 'wp-contest-jury' ); ?></p>
        <?php
        wp_login_form( array(
            'redirect'       => get_permalink( WPCJ_Settings::get_jury_page_id() ) ?: get_permalink(),
            'label_username' => __( 'Username', 'wp-contest-jury' ),
            'label_password' => __( 'Password', 'wp-contest-jury' ),
            'label_log_in'   => __( 'Sign In', 'wp-contest-jury' ),
            'remember'       => true,
            'label_remember' => __( 'Keep me signed in', 'wp-contest-jury' ),
        ) );
        ?>
    </div>
</div>
