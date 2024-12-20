<?php
    //meta data
    header("Access-Control-Allow-Origin: *");
    header("Content-Type: application/json; charset=utf-8");
    header("Access-Control-Allow-Methods: POST, GET, PATCH, OPTIONS");
    header("Access-Control-Max-Age: 3600");
    header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Auth-User");

    if ($_SERVER['REQUEST_METHOD'] == "OPTIONS") {
      header('Access-Control-Allow-Origin: *');
      header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Auth-User");
      header("HTTP/1.1 200 OK");
      die();
    }
    date_default_timezone_set("Asia/Manila");

    define("SERVER", "localhost");
    define("DBASE", "tma"); //enter your own databasename
    define("USER", "root");
    define("PWORD", "");
    define("TOKEN_KEY", "D762D526BD8E1E5887C97E21JH2GBOKUGAYOWAIIINDAAAAAAAJ1H3K1JHKJBAHBKAJWDB2KJE5ECE3");
    define("SECRET_KEY", "Watshinohimitsunokagida");
    class Connection {
        protected $connectionString = "mysql:host=" . SERVER . ";dbname=" .DBASE. ";charset=utf8";
        protected $options = [
          \PDO::ATTR_ERRMODE =>\PDO::ERRMODE_EXCEPTION,  
          \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
          \PDO::ATTR_EMULATE_PREPARES => false
        ];

        public function connect(){
          return new \PDO($this->connectionString, USER, PWORD, $this->options);
        }
    }
?>