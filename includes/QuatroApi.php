<?php /** @noinspection SpellCheckingInspection */


class QuatroApi
{

    /**
     * QuatroApi constructor.
     */
    public function __construct()
    {
    }

    public static function cleanup($str, $maxLength = 100, $regex = "/[^a-zA-Z0-6 \p{L}[:punct:]]/ui") {
        return substr(preg_replace($regex, '', $str), 0, $maxLength);
    }

    public function requestPayment($wc_billingAddress, $order_id, $cart, $callBackUrl)
    {
        $options = get_option('wp_quatro_settings');

        $apiKey = $options['wp_quatro_apikey'];
        $oz = $options['wp_quatro_oz'];

        $data = new stdClass();

        $data->orderNumber = $order_id;

        $data->applicant = new stdClass();
        $data->applicant->firstName = self::cleanup($wc_billingAddress['first_name'],50,"/[^a-zA-Z0-6 \p{L}.-]/ui");
        $data->applicant->lastName = self::cleanup($wc_billingAddress['last_name'], 80,"/[^a-zA-Z0-6 \p{L}.-]/ui");
        $data->applicant->email = self::cleanup($wc_billingAddress['email'], 51,"/[^A-Za-z0-9._@-]/");
        $data->applicant->mobile = self::cleanup($wc_billingAddress['phone'], 13,"/[^0-9]/");

        $data->applicant->permanentAddress = new stdClass();
        $data->applicant->permanentAddress->addressLine = self::cleanup($wc_billingAddress['address'], 100);
        $data->applicant->permanentAddress->city = self::cleanup($wc_billingAddress['city'], 80);
        $data->applicant->permanentAddress->zipCode = self::cleanup($wc_billingAddress['zip'], 6,"/[^0-9]/");
        $data->applicant->permanentAddress->country = self::cleanup($wc_billingAddress['country'], 19);

        $total = ((float) WC()->cart->get_subtotal() + WC()->cart->get_total_tax()) - (WC()->cart->get_cart_discount_total());
        
        if ($options['wp_quatro_add_shipping']) {
            $total += (float)WC()->cart->get_shipping_total();
        }
        
        $titles = [];

        $validations = [];

        if(empty($data->applicant->firstName)) {
            $validations[]='Krstné meno';
        }

        if(empty($data->applicant->lastName)) {
            $validations[]='Meno';
        }

        if(empty($data->applicant->email)) {
            $validations[]='Meno';
        }

        if(empty($data->applicant->mobil)) {
            $validations[]='Telefoné číslo';
        }

        if(empty($data->applicant->addressLine)) {
            $validations[]='Ulice';
        }

        if(empty($data->applicant->city)) {
            $validations[]='Mesto';
        }

        if(empty($data->applicant->zipCode)) {
            $validations[]='PSČ';
        }

        if(empty($data->applicant->country)) {
            $validations[]='Země';
        }

        if(
            empty($data->applicant->firstName) ||
            empty($data->applicant->lastName) ||
            empty($data->applicant->email) ||
            empty($data->applicant->mobile) ||
            empty($data->applicant->permanentAddress->addressLine) ||
            empty($data->applicant->permanentAddress->city) ||
            empty($data->applicant->permanentAddress->zipCode) ||
            empty($data->applicant->permanentAddress->country)
        ) {
            return ['error'=>'Chyba validacie', 'validations' => $validations];
        }

        foreach ($cart as $cart_item_key => $values) {

            $_product = $values['data'];

            $__product = wc_get_product($_product->get_id());

//            if ($_product->is_on_sale()) {
            $titles[] = self::cleanup($__product->get_title());
//            }

        }

        $data->subject = self::cleanup(implode(',', $titles), 80);
        $data->totalAmount = number_format(floatval($total), 2,'.','');
        $data->goodsAction = NULL;
        $data->callback = $callBackUrl;

        return $this->postJSON($data, $options['wp_quatro_endpoint2'], $oz, $apiKey);
    }

    private function postJSON($data, $url, $oz, $apiKey)
    {
        $args = array(
            'headers' => array('Authorization' => 'Basic ' . base64_encode("$oz:$apiKey"), 'Content-Type' => 'application/json'),
            'body' => json_encode($data)
        );
        $options = get_option('wp_quatro_settings');

        if (isset($options['wp_quatro_enable_log']) && $options['wp_quatro_enable_log'] == 1) {
            QuatroLogger::makeRecord(json_encode($args), 'request');
        }

        $response = wp_remote_post($url . '/applications', $args);


        $result = wp_remote_retrieve_body($response);

        if (isset($options['wp_quatro_enable_log']) && $options['wp_quatro_enable_log'] == 1) {
            QuatroLogger::makeRecord($result, 'response');
        }

        return json_decode($result, true);
    }

}
