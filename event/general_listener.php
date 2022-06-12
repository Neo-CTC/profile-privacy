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

use Exception;
use phpbb\log\log;
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
			'core.grab_profile_fields_data'        => 'filter_profile_fields',
			'core.memberlist_prepare_profile_data' => 'filter_age',
			'core.index_modify_birthdays_list'     => 'filter_age_front_page',
		];
	}

	private $db;
	private $user;
	private $auth;
	private $table;
	private $log;

	public function __construct(driver_interface $db, user $user, auth $auth, log $log)
	{
		$this->db = $db;
		$this->user = $user;
		$this->auth = $auth;
		$this->log = $log;

		global $table_prefix;
		$this->table = $table_prefix . 'profile_privacy_ext';
	}

	/**
	 * @param $event
	 * @return void
	 */
	public function filter_profile_fields($event)
	{
		// Just in case
		if (empty($event['user_ids']))
		{
			return;
		}

		// Show everything to mods & admins
		if ($this->auth->acl_gets('a_', 'm_') || $this->auth->acl_getf_global('m_'))
		{
			return;
		}

		// Get user id for registered members and skip bots
		if ($this->user->data['is_registered'] && !$this->user->data['is_bot'])
		{
			$user_id = $this->user->id();
		}
		else
		{
			$user_id = 1;
		}

		// As a failsafe, clear all data, then reapply
		$original_data = $event['field_data'];
		$new_data = [];
		$event['field_data'] = [];


		// Fetch user privacy settings for all users on page
		$sql = 'SELECT * FROM ' . $this->table . ' WHERE ' . $this->db->sql_in_set('user_id', $event['user_ids']);
		try
		{
			$result = $this->run_sql($sql);
		}
		catch (Exception $exception)
		{
			// Exception failsafe; field data is already cleared; stop and return this way we don't crash the whole page
			return;
		}


		// Each user
		while ($row = $this->db->sql_fetchrow($result))
		{
			$profile_id = $row['user_id'];

			// Copy everything if viewing own profile
			if ($user_id == $profile_id)
			{
				$new_data[$profile_id] = $original_data[$profile_id];
			}

			// Does the profile we are viewing have the user listed as a friend?
			$is_friend_of = $user_id != 1 ? $this->reverse_zebra($profile_id, $user_id) : 0;

			// Each profile's privacy setting
			foreach ($row as $field => $data)
			{
				if ($field == 'user_id')
				{
					continue;
				}

				// Hide everything from foes
				// Todo might change this
				if ($is_friend_of === -1)
				{
					continue;
				}

				else
				{
					switch ($data)
					{
						// Public
						case 0:
							$new_data[$profile_id][$field] = $original_data[$profile_id][$field];
						break;

						// Members
						case 1:
							if ($user_id > 1)
							{
								$new_data[$profile_id][$field] = $original_data[$profile_id][$field];
							}
						break;

						// Friends
						case 2:
							if ($is_friend_of === 1)
							{
								$new_data[$profile_id][$field] = $original_data[$profile_id][$field];
							}
						break;

						// Nobody
						case 3:
						default:
						break;
					}
				}
			}
		}
		$this->db->sql_freeresult($result);

		// Copy filtered data back into event space
		$event['field_data'] = $new_data;
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

		// Get user id for registered members and skip bots
		if ($this->user->data['is_registered'] && !$this->user->data['is_bot'])
		{
			$user_id = $this->user->id();
		}
		else
		{
			$user_id = 1;
		}

		$profile_id = $event['data']['user_id'];

		// Skip if viewing own profile
		if ($user_id == $profile_id)
		{
			return;
		}

		// As a failsafe, clear age, then reapply later
		$original_data = $event['template_data']['AGE']; // Store for later use
		$new_data = $event['template_data'];  // Copy overloaded array
		$new_data['AGE'] = '';  // Now we can clear the age
		$event['template_data'] = $new_data;  // Shove updated template back into overloaded event

		// Fetch user privacy settings the one user
		$sql = 'SELECT bday_age FROM ' . $this->table . ' WHERE user_id = ' . $profile_id;
		try
		{
			$result = $this->run_sql($sql);
		}
		catch (Exception $exception)
		{
			return;
		}
		$value = $this->db->sql_fetchfield('bday_age', 0, $result);
		$this->db->sql_freeresult($result);

		if ($value !== false)
		{
			// Does the post author have the current user listed as a friend?
			$is_friend_of = $user_id != 1 ? $this->reverse_zebra($profile_id, $user_id) : 0;

			// Hide everything from foes
			// Todo might change this
			if ($is_friend_of === -1)
			{
				return;
			}

			else
			{
				switch ($value)
				{
					// Guest, field is open to all, do nothing
					case 0:
						$new_data['AGE'] = $original_data;
					break;

					// Members
					case 1:
						if ($user_id > 1)
						{
							$new_data['AGE'] = $original_data;
						}
					break;

					// Friends
					case 2:
						if ($is_friend_of === 1)
						{
							$new_data['AGE'] = $original_data;
						}
					break;

					// Nobody
					case 3:
					default:
					break;
				}
			}
		}
		// One last copy of data into the overloaded array
		$event['template_data'] = $new_data;
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

		// Get user id for registered members and skip bots
		if ($this->user->data['is_registered'] && !$this->user->data['is_bot'])
		{
			$user_id = $this->user->id();
		}
		else
		{
			$user_id = 1;
		}

		// Fetch user ids from list
		$profile_ids = [];
		foreach ($event['rows'] as $row)
		{
			$profile_ids[] = $row['user_id'];
		}

		// As a failsafe, clear birthday list, then reapply later
		$original_data = $event['birthdays'];
		$new_data = [];
		$event['birthdays'] = [];

		// Fetch user privacy settings for users
		$sql = 'SELECT user_id,bday_age FROM ' . $this->table . ' WHERE ' . $this->db->sql_in_set('user_id', $profile_ids);
		try
		{
			$result = $this->run_sql($sql);
		}
		catch (Exception $exception)
		{
			return;
		}

		// Store result to associative array for quick access
		$profile_settings = [];
		while ($row = $this->db->sql_fetchrow($result))
		{
			$profile_settings[$row['user_id']] = $row['bday_age'];
		}
		$this->db->sql_freeresult($result);

		// Each user
		foreach ($event['rows'] as $index => $data)
		{
			$profile_id = $data['user_id'];

			// Skip if viewing own profile
			if ($user_id == $profile_id)
			{
				$new_data[] = $original_data[$index];
				continue;
			}

			// Does the profile have the user listed as a friend?
			$is_friend_of = $user_id != 1 ? $this->reverse_zebra($profile_id, $user_id) : 0;

			// Hide everything from foes
			// Todo might change this
			if ($is_friend_of === -1)
			{
				continue;
			}
			else
			{
				switch ($profile_settings[$profile_id])
				{
					// Guest
					case 0:
						$new_data[] = $original_data[$index];
					break;

					// Members
					case 1:
						if ($user_id > 1)
						{
							$new_data[] = $original_data[$index];
						}
					break;

					// Friends
					case 2:
						if ($is_friend_of === 1)
						{
							$new_data[] = $original_data[$index];
						}
					break;

					// Nobody
					case 3:
					break;
				}
			}
		}
		$event['birthdays'] = $new_data;
	}

	/**
	 * Run a sql query with error handling
	 *
	 * @param string $sql
	 * @return mixed
	 * @throws \Exception
	 */
	private function run_sql($sql)
	{
		// By pass phpBB's sql error handler
		$this->db->sql_return_on_error(true);

		$result = $this->db->sql_query($sql);

		if ($this->db->get_sql_error_triggered())
		{
			// Give me that error array
			$sql_err = $this->db->get_sql_error_returned();

			// Okay phpBB you can have control back
			$this->db->sql_return_on_error();

			$err = new Exception($sql_err['message']);

			// We only care about who called this function
			$trace = $err->getTrace()[0];

			$err_message = '<strong>' . $sql_err['message'] . '</strong><br><br>' . htmlspecialchars($sql) . '<br><br>' . $trace['file'] . ':' . $trace['line'];

			// Add log entry; log_operation is an ACP language entry; $err_message is appended to log_operation via placeholders
			$this->log->add('critical', $this->user->id(), $this->user->ip, 'ACP_PROFILEPRIVACY_LOG_ERROR_SQL', false, [$err_message]);

			throw $err;
		}
		// Okay phpBB you can have control back
		$this->db->sql_return_on_error();
		return $result;
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
		$sql = 'SELECT friend,foe FROM ' . ZEBRA_TABLE . ' WHERE user_id = ' . $user . ' AND zebra_id = ' . $zebra;
		try
		{
			$result = $this->run_sql($sql);
		}
		catch (Exception $exception)
		{
			return 0;
		}
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
