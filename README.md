# QuickCsv
Portable PHP library that allows you to import and export CSV very fast by issuing special queries to RDBs.

## Why is QuickCsv so fast?
Compared with the input value from the web form, the data imported by CSV has many rows.

Each row in the CSV has a number of columns, and you will need to check for different validations. The row may already exist in the database or may not exist yet. You will check each line and the final data will be input by either INSERT/UPDATE. Since there are a lot of rows, you need to write and execute these operations for the number of rows x columns. It has slow results despite being very hard to implement. You will be disappointed.

QuickCsv solves the CSV import process flexibly and quickly. You just give the column information that the CSV data has as an Array, and the library automatically creates the table and put all the rows of the CSV on it. Without implementing the validation process in PHP, the values of all columns of all rows, correlation check between rows, foreign key constraints with foreign tables, etc. can be solved quickly with SQL. All of these are automatically generated and executed by QuickCsv.

## Your service with QuickCsv

By adopting this library, you will be able to provide such services to users.

- Resolving different character codes between CSV data and database
- Accept large amounts of CSV very fast
- Accept any CSV column added, omitted, rearranged (but you need to understand the column order before processing)
- Accept user-defined columns that do not exist as columns in the destination table

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

## CSV Field Schema
You give QuickCsvImporter an CSV column definition as an array. This is actually created as a temporary table. Imported CSV data is validated.

- **name**
  - The name of the csv column. It becomes the field name of the temporary table.
  - If the whole is given as an associative array, the name property of each column can be given as a key of the outer array. In that case, it can be omitted.
- **type**
  - The data type that the field assumes. The temporary table is once imported as VARCHAR and it is determined whether it can be CAST() to the given type.
  - Available: varchar, alphanumeric, datetime, date, decimal(n), decimal(n,m)
  - If varchar, do nothing. For other types, some confirmation is made.
  - 'alphanumeric' is `REGEXP '^[a-zA-Z0-9\-]+$'`. e.g. `'abcde123'`, `'123-456'`
  - Errors: XXX_alphanumeric, XXX_notdatetime, XXX_notinteger
- **maxlength**
  - Determine the length of the string entered in the field.
  - Each field of the temporary table is defined as a column of maxlength + 1 characters.
  - Errors: XXX_maxlength
- **field** (optional)
  - define the field schema of the template table as manual
  - However, since the DEFAULT option of 'CREATE TABLE' has no effect during CSV import, specify the **default** key.
  - default case(without field key), `{$name} VARCHAR({$maxlength+1}) DEFAULT ''`
- **required** (optional)
  - The field is required input, and an empty field cause an error.
  - Errors: XXX_required
- **default** (optional)
  - Change the initial value actually entered in the temporary table if the field is empty.
  - Since it is concatenated with SQL as it is, it is necessary to write "'abc'" when giving a character string.
  - e.g. `'0'`, `'NULL'`, `"'abc'"`, `'NOW()'` 
- **custom** (optional)
  - Describe formula to validate the field value directly. Write an expression so that the canonical value returns TRUE.
  - Errors: XXX_custom
  - e.g. `deleteFlag BETWEEN '0' AND '1'`

Since some validation result field values are sensitive, errors must be recognized in a fixed order.

1. required
2. maxlength
3. type
4. custom


## Reference(API Doc)
https://kanryu.github.io/quick-csv/

## Complete Sample
To see *tests/test.php*

## SQL actually issued by each API
To see *tests/QuickCsvImporterSchemaTest.php*

## License

MIT

## Author

Copyright 2019 KATO Kanryu(k.kanryu@gmail.com)
