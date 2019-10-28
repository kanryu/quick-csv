<?php
require_once dirname(__FILE__).'/bootstrap.php';
require_once dirname(__FILE__).'/../src/QuickCsvImporter.php';
require_once dirname(__FILE__).'/../src/Mysql/QuickCsvImporter.php';
use Kanryu\QuickCsv\MySQL\QuickCsvImporter;

$table_field_tmpl = array(
	array('name' => 'productId',   'type' => 'decimal(15)',   'maxlength' => 15,   ), 
	array('name' => 'categoryId',  'type' => 'decimal(9)',    'maxlength' => 9,    'required' => true),
	array('name' => 'productCode', 'type' => 'varchar',       'maxlength' => 20,   ),
	array('name' => 'productName', 'type' => 'varchar',       'maxlength' => 40,   'required' => true),
	array('name' => 'price',       'type' => 'decimal(8,2)',  'maxlength' => 8,    'required' => true),
	array('name' => 'cost',        'type' => 'decimal(14,5)', 'maxlength' => 14,   'default' => "NULL"),
	array('name' => 'deleteFlag',  'type' => 'decimal(1)',    'maxlength' => 1,    'default' => "'0'", 'custom' => "deleteFlag BETWEEN '0' AND '1'"),
);

try {
    $pdo = new PDO($pdo_dsn, DB_USER, DB_PASS, $pdo_options);
    echo "Session Successed\n";
    
    $qcsv = new QuickCsvImporter(array(
        'targetTableName' => 'Product', 
        'targetPrimaryKey' => 'productId', 
        'fieldSchema' => $table_field_tmpl,
        'asTemporary' => false,
        'dumpSql' => true,
    ));
    $qcsv->setPdo($pdo);
//    $qcsv->setQueryCallback(function ($sql, $params, $api) {
//        echo "----------- {$api}:\n";
//        echo "$sql\n";
//    });
    $qcsv->create();
//    $qcsv->import('./validationerror.csv');
    $qcsv->import('./ok.csv');
    $errors = $qcsv->validateAllFields();
    $qcsv->validateDuplicatedId('productId');
    $qcsv->validateDuplicatedId(['productId', 'productCode']);
    $qcsv->validateNonExistForeignKey('categoryId', 'categoryId', 'Category', 'deleteFlag = 0');
    $qcsv->updateFieldNumberByAutoCount('productId', 50000, 'Product');
//    $qcsv->updateFieldNumberByAutoCountWithPrefix('productCode', 'apn', '#', 50000, 'Product');
//    $qcsv->updateFieldNumberByAutoCountWithPrefix('productCode', 'apn', '00000', 50000, 'Product');
    $qcsv->updateExistingRecords(array('updated_at' => '2019-10-28 10:10:10'));
    $qcsv->insertNonExistingRecords(array('created_at' => '2019-10-28 10:10:10', 'updated_at' => '2019-10-28 10:10:10'));
    
} catch (PDOException $e) {
    echo "Session Failed: " . $e->getMessage() . "\n";
    exit();
}
