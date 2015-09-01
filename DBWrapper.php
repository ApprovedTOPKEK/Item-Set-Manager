<?php

/**
 * Class DBWrapper
 * Simple Wrapper for mysqli-class. 90% unused. Could and should clean up, but it isn't that important.
 */

class DBWrapper {

	public $mysqli;
	private $query, $select, $where, $orderBy, $limit;

	public function __construct(){
		global $server, $dbname, $dbpass, $database;
		$this->mysqli = mysqli_connect($server, $dbname, $dbpass, $database);
	}

	public function select($what, $where){
		$this->select = "SELECT ".$what." FROM `".$where."`";
		return $this;
	}

	public function where(){
		$argn = func_num_args();
		if($argn % 2 != 0) return $this;

		$args = func_get_args();
		$this->where = " WHERE ";
		for($i = 0; $i < $argn; $i+=2){
			$this->where .= "`".$args[$i]."`=".(string)$args[$i+1];
			if($i+2 < $argn) $this->where .= " AND ";
		}
		return $this;
	}

	public function orderBy(){
		$argn = func_num_args();
		if($argn % 2 != 0) return $this;

		$this->orderBy = " ORDER BY ";
		for($i = 0; $i < $argn; $i+=2){
			$this->orderBy .= "`".func_get_arg($i)."` ".func_get_arg($i+1);
			if($i+2 < $argn) $this->where .= ", ";
		}
		return $this;
	}

	public function limit($sindex, $max){
		$this->limit = " LIMIT ".$sindex.", ".$max;
		return $this;
	}

	public function buildQuery(){
		$this->query = $this->select.$this->where.$this->orderBy.$this->limit.";";
		return $this;
	}

	public function executeQuery($clear = true){
		$res = $this->mysqli->query($this->query);
		if(!$res){die($this->mysqli->error);}
		$ret = array();
		while($row = $res->fetch_assoc()){
			array_push($ret, $row);
		}

		if($clear) $this->clear();
		return $ret;
	}

	public function query($query, $clear = true){
		$this->query = $query;
		return $this->executeQuery($clear);
	}

	public function clear(){
		$this->query = "";
		$this->limit = "";
		$this->orderBy = "";
		$this->select = "";
		$this->where = "";
	}

}

?>