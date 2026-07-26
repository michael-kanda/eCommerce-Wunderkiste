<?php
/**
 * Admin class for eCommerce Wunderkiste.
 *
 * @package eCommerceWunderkiste
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class WPE_Admin
 */
class WPE_Admin {

    /**
     * Option keys that are simple on/off switches.
     *
     * @var string[]
     */
    private $checkbox_keys = array(
        'enable_price_on_request',
        'enable_disable_shipping',
        'enable_product_accessories',
        'enable_image_resizer',
        'enable_order_recovery',
        'enable_tiered_pricing',
        'enable_cart_info_box',
    );

    /**
     * Constructor.
     */
    public function __construct() {
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
        add_filter( 'plugin_action_links_' . WPE_PLUGIN_BASENAME, array( $this, 'add_settings_link' ) );
    }

    /**
     * Register the settings screen.
     */
    public function add_admin_menu() {
        add_submenu_page(
            'woocommerce',
            __( 'Product Extras', 'ecommerce-wunderkiste' ),
            __( 'Product Extras', 'ecommerce-wunderkiste' ),
            'manage_woocommerce',
            'woo-product-extras',
            array( $this, 'render_settings_page' )
        );
    }

    /**
     * Register settings, sections and fields.
     */
    public function register_settings() {
        register_setting(
            'wpe_settings_group',
            'wpe_options',
            array(
                'type'              => 'array',
                'sanitize_callback' => array( $this, 'sanitize_options' ),
            )
        );

        add_settings_section(
            'wpe_main_section',
            __( 'Modules & settings', 'ecommerce-wunderkiste' ),
            array( $this, 'main_section_callback' ),
            'woo-product-extras'
        );

        add_settings_field(
            'plugin_language',
            __( 'Language for e-mails', 'ecommerce-wunderkiste' ),
            array( $this, 'select_language_callback' ),
            'woo-product-extras',
            'wpe_main_section',
            array(
                'id'          => 'plugin_language',
                'description' => __( 'Language used for the Order Recovery e-mails.', 'ecommerce-wunderkiste' ),
            )
        );

        $checkboxes = array(
            'enable_price_on_request'    => array(
                'label'       => __( 'Price on request', 'ecommerce-wunderkiste' ),
                'description' => __( 'Show "Price on request" instead of the price on individual products.', 'ecommerce-wunderkiste' ),
            ),
            'enable_disable_shipping'    => array(
                'label'       => __( 'Disable shipping methods', 'ecommerce-wunderkiste' ),
                'description' => __( 'Disable specific shipping methods per product.', 'ecommerce-wunderkiste' ),
            ),
            'enable_product_accessories' => array(
                'label'       => __( 'Accessories tab', 'ecommerce-wunderkiste' ),
                'description' => __( 'Adds an "Accessories" tab to products for linking matching items.', 'ecommerce-wunderkiste' ),
            ),
            'enable_image_resizer'       => array(
                'label'       => __( 'Image resizer 800px/1200px', 'ecommerce-wunderkiste' ),
                'description' => __( 'Adds a button to the media library to scale images down to 800px or 1200px. Overwrites the original file.', 'ecommerce-wunderkiste' ),
            ),
            'enable_order_recovery'      => array(
                'label'       => __( 'Order recovery (payment abandoned)', 'ecommerce-wunderkiste' ),
                'description' => __( 'Reminder mail after 1 hour, instant mail on payment failure, manual resend button.', 'ecommerce-wunderkiste' ),
            ),
            'enable_tiered_pricing'      => array(
                'label'       => __( 'Tiered pricing', 'ecommerce-wunderkiste' ),
                'description' => __( 'Quantity based pricing per product (volume discounts).', 'ecommerce-wunderkiste' ),
            ),
            'enable_cart_info_box'       => array(
                'label'       => __( 'Cart info box', 'ecommerce-wunderkiste' ),
                'description' => __( 'Shows a configurable info box in the cart (classic cart and/or cart block). Configure under WooCommerce → Cart Info Box.', 'ecommerce-wunderkiste' ),
            ),
        );

        foreach ( $checkboxes as $id => $field ) {
            add_settings_field(
                $id,
                $field['label'],
                array( $this, 'checkbox_field_callback' ),
                'woo-product-extras',
                'wpe_main_section',
                array(
                    'id'          => $id,
                    'description' => $field['description'],
                )
            );
        }

        // Order Recovery section.
        add_settings_section(
            'wpe_recovery_section',
            __( 'Order recovery', 'ecommerce-wunderkiste' ),
            array( $this, 'recovery_section_callback' ),
            'woo-product-extras'
        );

        add_settings_field(
            'recovery_contact_email',
            __( 'Contact address in recovery e-mails', 'ecommerce-wunderkiste' ),
            array( $this, 'email_field_callback' ),
            'woo-product-extras',
            'wpe_recovery_section',
            array(
                'id'          => 'recovery_contact_email',
                'description' => __( 'Shown to customers as the contact address. Leave empty to use the WordPress admin e-mail.', 'ecommerce-wunderkiste' ),
            )
        );

        // CSS section.
        add_settings_section(
            'wpe_css_section',
            __( 'Custom CSS', 'ecommerce-wunderkiste' ),
            array( $this, 'css_section_callback' ),
            'woo-product-extras'
        );

        add_settings_field(
            'price_on_request_css',
            __( 'Price on request CSS', 'ecommerce-wunderkiste' ),
            array( $this, 'textarea_field_callback' ),
            'woo-product-extras',
            'wpe_css_section',
            array(
                'id'          => 'price_on_request_css',
                'description' => __( 'CSS for the "Price on request" output. Use the class .price-on-request', 'ecommerce-wunderkiste' ),
                'rows'        => 10,
            )
        );
    }

    /**
     * Main section intro.
     */
    public function main_section_callback() {
        echo '<p>' . esc_html__( 'Enable the modules you need.', 'ecommerce-wunderkiste' ) . '</p>';
    }

    /**
     * Recovery section intro.
     */
    public function recovery_section_callback() {
        echo '<p>' . esc_html__( 'These settings only take effect while the Order Recovery module is enabled.', 'ecommerce-wunderkiste' ) . '</p>';

        if ( defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ) {
            echo '<p class="notice notice-warning" style="padding:8px 12px;">'
                . esc_html__( 'DISABLE_WP_CRON is active on this site. Make sure a real system cron calls wp-cron.php, otherwise the 1-hour reminder will never run.', 'ecommerce-wunderkiste' )
                . '</p>';
        }
    }

    /**
     * CSS section intro.
     */
    public function css_section_callback() {
        echo '<p>' . esc_html__( 'Adjust the appearance with your own CSS.', 'ecommerce-wunderkiste' ) . '</p>';
    }

    /**
     * Render a checkbox field.
     *
     * @param array $args Field args.
     */
    public function checkbox_field_callback( $args ) {
        $options = get_option( 'wpe_options', array() );
        $value   = isset( $options[ $args['id'] ] ) ? $options[ $args['id'] ] : 0;
        ?>
        <label>
            <input type="checkbox"
                   name="wpe_options[<?php echo esc_attr( $args['id'] ); ?>]"
                   value="1"
                   <?php checked( 1, (int) $value ); ?>>
            <?php echo esc_html( $args['description'] ); ?>
        </label>
        <?php
    }

    /**
     * Render the language select.
     *
     * @param array $args Field args.
     */
    public function select_language_callback( $args ) {
        $options = get_option( 'wpe_options', array() );
        $value   = isset( $options[ $args['id'] ] ) ? $options[ $args['id'] ] : 'de';
        ?>
        <select name="wpe_options[<?php echo esc_attr( $args['id'] ); ?>]">
            <option value="de" <?php selected( 'de', $value ); ?>>Deutsch</option>
            <option value="en" <?php selected( 'en', $value ); ?>>English</option>
        </select>
        <p class="description"><?php echo esc_html( $args['description'] ); ?></p>
        <?php
    }

    /**
     * Render an e-mail field.
     *
     * @param array $args Field args.
     */
    public function email_field_callback( $args ) {
        $options = get_option( 'wpe_options', array() );
        $value   = isset( $options[ $args['id'] ] ) ? $options[ $args['id'] ] : '';
        ?>
        <input type="email"
               class="regular-text"
               name="wpe_options[<?php echo esc_attr( $args['id'] ); ?>]"
               id="<?php echo esc_attr( $args['id'] ); ?>"
               value="<?php echo esc_attr( $value ); ?>"
               placeholder="<?php echo esc_attr( get_option( 'admin_email' ) ); ?>">
        <p class="description"><?php echo esc_html( $args['description'] ); ?></p>
        <?php
    }

    /**
     * Render a textarea field.
     *
     * @param array $args Field args.
     */
    public function textarea_field_callback( $args ) {
        $options = get_option( 'wpe_options', array() );
        $value   = isset( $options[ $args['id'] ] ) ? $options[ $args['id'] ] : '';
        $rows    = isset( $args['rows'] ) ? (int) $args['rows'] : 5;
        ?>
        <textarea name="wpe_options[<?php echo esc_attr( $args['id'] ); ?>]"
                  id="<?php echo esc_attr( $args['id'] ); ?>"
                  rows="<?php echo esc_attr( $rows ); ?>"
                  class="large-text code"><?php echo esc_textarea( $value ); ?></textarea>
        <p class="description"><?php echo esc_html( $args['description'] ); ?></p>
        <?php
    }

    /**
     * Sanitize the option array.
     *
     * @param mixed $input Raw input.
     * @return array
     */
    public function sanitize_options( $input ) {
        $existing  = get_option( 'wpe_options', array() );
        $existing  = is_array( $existing ) ? $existing : array();
        $input     = is_array( $input ) ? $input : array();
        $sanitized = $existing;

        foreach ( $this->checkbox_keys as $key ) {
            $sanitized[ $key ] = ! empty( $input[ $key ] ) ? 1 : 0;
        }

        $sanitized['plugin_language'] = ( isset( $input['plugin_language'] ) && 'en' === $input['plugin_language'] ) ? 'en' : 'de';

        if ( isset( $input['recovery_contact_email'] ) ) {
            $email = sanitize_email( wp_unslash( $input['recovery_contact_email'] ) );

            if ( '' !== trim( (string) $input['recovery_contact_email'] ) && ! is_email( $email ) ) {
                add_settings_error(
                    'wpe_options',
                    'wpe_invalid_email',
                    __( 'The contact address is not a valid e-mail address and was not saved.', 'ecommerce-wunderkiste' ),
                    'error'
                );
                $email = isset( $existing['recovery_contact_email'] ) ? $existing['recovery_contact_email'] : '';
            }

            $sanitized['recovery_contact_email'] = $email;
        }

        if ( isset( $input['price_on_request_css'] ) ) {
            $sanitized['price_on_request_css'] = wp_strip_all_tags( wp_unslash( $input['price_on_request_css'] ) );
        }

        return $sanitized;
    }

    /**
     * Load the CodeMirror editor on our settings screen.
     *
     * @param string $hook Current admin page hook.
     */
    public function enqueue_admin_scripts( $hook ) {
        if ( 'woocommerce_page_woo-product-extras' !== $hook ) {
            return;
        }

        $settings = wp_enqueue_code_editor( array( 'type' => 'text/css' ) );

        // False when the user disabled syntax highlighting, or when
        // wp_enqueue_code_editor() is short-circuited. Nothing else to do then.
        if ( false === $settings ) {
            return;
        }

        // Hook the init onto `code-editor` itself instead of
        // `wp-theme-plugin-editor` - the latter is not registered when
        // DISALLOW_FILE_EDIT is set, which silently dropped the inline script.
        wp_add_inline_script(
            'code-editor',
            sprintf(
                'jQuery(function($){ if ($("#price_on_request_css").length) { wp.codeEditor.initialize($("#price_on_request_css"), %s); } });',
                wp_json_encode( $settings )
            )
        );
    }

    /**
     * Add a settings link on the plugins screen.
     *
     * @param array $links Existing links.
     * @return array
     */
    public function add_settings_link( $links ) {
        $settings_link = '<a href="' . esc_url( admin_url( 'admin.php?page=woo-product-extras' ) ) . '">'
            . esc_html__( 'Settings', 'ecommerce-wunderkiste' ) . '</a>';
        array_unshift( $links, $settings_link );
        return $links;
    }

    /**
     * Render the settings screen.
     */
    public function render_settings_page() {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_die( esc_html__( 'You do not have permission to access this page.', 'ecommerce-wunderkiste' ) );
        }
        ?>
        <div class="wrap">
            <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

            <?php settings_errors(); ?>

            <form action="options.php" method="post">
                <?php
                settings_fields( 'wpe_settings_group' );
                do_settings_sections( 'woo-product-extras' );
                submit_button( __( 'Save settings', 'ecommerce-wunderkiste' ) );
                ?>
            </form>
        </div>
        <?php
    }
}
