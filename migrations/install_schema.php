<?php
/**
 *
 * Profile Privacy. An extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2022, Neo
 * @license       GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace crosstimecafe\profileprivacy\migrations;

use phpbb\db\migration\migration;

/**
 * Prepares the database
 */
class install_schema extends migration
{
	/**
	 * Check if the database is already prepared
	 *
	 * @return bool
	 */
	public function effectively_installed()
	{
		return $this->db_tools->sql_table_exists($this->table_prefix . 'crosstimecafe_profileprivacy');
	}

	/**
	 * Return array of dependencies
	 *
	 * @return string[]
	 */
	public static function depends_on()
	{
		return ['\phpbb\db\migration\data\v320\v320'];
	}

	/**
	 * Add a new table
	 *
	 * @return array Array of schema changes
	 */
	public function update_schema()
	{
		return [
			'add_tables' => [
				$this->table_prefix . 'profileprivacy' => [
					'COLUMNS'     => [
						'user_id' => ['UINT', null],
					],
					'PRIMARY_KEY' => 'user_id',
				],
			],
		];
	}

	/**
	 * Drop the table
	 *
	 * @return array Array of schema changes
	 */
	public function revert_schema()
	{
		return [
			'drop_tables' => [
				$this->table_prefix . 'profileprivacy',
			],
		];
	}

	/**
	 * Tell phpbb we want to use a function to fill the database with data
	 *
	 * @return array[]
	 */
	public function update_data()
	{
		return [
			['custom', [[$this, 'build_table']]],
		];
	}

	/**
	 * Add columns to table and then fill with default user data
	 *
	 * @return bool
	 */
	public function build_table()
	{
		// Clone profile field columns from field data table to new privacy table
		$columns = $this->db_tools->sql_list_columns(PROFILE_FIELDS_DATA_TABLE);
		foreach ($columns as $column)
		{
			if ($column == 'user_id')
			{
				continue;
			}
			$this->db_tools->sql_column_add($this->table_prefix . 'profileprivacy', $column, ['UINT', 1]);
		}


		// Add defaults for users currently in data table
		$sql = 'SELECT user_id FROM ' . PROFILE_FIELDS_DATA_TABLE; // Todo add limits in case of forum with 10,000's of users
		$result = $this->db->sql_query($sql);

		$users = [];
		while ($row = $this->db->sql_fetchrow($result))
		{
			$users[] = [
				'user_id' => $row['user_id']
			];
		}
		$this->db->sql_freeresult($result);

		if (!empty($users))
		{
			$this->db->sql_multi_insert($this->table_prefix . 'profileprivacy', $users);
		}

		return true;
	}
}
