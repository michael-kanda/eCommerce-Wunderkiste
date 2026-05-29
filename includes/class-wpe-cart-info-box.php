<?php
/**
 * Wrapper-Modul: WooCommerce Cart Info Box
 *
 * Integriert das ehemals eigenständige Plugin "WooCommerce Cart Info Box"
 * (woo-cart-info-box) als Modul in eCommerce Wunderkiste.
 *
 * @package eCommerceWunderkiste
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class WPE_Cart_Info_Box
 *
 * Definiert die WCIB_*-Konstanten passend zur Modul-Pfadstruktur,
 * lädt die ursprünglichen Klassen unverändert und bootet sie.
 */
class WPE_Cart_Info_Box {

    public function __construct() {
        if ( ! defined( 'WCIB_VERSION' ) ) {
            define( 'WCIB_VERSION', WPE_VERSION );
            define( 'WCIB_PLUGIN_FILE', WPE_PLUGIN_DIR . 'woo-product-extras.php' );
            define( 'WCIB_PLUGIN_DIR',  WPE_PLUGIN_DIR . 'includes/cart-info-box/' );
            define( 'WCIB_PLUGIN_URL',  WPE_PLUGIN_URL . 'includes/cart-info-box/' );
            define( 'WCIB_OPTION_KEY',  'wcib_settings' );
        }

        // HPOS- und Cart/Checkout-Block-Kompatibilität deklarieren.
        add_action(
            'before_woocommerce_init',
            static function () {
                if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
                    \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
                        'custom_order_tables',
                        WCIB_PLUGIN_FILE,
                        true
                    );
                    \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
                        'cart_checkout_blocks',
                        WCIB_PLUGIN_FILE,
                        true
                    );
                }
            }
        );

        require_once WCIB_PLUGIN_DIR . 'class-wcib-settings.php';
        require_once WCIB_PLUGIN_DIR . 'class-wcib-display.php';
        require_once WCIB_PLUGIN_DIR . 'class-wcib-block.php';

        new WCIB_Settings();
        new WCIB_Display();
        new WCIB_Block();

        $this->maybe_seed_defaults();
    }

    /**
     * Setzt einmalig sinnvolle Default-Werte, falls noch keine Optionen existieren.
     * Übernimmt die Defaults aus dem ursprünglichen Plugin-Aktivierungs-Hook.
     */
    private function maybe_seed_defaults() {
        if ( false !== get_option( WCIB_OPTION_KEY ) ) {
            return;
        }

        add_option(
            WCIB_OPTION_KEY,
            array(
                'enabled'         => 1,
                'title'           => __( 'Info', 'woo-cart-info-box' ),
                'message'         => __( 'Versandkostenfrei ab 99 €', 'woo-cart-info-box' ),
                'icon'            => '🚚',
                'bg_color'        => '#eaf6ff',
                'text_color'      => '#0a4a7a',
                'position'        => 'before_cart',
                'display_classic' => 1,
                'display_block'   => 1,
                'block_position'  => 'before',
            )
        );
    }
}
