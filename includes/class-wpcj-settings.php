<?php
defined( 'ABSPATH' ) || exit;

/**
 * Manages all plugin configuration stored in WordPress options.
 * Stored as a single serialized array under 'wpcj_settings'.
 */
class WPCJ_Settings {

    const OPTION_KEY = 'wpcj_settings';

    private static function defaults(): array {
        return array(
            'galleries'         => array(),
            'show_author_name'  => false,
            'jury_page_id'      => 0,
            'welcome_message'   => '',
            'require_all_votes' => false,
        );
    }

    public static function get_all(): array {
        $saved = get_option( self::OPTION_KEY, array() );
        return wp_parse_args( $saved, self::defaults() );
    }

    public static function show_author_name(): bool {
        return (bool) self::get_all()['show_author_name'];
    }

    public static function get_jury_page_id(): int {
        return (int) self::get_all()['jury_page_id'];
    }

    public static function get_welcome_message(): string {
        return (string) self::get_all()['welcome_message'];
    }

    public static function require_all_votes(): bool {
        return (bool) self::get_all()['require_all_votes'];
    }

    /**
     * Returns configured galleries as an associative array keyed by gallery ID.
     * [ gallery_id => label ]
     */
    public static function get_galleries(): array {
        $settings  = self::get_all();
        $galleries = array();
        foreach ( $settings['galleries'] as $g ) {
            $id = (int) $g['id'];
            if ( $id > 0 ) {
                $galleries[ $id ] = sanitize_text_field( $g['label'] );
            }
        }
        return $galleries;
    }

    public static function get_gallery_label( int $gallery_id ): string {
        $galleries = self::get_galleries();
        return $galleries[ $gallery_id ] ?? sprintf( __( 'Gallery %d', 'wp-contest-jury' ), $gallery_id );
    }

    /**
     * Returns the configured round types for a gallery.
     * Falls back to the four default types if none are configured.
     */
    public static function get_gallery_round_types( int $gallery_id ): array {
        $settings = self::get_all();
        foreach ( $settings['galleries'] as $g ) {
            if ( (int) $g['id'] === $gallery_id && ! empty( $g['round_types'] ) ) {
                return array_values( array_filter( array_map( 'strval', $g['round_types'] ) ) );
            }
        }
        return array( 'initial', 'shortlist', 'final', 'winner' );
    }

    public static function save( array $data ): void {
        $clean = self::defaults();

        if ( ! empty( $data['galleries'] ) && is_array( $data['galleries'] ) ) {
            foreach ( $data['galleries'] as $g ) {
                $id    = absint( $g['id'] ?? 0 );
                $label = sanitize_text_field( $g['label'] ?? '' );
                if ( $id > 0 && $label !== '' ) {
                    $types = array();
                    if ( ! empty( $g['round_types'] ) && is_array( $g['round_types'] ) ) {
                        foreach ( $g['round_types'] as $t ) {
                            $t = substr( trim( sanitize_text_field( $t ) ), 0, 30 );
                            if ( $t !== '' ) {
                                $types[] = $t;
                            }
                        }
                    }
                    $clean['galleries'][] = array( 'id' => $id, 'label' => $label, 'round_types' => $types );
                }
            }
        }

        $clean['show_author_name']  = ! empty( $data['show_author_name'] );
        $clean['jury_page_id']      = absint( $data['jury_page_id'] ?? 0 );
        $clean['welcome_message']   = sanitize_textarea_field( $data['welcome_message'] ?? '' );
        $clean['require_all_votes'] = ! empty( $data['require_all_votes'] );

        update_option( self::OPTION_KEY, $clean );
    }
}
