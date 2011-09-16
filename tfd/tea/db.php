<?php

	require_once(TEA_DIR.'db_class'.EXT);

	class DB extends MySQL{
	
		function __construct(){
			parent::__construct();
		}
		
		function table_exists($table){
			$sql = sprintf("SHOW TABLES LIKE '%s'", mysql_real_escape_string($table));
			$tables = parent::query($sql, true);
			if(empty($tables)) return false;
			return true;
		}
		
		function drop_table($table){
			$sql = sprintf("DROP TABLE IF EXISTS `%s`", mysql_real_escape_string($table));
			try{
				parent::query($sql);	
			}catch(Exception $e){
				echo "Could not drop table {$table}. Error message: {$e->getMessage()}.\n";
				exit(0);
			}
		}
		
		function create_table($table, $columns){
			$sql = sprintf("CREATE TABLE `%s` (", mysql_real_escape_string($table));
			$keys = array();
			foreach($columns as $name => $info){
				// is it a key?
				if(!empty($info['key'])){
					$keys[$name] = $info['key'];
				}
				switch($info['type']){
					case 'float':
					case 'double':
					case 'tinytext':
					case 'text':
					case 'mediumtext':
					case 'longtext':
					case 'date':
					case 'datetime':
					case 'timestamp':
					case 'time':
						$type = $info['type'];
						break;
					default:
						$type = $info['type'].'('.$info['length'].')';
				}
				if($info['key'] !== 'primary'){
					if(!empty($info['default']) || $info['default'] !== false){
						if(strtoupper($info['default']) === $info['default'] && !empty($info['default'])){
							$default = sprintf("DEFAULT %s", mysql_real_escape_string($info['default']));
						}else{
							$default = sprintf("DEFAULT '%s'", mysql_real_escape_string($info['default']));
						}
					}elseif($info['null'] === true){
						$default = 'DEFAULT NULL';
					}elseif(preg_match('/time/', $type)){
						$default = 'DEFAULT CURRENT_TIMESTAMP';
					}else{
						$default = 'DEFAULT NULL';
						$info['null'] = true;
					}
					$null = ($info['null'] === false) ? 'NOT NULL' : '';
				}
				$sql .= sprintf("`%s` %s %s %s %s,",
					$name, $type, $null, $default, strtoupper($info['extra'])
				);
			}
			foreach($keys as $field => $type){
				$type = ($type === true) ? 'KEY' : strtoupper($type).' KEY';
				$sql .= $type.' ';
				if($type != 'primary') $sql .= sprintf("`%s`", mysql_real_escape_string($field));
				$sql .= sprintf("(`%s`),", mysql_real_escape_string($field));
			}
			$sql = substr($sql, 0, -1);
			$sql .= ')';
			try{
				parent::query($sql);
			}catch(Exception $e){
				echo "{$e->getMessage()}\n";
				exit(0);
			}
		}
		
		function add_column($table, $name, $info, $after = null){
			switch($info['type']){
				case 'float':
				case 'double':
				case 'tinytext':
				case 'text':
				case 'mediumtext':
				case 'longtext':
				case 'date':
				case 'datetime':
				case 'timestamp':
				case 'time':
					$type = $info['type'];
					break;
				default:
					$type = $info['type'].'('.$info['length'].')';
			}
			$sql = sprintf("ALTER TABLE `%s` ADD `%s` %s", mysql_real_escape_string($table), mysql_real_escape_string($name), $type);
			if($info['null'] === false) $sql .= " NOT NULL";
			if(!empty($info['default'])){
				$sql .= (strtoupper($info['default']) === $info['default']) ? " DEFAULT {$info['default']}" : " DEFAULT {$info['default']}";
			}
			if(!is_null($info['after'])){
				$sql .= sprintf(" AFTER `%s`", mysql_real_escape_string($info['after']));
			}
			if(!empty($info['key'])){
				$sql .= sprintf(", ADD %s(`%s`)", ($info['key'] == 'primary') ? 'PRIMARY KEY' : strtoupper($info['key']), $name);
			}
			try{
				parent::query($sql);
			}catch(Eception $e){
				echo $e->getMessage().PHP_EOL;
				exit(0);
			}
		}
		
		function drop_column($table, $column){
			$sql = sprintf("ALTER TABLE `%s` DROP `%s`", mysql_real_escape_string($table), mysql_real_escape_string($column));
			try{
				parent::query($sql);
			}catch(Exception $e){
				echo $e->getMessage().PHP_EOL;
				exit(0);
			}
		}
		
		function add_key($table, $column, $key_type){
			if($key_type == 'primary') $key_type .= ' key';
			$sql = sprintf("ALTER TABLE `%s` ADD %s(`%s`)", mysql_real_escape_string($table), strtoupper($key_type), mysql_real_escape_string($column));
			try{
				parent::query($sql);
			}catch(Exception $e){
				echo $e->getMessage().PHP_EOL;
				exit(0);
			}
		}
		
		function remove_key($table, $column){
			$sql = sprintf("SHOW FIELDS FROM `%s` WHERE `Field` = '%s'", mysql_real_escape_string($table), mysql_real_escape_string($column));
			$col_info = parent::query($sql, true);
			if($col_info[0]['Key'] === 'PRI'){
				// DROP PRIMARY KEY
				$drop = "PRIMARY KEY";
			}elseif($col_info[0]['Key'] === 'UNI'){
				// ALTER TABLE test DROP KEY `test`
				$drop = sprintf("KEY `%s`", mysql_real_escape_string($column));
			}else{
				// ALTER TABLE test DROP INDEX `test`
				$drop = sprintf("INDEX `%s`", mysql_real_escape_string($column));
			}
			try{
				$sql = sprintf("ALTER TABLE `%s` DROP %s", mysql_real_escape_string($table), $drop);
				parent::query($sql);
			}catch(Exception $e){
				echo $e->getMessage().PHP_EOL;
				exit(0);
			}
		}
	
	}