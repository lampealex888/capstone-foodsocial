<?php
/**
 * Plugin Name: Recipe Recommender
 * Version: 1.0.0
 * Description: Get recipe recommendations!
 * Author: Alex Lampe and Shreya Kalla
 */

 // Exit if accessed directly
 if (!defined('ABSPATH')) {
    exit;
  }

// Register the plugin's shortcode
function recipe_recommender_shortcode($atts = array())
{
    wp_enqueue_script('recipe-recommender', plugin_dir_url(__FILE__) . 'recipe-recommender.js', array('jquery'), '1.0', true);
    wp_localize_script('recipe-recommender', 'recipe_recommender_ajax_obj', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('recipe_recommender_nonce')
    ));

    ob_start();
    ?>
    <div id="recipe-recommender-container">
        <label for="ingredients">Enter ingredients (comma-separated and one word):</label>
        <input type="text" id="ingredients" placeholder="rice, soy, sauce, egg">
        <button id="get-recipe" style="font-size: smaller;">Get Recipe!</button>
        <div id="recipe-result"></div>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('recipe_recommender', 'recipe_recommender_shortcode');

// Handle the AJAX request
function recipe_recommender_ajax_handler()
{
    check_ajax_referer('recipe_recommender_nonce', 'nonce');

    $ingredients = sanitize_text_field($_POST['ingredients']);

    $api_url = "http://34.28.80.121:5000/recipe?ingredients=$ingredients";

    $response = wp_remote_get($api_url);

    if (is_wp_error($response)) {
        $error_message = $response->get_error_message();
        wp_send_json_error($error_message);
    } else {
        $recipes = json_decode($response['body'], true);
        if (!empty($recipes)) {
            $recipe_list = "<h3>Recommended Recipes:</h3><ul>";
            foreach ($recipes as $recipe) {
                $ingredients_with_spaces = implode(', ', explode(',', $recipe['ingredients']));
                $recipe_list .= "<li><strong>{$recipe['recipe']}</strong> <a href='{$recipe['url']}' target='_blank'>(View Recipe)</a><br>Ingredients: {$ingredients_with_spaces}<br><span style='font-size: small;'>Score: {$recipe['score']}</span></li>";
            }
            $recipe_list .= "</ul>";
            wp_send_json_success($recipe_list);
        } else {
            wp_send_json_error("No recipes found for the provided ingredients.");
        }
    }
}
add_action('wp_ajax_recipe_recommender', 'recipe_recommender_ajax_handler');
add_action('wp_ajax_nopriv_recipe_recommender', 'recipe_recommender_ajax_handler');