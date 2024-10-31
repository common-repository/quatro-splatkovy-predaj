<?php /** @noinspection PhpUndefinedFieldInspection */

/** @noinspection SpellCheckingInspection */


class WpQuatroWooCommercePayment extends WC_Payment_Gateway
{

    function __construct()
    {

        $this->id = "wp_wc__quatro_payment_plugin";
        $this->method_title = __("Quatro splátky platebná metoda", 'wp-quatro');
        $this->method_description = __("Quatro splátky platebná metoda pro WooCommerce", 'wp-quatro');
        $this->title = __("Quatro splátkový predaj", 'wp-quatro');
        $this->icon = null;
        $this->has_fields = false;
        $this->init_form_fields();
        $this->init_settings();

        foreach ($this->settings as $setting_key => $value) {
            $this->$setting_key = $value;
        }

        if (is_admin()) {
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        }
    }

    public function init_form_fields()
    {
        $this->form_fields = array(
            'enabled' => array(
                'title' => __('Povolit / Zakázat', 'wp-quatro'),
                'label' => __('Povolit platební bránu Quatro', 'wp-quatro'),
                'type' => 'checkbox',
                'default' => 'no',
            )
        );
    }

    public function process_payment($order_id)
    {
        global $woocommerce;
        include_once __DIR__ . '/QuatroApi.php';
        include_once __DIR__ . '/QuatroLogger.php';


        $options = get_option('wp_quatro_settings', ['wp_quatro_apikey' => '', 'wp_quatro_oz' => '', 'wp_quatro_url' => '', 'wp_quatro_icon' => '', 'wp_quatro_success_page' => 0, 'wp_quatro_fail_page' => 0]);
        $quatroApi = new QuatroApi();

        $customer_order = new WC_Order($order_id);

        $data = [
            "first_name" => $customer_order->billing_first_name,
            "last_name" => $customer_order->billing_last_name,
            "address" => $customer_order->billing_address_1,
            "city" => $customer_order->billing_city,
            "state" => $customer_order->billing_state,
            "zip" => $customer_order->billing_postcode,
            "country" => $customer_order->billing_country,
            "phone" => $customer_order->billing_phone,
            "email" => $customer_order->billing_email,
        ];

        $result = $quatroApi->requestPayment($data, $order_id, $woocommerce->cart->get_cart(), $this->prepareCallbackUrl($order_id));


        if (isset($result['redirectUrl']) && !isset($result['error'])) {
            update_post_meta($order_id, 'quatro_transaction', $result['id']);
            update_post_meta($order_id, 'quatro_status', 'waiting');
            $customer_order->add_order_note(__('Nová platba Quatro bola uspešně zpracována', 'wp-quatro'));
            $quatro_order_status = apply_filters('quatro_payment_order_status', 'on-hold', $customer_order);
            $customer_order->update_status($quatro_order_status, __('Nová platba Quatro, čaká na potvrdenie', 'wp-quatro'));
            $customer_order->payment_complete();
            $woocommerce->cart->empty_cart();
            return array(
                'result' => 'success',
                'redirect' => $result['redirectUrl']
            );
        } elseif(!is_null($result) && isset($result['error'])) {
            $page = $options['wp_quatro_fail_page'];
            $customer_order->update_status('failed', __('Nová platba Quatro, zamietnutá', 'wp-quatro'));
            $customer_order->add_order_note(__('Nová platba Quatro bola zamietnutá API', 'wp-quatro'));
            update_post_meta($order_id, 'quatro_status', 'failed');
            return array(
                'result' => 'failed',
                'redirect' => get_page_link($page).'?error='.$result['error'].'&validations='.implode(',',isset($result['validations']) ? $result['validations'] : []),
            );
        } else {
            $page = $options['wp_quatro_fail_page'];
            $customer_order->update_status('failed', __('Nová platba Quatro, zamietnutá', 'wp-quatro'));
            $customer_order->add_order_note(__('Nová platba Quatro bola zamietnutá API', 'wp-quatro'));
            update_post_meta($order_id, 'quatro_status', 'failed');
            return array(
                'result' => 'failed',
                'redirect' => get_page_link($page).'?error=Neznámá chyba'.'&validations=Prázná odpověď API',
            );
        }
    }

    public function validate_fields()
    {
        return true;
    }

    private function prepareCallbackUrl($order_id)
    {
        return admin_url('admin-ajax.php').'?action=wpquatro_ajax_callback';
    }


}
