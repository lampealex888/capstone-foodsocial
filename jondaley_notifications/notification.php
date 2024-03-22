<?php

// Enums
class ReadStatus
{
    const UNREAD = 0;
    const READ = 1;
}

class NotificationType
{
    const NEW_FOLLOWER = 'newFollower';
    const NEW_COMMENT = 'newComment';
    const NEW_REPLY = 'newReply';
    const NEW_CREATOR_RECIPE = 'newCreatorRecipe';
    const NEW_REVIEW_OR_RATING = 'newReviewOrRating';
}
// End of Enums

class Notification
{
    public $id;
    public $invoker_username;
    public $user_id;
    public $type;
    public $message;
    public $isRead;
    public $created_at;
    public $invoker_id; /* id of user or creator who caused the notification */ 
    public $post_id; /* id of post which caused the notification */
    // Notes: How can I we get the url of the post that caused the notification to occur? does that happen in the frontend? 
    // or do we need to call some other endpoints. Also we need to fetch the avatar of the user (How to fetch the gravatar at the front end?)

    public function __construct($user_id, $type, $message, $isRead = ReadStatus::UNREAD, $invoker_id, $post_id)
    {
        $this->user_id = $user_id;
        $this->type = $type;
        $this->message = $message;
        $this->isRead = $isRead;
        $this->invoker_id = $invoker_id;
        $this->post_id = $post_id;
    }
}
