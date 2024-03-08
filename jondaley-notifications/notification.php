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
    public $user_id;
    public $type;
    public $message;
    public $isRead;
    public $created_at;

    public function __construct($user_id, $type, $message, $isRead = ReadStatus::UNREAD)
    {
        $this->user_id = $user_id;
        $this->type = $type;
        $this->message = $message;
        $this->isRead = $isRead;
        $this->created_at = date('Y-m-d H:i:s'); // Assuming creation time is now
    }
}
