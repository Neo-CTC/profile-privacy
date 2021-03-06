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
class install_ucp_module extends migration
{
	/**
	 * Check if module installed
	 *
	 * @return bool
	 */
	public function effectively_installed()
	{
		$sql       = 'SELECT module_id
			FROM ' . $this->table_prefix . "modules
			WHERE module_class = 'ucp'
				AND module_langname = 'UCP_PROFILEPRIVACY_TITLE'";
		$result    = $this->db->sql_query($sql);
		$module_id = $this->db->sql_fetchfield('module_id');
		$this->db->sql_freeresult($result);

		return $module_id !== false;
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
	 * Insert module into ucp
	 *
	 * @return array[]
	 */
	public function update_data()
	{
		return [
			[
				'module.add', [
				'ucp',
				'UCP_PROFILE',
				[
					'module_basename' => '\crosstimecafe\profileprivacy\ucp\main_module',
					'modes'           => ['settings'],
				],
			],
			],
		];
	}
}
