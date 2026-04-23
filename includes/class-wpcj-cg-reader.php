<?php
defined( 'ABSPATH' ) || exit;

/**
 * Read-only adapter for Contest Gallery PRO database tables.
 *
 * IMPORTANT: This class NEVER writes to CG PRO tables.
 * It exists solely to abstract the CG PRO schema quirks so the rest of the
 * jury plugin is decoupled from CG PRO's internal naming conventions.
 *
 * CG PRO table naming convention (note: intentional "1" instead of "l"):
 *   {prefix}contest_gal1ery          — photo entries (GalleryID column distinguishes galleries)
 *   {prefix}contest_gal1ery_entries   — form field values (EAV, pid → contest_gal1ery.id)
 *   {prefix}contest_gal1ery_options   — one row per gallery (id = GalleryID, GalleryName)
 */
class WPCJ_CG_Reader {

    /**
     * CG PRO uses ONE table for all galleries (GalleryID column distinguishes them).
     * Table name: {prefix}contest_gal1ery — note intentional "1" instead of "l".
     * The $i parameter in CG PRO's create-tables.php is the multisite blog prefix
     * (empty string on single-site), NOT the gallery ID.
     */
    private static function images_table(): string {
        global $wpdb;
        return $wpdb->prefix . 'contest_gal1ery';
    }

    private static function form_data_table(): string {
        global $wpdb;
        return $wpdb->prefix . 'contest_gal1ery_entries';
    }

    private static function options_table(): string {
        global $wpdb;
        return $wpdb->prefix . 'contest_gal1ery_options';
    }

    /**
     * Returns all galleries configured in CG PRO.
     * [ [ 'id' => int, 'name' => string ], ... ]
     * Returns empty array if CG PRO is not installed or has no galleries.
     */
    public static function get_cg_galleries(): array {
        global $wpdb;
        $table = self::options_table();

        if ( $wpdb->get_var( "SHOW TABLES LIKE '$table'" ) !== $table ) {
            return array();
        }

        return $wpdb->get_results(
            "SELECT id, GalleryName AS name FROM `$table` ORDER BY id ASC",
            ARRAY_A
        ) ?: array();
    }

    /**
     * Returns all approved entries for a gallery.
     * Includes WpUserId so callers can look up the uploader via get_wp_user_info().
     *
     * @return array[] Each row: id, NamePic, ImgType, GalleryID, MultipleFiles, Category, Timestamp, WpUserId
     */
    public static function get_entries( int $gallery_id, bool $only_active = true ): array {
        global $wpdb;
        $table      = self::images_table();
        $active_sql = $only_active ? 'AND Active = 1' : '';

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, NamePic, ImgType, GalleryID, MultipleFiles, Category, Timestamp, WpUserId
                 FROM `$table`
                 WHERE GalleryID = %d $active_sql
                 ORDER BY id ASC",
                $gallery_id
            ),
            ARRAY_A
        ) ?: array();
    }

    /**
     * Returns a single entry row.
     */
    public static function get_entry( int $gallery_id, int $entry_id ): ?array {
        global $wpdb;
        $table = self::images_table();
        $row   = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, NamePic, ImgType, GalleryID, MultipleFiles, Category, Timestamp, WpUserId
                 FROM `$table`
                 WHERE id = %d AND GalleryID = %d",
                $entry_id, $gallery_id
            ),
            ARRAY_A
        );
        return $row ?: null;
    }

    /**
     * Returns entries by ID list (e.g. shortlist for a round).
     *
     * @param int[] $entry_ids
     */
    public static function get_entries_by_ids( int $gallery_id, array $entry_ids ): array {
        if ( empty( $entry_ids ) ) {
            return array();
        }
        global $wpdb;
        $table        = self::images_table();
        $placeholders = implode( ',', array_fill( 0, count( $entry_ids ), '%d' ) );

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, NamePic, ImgType, GalleryID, MultipleFiles, Category, Timestamp, WpUserId
                 FROM `$table`
                 WHERE id IN ($placeholders)",
                ...$entry_ids
            ),
            ARRAY_A
        ) ?: array();
    }

    /**
     * Returns all form field values for an entry from the PCG EAV table,
     * excluding identity fields (field_name / field_surname — see README).
     *
     * Short_Text = single-line fields (title, etc.)
     * Long_Text  = multi-line fields (synopsis, description, etc.)
     *
     * @return array[] Each row: f_input_id, Short_Text, Long_Text
     */
    public static function get_entry_fields( int $entry_id, array $exclude_ids = array() ): array {
        global $wpdb;
        $table = self::form_data_table();

        if ( ! empty( $exclude_ids ) ) {
            $placeholders = implode( ',', array_fill( 0, count( $exclude_ids ), '%d' ) );
            $rows = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT f_input_id, Short_Text, Long_Text FROM `$table`
                     WHERE pid = %d AND f_input_id NOT IN ($placeholders)
                     ORDER BY f_input_id ASC",
                    $entry_id,
                    ...$exclude_ids
                ),
                ARRAY_A
            );
        } else {
            $rows = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT f_input_id, Short_Text, Long_Text FROM `$table`
                     WHERE pid = %d ORDER BY f_input_id ASC",
                    $entry_id
                ),
                ARRAY_A
            );
        }

        return $rows ?: array();
    }

    /**
     * Returns f_input_ids of PCG form fields marked as author identity
     * (Field_Content.titel = "field_name" or "field_surname").
     *
     * Convention documented in README: if the PCG upload form includes
     * name/surname fields, their titel must be exactly "field_name" /
     * "field_surname" so the jury plugin can hide them from jurors.
     * Alternatively, omit those fields entirely (recommended).
     *
     * Returns empty array if wp_contest_gal1ery_f_input is absent or empty.
     */
    public static function get_identity_field_ids( int $gallery_id ): array {
        global $wpdb;
        $table = $wpdb->prefix . 'contest_gal1ery_f_input';

        if ( $wpdb->get_var( "SHOW TABLES LIKE '$table'" ) !== $table ) {
            return array();
        }

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, Field_Content FROM `$table` WHERE GalleryID = %d",
                $gallery_id
            ),
            ARRAY_A
        ) ?: array();

        $ids = array();
        foreach ( $rows as $row ) {
            $content = json_decode( $row['Field_Content'], true );
            if ( is_array( $content ) && isset( $content['titel'] ) &&
                 in_array( $content['titel'], array( 'field_name', 'field_surname' ), true ) ) {
                $ids[] = (int) $row['id'];
            }
        }
        return $ids;
    }

    /**
     * Returns the WordPress user data for the uploader of an entry.
     * Author identity comes from the WP user account (stable across festivals),
     * not from PCG form fields (which change per festival).
     *
     * Returns null if WpUserId is 0 or the user no longer exists.
     */
    public static function get_wp_user_info( int $wp_user_id ): ?array {
        if ( $wp_user_id <= 0 ) {
            return null;
        }
        $user = get_userdata( $wp_user_id );
        if ( ! $user ) {
            return null;
        }
        return array(
            'first_name'   => (string) get_user_meta( $wp_user_id, 'first_name', true ),
            'last_name'    => (string) get_user_meta( $wp_user_id, 'last_name', true ),
            'display_name' => $user->display_name,
            'email'        => $user->user_email,
            'login'        => $user->user_login,
        );
    }

    /**
     * Returns a map of f_input_id → human-readable label for all fields of a gallery.
     * Labels come from wp_contest_gal1ery_f_input.Field_Content (JSON key "titel").
     * Returns empty array if the table is absent (e.g. dev env without PCG installed).
     *
     * @return array<int, string>  [ f_input_id => label ]
     */
    public static function get_field_labels( int $gallery_id ): array {
        global $wpdb;
        $table = $wpdb->prefix . 'contest_gal1ery_f_input';

        if ( $wpdb->get_var( "SHOW TABLES LIKE '$table'" ) !== $table ) {
            return array();
        }

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, Field_Content FROM `$table` WHERE GalleryID = %d",
                $gallery_id
            ),
            ARRAY_A
        ) ?: array();

        $labels = array();
        foreach ( $rows as $row ) {
            $content = json_decode( $row['Field_Content'], true );
            if ( is_array( $content ) && isset( $content['titel'] ) && $content['titel'] !== '' ) {
                $labels[ (int) $row['id'] ] = (string) $content['titel'];
            }
        }
        return $labels;
    }

    /**
     * Returns the URL to the full-size image.
     * CG PRO path: wp-content/uploads/contest-gallery/gallery-id-{id}/{Timestamp}_{NamePic}-1024width.{ImgType}
     */
    public static function get_image_url( int $gallery_id, int $timestamp, string $name_pic, string $img_type ): string {
        return content_url( 'uploads/contest-gallery/gallery-id-' . $gallery_id . '/' . $timestamp . '_' . $name_pic . '-1024width.' . $img_type );
    }

    /**
     * Returns the URL to the 300-pixel-wide thumbnail.
     * CG PRO path: wp-content/uploads/contest-gallery/gallery-id-{id}/{Timestamp}_{NamePic}-300width.{ImgType}
     */
    public static function get_thumbnail_url( int $gallery_id, int $timestamp, string $name_pic, string $img_type ): string {
        return content_url( 'uploads/contest-gallery/gallery-id-' . $gallery_id . '/' . $timestamp . '_' . $name_pic . '-300width.' . $img_type );
    }
}
