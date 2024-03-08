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
    PRIMARY KEY (id)
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

// function to create a notification
function create_notification($user_id, $type, $message)
{
  global $wpdb;
  $table_name = NOTIFICATION_TABLE;
  $isRead = ReadStatus::UNREAD; // Default status is UNREAD

  $sql = $wpdb->prepare(
    "INSERT INTO $table_name (user_id, type, message, isRead) VALUES (%d, %s, %s, %s)",
    $user_id,
    $type,
    $message,
    $isRead
  );

  $result = $wpdb->query($sql);

  // Check if the insertion was successful
  if ($result === false) {
    error_log('Failed to create notification: ' . $wpdb->last_error);
    return false;
  }

  return true;
}
// ------------------------------------------------------------------------------------------------------
/* End of table creation, deletion */

/* Begin CRUD operations */
// ------------------------------------------------------------------------------------------------------
// function to get all notifications given a user id
function get_all_notifications_for_user($user_id)
{
  global $wpdb;
  $table_name = NOTIFICATION_TABLE;
  $query = $wpdb->prepare("SELECT * FROM $table_name WHERE user_id = %d ORDER BY created_at DESC", $user_id);
  $results = $wpdb->get_results($query);

  if (empty($results)) {
    return [];
  }

  $notifications = [];
  foreach ($results as $result) {
    $notification = new Notification(
      $result->user_id,
      $result->type,
      $result->message,
      $result->isRead
    );
    $notification->id = $result->id;
    $notification->created_at = $result->created_at;
    $notifications[] = $notification;
  }

  return $notifications;
}

// function to get all unread notifications given a user id
function get_all_unread_notifications_for_user($user_id)
{
  global $wpdb;
  $table_name = NOTIFICATION_TABLE;
  $query = $wpdb->prepare("SELECT * FROM $table_name WHERE user_id = %d AND isRead = %s ORDER BY created_at DESC", $user_id, ReadStatus::UNREAD);
  $results = $wpdb->get_results($query);

  if (empty($results)) {
    return [];
  }

  $notifications = [];
  foreach ($results as $result) {
    $notification = new Notification(
      $result->user_id,
      $result->type,
      $result->message,
      $result->isRead
    );
    $notification->id = $result->id;
    $notification->created_at = $result->created_at;
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
function mark_notification_as_read($notification_id)
{
  global $wpdb;
  $table_name = NOTIFICATION_TABLE;
  $isRead = ReadStatus::READ; // Default status is UNREAD

  $sql = $wpdb->prepare(
    "UPDATE $table_name SET isRead = %s WHERE id = %d",
    $isRead,
    $notification_id
  );

  $result = $wpdb->query($sql);

  if ($result === false) {
    error_log('Failed to create notification: ' . $wpdb->last_error);
    return false;
  }

  return true;
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

// Endpoint to get all notifications and unread notifications count
// Note: I imagine this to be called the first time when a user loads the page to load the bell with a count representing 
// the unread notifications (the same goes if cache is empty), and when the bill is opened, show all notifications. 
// The user should have all the notifications cached to prevent unneccessary calling of this expensive endpoint. 
// There should be another (less expensive) endpoint that only loads the new notifications. 
function jondaley_get_all_notifications_and_unread_notifications_count_ajax()
{
  header('Content-Type: application/json; charset=UTF-8');
  // Ensure the user is logged in to access their notifications
  if (!is_user_logged_in()) {
    wp_send_json_error('User not logged in', 403);
    return;
  }

  $user_id = get_current_user_id();
  if (!$user_id) {
    wp_send_json_error('Invalid user ID', 404);
    return;
  }

  $notifications = get_all_unread_notifications_for_user($user_id);
  $unread_notifications_count = get_unread_notification_count_for_user($user_id);

  $response = [
    'notifications' => $notifications,
    'unread_notifications_count' => $unread_notifications_count
  ];

  wp_send_json_success($response, 200);
}

// Endpoint to get all unread notifications and their count
function jondaley_get_all_unread_notifications_ajax()
{
  header('Content-Type: application/json; charset=UTF-8');

  if (!is_user_logged_in()) {
    wp_send_json_error('User not logged in', 403);
    return;
  }

  $user_id = get_current_user_id();
  if (!$user_id) {
    wp_send_json_error('Invalid user ID', 404);
    return;
  }

  $unread_notifications = get_all_unread_notifications_for_user($user_id);
  $unread_notifications_count = count($unread_notifications);

  $response = [
    'unread_notifications' => $unread_notifications,
    'unread_notifications_count' => $unread_notifications_count
  ];

  wp_send_json_success($response, 200);
}

add_action('wp_ajax_jondaley_get_all_unread_notifications', 'jondaley_get_all_unread_notifications_ajax');
add_action('wp_ajax_nopriv_jondaley_get_all_unread_notifications', 'jondaley_get_all_unread_notifications_ajax');

add_action('wp_ajax_jondaley_get_all_notifications_and_unread_notifications_count', 'jondaley_get_all_notifications_and_unread_notifications_count_ajax');
add_action('wp_ajax_nopriv_jondaley_get_all_notifications_and_unread_notifications_count', 'jondaley_get_all_notifications_and_unread_notifications_count_ajax');
// -------------------------------------------------------------------------------------------------------
/* End AJAX endpoints */


/* begin of test routines */
//---------------------------------------------------------------------------------------------------------
// function test()
// {
//   $users = [
//     ['id' => 2, 'name' => 'Jon Daley'],
//     ['id' => 4, 'name' => 'Bill'],
//     ['id' => 5, 'name' => 'the FoodSocial Team'],
//   ];

//   $notifications = [
//     ['type' => 'newFollower', 'message' => 'You have a new follower!'],
//     ['type' => 'newComment', 'message' => 'Someone commented on your post.'],
//   ];


//   foreach ($users as $user) {
//     foreach ($notifications as $notification) {
//       $result = create_notification($user['id'], $notification['type'], $notification['message']);
//       if (!$result) {
//         echo "Failed to create notification for user {$user['name']} (ID: {$user['id']})\n";
//       }
//     }
//   }
// }

// function test_print_all_unread_notifications_for_user_2()
// {
//   $notifications = get_all_notifications_for_user(2);
//   foreach ($notifications as $notification) {
//     echo "User ID: {$notification->user_id}, Type: {$notification->type}, Message: {$notification->message}, isRead: {$notification->isRead}\n";
//   }
// }

// function mark_first_10_notifications_as_read()
// {
//   $notifications = get_all_unread_notifications_for_user(2);
//   $first_10_notifications = array_slice($notifications, 0, 10);
//   foreach ($first_10_notifications as $notification) {
//     $result = mark_notification_as_read($notification->id);
//     if (!$result) {
//       echo "Failed to mark notification {$notification->id} as read\n";
//     }
//   }
// }

//test();
// test_print_all_unread_notifications_for_user_2();
// mark_first_10_notifications_as_read();
//---------------------------------------------------------------------------------------------------------
/* end of test routines */
