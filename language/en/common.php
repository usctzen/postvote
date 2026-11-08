<?php
/**
 * Post Vote extension for phpBB 3.3.x.
 * US English Language File.
 *
 * @copyright (c) 2026 Marc DUVAL (usctzen) <duvalm@gmail.com>
 * @license MIT
 */

if (!defined('IN_PHPBB')) {
    exit;
}

if (empty($lang) || !is_array($lang)) {
    $lang = [];
}

$lang = array_merge($lang, [
    'POST_UPVOTE'          => 'Upvote',
    'POST_REMOVE_UPVOTE'   => 'Remove upvote',
    'POST_DOWNVOTE'        => 'Downvote',
    'POST_REMOVE_DOWNVOTE' => 'Remove downvote',
    'POST_UPVOTES'         => 'Upvotes',
    'POST_DOWNVOTES'       => 'Downvotes',
    'POST_NOT_FOUND'       => 'The requested post was not found.',
    'NOT_LOGGED_IN'        => 'You must be logged in to vote on posts.',
    'CANNOT_VOTE_OWN_POST' => 'You cannot vote on your own posts.',
    'VOTE_ERROR'           => 'An error occurred while processing your vote.',
]);
