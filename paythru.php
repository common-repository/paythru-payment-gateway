<?php
/*
 * Plugin Name: Paythru Payment Gateway
 * Plugin URI: https://paythru.ng/
 * Description: WooCommerce payment gateway for Paythru, take debit/credit card payments on your store.
 * Author: Pethahiah
 * Author URI: https://github.com/Pethahiah
 * Version: 1.0.0
 */

/*
 * This action hook registers our PHP class as a WooCommerce payment gateway
 */
add_filter('woocommerce_payment_gateways', 'paythru_add_gateway_class');
function paythru_add_gateway_class($gateways)
{
    $gateways[] = 'WC_Paythru_Gateway'; // your class name is here
    return $gateways;
}

/*
 * The class itself, please note that it is inside plugins_loaded action hook
 */
add_action('plugins_loaded', 'paythru_init_gateway_class');
function paythru_init_gateway_class()
{

    class WC_Paythru_Gateway extends WC_Payment_Gateway
    {

        /**
         * Class constructor, more about it in Step 3
         */

        public function __construct()
        {

            $this->id = 'paythru'; // payment gateway plugin ID
            $this->icon = 'https://paythru.ng/images/paythru.png'; // URL of the icon that will be displayed on checkout page near your gateway name
            $this->has_fields = false; // in case you need a custom credit card form
            $this->method_title = 'Paythru Gateway';
            $this->method_description = 'Paythru provide merchants with the tools and services needed to accept online payments from local and international customers using Mastercard, Visa, Verve Cards and Bank Accounts. Sign up for a Paythru account, and get your API keys'; // will be displayed on the options page

            // gateways can support subscriptions, refunds, saved payment methods,
            // but in this tutorial we begin with simple payments
            $this->supports = array(
                'products',
            );

            // Method with all the options fields
            $this->init_form_fields();

            // Load the settings.
            $this->init_settings();
            $this->title = $this->get_option('title');
            $this->description = $this->get_option('description');
            $this->enabled = $this->get_option('enabled');
            $this->testmode = 'yes' === $this->get_option('testmode');
            $this->secret_key = $this->get_option('secret_key');
            $this->application_id = $this->get_option('application_id');
            $this->product_id = $this->get_option('product_id');
            $this->base_url = $this->testmode ? 'https://sandbox.paythru.ng/cardfree/transaction/create' : 'https://services.paythru.ng/cardfree/transaction/create';
            $this->transaction_url = $this->testmode ? 'https://sandbox.paythru.ng/cardfree/transaction/status/' : 'https://services.paythru.ng/cardfree/transaction/status/';

            // This action hook saves the settings
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));

            // You can also register a webhook here
            //http://zenithcoder.com/wc-api/callback/
            add_action('woocommerce_api_callback', array($this, 'process_webhooks'));

        }

        /**
         * Plugin options, we deal with it in Step 3 too
         */
        public function init_form_fields()
        {

            $this->form_fields = array(
                'enabled' => array(
                    'title' => 'Enable/Disable',
                    'label' => 'Enable paythru Gateway',
                    'type' => 'checkbox',
                    'description' => '',
                    'default' => 'no',
                ),
                'title' => array(
                    'title' => 'Title',
                    'type' => 'text',
                    'description' => 'This controls the title which the user sees during checkout.',
                    'default' => 'Credit Card',
                    'desc_tip' => true,
                ),
                'description' => array(
                    'title' => 'Description',
                    'type' => 'textarea',
                    'description' => 'This controls the description which the user sees during checkout.',
                    'default' => 'Pay with your credit card via our super-cool payment gateway.',
                ),
                'testmode' => array(
                    'title' => 'Test mode',
                    'label' => 'Enable Test Mode',
                    'type' => 'checkbox',
                    'description' => 'Place the payment gateway in test mode using test API keys.',
                    'default' => 'yes',
                    'desc_tip' => true,
                ),
                'secret_key' => array(
                    'title' => 'Secret',
                    'type' => 'text',
                ),
                'application_id' => array(
                    'title' => 'Application Id',
                    'type' => 'text',
                ),
                'product_id' => array(
                    'title' => 'Product Id',
                    'type' => 'text',
                    'description' => 'This is your product Id on PayThruTM Card-Free platform',
                ),
            );
        }

        /**
         * Admin Panel Options.
         */
        public function admin_options()
        {

            ?>

		<h2>
		<?php
esc_html(__('Paythru', 'paythru'));
            ?>
		<?php
if (function_exists('wc_back_link')) {
                wc_back_link(__('Return to payments', 'paythru'), admin_url('admin.php?page=wc-settings&tab=checkout'));
            }
            ?>
		</h2>

		<h4>
		<?php /* translators: %s: set webhook url */?>
        <strong><?php printf('set your webhook URL <a href="%1$s" target="_blank" rel="noopener noreferrer">here</a> to the URL below<span style="color: red"><pre><code>%2$s</code></pre></span>', 'https://paythru.ng', WC()->api_request_url('callback'));?></strong>
		</h4>

		<?php

            echo '<table class="form-table">';
            $this->generate_settings_html();
            echo '</table>';

        }

        /**
         * Get the return url (thank you page).
         *
         * @param WC_Order|null $order Order object.
         * @return string
         */
        public function get_return_url($order = null)
        {
            global $paythru_url;
            return $paythru_url;
        }

        /*
         * We're processing the payments here, everything about it is in Step 5
         */
        public function process_payment($order_id)
        {
            // $this->paytrhug_sddfd();
            global $woocommerce;

            // we need it to get any order detailes
            $order = wc_get_order($order_id);

            /*
             * Your API interaction could be built with wp_remote_post()
             */
            $time_unix = time();
            $transaction_reference = $time_unix . rand(10 * 45, 100 * 98);
            $args = array(
                "amount" => $order->order_total,
                "productId" => $this->product_id,
                "transactionReference" => $transaction_reference.'***'.$order_id,
                "paymentDescription" => "online payment",
                "paymentType" => 4,
                "displaySummary" => true,
                "redirectUrl" => get_home_url(),
                "Sign" => hash('sha512', $order->order_total . $this->secret_key),
            );

            //$response = wp_remote_post($this->base_url, $args );

            $response = wp_remote_post(
                $this->base_url,
                array(
                    'body' => $args,
                    'headers' => array(
                        'ApplicationId' => $this->application_id,
                        'Timestamp' => $time_unix,
                        'Signature' => hash('sha512', $time_unix . $this->secret_key),
                    ),
                )
            );

            sleep( 10 );
            
            $response_code = wp_remote_retrieve_response_code($response);
            $res = json_decode(wp_remote_retrieve_body($response), true);
            //var_dump($res);
            global $paythru_url;
            $paythru_url = $res['PayLink'];

            // Empty cart
            //  $woocommerce->cart->empty_cart();

            return array(
                'result' => 'success',
                'redirect' => $this->get_return_url($order),
            );

        }

        /*
         *  a webhook
         */
        /*  public function webhook()
        {

        $order = wc_get_order($_GET['id']);
        $order->payment_complete();
        $order->reduce_order_stock();

        update_option('webhook_debug', $_GET);
        }*/

        private function get_order_id_from_merchant_reference($reference)
        {
            return explode("***",$reference)[1];
        }

        /**
         * Process Webhook.
         */
        public function process_webhooks()
        {

            /*if ( ( strtoupper( sanitize_text_field(isset($_SERVER['REQUEST_METHOD'] ) ? $_SERVER['REQUEST_METHOD'] : '' ) ) != 'POST' )  ) {
            exit;
            }*/

            $json = file_get_contents('php://input');

            $event = json_decode($json);

            if ('1' == $event->status) {

                sleep(10);

                $order = wc_get_order($this->get_order_id_from_merchant_reference($event->merchantReference));

                if (!$order) {
                    exit;
                }

                http_response_code(200);

                if (in_array($order->get_status(), array('processing', 'completed', 'on-hold'))) {
                    exit;
                }

                $order_currency = method_exists($order, 'get_currency') ? $order->get_currency() : $order->get_order_currency();

                $currency_symbol = get_woocommerce_currency_symbol($order_currency);

                $order_total = $order->get_total();

                $amount_paid = $event->amount;

                $payment_currency = strtoupper($event->currency);

                $gateway_symbol = get_woocommerce_currency_symbol($payment_currency);

                // check if the amount paid is equal to the order amount.
                if ($amount_paid < $order_total) {

                    $order->update_status('on-hold', '');

                    add_post_meta($event->merchantReference, '_transaction_id', $event->merchantReference, true);
                    /* translators: amount error */
                    $notice = sprintf(__('Thank you for shopping with us.%1$sYour payment transaction was successful, but the amount paid is not the same as the total order amount.%2$sYour order is currently on hold.%3$sKindly contact us for more information regarding your order and payment status.', 'paythru'), '<br />', '<br />', '<br />');
                    $notice_type = 'notice';

                    // Add Customer Order Note.
                    $order->add_order_note($notice, 1);

                    // Add Admin Order Note.
                    /* translators: amount error */
                    $admin_order_note = sprintf(__('<strong>Look into this order</strong>%1$sThis order is currently on hold.%2$sReason: Amount paid is less than the total order amount.%3$sAmount Paid was <strong>%4$s (%5$s)</strong> while the total order amount is <strong>%6$s (%7$s)</strong>%8$s<strong>Paythru Transaction Reference:</strong> %9$s', 'paythru'), '<br />', '<br />', '<br />', $currency_symbol, $amount_paid, $currency_symbol, $order_total, '<br />', $event->merchantReference);
                    $order->add_order_note($admin_order_note);

                    function_exists('wc_reduce_stock_levels') ? wc_reduce_stock_levels($event->merchantReference) : $order->reduce_order_stock();

                    wc_add_notice($notice, $notice_type);

                    wc_empty_cart();

                } else {

                    if ($payment_currency !== $order_currency) {

                        $order->update_status('on-hold', '');

                        update_post_meta($event->merchantReference, '_transaction_id', $event->merchantReference);
                        /* translators: currency error */
                        $notice = sprintf(__('Thank you for shopping with us.%1$sYour payment was successful, but the payment currency is different from the order currency.%2$sYour order is currently on-hold.%3$sKindly contact us for more information regarding your order and payment status.', 'paythru'), '<br />', '<br />', '<br />');
                        $notice_type = 'notice';

                        // Add Customer Order Note.
                        $order->add_order_note($notice, 1);

                        // Add Admin Order Note.
                        /* translators: currency error */
                        $admin_order_note = sprintf(__('<strong>Look into this order</strong>%1$sThis order is currently on hold.%2$sReason: Order currency is different from the payment currency.%3$sOrder Currency is <strong>%4$s (%5$s)</strong> while the payment currency is <strong>%6$s (%7$s)</strong>%8$s<strong>Paythru Transaction Reference:</strong> %9$s', 'paythru'), '<br />', '<br />', '<br />', $order_currency, $currency_symbol, $payment_currency, $gateway_symbol, '<br />', $event->payThruReference);
                        $order->add_order_note($admin_order_note);

                        function_exists('wc_reduce_stock_levels') ? wc_reduce_stock_levels($event->merchantReference) : $order->reduce_order_stock();

                        wc_add_notice($notice, $notice_type);

                    } else {

                        $order->payment_complete($event->payThruReference);
                        /* translators: %s: transaction reference */
                        $order->add_order_note(sprintf(__('Payment via Paythru successful (Transaction Reference: %s)', 'paythru'), $event->payThruReference));

                        wc_empty_cart();
                    }
                }

                exit;
            }

            exit;

        }

    }
}
