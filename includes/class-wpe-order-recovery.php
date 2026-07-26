<?php
/**
 * Order Recovery module.
 *
 * Handles failed payments and abandoned payment attempts.
 *
 * @package eCommerceWunderkiste
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class WPE_Order_Recovery
 */
class WPE_Order_Recovery {

    /**
     * Meta key storing why a recovery mail was sent.
     */
    const CONTEXT_META = '_wpe_recovery_context';

    /**
     * Constructor.
     */
    public function __construct() {
        // Scenario A: check one hour after the order was created.
        add_action( 'woocommerce_checkout_order_created', array( $this, 'schedule_pending_check_on_create' ), 10, 1 );
        add_action( 'woocommerce_store_api_checkout_order_processed', array( $this, 'schedule_pending_check_on_create' ), 10, 1 );
        add_action( 'woocommerce_thankyou', array( $this, 'schedule_pending_check_on_thankyou' ), 10, 1 );
        add_action( 'wpe_check_pending_order_event', array( $this, 'check_pending_order_status' ) );

        // Scenario B: immediate mail when the payment fails.
        add_action( 'woocommerce_order_status_failed', array( $this, 'send_recovery_email_immediately' ), 10, 2 );

        // Cancel the reminder once the order leaves "pending".
        add_action( 'woocommerce_order_status_processing', array( $this, 'clear_scheduled_check' ) );
        add_action( 'woocommerce_order_status_completed', array( $this, 'clear_scheduled_check' ) );
        add_action( 'woocommerce_order_status_cancelled', array( $this, 'clear_scheduled_check' ) );
        add_action( 'woocommerce_order_status_refunded', array( $this, 'clear_scheduled_check' ) );

        // Scenario C: manual button on the order screen.
        add_filter( 'woocommerce_order_actions', array( $this, 'add_custom_order_action' ) );
        add_action( 'woocommerce_order_action_wpe_send_payment_link', array( $this, 'process_custom_order_action' ) );

        // Extra message block inside the customer invoice mail.
        add_action( 'woocommerce_email_before_order_table', array( $this, 'add_custom_email_message' ), 10, 4 );
    }

    /**
     * Configured e-mail language.
     *
     * @return string 'de' or 'en'.
     */
    private function get_language() {
        $options = get_option( 'wpe_options', array() );
        return ( isset( $options['plugin_language'] ) && 'en' === $options['plugin_language'] ) ? 'en' : 'de';
    }

    /**
     * Contact address shown to customers.
     *
     * Configurable under WooCommerce → Product Extras; falls back to the
     * WordPress admin address rather than a hard-coded one.
     *
     * @return string
     */
    private function get_contact_email() {
        $options = get_option( 'wpe_options', array() );
        $email   = isset( $options['recovery_contact_email'] ) ? trim( (string) $options['recovery_contact_email'] ) : '';

        if ( ! is_email( $email ) ) {
            $email = get_option( 'admin_email' );
        }

        /**
         * Filter the contact address printed in recovery e-mails.
         *
         * @param string $email Contact address.
         */
        return (string) apply_filters( 'wpe_recovery_contact_email', $email );
    }

    /**
     * Resolve an order object from mixed input.
     *
     * @param WC_Order|int $order Order or order ID.
     * @return WC_Order|false
     */
    private function resolve_order( $order ) {
        if ( $order instanceof WC_Order ) {
            return $order;
        }

        return wc_get_order( $order );
    }

    /**
     * Trigger the WooCommerce customer invoice e-mail.
     *
     * @param WC_Order $order   Order object.
     * @param string   $context Why the mail is being sent.
     * @return bool Whether the mail was triggered.
     */
    private function trigger_customer_invoice( $order, $context ) {
        if ( ! function_exists( 'WC' ) || ! WC()->mailer() ) {
            return false;
        }

        $emails = WC()->mailer()->get_emails();

        if ( empty( $emails['WC_Email_Customer_Invoice'] ) || ! is_object( $emails['WC_Email_Customer_Invoice'] ) ) {
            $this->log_debug( 'Order #' . $order->get_id() . ' - WC_Email_Customer_Invoice is not available.' );
            return false;
        }

        // Remembered so the message block can use the right wording.
        $order->update_meta_data( self::CONTEXT_META, $context );
        $order->save();

        $emails['WC_Email_Customer_Invoice']->trigger( $order->get_id(), $order );

        return true;
    }

    /**
     * Scenario A: schedule the check when the order is created.
     *
     * @param WC_Order|int $order Order or order ID.
     */
    public function schedule_pending_check_on_create( $order ) {
        $order = $this->resolve_order( $order );

        if ( ! $order ) {
            return;
        }

        if ( ! $order->has_status( array( 'pending', 'on-hold' ) ) ) {
            $this->log_debug( 'Order #' . $order->get_id() . ' - status is not pending/on-hold, nothing scheduled.' );
            return;
        }

        $this->schedule_pending_check( $order->get_id() );
    }

    /**
     * Scenario A fallback: schedule from the thank-you page.
     *
     * @param int $order_id Order ID.
     */
    public function schedule_pending_check_on_thankyou( $order_id ) {
        $order = $this->resolve_order( $order_id );

        if ( ! $order || ! $order->has_status( 'pending' ) ) {
            return;
        }

        $this->schedule_pending_check( $order->get_id() );
    }

    /**
     * Schedule the one-hour check.
     *
     * @param int $order_id Order ID.
     */
    private function schedule_pending_check( $order_id ) {
        $order_id = (int) $order_id;

        if ( wp_next_scheduled( 'wpe_check_pending_order_event', array( $order_id ) ) ) {
            return;
        }

        /**
         * Filter the delay before the reminder is sent.
         *
         * @param int $delay Delay in seconds. Default one hour.
         */
        $delay = (int) apply_filters( 'wpe_recovery_pending_delay', HOUR_IN_SECONDS );

        $scheduled = wp_schedule_single_event( time() + $delay, 'wpe_check_pending_order_event', array( $order_id ) );

        if ( is_wp_error( $scheduled ) || false === $scheduled ) {
            $this->log_debug( 'Order #' . $order_id . ' - could not schedule the reminder.' );
            return;
        }

        $this->log_debug( 'Order #' . $order_id . ' - reminder scheduled for ' . gmdate( 'Y-m-d H:i:s', time() + $delay ) . ' UTC.' );
    }

    /**
     * Remove a scheduled check.
     *
     * @param int $order_id Order ID.
     */
    public function clear_scheduled_check( $order_id ) {
        $order_id  = (int) $order_id;
        $timestamp = wp_next_scheduled( 'wpe_check_pending_order_event', array( $order_id ) );

        if ( $timestamp ) {
            wp_unschedule_event( $timestamp, 'wpe_check_pending_order_event', array( $order_id ) );
        }
    }

    /**
     * Scenario A: cron callback.
     *
     * @param int $order_id Order ID.
     */
    public function check_pending_order_status( $order_id ) {
        $order = $this->resolve_order( $order_id );

        if ( ! $order ) {
            $this->log_debug( 'Order #' . $order_id . ' - order not found.' );
            return;
        }

        if ( ! $order->has_status( 'pending' ) ) {
            $this->log_debug( 'Order #' . $order->get_id() . ' - no longer pending (' . $order->get_status() . '), no mail sent.' );
            return;
        }

        // Offline gateways legitimately sit in an unpaid state, so exclude them
        // from the "your payment did not go through" wording and mail entirely.
        if ( $this->is_offline_gateway( $order ) ) {
            $this->log_debug( 'Order #' . $order->get_id() . ' - offline payment method, no reminder sent.' );
            return;
        }

        if ( $this->trigger_customer_invoice( $order, 'pending' ) ) {
            $order->add_order_note( __( '[Wunderkiste] Automatic reminder e-mail sent after 1 hour.', 'ecommerce-wunderkiste' ) );
            $this->send_admin_info_mail( $order, 'pending_recovery' );
        }
    }

    /**
     * Is the order using an offline/manual payment method?
     *
     * @param WC_Order $order Order object.
     * @return bool
     */
    private function is_offline_gateway( $order ) {
        /**
         * Filter the payment methods excluded from recovery mails.
         *
         * @param string[] $gateways Gateway IDs.
         * @param WC_Order $order    Order object.
         */
        $offline = apply_filters(
            'wpe_recovery_offline_gateways',
            array( 'bacs', 'cheque', 'cod' ),
            $order
        );

        return in_array( $order->get_payment_method(), (array) $offline, true );
    }

    /**
     * Scenario B: immediate mail on failure.
     *
     * @param int      $order_id Order ID.
     * @param WC_Order $order    Order object.
     */
    public function send_recovery_email_immediately( $order_id, $order ) {
        $order = $this->resolve_order( $order ? $order : $order_id );

        if ( ! $order ) {
            return;
        }

        if ( $this->trigger_customer_invoice( $order, 'failed' ) ) {
            $order->add_order_note( __( '[Wunderkiste] Immediate e-mail sent after the payment failed.', 'ecommerce-wunderkiste' ) );
            $this->send_admin_info_mail( $order, 'failed_recovery' );
        }

        $this->clear_scheduled_check( $order->get_id() );
    }

    /**
     * Notify the shop admin.
     *
     * @param WC_Order $order Order object.
     * @param string   $type  'failed_recovery' or 'pending_recovery'.
     */
    private function send_admin_info_mail( $order, $type ) {
        $to        = get_option( 'admin_email' );
        $order_id  = $order->get_id();
        // HPOS-safe: with custom order tables the old post.php link is dead.
        $edit_link = method_exists( $order, 'get_edit_order_url' )
            ? $order->get_edit_order_url()
            : admin_url( 'post.php?post=' . $order_id . '&action=edit' );
        $lang      = $this->get_language();

        if ( 'failed_recovery' === $type ) {
            if ( 'en' === $lang ) {
                $subject = sprintf( '[Admin info] Payment FAILED: order #%s', $order_id );
                $intro   = sprintf( 'the payment for order #%s has failed (status: failed).', $order_id );
            } else {
                $subject = sprintf( '[Admin Info] Zahlung FEHLGESCHLAGEN: Bestellung #%s', $order_id );
                $intro   = sprintf( 'die Zahlung fuer Bestellung #%s ist fehlgeschlagen (Status: Fehlgeschlagen).', $order_id );
            }
        } elseif ( 'en' === $lang ) {
            $subject = sprintf( '[Admin info] Payment still pending: order #%s', $order_id );
            $intro   = sprintf( 'order #%s has been unpaid for one hour (status: pending).', $order_id );
        } else {
            $subject = sprintf( '[Admin Info] Zahlung noch ausstehend: Bestellung #%s', $order_id );
            $intro   = sprintf( 'die Bestellung #%s ist seit einer Stunde unbezahlt (Status: Zahlung ausstehend).', $order_id );
        }

        if ( 'en' === $lang ) {
            $message = sprintf(
                "Hello,\n\n%s\n\nThe customer has automatically been sent a payment link.\n\nOrder: %s",
                $intro,
                $edit_link
            );
        } else {
            $message = sprintf(
                "Hallo,\n\n%s\n\nDem Kunden wurde automatisch ein Zahlungslink gesendet.\n\nBestellung: %s",
                $intro,
                $edit_link
            );
        }

        wp_mail( $to, $subject, $message );
    }

    /**
     * Scenario C: register the manual action.
     *
     * @param array $actions Existing order actions.
     * @return array
     */
    public function add_custom_order_action( $actions ) {
        $actions['wpe_send_payment_link'] = ( 'en' === $this->get_language() )
            ? 'Send payment link via e-mail (Wunderkiste)'
            : 'Zahlungslink per E-Mail senden (Wunderkiste)';

        return $actions;
    }

    /**
     * Scenario C: run the manual action.
     *
     * @param WC_Order $order Order object.
     */
    public function process_custom_order_action( $order ) {
        $order = $this->resolve_order( $order );

        if ( ! $order ) {
            return;
        }

        if ( $this->trigger_customer_invoice( $order, 'manual' ) ) {
            $order->add_order_note( __( '[Wunderkiste] Payment link sent manually.', 'ecommerce-wunderkiste' ), false, true );
        }
    }

    /**
     * Add the recovery message block to the customer invoice mail.
     *
     * @param WC_Order $order         Order object.
     * @param bool     $sent_to_admin Whether the mail goes to an admin.
     * @param bool     $plain_text    Whether this is the plain text version.
     * @param WC_Email $email         Email object.
     */
    public function add_custom_email_message( $order, $sent_to_admin, $plain_text, $email ) {
        if ( ! is_object( $email ) || 'customer_invoice' !== $email->id || $sent_to_admin ) {
            return;
        }

        $context = $order->get_meta( self::CONTEXT_META );

        // Only decorate mails this module actually triggered - a manually sent
        // invoice from the order screen should not claim a failed payment.
        if ( ! in_array( $context, array( 'pending', 'failed', 'manual' ), true ) ) {
            return;
        }

        $lang    = $this->get_language();
        $pay_url = $order->get_checkout_payment_url();
        $contact = $this->get_contact_email();

        if ( 'failed' === $context ) {
            $headline = ( 'en' === $lang )
                ? __( 'Your payment could not be completed.', 'ecommerce-wunderkiste' )
                : 'Deine Zahlung konnte leider nicht abgeschlossen werden.';
            $body     = ( 'en' === $lang )
                ? __( 'No worries, your order is saved. You can retry the payment here:', 'ecommerce-wunderkiste' )
                : 'Keine Sorge, deine Bestellung ist gespeichert. Du kannst die Zahlung hier nachholen:';
        } elseif ( 'pending' === $context ) {
            $headline = ( 'en' === $lang )
                ? __( 'Your order is still waiting for payment.', 'ecommerce-wunderkiste' )
                : 'Deine Bestellung wartet noch auf die Zahlung.';
            $body     = ( 'en' === $lang )
                ? __( 'It looks like the payment process was interrupted. You can complete it here:', 'ecommerce-wunderkiste' )
                : 'Es sieht so aus, als waere der Zahlungsvorgang unterbrochen worden. Du kannst ihn hier abschliessen:';
        } else {
            $headline = ( 'en' === $lang )
                ? __( 'Here is the payment link for your order.', 'ecommerce-wunderkiste' )
                : 'Hier ist der Zahlungslink zu deiner Bestellung.';
            $body     = ( 'en' === $lang )
                ? __( 'You can complete the payment here:', 'ecommerce-wunderkiste' )
                : 'Du kannst die Zahlung hier abschliessen:';
        }

        $button = ( 'en' === $lang ) ? __( 'Pay now', 'ecommerce-wunderkiste' ) : 'Jetzt bezahlen';

        if ( $plain_text ) {
            echo "\n" . esc_html( $headline ) . "\n" . esc_html( $body ) . "\n" . esc_url_raw( $pay_url ) . "\n\n";
            return;
        }

        ?>
        <div style="background:#fff3cd;color:#856404;padding:20px;border:1px solid #ffeeba;border-radius:5px;margin-bottom:20px;text-align:center;">
            <p style="font-size:16px;margin-top:0;"><strong><?php echo esc_html( $headline ); ?></strong></p>
            <p><?php echo esc_html( $body ); ?></p>
            <p>
                <a href="<?php echo esc_url( $pay_url ); ?>" style="background-color:#d9534f;color:#ffffff;display:inline-block;padding:12px 24px;text-decoration:none;border-radius:4px;font-weight:bold;margin:10px 0;">
                    <?php echo esc_html( $button ); ?>
                </a>
            </p>
            <p style="margin-top:15px;font-size:12px;color:#856404;border-top:1px solid #ffeeba;padding-top:10px;">
                <?php
                printf(
                    /* translators: %s: contact e-mail address */
                    esc_html( 'en' === $lang ? __( 'Questions? Contact us at: %s', 'ecommerce-wunderkiste' ) : 'Fragen? Kontaktiere uns unter: %s' ),
                    '<a href="mailto:' . esc_attr( $contact ) . '" style="color:#856404;">' . esc_html( $contact ) . '</a>'
                );
                ?>
            </p>
        </div>
        <?php
    }

    /**
     * Debug logger.
     *
     * @param string $message Message.
     */
    private function log_debug( $message ) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            if ( function_exists( 'wc_get_logger' ) ) {
                wc_get_logger()->debug( $message, array( 'source' => 'wpe-order-recovery' ) );
                return;
            }

            error_log( '[WPE Order Recovery] ' . $message ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
        }
    }
}
