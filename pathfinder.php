<?php
/*
 * Plugin Name: Pathfinder
 * Plugin URI: https://github.com/pathfinder-app/woocommerce-pathfinder
 * Description: Pathfinder connector for WooCommerce stores.
 * Version: 1.0.3
 * Author: Pathfinder
 * Author Email: dev@pathfindercommerce.com
 * Author URI: https://pathfindercommerce.com/
 * Requires at least: 5.2
 * Tested up to: 5.5.1
 * Text Domain: wc-pathfinder
 * Network: false
 * Requires PHP: 7.0
 * GitHub Plugin URI: https://github.com/pathfinder-app/woocommerce-pathfinder
 *
 * WooCommerce Pathfinder is distributed under the terms of the
 * GNU General Public License as published by the Free Software Foundation,
 * either version 2 of the License, or any later version.
 *
 * WooCommerce Pathfinder is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with WooCommerce Pathfinder. If not, see <http://www.gnu.org/licenses/>.
 *
 * @package WC_Pathfinder
 * @author Goran Kulimbanov / Pathfinder Commerce
 * @category Core
 */

if (!defined('ABSPATH')) {
    exit;
} // Exit if accessed directly

if (!defined('PATHFINDER_APP_URL')) {
    define('PATHFINDER_APP_URL', "https://woocommerce.pf-connected.com");
}
if (!defined('PATHFINDER_PLUGIN')) {
    define('PATHFINDER_PLUGIN', "pathfinder/pathfinder.php");
}

if (in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    if (!class_exists('WC_Pathfinder')) {
        class WC_Pathfinder
        {
            public function __construct()
            {
                /** MENU **/
                add_action('admin_menu', array($this, 'add_menu'));

                /** WEBHOOKS **/
                add_action('created_term', array(&$this, 'woocommerce_category_webhook'), 10, 3);
                add_action('edit_term', array(&$this, 'woocommerce_category_webhook'), 10, 3);
                add_action('woocommerce_add_to_cart', array(&$this, 'woocommerce_add_to_cart_webhook'), 10, 6);
                add_action('woocommerce_remove_cart_item', array(&$this, 'woocommerce_remove_cart_webhook'), 10, 2);
                add_action('woocommerce_after_checkout_form', array(&$this, 'woocommerce_checkout_webhook'), 10, 1);


                /** SCRIPT TAG **/
                add_action('rest_api_init', array(&$this, 'api_loaded'));
                add_filter('woocommerce_rest_api_get_rest_namespaces',
                    array(&$this, 'load_pathfinder_script_controller'));

                /** LOAD SCRIPTS **/
                add_action('wp_enqueue_scripts', array(&$this, 'load_pathfinder_scripts'));
            }

            public function add_menu()
            {
                $this->page_id = add_submenu_page(
                    'woocommerce',
                    __('Pathfinder', 'wc-pathfinder'),
                    __('Pathfinder', 'wc-pathfinder'),
                    'manage_woocommerce',
                    'wc-pathfinder',
                    array($this, 'pathfinder_admin_page_callback')
                );
            }

            public function pathfinder_admin_page_callback()
            {
                if ($this->is_connected()) {
                    $params = array(
                        'shop' => parse_url(home_url())['host'],
                        'tag' => get_option(Pathfinder_Pfconnected_Tags::PFCONNECTED_TAG),
                    );
                    $params['hmac'] = hash_hmac("sha1", http_build_query($params),
                        get_option(Pathfinder_Pfconnected_Tags::PFCONNECTED_TAG));
                    $redirect_url = PATHFINDER_APP_URL . '/login?' . http_build_query($params);
                } else {
                    $redirect_url = PATHFINDER_APP_URL . '?shop=' . home_url();
                }
                if (wp_redirect($redirect_url)) {
                    exit;
                }
            }

            public function woocommerce_remove_cart_webhook($cart_item_key)
            {
                global $woocommerce;
                do_action('woocommerce_add_to_cart_webhook',
                    [
                        "id" => $cart_item_key,
                        'cookie_cart' => $_COOKIE["cart"],
                        'woocommerce_cart' => isset($woocommerce->cart) ? $woocommerce->cart : '',
                    ]);
            }

            public function woocommerce_add_to_cart_webhook(
                $cart_item_key,
                $product_id,
                $quantity,
                $variation_id,
                $variation,
                $cart_item_data
            ) {
                global $woocommerce;
                if (empty($variation_id)) {
                    $variation_id = $product_id;
                }

                $_product = wc_get_product($variation_id);
                $price = $_product->get_price();
                do_action('woocommerce_add_to_cart_webhook',
                    [
                        "id" => $cart_item_key,
                        "product_id" => $product_id,
                        "quantity" => $quantity,
                        "variant_id" => $variation_id,
                        "price" => $price,
                        'variant' => $variation,
                        'cookie_cart' => $_COOKIE["cart"],
                        'woocommerce_cart' => isset($woocommerce->cart) ? $woocommerce->cart : '',
                        'cart_item_data' => $cart_item_data
                    ]);
            }

            public function woocommerce_checkout_webhook($cart_item_key)
            {
                global $woocommerce;
                do_action('woocommerce_checkout_view_webhook',
                    [
                        "id" => $cart_item_key,
                        "contact" => wp_get_current_user(),
                        'cookie_cart' => $_COOKIE["cart"],
                        'woocommerce_cart' => isset($woocommerce->cart) ? $woocommerce->cart : '',
                    ]);
            }

            public function woocommerce_category_webhook($term_id, $taxonomy_id = '', $taxonomy = '')
            {
                if ('product_cat' === $taxonomy) {
                    $term = get_term($term_id);
                    $term->id = $term_id;
                    $term->term_taxonomy_id = !empty($taxonomy_id) ? $taxonomy_id : null;
                    $thumb_id = get_term_meta($term_id, 'thumbnail_id', true);
                    $term->image = ['src' => wp_get_attachment_url($thumb_id)];
                    do_action('woocommerce_admin_product_cat_updated', [0 => $term]);
                }
            }

            public function api_loaded()
            {
                include_once('includes/class-pathfinder-script-controller.php');
            }

            public function load_pathfinder_script_controller($controllers)
            {
                $controllers['wc/v3']['script'] = 'Pathfinder_Script_Controller';

                return $controllers;
            }

            public function load_pathfinder_scripts()
            {
                include_once('includes/class-pathfinder-script-tags.php');
                $this->load_cart_scripts();
                $tags = Pathfinder_Script_Tags::getConstants();
                foreach ($tags as $tag) {
                    $options = get_option($tag);
                    if (!empty($options)) {
                        if ($tag == Pathfinder_Script_Tags::PATHFINDER_INLINE) {
                            wp_register_script($tag, '');
                            wp_add_inline_script($tag, $options);
                        }
                        wp_enqueue_script($tag, $options);
                    }
                }
            }

            private function load_cart_scripts()
            {
                if (!isset($_COOKIE["cart"])) {
                    setcookie("cart", $this->GUID());
                }
            }

            private function is_connected()
            {
                include_once('includes/class-pathfinder-pfconnected-tags.php');

                return !empty(get_option(Pathfinder_Pfconnected_Tags::PFCONNECTED_TAG));
            }

            private function GUID()
            {
                if (function_exists('com_create_guid') === true) {
                    return trim(com_create_guid(), '{}');
                }

                return sprintf('%04X%04X-%04X-%04X-%04X-%04X%04X%04X', mt_rand(0, 65535), mt_rand(0, 65535),
                    mt_rand(0, 65535), mt_rand(16384, 20479), mt_rand(32768, 49151), mt_rand(0, 65535),
                    mt_rand(0, 65535), mt_rand(0, 65535));
            }
        }

        $GLOBALS['WC_Pathfinder'] = new WC_Pathfinder();
    }
}

/** ACTIVATE OR DEACTIVATE PLUGIN PING **/
register_activation_hook(__FILE__, 'activate_plugin_confirm');
add_action('admin_init', 'pathfinder_activation_redirect');
register_deactivation_hook(__FILE__, 'deactivate_plugin_confirm');

/** UNINSTALL **/
register_uninstall_hook(__FILE__, 'uninstall_plugin_disconnect');

function activate_plugin_confirm()
{
    sync_status_ping(PATHFINDER_APP_URL . '/api/activate');
    if (
        (isset($_REQUEST['action']) && 'activate-selected' === $_REQUEST['action']) &&
        (isset($_POST['checked']) && count($_POST['checked']) > 1)) {
        return;
    }
    add_option('pathfinder_activation_redirect', wp_get_current_user()->ID);
}

function pathfinder_activation_redirect()
{
    if (intval(get_option('pathfinder_activation_redirect', false)) === wp_get_current_user()->ID) {
        delete_option('pathfinder_activation_redirect');
        wp_redirect(PATHFINDER_APP_URL . '?shop=' . home_url());
        exit;
    }
}

function deactivate_plugin_confirm()
{
    sync_status_ping(PATHFINDER_APP_URL . '/api/deactivate');
}

function sync_status_ping($url)
{
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POSTFIELDS, ['shop_url' => home_url()]);
    curl_exec($ch);
    curl_close($ch);
}

function uninstall_plugin_disconnect()
{
    include_once('includes/class-pathfinder-pfconnected-tags.php');
    include_once('includes/class-pathfinder-script-tags.php');

    if (empty(get_option(Pathfinder_Pfconnected_Tags::PFCONNECTED_TAG))) {
        return;
    }
    //prepare
    $params = array(
        'shop' => parse_url(home_url())['host'],
        'tag' => get_option(Pathfinder_Pfconnected_Tags::PFCONNECTED_TAG),
    );
    $params['hmac'] = hash_hmac("sha1", http_build_query($params),
        get_option(Pathfinder_Pfconnected_Tags::PFCONNECTED_TAG));
    do_action('woocommerce_uninstall_plugin_webhook', $params);

    //remove all script tags
    $tags = Pathfinder_Script_Tags::getConstants();
    foreach ($tags as $tag) {
        delete_option($tag);
    }
    delete_option(Pathfinder_Pfconnected_Tags::PFCONNECTED_TAG);
}
