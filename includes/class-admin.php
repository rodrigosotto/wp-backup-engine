<?php
namespace WPSE;

defined( 'ABSPATH' ) || exit;

class Admin {

    public function __construct( private Snapshot_Repository $repository ) {}

    public function register(): void {
        add_action( 'admin_menu',            [ $this, 'add_menu_page' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
    }

    public function add_menu_page(): void {
        add_menu_page(
            __( 'WP Snapshot Engine', 'wp-snapshot-engine' ),
            __( 'Snapshots', 'wp-snapshot-engine' ),
            'manage_options',
            'wp-snapshot-engine',
            [ $this, 'render_page' ],
            'dashicons-backup',
            80
        );
    }

    public function enqueue_assets( string $hook ): void {
        if ( $hook !== 'toplevel_page_wp-snapshot-engine' ) {
            return;
        }
        wp_enqueue_style(
            'wpse-admin',
            WPSE_PLUGIN_URL . 'assets/css/admin.css',
            [],
            WPSE_VERSION
        );
        wp_enqueue_script(
            'wpse-admin',
            WPSE_PLUGIN_URL . 'assets/js/admin.js',
            [],
            WPSE_VERSION,
            true
        );
        wp_localize_script( 'wpse-admin', 'WPSE', [
            'apiBase' => rest_url( 'wp-snapshot-engine/v1' ),
            'nonce'   => wp_create_nonce( 'wp_rest' ),
            'i18n'    => [
                'confirmRestore' => __( 'Restore this snapshot? Current content will be replaced.', 'wp-snapshot-engine' ),
                'confirmDelete'  => __( 'Delete this snapshot permanently?', 'wp-snapshot-engine' ),
                'restoring'      => __( 'Restoring...', 'wp-snapshot-engine' ),
                'restored'       => __( 'Restored successfully!', 'wp-snapshot-engine' ),
                'deleted'        => __( 'Snapshot deleted.', 'wp-snapshot-engine' ),
                'error'          => __( 'An error occurred. Please try again.', 'wp-snapshot-engine' ),
            ],
        ] );
    }

    public function render_page(): void {
        require WPSE_PLUGIN_DIR . 'views/admin-page.php';
    }
}
