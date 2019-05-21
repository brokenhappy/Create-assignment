<?php
	function get_db_connection() {
		$dbpass = "";
		$username = "root";
		$dbname = "create_opdracht";
		$conn = new PDO("mysql:host=localhost;dbname=$dbname;", $username, $dbpass);
		$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		return $conn;
	}
?>