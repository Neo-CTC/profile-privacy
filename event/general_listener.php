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
			'core.grab_profile_fields_data' => 'filter_profile_fields',
		];
	}

	protected $db;
	protected $user;
	protected $auth;
	protected $table;

	public function __construct(driver_interface $db, user $user, auth $auth)
	{
		$this->db = $db;
		$this->user = $user;
		$this->auth = $auth;

		global $table_prefix;
		$this->table = $table_prefix . 'profileprivacy';
	}

	/**
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

		// Get user id for registered members and skip bots
		if ($this->user->data['is_registered'] && !$this->user->data['is_bot'])
		{
			$self_uid = $this->user->id();
		}
		else
		{
			$self_uid = 1;
		}

		$user_ids = $event['user_ids'];


		//Fetch user privacy settings for all users on page
		$sql = 'SELECT * 
			FROM ' . $this->table . '
			WHERE ' . $this->db->sql_in_set('user_id', $user_ids);
		$result = $this->db->sql_query($sql);

		// Each user
		while ($row = $this->db->sql_fetchrow($result))
		{
			$uid = $row['user_id'];

			// Skip if viewing own profile
			if ($self_uid == $uid)
			{
				continue;
			}

			// Does the post author have the current user listed as a friend?
			$is_friend_of = $self_uid != 1 ? $this->reverse_zebra($uid, $self_uid) : 0;

			// Each user's privacy setting
			foreach ($row as $key => $value)
			{
				if ($key == 'user_id')
				{
					continue;
				}

				// Hide everything from foes
				// Todo might change this
				if ($is_friend_of === -1)
				{
					$fd = $event['field_data'];
					$fd[$uid][$key] = '';
					$event['field_data'] = $fd;
				}

				else
				{
					switch ($value)
					{
						// Guest, field is open to all, do nothing
						case 0:
						break;

						// Members
						case 1:
							if ($self_uid === 1)
							{
								$fd = $event['field_data'];
								$fd[$uid][$key] = '';
								$event['field_data'] = $fd;
							}
						break;

						// Friends
						case 2:
							if ($is_friend_of !== 1)
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
	 *
	 * @param $user
	 * @param $zebra
	 * @return int
	 */
	private function reverse_zebra($user, $zebra): int
	{
		$sql = 'SELECT friend,foe
				FROM ' . ZEBRA_TABLE . "
				WHERE user_id = $user AND zebra_id = $zebra";
		$result = $this->db->sql_query($sql);
		$row = $this->db->sql_fetchrow($result);
		if ($row === false)
		{
			return 0;
		}

		if ($row['foe'] === '1')
		{
			return -1;
		}
		elseif ($row['friend'] === '1')
		{
			return 1;
		}
		else
		{
			return 0;
		}
	}
}