<?php

require_once dirname(__FILE__).'/../src/QuickCsvImporter.php';
require_once dirname(__FILE__).'/../src/Mysql/QuickCsvImporter.php';
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Constraint\IsEqual;
use Kanryu\QuickCsv\MySQL\QuickCsvImporter;

class QuickCsvImporterSchemaTest extends TestCase
{
    public $table_field_tmpl = array(
    	array('name' => 'productId',   'type' => 'decimal(15)',   'maxlength' => 15,   ), 
    	array('name' => 'categoryId',  'type' => 'decimal(9)',    'maxlength' => 9,    'required' => true),
    	array('name' => 'productCode', 'type' => 'alphanumeric',  'maxlength' => 20,   ),
    	array('name' => 'productName', 'type' => 'varchar',       'maxlength' => 40,   'required' => true),
    	array('name' => 'price',       'type' => 'decimal(8,2)',  'maxlength' => 8,    'required' => true),
    	array('name' => 'cost',        'type' => 'decimal(14,5)', 'maxlength' => 14,   'default' => "NULL"),
    	array('name' => 'deleteFlag',  'type' => 'decimal(1)',    'maxlength' => 1,    'default' => "'0'", 'custom' => "deleteFlag BETWEEN '0' AND '1'"),
    );
    public $importer = null;
    public $sqls = array();
    public $params = array();
    /**
     * Asserts that two sql strings are equal.
     *
     * @throws ExpectationFailedException
     * @throws \SebastianBergmann\RecursionContext\InvalidArgumentException
     */
    public static function assertSqlEquals($expected, $actual, string $message = '', float $delta = 0.0, int $maxDepth = 10, bool $canonicalize = false, bool $ignoreCase = false): void
    {
        $expected = trim(preg_replace('/[\s\r\n]+/', ' ', $expected));
        $actual = trim(preg_replace('/[\s\r\n]+/', ' ', $actual));
        $constraint = new IsEqual(
            $expected,
            $delta,
            $maxDepth,
            $canonicalize,
            $ignoreCase
        );

        static::assertThat($actual, $constraint, $message);
    }

    public function setUp()
    {
        $this->importer = $qcsv = new QuickCsvImporter(array(
            'destTableName' => 'Product', 
            'destPrimaryKey' => 'productId', 
            'fieldSchema' => $this->table_field_tmpl,
            'asTemporary' => false,
        ));
        $tester = $this;
        $this->sqls = array();
        $this->params = array();
        $qcsv->setQueryCallback(function ($sql, $params, $api) use($tester) {
            $tester->sqls[] = $sql;
            $tester->params[] = $params;
        });
    }
    
    public function test_create()
    {
        $this->importer->create();
        $this->assertSqlEquals("
            CREATE  TABLE tempCsvData
            (
                `id`      INT(9) NOT NULL AUTO_INCREMENT,
                `productId` VARCHAR(16) DEFAULT '',
                `categoryId` VARCHAR(10) DEFAULT '',
                `productCode` VARCHAR(21) DEFAULT '',
                `productName` VARCHAR(41) DEFAULT '',
                `price` VARCHAR(9) DEFAULT '',
                `cost` VARCHAR(15) DEFAULT NULL,
                `deleteFlag` VARCHAR(2) DEFAULT '0',
                PRIMARY KEY (`id`)
            ) ENGINE=MyISAM DEFAULT CHARSET=utf8;
        ",$this->sqls[1]);
    }
    
    public function test_import()
    {
        $this->importer->import('./test.csv');
        $sql = <<< SQL
            LOAD DATA LOCAL INFILE "./test.csv"
            INTO TABLE tempCsvData
            CHARACTER SET cp932
            FIELDS TERMINATED BY ','
            OPTIONALLY ENCLOSED BY '"'
            LINES TERMINATED BY '\\r\\n' STARTING BY ''
            IGNORE 1 LINES
            (productId,categoryId,productCode,productName,price,@var_cost,@var_deleteFlag)
            SET cost = CASE @var_cost WHEN '' THEN NULL ELSE @var_cost END,
                deleteFlag = CASE @var_deleteFlag WHEN '' THEN '0' ELSE @var_deleteFlag END
SQL;
        $this->assertSqlEquals($sql, $this->sqls[0]);
    }
    
    public function test_validateAllFields()
    {
        $this->importer->validateAllFields();
        $this->assertSqlEquals("
            SELECT
                id,
                CHAR_LENGTH(productId) > 15 AS productId_maxlength,
                (productId != '' AND productId = 0 AND productId != '0') OR CAST(productId AS decimal(15)) != productId AS productId_notinteger,
                CHAR_LENGTH(categoryId) > 9 AS categoryId_maxlength,
                categoryId = '' AS categoryId_required,
                (1=1 AND categoryId = 0 AND categoryId != '0') OR CAST(categoryId AS decimal(9)) != categoryId AS categoryId_notinteger,
                CHAR_LENGTH(productCode) > 20 AS productCode_maxlength,
                (productCode != '' AND productCode != '' AND NOT productCode REGEXP '^[a-zA-Z0-9\-]+$') AS productCode_notalphanumeric,
                CHAR_LENGTH(productName) > 40 AS productName_maxlength,
                productName = '' AS productName_required,
                CHAR_LENGTH(price) > 8 AS price_maxlength,
                price = '' AS price_required,
                (1=1 AND price = 0 AND price != '0') OR CAST(price AS decimal(8,2)) != price AS price_notinteger,
                CHAR_LENGTH(cost) > 14 AS cost_maxlength,
                (cost IS NOT NULL AND cost = 0 AND cost != '0') OR CAST(cost AS decimal(14,5)) != cost AS cost_notinteger,
                CHAR_LENGTH(deleteFlag) > 1 AS deleteFlag_maxlength,
                (deleteFlag != '' AND NOT (deleteFlag BETWEEN '0' AND '1')) AS deleteFlag_custom,
                (deleteFlag != '0' AND deleteFlag = 0 AND deleteFlag != '0') OR CAST(deleteFlag AS decimal(1)) != deleteFlag AS deleteFlag_notinteger
            FROM
                tempCsvData
            WHERE
                CHAR_LENGTH(productId) > 15
                OR (productId != '' AND productId = 0 AND productId != '0') OR CAST(productId AS decimal(15)) != productId
                OR CHAR_LENGTH(categoryId) > 9
                OR categoryId = ''
                OR (1=1 AND categoryId = 0 AND categoryId != '0') OR CAST(categoryId AS decimal(9)) != categoryId
                OR CHAR_LENGTH(productCode) > 20
                OR (productCode != '' AND productCode != '' AND NOT productCode REGEXP '^[a-zA-Z0-9\-]+$')
                OR CHAR_LENGTH(productName) > 40
                OR productName = ''
                OR CHAR_LENGTH(price) > 8
                OR price = ''
                OR (1=1 AND price = 0 AND price != '0') OR CAST(price AS decimal(8,2)) != price
                OR CHAR_LENGTH(cost) > 14
                OR (cost IS NOT NULL AND cost = 0 AND cost != '0') OR CAST(cost AS decimal(14,5)) != cost
                OR CHAR_LENGTH(deleteFlag) > 1
                OR (deleteFlag != '' AND NOT (deleteFlag BETWEEN '0' AND '1'))
                OR (deleteFlag != '0' AND deleteFlag = 0 AND deleteFlag != '0') OR CAST(deleteFlag AS decimal(1)) != deleteFlag

            ORDER BY id
        ",$this->sqls[0]);
    }
    
    public function test_validateDuplicatedId_single()
    {
        $this->importer->validateDuplicatedId('productId');
        $this->assertSqlEquals("
            SELECT t1.id, t1.productId
            FROM tempCsvData t1
            INNER JOIN
            (
                SELECT productId, COUNT(*) as ___count
                FROM tempCsvData
                WHERE productId != ''
                GROUP BY productId
                ORDER BY NULL
            ) t2
            ON
                t1.productId = t2.productId
            WHERE t2.___count > 1
            ORDER BY t1.id
        ",$this->sqls[0]);
    }
    
    public function test_validateDuplicatedId_complex()
    {
        $this->importer->validateDuplicatedId(['productId', 'productCode']);
        $this->assertSqlEquals("
            SELECT t1.id, t1.productId, t1.productCode
            FROM tempCsvData t1
            INNER JOIN
            (
                SELECT productId, productCode, COUNT(*) as ___count
                FROM tempCsvData
                WHERE productId != '' AND
                productCode != ''
                GROUP BY productId, productCode
                ORDER BY NULL
            ) t2
            ON
                t1.productId = t2.productId AND t1.productCode = t2.productCode
            WHERE t2.___count > 1
            ORDER BY t1.id
        ",$this->sqls[0]);
    }
    
    public function test_validateNonExistForeignKey()
    {
        $this->importer->validateNonExistForeignKey('categoryId', 'categoryId', 'Category', 'deleteFlag = 0');
        $this->assertSqlEquals("
            SELECT
                id, categoryId
            FROM
                tempCsvData
            WHERE
                categoryId NOT IN
                (
                    SELECT
                        categoryId
                    FROM
                        Category
                    WHERE
                        1 = 1
                        AND deleteFlag = 0
                )
            ORDER BY id
        ",$this->sqls[0]);
    }
    
    public function test_updateFieldNumberByAutoCount()
    {
        $this->importer->updateFieldNumberByAutoCount('productId', 50000, 'Product');
        $this->assertSqlEquals("
            UPDATE tempCsvData t10,
            (
                SELECT t2.id, t2.id + t1.productId AS productId
                FROM tempCsvData t2,
                (
                    SELECT MAX(productId) AS productId
                    FROM (
                        SELECT 50000 AS productId
                        UNION
                        SELECT MAX(CAST(productId AS UNSIGNED)) AS productId
                        FROM tempCsvData
                        UNION
                        SELECT MAX(productId) AS productId
                        FROM Product
                    ) t0
                ) t1
            ) t20
            SET
                t10.productId = t20.productId
            WHERE
                t10.productId = 0
            AND t10.id = t20.id
        ",$this->sqls[0]);
    }
    
    public function test_updateFieldNumberByAutoCountWithPrefix_left()
    {
        $this->importer->updateFieldNumberByAutoCountWithPrefix('productCode', 'apn', '#', 50000, 'Product');
        $this->assertSqlEquals("
            UPDATE tempCsvData t10,
            (
                SELECT t2.id, CONCAT(apn, t2.id + IFNULL(t1.productCode, 0)) AS productCode
                FROM tempCsvData t2,
                (
                    SELECT MAX(productCode) AS productCode
                    FROM (
                        SELECT 50000 AS productCode
                        UNION
                        SELECT MAX(CAST(SUBSTRING(productCode, 3) AS UNSIGNED)) AS productCode
                        FROM tempCsvData
                        WHERE
                            CHAR_LENGTH(productCode) > 3
                        UNION
                        SELECT MAX(CAST(SUBSTRING(productCode, 3) AS UNSIGNED)) AS productCode
                        FROM Product
                        WHERE
                            CHAR_LENGTH(productCode) > 3
                    ) t0
                ) t1
            ) t20
            SET
                t10.productCode = t20.productCode
            WHERE
                t10.productCode = 0
            AND t10.id = t20.id
        ",$this->sqls[0]);
    }
    
    public function test_updateFieldNumberByAutoCountWithPrefix_right()
    {
        $this->importer->updateFieldNumberByAutoCountWithPrefix('productCode', 'apn', '00000', 50000, 'Product');
        $this->assertSqlEquals("
            UPDATE tempCsvData t10,
            (
                SELECT t2.id, CONCAT(apn, LPAD(t2.id + IFNULL(t1.productCode, 0), 5, '0')) AS productCode
                FROM tempCsvData t2,
                (
                    SELECT MAX(productCode) AS productCode
                    FROM (
                        SELECT 50000 AS productCode
                        UNION
                        SELECT MAX(CAST(SUBSTRING(productCode, 3) AS UNSIGNED)) AS productCode
                        FROM tempCsvData
                        WHERE
                            CHAR_LENGTH(productCode) > 3
                        UNION
                        SELECT MAX(CAST(SUBSTRING(productCode, 3) AS UNSIGNED)) AS productCode
                        FROM Product
                        WHERE
                            CHAR_LENGTH(productCode) > 3
                    ) t0
                ) t1
            ) t20
            SET
                t10.productCode = t20.productCode
            WHERE
                t10.productCode = 0
            AND t10.id = t20.id
        ",$this->sqls[0]);
    }
    
    public function test_updateExistingRecords()
    {
        $this->importer->updateExistingRecords(array('updated_at' => '2019-10-28 10:10:10'));
        $this->assertSqlEquals("
            UPDATE
                Product t1,
                tempCsvData t2
            SET
                t1.productId = t2.productId,
                t1.categoryId = t2.categoryId,
                t1.productCode = t2.productCode,
                t1.productName = t2.productName,
                t1.price = t2.price,
                t1.cost = t2.cost,
                t1.deleteFlag = t2.deleteFlag,
                t1.updated_at = :updated_at
            WHERE
                t1.productId = t2.productId
        ",$this->sqls[0]);
    }
    
    public function test_insertNonExistingRecords()
    {
        $this->importer->insertNonExistingRecords(array('created_at' => '2019-10-28 10:10:10', 'updated_at' => '2019-10-28 10:10:10'));
        $this->assertSqlEquals("
            INSERT INTO Product
                (productId,categoryId,productCode,productName,price,cost,deleteFlag,created_at,updated_at)
            SELECT
                t2.productId,t2.categoryId,t2.productCode,t2.productName,t2.price,t2.cost,t2.deleteFlag,:created_at,:updated_at
            FROM tempCsvData t2
            LEFT JOIN Product t1
              ON t1.productId = t2.productId
            WHERE
                t1.productId IS NULL
            ORDER BY t2.id
        ",$this->sqls[0]);
    }
}

