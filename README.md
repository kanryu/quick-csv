# QuickCsv
Portable PHP library that allows you to import and export CSV very fast by issuing special queries to RDBs.

## Install

```bash
composer require kanryu/quick-csv
```

## Usage

```php
use Kanryu\QuickCsv\QuickCsvImporter;

// -- (initialize begins)
$table_field_tmpl = array(
	array('name' => 'productId',   'type' => 'decimal(15)',   'maxlength' => 15,   ), 
	array('name' => 'categoryId',  'type' => 'decimal(9)',    'maxlength' => 9,    'required' => true),
	array('name' => 'productCode', 'type' => 'varchar',       'maxlength' => 20,   ),
	array('name' => 'productName', 'type' => 'varchar',       'maxlength' => 40,   'required' => true),
	array('name' => 'price',       'type' => 'decimal(8,2)',  'maxlength' => 8,    'required' => true),
	array('name' => 'cost',        'type' => 'decimal(14,5)', 'maxlength' => 14,   'default' => "NULL"),
	array('name' => 'deleteFlag',  'type' => 'decimal(1)',    'maxlength' => 1,    'default' => "'0'", 'custom' => "deleteFlag BETWEEN '0' AND '1'"),
);
$qcsv = new QuickCsvImporter([
    'targetTableName' => 'Product', 
    'targetPrimaryKey' => 'productId', 
    'fieldSchema' => $table_field_tmpl
]);
$qcsv->setPdo($pdo); // set your pdo or other RDB drivers;

$qcsv->create(); // create temporary table for importing
$qcsv->import('./test.csv'); // import csv to the temporary table
// -- (initialize ends)

// -- (validate begins)
$qcsv->validateAllFields(); // validate all records, all fields
$qcsv->validateDuplicatedId('productId'); // validate duplicated uniqued field

// If the specified field is the primary key of an external table, the key must exist in the external table.
$qcsv->validateNonExistForeignKey('categoryId', 'categoryId', 'Category', 'deleteFlag = 0');
// -- (validate ends)

// -- merge to the target table
$qcsv->updateExistingRecords(); // Overwrite records existing in the target table with CSV
$qcsv->insertNonExistingRecords(); // Add a new record from CSV that does not exist in the target table
```


## License

MIT

## Author

Copyright 2019 KATO Kanryu(k.kanryu@gmail.com)
