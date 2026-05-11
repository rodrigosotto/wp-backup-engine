<?php
/**
 * Plugin Name: WP Snapshot Engine
 * Plugin URI:  https://github.com/your-org/wp-snapshot-engine
 * Description: Professional version control for WordPress and Elementor — automatic incremental snapshots with a modern visual timeline.
 * Version:     1.0.0
 * Author:      Your Name
 * License:     GPL-2.0+
 * Text Domain: wp-snapshot-engine
 */

defined( 'ABSPATH' ) || exit;

define( 'WPSE_VERSION',     '1.0.0' );
define( 'WPSE_PLUGIN_FILE', __FILE__ );
define( 'WPSE_PLUGIN_DIR',  plugin_dir_path( __FILE__ ) );
define( 'WPSE_PLUGIN_URL',  plugin_dir_url( __FILE__ ) );

spl_autoload_register( function ( $class ) {
    $prefix = 'WPSE\\';
    if ( strpos( $class, $prefix ) !== 0 ) {
        return;
    }
    $relative = str_replace( '\\', DIRECTORY_SEPARATOR, substr( $class, strlen( $prefix ) ) );
    $file     = WPSE_PLUGIN_DIR . 'includes/class-' . strtolower( str_replace( '_', '-', $relative ) ) . '.php';
    if ( file_exists( $file ) ) {
        require $file;
    }
} );

register_activation_hook( __FILE__, function () {
    require_once WPSE_PLUGIN_DIR . 'includes/class-installer.php';
    WPSE\Installer::activate();
} );

register_deactivation_hook( __FILE__, function () {
    require_once WPSE_PLUGIN_DIR . 'includes/class-installer.php';
    WPSE\Installer::deactivate();
} );

add_action( 'plugins_loaded', function () {
    require_once WPSE_PLUGIN_DIR . 'includes/class-installer.php';

    $repository = new WPSE\Snapshot_Repository();
    $hasher     = new WPSE\Hasher();
    $serializer = new WPSE\Elementor_Serializer();
    $service    = new WPSE\Snapshot_Service( $repository, $hasher, $serializer );
    $manager    = new WPSE\Snapshot_Manager( $service );
    $restore    = new WPSE\Restore_Service( $repository, $serializer );

    $manager->register_hooks();

    $rest = new WPSE\Rest_API( $repository, $restore );
    add_action( 'rest_api_init', [ $rest, 'register_routes' ] );

    if ( is_admin() ) {
        $admin = new WPSE\Admin( $repository );
        $admin->register();
    }
} );
