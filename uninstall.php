<?php
/**
 * Uninstall routine for eCommerce Wunderkiste.
 *
 * Runs when the plugin is deleted (not merely deactivated) and removes the
 * options and product meta the plugin created.
 *
 * @package eCommerceWunderkiste
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

/**
 * Remove all plugin data for the current site.
 */
function wpe_uninstall_cleanup_site() {
    delete_option( 'wpe_options' );
    delete_option( 'wcib_settings' );

    $meta_keys = array(
        '_price_on_request',
        '_price_on_request_text',
        '_disabled_shipping_methods',
        '_wpe_accessory_products',
        '_wpe_tiered_pricing_rules',
        '_wpe_recovery_context',
    );

    foreach ( $meta_keys as $meta_key ) {
        delete_post_meta_by_key( $meta_key );
    }

    // Remove any recovery checks still sitting in the cron array.
    $crons = _get_cron_array();

    if ( is_array( $crons ) ) {
        foreach ( $crons as $timestamp => $hooks ) {
            if ( empty( $hooks['wpe_check_pending_order_event'] ) ) {
                continue;
            }
            foreach ( $hooks['wpe_check_pending_order_event'] as $event ) {
                wp_unschedule_event( $timestamp, 'wpe_check_pending_order_event', $event['args'] );
            }
        }
    }
}

if ( is_multisite() ) {
    $wpe_site_ids = get_sites(
        array(
            'fields' => 'ids',
            'number' => 0,
        )
    );

    foreach ( $wpe_site_ids as $wpe_site_id ) {
        switch_to_blog( (int) $wpe_site_id );
        wpe_uninstall_cleanup_site();
        restore_current_blog();
    }
} else {
    wpe_uninstall_cleanup_site();
}
