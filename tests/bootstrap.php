<?php
// database environment to test
define('ENVFILE', dirname(__FILE__).'/env.php');
if (file_exists(ENVFILE)) { require_once ENVFILE; }
if (!defined('DB_HOST')) { define('DB_HOST', '127.0.0.1'); }
if (!defined('DATABASE')) { define('DATABASE', 'testdb'); }
if (!defined('DB_USER')) { define('DB_USER', 'testdb'); }
if (!defined('DB_PASS')) { define('DB_PASS', 'testdb'); }
if (!defined('DB_ENCODING')) { define('DB_ENCODING', 'utf8mb4'); }

$db_host = DB_HOST;
$database = DATABASE;
$db_encoding = DB_ENCODING;
$pdo_dsn = "mysql:host={$db_host};dbname={$database};charset={$db_encoding}";
$pdo_options = array(
    PDO::MYSQL_ATTR_LOCAL_INFILE => true, // needed to call QuickCsvImporter::import()
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
);

