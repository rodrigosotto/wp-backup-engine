<?php
namespace WPSE;

defined( 'ABSPATH' ) || exit;

/**
 * Handles serialisation and deserialisation of Elementor post meta.
 */
class Elementor_Serializer {

    /** Meta keys managed by Elementor. */
    const ELEMENTOR_KEYS = [
        '_elementor_data',
        '_elementor_page_settings',
        '_elementor_edit_mode',
        '_elementor_template_type',
        '_elementor_version',
        '_elementor_pro_version',
    ];

    /**
     * Capture all Elementor meta for a given post ID.
     *
     * @param int $post_id
     * @return array<string,mixed>
     */
    public function capture( int $post_id ): array {
        $data = [];
        foreach ( self::ELEMENTOR_KEYS as $key ) {
            $raw = get_post_meta( $post_id, $key, true );
            if ( $raw !== false && $raw !== '' ) {
                $data[ $key ] = $raw;
            }
        }
        return $data;
    }

    /**
     * Restore Elementor meta from a captured snapshot array.
     *
     * @param int          $post_id
     * @param array<string,mixed> $data
     */
    public function restore( int $post_id, array $data ): void {
        foreach ( self::ELEMENTOR_KEYS as $key ) {
            if ( array_key_exists( $key, $data ) ) {
                update_post_meta( $post_id, $key, $data[ $key ] );
            } else {
                delete_post_meta( $post_id, $key );
            }
        }

        // Invalidate Elementor's cached CSS for this post so it is regenerated on the
        // next frontend page view — where Elementor is fully initialised (all widgets
        // registered) and can produce correct CSS from the restored _elementor_data.
        //
        // We intentionally do NOT call Post::create()->update() here: that API runs
        // inside the REST API request context, where Elementor does not register its
        // widget types, causing it to emit empty / broken CSS that then gets persisted
        // and served on subsequent views without a further regeneration attempt.
        //
        // Two storage locations must be cleared to cover both Elementor CSS modes:
        //   1. `_elementor_css` post meta  — used by inline / atomic CSS mode
        //   2. post-{ID}.css file          — used by file-based CSS mode
        delete_post_meta( $post_id, '_elementor_css' );

        $upload     = wp_get_upload_dir();
        $css_path   = $upload['basedir'] . '/elementor/css/post-' . $post_id . '.css';
        if ( file_exists( $css_path ) ) {
            // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
            @unlink( $css_path );
        }
    }

    /**
     * Decode _elementor_data from JSON string to array if necessary.
     *
     * @param mixed $raw
     * @return array
     */
    public function decode_elementor_data( $raw ): array {
        if ( is_string( $raw ) ) {
            $decoded = json_decode( $raw, true );
            return is_array( $decoded ) ? $decoded : [];
        }
        return is_array( $raw ) ? $raw : [];
    }
}
