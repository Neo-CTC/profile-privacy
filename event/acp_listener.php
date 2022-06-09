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
use phpbb\db\tools\tools_interface;

/**
 *
 */
class acp_listener implements EventSubscriberInterface
{
	/**
	 * Return events to listen to
	 *
	 * @return string[]
	 */
	public static function getSubscribedEvents()
	{
		return [
			'core.acp_profile_create_edit_save_before' => 'modify_profile_columns',
			// Todo we need a new event for phpBB
			// 'core.acp_profile_delete_before' => 'delete_profile_columns',
			// But until then, we'll double check for deleted columns on each visit the the acp profile page
			'core.acp_profile_action'                  => 'check_columns',
		];
	}

	protected $db_tools;
	protected $table;

	public function __construct(tools_interface $tools)
	{
		$this->db_tools = $tools;

		global $table_prefix;
		$this->table = $table_prefix . 'profile_privacy_ext';
	}

	/**
	 * Adds a new column to the privacy table when creating a new profile field
	 *
	 * @param $event
	 * @return void
	 */
	public function modify_profile_columns($event)
	{
		$fid = $event['field_data']['field_ident'];
		$action = $event['action'];

		// We only care about creating a field since the field identifier never changes
		if ($action == 'create')
		{
			$this->db_tools->sql_column_add($this->table, 'pf_' . $fid, ['UINT', 1]);
		}
	}

	/**
	 * @param $event
	 * @return void
	 */
	public function check_columns($event)
	{
		$phpbb_columns = $this->db_tools->sql_list_columns(PROFILE_FIELDS_DATA_TABLE);
		$my_columns = $this->db_tools->sql_list_columns($this->table);
		$extra_columns = array_diff($my_columns, $phpbb_columns);
		foreach ($extra_columns as $column)
		{
			// Don't delete the birthday column
			if ($column == 'bday_age')
			{
				continue;
			}
			$this->db_tools->sql_column_remove($this->table, $column);
		}
	}
}
