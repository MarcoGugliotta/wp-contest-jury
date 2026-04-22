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
 *   {prefix}{galleryID}contest_gal1ery          — photo entries
 *   {prefix}{galleryID}contest_gal1ery_entries   — form field values (author data)
 *
 * For our two galleries:
 *   Gallery 1 → "Serie Fotografica"  (multiple images per entry)
 *   Gallery 2 → "Foto Singola"
 */
class WPCJ_CG_Reader {

    /**
     * Returns the entries table name for a given gallery ID.
     * Active = 1 means the entry has been approved by the admin.
     */
    private static function images_table( int $gallery_id ): string {
        global $wpdb;
        return $wpdb->base_prefix . $gallery_id . 'contest_gal1ery';
    }

    private static function form_data_table( int $gallery_id ): string {
        global $wpdb;
        return $wpdb->base_prefix . $gallery_id . 'contest_gal1ery_entries';
    }

    /**
     * Returns all approved entries for a gallery, WITHOUT author information.
     * Anonymous voting: jurors see only the image data, never the uploader's identity.
     *
     * @return array[] Each row: id, NamePic, GalleryID, MultipleFiles, Category
     */
    public static function get_entries( int $gallery_id, bool $only_active = true ): array {
        global $wpdb;
        $table      = self::images_table( $gallery_id );
        $active_sql = $only_active ? 'AND Active = 1' : '';

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, NamePic, GalleryID, MultipleFiles, Category, Timestamp
                 FROM `$table`
                 WHERE GalleryID = %d $active_sql
                 ORDER BY id ASC",
                $gallery_id
            ),
            ARRAY_A
        ) ?: array();
    }

    /**
     * Returns a single entry row (still author-anonymous).
     */
    public static function get_entry( int $gallery_id, int $entry_id ): ?array {
        global $wpdb;
        $table = self::images_table( $gallery_id );
        $row   = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, NamePic, GalleryID, MultipleFiles, Category, Timestamp
                 FROM `$table`
                 WHERE id = %d AND GalleryID = %d",
                $entry_id, $gallery_id
            ),
            ARRAY_A
        );
        return $row ?: null;
    }

    /**
     * Returns only the entries that are on the shortlist for a given round.
     * Used by jury chief to view/manage shortlisted works.
     *
     * @param int[] $entry_ids
     */
    public static function get_entries_by_ids( int $gallery_id, array $entry_ids ): array {
        if ( empty( $entry_ids ) ) {
            return array();
        }
        global $wpdb;
        $table       = self::images_table( $gallery_id );
        $placeholders = implode( ',', array_fill( 0, count( $entry_ids ), '%d' ) );

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, NamePic, GalleryID, MultipleFiles, Category
                 FROM `$table`
                 WHERE id IN ($placeholders)",
                ...$entry_ids
            ),
            ARRAY_A
        ) ?: array();
    }

    /**
     * Returns author data for a single entry — ONLY for jury chief / admin use.
     * Never expose this to jury_member role.
     *
     * Form fields are stored as rows (EAV pattern), each row has:
     *   pid           → references the images table id
     *   f_input_id    → which form field
     *   Short_Text    → the submitted text value
     */
    public static function get_author_data( int $gallery_id, int $entry_id ): array {
        global $wpdb;
        $table = self::form_data_table( $gallery_id );

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT f_input_id, Short_Text, Long_Text
                 FROM `$table`
                 WHERE pid = %d",
                $entry_id
            ),
            ARRAY_A
        ) ?: array();
    }

    /**
     * Returns the URL path to an uploaded image.
     * CG PRO stores files in wp-content/uploads/{galleryID}/ by default.
     */
    public static function get_image_url( int $gallery_id, string $name_pic ): string {
        return content_url( 'uploads/' . $gallery_id . '/' . $name_pic );
    }

    public static function get_thumbnail_url( int $gallery_id, string $name_pic ): string {
        $ext      = pathinfo( $name_pic, PATHINFO_EXTENSION );
        $basename = pathinfo( $name_pic, PATHINFO_FILENAME );
        return content_url( 'uploads/' . $gallery_id . '/thumb_' . $basename . '.' . $ext );
    }
}
