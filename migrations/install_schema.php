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
	public function effectively_installed(): bool
	{
		return $this->db_tools->sql_table_exists($this->table_prefix . 'profile_privacy_ext');
	}

	/**
	 * Returns array of dependencies to run beforehand
	 *
	 * @return string[]
	 */
	public static function depends_on(): array
	{
		return ['\phpbb\db\migration\data\v320\v320'];
	}

	/**
	 * Add a new table and a few columns. More columns to be added later
	 *
	 * @return array[] Array of schema changes
	 */
	public function update_schema(): array
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
	 * @return array[] Array of schema changes
	 */
	public function revert_schema(): array
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
	 * @return array[] Array of changes to run
	 */
	public function update_data(): array
	{
		return [
			['custom', [[$this, 'build_table']]],
		];
	}

	/**
	 * Add profile columns to table and add defaults for all users
	 *
	 * @return bool
	 */
	public function build_table(): bool
	{
		$table = $this->table_prefix . 'profile_privacy_ext';

		// Clone profile field columns from field data table to new privacy table
		$columns = $this->db_tools->sql_list_columns(PROFILE_FIELDS_DATA_TABLE);
		foreach ($columns as $column)
		{
			if ($column == 'user_id')
			{
				continue;
			}
			$this->db_tools->sql_column_add($table, $column, ['UINT', 0]);
		}

		// Add default for anonymous user
		$this->db->sql_multi_insert($table, ['user_id' => 1]);

		// Get all normal users and...
		$sql    = 'SELECT user_id FROM ' . USERS_TABLE . ' WHERE user_type != ' . USER_IGNORE;
		$offset = 0;
		$limit  = 1000;
		$result = $this->db->sql_query_limit($sql, $limit, $offset);

		// ...shove into table
		while ($rows = $this->db->sql_fetchrowset($result))
		{
			$this->db->sql_multi_insert($table, $rows);
			$offset += $limit;
			$result = $this->db->sql_query_limit($sql, $limit, $offset);
		}
		return true;
	}
}
