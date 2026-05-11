<?php
namespace WPSE;

defined( 'ABSPATH' ) || exit;

/**
 * Generates deterministic MD5 hashes for snapshot payloads.
 */
class Hasher {

    /**
     * Hash an arbitrary string or array (serialised deterministically).
     *
     * @param string|array $data
     * @return string MD5 hex string (32 chars).
     */
    public function hash( $data ): string {
        if ( is_array( $data ) ) {
            // Sort keys so order differences don't create different hashes.
            $data = $this->recursive_ksort( $data );
            $data = wp_json_encode( $data );
        }
        return md5( (string) $data );
    }

    private function recursive_ksort( array $array ): array {
        ksort( $array );
        foreach ( $array as &$value ) {
            if ( is_array( $value ) ) {
                $value = $this->recursive_ksort( $value );
            }
        }
        return $array;
    }
}
