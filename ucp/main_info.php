<?php
/**
 *
 * Profile Privacy. An extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2022, Neo
 * @license       GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace crosstimecafe\profileprivacy\ucp;

/**
 * Main ucp module info
 *
 */
class main_info
{
	/**
	 * Return module setup options
	 *
	 * @return array
	 */
	public function module()
	{
		return [
			'filename' => '\crosstimecafe\profileprivacy\ucp\main_module',
			'title'    => 'UCP_PROFILEPRIVACY_TITLE',
			'modes'    => [
				'settings' => [
					'title' => 'UCP_PROFILEPRIVACY_EDIT',
					'auth'  => 'ext_crosstimecafe/profileprivacy',
					'cat'   => ['UCP_PROFILE'],
				],
			],
		];
	}
}
