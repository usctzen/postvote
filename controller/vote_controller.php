<?php
/**
 * Post Vote extension for phpBB 3.3.x.
 * Controller handling upvote/downvote AJAX and fallback actions.
 *
 * @copyright (c) 2026 Marc DUVAL (usctzen) <duvalm@gmail.com>
 * @license MIT
 */

namespace usctzen\postvote\controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

class vote_controller
{
    public const VOTE_UP = 1;
    public const VOTE_DOWN = -1;

    /** @var \phpbb\user */
    protected $user;

    /** @var \phpbb\db\driver\driver_interface */
    protected $db;

    /** @var \phpbb\request\request_interface */
    protected $request;

    /** @var string */
    protected $table_prefix;

    /** @var string */
    protected $root_path;

    /** @var string */
    protected $php_ext;

    public function __construct(
        \phpbb\user $user,
        \phpbb\db\driver\driver_interface $db,
        \phpbb\request\request_interface $request,
        string $table_prefix,
        string $root_path,
        string $php_ext
    ) {
        $this->user = $user;
        $this->db = $db;
        $this->request = $request;
        $this->table_prefix = $table_prefix;
        $this->root_path = $root_path;
        $this->php_ext = $php_ext;
    }

    /**
     * Handle upvote / downvote toggle or switch for a post.
     *
     * @param int    $post_id
     * @param string $direction 'up' or 'down'
     * @return Response
     */
    public function vote(int $post_id, string $direction): Response
    {
        $this->user->add_lang_ext('usctzen/postvote', 'common');
        $is_ajax = $this->request->is_ajax();

        // Check if user is logged in and not a search bot or ignored user
        $is_registered = !empty($this->user->data['is_registered']);
        $user_type = isset($this->user->data['user_type']) ? (int) $this->user->data['user_type'] : USER_NORMAL;

        if (!$is_registered || $user_type === USER_IGNORE) {
            if ($is_ajax) {
                return new JsonResponse([
                    'success' => false,
                    'message' => $this->user->lang('NOT_LOGGED_IN'),
                ], 403);
            }
            return $this->redirect_to_post($post_id);
        }

        $user_id = (int) $this->user->data['user_id'];
        $table = $this->table_prefix . 'post_votes';

        // Check if the post exists and retrieve its author
        $sql = 'SELECT post_id, poster_id FROM ' . $this->table_prefix . 'posts WHERE post_id = ' . (int) $post_id;
        $result = $this->db->sql_query_limit($sql, 1);
        $post_row = $this->db->sql_fetchrow($result);
        $this->db->sql_freeresult($result);

        if (!$post_row) {
            if ($is_ajax) {
                return new JsonResponse([
                    'success' => false,
                    'message' => $this->user->lang('POST_NOT_FOUND'),
                ], 404);
            }
            trigger_error('POST_NOT_FOUND');
        }

        // Prevent users from voting on their own posts
        if ((int) $post_row['poster_id'] === $user_id) {
            if ($is_ajax) {
                return new JsonResponse([
                    'success' => false,
                    'message' => $this->user->lang('CANNOT_VOTE_OWN_POST'),
                ], 403);
            }
            return $this->redirect_to_post($post_id);
        }

        $target_vote = ($direction === 'down') ? self::VOTE_DOWN : self::VOTE_UP;

        // Check if user has already voted on this post
        $sql = 'SELECT vote_id, vote_type FROM ' . $table . ' WHERE post_id = ' . (int) $post_id . ' AND user_id = ' . $user_id;
        $result = $this->db->sql_query_limit($sql, 1);
        $vote_row = $this->db->sql_fetchrow($result);
        $this->db->sql_freeresult($result);

        $user_vote = 0;

        if ($vote_row) {
            $current_vote = (int) $vote_row['vote_type'];
            $vote_id = (int) $vote_row['vote_id'];

            if ($current_vote === $target_vote) {
                // Clicking the same vote button removes the vote
                $sql = 'DELETE FROM ' . $table . ' WHERE vote_id = ' . $vote_id;
                $this->db->sql_query($sql);
                $user_vote = 0;
            } else {
                // Switching from upvote to downvote (or vice versa)
                $sql_data = [
                    'vote_type' => $target_vote,
                    'vote_time' => time(),
                ];
                $sql = 'UPDATE ' . $table . ' SET ' . $this->db->sql_build_array('UPDATE', $sql_data) . ' WHERE vote_id = ' . $vote_id;
                $this->db->sql_query($sql);
                $user_vote = $target_vote;
            }
        } else {
            // Insert new vote
            $sql_data = [
                'post_id'   => (int) $post_id,
                'user_id'   => $user_id,
                'vote_type' => $target_vote,
                'vote_time' => time(),
            ];
            $sql = 'INSERT INTO ' . $table . ' ' . $this->db->sql_build_array('INSERT', $sql_data);
            $this->db->sql_query($sql);
            $user_vote = $target_vote;
        }

        // Count total upvotes and downvotes for this post in a single query
        $upvotes = 0;
        $downvotes = 0;

        $sql = 'SELECT vote_type, COUNT(vote_id) AS total_votes
                FROM ' . $table . '
                WHERE post_id = ' . (int) $post_id . '
                GROUP BY vote_type';
        $result = $this->db->sql_query($sql);
        while ($row = $this->db->sql_fetchrow($result)) {
            $type = (int) $row['vote_type'];
            if ($type === self::VOTE_UP) {
                $upvotes = (int) $row['total_votes'];
            } elseif ($type === self::VOTE_DOWN) {
                $downvotes = (int) $row['total_votes'];
            }
        }
        $this->db->sql_freeresult($result);

        if ($is_ajax) {
            return new JsonResponse([
                'success'    => true,
                'user_vote'  => $user_vote,
                'upvotes'    => $upvotes,
                'downvotes'  => $downvotes,
                'up_label'   => ($user_vote === self::VOTE_UP) ? $this->user->lang('POST_REMOVE_UPVOTE') : $this->user->lang('POST_UPVOTE'),
                'down_label' => ($user_vote === self::VOTE_DOWN) ? $this->user->lang('POST_REMOVE_DOWNVOTE') : $this->user->lang('POST_DOWNVOTE'),
            ]);
        }

        // Non-AJAX fallback: redirect back to the post on viewtopic
        return $this->redirect_to_post($post_id);
    }

    /**
     * Build a clean redirect response back to the post.
     *
     * @param int $post_id
     * @return RedirectResponse
     */
    protected function redirect_to_post(int $post_id): RedirectResponse
    {
        $url = append_sid($this->root_path . 'viewtopic.' . $this->php_ext, 'p=' . $post_id, false) . '#p' . $post_id;
        return new RedirectResponse($url);
    }
}
