<?php
class Mysql_i
{
	var $config;				//информация о подключении
	
	private $db_resource;		//ресурс подключения к базе данных
	private $memcache;			//Экз класса мемкеш
	
	var $debug		= true;		//флаг дебага. Если поднят - при ошибочном SQL запросе будет выведено сообщение об ошибке и скрипт будет остановлен
	
	//лог событий
	var $log 		= false;	//флаг лога редактирования данных. Если поднят, то в таблицу лога будут записаны события вызванные методами insert(), update(), delete()
	var $log_table	= 'log';	//имя таблицы лога
	var $user_id;				//id пользователя
	var $module_id;				//id модуля
	var $block_read_memcache = false; // блокировка чтения из memcache
	
	//лог SQL
	var $sql_log	= false;	//флаг логирования SQL запросов. Если поднят - все запросы будут записаны в файл
	var $sql_log_file = 'mysqli_log.sql'; //имя файла лога запросов.
	
	var $count_sql = 0;
	var $count_sql_memcache = 0;
	var $dump_sql = array();
	/*
	конструктор выполняет подключение к базе данных
	$host - хост
	$db - имя базы данных
	$user - имя пользователя
	$pass - пароль
	*/
	function __construct($host='', $db='', $user='', $pass='', $memcache_host='', $memcache_port='')
	{
		$this->config['host'] = $host;
		$this->config['user'] = $user;
		$this->config['pass'] = $pass;
		$this->config['db'] = $db;
		
		$this->db_resource = @mysqli_connect($host, $user, $pass, $db)
			or exit("Error of connect to Data Base ".mysqli_connect_error());
				
		@set_magic_quotes_runtime(0); //выключаем директиву magic_quotes_runtime (автоматическое экранирования спецсимволов при извлечении данных из базы)
		
		if(USE_SQL_UTF8)
		{
			$this->Query("SET NAMES 'utf8'");
			$this->Query("SET collation_connection = 'utf8_general_ci'");
			$this->Query("SET collation_server = 'utf8_general_ci'");
			$this->Query("SET character_set_client = 'utf8'");
			$this->Query("SET character_set_connection = 'utf8'");
			$this->Query("SET character_set_results = 'utf8'");
			$this->Query("SET character_set_server = 'utf8'");
		}
		
		$this->Query("SET LOCAL time_zone='".date('P')."'");
		
		if (USE_MEMCACHE)
		{
			$this->memcache = new Memcache;

			@$this->memcache->connect($memcache_host, $memcache_port)
				or exit("Error of create connecting with Memcache.");
				
		//    dump($this->memcache);
		}
	}
	
	/*
	закрывает соединение с базой данных
	*/
	function __destruct()
	{
		if(is_resource($this->db_resource))
			mysqli_close($this->db_resource);
	}
	
	/*
	метод включения SQL лога
	при включении файл лога очищается
	*/
	function setSqlLog($val=true)
	{
		$this->sql_log = $val;
		
		if($val)
			@file_put_contents($this->sql_log_file, null);
	}
	
	/*
	метод установки флага лога
	$val - значение флага
	$user_id - id пользователя
	$module_id - id модуля
	*/
	function setLog($log=true, $user_id=null, $module_id=null)
	{
		$this->log = $log;
		
		if($user_id)
			$this->user_id = $user_id;
		
		if($module_id)
			$this->module_id = $module_id;
	}
	
	/*
	возвращает номер и текст ошибки SQL
	*/
	function getErrorText()
	{
		return mysqli_errno($this->db_resource).": ".mysqli_error($this->db_resource);
	}
	
	/*
	метод выполняет запрос к базе данных
	Возвращает результат выполнения запроса
	$query - запрос
	Если флаг SQL лога поднят - записать в файл лога запрос
	Если запрос вызвал ошибку и флаг дебага поднят - вывести сообщение об ошибке и остановит скрипт
	*/
	function Query($query)
	{
		$this->count_sql++;

		if($this->sql_log and $this->sql_log_file)
		{
			$file = @fopen($this->sql_log_file, 'a+');
			@fputs($file, $query."\r\n");
			@fclose($file);
		}
		//dump($query);
		$time_start = timeMeasure();
		$res = mysqli_query($this->db_resource, $query);
		
		if ($this->debug === true)
			$this->dump_sql[] = array("query" => $query, 'time' => round(timeMeasure()-$time_start, 10));
		
		if(mysqli_errno($this->db_resource) and $this->debug)
			exit('Error of SQL<br />Query: <code>'.$query.'</code><br />'.$this->getErrorText());
		elseif(mysqli_errno($this->db_resource))
			return false;
		else
			return $res;
	}
	
	/*
	возвращает строку из результата запроса в виде одномерного массива или единственного значнения
	$res - ресурс результата
	$field - имя поля
		Если задано - возвращается единственное значение указанного поля
	*/
	function Data($res, $field=null)
	{
		$data = mysqli_fetch_assoc($res);

		if(!isset($field))
			return $data;
		else
		{
		    if(isset($data[$field]))
			return $data[$field];
		    else
			return $data;
		}
					
		mysqli_free_result($this->db_resource, $res);
	}
	
	/*
	возвращает строку из результата запроса в виде одномерного массива или единственного значнения
	$res - ресурс результата
	$field - имя поля
		Если задано - возвращается единственное значение указанного поля
	*/
	function getDataFromMemcache($memcache_key)
	{
		if (USE_MEMCACHE && $memcache_key && $this->block_read_memcache === false)
		{
			$get_result = $this -> memcache->get($memcache_key);
			if ($get_result !== false)
			{
				if ($get_result == 'sql_empty')
					return array();
				else
					return $get_result;
			}
			else 
				return false;
		}
		else 
			return false;
	}
	
	/*
	возвращает строку из результата запроса в виде одномерного массива или единственного значнения
	$res - ресурс результата
	$field - имя поля
		Если задано - возвращается единственное значение указанного поля
	*/
	function setDataToMemcache($memcache_key, $result,  $time = null)
	{
		if (USE_MEMCACHE && $memcache_key && is_array($result))
		{
			if (!isset($time))
				$time = TIME_MEMCACHE;
			
			if (empty($result))
				$this->memcache->set($memcache_key, 'sql_empty', MEMCACHE_COMPRESSED, $time) 
					or die ("Error failed to save data at the server Memcache");
			else
				$this->memcache->set($memcache_key, $result, MEMCACHE_COMPRESSED, $time) 
					or die ("Error failed to save data at the server Memcache");
		}
		elseif (USE_MEMCACHE && $memcache_key && isset($result))
			$this->memcache->set($memcache_key, $result, MEMCACHE_COMPRESSED, $time) 
				or die ("Error failed to save data at the server Memcache");
		else 
			return false;
	}
	
	/*
	возвращает результат запроса в виде двумерного (одномерный) массива
	$res - ресурс результата
	$key - имя ключа массива строки, которое будет взято за номер строки
		если не задан - номер будет сгенерирован автоматически
	$key - имя ключа массива строки, которое будет взято за значение строки
		если задан - результат будет представлять из себя уже одномерный массив
	*/
	function DataFull($res, $key=null, $value=null)
	{
		$data = array();
		
		while($row = mysqli_fetch_assoc($res))
		{
			if($key)
			{
				if($value)
					$data[$row[$key]] = $row[$value];
				else
					$data[$row[$key]] = $row;
			}
			elseif($value)
				$data[] = $row[$value];
			else
				$data[] = $row;
		}
		
		return $data;
	}
	
	/*
	возвращает число сток в результате запроса
	$res - ресурс результата
	*/
	function getNum($res)
	{
		return mysqli_num_rows($res);
	}
	
	/*
	возвращает сгенерированный ID последней операцией INSERT
	*/
	function getInsertId()
	{
		return mysqli_insert_id($this->db_resource);
	}
	
	/*
	рекурсивный метод обработки значений перед вставкой в SQL запрос
	Удаляет лишние пробелы в начале и конце значения переменной (строки), экранирует спецсимволы
	$val - значение или массив
	$tags - флаг сохранения html тегов (необязательный параметр. по умолчанию поднят)
	*/
	function preData($val, $tags=true)
	{
		if(is_array($val))
	    {
			foreach($val as $key=>$value)
				$val[$key] = $this->preData($value, $tags);
	    }
	    elseif(is_string($val))
	    {
			$val = trim($val);
			
	        if(!$tags)
				$val = strip_tags($val);
			
			$val = mysqli_real_escape_string($this->db_resource, $val);
			
	        $val = "'".$val."'";
	    }
	    elseif(is_bool($val))
			$val = (int)$val;
		elseif($val===null)
			$val = "''";
		
	    return $val;
	}
	
	/*
	метод вставки строки в таблицу базы даннных
	$table - имя таблицы базы, в которую надо всавить строку
	$val - ассоциативный массив значений полей (ключ - имя поля)
	$tags - флаг сохранения html тегов (необязательный параметр. по умолчанию поднят)
	Возвращает сгенерированный идентификатор
	*/
	function insert($table, $val, $tags=true)
	{
		$col_list = array();
		$val_list = array();
		
		foreach($val as $key=>$value)
		{
			$col_list[] = $key;
			$val_list[] = $value;
		}
		
		$col_list = implode(', ', $col_list);
		
		$val_list = $this->preData($val_list, $tags);
		$val_list = implode(', ', $val_list);
		
		$error = false;
		$this->Query('insert into '.$table.' ('.$col_list.') values ('.$val_list.')') or $error = true;
	
		if(USE_MEMCACHE)
			$this->memcache->flush();
			
		if($error)
			return false;
		
		elseif($id = $this->getInsertId())
		{
			if($this->log)
				$this->systemLog($table, $id, 1);
			
			return $id;
		}
		else
			return true;
	}
	
	/*
	метод обновления строки в таблице базы даннных
	Если строка с указанным ключем уже существует - обновляет строку эту строку
	Иначе вставляет новую строку
	$table - имя таблицы базы, в которой надо обновить строку
	$val - ассоциативный массив значений полей (ключ - имя поля)
	$tags - флаг сохранения html тегов (необязательный параметр. по умолчанию поднят)
	Возвращает (сгенерированный) идентификатор
	*/
	function replace($table, $val, $tags=true)
	{
		$col_list = array();
		$val_list = array();
		
		foreach($val as $key=>$value)
		{
			$col_list[] = $key;
			$val_list[] = $value;
		}
		
		$col_list = implode(', ', $col_list);
		
		$val_list = $this->preData($val_list, $tags);
		$val_list = implode(', ', $val_list);
		
		$error = false;
		$this->Query('replace into '.$table.' ('.$col_list.') values ('.$val_list.')') or $error = true;
		
		if($error)
			return false;
		
		elseif($id = $this->getInsertId())
		{
			if($this->log)
			{
				if(mysqli_affected_rows($this->db_resource)>1)
					$action = 2; //обновление
				else
					$action = 1; //вставка
				
				$this->systemLog($table, $id, $action);
			}
			
			return $id;
		}
		else
			return true;
	}
	
	/*
	метод изменения строки в таблице базы даннных
	$table - имя таблицы базы
	$val - ассоциативный массив значений полей (ключ - имя поля)
	$where - ассоциативный массив полей идентификаторов(ключ - имя поля)
	$tags - флаг сохранения html тегов (необязательный параметр. по умолчанию поднят)
	*/
	function update($table, $val, $where, $tags=true)
	{
		$col_list = array();
		
		foreach($val as $key=>$value)
			if(is_numeric($key))
				$col_list[] = $value;
			else
				$col_list[] = $key.'='.$this->preData($value, $tags);
		
		$col_list = implode(', ', $col_list);
		
		$where_list = array();
		
		foreach($where as $key=>$vaule)
			$where_list[] = $key.'='.$this->preData($vaule);
		
		$where_list = implode(' and ', $where_list);
		
		$error = false;

		$this->Query('update '.$table.' set '.$col_list.' where '.$where_list) or $error = true;
		
		if(USE_MEMCACHE)
			$this->memcache->flush();
			
		if($error)
			return false;
		else
		{
			if($this->log and isset($where['id']))
				$this->systemLog($table, $where['id'], 2);
			
			return true;
		}
	}
	
	/*
	метод удаления строки из таблицы базы даннных
	$table - имя таблицы базы, в которую надо всавить строку
	$where - ассоциативный массив полей идентификаторов(ключ - имя поля)
	*/
	function delete($table, $where)
	{
		$where_list = array();
		
		foreach($where as $key=>$vaule)
			$where_list[] = $key.'='.$this->preData($vaule);
		
		$where_list = implode(' and ', $where_list);
		
		$error = false;
		$this->Query('delete from '.$table.' where '.$where_list) or $error = true;
		
		if(USE_MEMCACHE)
			$this->memcache->flush();
			
		if($error)
			return false;
		else
		{
			if($this->log and isset($where['id']))
				$this->systemLog($table, $where['id'], 3);
			
			return true;
		}
	}
	
	/*
	метод осуществляет простую выборку данных из ОДНОЙ строки таблицы
	$table - имя таблицы или список имен таблиц
	$columns - имя поля или список
		Если является массивом - будет возвращен результат в виде одномерного ассоциативного массива со значениями указанных полей
		Иначе если строка - будет возвращено единственное значение указанного поля
		Иначе если ничему не равен - будут возвращен результат в виде одномерного ассоциативного массива со значениями всех полей
	$where - список критериев
		Может являться ассоциативным массивом (ключ - имя поля). В этом случае все критерии будут объеденены через оператор AND
		Может являться строкой
	*/
	function select($table=null, $columns=null, $where=null, $use_memcache = true, $flag_full = false)
	{
		$sql = 'select';
		
		if(is_array($columns))
		{
			$sql .= ' '.implode(', ', $columns);
		}
		elseif($columns)
			$sql .= ' '.$columns;
		else
			$sql .= ' *';
		
		if($table)
			$sql .= ' from '.$table;
		
		if(is_array($where))
		{
			$where_list = array();
			foreach($where as $key=>$val)
				$where_list[] = $key.'='.$this->preData($val);
			
			$sql .= ' where '.implode(' and ', $where_list);
		}
		elseif($where)
			$sql .= ' where '.$where;
		
		$memcache_key = md5($sql);
		
		if (USE_MEMCACHE && $use_memcache)
		{
			$get_result = $this -> memcache->get($memcache_key);
			if ($get_result !== false)
			{
				$this->count_sql_memcache++;
				
				return $get_result;
			}
		}
		
		$result = $this->Query($sql);
		if(!$result)
			return false;
		
		if(is_array($columns) or !$columns)
		{
			if (isset($flag_full)&&$flag_full==true)
				$result = $this->DataFull($result);
			else
				$result = $this->Data($result);
				
			if (USE_MEMCACHE && $use_memcache)
				$this -> memcache->set($memcache_key, $result, MEMCACHE_COMPRESSED, TIME_MEMCACHE) 
					or die ("Error failed to save data at the server Memcache");
					
			return $result;
		}
		elseif($columns)
		{

			if (isset($flag_full)&&$flag_full==true)
				$result = $this->DataFull($result);
			else
				$result = $this->Data($result, $columns);
			if (USE_MEMCACHE && $use_memcache)
				$this -> memcache->set($memcache_key, $result, MEMCACHE_COMPRESSED, TIME_MEMCACHE) 
					or die ("Error failed to save data at the server Memcache");
			return $result;
		}
	}
	
	/*
	очищает (пересоздает заново) одну или несколько таблиц
	$table - имя таблицы или список имен таблиц через запятую
	*/
	function truncate($table)
	{
		$this->Query('truncate table '.$table);
	}
	
	/*
	записывает событие в системный лог (используется толко в администраторском разделе)
	формат лога: Id_пользователя, Имя_таблицы, Id_объекта, Действие (1-insert, 2-update, 3-delete), Дата_и_время
	$table - имя таблицы, задействованной в событии
	$id - идентификатор строки, над которой произошло событие
	$action - ключ события
	*/
	function systemLog($table, $id, $action)
	{
		if($this->log and $this->user_id)
			$this->Query('insert into '.$this->log_table." (user_id, module_id, table_name, object_id, action) values (".$this->user_id.", ".$this->module_id.", '$table', $id, $action)");
	}
}
?>