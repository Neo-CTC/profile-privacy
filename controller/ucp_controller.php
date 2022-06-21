<?php
/**
 *
 * Profile Privacy. An extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2022, Neo
 * @license       GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace crosstimecafe\profileprivacy\controller;

use phpbb\db\driver\driver_interface;
use phpbb\db\tools\tools_interface;
use phpbb\language\language;
use phpbb\request\request;
use phpbb\template\template;
use phpbb\user;

/**
 * Profile Privacy UCP controller.
 */
class ucp_controller
{
	private $db;
	private $language;
	private $request;
	private $template;
	private $user;

	private $table;
	private $u_action;
	private $db_tools;

	private $uid;

	public function __construct(driver_interface $db, language $language, request $request, template $template, user $user, tools_interface $tools)
	{
		$this->db       = $db;
		$this->language = $language;
		$this->request  = $request;
		$this->template = $template;
		$this->user     = $user;
		$this->db_tools = $tools;

		global $table_prefix;
		$this->table = $table_prefix . 'profile_privacy_ext';

		$this->uid = $this->user->id();
	}

	/**
	 * Display the options a user can configure for this extension.
	 *
	 * @return void
	 */
	public function display_options()
	{
		// Create a form key for preventing CSRF attacks
		add_form_key('crosstimecafe_profileprivacy_ucp');

		// Create an array to collect errors that will be output to the user
		$errors = [];

		// Is the form being submitted to us?
		if ($this->request->is_set_post('submit'))
		{
			// Test if the submitted form is valid
			if (!check_form_key('crosstimecafe_profileprivacy_ucp'))
			{
				$errors[] = $this->language->lang('FORM_INVALID');
			}

			$data = [];

			// Load column names
			$columns = $this->db_tools->sql_list_columns($this->table);

			// Loop over column names
			foreach ($columns as $column)
			{
				if ($column == 'user_id')
				{
					continue;
				}
				$v = $this->request->variable($column, 1);
				if ($v < 0 or $v > 3)
				{
					$v = 3;
				}
				$data[$column] = $v;
			}

			// If no errors, process the form data
			if (empty($errors))
			{
				$sql = 'UPDATE ' . $this->table .
					' SET ' . $this->db->sql_build_array('UPDATE', $data) .
					' WHERE user_id = ' . $this->uid;
				$this->db->sql_query($sql);

				// Option settings have been updated
				// Confirm this to the user and provide (automated) link back to previous page
				meta_refresh(3, $this->u_action);
				$message = $this->language->lang('PREFERENCES_UPDATED') . '<br /><br />' . $this->language->lang('RETURN_UCP', '<a href="' . $this->u_action . '">', '</a>');
				trigger_error($message);
			}
		}

		// Fetch all profile fields
		$this->generate_profile_fields($this->user->get_iso_lang_id());

		$s_errors = !empty($errors);

		// Set output variables for display in the template
		$this->template->assign_vars([
			'S_ERROR'   => $s_errors,
			'ERROR_MSG' => $s_errors ? implode('<br />', $errors) : '',

			'U_UCP_ACTION' => $this->u_action,
		]);
	}

	/**
	 * Set custom form action.
	 *
	 * @param string $u_action Custom form action
	 * @return void
	 */
	public function set_page_url($u_action)
	{
		$this->u_action = $u_action;
	}

	/**
	 * Generate the settings template
	 * Recreating function from phpbb/profilefields/manager.php for finer control over template
	 *
	 * @param int $lang_id
	 * @return void
	 */
	public function generate_profile_fields($lang_id)
	{
		// Limit fields to only those that are visible. For admin and mods, the can already see all
		// fields. Thus, I don't think they need to bypass the visibility here.
		$sql_where = ' AND f.field_show_profile = 1';

		// Fetch profile fields
		$sql    = 'SELECT l.*, f.*' .
			' FROM ' . PROFILE_LANG_TABLE . ' l, ' . PROFILE_FIELDS_TABLE . ' f' .
			' WHERE l.field_id = f.field_id AND f.field_active = 1 AND l.lang_id = ' . $lang_id . $sql_where .
			' ORDER BY f.field_order ASC';
		$result = $this->db->sql_query($sql);

		$fields = $this->db->sql_fetchrowset($result);
		$this->db->sql_freeresult($result);

		// Fetch current field settings
		$sql            = 'SELECT * FROM ' . $this->table . ' WHERE user_id = ' . $this->uid;
		$result         = $this->db->sql_query($sql);
		$field_settings = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);

		// But first, let's add in a few non-profile options
		$this->template->assign_block_vars('board_fields', [
			'FIELD_ID'      => 'online',
			'LANG_NAME'     => $this->language->lang('UCP_PROFILEPRIVACY_SETTING_ONLINE'),
			'FIELD_SETTING' => $field_settings['online'],
		]);
		$this->template->assign_block_vars('board_fields', [
			'FIELD_ID'      => 'bday_age',
			'LANG_NAME'     => $this->language->lang('BIRTHDAY'),
			'FIELD_SETTING' => $field_settings['bday_age'],
		]);

		foreach ($fields as $field)
		{
			$field['field_ident'] = 'pf_' . $field['field_ident'];
			$this->template->assign_block_vars('profile_fields', [
				'FIELD_ID'      => $field['field_ident'],
				'LANG_NAME'     => $this->language->lang($field['lang_name']),
				'FIELD_SETTING' => $field_settings[$field['field_ident']],
			]);
		}
	}
}
