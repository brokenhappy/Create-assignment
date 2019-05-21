<?php
    function _DB_getSettings() {
        static $__settings = null;
        if ($__settings === null) // Downloads the data from the config if it was not loaded before
            return json_decode(file_get_contents("db_config.txt"));
        return $__settings;
    }

    /**
     * Establishes a connection with the database
     *
     * @return PDO a PDO object to communicate with the MySQL database
     */
    function DB_getConnection() : PDO {
        $dbSettings = _DB_getSettings();
        $PDO        = new PDO(
            "mysql:" 
            . "host=" . $dbSettings->host . ";"
            . "dbname=" . $dbSettings->schema . ";", 
            $dbSettings->username, 
            $dbSettings->password);
        $PDO->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $PDO;
    }
?>