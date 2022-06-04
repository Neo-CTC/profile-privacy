<?php
/**
 *
 * Profile Privacy. An extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2022, Neo
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace crosstimecafe\profileprivacy\migrations;

class install_schema extends \phpbb\db\migration\migration
{
    public function effectively_installed()
    {
        return $this->db_tools->sql_table_exists($this->table_prefix . 'crosstimecafe_profileprivacy');
    }

    public static function depends_on()
    {
        return ['\phpbb\db\migration\data\v320\v320'];
    }

    /**
     * Update database schema.
     *
     * https://area51.phpbb.com/docs/dev/3.2.x/migrations/schema_changes.html
     *	add_tables: Add tables
     *	drop_tables: Drop tables
     *	add_columns: Add columns to a table
     *	drop_columns: Removing/Dropping columns
     *	change_columns: Column changes (only type, not name)
     *	add_primary_keys: adding primary keys
     *	add_unique_index: adding an unique index
     *	add_index: adding an index (can be column:index_size if you need to provide size)
     *	drop_keys: Dropping keys
     *
     * This sample migration adds a new column to the users table.
     * It also adds an example of a new table that can hold new data.
     *
     * @return array Array of schema changes
     */
    public function update_schema()
    {
		return [
			'add_tables' => [
				$this->table_prefix . 'profileprivacy' => [
					'COLUMNS' => [
						'user_id' => ['UINT', null],
					],
					'PRIMARY_KEY'	=> 'user_id',
				],
			],
		];
    }

    /**
     * Revert database schema changes. This method is almost always required
     * to revert the changes made above by update_schema.
     *
     * https://area51.phpbb.com/docs/dev/3.2.x/migrations/schema_changes.html
     *	add_tables: Add tables
     *	drop_tables: Drop tables
     *	add_columns: Add columns to a table
     *	drop_columns: Removing/Dropping columns
     *	change_columns: Column changes (only type, not name)
     *	add_primary_keys: adding primary keys
     *	add_unique_index: adding an unique index
     *	add_index: adding an index (can be column:index_size if you need to provide size)
     *	drop_keys: Dropping keys
     *
     * This sample migration removes the column that was added the users table in update_schema.
     * It also removes the table that was added in update_schema.
     *
     * @return array Array of schema changes
     */
    public function revert_schema()
    {
		return [
			'drop_tables'		=> [
				$this->table_prefix . 'profileprivacy',
			],
		];
    }

    public function update_data()
    {
        return [
            ['custom', [[$this, 'build_table']]],
        ];
    }

    public function build_table()
    {
        // Clone profile field columns from field data table to new privacy table
        $columns = $this->db_tools->sql_list_columns(PROFILE_FIELDS_DATA_TABLE);
        foreach($columns as $column)
        {
            if($column == 'user_id')
            {
                continue;
            }
            $this->db_tools->sql_column_add($this->table_prefix . 'profileprivacy',$column,['UINT',1]);
        }


        // Add defaults for users currently in data table
        $sql = 'SELECT user_id FROM ' . PROFILE_FIELDS_DATA_TABLE; // Todo add limits in case of forum with 10,000's of users
        $result = $this->db->sql_query($sql);

        $users = [];
        while($row = $this->db->sql_fetchrow($result))
        {
            $users[] = [
                'user_id' => $row['user_id']
            ];
        }
        $this->db->sql_freeresult($result);

        if(!empty($users))
        {
            $this->db->sql_multi_insert($this->table_prefix . 'profileprivacy', $users);
        }

        return true;
    }
}
