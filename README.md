# QuickCsv
Portable PHP library that allows you to import and export CSV very fast by executing special queries to RDBs.

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
    'destTableName' => 'Product', 
    'destPrimaryKey' => 'productId', 
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

// -- merge to the destination table
$qcsv->updateExistingRecords(); // Overwrite records existing in the destination table with CSV
$qcsv->insertNonExistingRecords(); // Add a new record from CSV that does not exist in the destination table
```

## CSV Field Schema
You give QuickCsvImporter an CSV column definition as an array. This is actually created as a temporary table. Imported CSV data is validated.

- **name**
  - The name of the CSV column. It becomes the field name of the temporary table.
  - If the whole is given as an associative array, the name property of each column can be given as a key of the outer array. In that case, it can be omitted.
- **type**
  - The data type that the field assumes. The temporary table is once imported as VARCHAR and it is determined whether it can be CAST() to the given type.
  - Available: *varchar, alphanumeric, datetime, date, decimal(n), decimal(n,m)*
    - Note: *int, tinyint* fields as *decimal(n)*
  - If varchar, do nothing. For other types, some confirmation is made.
  - 'alphanumeric' is `REGEXP '^[a-zA-Z0-9\-]+$'`. e.g. `'abcde123'`, `'123-456'`
  - You can add a new type by calling setValidatorForType().
  - Errors: *XXX_notalphanumeric, XXX_notdatetime, XXX_notdecimal*
- **maxlength**
  - Determine the length of the string entered in the field.
  - Each field of the temporary table is defined as a column of maxlength + 1 characters.
  - Errors: *XXX_maxlength*
- **field** (optional)
  - Define the field schema of the temporary table by manual.
  - However, since the DEFAULT option of 'CREATE TABLE' has no effect during CSV import, specify the **default** key.
  - Without **field** key, auto generated as `{$name} VARCHAR({$maxlength+1}) DEFAULT {$default}`
- **required** (optional)
  - The CSV column is required input, and an empty value cause an error.
  - Do not specify **default** at the same time. Because **required** checks if the field is **''** .
  - Errors: *XXX_required*
- **default** (optional)
  - Change the initial value actually entered in the temporary table if the value of the CSV column is empty.
  - Since it is inserted with SQL as it is, it is necessary to write "'abc'" when giving a character string.
  - e.g. `'0'`, `'NULL'`, `"'abc'"`, `'NOW()'` 
- **custom** (optional)
  - Describe SQL formula to validate the field value directly. 
  - Give an SQL expression so that the canonical value returns TRUE.
  - You can put all fields you set and `id` field(as CSV row number) on the expression.
  - Errors: *XXX_custom*
  - e.g. `deleteFlag BETWEEN '0' AND '1'`
- **skipped** (optional)
  - If true, it is one of the CSV fields but not the destination table field.
  - Skipped from the transfer fields when updateExistingRecords/insertNonExistingRecords is executed.
  - This can be specified for columns that are not entered in the destination table in some way, such as user-defined columns and comment columns.

Since some validation result of CSV column values are sensitive, errors must be recognized in a fixed order.

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
