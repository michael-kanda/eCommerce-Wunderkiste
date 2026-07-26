<?php
/**
 * Wrapper module: WooCommerce Cart Info Box.
 *
 * Integrates the formerly standalone "WooCommerce Cart Info Box" plugin
 * as a module of eCommerce Wunderkiste.
 *
 * @package eCommerceWunderkiste
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class WPE_Cart_Info_Box
 */
class WPE_Cart_Info_Box {

    /**
     * Constructor.
     */
    public function __construct() {
        // The standalone plugin is still active: it already owns the classes,
        // the settings screen and the box output. Loading the module on top
        // would duplicate the admin menu and render the box twice.
        if ( class_exists( 'WCIB_Settings' ) || class_exists( 'WCIB_Display' ) ) {
            add_action( 'admin_notices', array( $this, 'duplicate_plugin_notice' ) );
            return;
        }

        if ( ! defined( 'WCIB_VERSION' ) ) {
            define( 'WCIB_VERSION', WPE_VERSION );
            define( 'WCIB_PLUGIN_FILE', WPE_PLUGIN_FILE );
            define( 'WCIB_PLUGIN_DIR', WPE_PLUGIN_DIR . 'includes/cart-info-box/' );
            define( 'WCIB_PLUGIN_URL', WPE_PLUGIN_URL . 'includes/cart-info-box/' );
            define( 'WCIB_OPTION_KEY', 'wcib_settings' );
        }

        require_once WCIB_PLUGIN_DIR . 'class-wcib-settings.php';
        require_once WCIB_PLUGIN_DIR . 'class-wcib-display.php';
        require_once WCIB_PLUGIN_DIR . 'class-wcib-block.php';

        new WCIB_Settings();
        new WCIB_Display();
        new WCIB_Block();

        $this->maybe_seed_defaults();
    }

    /**
     * Warn about the standalone plugin still being active.
     */
    public function duplicate_plugin_notice() {
        if ( ! current_user_can( 'activate_plugins' ) ) {
            return;
        }
        ?>
        <div class="notice notice-warning">
            <p>
                <?php
                esc_html_e(
                    'eCommerce Wunderkiste: the Cart Info Box module is switched off because the standalone "WooCommerce Cart Info Box" plugin is still active. Deactivate that plugin to use the built-in module. Your settings are kept either way.',
                    'ecommerce-wunderkiste'
                );
                ?>
            </p>
        </div>
        <?php
    }

    /**
     * Seed sensible defaults once, if no options exist yet.
     */
    private function maybe_seed_defaults() {
        if ( false !== get_option( WCIB_OPTION_KEY ) ) {
            return;
        }

        add_option( WCIB_OPTION_KEY, WCIB_Settings::get_defaults() );
    }
}
