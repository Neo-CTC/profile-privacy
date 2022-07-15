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
class install_schema_v2 extends migration
{
	/**
	 * Check if the database is already prepared
	 *
	 * @return bool
	 */
	public function effectively_installed()
	{
		return $this->db_tools->sql_column_exists($this->table_prefix . 'profile_privacy_ext', 'pm');
	}

	/**
	 * Return array of dependencies
	 *
	 * @return string[]
	 */
	public static function depends_on()
	{
		return ['\crosstimecafe\profileprivacy\migrations\install_schema'];
	}

	/**
	 * Add a new column
	 *
	 * @return array Array of schema changes
	 */
	public function update_schema()
	{
		return [
			'add_columns' => [
				$this->table_prefix . 'profile_privacy_ext' => [
					// Be sure to also update the column list in acp_listener.php
					'pm'    => ['UINT', 1, 'after' => 'online'],
					'email' => ['UINT', 1, 'after' => 'pm'],
				],
			],
		];
	}
}
