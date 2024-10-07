<?php
/*
Plugin Name: Uptime Robot Status
Description: A plugin to display Uptime Robot monitor status using the Uptime Robot API.
Version: 1.0
Author: Your Name
*/

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}
function fetch_uptime_robot_data() {
    $api_key = 'your-uptime-robot-api-key';
    $url = 'https://api.uptimerobot.com/v2/getMonitors';

    // Setup POST data for the API request
    $postData = array(
        'api_key' => $api_key,
        'format' => 'json'
    );

    // Make an HTTP POST request
    $response = wp_remote_post($url, array(
        'body' => $postData
    ));

    if (is_wp_error($response)) {
        return 'Error fetching data from Uptime Robot.';
    }

    $data = json_decode(wp_remote_retrieve_body($response), true);

    // Process and display the data
    if (!empty($data['monitors'])) {
        $output = '<ul>';
        foreach ($data['monitors'] as $monitor) {
            $output .= '<li>';
            $output .= '<strong>' . esc_html($monitor['friendly_name']) . '</strong>: ';
            $output .= esc_html($monitor['status'] == 2 ? 'Up' : 'Down') . ' | ';
            $output .= 'Uptime: ' . esc_html($monitor['all_time_uptime_ratio']) . '%';
            $output .= '</li>';
        }
        $output .= '</ul>';
        return $output;
    } else {
        return 'No monitors available.';
    }
}

// Shortcode to display Uptime Robot status
function display_uptime_robot_status() {
    return fetch_uptime_robot_data();
}
add_shortcode('uptime_robot_status', 'display_uptime_robot_status');
