<?php
/**
 *
 * Profile Privacy. An extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2022, Neo
 * @license       GNU General Public License, version 2 (GPL-2.0)
 *
 */

if (!defined('IN_PHPBB'))
{
	exit;
}

if (empty($lang) || !is_array($lang))
{
	$lang = [];
}

$lang = array_merge($lang, [
	'UCP_PROFILEPRIVACY_EDIT'            => 'Edit profile privacy settings',
	'UCP_PROFILEPRIVACY_TITLE'           => 'Profile Privacy Module',
	'UCP_PROFILEPRIVACY_EXPLAIN'         => 'These settings allow you to control who can view your profile fields. Please note this can not and should not be used to protect highly sensitive personal information. If you must share sensitive information, use a private channel such as private messages or email.<br>Administrators and moderators can always view all fields',
	'UCP_PROFILEPRIVACY_SETTING_FIELD'   => 'Field',
	'UCP_PROFILEPRIVACY_SETTING_PUBLIC'  => 'Public',
	'UCP_PROFILEPRIVACY_SETTING_MEMBERS' => 'Members',
	'UCP_PROFILEPRIVACY_SETTING_FRIENDS' => 'Friends',
	'UCP_PROFILEPRIVACY_SETTING_HIDDEN'  => 'Hidden',
]);
