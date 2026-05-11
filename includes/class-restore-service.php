<?php
namespace WPSE;

defined( 'ABSPATH' ) || exit;

/**
 * Restores WordPress state from a snapshot.
 */
class Restore_Service {

    public function __construct(
        private Snapshot_Repository $repository,
        private Elementor_Serializer $serializer
    ) {}

    /**
     * Full restore: post content + meta + Elementor data.
     *
     * @param int $snapshot_id
     * @return bool
     */
    public function restore_full( int $snapshot_id ): bool {
        $snapshot = $this->repository->find( $snapshot_id );
        if ( ! $snapshot ) {
            return false;
        }

        $payload = json_decode( $snapshot->payload, true );
        if ( ! is_array( $payload ) ) {
            return false;
        }

        $entity_type = $snapshot->entity_type;

        if ( $entity_type === 'post' || $entity_type === 'elementor' ) {
            return $this->restore_post( $payload );
        }

        if ( $entity_type === 'option' ) {
            return $this->restore_option( $payload );
        }

        return false;
    }

    /**
     * Restore only the Elementor data from a snapshot (leaves post_content untouched).
     */
    public function restore_elementor_only( int $snapshot_id ): bool {
        $snapshot = $this->repository->find( $snapshot_id );
        if ( ! $snapshot ) {
            return false;
        }

        $payload = json_decode( $snapshot->payload, true );
        if ( ! isset( $payload['elementor'], $payload['post']['ID'] ) ) {
            return false;
        }

        $post_id = (int) $payload['post']['ID'];
        $this->serializer->restore( $post_id, $payload['elementor'] );
        return true;
    }

    // ------------------------------------------------------------------
    // Internal
    // ------------------------------------------------------------------

    private function restore_post( array $payload ): bool {
        if ( empty( $payload['post']['ID'] ) ) {
            return false;
        }

        $post_id = (int) $payload['post']['ID'];

        // Update post content fields.
        $result = wp_update_post( [
            'ID'           => $post_id,
            'post_title'   => $payload['post']['post_title']   ?? '',
            'post_content' => $payload['post']['post_content'] ?? '',
        ], true );

        if ( is_wp_error( $result ) ) {
            return false;
        }

        // Restore Elementor meta.
        if ( ! empty( $payload['elementor'] ) ) {
            $this->serializer->restore( $post_id, $payload['elementor'] );
        }

        return true;
    }

    private function restore_option( array $payload ): bool {
        if ( empty( $payload['option_name'] ) ) {
            return false;
        }
        update_option( $payload['option_name'], $payload['option_value'] );
        return true;
    }
}
