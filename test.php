<?php

require_once './src/QuickCsv.php';
use QuickCsv\QuickCsv;

$dsn = 'mysql:dbname=testdb;host=192.168.10.112';
$user = 'testdb';
$password = 'testdb';

$table_field_tmpl = array(
        'productId'                   => array('no' => 1,  'type' => 'decimal(15)',   'maxlength' => 15,   ), 
        'categoryId'                  => array('no' => 2,  'type' => 'decimal(9)',    'maxlength' => 9,    'required' => true),
        'productCode'                 => array('no' => 3,  'type' => 'varchar',       'maxlength' => 20,   ),
        'productName'                 => array('no' => 4,  'type' => 'varchar',       'maxlength' => 85,   'required' => true),
        'price'                       => array('no' => 6,  'type' => 'decimal(8)',    'maxlength' => 8,    'required' => true),
        'cost'                        => array('no' => 7,  'type' => 'decimal(14,5)', 'maxlength' => 14,   'default' => "NULL"),
        'displayFlag'                 => array('no' => 17, 'type' => 'decimal(1)',    'maxlength' => 1,    'default' => "'0'", 'custom' => "displayFlag BETWEEN '0' AND '1'"),
);

try {
    //$dbh = new PDO($dsn, $user, $password);
    echo "Session Successed\n";
    
    //$qcsv = new QuickCsv();
    $qcsv = new QuickCsv([
        'targetTableName' => 'Product', 
        'targetPrimaryKey' => 'productId', 
        'fieldSchema' => $table_field_tmpl
    ]);
    $qcsv->setQueryCallback(function ($sql, $params, $api) {
        echo "----------- {$api}:\n";
        echo "$sql\n";
    });
    $qcsv->create();
    $qcsv->import('./test.csv');
    $qcsv->validateAllFields();
    $qcsv->validateDuplicatedId('productId');
    $qcsv->validateDuplicatedId(['productId', 'productCode']);
    $qcsv->validateNonExistForeignKey('productId', 'productId', 'Product', 'deleteFlag = 0');
    $qcsv->updateFieldNumberByAutoCount('productId', 50000, 'Product');
    $qcsv->updateFieldNumberByAutoCountWithPrefix('productCode', 'apn', '#', 50000, 'Product');
    $qcsv->updateFieldNumberByAutoCountWithPrefix('productCode', 'apn', '00000', 50000, 'Product');
    
} catch (PDOException $e) {
    echo "Session Failed: " . $e->getMessage() . "\n";
    exit();
}
