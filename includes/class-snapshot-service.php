<?php
namespace WPSE;

defined( 'ABSPATH' ) || exit;

/**
 * Orchestrates snapshot creation with deduplication and limit enforcement.
 */
class Snapshot_Service {

    private const MAX_PER_ENTITY = 20;

    public function __construct(
        private Snapshot_Repository $repository,
        private Hasher $hasher,
        private Elementor_Serializer $serializer
    ) {}

    /**
     * Create a snapshot for a WordPress post (content + meta + Elementor data).
     */
    public function snapshot_post( int $post_id, string $group_id = '' ): ?int {
        $post = get_post( $post_id );
        if ( ! $post || wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
            return null;
        }

        $elementor = $this->serializer->capture( $post_id );
        $has_elementor = ! empty( $elementor['_elementor_data'] );

        $payload = [
            'post'      => [
                'ID'           => $post->ID,
                'post_title'   => $post->post_title,
                'post_content' => $post->post_content,
                'post_status'  => $post->post_status,
                'post_type'    => $post->post_type,
            ],
            'elementor' => $elementor,
        ];

        $snapshot_type = $has_elementor ? 'elementor' : 'post';

        return $this->save(
            entity_type:   'post',
            entity_id:     $post_id,
            snapshot_type: $snapshot_type,
            payload:       $payload,
            group_id:      $group_id ?: $this->generate_group_id(),
            label:         $post->post_title,
        );
    }

    /**
     * Create a snapshot for a WordPress option.
     */
    public function snapshot_option( string $option_name, $new_value, string $group_id = '' ): ?int {
        $payload = [
            'option_name'  => $option_name,
            'option_value' => $new_value,
        ];

        return $this->save(
            entity_type:   'option',
            entity_id:     null,
            snapshot_type: 'option',
            payload:       $payload,
            group_id:      $group_id ?: $this->generate_group_id(),
            label:         $option_name,
        );
    }

    // ------------------------------------------------------------------
    // Internal
    // ------------------------------------------------------------------

    private function save(
        string $entity_type,
        ?int   $entity_id,
        string $snapshot_type,
        array  $payload,
        string $group_id,
        string $label
    ): ?int {
        $encoded = wp_json_encode( $payload );
        $hash    = $this->hasher->hash( $encoded );

        // Deduplication: skip if content unchanged.
        $last_hash = $this->repository->last_hash( $entity_type, $entity_id );
        if ( $last_hash === $hash ) {
            return null;
        }

        $id = $this->repository->insert( [
            'entity_type'   => $entity_type,
            'entity_id'     => $entity_id,
            'snapshot_type' => $snapshot_type,
            'hash'          => $hash,
            'payload'       => $encoded,
            'group_id'      => $group_id,
        ] );

        if ( $id ) {
            $this->repository->enforce_limit( $entity_type, $entity_id, self::MAX_PER_ENTITY );
        }

        return $id ?: null;
    }

    private function generate_group_id(): string {
        return uniqid( 'grp_', true );
    }
}
