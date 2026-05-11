<?php
namespace WPSE;

defined( 'ABSPATH' ) || exit;

class Snapshot_Repository {

    private string $table;

    public function __construct() {
        global $wpdb;
        $this->table = $wpdb->prefix . 'snapshots';
    }

    public function insert( array $data ) {
        global $wpdb;
        $result = $wpdb->insert(
            $this->table,
            [
                'entity_type'   => $data['entity_type'],
                'entity_id'     => $data['entity_id'] ?? null,
                'snapshot_type' => $data['snapshot_type'],
                'hash'          => $data['hash'],
                'payload'       => $data['payload'],
                'created_at'    => current_time( 'mysql' ),
                'group_id'      => $data['group_id'] ?? '',
            ],
            [ '%s', '%d', '%s', '%s', '%s', '%s', '%s' ]
        );
        return $result ? (int) $wpdb->insert_id : false;
    }

    public function delete( int $id ): bool {
        global $wpdb;
        return (bool) $wpdb->delete( $this->table, [ 'id' => $id ], [ '%d' ] );
    }

    public function enforce_limit( string $entity_type, ?int $entity_id, int $limit ): void {
        global $wpdb;
        $eid_clause = is_null( $entity_id )
            ? 'entity_id IS NULL'
            : $wpdb->prepare( 'entity_id = %d', $entity_id );

        $ids = $wpdb->get_col( $wpdb->prepare(
            "SELECT id FROM {$this->table}
             WHERE entity_type = %s AND {$eid_clause}
             ORDER BY created_at DESC
             LIMIT 99999 OFFSET %d",
            $entity_type, $limit
        ) );

        if ( ! empty( $ids ) ) {
            $ph = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
            $wpdb->query( $wpdb->prepare( "DELETE FROM {$this->table} WHERE id IN ({$ph})", $ids ) );
        }
    }

    public function find( int $id ): ?object {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->table} WHERE id = %d", $id ) );
    }

    public function query( array $args = [] ): array {
        global $wpdb;

        $per_page = max( 1, (int) ( $args['per_page'] ?? 20 ) );
        $page     = max( 1, (int) ( $args['page'] ?? 1 ) );
        $offset   = ( $page - 1 ) * $per_page;

        $where = []; $params = [];

        if ( ! empty( $args['snapshot_type'] ) ) { $where[] = 'snapshot_type = %s'; $params[] = $args['snapshot_type']; }
        if ( ! empty( $args['entity_type'] )   ) { $where[] = 'entity_type = %s';   $params[] = $args['entity_type'];   }
        if ( isset( $args['entity_id'] )        ) { $where[] = 'entity_id = %d';     $params[] = (int) $args['entity_id']; }
        if ( ! empty( $args['date_from'] )      ) { $where[] = 'created_at >= %s';   $params[] = $args['date_from'];     }
        if ( ! empty( $args['date_to'] )        ) { $where[] = 'created_at <= %s';   $params[] = $args['date_to'];       }

        $wsql = $where ? 'WHERE ' . implode( ' AND ', $where ) : '';

        $count_q = "SELECT COUNT(*) FROM {$this->table} {$wsql}";
        $total   = (int) ( $params ? $wpdb->get_var( $wpdb->prepare( $count_q, $params ) ) : $wpdb->get_var( $count_q ) );

        $items_params = array_merge( $params, [ $per_page, $offset ] );
        $items_q = "SELECT id, entity_type, entity_id, snapshot_type, hash, created_at, group_id,
                           LEFT(payload,300) AS payload_preview
                    FROM {$this->table} {$wsql}
                    ORDER BY created_at DESC
                    LIMIT %d OFFSET %d";

        $items = $wpdb->get_results( $wpdb->prepare( $items_q, $items_params ) );

        return [ 'items' => $items ?: [], 'total' => $total ];
    }

    public function last_hash( string $entity_type, ?int $entity_id ): ?string {
        global $wpdb;
        $eid_clause = is_null( $entity_id )
            ? 'entity_id IS NULL'
            : $wpdb->prepare( 'entity_id = %d', $entity_id );
        return $wpdb->get_var( $wpdb->prepare(
            "SELECT hash FROM {$this->table}
             WHERE entity_type = %s AND {$eid_clause}
             ORDER BY created_at DESC LIMIT 1",
            $entity_type
        ) );
    }
}
