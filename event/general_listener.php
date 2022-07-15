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
use phpbb\language\language;
use phpbb\user;
use phpbb\auth\auth;
use phpbb\db\driver\driver_interface;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Applies privacy settings at various locations throughout phpBB
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
			'core.memberlist_prepare_profile_data'          => 'filter_profile_page',
			'core.index_modify_birthdays_list'              => 'filter_age_front_page',
			'core.obtain_users_online_string_before_modify' => 'filter_online',
			'core.viewtopic_modify_post_data'               => 'filter_online_topic_post',
			'core.viewtopic_modify_post_row'                => 'filter_viewtopic_contact',
			'core.viewonline_modify_user_row'               => 'filter_online_view',
			'core.ucp_pm_view_messsage'                     => 'filter_pm_view',
			'core.message_list_actions'                     => 'filter_pm_receiving',
		];
	}

	private $db;
	private $user;
	private $auth;
	private $table;
	private $uid;
	private $db_tools;
	private $language;

	public function __construct(driver_interface $db, user $user, auth $auth, tools_interface $tools, language $language)
	{
		$this->db       = $db;
		$this->user     = $user;
		$this->auth     = $auth;
		$this->db_tools = $tools;
		$this->language = $language;

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

		$fields     = $this->db_tools->sql_list_columns($this->table);
		$field_data = $event['field_data'];
		$user_ids   = array_keys($field_data);

		$acl = $this->access_control($user_ids, $fields);
		foreach ($user_ids as $user_id)
		{
			foreach ($fields as $field)
			{
				if (!isset($acl[$user_id][$field]))
				{
					$field_data[$user_id][$field] = '';
				}
			}
		}
		$event['field_data'] = $field_data;
	}

	/**
	 * Filters non profile fields from view profile page
	 *
	 * @param $event
	 * @return void
	 */
	public function filter_profile_page($event)
	{
		// Show everything to mods & admins
		if ($this->auth->acl_gets('a_', 'm_') || $this->auth->acl_getf_global('m_'))
		{
			return;
		}
		$user_id = $event['data']['user_id'];
		$fields  = ['bday_age', 'online', 'pm', 'email'];
		$acl     = $this->access_control([$user_id], $fields);

		$template = $event['template_data'];
		if (!isset($acl[$user_id]['bday_age']))
		{
			$template['AGE'] = '';
		}

		if (!isset($acl[$user_id]['online']))
		{
			$template['LAST_ACTIVE'] = ' - ';
			$template['S_ONLINE']    = false;
		}

		if (!isset($acl[$user_id]['pm']))
		{
			$template['U_PM'] = '';
		}

		if (!isset($acl[$user_id]['email']))
		{
			$template['U_EMAIL'] = '';
		}

		$event['template_data'] = $template;
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
		$user_ids = [];
		$rows     = $event['rows'];
		foreach ($rows as $row)
		{
			$user_ids[] = $row['user_id'];
		}

		$acl = $this->access_control($user_ids, ['bday_age']);

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

	/**
	 * Filter usernames from the bottom of index, view forum, and view topic pages
	 *
	 * @param $event
	 * @return void
	 */
	public function filter_online($event)
	{
		if ($this->auth->acl_gets('a_', 'm_') || $this->auth->acl_getf_global('m_'))
		{
			return;
		}

		$online_users     = $event['online_users'];
		$user_ids         = array_keys($online_users['online_users']);
		$user_online_link = $event['user_online_link'];

		// Anybody home? Skip if there are no members online
		if (empty($user_ids))
		{
			return;
		}

		$acl = $this->access_control($user_ids, ['online']);

		// Bots don't have a profile entry, we'll need to add them back in
		foreach ($event['rowset'] as $row)
		{
			if ($row['user_type'] == 2)
			{
				$acl[$row['user_id']]['online'] = true;
			}
		}

		// Remove everyone we can't view from the online list
		foreach ($user_ids as $user_id)
		{
			// User is online, but no access to view
			if (!isset($acl[$user_id]))
			{
				// Add to hidden users
				$online_users['hidden_users'][$user_id] = $user_id;

				// Adjust counts
				$online_users['visible_online']--;
				$online_users['hidden_online']++;

				// Remove from displayed list
				unset($user_online_link[$user_id]);
			}
		}

		$event['online_users']     = $online_users;
		$event['user_online_link'] = $user_online_link;
	}

	/**
	 * Filter online status from posts on view topic page
	 *
	 * @param $event
	 * @return void
	 */
	public function filter_online_topic_post($event)
	{
		if ($this->auth->acl_gets('a_', 'm_') || $this->auth->acl_getf_global('m_'))
		{
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

	/**
	 * Filter usernames from the view online page
	 *
	 * @param $event
	 * @return void
	 */
	public function filter_online_view($event)
	{
		// Bypass bots, and guests
		if ($event['row']['user_type'] == 2)
		{
			return;
		}

		if ($this->auth->acl_gets('a_', 'm_') || $this->auth->acl_getf_global('m_'))
		{
			return;
		}

		$user_id = $event['row']['user_id'];

		$acl = $this->access_control([$user_id], ['online']);
		if (!isset($acl[$user_id]))
		{
			// We can't stop phpBB from adding this row to the template block
			// The best we can do is blank out the usernames
			$template                   = $event['template_row'];
			$template['USERNAME']       = '******';
			$template['USERNAME_FULL']  = '******';
			$template['USERNAME_COLOR'] = '';
			$event['template_row']      = $template;
		}
	}

	/**
	 * Filter online status on view private message page
	 *
	 * @param $event
	 * @return void
	 */
	public function filter_pm_view($event)
	{
		if ($this->auth->acl_gets('a_', 'm_') || $this->auth->acl_getf_global('m_'))
		{
			return;
		}

		$user_id   = $event['message_row']['user_id'];
		$msg_data  = $event['msg_data'];
		$user_info = $event['user_info'];

		$acl = $this->access_control([$user_id], ['online', 'pm', 'email']);
		if (!isset($acl[$user_id]['online']))
		{
			$msg_data['S_ONLINE']   = false;
			$msg_data['ONLINE_IMG'] = '';
		}

		// This doesn't really work
		if (!isset($acl[$user_id]['pm']))
		{
			$msg_data['U_PM'] = '';
		}

		if (!isset($acl[$user_id]['email']))
		{
			$user_info['email'] = '';
		}
		$event['msg_data']  = $msg_data;
		$event['user_info'] = $user_info;
	}

	/**
	 * Remove recipients from private message based on privacy settings
	 *
	 * @param $event
	 * @return void
	 */
	public function filter_pm_receiving($event)
	{
		// Skip admins and mods as always
		if ($this->auth->acl_gets('a_', 'm_') || $this->auth->acl_getf_global('m_'))
		{
			return;
		}

		// Skip address lists with no users in it
		if (!isset($event['address_list']['u']))
		{
			return;
		}

		$address_list = $event['address_list'];
		$user_ids     = array_keys($address_list['u']);
		$err_ids      = [];

		$acl = $this->access_control($user_ids, ['pm']);

		// Remove user ids from address list
		foreach ($user_ids as $user_id)
		{
			if (!isset($acl[$user_id]))
			{
				unset($address_list['u'][$user_id]);
				$err_ids[] = $user_id;
			}
		}

		/*
		 *  And now we reverse the access control. PM blocks work both ways. If one user blocks another, then neither of them PM each other
		 */

		// Update user_ids in case any were removed
		$user_ids = array_keys($address_list['u']);

		// Only send PMs to recipients that can reply
		$acl = $this->reverse_pm_control($user_ids);

		$reverse_blocked = [];
		foreach ($user_ids as $user_id)
		{
			if (!isset($acl[$user_id]))
			{
				unset($address_list['u'][$user_id]);
				$err_ids[] = $user_id;

				$reverse_blocked[$user_id] = true;
			}
		}

		// Make an error message for each removed address list entry
		if (!empty($err_ids || !empty($reverse_err_ids)))
		{
			$sql = 'SELECT user_id,username
					FROM ' . USERS_TABLE . '
					WHERE ' . $this->db->sql_in_set('user_id', $err_ids);

			$result = $this->db->sql_query($sql);

			$err = $event['error'];
			while ($row = $this->db->sql_fetchrow($result))
			{
				if (!isset($reverse_blocked[$row['user_id']]))
				{
					$err[] = $this->language->lang('UCP_PROFILEPRIVACY_PM_DENIED', $row['username']);
				}
				else
				{
					$err[] = $this->language->lang('UCP_PROFILEPRIVACY_PM_DENIED_REVERSE', $row['username']);
				}
			}
			$this->db->sql_freeresult($result);

			$event['error']        = $err;
			$event['address_list'] = $address_list;
		}
	}

	/**
	 * Apply privacy settings to contact fields on view topic page
	 *
	 * @param $event
	 * @return void
	 */
	public function filter_viewtopic_contact($event)
	{
		$post_row   = $event['post_row'];
		$user_cache = $event['user_cache'];
		$user_id    = $event['poster_id'];

		$acl = $this->access_control($user_id, ['pm', 'email']);

		if (!isset($acl[$user_id]['pm']))
		{
			$post_row['U_PM'] = '';
		}
		if (!isset($acl[$user_id]['email']))
		{
			$user_cache[$user_id]['email'] = '';
		}

		$event['post_row']   = $post_row;
		$event['user_cache'] = $user_cache;
	}

	/**
	 * Create a multidimensional array of user ids and profile fields the current user can view
	 *
	 * @param int[]    $user_ids Array of user ids
	 * @param string[] $fields   Array of columns to
	 * @return array An array of fields the user can view [user id] => [profile field] => true
	 */
	private function access_control($user_ids, $fields)
	{
		if (empty($user_ids || empty($fields)))
			return [];

		// Build friend or foe list
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
					$foe_list[$row['user_id']] = true;
				}
				else if ($row['friend'] === '1')
				{
					$friend_list[$row['user_id']] = true;
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
				foreach ($row as $key => $data)
				{
					$acl[$this->uid][$key] = true;
				}
				continue;
			}

			// Skip foes
			if (isset($foe_list[$row['user_id']]))
			{
				continue;
			}

			foreach ($fields as $field)
			{
				switch ($row[$field])
				{
					case 0:
						$acl[$row['user_id']][$field] = true;
					break;

					case 1:
						if ($this->uid > 1)
						{
							$acl[$row['user_id']][$field] = true;
						}
					break;

					case 2:
						if (isset($friend_list[$row['user_id']]))
						{
							$acl[$row['user_id']][$field] = true;
						}
					break;

					default:
					break;
				}
			}
		}
		return $acl;
	}

	/**
	 * Check if other users can reply to a pm sent to them
	 *
	 * @param $user_ids
	 * @return array
	 */
	private function reverse_pm_control($user_ids)
	{
		if (empty($user_ids))
			return [];

		// Build friend or foe list
		$friend_list = [];
		$foe_list    = [];
		if ($this->uid > 1)
		{
			$sql = 'SELECT zebra_id,friend,foe FROM ' . ZEBRA_TABLE . '
				WHERE user_id = ' . $this->uid . '
				AND ' . $this->db->sql_in_set('zebra_id', $user_ids);

			$result = $this->db->sql_query($sql);
			while ($row = $this->db->sql_fetchrow($result))
			{
				if ($row['foe'] === '1')
				{
					$foe_list[$row['zebra_id']] = true;
				}
				else if ($row['friend'] === '1')
				{
					$friend_list[$row['zebra_id']] = true;
				}
			}
			$this->db->sql_freeresult($result);
		}

		// Fetch privacy settings
		$sql     = 'SELECT pm FROM ' . $this->table . ' WHERE user_id = ' . $this->uid;
		$result  = $this->db->sql_query($sql);
		$setting = $this->db->sql_fetchfield('pm');
		$this->db->sql_freeresult($result);

		// Access Control List
		$acl = [];
		foreach ($user_ids as $user_id)
		{
			// Approve own profile
			if ($this->uid == $user_id)
			{
				$acl[$this->uid] = true;
				continue;
			}

			// Skip foes
			if (isset($foe_list[$user_id]))
			{
				continue;
			}

			switch ($setting)
			{
				// Members
				case 1:
					$acl[$user_id] = true;
				break;

				// Friends
				case 2:
					if (isset($friend_list[$user_id]))
					{
						$acl[$user_id] = true;
					}
				break;

				// Guests (0) & Hidden (3)
				default:
				break;
			}
		}
		return $acl;
	}
	// Todo: same group members setting
}
