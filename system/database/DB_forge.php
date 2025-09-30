<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');
/**
 * Code Igniter
 *
 * An open source application development framework for PHP 5.1.6 or newer
 *
 * @package		CodeIgniter
 * @author		ExpressionEngine Dev Team
 * @copyright	Copyright (c) 2008 - 2014, EllisLab, Inc.
 * @license		http://codeigniter.com/user_guide/license.html
 * @link		http://codeigniter.com
 * @since		Version 1.0
 * @filesource
 */

// ------------------------------------------------------------------------

/**
 * Database Utility Class
 *
 * @category	Database
 * @author		ExpressionEngine Dev Team
 * @link		http://codeigniter.com/user_guide/database/
 */
class CI_DB_forge {

	var $fields			= array();
	var $keys			= array();
	var $primary_keys	= array();
	var $db_char_set	=	'';

	/**
	 * Constructor
	 *
	 * Grabs the CI super object instance so we can access it.
	 *
	 */
	function __construct()
	{
		// Assign the main database object to $this->db
		$CI =& get_instance();
		$this->db =& $CI->db;
//		log_message('debug', "Database Forge Class Initialized");
	}

	// --------------------------------------------------------------------

	/**
	 * Create database
	 *
	 * @access	public
	 * @param	string	the database name
	 * @return	bool
	 */
	function create_database($db_name)
	{
		$sql = $this->_create_database($db_name);

		if (is_bool($sql))
		{
			return $sql;
		}

		return $this->db->query($sql);
	}

	// --------------------------------------------------------------------

	/**
	 * Drop database
	 *
	 * @access	public
	 * @param	string	the database name
	 * @return	bool
	 */
	function drop_database($db_name)
	{
		$sql = $this->_drop_database($db_name);

		if (is_bool($sql))
		{
			return $sql;
		}

		return $this->db->query($sql);
	}

	// --------------------------------------------------------------------

	/**
	 * Add Key
	 *
	 * @access	public
	 * @param	string	key
	 * @param	string	type
	 * @return	void
	 */
	function add_key($key = '', $primary = FALSE)
	{
		if (is_array($key))
		{
			foreach ($key as $one)
			{
				$this->add_key($one, $primary);
			}

			return;
		}

		if ($key == ''){
			_html_error('Key information is required for that operation.', 500);
		}

		if ($primary === TRUE)
		{
			$this->primary_keys[] = $key;
		}
		else
		{
			$this->keys[] = $key;
		}
	}

	// --------------------------------------------------------------------

	/**
	 * Add Field
	 *
	 * @access	public
	 * @param	string	collation
	 * @return	void
	 */
	function add_field($field = '')
	{
		if ($field == ''){
			_html_error('Field information is required.', 500);
		}

		if (is_string($field))
		{
			if ($field == 'id')
			{
				$this->add_field(array(
										'id' => array(
													'type' => 'INT',
													'constraint' => 9,
													'auto_increment' => TRUE
													)
								));
				$this->add_key('id', TRUE);
			}
			else
			{
				if (strpos($field, ' ') === FALSE)
				{
					_html_error('Field information is required for that operation.', 500);
				}

				$this->fields[] = $field;
			}
		}

		if (is_array($field))
		{
			$this->fields = array_merge($this->fields, $field);
		}

	}

	// --------------------------------------------------------------------

	/**
	 * Create Table
	 *
	 * @access	public
	 * @param	string	the table name
	 * @return	bool
	 */
	function create_table($table = '', $if_not_exists = FALSE)
	{
		if ($table == '')
		{
			_html_error('A table name is required for that operation.', 500);
		}

		if (count($this->fields) == 0)
		{
			_html_error('Field information is required.', 500);
		}

		$sql = $this->_create_table($this->db->dbprefix.$table, $this->fields, $this->primary_keys, $this->keys, $if_not_exists);

		$this->_reset();
		return $this->db->query($sql);
	}

	// --------------------------------------------------------------------

	/**
	 * Drop Table
	 *
	 * @access	public
	 * @param	string	the table name
	 * @return	bool
	 */
	function drop_table($table_name)
	{
		$sql = $this->_drop_table($this->db->dbprefix.$table_name);

		if (is_bool($sql))
		{
			return $sql;
		}

		return $this->db->query($sql);
	}

	// --------------------------------------------------------------------

	/**
	 * Rename Table
	 *
	 * @access	public
	 * @param	string	the old table name
	 * @param	string	the new table name
	 * @return	bool
	 */
	function rename_table($table_name, $new_table_name)
	{
		if ($table_name == '' OR $new_table_name == '')
		{
			_html_error('A table name is required for that operation.', 500);
		}

		$sql = $this->_rename_table($this->db->dbprefix.$table_name, $this->db->dbprefix.$new_table_name);
		return $this->db->query($sql);
	}

	// --------------------------------------------------------------------

	/**
	 * Column Add
	 *
	 * @access	public
	 * @param	string	the table name
	 * @param	string	the column name
	 * @param	string	the column definition
	 * @return	bool
	 */
	function add_column($table = '', $field = array(), $after_field = '')
	{
		if ($table == ''){
			_html_error('A table name is required for that operation.', 500);
		}

		// add field info into field array, but we can only do one at a time
		// so we cycle through

		foreach ($field as $k => $v)
		{
			$this->add_field(array($k => $field[$k]));

			if (count($this->fields) == 0){
				_html_error('Field information is required.', 500);
			}

			$sql = $this->_alter_table('ADD', $this->db->dbprefix.$table, $this->fields, $after_field);

			$this->_reset();

			if ($this->db->query($sql) === FALSE)
			{
				return FALSE;
			}
		}

		return TRUE;

	}

	// --------------------------------------------------------------------

	/**
	 * Column Drop
	 *
	 * @access	public
	 * @param	string	the table name
	 * @param	string	the column name
	 * @return	bool
	 */
	function drop_column($table = '', $column_name = '')
	{

		if ($table == '')
		{
			_html_error('A table name is required for that operation.', 500);
		}

		if ($column_name == '')
		{
			_html_error('A column name is required for that operation.', 500);
		}

		$sql = $this->_alter_table('DROP', $this->db->dbprefix.$table, $column_name);

		return $this->db->query($sql);
	}

	// --------------------------------------------------------------------

	/**
	 * Column Modify
	 *
	 * @access	public
	 * @param	string	the table name
	 * @param	string	the column name
	 * @param	string	the column definition
	 * @return	bool
	 */
	function modify_column($table = '', $field = array())
	{
		if ($table == '')
		{
			_html_error('A table name is required for that operation.', 500);
		}

		// add field info into field array, but we can only do one at a time
		// so we cycle through

		foreach ($field as $k => $v)
		{
			// If no name provided, use the current name
			if ( ! isset($field[$k]['name']))
			{
				$field[$k]['name'] = $k;
			}

			$this->add_field(array($k => $field[$k]));

			if (count($this->fields) == 0)
			{
				_html_error('Field information is required.', 500);
			}

			$sql = $this->_alter_table('CHANGE', $this->db->dbprefix.$table, $this->fields);

			$this->_reset();

			if ($this->db->query($sql) === FALSE)
			{
				return FALSE;
			}
		}

		return TRUE;
	}

	// --------------------------------------------------------------------

	/**
	 * Reset
	 *
	 * Resets table creation vars
	 *
	 * @access	private
	 * @return	void
	 */
	function _reset()
	{
		$this->fields		= array();
		$this->keys			= array();
		$this->primary_keys	= array();
	}

}

/* End of file DB_forge.php */
/* Location: ./system/database/DB_forge.php */