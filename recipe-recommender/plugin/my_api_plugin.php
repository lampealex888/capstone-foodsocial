<?php
 /*
 Plugin Name: My API Plugin
 Description: Plugin to call an API from an HTTP website.
 Version: 1.0
 Author: Your Name
 */
 
 // Enqueue Scripts and Styles
 function my_api_plugin_scripts() {
     wp_enqueue_script('my-api-plugin-script', plugins_url('script.js', __FILE__), array('jquery'), '1.0', true);
     wp_enqueue_style('my-api-plugin-style', plugins_url('styles.css', __FILE__));
 }
 add_action('wp_enqueue_scripts', 'my_api_plugin_scripts');
 

 function my_api_shortcode() {
     // Call the API using wp_remote_get
     $response = wp_remote_get('Add API link');
     
     if (is_wp_error($response)) {
         return 'Error: ' . $response->get_error_message();
     }
     
     // Process the API response data
     $data = wp_remote_retrieve_body($response);
     
     // Return the processed data
     return '<div id="api-data">' . $data . '</div>';
 }
 add_shortcode('my_api', 'my_api_shortcode');