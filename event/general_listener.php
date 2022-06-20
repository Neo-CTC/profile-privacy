<?php
/**
 *
 * Profile Privacy. An extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2022, Neo
 * @license       GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace crosstimecafe\profileprivacy\event;

use phpbb\db\tools\tools_interface;
use phpbb\user;
use phpbb\auth\auth;
use phpbb\db\driver\driver_interface;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 *
 */
class general_listener implements EventSubscriberInterface
{
	/**
	 * @return string[]
	 */
	public static function getSubscribedEvents()
	{
		return [
			'core.grab_profile_fields_data'                 => 'filter_profile_fields',
			'core.memberlist_prepare_profile_data'          => 'filter_age',
			'core.index_modify_birthdays_list'              => 'filter_age_front_page',
			'core.obtain_users_online_string_before_modify' => 'filter_online',
			'core.viewtopic_modify_post_data'               => 'filter_online_topic_post',
			'core.viewonline_modify_user_row'               => 'filter_online_view',

		];
	}

	private $db;
	private $user;
	private $auth;
	private $table;
	private $uid;
	private $db_tools;

	public function __construct(driver_interface $db, user $user, auth $auth, tools_interface $tools)
	{
		$this->db       = $db;
		$this->user     = $user;
		$this->auth     = $auth;
		$this->db_tools = $tools;

		global $table_prefix;
		$this->table = $table_prefix . 'profile_privacy_ext';

		$this->uid = ($this->user->data['is_registered'] && !$this->user->data['is_bot']) ? $this->user->id() : 1;
	}

	/**
	 * Filters profile fields on both the view topic page and the view profile page
	 *
	 * @param $event
	 * @return void
	 */
	public function filter_profile_fields($event)
	{
		// Show everything to mods & admins
		if ($this->auth->acl_gets('a_', 'm_') || $this->auth->acl_getf_global('m_'))
		{
			return;
		}

		$user_ids   = $event['user_ids'];
		$fields     = $this->db_tools->sql_list_columns($this->table);
		$field_data = $event['field_data'];

		$acl = $this->access_control($user_ids, $fields);
		foreach ($user_ids as $user_id)
		{
			foreach ($fields as $field)
			{
				if (!isset($acl[$user_id]) || !in_array($field, $acl[$user_id]))
				{
					$field_data[$user_id][$field] = '';
				}
			}
		}
		$event['field_data'] = $field_data;
	}

	/**
	 * Filters age from view profile page
	 *
	 * @param $event
	 * @return void
	 */
	public function filter_age($event)
	{
		// Show everything to mods & admins
		if ($this->auth->acl_gets('a_', 'm_') || $this->auth->acl_getf_global('m_'))
		{
			return;
		}
		$profile_id = $event['data']['user_id'];
		$acl        = $this->access_control([$profile_id], ['bday_age']);
		if (!isset($acl[$profile_id]))
		{
			$template               = $event['template_data'];
			$template['AGE']        = '';
			$event['template_data'] = $template;
		}
	}

	/**
	 * Filter birthdays from index.php
	 *
	 * @param $event
	 * @return void
	 */
	public function filter_age_front_page($event)
	{
		// If nothing, do nothing
		if (empty($event['rows']))
		{
			return;
		}

		// Show everything to mods & admins
		if ($this->auth->acl_gets('a_', 'm_') || $this->auth->acl_getf_global('m_'))
		{
			return;
		}

		// Fetch user ids from list
		$profile_ids = [];
		$rows        = $event['rows'];
		foreach ($rows as $row)
		{
			$profile_ids[] = $row['user_id'];
		}

		$acl = $this->access_control($profile_ids, ['bday_age']);

		$birthday_list = [];
		foreach ($rows as $index => $data)
		{
			$user_id = $data['user_id'];
			if (isset($acl[$user_id]))
			{
				$birthday_list[] = $event['birthdays'][$index];
			}
		}
		$event['birthdays'] = $birthday_list;
	}

	public function filter_online($event)
	{
		if ($this->auth->acl_gets('a_', 'm_') || $this->auth->acl_getf_global('m_'))
		{
			// Todo check for 'can view hidden users permission'
			return;
		}

		$online_users     = $event['online_users'];
		$online_ids       = array_keys($online_users['online_users']);
		$user_online_link = $event['user_online_link'];

		// Anybody home? Skip if there are no members online
		if (empty($online_ids))
		{
			return;
		}

		$acl = $this->access_control($online_ids, ['online']);

		// Bots don't have a profile entry, we'll need to add them back in
		foreach ($event['rowset'] as $row)
		{
			if ($row['user_type'] == 2)
			{
				$acl[$row['user_id']] = true;
			}
		}

		// Remove everyone we can't view from the online list
		foreach ($online_ids as $online_id)
		{
			// User is online, but no access to view
			if (!isset($acl[$online_id]))
			{
				// Add to hidden users
				$online_users['hidden_users'][$online_id] = $online_id;

				// Adjust counts
				$online_users['visible_online']--;
				$online_users['hidden_online']++;

				// Remove from displayed list
				unset($user_online_link[$online_id]);
			}
		}

		$event['online_users']     = $online_users;
		$event['user_online_link'] = $user_online_link;
	}

	public function filter_online_topic_post($event)
	{
		if ($this->auth->acl_gets('a_', 'm_') || $this->auth->acl_getf_global('m_'))
		{
			// Todo check for 'can view hidden users permission'
			return;
		}

		$user_ids   = array_keys($event['user_cache']);
		$user_cache = $event['user_cache'];
		$acl        = $this->access_control($user_ids, ['online']);
		foreach ($user_ids as $user_id)
		{
			if (!isset($acl[$user_id]))
			{
				$user_cache[$user_id]['online'] = false;
			}
		}
		$event['user_cache'] = $user_cache;
	}

	public function filter_online_view($event)
	{
		// Bypass bots, and guests
		if ($event['row']['user_type'] == 2)
		{
			return;
		}

		if ($this->auth->acl_gets('a_', 'm_') || $this->auth->acl_getf_global('m_'))
		{
			// Todo check for 'can view hidden users permission'
			return;
		}

		$user_id = $event['row']['user_id'];

		$acl = $this->access_control([$user_id], ['online']);
		if (!isset($acl[$user_id]))
		{
			$template                   = $event['template_row'];
			$template['USERNAME']       = '******';
			$template['USERNAME_FULL']  = '******';
			$template['USERNAME_COLOR'] = '';
			$event['template_row']      = $template;
		}
	}

	/**
	 * Find if a user has access to a given array of user ids and fields
	 *
	 * @param array $user_ids
	 * @param array $fields
	 * @return array
	 */
	private function access_control($user_ids, $fields)
	{
		if (empty($user_ids || empty($fields)))
			return [];

		// Build Friend list
		$friend_list = [];
		$foe_list    = [];
		if ($this->uid > 1)
		{
			$sql = 'SELECT user_id,friend,foe FROM ' . ZEBRA_TABLE . '
			 WHERE ' . $this->db->sql_in_set('user_id', $user_ids) . '
			 AND zebra_id = ' . $this->uid;

			$result = $this->db->sql_query($sql);
			while ($row = $this->db->sql_fetchrow($result))
			{
				if ($row['foe'] === '1')
				{
					$foe_list[] = $row['user_id'];
				}
				else if ($row['friend'] === '1')
				{
					$friend_list[] = $row['user_id'];
				}
			}
			$this->db->sql_freeresult($result);
		}

		// Fetch privacy settings
		$sql    = 'SELECT user_id,' . implode(',', $fields) . ' FROM ' . $this->table . '
		WHERE ' . $this->db->sql_in_set('user_id', $user_ids);
		$result = $this->db->sql_query($sql);

		// Access Control List
		$acl = [];
		while ($row = $this->db->sql_fetchrow($result))
		{
			// Approve own profile
			if ($this->uid == $row['user_id'])
			{
				$acl[$this->uid] = array_keys($row);
				continue;
			}

			// Skip foes
			if (in_array($row['user_id'], $foe_list))
			{
				continue;
			}

			foreach ($fields as $field)
			{
				switch ($row[$field])
				{
					case 0:
						$acl[$row['user_id']][] = $field;
					break;
					case 1:
						if ($this->uid > 1)
						{
							$acl[$row['user_id']][] = $field;
						}
					break;
					case 2:
						if (in_array($row['user_id'], $friend_list))
						{
							$acl[$row['user_id']][] = $field;
						}
					break;
					default:
					break;
				}
			}
		}
		return $acl;
	}
}
