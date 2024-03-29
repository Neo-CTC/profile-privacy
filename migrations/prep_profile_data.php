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
 * Setup ucp module
 */
class prep_profile_data extends migration
{
	/**
	 * Return array of dependencies
	 *
	 * @return string[]
	 */
	public static function depends_on(): array
	{
		return [
			'\crosstimecafe\profileprivacy\migrations\install_ucp_module',
			'\crosstimecafe\profileprivacy\migrations\install_schema_v2',
		];
	}

	/**
	 * Update the thing
	 *
	 * @return array[]
	 */
	public function update_data(): array
	{
		return [
			[
				'custom', [
					[$this, 'prep_data'],
				],
			],
		];
	}

	// Fill in privacy table for all current users
	public function prep_data()
	{
		$table = $this->table_prefix . 'profile_privacy_ext';

		// SQL join to find all users without a privacy entry due to an error in install_schema
		$sql_array   = [
			'SELECT'    => 'u.user_id',
			'FROM'      => [
				USERS_TABLE => 'u',
			],
			'LEFT_JOIN' => [
				[
					'FROM' => [$table => 'p'],
					'ON'   => 'u.user_id = p.user_id',
				],
			],
			'WHERE'     => 'u.user_type != ' . USER_IGNORE . ' AND p.user_id IS NULL',
			'ORDER_BY'  => 'u.user_id',
		];
		$sql         = $this->db->sql_build_query('SELECT', $sql_array);
		$limit       = 500;
		$join_result = $this->db->sql_query_limit($sql, $limit);

		while ($rows = $this->db->sql_fetchrowset($join_result))
		{
			$this->db->sql_multi_insert($table, $rows);
			$this->db->sql_freeresult($join_result);
			$join_result = $this->db->sql_query_limit($sql, $limit);
		}
	}
}
