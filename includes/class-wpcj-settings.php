<?php
defined( 'ABSPATH' ) || exit;

/**
 * Manages all plugin configuration stored in WordPress options.
 *
 * The gallery list is the core configurable item: each installation defines
 * which Contest Gallery PRO gallery IDs to use and what to call them.
 *
 * Stored in wp_options as a single serialized array under 'wpcj_settings'.
 * Think of this as a typed config bean — get_option/update_option are the
 * WP equivalent of reading from application.properties.
 *
 * Default option shape:
 * [
 *   'galleries' => [
 *     [ 'id' => 1, 'label' => 'Gallery 1' ],
 *     ...
 *   ],
 * ]
 */
class WPCJ_Settings {

    const OPTION_KEY = 'wpcj_settings';

    private static function defaults(): array {
        return array(
            'galleries' => array(),
        );
    }

    public static function get_all(): array {
        $saved = get_option( self::OPTION_KEY, array() );
        return wp_parse_args( $saved, self::defaults() );
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

    /**
     * Returns the human-readable label for a gallery ID.
     * Falls back to "Gallery {id}" if not configured.
     */
    public static function get_gallery_label( int $gallery_id ): string {
        $galleries = self::get_galleries();
        return $galleries[ $gallery_id ] ?? sprintf( __( 'Gallery %d', 'wp-contest-jury' ), $gallery_id );
    }

    public static function save( array $data ): void {
        $clean = self::defaults();

        // Galleries: array of {id, label} pairs submitted from the settings form
        if ( ! empty( $data['galleries'] ) && is_array( $data['galleries'] ) ) {
            foreach ( $data['galleries'] as $g ) {
                $id    = absint( $g['id'] ?? 0 );
                $label = sanitize_text_field( $g['label'] ?? '' );
                if ( $id > 0 && $label !== '' ) {
                    $clean['galleries'][] = array( 'id' => $id, 'label' => $label );
                }
            }
        }

        update_option( self::OPTION_KEY, $clean );
    }
}
