<?php
	function __get_db_settings() {
		static $__settings = null;
		if ($__settings === null) //downloads the data from the config if it was not loaded before
			return json_decode(file_get_contents("db_config.txt"));
		return $__settings;
	}

	/**
	 * Establishes a connection with the database
	 *
	 * @return PDO a PDO object to communicate with the MySQL database
	 */
	function get_db_connection() : PDO {
		$db_settings = __get_db_settings();
		$PDO = new PDO(
			"mysql:" 
			. "host=" . $db_settings->host . ";"
			. "dbname=" . $db_settings->schema . ";", 
			$db_settings->username, 
			$db_settings->password);
		$PDO->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		return $PDO;
	}
?>