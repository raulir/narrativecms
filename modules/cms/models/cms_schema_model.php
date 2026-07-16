<?php

namespace cms;

if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class cms_schema_model extends \Model {

	private $_fix_sql_errors = [];
	private $_fix_key = '';

	function clear_fix_errors() {
		$this->_fix_sql_errors = [];
	}

	function get_fix_errors() {
		return $this->_fix_sql_errors;
	}

	function record_fix_error($key, $message, $sql = '') {
		$parts = explode(':', $key);
		$this->_fix_sql_errors[] = [
			'module' => $parts[0] ?? '',
			'key' => $key,
			'message' => $message,
			'sql' => $sql,
		];
	}

	function check_schema() {
		$errors = [];
		list($merged, $owner) = $this->_build_merged_schemas();
		
		if (empty($merged)) {
			$errors['global'] = 'No schema JSON files found';
			return $errors;
		}

		foreach ($merged as $table => $def) {
			$module = $owner[$table] ?? 'unknown';
			$base_key = $module . ':' . $table;

			if (!$this->db->table_exists($table)) {
				$migrate_from = $def['migrate_from']['table'] ?? '';
				if ($migrate_from && $this->db->table_exists($migrate_from)) {
					$errors[$base_key] = "Table {$table} not found — migrate from {$migrate_from}";
				} else {
					$errors[$base_key] = "Table {$table} not found";
				}
				continue;
			}

			$db_cols = $this->_get_db_columns($table);
			$migrate_cols = $def['migrate_from']['columns'] ?? [];
			if (!empty($def['columns'])) {
				foreach ($def['columns'] as $col_name => $spec) {
					$col_key = $base_key . ':columns:' . $col_name;
					if (!isset($db_cols[$col_name])) {
						$legacy_col = $this->_migrate_from_legacy_column($col_name, $migrate_cols, $db_cols);
						if ($legacy_col) {
							$this->_compare_column($errors, $col_key, $spec, $db_cols[$legacy_col]);
							continue;
						}
						$errors[$col_key] = "Field \"{$col_name}\" not found";
						continue;
					}
					$this->_compare_column($errors, $col_key, $spec, $db_cols[$col_name]);
				}
			}

			$db_idx = $this->_get_db_indexes($table);
			if (!empty($def['indexes'])) {
				foreach ($def['indexes'] as $idx_name => $spec) {
					$idx_key = $base_key . ':indexes:' . $idx_name;
					if (!isset($db_idx[$idx_name])) {
						$errors[$idx_key] = "Index \"{$idx_name}\" not found";
						continue;
					}
					$actual_idx = $db_idx[$idx_name];
					if (!empty($migrate_cols) && !empty($actual_idx['columns'])) {
						$actual_idx['columns'] = $this->_map_migrated_column_names($actual_idx['columns'], $migrate_cols);
					}
					$this->_compare_index($errors, $idx_key, $spec, $actual_idx);
				}
			}
		}
		return $errors;
	}

	// ====================== FIX SCHEMA ======================

	function fix_schema($path) {
		if (empty($path)) {
			return false;
		}

		$this->clear_fix_errors();
		$this->_fix_key = $path;

		$parts = explode(':', $path);
		$module = $parts[0] ?? '';

		// Re-check that this exact path (or its subtree) still has an issue
		$all_errors = $this->check_schema();
		if (!$this->_path_has_issue($all_errors, $path)) {
			return true; // already ok
		}

		list($merged, $owner) = $this->_build_merged_schemas();

		// 1. Module level (all tables owned by this module)
		if (count($parts) === 1) {
			$count = 0;
			foreach ($owner as $table => $mod) {
				if ($mod === $module && isset($merged[$table])) {
					if ($this->_fix_table($table, $merged[$table])) {
						$count++;
					}
				}
			}
			return $count > 0 && empty($this->_fix_sql_errors);
		}

		$table = $parts[1] ?? '';
		if (!isset($merged[$table]) || ($owner[$table] ?? '') !== $module) {
			return false;
		}

		$def = $merged[$table];

		// 2. Table level
		if (count($parts) === 2) {
			return $this->_fix_table($table, $def) && empty($this->_fix_sql_errors);
		}

		$section = $parts[2] ?? '';

		// 3–4. Column / index level — run full phased table fix (auto_increment needs index phase)
		if ($section === 'columns' || $section === 'indexes') {
			return $this->_fix_table($table, $def) && empty($this->_fix_sql_errors);
		}

		return false;
	}
	
	/**
	 * @param string|null $filter_module If set, only that schema package’s errors
	 */
	function get_schema_errors_with_status($filter_module = null) {
	    $errors = $this->check_schema();
	    $grouped = [];
	
	    list($merged, $owner) = $this->_build_merged_schemas();

	    $filter_module = ($filter_module === null || $filter_module === '') ? null : (string)$filter_module;
	
	    foreach ($errors as $key => $message) {
	        $parts = explode(':', $key);
	        $module  = $parts[0] ?? '(unknown)';
	        $table   = $parts[1] ?? '';
	        $section = $parts[2] ?? '';

	        if ($filter_module !== null && $module !== $filter_module) {
	        	continue;
	        }
	
	        if (!isset($grouped[$module])) {
	            $grouped[$module] = [];
	        }
	
	        $item = [
	            'location'    => $key,
	            'description' => $message,
	            'key'         => $key,
	            'enabled'     => true,
	        ];
	
	        // For indexes: disable if any required column is missing **in current DB**
	        if ($section === 'indexes') {
	            $index_name = $parts[3] ?? '';
	            if (isset($merged[$table]['indexes'][$index_name]['columns'])) {
	                $required = (array)$merged[$table]['indexes'][$index_name]['columns'];
	
	                // Get ACTUAL current columns from database
	                $db_cols = $this->_get_db_columns($table);
	
	                foreach ($required as $col) {
	                    if (!isset($db_cols[$col])) {
	                        $item['enabled'] = false;
	                        break;
	                    }
	                }
	            }
	        }
	
	        $grouped[$module][] = $item;
	    }
	
	    return [
	        'grouped'    => $grouped,
	        'has_errors' => !empty($grouped),
	    ];
	}
	
	// ====================== PANEL TABLE SCHEMAS ======================

	function get_panel_table_modules() {
		list(, $owner) = $this->_build_panel_table_schemas();
		return array_values(array_unique($owner));
	}

	function get_panel_table_names() {
		list($schemas, ) = $this->_build_panel_table_schemas();
		return array_keys($schemas);
	}

	function get_panel_table_modules_pending() {
		$this->load->model('cms/cms_page_panel_model');

		list($schemas, $owner) = $this->_build_panel_table_schemas();
		$pending = [];

		foreach ($schemas as $table => $def) {
			$table_module = $owner[$table] ?? '';
			if (!$table_module) {
				continue;
			}
			if ($this->_panel_table_needs_sync($table, $table_module)) {
				$pending[$table_module] = $table_module;
			}
		}

		return array_values($pending);
	}

	private function _panel_table_needs_sync($table, $table_module) {
		if (!$this->db->table_exists($table)) {
			return false;
		}

		$panel_name = $table_module.'/'.preg_replace('/^'.preg_quote($table_module, '/').'_/', '', $table);
		$table_fields = $this->cms_page_panel_model->get_panel_table_fields($panel_name);
		if (empty($table_fields)) {
			return false;
		}

		$field_names = array_keys($table_fields);
		$in_list = "'".implode("','", $field_names)."'";

		$sql = "select count(*) as c from cms_page_panel_param p ".
			"join cms_page_panel a on a.cms_page_panel_id = p.cms_page_panel_id ".
			"where a.panel_name = ? and p.name in (".$in_list.") and p.language = '' ";
		$query = $this->db->query($sql, [$panel_name]);
		if ((int)$query->row_array()['c'] > 0) {
			return true;
		}

		$sql = "select count(*) as c from cms_page_panel a ".
			"left join `{$table}` t on t.cms_page_panel_id = a.cms_page_panel_id ".
			"where a.panel_name = ? and t.cms_page_panel_id is null ";
		$query = $this->db->query($sql, [$panel_name]);
		if ((int)$query->row_array()['c'] > 0) {
			return true;
		}

		return false;
	}

	function synchronise_panel_table_data($module = '') {
		$this->load->model('cms/cms_page_panel_model');

		list($schemas, $owner) = $this->_build_panel_table_schemas();
		$stats = ['synced' => 0, 'skipped' => 0, 'errors' => []];

		foreach ($schemas as $table => $def) {
			$table_module = $owner[$table] ?? '';
			if ($module && $table_module !== $module) {
				continue;
			}
			if (!$this->db->table_exists($table)) {
				$stats['errors'][] = $table.': table not found — run schema fix first';
				continue;
			}

			$panel_name = $table_module.'/'.preg_replace('/^'.preg_quote($table_module, '/').'_/', '', $table);
			$table_fields = $this->cms_page_panel_model->get_panel_table_fields($panel_name);
			if (empty($table_fields)) {
				continue;
			}

			$sql = "select cms_page_panel_id from cms_page_panel where panel_name = ? ";
			$query = $this->db->query($sql, [$panel_name]);
			$rows = $query->result_array();

			foreach ($rows as $row) {
				$cms_page_panel_id = (int)$row['cms_page_panel_id'];
				$row_data = ['cms_page_panel_id' => $cms_page_panel_id];
				$has_source = false;
				$has_legacy_params = false;

				foreach ($table_fields as $field_name => $field_spec) {
					$sql = "select cms_page_panel_param_id from cms_page_panel_param where cms_page_panel_id = ? and name = ? and language = '' limit 1 ";
					$legacy_query = $this->db->query($sql, [$cms_page_panel_id, $field_name]);
					if ($legacy_query->num_rows()) {
						$has_legacy_params = true;
					}

					$value = $this->_get_panel_param_value($cms_page_panel_id, $field_name);
					if ($value === null) {
						continue;
					}
					$row_data[$field_name] = $value;
					$has_source = true;
				}

				if (!$has_source) {
					$stats['skipped']++;
					continue;
				}

				if (!$has_legacy_params && $this->_panel_table_row_complete($table, $cms_page_panel_id, $table_fields, $row_data)) {
					$stats['skipped']++;
					continue;
				}

				if ($this->_upsert_panel_table_row($table, $row_data)) {
					foreach (array_keys($table_fields) as $field_name) {
						if (array_key_exists($field_name, $row_data)) {
							$sql = "delete from cms_page_panel_param where cms_page_panel_id = ? and name = ? and language = '' ";
							$this->db->query($sql, [$cms_page_panel_id, $field_name]);
						}
					}
					$this->cms_page_panel_model->_update_cached_params($cms_page_panel_id);
					$stats['synced']++;
				} else {
					$stats['errors'][] = $table.':'.$cms_page_panel_id.': upsert failed';
				}
			}
		}

		return $stats;
	}

	private function _panel_table_row_complete($table, $cms_page_panel_id, $table_fields, $row_data) {
		$sql = "select * from `{$table}` where cms_page_panel_id = ? limit 1 ";
		$query = $this->db->query($sql, [(int)$cms_page_panel_id]);
		if (!$query->num_rows()) {
			return false;
		}

		$existing = $query->row_array();
		foreach ($table_fields as $field_name => $field_spec) {
			if (!array_key_exists($field_name, $row_data)) {
				continue;
			}
			if (!array_key_exists($field_name, $existing) || (string)$existing[$field_name] !== (string)$row_data[$field_name]) {
				return false;
			}
		}

		return true;
	}

	private function _get_panel_param_value($cms_page_panel_id, $field_name) {
		$sql = "select value from cms_page_panel_param where cms_page_panel_id = ? and name = ? and language = '' limit 1 ";
		$query = $this->db->query($sql, [$cms_page_panel_id, $field_name]);
		if ($query->num_rows()) {
			return $query->row_array()['value'];
		}

		$sql = "select value from cms_page_panel_param where cms_page_panel_id = ? and name = '' limit 1 ";
		$query = $this->db->query($sql, [$cms_page_panel_id]);
		if (!$query->num_rows()) {
			return null;
		}

		$params = cms_json_decode($query->row_array()['value'], 'panel params cache');
		if (!is_array($params) || !array_key_exists($field_name, $params)) {
			return null;
		}

		return $params[$field_name];
	}

	private function _upsert_panel_table_row($table, $row_data) {
		$cms_page_panel_id = (int)$row_data['cms_page_panel_id'];
		unset($row_data['cms_page_panel_id']);

		$sql = "select cms_page_panel_id from `{$table}` where cms_page_panel_id = ? limit 1 ";
		$query = $this->db->query($sql, [$cms_page_panel_id]);

		if ($query->num_rows()) {
			if (empty($row_data)) {
				return true;
			}
			$sets = [];
			$bind = [];
			foreach ($row_data as $col => $val) {
				$sets[] = '`'.$col.'` = ?';
				$bind[] = $val;
			}
			$bind[] = $cms_page_panel_id;
			$sql = "update `{$table}` set ".implode(', ', $sets)." where cms_page_panel_id = ? ";
			return $this->_execute($sql, $bind);
		}

		$row_data['cms_page_panel_id'] = $cms_page_panel_id;
		$cols = array_keys($row_data);
		$sql = "insert into `{$table}` (`".implode('`, `', $cols)."`) values (".implode(', ', array_fill(0, count($cols), '?')).") ";
		return $this->_execute($sql, array_values($row_data));
	}

	private function _build_panel_table_schemas() {
		$merged = [];
		$owner = [];
		$modules = $GLOBALS['config']['modules'] ?? [];

		foreach ($modules as $module) {
			$def_dir = $GLOBALS['config']['base_path'].'modules/'.$module.'/definitions/';
			if (!is_dir($def_dir)) {
				continue;
			}

			foreach (glob($def_dir.'*.json') as $file) {
				$panel = basename($file, '.json');
				$json = file_get_contents($file);
				$def = cms_json_decode($json, basename($file));
				if (!is_array($def) || empty($def['list']) || empty($def['item'])) {
					continue;
				}

				$table_fields = [];
				foreach ($def['item'] as $item) {
					if (!empty($item['table']) && $item['table'] == '1' && !empty($item['name'])) {
						$table_fields[$item['name']] = $item;
					}
				}
				if (empty($table_fields)) {
					continue;
				}

				$table = $module.'_'.$panel;
				$merged[$table] = $this->_build_panel_table_schema($table, $table_fields);
				$owner[$table] = $module;
			}
		}

		return [$merged, $owner];
	}

	private function _build_panel_table_schema($table, $table_fields) {
		$columns = [
			'cms_page_panel_id' => [
				'type' => 'INT',
				'constraint' => 10,
				'unsigned' => true,
				'null' => false,
			],
		];
		$indexes = [
			'PRIMARY' => ['columns' => ['cms_page_panel_id'], 'type' => 'primary'],
		];

		foreach ($table_fields as $name => $spec) {
			$columns[$name] = $this->_parse_panel_table_type($spec['table_type'] ?? 'text');

			if (!empty($spec['table_index'])) {
				$idx_name = $name.'_idx';
				if ($spec['table_index'] === 'unique') {
					$indexes[$idx_name] = ['columns' => [$name], 'type' => 'unique'];
				} else {
					$indexes[$idx_name] = ['columns' => [$name]];
				}
			}
		}

		return [
			'table' => $table,
			'engine' => 'InnoDB',
			'charset' => 'utf8mb4',
			'collation' => 'utf8mb4_general_ci',
			'columns' => $columns,
			'indexes' => $indexes,
		];
	}

	private function _parse_panel_table_type($table_type) {
		if (preg_match('/^varchar:(\d+)$/i', $table_type, $m)) {
			return [
				'type' => 'VARCHAR',
				'constraint' => (int)$m[1],
				'null' => false,
				'default' => '',
			];
		}

		return [
			'type' => 'TEXT',
			'null' => false,
		];
	}

	// ====================== REUSABLE HELPERS ======================

	private function _build_merged_schemas() {
		list($merged, $owner) = $this->_build_panel_table_schemas();
		$modules = $GLOBALS['config']['modules'] ?? [];

		foreach ($modules as $module) {
			$schema_dir = $GLOBALS['config']['base_path'].'modules/'.$module.'/schema/';
			if (!is_dir($schema_dir)) continue;

			$files = glob($schema_dir . '*.json');
			foreach ($files as $file) {

				$json = file_get_contents($file);
				$def = cms_json_decode($json, basename($file));
	    
			    if (!is_array($def)) {
			        continue;
			    }

				$table = $def['table'] ?? basename($file, '.json');

				if (isset($merged[$table])) {
					$merged[$table] = $this->_deep_merge($merged[$table], $def);
				} else {
					$merged[$table] = $def;
				}
				$owner[$table] = $module;
				
			}
		}
		return [$merged, $owner];
	}

	private function _path_has_issue($errors, $path) {
		if (isset($errors[$path])) return true;
		foreach (array_keys($errors) as $key) {
			if (strpos($key, $path.':') === 0) return true;
		}
		return false;
	}

	// ====================== FIX CORE ======================

	private function _fix_table($table, $def) {
		if (!$this->db->table_exists($table)) {
			if ($this->_migrate_table_from($table, $def)) {
				// table migrated — continue with column/index fixes below
			} else {
				return $this->_create_table($table, $def);
			}
		}

		return $this->_fix_table_phased($table, $def);
	}

	private function _fix_table_phased($table, $def) {
		$ok = true;
		$deferred_auto = [];

		if (!$this->_reconcile_migrated_columns($table, $def, $deferred_auto)) {
			return false;
		}

		if (!$this->_migrate_columns_from($table, $def, $deferred_auto)) {
			return false;
		}

		if (!empty($def['columns'])) {
			foreach ($def['columns'] as $col => $spec) {
				if (!$this->_fix_column_phase1($table, $col, $spec, $def, $deferred_auto)) {
					$ok = false;
				}
			}
		}

		if (!$ok || !empty($this->_fix_sql_errors)) {
			return false;
		}

		if (!$this->_prepare_auto_increment_for_indexes($table, $def)) {
			return false;
		}

		if (!empty($def['indexes'])) {
			foreach ($def['indexes'] as $idx => $spec) {
				if (!$this->_fix_index($table, $idx, $spec)) {
					$ok = false;
				}
			}
		}

		if (!$ok || !empty($this->_fix_sql_errors)) {
			return false;
		}

		foreach ($deferred_auto as $col => $spec) {
			if (!$this->_apply_auto_increment($table, $col, $spec)) {
				$ok = false;
			}
		}

		return $ok && empty($this->_fix_sql_errors);
	}

	private function _fix_column_phase1($table, $column, $spec, $def, &$deferred_auto) {
		$db_cols = $this->_get_db_columns($table);
		$defer = $this->_should_defer_auto_increment($table, $column, $spec, $def);

		if (!isset($db_cols[$column])) {
			$opts = $defer ? ['include_auto_increment' => false] : [];
			if (!$this->_add_column($table, $column, $spec, $def, $opts)) {
				return false;
			}
			if ($defer) {
				$deferred_auto[$column] = $spec;
			}
			return true;
		}

		$compare_spec = $spec;
		if ($defer) {
			$compare_spec = $spec;
			unset($compare_spec['auto_increment']);
		}

		$tmp_errors = [];
		$this->_compare_column($tmp_errors, 'temp', $compare_spec, $db_cols[$column]);
		if (empty($tmp_errors)) {
			if ($defer) {
				$deferred_auto[$column] = $spec;
			}
			return true;
		}

		$opts = $defer ? ['include_auto_increment' => false] : [];
		if (!$this->_modify_column($table, $column, $spec, $opts)) {
			return false;
		}
		if ($defer) {
			$deferred_auto[$column] = $spec;
		}
		return true;
	}

	private function _get_auto_increment_column($table) {
		foreach ($this->_get_db_columns($table) as $name => $col) {
			if (($col['Extra'] ?? '') === 'auto_increment') {
				return $name;
			}
		}
		return null;
	}

	private function _column_is_keyed($table, $column) {
		foreach ($this->_get_db_indexes($table) as $idx) {
			if (in_array($column, $idx['columns'] ?? [], true)) {
				return true;
			}
		}
		return false;
	}

	private function _schema_primary_column($def) {
		$cols = $def['indexes']['PRIMARY']['columns'] ?? [];
		if (empty($cols) || !is_array($cols)) {
			return null;
		}
		return $cols[0];
	}

	private function _should_defer_auto_increment($table, $column, $spec, $def) {
		if (empty($spec['auto_increment'])) {
			return false;
		}

		if (!$this->_column_is_keyed($table, $column)) {
			return true;
		}

		$existing_auto = $this->_get_auto_increment_column($table);
		if ($existing_auto && $existing_auto !== $column) {
			return true;
		}

		return false;
	}

	private function _prepare_auto_increment_for_indexes($table, $def) {
		$schema_primary = $this->_schema_primary_column($def);
		$existing_auto = $this->_get_auto_increment_column($table);

		if ($existing_auto && $schema_primary && $existing_auto !== $schema_primary) {
			if (!empty($def['columns'][$existing_auto])) {
				if (!$this->_strip_auto_increment($table, $existing_auto, $def['columns'][$existing_auto])) {
					return false;
				}
			} else {
				if (!$this->_strip_auto_increment_raw($table, $existing_auto)) {
					return false;
				}
			}
		}

		$db_idx = $this->_get_db_indexes($table);
		$schema_pk_cols = $def['indexes']['PRIMARY']['columns'] ?? null;
		if ($schema_pk_cols && isset($db_idx['PRIMARY'])) {
			$db_pk = implode(',', $db_idx['PRIMARY']['columns']);
			$schema_pk = implode(',', (array)$schema_pk_cols);
			if ($db_pk !== $schema_pk) {
				if (!$this->_drop_index($table, 'PRIMARY')) {
					return false;
				}
			}
		}

		return true;
	}

	private function _strip_auto_increment($table, $column, $spec) {
		$col_def = $this->_build_column_definition($column, $spec, ['include_auto_increment' => false]);
		$sql = "ALTER TABLE `{$table}` MODIFY COLUMN {$col_def}";
		return $this->_execute($sql);
	}

	private function _strip_auto_increment_raw($table, $column) {
		$db_cols = $this->_get_db_columns($table);
		if (!isset($db_cols[$column])) {
			return true;
		}

		$col = $db_cols[$column];
		$sql = "ALTER TABLE `{$table}` MODIFY COLUMN `{$column}` {$col['Type']}";
		$sql .= ($col['Null'] === 'YES') ? ' NULL' : ' NOT NULL';

		if ($col['Default'] !== null) {
			if ($this->_is_timestamp_default($col['Default'])) {
				$sql .= ' DEFAULT CURRENT_TIMESTAMP';
			} else {
				$sql .= ' DEFAULT '.$this->db->escape($col['Default']);
			}
		} elseif ($col['Null'] === 'YES') {
			$sql .= ' DEFAULT NULL';
		}

		return $this->_execute($sql);
	}

	private function _migrate_from_legacy_column($new_col, $migrate_cols, $db_cols) {
		foreach ($migrate_cols as $old_col => $mapped_new_col) {
			if ($mapped_new_col === $new_col && isset($db_cols[$old_col])) {
				return $old_col;
			}
		}
		return null;
	}

	private function _map_migrated_column_names($columns, $migrate_cols) {
		$mapped = [];
		foreach ((array)$columns as $col) {
			$mapped[] = $migrate_cols[$col] ?? $col;
		}
		return $mapped;
	}

	private function _reconcile_migrated_columns($table, $def, &$deferred_auto) {
		$migrate_cols = $def['migrate_from']['columns'] ?? [];
		if (empty($migrate_cols) || empty($def['columns'])) {
			return true;
		}

		$db_cols = $this->_get_db_columns($table);
		foreach ($migrate_cols as $old_col => $new_col) {
			if (!isset($db_cols[$old_col]) || !isset($db_cols[$new_col])) {
				continue;
			}
			if (!isset($def['columns'][$new_col])) {
				continue;
			}

			if (!$this->_execute(
				'UPDATE `'.$table.'` SET `'.$new_col.'` = `'.$old_col.'` WHERE `'.$new_col.'` = 0 OR `'.$new_col.'` IS NULL'
			)) {
				return false;
			}

			$existing_auto = $this->_get_auto_increment_column($table);
			if ($existing_auto === $old_col) {
				if (!$this->_strip_auto_increment_raw($table, $old_col)) {
					return false;
				}
			}

			$db_idx = $this->_get_db_indexes($table);
			$pk_cols = $db_idx['PRIMARY']['columns'] ?? [];
			if (in_array($old_col, $pk_cols, true)) {
				if (!$this->_drop_index($table, 'PRIMARY')) {
					return false;
				}
			}

			if (!$this->_execute('ALTER TABLE `'.$table.'` DROP COLUMN `'.$old_col.'`')) {
				return false;
			}

			$col_spec = $def['columns'][$new_col];
			if (!empty($col_spec['auto_increment']) && !$this->_get_auto_increment_column($table)) {
				$defer = $this->_should_defer_auto_increment($table, $new_col, $col_spec, $def);
				if ($defer) {
					$deferred_auto[$new_col] = $col_spec;
				}
			}
		}

		return empty($this->_fix_sql_errors);
	}

	private function _repair_primary_column_values($table, $column) {
		$db_cols = $this->_get_db_columns($table);
		if (!isset($db_cols[$column])) {
			return true;
		}

		$problem_groups = $this->db->query(
			'SELECT `'.$column.'` AS val, COUNT(*) AS cnt FROM `'.$table.'` GROUP BY `'.$column.'` HAVING cnt > 1 OR val = 0'
		)->result_array();

		if (empty($problem_groups)) {
			return true;
		}

		$max_row = $this->db->query('SELECT COALESCE(MAX(`'.$column.'`), 0) AS m FROM `'.$table.'`')->row_array();
		$next_id = max(1, (int)($max_row['m'] ?? 0) + 1);

		foreach ($problem_groups as $group) {
			$val = $group['val'];
			$rows = $this->db->query('SELECT * FROM `'.$table.'` WHERE `'.$column.'` = ?', [$val])->result_array();
			$keep_first = ((int)$val > 0);

			foreach ($rows as $i => $row) {
				if ($keep_first && $i === 0) {
					continue;
				}

				$where_parts = [];
				$binds = [];
				foreach ($db_cols as $col_name => $_) {
					$where_parts[] = '`'.$col_name.'` = ?';
					$binds[] = $row[$col_name];
				}

				$sql = 'UPDATE `'.$table.'` SET `'.$column.'` = ? WHERE '.implode(' AND ', $where_parts).' LIMIT 1';
				array_unshift($binds, $next_id);
				if (!$this->_execute($sql, $binds)) {
					return false;
				}
				$next_id++;
			}
		}

		return empty($this->_fix_sql_errors);
	}

	private function _migrate_columns_from($table, $def, &$deferred_auto) {
		$migrate = $def['migrate_from'] ?? null;
		if (empty($migrate['columns']) || empty($def['columns'])) {
			return true;
		}

		$db_cols = $this->_get_db_columns($table);
		foreach ($migrate['columns'] as $old_col => $new_col) {
			if (!isset($db_cols[$old_col]) || isset($db_cols[$new_col])) {
				continue;
			}
			if (!isset($def['columns'][$new_col])) {
				continue;
			}

			$col_spec = $def['columns'][$new_col];
			$defer = $this->_should_defer_auto_increment($table, $new_col, $col_spec, $def);
			$opts = $defer ? ['include_auto_increment' => false] : [];
			$col_def = $this->_build_column_definition($new_col, $col_spec, $opts);
			if (!$this->_execute('ALTER TABLE `'.$table.'` CHANGE `'.$old_col.'` '.$col_def)) {
				return false;
			}
			if ($defer) {
				$deferred_auto[$new_col] = $col_spec;
			}
		}

		return empty($this->_fix_sql_errors);
	}

	private function _apply_auto_increment($table, $column, $spec) {
		if (!$this->_column_is_keyed($table, $column)) {
			$this->record_fix_error(
				$this->_fix_key,
				'Cannot apply AUTO_INCREMENT — column "'.$column.'" is not indexed yet',
				''
			);
			return false;
		}

		$col_def = $this->_build_column_definition($column, $spec, ['include_auto_increment' => true]);
		$sql = "ALTER TABLE `{$table}` MODIFY COLUMN {$col_def}";
		return $this->_execute($sql);
	}

	private function _fix_index($table, $index_name, $spec) {
		$db_idx = $this->_get_db_indexes($table);
	
		$tmp_errors = [];
		$this->_compare_index($tmp_errors, 'temp', $spec, $db_idx[$index_name] ?? []);
		
		// already correct or doesn't exist but matches expected → no need to touch
		if (empty($tmp_errors) && isset($db_idx[$index_name])) {
			return true;
		}
	
		// drop only if it actually exists (avoids fatal on missing index)
		if (isset($db_idx[$index_name])) {
			$this->_drop_index($table, $index_name);
		}
	
		// always (re)create
		return $this->_create_index($table, $index_name, $spec);
	}

	// ====================== SQL BUILDERS ======================

	private function _create_table($table, $def) {
	    $cols = [];
	    foreach ($def['columns'] as $name => $spec) {
	        $cols[] = $this->_build_column_definition($name, $spec);
	    }
	
	    $sql = "CREATE TABLE `{$table}` (\n " . implode(",\n ", $cols);
	
	    // Always add indexes — including PRIMARY
	    if (!empty($def['indexes'])) {
	        foreach ($def['indexes'] as $idx_name => $spec) {
	            $sql .= ",\n " . $this->_build_index_definition($idx_name, $spec);
	        }
	    }
	
	    $sql .= "\n) ENGINE=" . ($def['engine'] ?? 'InnoDB') .
	            " DEFAULT CHARSET=" . ($def['charset'] ?? 'utf8mb4') .
	            " COLLATE=" . ($def['collation'] ?? 'utf8mb4_general_ci');
	
	    return $this->_execute($sql);
	}

	private function _add_column($table, $column, $spec, $def, $opts = []) {
		$col_def = $this->_build_column_definition($column, $spec, $opts);
		$after   = $this->_get_after_clause($table, $column, $def);

		$sql = "ALTER TABLE `{$table}` ADD COLUMN {$col_def}{$after}";
		return $this->_execute($sql);
	}

	private function _modify_column($table, $column, $spec, $opts = []) {
		$def = $this->_build_column_definition($column, $spec, $opts);
		$sql = "ALTER TABLE `{$table}` MODIFY COLUMN {$def}";
		return $this->_execute($sql);
	}

	private function _build_column_definition($name, $spec, $opts = []) {
		$include_auto = $opts['include_auto_increment'] ?? true;
		$type = strtoupper($spec['type']);
		if (isset($spec['constraint'])) {
			$type .= "({$spec['constraint']})";
		}
		if (!empty($spec['unsigned'])) $type .= " UNSIGNED";

		$sql = "`{$name}` {$type}";

		// charset / collation (important for your new "type" column)
		if (!empty($spec['charset'])) $sql .= " CHARACTER SET {$spec['charset']}";
		if (!empty($spec['collation'])) $sql .= " COLLATE {$spec['collation']}";

		// null / default / auto_increment
		$sql .= (empty($spec['null']) ? " NOT NULL" : " NULL");

		if (array_key_exists('default', $spec)) {
			if ($spec['default'] === null) {
				$sql .= " DEFAULT NULL";
			} elseif ($this->_is_timestamp_default($spec['default'])) {
				$sql .= " DEFAULT CURRENT_TIMESTAMP";
			} else {
				$sql .= " DEFAULT " . $this->db->escape($spec['default']);
			}
		}

		if ($include_auto && !empty($spec['auto_increment'])) {
			$sql .= " AUTO_INCREMENT";
		}

		return $sql;
	}

	private function _build_index_definition($name, $spec) {
		$cols = array_map(function($c){ return "`{$c}`"; }, (array)$spec['columns']);
		$col_str = implode(',', $cols);

		if ($name === 'PRIMARY') {
			return "PRIMARY KEY ({$col_str})";
		}
		if (!empty($spec['type']) && $spec['type'] === 'unique') {
			return "UNIQUE KEY `{$name}` ({$col_str})";
		}
		// length support (e.g. filename_idx length:10)
		$length = '';
		if (!empty($spec['length'])) {
			$length = "({$spec['length']})";
			// if length is per-column it can be extended later
		}
		return "KEY `{$name}` ({$col_str}{$length})";
	}

	private function _drop_index($table, $index_name) {
		if ($index_name === 'PRIMARY') {
			return $this->_execute("ALTER TABLE `{$table}` DROP PRIMARY KEY");
		}
		return $this->_execute("ALTER TABLE `{$table}` DROP INDEX IF EXISTS `{$index_name}`");
	}
	
	private function _create_index($table, $index_name, $spec) {
		if ($index_name === 'PRIMARY' && !empty($spec['columns'])) {
			foreach ((array)$spec['columns'] as $col) {
				if (!$this->_repair_primary_column_values($table, $col)) {
					return false;
				}
			}
		}

		$def = $this->_build_index_definition($index_name, $spec);
		$sql = "ALTER TABLE `{$table}` ADD {$def}";
		return $this->_execute($sql);
	}

	private function _execute($sql, $bind = []) {
		try {
			if (!empty($bind)) {
				$this->db->query($sql, $bind);
			} else {
				$this->db->query($sql);
			}
			return true;
		} catch (\mysqli_sql_exception $e) {
			$this->record_fix_error($this->_fix_key, $e->getMessage(), $sql);
			return false;
		}
	}

    function _deep_merge($a, $b) {
        foreach ($b as $k => $v) {
            if (is_array($v) && isset($a[$k]) && is_array($a[$k])) {
                $a[$k] = $this->_deep_merge($a[$k], $v);
            } else {
                $a[$k] = $v;
            }
        }
        return $a;
    }

    function _get_db_columns($table) {
        $rows = $this->db->query("SHOW FULL COLUMNS FROM `{$table}`")->result_array();
        $cols = [];
        foreach ($rows as $r) {
            $cols[$r['Field']] = $r;
        }
        return $cols;
    }

    function _compare_column(&$errors, $base_key, $expected, $actual) {
        // type
        $exp_type = strtoupper($expected['type'] ?? '');
        $act_type = strtoupper(explode('(', $actual['Type'])[0] ?? '');
        if ($exp_type && $exp_type !== $act_type) {
            $errors[$base_key . ':type'] = "Type should be {$exp_type} (current: {$act_type})";
        }

        // constraint / length
        if (isset($expected['constraint'])) {
            if (preg_match('/\((\d+)\)/', $actual['Type'], $m)) {
                if ((int)$m[1] !== (int)$expected['constraint']) {
                    $errors[$base_key . ':constraint'] = "Length should be {$expected['constraint']}";
                }
            }
        }

        // unsigned
        if (isset($expected['unsigned'])) {
            $is_unsigned = stripos($actual['Type'], 'unsigned') !== false;
            $want       = (bool)$expected['unsigned'];
            if ($is_unsigned !== $want) {
                $errors[$base_key . ':unsigned'] = 'Property "unsigned" value not "' . 
                		($want ? 'true' : 'false') . '" (current "' . ($is_unsigned ? 'true' : 'false') . '")';
            }
        }

        // null
        if (isset($expected['null'])) {
            $want = $expected['null'] ? 'YES' : 'NO';
            if ($want !== $actual['Null']) {
                $errors[$base_key . ':null'] = "Null should be {$want}";
            }
        }

        // default
        if (array_key_exists('default', $expected)) {
            $want = $this->_normalise_default_value($expected['default']);
            $have = $this->_normalise_default_value($actual['Default']);
            if ($want !== $have) {
                $errors[$base_key . ':default'] = "Default should be '{$want}' (current '{$have}')";
            }
        }

        // auto_increment
        if (isset($expected['auto_increment'])) {
            $want = (bool)$expected['auto_increment'];
            $have = ($actual['Extra'] === 'auto_increment');
            if ($want !== $have) {
                $errors[$base_key . ':auto_increment'] = 'Auto increment should be ' . ($want ? 'true' : 'false');
            }
        }
        
        // charset
        if (isset($expected['charset'])) {
        	$want = strtolower($expected['charset']);
        	$have = '';
        	if (!empty($actual['Collation'])) {
        		$parts = explode('_', $actual['Collation']);
        		$have = strtolower($parts[0] ?? '');
        	}
        	if ($want !== $have) {
        		$current = $have ?: 'NULL';
        		$errors[$base_key . ':charset'] = "Charset should be {$expected['charset']} (current: {$current})";
        	}
        }
        // collation
        if (isset($expected['collation'])) {
        	$want = strtolower($expected['collation']);
        	$have = strtolower($actual['Collation'] ?? '');
        	if ($want !== $have) {
        		$current = $actual['Collation'] ?: 'NULL';
        		$errors[$base_key . ':collation'] = "Collation should be {$expected['collation']} (current: {$current})";
        	}
        }
        
    }

    function _get_db_indexes($table) {
        $rows = $this->db->query("SHOW INDEX FROM `{$table}`")->result_array();
        $idx = [];
        foreach ($rows as $r) {
            $name = $r['Key_name'];
            if (!isset($idx[$name])) {
                $idx[$name] = [
                    'columns' => [],
                    'type'    => ($name === 'PRIMARY') ? 'primary' : ($r['Non_unique'] == 0 ? 'unique' : 'index')
                ];
            }
            $idx[$name]['columns'][] = $r['Column_name'];
        }
        return $idx;
    }

    function _compare_index(&$errors, $base_key, $expected, $actual) {
        // columns
        if (isset($expected['columns'])) {
            $exp = implode(',', (array)$expected['columns']);
            $act = implode(',', $actual['columns'] ?? []);
            if ($exp !== $act) {
                $errors[$base_key . ':columns'] = "Columns should be [{$exp}]";
            }
        }

        // type
        if (isset($expected['type'])) {
            $exp = strtolower($expected['type']);
            $act = strtolower($actual['type']);
            if ($exp !== $act) {
                $errors[$base_key . ':type'] = "Index type should be {$exp}";
            }
        }
    }
    
	private function _normalise_default_value($value) {

		if ($value === null) {
			return 'NULL';
		}

		$s = trim((string)$value);
		if ($this->_is_timestamp_default($s)) {
			return 'CURRENT_TIMESTAMP';
		}

		return $s;

	}

	private function _is_timestamp_default($value) {

		return (bool)preg_match('/^current_timestamp(\(\))?$/i', trim((string)$value));

	}

	private function _migrate_table_from($table, $def) {

		$migrate = $def['migrate_from'] ?? null;
		if (empty($migrate['table'])) {
			return false;
		}

		$old_table = $migrate['table'];
		if (!$this->db->table_exists($old_table) || $this->db->table_exists($table)) {
			return false;
		}

		if (!$this->_execute('RENAME TABLE `'.$old_table.'` TO `'.$table.'`')) {
			return false;
		}

		if (!empty($migrate['columns']) && !empty($def['columns'])) {
			foreach ($migrate['columns'] as $old_col => $new_col) {
				if (!isset($def['columns'][$new_col])) {
					continue;
				}
				$col_spec = $def['columns'][$new_col];
				$defer = $this->_should_defer_auto_increment($table, $new_col, $col_spec, $def);
				$opts = $defer ? ['include_auto_increment' => false] : [];
				$col_def = $this->_build_column_definition($new_col, $col_spec, $opts);
				if (!$this->_execute('ALTER TABLE `'.$table.'` CHANGE `'.$old_col.'` '.$col_def)) {
					return false;
				}
			}
		}

		if (!empty($migrate['indexes']) && !empty($def['indexes'])) {
			$db_idx = $this->_get_db_indexes($table);
			foreach ($migrate['indexes'] as $old_idx => $new_idx) {
				if (isset($db_idx[$old_idx])) {
					if (!$this->_drop_index($table, $old_idx)) {
						return false;
					}
				}
				if (isset($def['indexes'][$new_idx])) {
					if (!$this->_create_index($table, $new_idx, $def['indexes'][$new_idx])) {
						return false;
					}
				}
			}
		}

		return empty($this->_fix_sql_errors);

	}

    protected function _get_after_clause($table, $new_column, $def) {
    	if (empty($def['columns']) || !is_array($def['columns'])) {
    		return '';
    	}
    
    	$column_order = array_keys($def['columns']);
    	$pos = array_search($new_column, $column_order);
    
    	if ($pos === false || $pos === 0) {
    		return ''; // first column in schema -> add at end (default)
    	}
    
    	$prev_column = $column_order[$pos - 1];
    
    	// only use AFTER if previous column already exists in DB
    	$db_cols = $this->_get_db_columns($table);
    	if (isset($db_cols[$prev_column])) {
    		return " AFTER `{$prev_column}`";
    	}
    
    	return ''; // fallback to end
    }
    
}