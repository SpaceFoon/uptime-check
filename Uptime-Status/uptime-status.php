<?php
/*
Plugin Name: Uptime Robot Status
Plugin source: https://github.com/SpaceFoon/uptime-check
Description: A WordPress plugin to display the status of your Uptime Robot monitors with uptimerobot.com's API.
Version: 1.0
Author: SpaceFoon
License: GPL2
License URI: https://www.gnu.org/licenses/gpl-2.0.html
*/

// Hook to add setting link under settings
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

    add_settings_field(
        'urs_monitor_id',
        __('Monitor ID', 'wordpress'),
        'urs_monitor_id_render',
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

// Render Monitor ID input
function urs_monitor_id_render() {
    $options = get_option('urs_settings');
    ?>
    <input type='text' name='urs_settings[urs_monitor_id]' value='<?php echo isset($options['urs_monitor_id']) ? esc_attr($options['urs_monitor_id']) : ''; ?>' placeholder="Enter Monitor ID">
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
    $monitor_id = isset($options['urs_monitor_id']) ? $options['urs_monitor_id'] : '';

    if (empty($api_key) || empty($monitor_id)) {
        return '<p class="error">Please configure your API Key and Monitor ID in the settings.</p>';
    }

    $api_url = 'https://api.uptimerobot.com/v2/getMonitors';

    $post_fields = array(
        'api_key' => $api_key,
        'monitor_ids' => $monitor_id, // Only fetch the specified monitor
        'format' => 'json',
        'logs' => 1,
        'response_times' => 1,
        'ssl' => 1,
        'all_time_uptime_ratio' => 1
    );

    $response = wp_remote_post($api_url, array('body' => $post_fields));

    if (is_wp_error($response)) {
        return '<p class="error">Unable to fetch data from Uptime Robot API: ' . esc_html($response->get_error_message()) . '</p>';
    }

    $data = json_decode(wp_remote_retrieve_body($response), true);

    if (!$data || isset($data['error'])) {
        return '<p class="error">Error fetching Uptime Robot data: ' . esc_html($data['error']['message']) . '</p>';
    }

    // Prepare the output HTML
    $output = '<div class="uptime-monitor">';

    // Accessing the specific monitor directly
    if (isset($data['monitors']) && count($data['monitors']) > 0) {
        $monitor = $data['monitors'][0]; // Get the first monitor only
        $status = ($monitor['status'] == 2) ? 'Up' : 'Down';
        $uptime_percentage = number_format($monitor['all_time_uptime_ratio'], 2) . '%';
        $ssl_info = isset($monitor['ssl']['product']) ? $monitor['ssl']['product'] . ' (Expires: ' . date('Y-m-d', $monitor['ssl']['expires']) . ')' : 'No SSL info';

        // Format the recent response times (last 5 entries)
        $response_times = isset($monitor['response_times']) ? array_slice($monitor['response_times'], 0, 5) : [];
        $response_time_avg = $response_time_min = $response_time_max = 0;

        if (!empty($response_times)) {
            $response_time_values = array_column($response_times, 'value');
            $response_time_avg = round(array_sum($response_time_values) / count($response_time_values));
            $response_time_min = min($response_time_values);
            $response_time_max = max($response_time_values);
        }

        // Recent notifications (last 5 entries)
        $logs = isset($monitor['logs']) ? array_slice($monitor['logs'], 0, 5) : [];
        $logs_list = '';
        foreach ($logs as $log) {
            $log_date = date('Y-m-d H:i:s', $log['datetime']);
            $log_type = ($log['type'] == 1) ? 'Up' : 'Down';
            $logs_list .= '<li>' . esc_html($log_date . ' - ' . $log_type) . '</li>';
        }
        if (empty($logs_list)) {
            $logs_list = '<li>No recent incidents available.</li>';
        }

        // Create the overall HTML structure
        $output .= '<div class="monitor-section">
                        <div class="monitor-header">
                            <h2>' . esc_html($monitor['friendly_name']) . '</h2>
                            <span class="status ' . strtolower($status) . '">' . esc_html($status) . '</span>
                        </div>
                        <div class="monitor-info">
                            <div class="info-block">
                                <h3>Current Status</h3>
                                <p>' . esc_html($status) . ' for ' . esc_html($monitor['uptime']) . '</p>
                            </div>
                            <div class="info-block">
                                <h3>Last Check</h3>
                                <p>' . date('Y-m-d H:i:s', $monitor['last_check']) . '</p>
                            </div>
                            <div class="info-block">
                                <h3>Last 24 Hours Uptime</h3>
                                <p>Uptime: ' . esc_html($uptime_percentage) . '</p>
                            </div>
                        </div>
                        <div class="ssl-info">
                            <h3>Domain & SSL</h3>
                            <p>' . esc_html($ssl_info) . '</p>
                        </div>
                        <div class="response-time">
                            <h3>Response Time</h3>
                            <div class="response-graph">
        <ul>';

        foreach ($response_times as $time) {
            $output .= '<li><div class="graph-bar" style="width:' . esc_attr($time['value']) . 'px;"></div></li>';
        }
        
        $output .= ' </ul></div>
                            <p>Average: ' . esc_html($response_time_avg) . ' ms | Min: ' . esc_html($response_time_min) . ' ms | Max: ' . esc_html($response_time_max) . ' ms</p>
                        </div>
                        <div class="recent-incidents">
                            <h3>Recent Incidents</h3>
                            <ul>' . $logs_list . '</ul>
                        </div>
                    </div>';
    } else {
        $output .= '<p class="error">No monitor found with the specified ID.</p>';
    }

    $output .= '</div>';

    return $output;
}

// Shortcode to display uptime status
function uptime_robot_shortcode() {
    return fetch_uptime_robot_data();
}
add_shortcode('uptime_robot_status', 'uptime_robot_shortcode');

// Custom styles to match the dark look
function uptime_robot_styles() {
    ?>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #121212;
            color: #f5f5f5;
        }
        
        .uptime-monitor {
            background-color: #1c1e22;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.3);
            margin-bottom: 20px; /* Add space between monitor blocks */
        }
        
        .monitor-section {
            border: 1px solid #444;
            border-radius: 5px;
            overflow: hidden;
            margin-bottom: 20px; /* Add space between sections */
        }

        .monitor-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background-color: #2a2d30;
            padding: 10px 15px;
        }

        .monitor-header h2 {
            margin: 0;
            font-size: 1.5em;
        }

        .status {
            font-weight: bold;
            padding: 5px 10px;
            border-radius: 5px;
            text-transform: uppercase;
            font-size: 0.9em;
            text-align: center; /* Center the status text */
            margin: 0 auto; /* Center alignment */
            width: fit-content; /* Adjust width based on content */
            animation-duration: 1s; /* Duration for the blinking effect */
            animation-iteration-count: infinite; /* Repeat animation */
        }

        .status.up {
            background-color: green;
            color: white;
            animation-name: blink-green; /* Blinking animation for up status */
        }

        .status.down {
            background-color: red;
            color: white;
            animation-name: blink-red; /* Blinking animation for down status */
        }

        @keyframes blink-green {
            0%, 100% {
                box-shadow: 0 0 10px green, 0 0 20px green;
                opacity: 1; /* Fully visible */
            }
            50% {
                box-shadow: 0 0 5px green, 0 0 10px green;
                opacity: 0.7; /* Dimmed */
            }
        }

        @keyframes blink-red {
            0%, 100% {
                box-shadow: 0 0 10px red, 0 0 20px red;
                opacity: 1; /* Fully visible */
            }
            50% {
                box-shadow: 0 0 5px red, 0 0 10px red;
                opacity: 0.7; /* Dimmed */
            }
        }

        .monitor-info, .ssl-info, .response-time, .recent-incidents {
            margin-top: 15px;
            padding: 10px 15px;
            background-color: #282c34;
            border: 1px solid #444; /* Box border for info sections */
            border-radius: 5px; /* Rounded corners for info sections */
        }

        .info-block {
            margin-bottom: 10px;
        }

        .response-graph {
            display: flex;
            align-items: center;
        }

        .graph-bar {
            height: 20px;
            background-color: #4caf50;
            margin-right: 5px;
            transition: width 0.5s ease;
        }

        .recent-incidents ul {
            list-style-type: none;
            padding: 0;
            margin: 0;
        }

        .recent-incidents li {
            padding: 5px 0;
            border-bottom: 1px solid #444;
        }
    </style>
    <?php
}
add_action('wp_head', 'uptime_robot_styles');

