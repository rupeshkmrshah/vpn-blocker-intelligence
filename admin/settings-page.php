<?php

add_action('admin_menu', function () {
    add_menu_page(
        'VPN Blocker Settings',
        'Color Mag',
        'manage_options',
        'color-mag',
        'vpn_blocker_settings_page',
		'dashicons-shield',     
        60 
    );
});

function vpn_blocker_settings_page() {
    // Handle form submission safely
    if (!empty($_POST['vpn_blocker_save_settings'])) {
        // Sanitize and save the API key
        update_option('vpn_blocker_api_key', sanitize_text_field($_POST['vpn_blocker_api_key'] ?? ''));

        // Sanitize and save the custom message
        update_option('vpn_blocker_message', sanitize_text_field($_POST['vpn_blocker_message'] ?? 'Access denied. VPN detected.'));

        // Sanitize and save the blocked pages
        $blocked_pages = isset($_POST['vpn_blocker_pages']) ? array_map('intval', (array) $_POST['vpn_blocker_pages']) : [];
        update_option('vpn_blocker_pages', $blocked_pages);


        // Sanitize and save custom page URLs
		update_option('vpn_blocker_custom_urls', sanitize_textarea_field($_POST['vpn_blocker_custom_urls'] ?? ''));
		
		// Sanitize and save the custom redirect URL
		update_option('vpn_blocker_redirect_url', esc_url_raw($_POST['vpn_blocker_redirect_url'] ?? ''));
		
 		// Save custom error page URL
		update_option('vpn_blocker_error_page', esc_url_raw($_POST['vpn_blocker_error_page'] ?? ''));
 		
		// Validate the custom error page URL
		$error_page_url = esc_url_raw($_POST['vpn_blocker_error_page'] ?? '');
		if ($error_page_url && !is_valid_url($error_page_url)) {
			echo '<div class="error"><p>The custom error page URL is not accessible. Please check the URL.</p></div>';
		} else {
			update_option('vpn_blocker_error_page', $error_page_url);
		}

        
        echo '<div class="updated"><p>Settings saved successfully.</p></div>';
    }

    // Fetch saved options
    $api_key = get_option('vpn_blocker_api_key', '');
    $message = get_option('vpn_blocker_message', 'Access denied. VPN detected.');
    $blocked_pages = get_option('vpn_blocker_pages', []);
    $custom_urls = get_option('vpn_blocker_custom_urls', '');



    // Fetch all pages and posts
    $pages = get_posts(['post_type' => ['page', 'post'], 'numberposts' => -1]);
    

    
    ?>
    <div class="wrap">
        <h1>VPN Blocker Settings</h1>
        <form method="post">
            <table class="form-table">
                <tr>
                    <th><label for="vpn_blocker_api_key">API Key</label></th>
                    <td><input type="text" name="vpn_blocker_api_key" value="<?php echo esc_attr($api_key); ?>" class="regular-text" /></td>
                </tr>
                <tr>
                    <th><label for="vpn_blocker_message">Custom Message</label></th>
                    <td><input type="text" name="vpn_blocker_message" value="<?php echo esc_attr($message); ?>" class="regular-text" /></td>
                </tr>
				<tr>
					<th><label for="vpn_blocker_error_page">Custom Error Page URL</label></th>
					<td>
						<input type="text" name="vpn_blocker_error_page" value="<?php echo esc_attr(get_option('vpn_blocker_error_page', '')); ?>" class="regular-text" />
						<p class="description">
							Enter the full URL of the custom error page (e.g., https://example.com/error-page). Users will be redirected here if blocked.
						</p>
					</td>
				</tr>
                <tr>
					<th><label for="vpn_blocker_pages">Blocked Pages</label></th>
					<td>
						<select name="vpn_blocker_pages[]" id="vpn_blocker_pages" multiple style="width: 100%;">
							<?php foreach ($pages as $page) : ?>
								<option value="<?php echo $page->ID; ?>" <?php selected(in_array($page->ID, $blocked_pages)); ?>>
									<?php echo esc_html($page->post_title); ?>
								</option>
							<?php endforeach; ?>
						</select>
						<p class="description">Search and select the pages or posts you want to block.</p>
					</td>
				</tr>
                <tr>
                    <th><label for="vpn_blocker_custom_urls">Custom Page URLs</label></th>
                    <td>
                        <textarea name="vpn_blocker_custom_urls" id="vpn_blocker_custom_urls" rows="5" style="width: 100%;"><?php echo esc_textarea($custom_urls); ?></textarea>
                        <p class="description">Enter custom URLs to block (one per line). Example: <code>/example</code>, <code>/example/sub-example</code>.</p>
                    </td>
                </tr>
            </table>
            <input type="hidden" name="vpn_blocker_save_settings" value="1" />
            <?php submit_button('Save Settings', 'primary'); ?>
        </form>
    </div>
    <?php
}

// Add submenu for whitelist and blacklist pages
add_action('admin_menu', function () {
    add_submenu_page(
        'color-mag',
        'Whitelist',
        'Whitelist',
        'manage_options',
        'vpn-blocker-whitelist',
        'vpn_blocker_whitelist_page'
    );
    add_submenu_page(
        'color-mag',
        'Blacklist',
        'Blacklist',
        'manage_options',
        'vpn-blocker-blacklist',
        'vpn_blocker_blacklist_page'
    );
});

// Function to display the whitelist page
function vpn_blocker_whitelist_page() {
    ?>
    <div class="wrap">
        <h1>Whitelist</h1>
        <form method="post">
            <table class="form-table">
                <tr>
                    <th><label for="vpn_blocker_whitelist">Whitelist IP Addresses</label></th>
                    <td>
                        <textarea name="vpn_blocker_whitelist" id="vpn_blocker_whitelist" rows="5" style="width: 100%;"><?php echo esc_textarea(get_option('vpn_blocker_whitelist', '')); ?></textarea>
                        <p class="description">Enter IP addresses to whitelist (one per line). Example: <code>192.168.1.1</code>, <code>192.168.1.2</code>.</p>
                    </td>
                </tr>
            </table>
            <input type="hidden" name="vpn_blocker_save_settings" value="1" />
            <?php submit_button('Save Settings', 'primary'); ?>
        </form>
    </div>
    <?php
}

// Function to display the blacklist page
function vpn_blocker_blacklist_page() {
    ?>
    <div class="wrap">
        <h1>Blacklist</h1>
        <form method="post">
            <table class="form-table">
                <tr>
                    <th><label for="vpn_blocker_blacklist">Blacklist IP Addresses</label></th>
                    <td>
                        <textarea name="vpn_blocker_blacklist" id="vpn_blocker_blacklist" rows="5" style="width: 100%;"><?php echo esc_textarea(get_option('vpn_blocker_blacklist', '')); ?></textarea>
                        <p class="description">Enter IP addresses to blacklist (one per line). Example: <code>192.168.1.1</code>, <code>192.168.1.2</code>.</p>
                    </td>
                </tr>
            </table>
            <input type="hidden" name="vpn_blocker_save_settings" value="1" />
            <?php submit_button('Save Settings', 'primary'); ?>
        </form>
    </div>
    <?php
}


// Save settings for whitelist, blacklist, and error page
add_action('admin_init', function () {
    if (isset($_POST['vpn_blocker_save_settings'])) {
        // Only update whitelist and blacklist if provided, and avoid overwriting them
        if (isset($_POST['vpn_blocker_whitelist'])) {
            update_option('vpn_blocker_whitelist', sanitize_textarea_field($_POST['vpn_blocker_whitelist']));
        }

        if (isset($_POST['vpn_blocker_blacklist'])) {
            update_option('vpn_blocker_blacklist', sanitize_textarea_field($_POST['vpn_blocker_blacklist']));
        }

        // Ensure the error page URL is updated correctly
        if (isset($_POST['vpn_blocker_error_page'])) {
            update_option('vpn_blocker_error_page', sanitize_text_field($_POST['vpn_blocker_error_page']));
        }

        // Ensure message text is updated correctly
        if (isset($_POST['vpn_blocker_message'])) {
            update_option('vpn_blocker_message', sanitize_textarea_field($_POST['vpn_blocker_message']));
        }
    }
});

// Register settings
add_action('admin_init', function () {
    register_setting('vpn_blocker_options', 'vpn_blocker_whitelist');
    register_setting('vpn_blocker_options', 'vpn_blocker_blacklist');
});