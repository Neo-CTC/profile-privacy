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
		return $this->db_tools->sql_table_exists($this->table_prefix . 'profile_privacy_ext');
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
				$this->table_prefix . 'profile_privacy_ext' => [
					// Be sure to also update the column list in acp_listener.php
					'COLUMNS'     => [
						'user_id'  => ['UINT', 0],
						'bday_age' => ['UINT', 0],
						'online'   => ['UINT', 0],
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
				$this->table_prefix . 'profile_privacy_ext',
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
			$this->db_tools->sql_column_add($this->table_prefix . 'profile_privacy_ext', $column, ['UINT', 0]);
		}


		// Get total count of users
		$sql    = 'SELECT COUNT(user_id) AS total FROM ' . PROFILE_FIELDS_DATA_TABLE;
		$result = $this->db->sql_query($sql);
		$count  = $this->db->sql_fetchfield('total', 0, $result);

		// Add default for anonymous user
		$this->db->sql_multi_insert($this->table_prefix . 'profile_privacy_ext', ['user_id' => 1]);

		// Add defaults for users
		for ($i = 0; $i < $count; $i += 500)
		{
			$sql    = 'SELECT user_id FROM ' . PROFILE_FIELDS_DATA_TABLE;
			$result = $this->db->sql_query_limit($sql, 500, $i);

			$users = [];
			while ($row = $this->db->sql_fetchrow($result))
			{
				$users[] = [
					'user_id' => $row['user_id'],
				];
			}
			$this->db->sql_freeresult($result);

			if (!empty($users))
			{
				$this->db->sql_multi_insert($this->table_prefix . 'profile_privacy_ext', $users);
			}
		}
		return true;
	}
}
