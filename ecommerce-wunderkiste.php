<?php
/**
 * Plugin Name: eCommerce Wunderkiste
 * Plugin URI: https://designare.at
 * Description: Extended product options for WooCommerce - Price on Request, Shipping Methods per Product, Accessories Tab, Image Resizer, Order Recovery, Tiered Pricing & Cart Info Box.
 * Version: 1.3.0
 * Author: Michael Kanda
 * Author URI: https://designare.at
 * Text Domain: ecommerce-wunderkiste
 * Domain Path: /languages
 * Requires at least: 6.5
 * Requires PHP: 7.4
 * Requires Plugins: woocommerce
 * WC requires at least: 9.0
 * WC tested up to: 11.0
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package eCommerceWunderkiste
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Plugin constants.
define( 'WPE_VERSION', '1.3.0' );
define( 'WPE_PLUGIN_FILE', __FILE__ );
define( 'WPE_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WPE_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'WPE_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Declare WooCommerce feature compatibility.
 *
 * Registered on file load so it runs before `before_woocommerce_init` fires,
 * and declared unconditionally so the flags do not depend on which modules
 * the site happens to have switched on.
 */
add_action(
    'before_woocommerce_init',
    static function () {
        if ( ! class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
            return;
        }
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', WPE_PLUGIN_FILE, true );
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', WPE_PLUGIN_FILE, true );
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'product_block_editor', WPE_PLUGIN_FILE, true );
    }
);

/**
 * Main plugin class.
 */
class WooCommerce_Product_Extras {

    /**
     * Singleton instance.
     *
     * @var WooCommerce_Product_Extras|null
     */
    private static $instance = null;

    /**
     * Singleton accessor.
     *
     * @return WooCommerce_Product_Extras
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor.
     */
    private function __construct() {
        add_action( 'plugins_loaded', array( $this, 'init' ) );

        // The settings pages run on `manage_woocommerce`, so options.php has to
        // accept that capability too - otherwise shop managers can open the
        // screen but get "you are not allowed to" when saving.
        add_filter( 'option_page_capability_wpe_settings_group', array( $this, 'settings_capability' ) );
        add_filter( 'option_page_capability_wcib_settings_group', array( $this, 'settings_capability' ) );

        register_activation_hook( __FILE__, array( $this, 'activate' ) );
        register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );
    }

    /**
     * Capability required to save this plugin's option groups.
     *
     * @return string
     */
    public function settings_capability() {
        return 'manage_woocommerce';
    }

    /**
     * Initialisation.
     */
    public function init() {
        if ( ! class_exists( 'WooCommerce' ) ) {
            add_action( 'admin_notices', array( $this, 'woocommerce_missing_notice' ) );
            return;
        }

        if ( is_admin() ) {
            require_once WPE_PLUGIN_DIR . 'includes/class-wpe-admin.php';
            new WPE_Admin();
        }

        $this->load_modules();
    }

    /**
     * Load the enabled modules.
     */
    private function load_modules() {
        $options = get_option( 'wpe_options', array() );

        $modules = array(
            'enable_price_on_request'    => array( 'class-wpe-price-on-request.php', 'WPE_Price_On_Request' ),
            'enable_disable_shipping'    => array( 'class-wpe-disable-shipping.php', 'WPE_Disable_Shipping' ),
            'enable_product_accessories' => array( 'class-wpe-product-accessories.php', 'WPE_Product_Accessories' ),
            'enable_image_resizer'       => array( 'class-wpe-image-resizer.php', 'WPE_Image_Resizer' ),
            'enable_order_recovery'      => array( 'class-wpe-order-recovery.php', 'WPE_Order_Recovery' ),
            'enable_tiered_pricing'      => array( 'class-wpe-tiered-pricing.php', 'WPE_Tiered_Pricing' ),
            'enable_cart_info_box'       => array( 'class-wpe-cart-info-box.php', 'WPE_Cart_Info_Box' ),
        );

        foreach ( $modules as $option_key => $module ) {
            if ( empty( $options[ $option_key ] ) ) {
                continue;
            }

            list( $file, $class ) = $module;
            require_once WPE_PLUGIN_DIR . 'includes/' . $file;

            if ( class_exists( $class ) ) {
                new $class();
            }
        }
    }

    /**
     * Admin notice when WooCommerce is missing.
     */
    public function woocommerce_missing_notice() {
        ?>
        <div class="notice notice-error">
            <p><?php esc_html_e( 'eCommerce Wunderkiste requires WooCommerce. Please install and activate WooCommerce.', 'ecommerce-wunderkiste' ); ?></p>
        </div>
        <?php
    }

    /**
     * Plugin activation.
     */
    public function activate() {
        $default_options = array(
            'enable_price_on_request'    => 0,
            'enable_disable_shipping'    => 0,
            'enable_product_accessories' => 0,
            'enable_image_resizer'       => 0,
            'enable_order_recovery'      => 0,
            'enable_tiered_pricing'      => 0,
            'enable_cart_info_box'       => 0,
            'plugin_language'            => 'de',
            'recovery_contact_email'     => get_option( 'admin_email' ),
            'price_on_request_css'       => ".price-on-request {\n    color: #e74c3c;\n    font-weight: bold;\n    font-size: 1.1em;\n}",
        );

        if ( ! get_option( 'wpe_options' ) ) {
            add_option( 'wpe_options', $default_options );
        }
    }

    /**
     * Plugin deactivation: remove every scheduled recovery check.
     */
    public function deactivate() {
        $crons = _get_cron_array();

        if ( ! is_array( $crons ) ) {
            return;
        }

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

WooCommerce_Product_Extras::get_instance();
