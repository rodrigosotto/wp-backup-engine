<?php
namespace WPSE;

defined( 'ABSPATH' ) || exit;

/**
 * Registers WordPress hooks and delegates to SnapshotService.
 */
class Snapshot_Manager {

    /** Options that are too noisy to snapshot. */
    private const IGNORED_OPTIONS = [
        'cron', '_transient_', '_site_transient_', 'active_plugins',
        'recently_edited', 'auto_updated_core_time',
    ];

    public function __construct( private Snapshot_Service $service ) {}

    public function register_hooks(): void {
        // Elementor save (fires after Elementor writes its data).
        add_action( 'elementor/editor/after_save', [ $this, 'on_elementor_save' ], 10, 2 );

        // Classic post/page save.
        add_action( 'save_post', [ $this, 'on_save_post' ], 20, 3 );

        // Options.
        add_action( 'updated_option', [ $this, 'on_updated_option' ], 10, 3 );
    }

    // ------------------------------------------------------------------
    // Handlers
    // ------------------------------------------------------------------

    /**
     * @param int   $post_id
     * @param array $editor_data (Elementor passes this, but we re-capture from DB for consistency)
     */
    public function on_elementor_save( int $post_id, array $editor_data ): void {
        $group_id = 'elementor_' . $post_id . '_' . time();
        $this->service->snapshot_post( $post_id, $group_id );
    }

    /**
     * @param int      $post_id
     * @param \WP_Post $post
     * @param bool     $update
     */
    public function on_save_post( int $post_id, \WP_Post $post, bool $update ): void {
        // Skip auto-saves, revisions, and non-public post types.
        if ( ! $update ) return;
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
        if ( wp_is_post_revision( $post_id ) ) return;
        if ( in_array( $post->post_type, [ 'revision', 'nav_menu_item', 'customize_changeset' ], true ) ) return;
        if ( ! current_user_can( 'edit_post', $post_id ) ) return;

        // If Elementor fired already for this request, skip duplicate.
        if ( did_action( 'elementor/editor/after_save' ) ) return;

        $group_id = 'post_' . $post_id . '_' . time();
        $this->service->snapshot_post( $post_id, $group_id );
    }

    /**
     * @param string $option
     * @param mixed  $old_value
     * @param mixed  $value
     */
    public function on_updated_option( string $option, $old_value, $value ): void {
        // Ignore high-frequency or internal options.
        foreach ( self::IGNORED_OPTIONS as $pattern ) {
            if ( strpos( $option, $pattern ) !== false ) return;
        }

        // Ignore if value hasn't actually changed.
        if ( $old_value === $value ) return;

        $group_id = 'option_' . md5( $option ) . '_' . time();
        $this->service->snapshot_option( $option, $value, $group_id );
    }
}
