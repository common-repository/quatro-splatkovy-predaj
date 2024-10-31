<?php /** @noinspection SpellCheckingInspection */

/**
 * @package           wp-quatro
 *
 * @wordpress-plugin
 * Plugin Name:       Quatro splátkový predaj
 * Plugin URI:        #
 * Description:       Vitajte v Quatre, šikovné a rýchle nakupovanie na splátky. Plugin pre implementáciu Quatro splátkového predaja na váš e-shop obsahuje - Quatro informatívnu kalkulačku, Quatro splátkový predaj - platobnú metódu, Prehľad objednávok.
 * Version:           2.5.2
 * Author:            Eliaš ITSolutions s.r.o.
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       wp-quatro
 * Domain Path:       /languages
*/


if (!defined('WPINC')) {
    die;
}

define('WP_QUATRO', '2.5.2');
define('WP_QUATRO_ENDPOINT1', 'https://quatro.vub.sk');
define('WP_QUATRO_ENDPOINT2', 'https://quatroapi.vub.sk');


global $wpdb;

define('WP_QUATRO_ICONS', [
    'ba.png',
    'bb.png',
    'bc.png',
    'bd.png',
    'be.png',
    'bf.png',
    'I1093.png',
    'old.I1093.png'
]);


function activate_wp_quatro()
{

    if (!class_exists('woocommerce')) {
        die(__('Tento plugin vyžaduje k svojmu spusteniu WooCommerce plugin.', 'wp-quatro'));
    }

    // Default settings
    $options = get_option('wp_quatro_settings', [
        'wp_quatro_apikey' => '',
        'wp_quatro_oz' => '',
        'wp_quatro_endpoint1' => WP_QUATRO_ENDPOINT1,
        'wp_quatro_endpoint2' => WP_QUATRO_ENDPOINT2,
        'wp_quatro_calc_product' => 0,
        'wp_quatro_calc_cart' => 0,
        'wp_quatro_icon' => '',
        'wp_quatro_success_page' => 0,
        'wp_quatro_fail_page' => 0,
        'wp_quatro_enable_log' => 0,
        'wp_quatro_add_shipping' => 0,
    ]);

    if(empty($options['wp_quatro_endpoint1'])) {
        $options['wp_quatro_endpoint1'] = WP_QUATRO_ENDPOINT1;
    }
    if(empty($options['wp_quatro_endpoint2'])) {
        $options['wp_quatro_endpoint2'] = WP_QUATRO_ENDPOINT2;
    }

    update_option('wp_quatro_settings', $options);

    $pages = get_pages([
        'meta_key' => 'quatro_page',
        'meta_value' => 'success'
    ]);

    if (count($pages) == 0) {
        $post_data = [
            'post_title' => __('Úspešná platba Quatro', 'wp-quatro'),
            'post_content' => '<p>' . __('Platba bola úspešná', 'wp-quatro') . '</p>',
            'post_type' => 'page',
            'post_status' => 'publish',
            'meta_input' => ['quatro_page' => 'success']
        ];
        $id = wp_insert_post($post_data);
        $options = get_option('wp_quatro_settings');
        $options['wp_quatro_success_page'] = $id;
        update_option('wp_quatro_settings', $options);
    }

    $pages = get_pages([
        'meta_key' => 'quatro_page',
        'meta_value' => 'fail'
    ]);

    if (count($pages) == 0) {
        $post_data = [
            'post_title' => __('Neúspešná platba Quatro', 'wp-quatro'),
            'post_content' => '<p>' . __('Platba bola neúspešná', 'wp-quatro') . '</p>',
            'post_type' => 'page',
            'post_status' => 'publish',
            'meta_input' => ['quatro_page' => 'fail']
        ];
        $id = wp_insert_post($post_data);
        $options = get_option('wp_quatro_settings');
        $options['wp_quatro_fail_page'] = $id;
        update_option('wp_quatro_settings', $options);
    }

    global $wpdb;

    $sql = "CREATE TABLE IF NOT EXISTS `{$wpdb->base_prefix}_quatro_log` (
        id int NOT NULL auto_increment,
        ts datetime NOT NULL,
        message TEXT NOT NULL,
        type VARCHAR(255),
        PRIMARY KEY  (id)
    );";

    $wpdb->query($sql);

    // Clean up - security issue

    if (file_exists(__DIR__ . '/includes/request.log')) {
        unlink(__DIR__ . '/includes/request.log');
    }
    if (file_exists(__DIR__ . '/includes/response.log')) {
        unlink(__DIR__ . '/includes/response.log');
    }
    if (file_exists(__DIR__ . '/includes/api.log')) {
        unlink(__DIR__ . '/includes/api.log');
    }

}

function deactivate_wp_quatro()
{
}


function wp_quatro_menu_page()
{
    add_menu_page(
        __('Quatro splátkový predaj', 'wp-quatro'),
        __('Quatro', 'wp-quatro'),
        'manage_options',
        'wp_quatro_dashboard',
        'wp_quatro_dashboard_cb',
        plugins_url('images/favicon_32x32.png', __FILE__)
    );

    add_submenu_page(
        'wp_quatro_dashboard',
        __('Quatro nastavenie', 'wp-quatro'),
        __('Nastavenie', 'wp-quatro'),
        'manage_options',
        'wp_quatro_settings',
        'wp_quatro_settings_cb'
    );

    add_submenu_page(
        'wp_quatro_dashboard',
        __('Quatro LOG', 'wp-quatro'),
        __('LOG', 'wp-quatro'),
        'manage_options',
        'wp_quatro_log',
        'wp_quatro_log_cb'
    );

    global $submenu;
    if (isset($submenu['wp_quatro_dashboard']))
        $submenu['wp_quatro_dashboard'][0][0] = __('Nástenka', 'wp-quatro');
}


function wp_quatro_settings()
{
    register_setting('wp_quatro_pluginPage', 'wp_quatro_settings');

    add_settings_section(
        'wp_quatro_pluginPage_main',
        __('Nastavenie Quatro', 'wp-quatro'),
        'wp_quatro_settings_main_callback',
        'wp_quatro_pluginPage'
    );

    add_settings_field(
        'wp_quatro_oz',
        __('Číslo obchodníka:', 'wp-quatro'),
        'wp_quatro_settings_oz',
        'wp_quatro_pluginPage',
        'wp_quatro_pluginPage_main'
    );

    add_settings_field(
        'wp_quatro_apikey',
        __('API Klůč:', 'wp-quatro'),
        'wp_quatro_settings_apikey',
        'wp_quatro_pluginPage',
        'wp_quatro_pluginPage_main'
    );

    add_settings_field(
        'wp_quatro_endpoint1',
        __('API Endpoint 1:', 'wp-quatro'),
        'wp_quatro_settings_endpoint1',
        'wp_quatro_pluginPage',
        'wp_quatro_pluginPage_main'
    );

    add_settings_field(
        'wp_quatro_endpoint2',
        __('API Endpoint 2:', 'wp-quatro'),
        'wp_quatro_settings_endpoint2',
        'wp_quatro_pluginPage',
        'wp_quatro_pluginPage_main'
    );

    add_settings_field(
        'wp_quatro_calc_product',
        __('Kalkulátor produktu:', 'wp-quatro'),
        'wp_quatro_settings_calc_product',
        'wp_quatro_pluginPage',
        'wp_quatro_pluginPage_main',
        [
            'type' => 'checkbox',
        ]
    );

    add_settings_field(
        'wp_quatro_calc_cart',
        __('Kalkulátor košíku:', 'wp-quatro'),
        'wp_quatro_settings_calc_cart',
        'wp_quatro_pluginPage',
        'wp_quatro_pluginPage_main',
        [
            'type' => 'checkbox',
        ]
    );

    add_settings_field(
        'wp_quatro_icon',
        __('Ikona kalkulačky:', 'wp-quatro'),
        'wp_quatro_settings_icon',
        'wp_quatro_pluginPage',
        'wp_quatro_pluginPage_main'
    );

    add_settings_field(
        'wp_quatro_success_page',
        __('Stránka úspešné platby:', 'wp-quatro'),
        'wp_quatro_settings_success_page',
        'wp_quatro_pluginPage',
        'wp_quatro_pluginPage_main'
    );

    add_settings_field(
        'wp_quatro_fail_page',
        __('Stránka neúspešné platby:', 'wp-quatro'),
        'wp_quatro_settings_fail_page',
        'wp_quatro_pluginPage',
        'wp_quatro_pluginPage_main'
    );

    add_settings_field(
        'wp_quatro_enable_log',
        __('Zapnout log:', 'wp-quatro'),
        'wp_quatro_settings_enable_log',
        'wp_quatro_pluginPage',
        'wp_quatro_pluginPage_main',
        [
            'type' => 'checkbox',
        ]
    );

    add_settings_field(
        'wp_quatro_add_shipping',
        __('Povoliť počítanie dopravy v splátkach:', 'wp-quatro'),
        'wp_quatro_settings_enable_add_shipping',
        'wp_quatro_pluginPage',
        'wp_quatro_pluginPage_main',
        [
            'type' => 'checkbox',
        ]
    );
}

function wp_quatro_settings_main_callback()
{
}

function wp_quatro_settings_enable_log()
{
    $options = get_option('wp_quatro_settings');
    ?>
    <input type='checkbox' name='wp_quatro_settings[wp_quatro_enable_log]' value='1' <?php checked('1', isset($options['wp_quatro_enable_log']) ? $options['wp_quatro_enable_log'] : '0'); ?>>
    <?php
}

function wp_quatro_settings_apikey()
{
    $options = get_option('wp_quatro_settings');
    ?>
    <input type='text' name='wp_quatro_settings[wp_quatro_apikey]' value='<?= isset($options['wp_quatro_apikey']) ? $options['wp_quatro_apikey'] : ''; ?>' style='width:400px;'>
    <?php

}

function wp_quatro_settings_endpoint1()
{
    $options = get_option('wp_quatro_settings');
    ?>
    <span><i>URL bez lomky na konci</i></span><br>
    <input type='text' name='wp_quatro_settings[wp_quatro_endpoint1]' value='<?= isset($options['wp_quatro_endpoint1']) ? $options['wp_quatro_endpoint1'] : ''; ?>' style='width:400px;'>
    <?php

}

function wp_quatro_settings_endpoint2()
{
    $options = get_option('wp_quatro_settings');
    ?>
    <span><i>URL bez lomky na konci</i></span><br>
    <input type='text' name='wp_quatro_settings[wp_quatro_endpoint2]' value='<?= isset($options['wp_quatro_endpoint2']) ? $options['wp_quatro_endpoint2'] : ''; ?>' style='width:400px;'>
    <?php

}

function wp_quatro_settings_calc_product()
{
    $options = get_option('wp_quatro_settings');
    ?>
    <span><i>Aktivovat produktový kalkulátor</i></span><br>
    <input type='checkbox' name='wp_quatro_settings[wp_quatro_calc_product]' value='1' <?= checked('1', isset($options['wp_quatro_calc_product']) ? $options['wp_quatro_calc_product'] : 0 ) ?>>
    <?php

}

function wp_quatro_settings_calc_cart()
{
    $options = get_option('wp_quatro_settings');
    ?>
    <span><i>Aktivovat kalkulátor košíku</i></span><br>
    <input type='checkbox' name='wp_quatro_settings[wp_quatro_calc_cart]' value='1' <?= checked('1', isset($options['wp_quatro_calc_cart']) ? $options['wp_quatro_calc_cart'] : 0 ) ?>>
    <?php

}


function wp_quatro_settings_oz()
{
    $options = get_option('wp_quatro_settings');
    ?>
    <input type='text' name='wp_quatro_settings[wp_quatro_oz]' value='<?= isset($options['wp_quatro_oz']) ? $options['wp_quatro_oz'] : ''; ?>' style='width:400px;'>
    <?php

}

function wp_quatro_settings_enable_add_shipping()
{
    $options = get_option('wp_quatro_settings');
    ?>
    <input type='checkbox' name='wp_quatro_settings[wp_quatro_add_shipping]' value='1' <?php checked('1', $options['wp_quatro_add_shipping'] ?? '0'); ?>>
    <?php
}

function wp_quatro_settings_icon()
{
    $options = get_option('wp_quatro_settings');
    ?>
    <select id="quatro-icon" type='text' name='wp_quatro_settings[wp_quatro_icon]' style="width: 400px">
        <?php
        foreach (WP_QUATRO_ICONS as $icon) {
            if ($options['wp_quatro_icon'] == $icon) {
                ?>
                <option value="<?= $icon ?>" selected data-image="<?= plugins_url("images/$icon",__FILE__) ?>"></option>
                <?php
            } else {
                ?>
                <option value="<?= $icon ?>" data-image="<?= plugins_url("images/$icon",__FILE__) ?>"></option>
                <?php
            }
        }
        ?>
    </select>
    <script language="javascript">
        jQuery(document).ready(function (e) {
            try {
                jQuery("#quatro-icon").msDropDown();
            } catch(e) {
                alert(e.message);
            }
        });
    </script>
    <?php

}

function wp_quatro_load_scripts($hook)
{

    // create my own version codes
    $my_js_ver = date("ymd-Gis", filemtime(plugin_dir_path(__FILE__) . 'js/msdropdown/jquery.dd.js'));
    $my_css_ver = date("ymd-Gis", filemtime(plugin_dir_path(__FILE__) . 'css/msdropdown/dd.css'));

    //
    wp_enqueue_script('msdropdown_js', plugins_url('js/msdropdown/jquery.dd.js', __FILE__), array('jquery'), $my_js_ver);
    wp_register_style('msdropdown_css', plugins_url('css/msdropdown/dd.css', __FILE__), false, $my_css_ver);
    wp_enqueue_style( 'msdropdown_css' );
}



function wp_quatro_settings_success_page()
{
    /** @var WP_Post[] $pages */
    $pages = get_pages(array('meta_key' => 'quatro_page', 'meta_value' => 'success'));
    $options = get_option('wp_quatro_settings');
    ?>
    <select type='text' name='wp_quatro_settings[wp_quatro_success_page]' style='width:400px'>
        <?php
        foreach ($pages as $page) {
            if ($options['wp_quatro_success_page'] == $page->ID) {
                ?>
                <option value="<?= $page->ID ?>" selected><?= $page->post_title ?></option>
                <?php
            } else {
                ?>
                <option value="<?= $page->ID ?>"><?= $page->post_title ?></option>
                <?php
            }
        }
        ?>
    </select>
    <?php


}

function wp_quatro_settings_fail_page()
{
    /** @var WP_Post[] $pages */
    $pages = get_pages(array('meta_key' => 'quatro_page', 'meta_value' => 'fail'));
    $options = get_option('wp_quatro_settings');
    ?>
    <select type='text' name='wp_quatro_settings[wp_quatro_fail_page]' style='width:400px'>
        <?php
        foreach ($pages as $page) {
            if ($options['wp_quatro_fail_page'] == $page->ID) {
                ?>
                <option value="<?= $page->ID ?>" selected><?= $page->post_title ?></option>
                <?php
            } else {
                ?>
                <option value="<?= $page->ID ?>"><?= $page->post_title ?></option>
                <?php
            }
        }
        ?>
    </select>
    <?php

}


function wp_quatro_calculator_product_link()
{
    global $product;
    
    $options = get_option('wp_quatro_settings');

    if(isset($options['wp_quatro_calc_product']) && $options['wp_quatro_calc_product'] == 1) {
        $data = $product->get_data();
        $is_variable = $product->is_type('variable');
        $price = number_format(floatval($data['price']), 2, '.', '');

        if ($is_variable) {
            ?>
            <script type="text/javascript">
                jQuery(document).ready(function($) {
                    // On variant change, show/hide the custom data and image
                    $('form.variations_form').on('show_variation', function(event, variation) {
                        $('#quatro-calculator-link').remove();

                        if (variation.display_price >= 100) {
                            $('.wp-block-add-to-cart-form').prepend(
                            `<a id="quatro-calculator-link" target="_blank" href="<?= $options['wp_quatro_endpoint1'] . '/kalkulacka/' . $options['wp_quatro_oz'] . '?cenaTovaru=' ?>` + variation.display_price +`">` + 
                                `<img src="<?= plugins_url('images/' . (!empty($options['wp_quatro_icon']) ? $options['wp_quatro_icon'] : 'ba.png'), __FILE__) ?>">` +
                            `</a>`
                            );
                        }
                    });

                    $('form.variations_form').on('reset_data', function() {
                        $('#quatro-calculator-link').remove();
                    });
                });
            </script>
            <?php
        } else if ($price >= 100) {
            ?>
            <a id="quatro-calculator-link" target="_blank" href="<?= $options['wp_quatro_endpoint1'] . '/kalkulacka/' . $options['wp_quatro_oz'] . '?cenaTovaru=' . $price ?>">
                <img src="<?= plugins_url('images/' . (!empty($options['wp_quatro_icon']) ? $options['wp_quatro_icon'] : 'ba.png'), __FILE__) ?>">
            </a>
            <?php
        }
    }
}

function wp_quatro_calculator_cart_link()
{
    global $woocommerce;

    $total = 0;

    foreach ($woocommerce->cart->get_cart() as $cart_item_key => $values) {

        $_product = $values['data'];

        $sale_price = $_product->get_data()['price'];
        $price = ($sale_price) * $values['quantity'];
        $total += $price;
    }

    $options = get_option('wp_quatro_settings');

    if (isset($options['wp_quatro_add_shipping']) && $options['wp_quatro_add_shipping'] == 1) {
        // Získaj cenu aktuálnej dopravy
        $shipping_total = WC()->cart->get_shipping_total();
        $total += $shipping_total;
    }

    $total = number_format($total,2,'.','');
    if(isset($options['wp_quatro_calc_cart']) && $options['wp_quatro_calc_cart'] == 1 && $total >= 100) {
        ?>
        <a target="_blank" href="<?= $options['wp_quatro_endpoint1'] . '/kalkulacka/' . $options['wp_quatro_oz'] . '?cenaTovaru=' . $total ?>"><img
                    src="<?= plugins_url('images/' . (!empty($options['wp_quatro_icon']) ? $options['wp_quatro_icon'] : 'ba.png'), __FILE__) ?>"></a>
        <?php
    }
}

require_once __DIR__ . '/includes/QuatroLogger.php';

function wp_quatro_log_cb() {
    $options = get_option('wp_quatro_settings');

    if(isset($options['wp_quatro_enable_log']) && $options['wp_quatro_enable_log'] == 1) {
        $results = QuatroLogger::getRecords();
        ?>
        <h1><?= __('Logy transakcí', 'wp-quatro') ?></h1>
        <table>
            <tr>
                <td>#</td>
                <td><?= __('Čas', 'wp-quatro') ?></td>
                <td><?= __('Zpráva', 'wp-quatro') ?></td>
                <td><?= __('Typ', 'wp-quatro') ?></td>
            </tr>
            <?php
            foreach ($results as $result) {
                ?>
                <tr>
                    <td><?= $result['id'] ?></td>
                    <td><?= $result['ts'] ?></td>
                    <td><?= $result['message'] ?></td>
                    <td><?= $result['type'] ?></td>
                </tr>
                <?php
            }
            ?>
        </table>
        <?php
    }
}

function wp_quatro_dashboard_cb()
{
    ?>
    <h1>Vitajte v Quatre, šikovné a rýchle nakupovanie na splátky.</h1>
    <p>
        Plugin pre implementáciu Quatro splátkového predaja na váš e-shop obsahuje - <b>Quatro informatívnu kalkulačku, Quatro splátkový predaj - platobnú metódu, Prehľad objednávok.</b>
    </p>
    <p>
        Ak ste našim obchodným partnerom, vytvorili sme vám jedinečné prístupové údaje, ktoré by ste mali mať k dispozícií.
        V prípade ak svoje prístupové údaje nemáte, prípadne ste ich zabudli, kontaktujte vášho obchodného zástupcu.
    </p>
    <h2>Quatro informatívna kalkulačka</h2>
    <p>
        tlačidlo s preklikom, ktorým sa otvorí kalkulačka je umiestnený v detaile produktu, po kliknutí sa načíta Quatro kalkulačka so splátkovými produktmi.
    </p>
    <h2>Quatro splátkový predaj - platobná metóda</h2>
    <p>
        tlačidlo sa zobrazí ako jedna z možností platby v nákupnom košíku pred odoslaním objednávky. Po úspešnom a záväznom odoslaní objednávky je zákazník presmerovaný z košíka priamo alebo cez medzistránku do novej Quatro žiadosti o nákup na splátky.
        Prehľad objednávok - v prehľade objednávok sa zobrazujú všetky zrealizované objednávky, ktoré mali zvolenú platobnú metódu Quatro.
    </p>
    <h2>Servisná podpora</h2>
    <p>
        Servis zaručuje spoločnosť Eliaš ITSolutions s.r.o., v prípade chyby nás kontaktujte na emaile <a href="mailto:support@elias-itsolutions.sk">support@elias-itsolutions.sk</a>,
        ak je chyba spôsobená nekompatibilitou medzi modulmi, kontaktujte nás tiež, zašleme vám ponuku na zákaznícku úpravu modulu s hodinovou sadzbou 40 € hod.
    </p>
    <?php
}

function wp_quatro_settings_cb()
{
    ?>
    <h1><?= __('Quatro nastavenie', 'wp-quatro') ?></h1>
    <form action='options.php' method='post'>

        <?php
        settings_fields('wp_quatro_pluginPage');
        do_settings_sections('wp_quatro_pluginPage');
        submit_button(__('Uložit', 'wp-quatro'), 'primary', 'submit-wp-quatro', false);
        ?>

    </form>
    <?php
}

function wp_quatro_wc_payment_init()
{
    if ( ! class_exists( 'WC_Payment_Gateway' ) ) return;

    include_once( __DIR__ . '/includes/WpQuatroWooCommercePayment.php' );

    add_filter( 'woocommerce_payment_gateways', 'wp_quatro_add_gateway' );
    function wp_quatro_add_gateway( $methods ) {
        $methods[] = 'WpQuatroWooCommercePayment';
        return $methods;
    }
}

function wpquatro_ajax_callback()
{
    $options = get_option('wp_quatro_settings');
    $order_id = isset($_GET['cn']) ? sanitize_text_field($_GET['cn']) : false;
    $state = isset($_GET['state']) ? sanitize_text_field($_GET['state']) : false;
    $transaction = isset($_GET['id']) ? sanitize_text_field($_GET['id']) : false;
    $checksum = isset($_GET['hmacSign']) ? sanitize_text_field($_GET['hmacSign']) : false;

    if ($order_id && $state && $transaction && $checksum) {
        $saved_transaction = get_post_meta($order_id, 'quatro_transaction', true);

        $data = "cn=$order_id&id=$transaction&state=$state";

        if ($transaction == $saved_transaction && strtolower($checksum) == hash_hmac('sha1', $data, $options['wp_quatro_apikey'])) {
            $customer_order = new WC_Order($order_id);
            switch (strtolower($state)) {
                /*
                 * signed - Stav v objednavkach WP "Podpísaná zmluva - expedujte tovar"
                 * approved - Stav v objednavkach WP "Schválená - čaká na podpis zmluvy"
                 * canceled - Stav v objednavkach WP "Zrušená alebo zamietnutá žiadosť"
                 */
                case "signed":
                    $customer_order->add_order_note(__('Platba Quatro bola podpísaná zmluva - expedujte tovar', 'wp-quatro'));
                    $customer_order->update_status('processing', __('Platba Quatro, bola podpísaná zmluva - expedujte tovar', 'wp-quatro'));
                    break;
                case "approved":
                    $customer_order->add_order_note(__('Platba Quatro bola schválená - čaká na podpis zmluvy', 'wp-quatro'));
                    $customer_order->update_status('pending', __('Platba Quatro, bola schválená - čaká na podpis zmluvy', 'wp-quatro'));
                    break;
                case "canceled":
                    $customer_order->add_order_note(__('Platba Quatro bola zrušená alebo zamietnutá žiadosť', 'wp-quatro'));
                    $customer_order->update_status('failed', __('Platba Quatro, zrušená alebo zamietnutá žiadosť', 'wp-quatro'));
                    break;
            }

        } else {
            die('Bad authorization');
        }
    } else {
        die('Missing data');
    }

    die();
}


add_filter( 'single_template', 'add_wpquatro_failed_template', 99 );

function add_wpquatro_failed_template( $template ) {
    $options = get_option('wp_quatro_settings', ['wp_quatro_apikey' => '', 'wp_quatro_oz' => '', 'wp_quatro_url' => '', 'wp_quatro_icon' => '', 'wp_quatro_success_page' => 0, 'wp_quatro_fail_page' => 0]);
    if ( get_the_ID() == $options['wp_quatro_fail_page'] ) {
        return plugin_dir_path( __FILE__ ) . 'failed-template.php';
    }

    return $template;
}

add_action("wp_ajax_nopriv_wpquatro_ajax_callback", "wpquatro_ajax_callback");
add_action("wp_ajax_wpquatro_ajax_callback", "wpquatro_ajax_callback");
register_activation_hook(__FILE__, 'activate_wp_quatro');
register_deactivation_hook(__FILE__, 'deactivate_wp_quatro');
add_action('admin_init', 'wp_quatro_settings');
add_action('admin_menu', 'wp_quatro_menu_page');
add_action('woocommerce_before_add_to_cart_form', 'wp_quatro_calculator_product_link');
add_action('woocommerce_proceed_to_checkout', 'wp_quatro_calculator_cart_link');
add_action('plugins_loaded', 'wp_quatro_wc_payment_init');
add_action('admin_enqueue_scripts', 'wp_quatro_load_scripts');
