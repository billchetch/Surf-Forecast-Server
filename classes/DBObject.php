<?php
class DBObject{
	protected static $dbh = null; //PDO object
	
	//to be set in initialised override ... every child class must have this declared
	protected static $config = array();
	
	const READ_MISSING_VALUES_ONLY = 1;
	const READ_ALL_VALUES = 2;
	
	public $rowdata;
	public $id;
	
	public static function getConfig($key){
		return isset(static::$config[$key]) ? static::$config[$key] : null;
	}
	
	public static function init($dbh, $params = null){
		if(empty($dbh))throw new Exception("DBOject::create No database supplied");
		
		self::$dbh = $dbh;
		
		static::initialise();
		
		if(empty(static::$config['TABLE_NAME']))throw new Exception("No table name in config");
		
		//get table name
		$t = static::$config['TABLE_NAME'];
		$sql = "select column_name from information_schema.columns where table_name = '$t'";
		$q = $dbh->query($sql);
		$columns = array();
		while($row = $q->fetch()){
			$columns[] = $row['column_name'];
		}
		static::$config['TABLE_COLUMNS'] = $columns;
		
		
		if(empty(static::$config['SELECT_ROW_BY_ID_SQL'])){
			$t = static::$config['TABLE_NAME'];
			$sql = "SELECT * FROM $t WHERE id=:id";
			static::$config['SELECT_ROW_BY_ID_SQL'] = $sql;
		}
		static::$config['SELECT_ROW_BY_ID_STATEMENT'] = self::$dbh->prepare(static::$config['SELECT_ROW_BY_ID_SQL']);
			
		if(!empty(static::$config['SELECT_ROW_SQL'])){
			$sql = static::$config['SELECT_ROW_SQL'];
			
			static::$config['SELECT_ROW_STATEMENT'] = self::$dbh->prepare($sql);
			static::$config['SELECT_ROW_PARAMS'] = self::extractBoundParameters($sql);
		}
			
		if(!empty(static::$config['SELECT_ROWS_SQL'])){
			$sql = static::$config['SELECT_ROWS_SQL'];
			static::$config['SELECT_ROWS_PARAMS'] = self::extractBoundParameters($sql);
			static::$config['SELECT_ROWS_STATEMENT'] = self::$dbh->prepare($sql);
		}
		
		if(empty(static::$config['DELETE_ROW_BY_ID_STATEMENT'])){
			$sql = "DELETE FROM $t WHERE id=:id LIMIT 1";
			static::$config['DELETE_ROW_BY_ID_STATEMENT'] = self::$dbh->prepare($sql);
		}
	}
	
	public static function initialise(){
		//dynamic opportunity to set config vars ... override this	
	}	
	
	public static function createInstance($dbh, $rowdata = null, $readFromDB = self::READ_MISSING_VALUES_ONLY, $params = null){
		self::init($dbh);

		return new static($rowdata, $readFromDB);
	}
	
	public static function createInstanceFromID($dbh, $id, $readFromDB = self::READ_MISSING_VALUES_ONLY, $params = null){
		return static::createInstance($dbh, array('id'=>$id), $readFromDB, $params);
	}
	
	public static function createCollection($dbh, $params = null){
		self::init($dbh);
		
		$stmt = null;
		if($params && isset($params['SQL']) && $params['SQL']){
			$stmt = self::$dbh->prepare($params['SQL']);
			unset($params['SQL']);
		} else  {
			if(empty(static::$config['SELECT_ROWS_STATEMENT']))throw new Exception("No collection statement set");
			$stmt = static::$config['SELECT_ROWS_STATEMENT'];
		}
		if(empty($stmt))throw new Exception("No statement for collection query");
		
		try{
			$stmt->execute($params);
		} catch (PDOException $e){
			throw $e;
		}
		$instances = array();
		while($row = $stmt->fetch(PDO::FETCH_ASSOC)){
			$instances[] = new static($row, false);
		}
		return $instances;
	}
	
	public static function extractBoundParameters($sql){
		$ar = explode(':', $sql.' ');
		$params = array();
		foreach ($ar as $ps){
			$param = substr($ps, 0, strpos($ps, ' '));
			if(strpos($sql, ':'.$param) !== false){
				array_push($params, $param);
			}
		}	
		return $params;
	}

	private static function parseFieldList($fieldList, $asArray){
		if(is_string($fieldList))$fieldList = explode(',', $fieldList);
		$fieldStr = "";
		$delimiter = "-";
		foreach($fieldList as $fieldName){
			$fieldStr.= ($fieldStr ? "," : "").$delimiter.$fieldName;
		}
		
		$fieldList = str_replace($delimiter, '', $fieldStr);
		$paramNames = str_replace($delimiter, ':', $fieldStr);
		
		
		if($asArray){
			$fieldList = explode(',', $fieldList);
			$paramNames = explode(',', $paramNames);
		}
		
		return array('fields'=>$fieldList, 'params'=>$paramNames);
	}
	
	public static function createInsertSQL($tableName, $fieldList){
		if(empty(self::$dbh))throw new Exception("Cannot create statement if database has not been set");
		$fl = self::parseFieldList($fieldList, false);
		$sql = "INSERT INTO $tableName (".$fl['fields'].") VALUES (".$fl['params'].")";
		return $sql;
	}
	
	public function createInsertStatement($fieldList){
		$sql = self::createInsertSQL(static::$config['TABLE_NAME'], $fieldList);
		return self::$dbh->prepare($sql);
	}
	
	public static function createUpdateSQL($tableName, $fieldList, $filter){
		if(empty(self::$dbh))throw new Exception("Cannot create statement if database has not been set");
		
		$fl = self::parseFieldList($fieldList, true);
		$sql = "";
		for($i = 0; $i < count($fl['fields']); $i++){
			$f = $fl['fields'][$i];
			$p = $fl['params'][$i];
			$sql.= ($sql ? "," : "")."$f=$p"; 
		}
		$sql = "UPDATE $tableName SET $sql WHERE $filter";
		return $sql;
	}
	
	public static function now(){
		if(empty(self::$dbh))throw new Exception("Database has not been set");
		$stmt = self::$dbh->query('SELECT NOW()');
		$row = $stmt->fetch();
		return $row[0];
	}
	
	public static function setUTC(){
		if(empty(self::$dbh))throw new Exception("Database has not been set");
		self::$dbh->query('SET time_zone = "+00:00"'); //UTC for all
	}
	
	public static function tzoffset(){
		if(empty(self::$dbh))throw new Exception("Database has not been set");
		$stmt = self::$dbh->query("SELECT CONCAT(IF(NOW()>=UTC_TIMESTAMP,'+','-'),TIME_FORMAT(TIMEDIFF(NOW(),UTC_TIMESTAMP),'%H%m'))");
		$row = $stmt->fetch();
		return $row[0];
	}
	
	public function __construct($rowdata, $readFromDB = self::READ_MISSING_VALUES_ONLY){
		$this->rowdata = $rowdata;
		if($rowdata && isset($rowdata['id'])){
			$this->id = $rowdata['id'];
		}
		if($readFromDB){
			$this->read();
			
			//write back the values we passed in construction
			if($readFromDB == self::READ_MISSING_VALUES_ONLY && $rowdata){
				foreach($rowdata as $k=>$v){
					if($k == 'id')continue;
					$this->rowdata[$k] = $v;
				}
			}
		} else {
			$this->rowdata = $rowdata;
		}
	}
	
	public function setID($id){
		if(isset($this->rowdata))$this->rowdata['id'] = $id;
		$this->id = $id;
	}
	
	public function setRowData($rd, $assignID = false){
		if(empty($this->rowdata))$this->rowdata = array();
		foreach($rd as $k=>$v){
			$this->rowdata[$k] = $v;
		}
		if($assignID && isset($this->rowdata['id']) && $this->rowdata['id'])$this->id = $this->rowdata['id'];
	}
	
	protected function assignR2V(&$val, $fieldName){
		if(isset($this->rowdata) && isset($this->rowdata[$fieldName]))$val = $this->rowdata[$fieldName];
	}
	
	public function createUpdateStatement($fieldList){
		$filter = static::$config['TABLE_NAME'].".id=".$this->id;
		$sql = self::createUpdateSQL(static::$config['TABLE_NAME'], $fieldList, $filter);
		return self::$dbh->prepare($sql);
	}
	
	public function read(){
		$stmt = null;
		$params = null;
			
		if(!empty($this->id)){
			$stmt = static::$config['SELECT_ROW_BY_ID_STATEMENT'];
			$params = array('id');
			$this->rowdata['id'] = $this->id;
		} elseif(isset($this->rowdata)) {
			$stmt = static::$config['SELECT_ROW_STATEMENT'];
			$params = static::$config['SELECT_ROW_PARAMS'];
		}
		if(empty($stmt)){
			return; //fail silently
		}
		try{ 
			$vals = array();
			foreach($params as $param){
				if(isset($this->rowdata[$param]))$vals[$param] = $this->rowdata[$param]; 
			}
			$stmt->execute($vals);
			$row = $stmt->fetch(PDO::FETCH_ASSOC);
			if($row){
				$this->rowdata = $row;
				if(!empty($this->rowdata['id'])){
					$this->id = $this->rowdata['id'];
				}
			}
			
		} catch (PDOException $e){
			//most likely rowdata doesn't match sql
			throw $e;
		}
	}
	
	public function write(){
		if(empty($this->rowdata))throw new Error("No row data to write");
		$stmt = null;
		$vals = $this->rowdata;
		unset($vals['id']); //just in case
		
		//ensure that only table values are written
		$columns = static::$config['TABLE_COLUMNS'];
		foreach($vals as $k=>$v){
			if(!in_array($k, $columns))unset($vals[$k]);
		}
		
		if(empty($this->id)){ //insert
			$stmt = $this->createInsertStatement(array_keys($vals));
			$stmt->execute($vals);
			$this->id = self::$dbh->lastInsertId();
			return $this->id;	
		} else { //update
			$stmt = $this->createUpdateStatement(array_keys($vals));
			$stmt->execute($vals);
			return $this->id;
		}
	}
	
	
	public function delete(){
		if(empty($this->id)){
			$id = isset($this->rowdata['id']) && $this->rowdata['id'] ? $this->rowdata['id'] : null;
		} else {
			$id = $this->id;
		}
		if(empty($id))throw new Exception("No ID specified for delete");
		
		$vals = array('id'=>$id);
		$stmt = static::$config['DELETE_ROW_BY_ID_STATEMENT'];
		$stmt->execute($vals);
		
		$this->id = null;
		$this->rowdata = null;
		return $id;
	}
}
?>