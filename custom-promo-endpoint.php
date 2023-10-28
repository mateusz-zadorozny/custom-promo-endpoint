<?php
/**
 * Plugin Name:     Custom Promo Endpoint
 * Plugin URI:      https://mpress.cc
 * Description:     Creates an endpoint for promo codes, requires Meta Box extended module: MB Settings Page 
 * Author:          Mateusz Zadorozny
 * Author URI:      https://mpress.cc
 * Text Domain:     custom-promo-endpoint
 * Domain Path:     /languages
 * Version:         0.1.0
 *
 * @package         Custom_Promo_Endpoint
 */

// define Settings Page
add_filter('mb_settings_pages', 'custom_promo_sets_settings');

function custom_promo_sets_settings($settings_pages)
{
    $settings_pages[] = [
        'menu_title' => __('Custom Promo Sets', 'custom-promo-sets'),
        'id' => 'custom-promo-sets',
        'position' => 14,
        'parent' => 'woocommerce-marketing',
        'icon_font_awesome' => 'fa-percent fa-solid',
        'capability' => 'edit_products',
        'style' => 'no-boxes',
        'columns' => 1,
        'icon_url' => 'fa-percent fa-solid',
    ];

    return $settings_pages;
}

// fields for Settings Page
add_filter('rwmb_meta_boxes', 'custom_endpoint_fields');

function custom_endpoint_fields($meta_boxes)
{
    $prefix = '';

    $meta_boxes[] = [
        'title' => __('Custom endpoint fields', 'custom-endpoint'),
        'settings_pages' => ['custom-promo-sets'],
        'fields' => [
            [
                'name' => __('Promo set', 'custom-endpoint'),
                'id' => $prefix . 'promo_set',
                'type' => 'group',
                'clone' => true,
                'fields' => [
                    [
                        'type' => 'heading',
                        'name' => __('Promo set', 'custom-endpoint'),
                    ],
                    [
                        'name' => __('Products', 'custom-endpoint'),
                        'id' => $prefix . 'products',
                        'type' => 'post',
                        'post_type' => ['product'],
                        'field_type' => 'select_advanced',
                        'clone' => true,
                        'columns' => 6,
                    ],
                    [
                        'name' => __('Pcs', 'custom-endpoint'),
                        'id' => $prefix . 'pcs',
                        'type' => 'number',
                        'clone' => true,
                        'columns' => 6,
                    ],
                    [
                        'name' => __('Endpoint url slug', 'custom-endpoint'),
                        'id' => $prefix . 'endpoint',
                        'type' => 'text',
                        'columns' => 6,
                    ],
                    [
                        'name' => __('Coupon for promo', 'custom-endpoint'),
                        'id' => $prefix . 'coupon_for_promo',
                        'type' => 'post',
                        'post_type' => ['shop_coupon'],
                        'field_type' => 'select_advanced',
                        'columns' => 6,
                    ],
                    [
                        'type' => 'divider',
                        'save_field' => false,
                    ],
                ],
            ],
            [
                'name' => __('Flush the permalinks', 'custom-endpoint'),
                'id' => $prefix . 'flush_permalinks',
                'type' => 'button',
                'label_description' => __('Flush the permalinks after adding new endpoint!', 'custom-endpoint'),
                'std' => 'Flush now',
                'save_field' => false,
            ],
        ],
    ];

    return $meta_boxes;
}

function apply_promo_on_custom_endpoint()
{
    // Get the current endpoint (will be empty if not on a defined endpoint)
    $current_endpoint = get_query_var('promo_endpoint');

    // Get the custom promo sets option
    $promo_sets_option = get_option('custom-promo-sets');

    // Check if the option exists and has the 'promo_set' key
    if (!is_array($promo_sets_option) || !array_key_exists('promo_set', $promo_sets_option) || empty($promo_sets_option['promo_set'])) {
        return; // Exit if not set, not an array, or empty
    }

    $promo_sets = $promo_sets_option['promo_set'];

    // Loop through each promo set
    foreach ($promo_sets as $set) {
        // Check if the current endpoint matches a defined endpoint in the promo sets
        if ($set['endpoint'] === $current_endpoint) {
            global $woocommerce;

            // Empty the cart
            $woocommerce->cart->empty_cart();

            // Loop through products in the current promo set
            foreach ($set['products'] as $index => $product_id) {
                // Get the quantity (default to 1 if not set)
                $quantity = isset($set['pcs'][$index]) ? intval($set['pcs'][$index]) : 1;
                // Add product to the cart
                $woocommerce->cart->add_to_cart($product_id, $quantity);
            }

            // Apply the coupon
            if (isset($set['coupon_for_promo'])) {
                $coupon_post = get_post($set['coupon_for_promo']);
                if ($coupon_post) {
                    $woocommerce->cart->apply_coupon($coupon_post->post_name);
                }
            }

            // Redirect to the cart (optional)
            wp_safe_redirect(wc_get_cart_url());
            exit;
        }
    }
}
add_action('template_redirect', 'apply_promo_on_custom_endpoint');

function custom_promo_rewrite_rule()
{
    // Get the custom promo sets option
    $promo_sets_option = get_option('custom-promo-sets');

    // Check if the option exists and has the 'promo_set' key
    if (!is_array($promo_sets_option) || !array_key_exists('promo_set', $promo_sets_option) || empty($promo_sets_option['promo_set'])) {
        return; // Exit if not set, not an array, or empty
    }

    $promo_sets = $promo_sets_option['promo_set'];

    // Create an array of all endpoints
    $endpoints = array_map(function ($set) {
        return $set['endpoint'];
    }, $promo_sets);

    // Create a regex pattern to match only our custom promo endpoints
    $pattern = '^(' . implode('|', $endpoints) . ')/?$';

    add_rewrite_rule($pattern, 'index.php?promo_endpoint=$matches[1]', 'top');
}
add_action('init', 'custom_promo_rewrite_rule');

// Add 'promo_endpoint' as a recognized query var
function custom_promo_query_vars($vars)
{
    $vars[] = 'promo_endpoint';
    return $vars;
}
add_filter('query_vars', 'custom_promo_query_vars');

function enqueue_custom_admin_script()
{
    wp_enqueue_script('custom-admin-script', plugin_dir_url(__FILE__) . 'js/admin.js', array('jquery'), '1.0.0', true);

    // Add localized variables to the script
    wp_localize_script(
        'custom-admin-script',
        'ajax_params',
        array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('flush_permalinks_nonce'),
        )
    );
}
add_action('admin_enqueue_scripts', 'enqueue_custom_admin_script');

// AJAX handler
function handle_flush_permalinks()
{
    // Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'flush_permalinks_nonce')) {
        wp_send_json_error();
        return;
    }

    // Flush the permalinks
    flush_rewrite_rules();

    wp_send_json_success();
}
add_action('wp_ajax_flush_permalinks', 'handle_flush_permalinks');
