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

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use phpbb\db\driver\driver_interface;
use phpbb\user;

/**
 * Class for listing to events in the UCP
 */
class ucp_listener implements EventSubscriberInterface
{
	/**
	 * @return string[]
	 */
	public static function getSubscribedEvents()
	{
		return [
			'core.ucp_profile_modify_profile_info' => 'create_profile_entry',
		];
	}

	private $db;
	private $user;
	private $table;

	public function __construct(driver_interface $db, user $user)
	{
		$this->db   = $db;
		$this->user = $user;

		global $table_prefix;
		$this->table = $table_prefix . 'profile_privacy_ext';
	}

	/**
	 * Creates an entry for the user in the privacy settings table if missing
	 *
	 * @param $event
	 * @return void
	 */
	public function create_profile_entry($event)
	{
		$sql       = 'SELECT user_id FROM ' . $this->table . ' WHERE user_id = ' . $this->user->id();
		$result    = $this->db->sql_query($sql);
		$has_entry = $this->db->sql_fetchfield('user_id', 0, $result);
		$this->db->sql_freeresult($result);

		if ($has_entry === false)
		{
			$sql = 'INSERT INTO ' . $this->table . ' ' . $this->db->sql_build_array('INSERT', ['user_id' => $this->user->id()]);
			$this->db->sql_query($sql);
		}
	}
}
