<?php
/*
Plugin Name: Uptime Robot Status
Plugin source: https://github.com/SpaceFoon/uptime-check
Description: A WordPress plugin to display the status of your Uptime Robot monitors with uptimerobot.com's API.
Version: 1.0
Author: SpaceFoon
License:      GPL2
License URI:  https://www.gnu.org/licenses/gpl-2.0.html
*/

// Hook to add settings menu
add_action('admin_menu', 'urs_add_admin_menu');

// Hook to register settings
add_action('admin_init', 'urs_settings_init');

// Add admin menu
function urs_add_admin_menu() {
    add_options_page('Uptime Robot Status', 'Uptime Robot Status', 'manage_options', 'uptime_robot_status', 'urs_options_page');
}

// Initialize settings
function urs_settings_init() {
    register_setting('urs_pluginPage', 'urs_settings');

    add_settings_section(
        'urs_pluginPage_section',
        __('Configure Uptime Robot API Key', 'wordpress'),
        null,
        'uptime_robot_status'
    );

    add_settings_field(
        'urs_api_key',
        __('API Key', 'wordpress'),
        'urs_api_key_render',
        'uptime_robot_status', 
        'urs_pluginPage_section'
    );
}

// Render API key input
function urs_api_key_render() {
    $options = get_option('urs_settings');
    ?>
    <input type='text' name='urs_settings[urs_api_key]' value='<?php echo isset($options['urs_api_key']) ? esc_attr($options['urs_api_key']) : ''; ?>'>
    <?php
}

function urs_options_page() {
    ?>
    <form action='options.php' method='post'>
        <h2>Uptime Robot Status</h2>
        <?php
        settings_fields('urs_pluginPage');
        do_settings_sections('uptime_robot_status');
        submit_button();
        ?>
    </form>
    <?php
}

// Fetch uptime data from Uptime Robot API
function fetch_uptime_robot_data() {
    $options = get_option('urs_settings');
    $api_key = isset($options['urs_api_key']) ? $options['urs_api_key'] : '';

    if (empty($api_key)) {
        return '<div class="error">API key not set. Please configure it in the settings.</div>';
    }

    $url = 'https://api.uptimerobot.com/v2/getMonitors';
    $postData = array(
        'api_key' => $api_key,
        'format' => 'json'
    );

    // Make an HTTP POST request
    $response = wp_remote_post($url, array(
        'body' => $postData
    ));

    if (is_wp_error($response)) {
        return '<div class="error">Error: ' . esc_html($response->get_error_message()) . '</div>';
    }

    $data = json_decode($response['body'], true);
    error_log("API Response: " . print_r($data, true)); // Log the API response for debugging

    if (!isset($data['monitors'])) {
        return '<div class="error">No monitors found. Please check your API key.</div>';
    }

    // Handle and format the response data for display
    $output = '<ul>';
    foreach ($data['monitors'] as $monitor) {
        $status_class = ($monitor['status'] == 2) ? 'online' : 'offline'; // Assuming status 2 means online
        $output .= '<li class="' . esc_attr($status_class) . '">
                        <span class="status-light"></span>
                        ' . esc_html($monitor['friendly_name']) . ': ' . esc_html($monitor['status']) . '
                    </li>';
    }
    $output .= '</ul>';

    return $output; // Return the formatted output
}

// Add shortcode to display uptime status
function uptime_robot_shortcode() {
    return fetch_uptime_robot_data(); // Return the fetched data
}
add_shortcode('uptime_robot_status', 'uptime_robot_shortcode');

// Add custom styles for frontend display
function uptime_robot_styles() {
    ?>
    <style>
        .status-light {
            display: inline-block;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            margin-right: 5px;
            animation: blink 1.5s infinite;
        }
        .online {
            color: green;
        }
        .offline {
            color: red;
        }
        .offline .status-light {
            background-color: red;
        }
        .online .status-light {
            background-color: green;
        }
        @keyframes blink {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
        .error {
            color: red;
            font-weight: bold;
        }
    </style>
    <?php
}
add_action('wp_head', 'uptime_robot_styles'); // Enqueue styles in the header
