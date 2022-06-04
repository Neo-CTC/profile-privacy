<?php
/**
 *
 * Profile Privacy. An extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2022, Neo
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace crosstimecafe\profileprivacy\event;

/**
 * @ignore
 */

use phpbb\user;
use phpbb\db\driver\driver_interface;
use phpbb\db\tools\tools_interface;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Profile Privacy Event listener.
 */
class main_listener implements EventSubscriberInterface
{
	public static function getSubscribedEvents()
	{
		return [
	        'core.grab_profile_fields_data'	=> 'filter_profile_fields',
            'core.acp_profile_create_edit_save_before' => 'modify_profile_columns',
            'core.ucp_profile_modify_profile_info' => 'create_profile_entry',
            // Todo add new event to phpBB
            // 'core.acp_profile_delete_before' => 'delete_profile_columns',
		];
	}

    protected $db;
    protected $user;
    protected $table;

    public function __construct(driver_interface $db, user $user, tools_interface $tools)
	{
        $this->db       = $db;
        $this->user     = $user;
        $this->db_tools = $tools;

        global $table_prefix;
        $this->table = $table_prefix . "profileprivacy";
	}

	public function filter_profile_fields($event)
	{
        $self_uid    = $this->user->id();
        $user_ids    = $event['user_ids'];
        $user_fields = $event['field_data'];

        //Fetch user privacy settings for all users on page
        $sql = 'SELECT * 
            FROM ' . $this->table . '
            WHERE '. $this->db->sql_in_set('user_id',$user_ids);
        $result = $this->db->sql_query($sql);

        // Each user
        while($row = $this->db->sql_fetchrow($result))
        {
            $uid = $row['user_id'];

            // Skip if view own profile
            if($self_uid == $uid){
                continue;
            }

            // Does the post author have the current user listed as a friend?
            $is_friend_of = $self_uid != 1 ? $this->reverse_zebra($uid, $self_uid) : 0;

            // Each user's privacy setting
            foreach($row as $key => $value){
                if($key == 'user_id')
                {
                    continue;
                }

                // Hide everything from foes
                // Todo might change this
                if($is_friend_of === -1)
                {
                    $fd = $event['field_data'];
                    $fd[$uid][$key] = '';
                    $event['field_data'] = $fd;
                }

                else
                {
                    switch($value)
                    {
                        // Guest, field is open to all, do nothing
                        case 0:
                            break;

                        // Members
                        case 1:
                            if($self_uid == 1)
                            {
                                $fd = $event['field_data'];
                                $fd[$uid][$key] = '';
                                $event['field_data'] = $fd;
                            }
                            break;

                        // Friends
                        case 2:
                            if($is_friend_of === 1)
                            {
                                $fd = $event['field_data'];
                                $fd[$uid][$key] = '';
                                $event['field_data'] = $fd;
                            }
                            break;

                        // Nobody
                        case 3:
                            $fd = $event['field_data'];
                            $fd[$uid][$key] = '';
                            $event['field_data'] = $fd;
                            break;
                    }
                }
            }
        }
        $this->db->sql_freeresult($result);
	}

    /**
     * Checks if $user has $zebra as a friend
     * @param $user string
     * @param $zebra string
     * @return bool
     */
    private function reverse_zebra($user, $zebra)
    {
        $sql = 'SELECT friend,foe
				FROM ' . ZEBRA_TABLE . "
				WHERE user_id = $user AND zebra_id = $zebra";
        $result = $this->db->sql_query($sql);
        $row = $this->db->sql_fetchrow($result);
        if($row === false)
        {
            return 0;
        }

        if($row['foe'] === '1')
        {
            return -1;
        }
        elseif ($row['friend'] === '1')
        {
            return 1;
        }
        else{
            return 0;
        }
    }

    public function create_profile_entry($event)
    {
        $sql = 'SELECT user_id
        FROM ' . $this->table . '
        WHERE user_id = ' . $this->user->id();
        $result = $this->db->sql_query($sql);
        $has_entry = $this->db->sql_fetchfield('user_id',0,$result);
        $this->db->sql_freeresult($result);

        if($has_entry === false)
        {
            $sql = 'INSERT INTO ' . $this->table . '
            ' . $this->db->sql_build_array("INSERT",['user_id' => $this->user->id()]);
            $this->db->sql_query($sql);
        }
    }

    public function modify_profile_columns($event)
    {
        $fid = $event['field_data']['field_ident'];
        $action = $event['action'];

        // We only care about creating a field since the field identifier never changes
        if($action == 'create')
        {
            $this->db_tools->sql_column_add($this->table,'pf_' . $fid,['UINT',1]);
        }
    }
}
