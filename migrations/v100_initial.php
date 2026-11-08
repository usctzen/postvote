<?php
/**
 * Post Vote extension for phpBB 3.3.x.
 *
 * @copyright (c) 2026 Marc DUVAL (usctzen) <duvalm@gmail.com>
 * @license MIT
 */

namespace usctzen\postvote\migrations;

class v100_initial extends \phpbb\db\migration\migration
{
    public static function depends_on()
    {
        return ['\phpbb\db\migration\data\v330\v330'];
    }

    public function effectively_installed()
    {
        return $this->db_tools->sql_table_exists($this->table_prefix . 'post_votes');
    }

    public function update_schema()
    {
        return [
            'add_tables' => [
                $this->table_prefix . 'post_votes' => [
                    'COLUMNS' => [
                        'vote_id'   => ['UINT', null, 'auto_increment'],
                        'post_id'   => ['UINT', 0],
                        'user_id'   => ['UINT', 0],
                        'vote_type' => ['TINT:1', 1],
                        'vote_time' => ['TIMESTAMP', 0],
                    ],
                    'PRIMARY_KEY' => 'vote_id',
                    'KEYS' => [
                        'post_id'       => ['INDEX', 'post_id'],
                        'user_post_idx' => ['UNIQUE', ['post_id', 'user_id']],
                    ],
                ],
            ],
        ];
    }

    public function update_data()
    {
        return [
            ['custom', [[$this, 'migrate_existing_likes']]],
        ];
    }

    /**
     * Automatically migrate likes from the legacy phpbb_post_likes table if it exists.
     */
    public function migrate_existing_likes()
    {
        $old_table = $this->table_prefix . 'post_likes';
        $new_table = $this->table_prefix . 'post_votes';

        if (!$this->db_tools->sql_table_exists($old_table) || !$this->db_tools->sql_table_exists($new_table)) {
            return;
        }

        // Load existing pairs in post_votes to avoid duplicate key conflicts
        $existing = [];
        $sql = 'SELECT post_id, user_id FROM ' . $new_table;
        $result = $this->db->sql_query($sql);
        while ($row = $this->db->sql_fetchrow($result)) {
            $key = (int) $row['post_id'] . '_' . (int) $row['user_id'];
            $existing[$key] = true;
        }
        $this->db->sql_freeresult($result);

        // Fetch legacy likes from post_likes
        $sql = 'SELECT post_id, user_id, like_time FROM ' . $old_table;
        $result = $this->db->sql_query($sql);

        $insert_buffer = [];
        while ($row = $this->db->sql_fetchrow($result)) {
            $post_id = (int) $row['post_id'];
            $user_id = (int) $row['user_id'];
            $key = $post_id . '_' . $user_id;

            if ($post_id > 0 && $user_id > 0 && !isset($existing[$key])) {
                $existing[$key] = true;
                $insert_buffer[] = [
                    'post_id'   => $post_id,
                    'user_id'   => $user_id,
                    'vote_type' => 1,
                    'vote_time' => (int) $row['like_time'],
                ];

                if (count($insert_buffer) >= 100) {
                    $this->db->sql_multi_insert($new_table, $insert_buffer);
                    $insert_buffer = [];
                }
            }
        }
        $this->db->sql_freeresult($result);

        if (!empty($insert_buffer)) {
            $this->db->sql_multi_insert($new_table, $insert_buffer);
        }
    }

    public function revert_schema()
    {
        return [
            'drop_tables' => [
                $this->table_prefix . 'post_votes',
            ],
        ];
    }
}
