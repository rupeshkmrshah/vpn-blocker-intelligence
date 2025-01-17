<?php
/**
 * Plugin Name: Color Mag
 * Description: Block VPN users from accessing specific pages or posts using the ProxyCheck.io API.
 * Version: 1.6
 * Author: Rupesh Shah
 */

if (!defined('ABSPATH')) exit; // Exit if accessed directly

// Include necessary files
require_once plugin_dir_path(__FILE__) . 'includes/ip-logger.php';
require_once plugin_dir_path(__FILE__) . 'includes/api-handler.php';

// Add settings page
require_once plugin_dir_path(__FILE__) . 'admin/settings-page.php';

// Enqueue frontend scripts
add_action('wp_enqueue_scripts', function () {
    wp_enqueue_script('vpn-blocker-script', plugin_dir_url(__FILE__) . 'js/blocker.js', [], '1.0', true);
});

// Hook to check VPN status on the frontend
add_action('template_redirect', 'vpn_blocker_check_vpn');

function vpn_blocker_check_vpn() {

    // Ensure this code is not accessed directly
    if (!defined('ABSPATH')) exit;

    // Exclude admin users
    if (current_user_can('manage_options')) {
        vpn_blocker_log_ip($_SERVER['REMOTE_ADDR'], 'Allowed (Admin User)');
        return;
    }

    // Only apply on specific pages or posts
    $blocked_ids = get_option('vpn_blocker_pages', []);
    if (!is_singular() || !in_array(get_the_ID(), $blocked_ids)) {
        return;
    }

    $visitor_ip = $_SERVER['REMOTE_ADDR'];
    $api_key = get_option('vpn_blocker_api_key');

    if (!$api_key) return;

    // Get the whitelist and blacklist IP addresses
    $whitelist_ips = explode("\n", get_option('vpn_blocker_whitelist', ''));
    $blacklist_ips = explode("\n", get_option('vpn_blocker_blacklist', ''));

    // Check if the IP address is in the whitelist
    if (in_array($visitor_ip, $whitelist_ips)) {
        // Allow access if the IP address is in the whitelist
        vpn_blocker_log_ip($visitor_ip, 'Allowed (Whitelist)');
        return;
    }

    // Check if the IP address is in the blacklist (even if the user is not on VPN)
    if (in_array($visitor_ip, $blacklist_ips)) {
		$error_page_url = get_option('vpn_blocker_error_page', '');
        // Block access if the IP address is in the blacklist
        vpn_blocker_log_ip($visitor_ip, 'Blocked (Blacklist)');
		//wp_redirect($error_page_url);
		//exit;
        wp_die(esc_html(get_option('vpn_blocker_message', 'Access denied. (IP Blocked)')));

    }

    // Check if the visitor is using a VPN
    $is_vpn = vpn_blocker_check_ip($visitor_ip, $api_key);

    // Start Whitelist & Blacklist IP Feature (Handling VPN and Non-VPN Users)
    if ($is_vpn) {
        $custom_message = get_option('vpn_blocker_message', 'Access denied. VPN detected.');
        vpn_blocker_log_ip($visitor_ip, 'Blocked');

        // Get the custom error page URL from settings
        $error_page_url = get_option('vpn_blocker_error_page', '');

        if ($error_page_url) {
            // Validate the error page URL (check if the page exists)
            if (!is_valid_url($error_page_url)) {
                // If the error page URL is broken, show the custom message instead
                wp_die($custom_message);
            }

            // If the error page is valid, redirect the user
            wp_redirect($error_page_url);
            exit;
        }

        // If no custom error page is set, show the default message
        wp_die($custom_message);
    } else {
        // If not using VPN, check for Blacklist
        if (in_array($visitor_ip, $blacklist_ips)) {
            // Block access if the IP address is in the blacklist
            vpn_blocker_log_ip($visitor_ip, 'Blocked (Blacklist)');
            wp_die(esc_html(get_option('vpn_blocker_message', 'Access denied. (IP Blocked)')));
        }

        // If no VPN and not in blacklist, allow access
        vpn_blocker_log_ip($visitor_ip, 'Allowed');
    }
}

// Helper function to validate URL (check if it returns a valid response)
function is_valid_url($url) {
    $response = wp_remote_get($url, ['timeout' => 10]);

    // Check if the response code is 200 (OK)
    if (is_wp_error($response)) {
        return false;
    }

    $response_code = wp_remote_retrieve_response_code($response);
    return $response_code === 200;
}

// Search and select page functionality
add_action('admin_enqueue_scripts', function ($hook) {
    // Load Select2 only on the VPN Blocker settings page
    if ($hook === 'toplevel_page_vpn-blocker') {
        // Enqueue Select2 styles and scripts
        wp_enqueue_style('select2', 'https://cdnjs.cloudflare.com/ajax/libs/select2/4.1.0-beta.1/css/select2.min.css');
        wp_enqueue_script('select2', 'https://cdnjs.cloudflare.com/ajax/libs/select2/4.1.0-beta.1/js/select2.min.js', ['jquery'], null, true);

        // Initialize Select2 for the blocked pages field
        wp_add_inline_script('select2', '
            jQuery(document).ready(function($) {
                $("#vpn_blocker_pages").select2({
                    placeholder: "Search and select pages to block",
                    allowClear: true
                });
            });
        ');
    }
});

// Custom page URL blocking
add_action('template_redirect', function () {
    // Get the custom URLs to block
    $custom_urls = explode("\n", get_option('vpn_blocker_custom_urls', ''));
    $current_url = home_url($_SERVER['REQUEST_URI']); // Current URL

    // Check if the current URL matches a blocked custom URL
    foreach ($custom_urls as $url) {
        $url = trim($url);
        if (!empty($url) && strpos($current_url, $url) !== false) {
            // Get the user's IP address
            $visitor_ip = $_SERVER['REMOTE_ADDR'];
            $api_key = get_option('vpn_blocker_api_key');

            if (!$api_key) return;

            // Check if the visitor is using a VPN
            $is_vpn = vpn_blocker_check_ip($visitor_ip, $api_key);

            // If the visitor is using a VPN, block access and show the custom message
            if ($is_vpn) {
                wp_die(esc_html(get_option('vpn_blocker_message', 'Access denied. VPN detected.')));
            }

            // If not using a VPN, check for IP blockages
            $whitelist_ips = explode("\n", get_option('vpn_blocker_whitelist', ''));
            $blacklist_ips = explode("\n", get_option('vpn_blocker_blacklist', ''));

            // Check whitelist first
            if (in_array($visitor_ip, $whitelist_ips)) {
                return; // Allow access for whitelisted IPs
            }

            // Check blacklist after whitelist
            if (in_array($visitor_ip, $blacklist_ips)) {
                wp_die(esc_html(get_option('vpn_blocker_message', 'Access denied. (IP Blocked)')));
            }

            // If neither VPN nor IP blockages, allow access
            return;
        }
    }
});
