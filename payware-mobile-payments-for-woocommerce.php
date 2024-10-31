<?php
/**
 * Plugin Name: payware Mobile Payments for WooCommerce
 * Plugin URI:
 * Description: Receive payments from any payware enabled mobile app.
 * Version: 1.2.0
 * Author: payware
 * Author URI: https://payware.eu
 * Developer: payware
 * Developer URI:
 * Text Domain: payware-mobile-payments-for-woocommerce
 * Domain Path: /languages
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if (!@paywr_is_woocommerce_active()) {
    return false;
}

function paywr_is_woocommerce_active()
{
    static $active_plugins;
    if (!isset($active_plugins)) {
        $active_plugins = (array)get_option('active_plugins', array());
        if (is_multisite()) {
            $active_plugins = array_merge($active_plugins, get_site_option('active_sitewide_plugins', array()));
        }
    }
    return
        in_array('woocommerce/woocommerce.php', $active_plugins) ||
        array_key_exists('woocommerce/woocommerce.php', $active_plugins);
}

/*
 * Translates
 */
add_action('plugins_loaded', 'paywr_init');
function paywr_init()
{
    load_plugin_textdomain('payware-mobile-payments-for-woocommerce', false, dirname(plugin_basename(__FILE__)) . '/languages/');
}

/*
 * Creating a mobile payment page when activating the plugin
 */
register_activation_hook(__FILE__, 'paywr_plugin_activate');
function paywr_plugin_activate()
{

    $title = "payware Mobile Payment";
    $content = '<div id="qr_code_img" style="text-align: center;">[PAYWR-MOBILE-PAGE]</div>';
    $content .= '<div style="text-align: center; display: inline-block; position: relative; margin-top: -20px; margin-bottom: 5px; width: 100%;">';
    $content .= '<img style="display: inline-block;" src="' . plugins_url('assets/img/pulse-1s-200px.svg', __FILE__) . '" />';
    $content .= '</div>';
    $content .= '<b style="text-align: center; display: inline-block; position: relative; top: -30px; width: 100%; font-size: 16px; color: #565656;">' . __('Awaiting mobile payment', 'payware-mobile-payments-for-woocommerce') . '</b>';
    $content .= '<a href="javascript:history.back()" style="background: #d6d6d6;padding: 10px 15px;display: inline-block;margin-top: 21px;border-radius: 3px;color: #fff;width: 250px;max-width: 100%;font-size: 16px;text-decoration: none;cursor: pointer;top: -30px;position: relative;">' . __('Back to checkout', 'payware-mobile-payments-for-woocommerce') . '</a>';

    if (!post_exists($title)) {
        $post_id = wp_insert_post(array(
            'post_type' => 'page',
            'post_title' => $title,
            'post_content' => $content,
            'post_status' => 'publish',
            'post_author' => 1,
            'post_name' => 'mobile-payments',
        ));
    }
}

require_once dirname(__FILE__) . '/includes/class-paywr-payware-api.php';

/*
* This action hook registers payware PHP class as a WooCommerce payment gateway
*/
add_filter('woocommerce_payment_gateways', 'PAYWR_add_gateway_class');
function PAYWR_add_gateway_class($gateways)
{
    $gateways[] = 'WC_PAYWR_Gateway';
    return $gateways;
}

// registering/loading additional files
add_action('wp_enqueue_scripts', function () {
    // admin styles
    wp_enqueue_style('paywr_admin_css', plugins_url('assets/css/admin.css', __FILE__));
    // qr code generator
    wp_enqueue_script('qrcodegen', plugins_url('assets/js/qrcodegen.min.js', __FILE__));
    // frontend styles
    wp_enqueue_style('paywr_frontend_css', plugins_url('assets/css/frontend.css', __FILE__));
    // frontend scripts
    wp_enqueue_script('paywr_main_js', plugins_url('assets/js/main.js', __FILE__), array('jquery'));
    wp_enqueue_script('jquery.bind', plugins_url('assets/js/jquery.bind.js', __FILE__), array('jquery'));
    // countdown
    wp_enqueue_script('jquery.countdown', plugins_url('assets/js/countdown.min.js', __FILE__), array('jquery'));
});

/**
 * Adds plugin page links
 * 
 * @since 1.0.0
 * @param array $links all plugin links
 * @return array $links all plugin links + our custom links (i.e., "Settings")
 */
function paywr_plugin_links( $links ) {

	$plugin_links = array(
		'<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout&section=payware-mobile-payments-for-woocommerce' ) . '">' . __( 'Settings', 'payware-mobile-payments-for-woocommerce' ) . '</a>'
	);

	return array_merge( $plugin_links, $links );
}
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'paywr_plugin_links' );

/*
* Initializing the class itself, it is inside plugins_loaded action hook
*/

add_action('plugins_loaded', 'PAYWR_init_gateway_plugin');
function PAYWR_init_gateway_plugin() {
    if (isset($_GET['paywr_ajax_url'])) {
        echo paywr_ajax();
        die(200);
    }
    
    require_once dirname( __FILE__ ) . '/includes/class-paywr-gateway.php';
        
    new WC_PAYWR_Gateway();
}

// Mobile page short-code
add_shortcode('PAYWR-MOBILE-PAGE', 'PAYWR_MOBILE_page');
function PAYWR_MOBILE_page()
{

    if (isset($_GET['order_id'])) {
        $sanitized = sanitize_text_field(wp_unslash($_GET['order_id']));
        $order = wc_get_order($sanitized);
        if ($order) {
            $return_url = $order->get_checkout_order_received_url();
        } else {
            $return_url = home_url();
        }
    } else {
        $return_url = home_url();
    }

    echo '<div id="wc-paywr-mobile-form" class="wc-paywr-mobile-form">';

    do_action('paywr_qr_form_before', 'payware-mobile-payments-for-woocommerce'); // action

    $site_name = get_bloginfo('name') ? sanitize_text_field(wp_unslash(get_bloginfo('name'))) : " ";
    $order_id = sanitize_text_field(wp_unslash($_GET['order_id']));
    $payment_link = sanitize_text_field(wp_unslash($_GET['payment_link']));
    $amount = sanitize_text_field(wp_unslash($_GET['amount']));
    $currency = sanitize_text_field(wp_unslash($_GET['currency']));
    $paywr_options = get_option( 'woocommerce_payware-mobile-payments-for-woocommerce_settings', array() ); 
    $tempTtl = sanitize_text_field(wp_unslash($paywr_options['time_to_live']));
    $ttl = is_numeric($tempTtl) == 1
            && (int)$tempTtl >= 60
            && (int)$tempTtl <= 600
            ? $tempTtl
            : "600";

    echo '
            <div class="paywr-mobile-form-wrapper">
            <input type="hidden" id="paywr-shop" value="' . $site_name . '">
            <input type="hidden" id="paywr-order-id" value="' . $order_id . '">
            <input type="hidden" id="paywr-return-url" value="' . $return_url . '">
            <input type="hidden" id="paywr-code-text" value="' . $payment_link . '">
            <input type="hidden" id="paywr-order-amount" value="' . $amount . '">
            <input type="hidden" id="paywr-order-currency" value="' . $currency . '">
            <input type="hidden" id="paywr-ttl" value="' . $ttl . '">
            <input type="hidden" id="paywr-expired" value="' . __('Expired', 'payware-mobile-payments-for-woocommerce') . '">
            <input type="hidden" id="paywr-expires-in" value="' . __('Expires in', 'payware-mobile-payments-for-woocommerce') . '">
            <input type="hidden" id="paywr-declined" value="' . __('Declined', 'payware-mobile-payments-for-woocommerce') . '">
            <input type="hidden" id="paywr-failed" value="' . __('Failed', 'payware-mobile-payments-for-woocommerce') . '">
            <input type="hidden" id="paywr-payId" value="' . __('Payment ID', 'payware-mobile-payments-for-woocommerce') . '">
            <input type="hidden" id="paywr-total" value="' . __('Order total', 'payware-mobile-payments-for-woocommerce') . '">
            <input type="hidden" id="paywr-label" value="' . __('Confim with your favorite mobile payment app.', 'payware-mobile-payments-for-woocommerce') . '">
            <div class="paywr-tr-box">
                <div id="paywr-tr-data" class="paywr-tr-data">
                    <div>
                        <span id="shop" class="bold" style="font-size: x-large;"></span>
                    </div>
                    <div>
                        <span id="payId" class="paywr-lbl"></span>: <span id="tr-id"></span>
                        <img src="https://shop.payware.eu/wp-content/plugins/payware-mobile-payments-for-woocommerce/assets/img/copy-icon.svg" style="cursor: pointer; max-width: 15px;" onclick="copyPaymentId()" alt="Copy to Clipboard">
                    </div>
                    <div>
                        <span id="ttl" style="font-weight: 600;"></span>
                    </div>
                    <div>
                        <span id="total" class="paywr-lbl"></span>: <span id="amount" class="bold xx-large"></span> <span id="currency" class="bold"></span>
                    </div>
                    <div class="paywr-lbl">
                        <span id="label" class="text-cl"></span>
                    </div>
                </div>
                <div id="paywr-qrcode" class="qr_code_img"></div>
            </div>
            <a class="paywr-paynow-btn onlymob" target="_blank" href="payware://' . $payment_link . '">' . __('Pay Now', 'payware-mobile-payments-for-woocommerce') . '</a>
          </div>';

    do_action('paywr_qr_form_after', 'payware-mobile-payments-for-woocommerce'); // action

    echo '<div class="clear"></div>';
    // JavaScript function to copy payment ID to clipboard
	echo '
    <script>
        function copyPaymentId() {
            var paymentId = document.getElementById("tr-id");
            var tempInput = document.createElement("input");
            tempInput.value = paymentId.textContent;
            document.body.appendChild(tempInput);
            tempInput.select();
            document.execCommand("copy");
            document.body.removeChild(tempInput);
            var alertBox = document.createElement("div");
            alertBox.textContent = "Payment ID copied to clipboard: " + paymentId.textContent;
            alertBox.style.position = "fixed";
            alertBox.style.top = "10px";
            alertBox.style.left = "10px";
            alertBox.style.background = "rgba(0, 0, 0, 0.8)";
            alertBox.style.color = "white";
            alertBox.style.padding = "10px";
            alertBox.style.borderRadius = "5px";
            alertBox.style.zIndex = "9999";
            document.body.appendChild(alertBox);
            setTimeout(function() {
                document.body.removeChild(alertBox);
            }, 3000); // Remove alert after 3 seconds (adjust as needed)
        }
    </script>
';
}

// paywr ajax       
function paywr_ajax()
{
    if (empty($_GET['action'])) {
        return __('Action is missed', 'payware-mobile-payments-for-woocommerce');
    }
    if ($_GET['action'] == 'getorderpaidstatus') {
        $sanitized = sanitize_text_field(wp_unslash($_GET['order_id']));
        return paywr_order_is_paid($sanitized);
    }
}

// order is changed
function paywr_order_is_paid($id)
{
    $post = get_post($id);
    // $state = get_private_order_notes($post->ID);
    
    if (isset($post->post_status)) {
        switch ($post->post_status) {
            case 'wc-processing':
                return 1;
                break;
            case 'wc-completed':
                return 1;
                break;
            case 'wc-cancelled':
                return 2;
                break;
            case 'wc-failed':
                return 3;
                break;
            default:
                return 0;
                break;
            }
    } else {
        return __('Invalid order ID', 'payware-mobile-payments-for-woocommerce');
    }
}

// debug
function paywrDebug($data, $die = false)
{
    echo "<pre>";
    var_dump($data);
    echo "</pre>";
    if ($die) exit(200);
}

function paywr_is_mobilepage()
{
    return wc_post_content_has_shortcode('PAYWR-MOBILE-PAGE');
}

// This is logging function for internal use.
// It writes only plugin predefined output to a text file in wc-logs folder.
// It doesn't accept user input in any way.

function paywrLog($title, $data = false)
{
    $stringFromArrayWithKeys  = http_build_query($data,'',', ');
    $allowed_html = wp_kses_allowed_html( 'post' );
    $allowed_protocols = '';
    $output = wp_kses($stringFromArrayWithKeys ,$allowed_html,$allowed_protocols);
    
    $filename = wp_upload_dir()["basedir"] . '/wc-logs/paywr-log.txt';
    if (!$data) {
        file_put_contents($filename, date('d.m.Y/H:i:s') . ': ' . esc_attr($title) . PHP_EOL, FILE_APPEND);
    } else {
        file_put_contents($filename, date('d.m.Y/H:i:s') . ': ' . esc_attr($title) . PHP_EOL . var_export($output, true) . PHP_EOL, FILE_APPEND);
    }
}