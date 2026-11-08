<?php
/**
 * Post Vote extension for phpBB 3.3.x.
 * Event listener class.
 *
 * @copyright (c) 2026 Marc DUVAL (usctzen) <duvalm@gmail.com>
 * @license MIT
 */

namespace usctzen\postvote\event;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use usctzen\postvote\controller\vote_controller;

class main_listener implements EventSubscriberInterface
{
    /** @var \phpbb\user */
    protected $user;

    /** @var \phpbb\template\template */
    protected $template;

    /** @var \phpbb\db\driver\driver_interface */
    protected $db;

    /** @var \phpbb\controller\helper */
    protected $helper;

    /** @var string */
    protected $table_prefix;

    /** @var array Cached upvote counts indexed by post_id */
    protected $upvotes_cache = [];

    /** @var array Cached downvote counts indexed by post_id */
    protected $downvotes_cache = [];

    /** @var array Cached current user vote status (1 or -1) indexed by post_id */
    protected $user_votes = [];

    public function __construct(
        \phpbb\user $user,
        \phpbb\template\template $template,
        \phpbb\db\driver\driver_interface $db,
        \phpbb\controller\helper $helper,
        string $table_prefix
    ) {
        $this->user = $user;
        $this->template = $template;
        $this->db = $db;
        $this->helper = $helper;
        $this->table_prefix = $table_prefix;
    }

    public static function getSubscribedEvents()
    {
        return [
            'core.user_setup'                 => 'load_language',
            'core.viewtopic_modify_post_data' => 'preload_post_votes',
            'core.viewtopic_modify_post_row'  => 'assign_post_votes_data',
        ];
    }

    /**
     * Load extension language files.
     *
     * @param \phpbb\event\data $event
     */
    public function load_language($event)
    {
        $lang_set_ext = $event['lang_set_ext'];
        $lang_set_ext[] = [
            'ext_name' => 'usctzen/postvote',
            'lang_set' => 'common',
        ];
        $event['lang_set_ext'] = $lang_set_ext;
    }

    /**
     * Preload upvotes, downvotes, and current user vote status for all posts on the page.
     * Avoids N+1 SQL query problem.
     *
     * @param \phpbb\event\data $event
     */
    public function preload_post_votes($event)
    {
        $post_list = $event['post_list'];
        if (empty($post_list) || !is_array($post_list)) {
            return;
        }

        $table = $this->table_prefix . 'post_votes';
        $current_user_id = (int) $this->user->data['user_id'];

        $sql = 'SELECT post_id, user_id, vote_type FROM ' . $table . ' WHERE ' . $this->db->sql_in_set('post_id', $post_list);
        $result = $this->db->sql_query($sql);

        while ($row = $this->db->sql_fetchrow($result)) {
            $p_id = (int) $row['post_id'];
            $u_id = (int) $row['user_id'];
            $v_type = (int) $row['vote_type'];

            if ($v_type === vote_controller::VOTE_UP) {
                $this->upvotes_cache[$p_id] = ($this->upvotes_cache[$p_id] ?? 0) + 1;
            } elseif ($v_type === vote_controller::VOTE_DOWN) {
                $this->downvotes_cache[$p_id] = ($this->downvotes_cache[$p_id] ?? 0) + 1;
            }

            if ($current_user_id > 0 && $u_id === $current_user_id) {
                $this->user_votes[$p_id] = $v_type;
            }
        }
        $this->db->sql_freeresult($result);
    }

    /**
     * Inject vote counters, status, and URLs into the postrow template block.
     *
     * @param \phpbb\event\data $event
     */
    public function assign_post_votes_data($event)
    {
        $post_row  = $event['post_row'];
        $post_id   = (int) $event['row']['post_id'];
        $poster_id = (int) ($event['poster_id'] ?? $event['row']['user_id'] ?? 0);

        $upvotes   = $this->upvotes_cache[$post_id] ?? 0;
        $downvotes = $this->downvotes_cache[$post_id] ?? 0;
        $user_vote = $this->user_votes[$post_id] ?? 0;

        $current_user_id = (int) $this->user->data['user_id'];
        $is_registered   = !empty($this->user->data['is_registered']);
        $user_type       = isset($this->user->data['user_type']) ? (int) $this->user->data['user_type'] : USER_NORMAL;

        // Users must be registered, not bots, and not the author of the post
        $can_vote = $is_registered && ($user_type !== USER_IGNORE) && ($poster_id !== $current_user_id);

        $post_row['POST_UPVOTES_COUNT']   = $upvotes;
        $post_row['POST_DOWNVOTES_COUNT'] = $downvotes;
        $post_row['S_POST_HAS_VOTES']     = ($upvotes > 0 || $downvotes > 0);
        $post_row['S_USER_UPVOTED']       = ($user_vote === vote_controller::VOTE_UP);
        $post_row['S_USER_DOWNVOTED']     = ($user_vote === vote_controller::VOTE_DOWN);
        $post_row['S_CAN_VOTE']           = $can_vote;
        $post_row['U_VOTE_UP']            = $this->helper->route('usctzen_postvote_vote', ['post_id' => $post_id, 'direction' => 'up']);
        $post_row['U_VOTE_DOWN']          = $this->helper->route('usctzen_postvote_vote', ['post_id' => $post_id, 'direction' => 'down']);

        $event['post_row'] = $post_row;
    }
}
