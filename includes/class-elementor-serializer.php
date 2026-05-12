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

        // Clear the `_elementor_css` post meta so Elementor does not treat a
        // stale CSS entry as still valid after the data has been restored.
        delete_post_meta( $post_id, '_elementor_css' );

        // Immediately regenerate the post CSS file from the restored _elementor_data.
        // Using update() rather than delete() ensures the fresh CSS file exists before
        // the next page view, preventing the broken-layout window that occurs when the
        // file is absent and Elementor fails to recreate it on-the-fly.
        if ( class_exists( '\Elementor\Core\Files\CSS\Post' ) ) {
            \Elementor\Core\Files\CSS\Post::create( $post_id )->update();
        } elseif ( class_exists( '\Elementor\Plugin' ) && isset( \Elementor\Plugin::$instance ) ) {
            // Elementor 2.x legacy fallback.
            $elementor = \Elementor\Plugin::$instance;
            if ( isset( $elementor->db ) && method_exists( $elementor->db, 'clear_cache' ) ) {
                $elementor->db->clear_cache( $post_id );
            }
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
