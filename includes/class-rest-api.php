<?php
namespace WPSE;

defined( 'ABSPATH' ) || exit;

class Rest_API {

    private const NAMESPACE = 'wp-snapshot-engine/v1';

    public function __construct(
        private Snapshot_Repository $repository,
        private Restore_Service $restore
    ) {}

    public function register_routes(): void {
        register_rest_route( self::NAMESPACE, '/snapshots', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [ $this, 'list_snapshots' ],
            'permission_callback' => [ $this, 'admin_permission' ],
            'args'                => [
                'snapshot_type' => [ 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ],
                'entity_type'   => [ 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ],
                'entity_id'     => [ 'type' => 'integer' ],
                'date_from'     => [ 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ],
                'date_to'       => [ 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ],
                'per_page'      => [ 'type' => 'integer', 'default' => 20, 'minimum' => 1, 'maximum' => 100 ],
                'page'          => [ 'type' => 'integer', 'default' => 1,  'minimum' => 1 ],
            ],
        ] );

        register_rest_route( self::NAMESPACE, '/snapshots/(?P<id>\d+)', [
            [
                'methods'             => \WP_REST_Server::READABLE,
                'callback'            => [ $this, 'get_snapshot' ],
                'permission_callback' => [ $this, 'admin_permission' ],
            ],
            [
                'methods'             => \WP_REST_Server::DELETABLE,
                'callback'            => [ $this, 'delete_snapshot' ],
                'permission_callback' => [ $this, 'admin_permission' ],
            ],
        ] );

        register_rest_route( self::NAMESPACE, '/restore/(?P<id>\d+)', [
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => [ $this, 'restore_snapshot' ],
            'permission_callback' => [ $this, 'admin_permission' ],
        ] );

        register_rest_route( self::NAMESPACE, '/restore/(?P<id>\d+)/elementor', [
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => [ $this, 'restore_elementor_only' ],
            'permission_callback' => [ $this, 'admin_permission' ],
        ] );
    }

    public function list_snapshots( \WP_REST_Request $request ): \WP_REST_Response {
        $result = $this->repository->query( [
            'snapshot_type' => $request->get_param( 'snapshot_type' ),
            'entity_type'   => $request->get_param( 'entity_type' ),
            'entity_id'     => $request->get_param( 'entity_id' ),
            'date_from'     => $request->get_param( 'date_from' ),
            'date_to'       => $request->get_param( 'date_to' ),
            'per_page'      => $request->get_param( 'per_page' ),
            'page'          => $request->get_param( 'page' ),
        ] );

        return new \WP_REST_Response( [
            'success' => true,
            'data'    => array_map( [ $this, 'format_item' ], $result['items'] ),
            'meta'    => [
                'total'    => $result['total'],
                'per_page' => (int) $request->get_param( 'per_page' ),
                'page'     => (int) $request->get_param( 'page' ),
            ],
        ] );
    }

    public function get_snapshot( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
        $snapshot = $this->repository->find( (int) $request->get_param( 'id' ) );
        if ( ! $snapshot ) {
            return new \WP_Error( 'not_found', 'Snapshot not found.', [ 'status' => 404 ] );
        }
        $data            = (array) $snapshot;
        $data['payload'] = json_decode( $snapshot->payload, true );
        return new \WP_REST_Response( [ 'success' => true, 'data' => $data ] );
    }

    public function delete_snapshot( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
        $id = (int) $request->get_param( 'id' );
        if ( ! $this->repository->find( $id ) ) {
            return new \WP_Error( 'not_found', 'Snapshot not found.', [ 'status' => 404 ] );
        }
        $this->repository->delete( $id );
        return new \WP_REST_Response( [ 'success' => true, 'message' => 'Snapshot deleted.' ] );
    }

    public function restore_snapshot( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
        $id = (int) $request->get_param( 'id' );
        if ( ! $this->restore->restore_full( $id ) ) {
            return new \WP_Error( 'restore_failed', 'Snapshot not found or restore failed.', [ 'status' => 400 ] );
        }
        return new \WP_REST_Response( [ 'success' => true, 'message' => 'Snapshot restored successfully.' ] );
    }

    public function restore_elementor_only( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
        $id = (int) $request->get_param( 'id' );
        if ( ! $this->restore->restore_elementor_only( $id ) ) {
            return new \WP_Error( 'restore_failed', 'Snapshot not found or has no Elementor data.', [ 'status' => 400 ] );
        }
        return new \WP_REST_Response( [ 'success' => true, 'message' => 'Elementor data restored successfully.' ] );
    }

    public function admin_permission(): bool {
        return current_user_can( 'manage_options' );
    }

    private function format_item( object $item ): array {
        return [
            'id'              => (int) $item->id,
            'entity_type'     => $item->entity_type,
            'entity_id'       => is_null( $item->entity_id ) ? null : (int) $item->entity_id,
            'snapshot_type'   => $item->snapshot_type,
            'hash'            => $item->hash,
            'created_at'      => $item->created_at,
            'group_id'        => $item->group_id,
            'payload_preview' => $item->payload_preview ?? '',
        ];
    }
}
