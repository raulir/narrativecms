<?php defined('BASEPATH') OR exit('No direct script access allowed');

class cms_schema_model extends Model {

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
				$errors[$base_key] = "Table {$table} not found";
				continue;
			}

			$db_cols = $this->_get_db_columns($table);
			if (!empty($def['columns'])) {
				foreach ($def['columns'] as $col_name => $spec) {
					$col_key = $base_key . ':columns:' . $col_name;
					if (!isset($db_cols[$col_name])) {
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
					$this->_compare_index($errors, $idx_key, $spec, $db_idx[$idx_name]);
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
			return $count > 0;
		}

		$table = $parts[1] ?? '';
		if (!isset($merged[$table]) || ($owner[$table] ?? '') !== $module) {
			return false;
		}

		$def = $merged[$table];

		// 2. Table level
		if (count($parts) === 2) {
			return $this->_fix_table($table, $def);
		}

		$section = $parts[2] ?? '';

		// 3. Column level (full column even if specific property is in path)
		if ($section === 'columns') {
			$column = $parts[3] ?? '';
			if (isset($def['columns'][$column])) {
				return $this->_fix_column($table, $column, $def['columns'][$column], $def);
			}
		}

		// 4. Index level (full index even if specific property)
		if ($section === 'indexes') {
			$index = $parts[3] ?? '';
			if (isset($def['indexes'][$index])) {
				return $this->_fix_index($table, $index, $def['indexes'][$index]);
			}
		}

		return false;
	}
	
	function get_schema_errors_with_status() {
	    $errors = $this->check_schema();
	    $grouped = [];
	
	    list($merged, $owner) = $this->_build_merged_schemas();
	
	    foreach ($errors as $key => $message) {
	        $parts = explode(':', $key);
	        $module  = $parts[0] ?? '(unknown)';
	        $table   = $parts[1] ?? '';
	        $section = $parts[2] ?? '';
	
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
	        'has_errors' => !empty($errors),
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
			return $this->_create_table($table, $def);
		}

		$ok = true;

		// columns – processed in exact JSON order
		if (!empty($def['columns'])) {
			foreach ($def['columns'] as $col => $spec) {
				if (!$this->_fix_column($table, $col, $spec, $def)) {
					$ok = false;
				}
			}
		}

		// indexes stay unchanged
		if (!empty($def['indexes'])) {
			foreach ($def['indexes'] as $idx => $spec) {
				if (!$this->_fix_index($table, $idx, $spec)) {
					$ok = false;
				}
			}
		}

		return $ok;
	}
	
	private function _fix_column($table, $column, $spec, $def) {
		$db_cols = $this->_get_db_columns($table);
	
		if (!isset($db_cols[$column])) {
			return $this->_add_column($table, $column, $spec, $def);
		}
	
		$tmp_errors = [];
		$this->_compare_column($tmp_errors, 'temp', $spec, $db_cols[$column]);
		if (empty($tmp_errors)) {
			return true;
		}
	
		$this->_modify_column($table, $column, $spec);
		return true;
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

	private function _add_column($table, $column, $spec, $def) {
		$col_def = $this->_build_column_definition($column, $spec);
		$after   = $this->_get_after_clause($table, $column, $def);
	
		$sql = "ALTER TABLE `{$table}` ADD COLUMN {$col_def}{$after}";
		$this->db->query($sql);
	
		return true;
	}

	private function _modify_column($table, $column, $spec) {
		$def = $this->_build_column_definition($column, $spec);
		$sql = "ALTER TABLE `{$table}` MODIFY COLUMN {$def}";
		return $this->_execute($sql);
	}

	private function _build_column_definition($name, $spec) {
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
			} else {
				$sql .= " DEFAULT " . $this->db->escape($spec['default']);
			}
		}

		if (!empty($spec['auto_increment'])) $sql .= " AUTO_INCREMENT";

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
			$this->db->query("ALTER TABLE `{$table}` DROP PRIMARY KEY");
		} else {
			$this->db->query("ALTER TABLE `{$table}` DROP INDEX IF EXISTS `{$index_name}`");
		}
	}
	
	private function _create_index($table, $index_name, $spec) {
		$def = $this->_build_index_definition($index_name, $spec);
		$sql = "ALTER TABLE `{$table}` ADD {$def}";
		return $this->_execute($sql);
	}

	private function _execute($sql, $bind = []) {
		if (!empty($bind)) {
			$this->db->query($sql, $bind);
		} else {
			$this->db->query($sql);
		}
		return true;
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
            $want = $expected['default'] === null ? 'NULL' : (string)$expected['default'];
            $have = $actual['Default'] === null ? 'NULL' : (string)$actual['Default'];
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