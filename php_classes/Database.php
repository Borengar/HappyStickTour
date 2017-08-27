<?php

class Database {
	
	private $database_host;
	private $database_dbname;
	private $database_username;
	private $database_password;

	function __construct() {
		$config = parse_ini_file('config.ini');
		$this->database_host = $config['databaseHost'];
		$this->database_dbname = $config['databaseDbname'];
		$this->database_username = $config['databaseUsername'];
		$this->database_password = $config['databasePassword'];
	}

	public function getConnection() {
		$connection = new PDO('mysql:host=' . $this->database_host . ';dbname=' . $this->database_dbname . ';charset=utf8', $this->database_username, $this->database_password);
		return $connection;
	}

}

?>