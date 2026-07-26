<?php
/**
 * Tiered Pricing module.
 *
 * Quantity based prices per product.
 *
 * @package eCommerceWunderkiste
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class WPE_Tiered_Pricing
 */
class WPE_Tiered_Pricing {

    /**
     * Meta key holding the tier rules.
     */
    const META_KEY = '_wpe_tiered_pricing_rules';

    /**
     * Constructor.
     */
    public function __construct() {
        add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ) );
        add_action( 'save_post_product', array( $this, 'save_meta_box' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );

        add_action( 'woocommerce_before_add_to_cart_form', array( $this, 'display_pricing_table' ), 20 );
        add_action( 'woocommerce_before_calculate_totals', array( $this, 'calculate_tiered_price' ), 10, 1 );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_styles' ) );
    }

    /**
     * Register the meta box.
     */
    public function add_meta_box() {
        add_meta_box(
            'wpe_tiered_pricing_box',
            __( 'Tiered pricing', 'ecommerce-wunderkiste' ),
            array( $this, 'render_meta_box' ),
            'product',
            'normal',
            'high'
        );
    }

    /**
     * Read the tier rules for a product.
     *
     * @param int $product_id Product or variation ID.
     * @return array
     */
    private function get_rules( $product_id ) {
        $rules = get_post_meta( (int) $product_id, self::META_KEY, true );

        return is_array( $rules ) ? $rules : array();
    }

    /**
     * Render the meta box.
     *
     * @param WP_Post $post Product post.
     */
    public function render_meta_box( $post ) {
        wp_nonce_field( 'wpe_tiered_pricing_save', 'wpe_tiered_pricing_nonce' );

        $rules = $this->get_rules( $post->ID );
        ?>
        <div class="wpe-tiered-pricing-wrapper">
            <p class="description"><?php esc_html_e( 'Define price tiers. Leave "Max qty" empty for "and more".', 'ecommerce-wunderkiste' ); ?></p>

            <table class="widefat" id="wpe-pricing-rules-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Min qty', 'ecommerce-wunderkiste' ); ?></th>
                        <th><?php esc_html_e( 'Max qty', 'ecommerce-wunderkiste' ); ?></th>
                        <th><?php esc_html_e( 'Unit price', 'ecommerce-wunderkiste' ); ?></th>
                        <th style="width:50px;"></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $rules as $rule ) : ?>
                        <?php
                        $min   = isset( $rule['min'] ) ? $rule['min'] : '';
                        $max   = isset( $rule['max'] ) ? $rule['max'] : '';
                        $price = isset( $rule['price'] ) ? $rule['price'] : '';
                        ?>
                        <tr>
                            <td><input type="number" name="wpe_tier_min[]" value="<?php echo esc_attr( $min ); ?>" class="widefat" min="1" step="1"></td>
                            <td><input type="number" name="wpe_tier_max[]" value="<?php echo esc_attr( $max ); ?>" class="widefat" min="1" step="1"></td>
                            <td><input type="text" name="wpe_tier_price[]" value="<?php echo esc_attr( wc_format_localized_price( $price ) ); ?>" class="widefat wc_input_price"></td>
                            <td><button type="button" class="button wpe-remove-row" aria-label="<?php esc_attr_e( 'Remove tier', 'ecommerce-wunderkiste' ); ?>">&times;</button></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="4">
                            <button type="button" class="button button-primary" id="wpe-add-tier-row"><?php esc_html_e( 'Add tier', 'ecommerce-wunderkiste' ); ?></button>
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
        <?php
    }

    /**
     * Enqueue the meta box script on product screens.
     *
     * @param string $hook Current admin page hook.
     */
    public function enqueue_admin_scripts( $hook ) {
        if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
            return;
        }

        $screen = get_current_screen();

        if ( ! $screen || 'product' !== $screen->post_type ) {
            return;
        }

        wp_register_script( 'wpe-tiered-pricing-admin', false, array( 'jquery' ), WPE_VERSION, true );
        wp_enqueue_script( 'wpe-tiered-pricing-admin' );
        wp_add_inline_script(
            'wpe-tiered-pricing-admin',
            'jQuery(function($){'
            . '$("#wpe-add-tier-row").on("click",function(){'
            . 'var $tbody=$("#wpe-pricing-rules-table tbody");'
            . 'var $row=$("<tr></tr>");'
            . '$row.append($("<td></td>").append($("<input>",{type:"number",name:"wpe_tier_min[]","class":"widefat",min:1,step:1})));'
            . '$row.append($("<td></td>").append($("<input>",{type:"number",name:"wpe_tier_max[]","class":"widefat",min:1,step:1})));'
            . '$row.append($("<td></td>").append($("<input>",{type:"text",name:"wpe_tier_price[]","class":"widefat wc_input_price"})));'
            . '$row.append($("<td></td>").append($("<button>",{type:"button","class":"button wpe-remove-row",text:"\u00d7"})));'
            . '$tbody.append($row);'
            . '});'
            . '$(document).on("click",".wpe-remove-row",function(){$(this).closest("tr").remove();});'
            . '});'
        );

        wp_register_style( 'wpe-tiered-pricing-admin', false, array(), WPE_VERSION );
        wp_enqueue_style( 'wpe-tiered-pricing-admin' );
        wp_add_inline_style(
            'wpe-tiered-pricing-admin',
            '#wpe-pricing-rules-table td{vertical-align:middle}.wpe-remove-row{color:#a00;border-color:#a00}'
        );
    }

    /**
     * Save the tier rules.
     *
     * @param int $post_id Product ID.
     */
    public function save_meta_box( $post_id ) {
        if ( ! isset( $_POST['wpe_tiered_pricing_nonce'] ) ) {
            return;
        }

        $nonce = sanitize_text_field( wp_unslash( $_POST['wpe_tiered_pricing_nonce'] ) );

        if ( ! wp_verify_nonce( $nonce, 'wpe_tiered_pricing_save' ) ) {
            return;
        }

        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }

        if ( ! current_user_can( 'edit_product', $post_id ) ) {
            return;
        }

        $rules = array();

        if ( isset( $_POST['wpe_tier_min'] ) && is_array( $_POST['wpe_tier_min'] ) ) {
            $mins   = array_map( 'sanitize_text_field', wp_unslash( $_POST['wpe_tier_min'] ) );
            $maxs   = isset( $_POST['wpe_tier_max'] ) && is_array( $_POST['wpe_tier_max'] )
                ? array_map( 'sanitize_text_field', wp_unslash( $_POST['wpe_tier_max'] ) )
                : array();
            $prices = isset( $_POST['wpe_tier_price'] ) && is_array( $_POST['wpe_tier_price'] )
                ? array_map( 'sanitize_text_field', wp_unslash( $_POST['wpe_tier_price'] ) )
                : array();

            $count = count( $mins );

            for ( $i = 0; $i < $count; $i++ ) {
                $min   = isset( $mins[ $i ] ) ? absint( $mins[ $i ] ) : 0;
                $price = isset( $prices[ $i ] ) ? wc_format_decimal( $prices[ $i ] ) : '';
                $max   = ( isset( $maxs[ $i ] ) && '' !== $maxs[ $i ] ) ? absint( $maxs[ $i ] ) : '';

                if ( $min < 1 || '' === $price ) {
                    continue;
                }

                // Drop nonsensical ranges instead of storing them.
                if ( '' !== $max && $max < $min ) {
                    continue;
                }

                $rules[] = array(
                    'min'   => $min,
                    'max'   => $max,
                    'price' => $price,
                );
            }

            usort(
                $rules,
                static function ( $a, $b ) {
                    return $a['min'] <=> $b['min'];
                }
            );
        }

        if ( empty( $rules ) ) {
            delete_post_meta( $post_id, self::META_KEY );
            return;
        }

        update_post_meta( $post_id, self::META_KEY, $rules );
    }

    /**
     * Frontend: render the tier table.
     */
    public function display_pricing_table() {
        global $product;

        if ( ! $product instanceof WC_Product ) {
            return;
        }

        $rules = $this->get_rules( $product->get_id() );

        if ( empty( $rules ) ) {
            return;
        }

        echo '<div class="wpe-tiered-pricing-table-container">';
        echo '<h4>' . esc_html__( 'Tiered pricing', 'ecommerce-wunderkiste' ) . '</h4>';
        echo '<table class="wpe-tiered-pricing-table"><thead><tr><th>'
            . esc_html__( 'Quantity', 'ecommerce-wunderkiste' ) . '</th><th>'
            . esc_html__( 'Price per unit', 'ecommerce-wunderkiste' ) . '</th></tr></thead><tbody>';

        foreach ( $rules as $rule ) {
            $range = ! empty( $rule['max'] )
                ? $rule['min'] . ' - ' . $rule['max']
                : $rule['min'] . '+';

            // wc_get_price_to_display() applies the shop's tax display setting,
            // so a net-entry shop showing gross prices no longer advertises net
            // amounts here (price indication rules in AT/DE).
            $display_price = wc_get_price_to_display( $product, array( 'price' => $rule['price'] ) );

            echo '<tr><td>' . esc_html( $range ) . ' ' . esc_html__( 'pcs.', 'ecommerce-wunderkiste' ) . '</td>';
            echo '<td>' . wp_kses_post( wc_price( $display_price ) . wc_get_price_suffix( $product, $display_price ) ) . '</td></tr>';
        }

        echo '</tbody></table></div>';
    }

    /**
     * Frontend: apply the tier price in the cart.
     *
     * Setting an absolute price is idempotent, so no run-once guard is needed
     * even when WooCommerce recalculates totals several times per request.
     *
     * @param WC_Cart $cart Cart object.
     */
    public function calculate_tiered_price( $cart ) {
        if ( is_admin() && ! wp_doing_ajax() ) {
            return;
        }

        if ( ! $cart instanceof WC_Cart ) {
            return;
        }

        foreach ( $cart->get_cart() as $cart_item ) {
            if ( empty( $cart_item['data'] ) || ! $cart_item['data'] instanceof WC_Product ) {
                continue;
            }

            $product  = $cart_item['data'];
            $quantity = (int) $cart_item['quantity'];

            // Variation-level rules win; otherwise fall back to the parent.
            $rules = $this->get_rules( $product->get_id() );

            if ( empty( $rules ) && ! empty( $cart_item['product_id'] ) ) {
                $rules = $this->get_rules( $cart_item['product_id'] );
            }

            if ( empty( $rules ) ) {
                continue;
            }

            $matched_price = null;

            foreach ( $rules as $rule ) {
                $min = (int) $rule['min'];
                $max = ( '' === $rule['max'] ) ? 0 : (int) $rule['max'];

                if ( $quantity >= $min && ( 0 === $max || $quantity <= $max ) ) {
                    $matched_price = $rule['price'];
                }
            }

            if ( null !== $matched_price ) {
                $product->set_price( $matched_price );
            }
        }
    }

    /**
     * Frontend styles.
     */
    public function enqueue_frontend_styles() {
        if ( ! function_exists( 'is_product' ) || ! is_product() ) {
            return;
        }

        wp_register_style( 'wpe-tiered-pricing', false, array(), WPE_VERSION );
        wp_enqueue_style( 'wpe-tiered-pricing' );
        wp_add_inline_style(
            'wpe-tiered-pricing',
            '.wpe-tiered-pricing-table-container{margin-bottom:20px}'
            . '.wpe-tiered-pricing-table{width:100%;max-width:400px;border-collapse:collapse;margin-top:10px}'
            . '.wpe-tiered-pricing-table th,.wpe-tiered-pricing-table td{border:1px solid #ddd;padding:8px;text-align:left}'
            . '.wpe-tiered-pricing-table th{background-color:#f9f9f9}'
            . '.wpe-tiered-pricing-table tr:nth-child(even){background-color:#f2f2f2}'
        );
    }
}
