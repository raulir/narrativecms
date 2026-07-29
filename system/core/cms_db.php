<?php if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * CMS database layer — single mysqli connection ($GLOBALS['db'] from full config).
 *
 * Models use $this->db (Loader attaches an instance of cms_db).
 * API: query + binds, result_array / row_array / num_rows, insert_id,
 * affected_rows, table_exists, escape / escape_str / escape_like_str, close.
 *
 * No Active Record, no multi-driver, no forge/utility.
 */

class cms_db {

	/** @var mysqli */
	public $conn;

	/** Last failed query errno (when query returns false without throwing) */
	public $last_error_number = 0;

	/** Last failed query message */
	public $last_error_message = '';

	public $bind_marker = '?';

	function __construct(){

		if (empty($GLOBALS['db']) || !($GLOBALS['db'] instanceof mysqli)){
			// Full config should have connected; last resort for edge boots
			if (!empty($GLOBALS['config']['database'])){
				$GLOBALS['db'] = @mysqli_connect(
						$GLOBALS['config']['database']['hostname'],
						$GLOBALS['config']['database']['username'],
						$GLOBALS['config']['database']['password'],
						$GLOBALS['config']['database']['database']
						);
			}
		}

		if (empty($GLOBALS['db']) || !($GLOBALS['db'] instanceof mysqli)){
			if (function_exists('_html_error')){
				_html_error('cms_db: no mysqli connection ($GLOBALS[\'db\'])', 500);
			}
			throw new RuntimeException('cms_db: no mysqli connection');
		}

		$this->conn = $GLOBALS['db'];

		// Keep connection on utf8mb4 if connect path skipped set_charset
		$cs = @mysqli_character_set_name($this->conn);
		if ($cs === false || strcasecmp((string)$cs, 'utf8mb4') !== 0){
			if (!@mysqli_set_charset($this->conn, 'utf8mb4')){
				@mysqli_query($this->conn, 'SET NAMES utf8mb4');
			}
		}

	}

	/**
	 * Run SQL. Optional ? binds (escaped and inlined — same contract as old CI).
	 * SELECT → cms_db_result; write (INSERT/UPDATE/…) → true; failure → false or throws mysqli_sql_exception.
	 *
	 * @param string $sql
	 * @param array|mixed|false $binds
	 * @return cms_db_result|true|false
	 */
	function query($sql, $binds = false){

		if ($sql === '' || $sql === null){
			return false;
		}

		if ($binds !== false){
			$sql = $this->compile_binds($sql, $binds);
		}

		$result = mysqli_query($this->conn, $sql);

		if ($result === false){
			$this->last_error_number = mysqli_errno($this->conn);
			$this->last_error_message = mysqli_error($this->conn);
			if (function_exists('_html_error')){
				_html_error('Error Number: '.$this->last_error_number.
						', message: '.$this->last_error_message.', SQL: '.$sql);
			}
			return false;
		}

		if ($this->is_write_type($sql)){
			if ($result instanceof mysqli_result){
				mysqli_free_result($result);
			}
			return true;
		}

		return new cms_db_result($result);

	}

	/**
	 * @param string $sql
	 * @param array|mixed $binds
	 * @return string
	 */
	function compile_binds($sql, $binds){

		if (strpos($sql, $this->bind_marker) === false){
			return $sql;
		}

		if (!is_array($binds)){
			$binds = [$binds];
		}

		$segments = explode($this->bind_marker, $sql);

		if (count($binds) >= count($segments)){
			$binds = array_slice($binds, 0, count($segments) - 1);
		}

		$result = $segments[0];
		$i = 0;
		foreach ($binds as $bind){
			$result .= $this->escape($bind);
			$result .= $segments[++$i];
		}

		return $result;

	}

	/**
	 * @param string $sql
	 * @return bool
	 */
	function is_write_type($sql){

		return (bool)preg_match(
				'/^\s*"?(SET|INSERT|UPDATE|DELETE|REPLACE|CREATE|DROP|TRUNCATE|LOAD DATA|COPY|ALTER|GRANT|REVOKE|LOCK|UNLOCK)\s+/i',
				$sql
				);

	}

	/**
	 * Smart escape for bind values (strings quoted, bool → 0/1, null → NULL).
	 *
	 * @param mixed $str
	 * @return mixed
	 */
	function escape($str){

		if (is_string($str)){
			return "'".$this->escape_str($str)."'";
		}
		if (is_bool($str)){
			return $str ? 1 : 0;
		}
		if ($str === null){
			return 'NULL';
		}

		return $str;

	}

	/**
	 * @param string|array $str
	 * @param bool $like Escape % and _ for LIKE
	 * @return string|array
	 */
	function escape_str($str, $like = false){

		if (is_array($str)){
			foreach ($str as $key => $val){
				$str[$key] = $this->escape_str($val, $like);
			}
			return $str;
		}

		$str = mysqli_real_escape_string($this->conn, (string)$str);

		if ($like === true){
			$str = str_replace(['%', '_'], ['\\%', '\\_'], $str);
		}

		return $str;

	}

	/**
	 * Escape for use inside LIKE patterns (does not add quotes).
	 *
	 * @param string $str
	 * @return string
	 */
	function escape_like_str($str){

		return $this->escape_str($str, true);

	}

	/**
	 * Whether a table exists (live SHOW TABLES — no stale list cache).
	 *
	 * @param string $table_name Bare table name
	 * @return bool
	 */
	function table_exists($table_name){

		$table_name = trim((string)$table_name);
		if ($table_name === '' || !preg_match('/^[A-Za-z0-9_]+$/', $table_name)){
			return false;
		}

		// LIKE treats _ as wildcard — escape so music_exercise matches literally
		$like = $this->escape_like_str($table_name);
		$sql = "SHOW TABLES LIKE '".$like."'";
		$result = mysqli_query($this->conn, $sql);
		if ($result === false){
			return false;
		}
		$exists = mysqli_num_rows($result) > 0;
		mysqli_free_result($result);

		return $exists;

	}

	/**
	 * @return int
	 */
	function insert_id(){

		return (int)mysqli_insert_id($this->conn);

	}

	/**
	 * @return int
	 */
	function affected_rows(){

		return (int)mysqli_affected_rows($this->conn);

	}

	function close(){

		if ($this->conn instanceof mysqli){
			@mysqli_close($this->conn);
		}
		if (isset($GLOBALS['db']) && $GLOBALS['db'] === $this->conn){
			$GLOBALS['db'] = null;
		}
		$this->conn = null;

	}

}

/**
 * SELECT result wrapper.
 */
class cms_db_result {

	/** @var mysqli_result|null */
	public $result_id;

	/** @var int */
	public $num_rows = 0;

	/** @var array|null cached rows */
	protected $result_array_cache = null;

	function __construct($result_id){

		$this->result_id = $result_id;
		if ($result_id instanceof mysqli_result){
			$this->num_rows = (int)mysqli_num_rows($result_id);
		} else {
			$this->num_rows = 0;
		}

	}

	/**
	 * @return int
	 */
	function num_rows(){

		return $this->num_rows;

	}

	/**
	 * @return array[]
	 */
	function result_array(){

		if ($this->result_array_cache !== null){
			return $this->result_array_cache;
		}

		$this->result_array_cache = [];
		if (!($this->result_id instanceof mysqli_result) || $this->num_rows === 0){
			return $this->result_array_cache;
		}

		mysqli_data_seek($this->result_id, 0);
		while ($row = mysqli_fetch_assoc($this->result_id)){
			$this->result_array_cache[] = $row;
		}

		return $this->result_array_cache;

	}

	/**
	 * @param int $n
	 * @return array|null
	 */
	function row_array($n = 0){

		$rows = $this->result_array();
		if (!isset($rows[$n])){
			return [];
		}
		return $rows[$n];

	}

	function free_result(){

		if ($this->result_id instanceof mysqli_result){
			mysqli_free_result($this->result_id);
			$this->result_id = null;
		}

	}

}
