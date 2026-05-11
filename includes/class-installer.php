<?php
namespace WPSE;

defined( 'ABSPATH' ) || exit;

class Installer {

    public static function activate(): void {
        global $wpdb;
        $table      = $wpdb->prefix . 'snapshots';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$table} (
            id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            entity_type VARCHAR(20)     NOT NULL DEFAULT '',
            entity_id   BIGINT          NULL,
            snapshot_type VARCHAR(20)   NOT NULL DEFAULT '',
            hash        CHAR(32)        NOT NULL DEFAULT '',
            payload     LONGTEXT        NOT NULL,
            created_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
            group_id    VARCHAR(64)     NOT NULL DEFAULT '',
            PRIMARY KEY (id),
            KEY idx_entity       (entity_type, entity_id),
            KEY idx_snapshot_type (snapshot_type),
            KEY idx_group_id     (group_id),
            KEY idx_created_at   (created_at)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );

        update_option( 'wpse_db_version', WPSE_VERSION );
    }

    public static function deactivate(): void {
        // Intentionally leave data on deactivation.
        // Uninstall hook should handle removal if desired.
    }
}
