<?php
/**
 * Strict hostname-to-tenant routing. Pure helpers; safe to unit test without WordPress.
 *
 * AARAA_BASE_DOMAIN must be the exact registrable application hostname,
 * e.g. example.com. No arbitrary HTTP Host value is treated as a tenant.
 */
function aaraa_tenant_from_host( $host, $base_domain ) {
    if ( ! is_string( $host ) || ! is_string( $base_domain ) ) {
        return false;
    }
    $host = strtolower( trim( $host ) );
    $base_domain = strtolower( trim( $base_domain ) );
    if ( ! preg_match( '/^[a-z0-9.-]+$/D', $base_domain ) ||
        strpos( $base_domain, '..' ) !== false || strpos( $base_domain, '.' ) === false ||
        strlen( $base_domain ) > 253 ) {
        return false;
    }
    // Permit the standard HTTP Host port suffix, but no untrusted domain suffixes.
    if ( preg_match( '/:([0-9]{1,5})$/D', $host, $m ) ) {
        if ( (int) $m[1] < 1 || (int) $m[1] > 65535 ) {
            return false;
        }
        $host = substr( $host, 0, -strlen( $m[0] ) );
    }
    if ( strlen( $host ) > 253 || ! preg_match( '/^[a-z0-9.-]+$/D', $host ) ||
        strpos( $host, '..' ) !== false || substr( $host, -1 ) === '.' ) {
        return false;
    }
    if ( $host === $base_domain || $host === 'www.' . $base_domain ||
        $host === 'api.' . $base_domain ) {
        return null; // Platform/admin site.
    }
    $suffix = '.' . $base_domain;
    if ( substr( $host, -strlen( $suffix ) ) !== $suffix ) {
        return false;
    }
    $label = substr( $host, 0, -strlen( $suffix ) );
    if ( strlen( $label ) < 1 || strlen( $label ) > 63 ||
        ! preg_match( '/^[a-z0-9](?:[a-z0-9-]*[a-z0-9])?$/D', $label ) ||
        in_array( $label, array( 'www', 'api' ), true ) ) {
        return false;
    }
    return $label;
}
