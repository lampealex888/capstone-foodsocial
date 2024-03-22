<?php
/*
   Plugin Name: Jon Daley Notifications
   Description: Allow registered users to receive notifications.
   Version: 1.0
   Author: Wafik Tawfik
   Author URI: http://wafik.world
   License: GPLv2 or later
   License URI: http://www.gnu.org/licenses/gpl-2.0.html
 */

# steps to follow:
/*
 1- Create the notification table.
 2- Create the notification endpoints.
 3- Trigger the notification endpoints based on the behavior we would like to achieve.
 Notes: Using the service worker doesn't look like it's going to work.
 */

define('NOTIFICATION_TABLE', 'notifications');
define('JONDALEY_NOTIFICATION_PLUGIN_DIR', WP_PLUGIN_DIR . '/' . basename(dirname(__FILE__)) . '/');
require_once(JONDALEY_NOTIFICATION_PLUGIN_DIR . 'notification.php');

register_activation_hook(__FILE__, 'jondaley_notification_install');
register_uninstall_hook(__FILE__, 'jondaley_notification_drop');

/* Begin table creation, deletion */
// ------------------------------------------------------------------------------------------------------
function jondaley_notification_install()
{
  global $wpdb;
  $table_name = NOTIFICATION_TABLE;

  $sql = "CREATE TABLE IF NOT EXISTS $table_name (
    id BIGINT NOT NULL AUTO_INCREMENT,
    user_id BIGINT NOT NULL,
    type ENUM('newFollower', 'newComment', 'newReply', 'newCreatorRecipe', 'newReviewOrRating') NOT NULL,
    message TEXT NOT NULL,
    isRead TINYINT(1) NOT NULL DEFAULT 0, /* 0 for UNREAD (false), 1 for READ (true) */
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    invoker_id BIGINT NOT NULL,
    post_id BIGINT NOT NULL
  )";

  $wpdb->query($sql);
}

function jondaley_notification_drop()
{
  global $wpdb;
  $table_name = NOTIFICATION_TABLE;
  $sql = "DROP TABLE IF EXISTS $table_name";
  $wpdb->query($sql);
}

// ------------------------------------------------------------------------------------------------------
/* End of table creation, deletion */

/* Begin CRUD operations */
// ------------------------------------------------------------------------------------------------------

// function to create a notification
function create_notification($user_id, $type, $message, $invoker_id, $post_id)
{
  global $wpdb;
  $table_name = NOTIFICATION_TABLE;
  $isRead = ReadStatus::UNREAD; // Default status is UNREAD

  $sql = $wpdb->prepare(
    "INSERT INTO $table_name (user_id, type, message, isRead, invoker_id, post_id) VALUES (%d, %s, %s, %d, %d, %d)",
    $user_id,
    $type,
    $message,
    $isRead,
    $invoker_id,
    $post_id
  );

  $result = $wpdb->query($sql);

  // Check if the insertion was successful
  if ($result === false) {
    error_log('Failed to create notification: ' . $wpdb->last_error);
    return false;
  }

  return true;
}


// Function to get a batch of notifications for a given user.
// Implemented pagenation to make this endpoint less expensive.
//  TODO: get url and name (jondaley profiles) get_user_meta
function get_notifications_for_user($user_id, $batch_size = 10, $page_no = 1)
{
  global $wpdb;
  $table_name = NOTIFICATION_TABLE;

  // Calculate OFFSET based on page_no and batch_size
  $offset = ($page_no - 1) * $batch_size;

  // Modify the query to include LIMIT and OFFSET for pagination
  // $query = $wpdb->prepare("SELECT * FROM $table_name WHERE user_id = %d ORDER BY created_at DESC LIMIT %d OFFSET %d", $user_id, $batch_size, $offset);
  $query = $wpdb->prepare("SELECT * FROM $table_name WHERE user_id = %d ORDER BY created_at DESC", $user_id);
  $results = $wpdb->get_results($query);

  if (empty($results)) {
    return false;
  }

  $notifications = [];
  foreach ($results as $result) {
    $user_data = get_userdata($result->invoker_id);
    $invoker_username = $user_data->display_name;
    $notification = new Notification(
      $result->user_id,
      $result->type,
      $result->message,
      $result->isRead,
      $result->invoker_id,
      $result->post_id
    );
    $notification->id = $result->id;
    $notification->created_at = $result->created_at;
    $notification->invoker_username = $invoker_username;
    $notifications[] = $notification;
  }

  return $notifications;
}


// get count of all unread notification for a user given a user id
function get_unread_notification_count_for_user($user_id)
{
  global $wpdb;
  $table_name = NOTIFICATION_TABLE;
  $query = $wpdb->prepare("SELECT COUNT(*) FROM $table_name WHERE user_id = %d AND isRead = %s", $user_id, ReadStatus::UNREAD);
  $count = $wpdb->get_var($query);

  if ($count === false) {
    error_log('Failed to retrieve unread notification count: ' . $wpdb->last_error);
    return 0;
  }

  return (int)$count;
}


// Mark a notification as read by id
function mark_notification_as_read($notification_id) {
  global $wpdb;
  $table_name = NOTIFICATION_TABLE;
  $unread = ReadStatus::UNREAD; // Assuming you have a constant for the unread status
  $read = ReadStatus::READ;

  // Update only if the current status is unread
  $sql = $wpdb->prepare(
      "UPDATE $table_name SET isRead = %s WHERE id = %d AND isRead = %s",
      $read, 
      $notification_id,
      $unread
  );

  $result = $wpdb->query($sql);

  if ($result === false) {
      // Query failed
      error_log('Failed to mark notification as read: ' . $wpdb->last_error);
      return false; 
  } elseif ($wpdb->rows_affected > 0) {
      // Notification was unread and now is marked as read
      return true; 
  } else {
      // Notification was already read or doesn't exist
      return null; 
  }
}



// Mark all unread notifications as read (invoked when user clicks on the bell to view his notifications).
function mark_all_notifications_as_read_for_user($user_id)
{
  global $wpdb;
  $table_name = NOTIFICATION_TABLE;
  $isRead = ReadStatus::READ; // Mark as read

  $sql = $wpdb->prepare(
    "UPDATE $table_name SET isRead = %s WHERE user_id = %d AND isRead = %s",
    $isRead,
    $user_id,
    ReadStatus::UNREAD // Only unread notifications
  );

  $result = $wpdb->query($sql);

  if ($result === false) {
    error_log('Failed to update notifications: ' . $wpdb->last_error);
    return false;
  }

  return true;
}

/* End of CRUD operations */
// ------------------------------------------------------------------------------------------------------


/* Begin AJAX endpoints */
// ------------------------------------------------------------------------------------------------------


// Notes: Consider using a namespace, or wrap in a controller class.
// https://developer.wordpress.org/rest-api/extending-the-rest-api/controller-classes/


// Endpoint to get all notifications
function jondaley_get_notifications_ajax()
{
  header('Content-Type: application/json; charset=UTF-8');

  // Ensure the user is logged in to access their notifications
  if (!is_user_logged_in()) {
    wp_send_json_error('User not logged in', 403);
    die('user not logged in');
  }

  $user_id = get_current_user_id();
  if (!$user_id) {
    wp_send_json_error('Invalid user ID', 404);
    die('invalid user ID');
  }

  // Retrieve batch_size and page_no from the query parameters, defaulting to 10 and 1 respectively
  $batch_size = isset($_GET['batch_size']) ? (sanitize_text_field((int)$_GET['batch_size'])) : 10;
  $page_no = isset($_GET['page_no']) ? (sanitize_text_field((int)$_GET['page_no'])) : 1;

  // Fetch paginated notifications
  $notifications = get_notifications_for_user($user_id, $batch_size, $page_no);

  $response = [
    'notifications' => $notifications,
    'batch_size' => $batch_size,
    'page_no' => $page_no
  ];

  wp_send_json_success($response, 200);
}

// Endpoint to get unread notifications count
function jondaley_get_unread_notifications_count_ajax()
{
  header('Content-Type: application/json; charset=UTF-8');

  if (!is_user_logged_in()) {
    wp_send_json_error('User not logged in', 403);
    die('user not logged in');
  }

  $user_id = get_current_user_id();
  if (!$user_id) {
    wp_send_json_error('Invalid user ID', 404);
    die('invalid user ID');
  }

  $notification_count = get_unread_notification_count_for_user($user_id);

  $response = [
    'count' => $notification_count
  ];

  wp_send_json_success($response, 200);
}

// Endpoint to mark all specific notifications as read
function jondaley_mark_notifications_as_read_ajax()
{
  header('Content-Type: application/json; charset=UTF-8');

  if (!is_user_logged_in()) {
    wp_send_json_error('User not logged in', 403);
    die('user not logged in');
}

$user_id = get_current_user_id();
if (!$user_id) {
    wp_send_json_error('Invalid user ID', 404);
    die('invalid user ID');
}

$result = mark_all_notifications_as_read_for_user($user_id);
  
  if ($result) {
    $response = [
      'all_marked_as_read' => $result
    ];
    wp_send_json_success($response, 200);
  } else {
    wp_send_json_error('Failed to mark notification as read', 500);
  }

  wp_die();
}

// add our custom endpoints to wordpress endpoints
add_action('wp_ajax_jondaley_get_notifications', 'jondaley_get_notifications_ajax');
add_action('wp_ajax_jondaley_get_unread_notifications_count', 'jondaley_get_unread_notifications_count_ajax');
add_action('wp_ajax_jondaley_mark_notifications_as_read', 'jondaley_mark_notifications_as_read_ajax');

// Notes: There is no need for a wp_ajax_nopriv_ action because marking notifications as read should only be available for logged-in users.

// -------------------------------------------------------------------------------------------------------
/* End AJAX endpoints */


/* Begin of test routines */
// Simple test routines just to check that my controller is working properly. (not a rigorous test by any means) 
//---------------------------------------------------------------------------------------------------------
function populate_notifications()
{
  $users = [
    ['id' => 327206, 'name' => 'Wafik Tawfik'],
    ['id' => 4, 'name' => 'Bill'],
    ['id' => 5, 'name' => 'the FoodSocial Team'],
  ];

  $notifications = [
    ['type' => 'newFollower', 'message' => 'You have a new follower!'],
    ['type' => 'newComment', 'message' => 'Someone commented on your post.'],
  ];

  $test_post_id = 6;
  $test_invoker_id = 4; 

  foreach ($users as $user) {
    foreach ($notifications as $notification) {
      $result = create_notification($user['id'], $notification['type'], $notification['message'], $test_invoker_id, $test_post_id);
      if (!$result) {
        //echo "Failed to create notification for user {$user['name']} (ID: {$user['id']})\n";
      } else {
        //echo "Notification created for user {$user['name']} (ID: {$user['id']})\n";
      }
    }
  }
}

function test_get_notifications_for_user()
{
  $user_id = 327206; 

  $notifications = get_notifications_for_user($user_id);

  if (empty($notifications)) {
    echo "No notifications found for user ID: $user_id\n";
    return;
  }

  echo "Notifications for user ID: $user_id\n";
  foreach ($notifications as $notification) {
    echo "---------------------------------\n";
    echo "Notification ID: " . $notification->id . "\n";
    echo "Type: " . $notification->type . "\n";
    echo "Message: " . $notification->message . "\n";
    echo "Read Status: " . ($notification->isRead ? 'Read' : 'Unread') . "\n";
    echo "Invoker ID: " . $notification->invoker_id . "\n";
    echo "Post ID: " . $notification->post_id . "\n";
    echo "Created At: " . $notification->created_at . "\n";
  }
}

function test_mark_notification_as_read()
{
  $notification_id = 1; 

  if (mark_notification_as_read($notification_id)) {
    echo "Notification ID $notification_id marked as read successfully.\n";
  } else {
    echo "Failed to mark Notification ID $notification_id as read.\n";
  }

  global $wpdb;
  $table_name = NOTIFICATION_TABLE;
  $query = $wpdb->prepare("SELECT isRead FROM $table_name WHERE id = %d", $notification_id);
  $isRead = $wpdb->get_var($query);

  if ($isRead == ReadStatus::READ) {
    echo "Verification successful: Notification ID $notification_id is marked as read in the database.\n";
  } else {
    echo "Verification failed: Notification ID $notification_id is still unread in the database.\n";
  }
}

function test_get_unread_notification_count_for_user()
{
  $user_id = 327206; 

  $unread_count = get_unread_notification_count_for_user($user_id);

  echo "User ID $user_id has $unread_count unread notification(s).\n";
}



// Call the test functions
// test_get_notifications_for_user();
// populate_notifications();
// test_get_unread_notification_count_for_user();
// test_mark_notification_as_read();

//---------------------------------------------------------------------------------------------------------
/* End of test routines */
