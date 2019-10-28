<?php

namespace Kanryu\QuickCsv;

/** Module to import CSV very fast
 *
 * - Import your CSV data directly into a new/existing temporary table with an RDB-specific API.
 * - Validation of input values and correlation record check are confirmed by executing SQL.
 * - To move data from the temporary table to the final table, execute a normal INSERT / UPDATE queries.
 */
class QuickCsvImporter
{
    /** This property is set when using PDO. Call setPdo(). */
    public $pdo = null;
    
    /** Set this property when you want to define query execution independently.
     *  Automatically updated when using PDO. */
    public $query_callback = null;
    
    /** Give the schema defining each column of the imported CSV as an associative array. */
    public $fieldSchema = array();
    
    /** associative array of $fieldSchema */
    public $fieldMap = array();
    
    /** Temporary table name to specify as the CSV direct import destination. */
    public $dataTableName = 'tempCsvData';
    
    /** Target table name where data is finally entered. */
    public $targetTableName = 'TARGET_TABLE';
    
    /** Primary key of the target table where data is finally entered. */
    public $targetPrimaryKey = 'PRIMARY_KEY';
    
    public $csvSeparator = "','";
    public $csvEncloser = "'\"'";
    public $csvLineStart = "''";
    public $csvLineSep = "'\\r\\n'";
    
    /** AUTO_INCREMENT field for returning row number on CSV */
    public $csvRecordId = 'id';
    
    /** If true, the first line of CSV is ignored when importing. */
    public $hasCsvHeader = true;
    
    /** If true, the CSV import table is TEMPORARY TABLE.
     *  If false, the table remains after the import is complete.
     *  Although there is no practical meaning, the import result remains.
     */
    public $asTemporary = true;
    
    /** If true, dump sqls. */
    public $dumpSql = false;
    
    /** Character code of temporary table */
    public $tableCharCode = 'utf8';
    
    /** CSV file character code */
    public $csvCharCode = 'cp932';
    
    /** Several properties can be specified here, but no arguments are required. 
     *
     * @param array $options The name of the table for which data is finally imported
     */
    public function __construct($options = array())
    {
        $this->query_callback = function ($sql, $params, $api) {
            throw new Exception('must be implemented');
        };
        $this->setProperties($options);
    }
    
    /** Set properties in batch */
    public function setProperties($options = array())
    {
        foreach ($options as $p => $v) {
            if ($p == 'fieldSchema') {
                $this->setFieldSchema($v);
            } elseif ($p == 'pdo') {
                $this->setPdo($v);
            } else {
                $this->$p = $v;
            }
        }
    }
    
    /** Specify associative array the relationship between each column of CSV
     *  to be imported and the column of import destination table.
     */
    public function setFieldSchema($schema)
    {
        $this->fieldSchema = $schema;
        $this->fieldMap = array();
        foreach ($schema as $i => $v) {
            $name = array_key_exists('name', $v) ? $v['name'] : $i;
            $this->fieldMap[$name] = $v;
        }
    }
    
    /** Set up an instance of PDO.
     *
     * For MySQL, you need to initialize it with PDO::MYSQL_ATTR_LOCAL_INFILE.
     */
    public function setPdo($pdo)
    {
        $this->pdo = $pdo;
        $that = $this;
        $this->query_callback = function ($sql, $params, $api) use($that) {
            if ($that->dumpSql) {
                echo "----------- {$api}:\n";
                echo "$sql\n";
            }
            $stmt = $this->pdo->prepare($sql);
            $result = $stmt->execute($params);
            switch($api) {
                case 'validateAllFields':
                    return $stmt->fetchAll(\PDO::FETCH_ASSOC);
                    break;
                default:
                    return $result;
            }
        };
    }
    
    /** Customize how queries are issued to the database.
     *
     * If PDO is used directly, setPdo() can be used.
     */
    public function setQueryCallback($query_callback)
    {
        $this->query_callback = $query_callback;
    }
    
    /** Run the query to the database with query_callback().
     */
    public function execQuery($sql, $params, $api)
    {
        return call_user_func_array($this->query_callback, func_get_args());
    }
    
    /**
     * Create a temporary table
     *
     * @param string $dataTableName The name of the temporary table
     * @param array $schema Give the schema defining each column of the imported CSV as an associative array.
     */
    public function create($dataTableName = null, $schema = null)
    {
        if (!empty($dataTableName)) {
            $this->dataTableName = $dataTableName;
        }
        if (!empty($fields)) {
            $this->setFieldSchema($fields);
        }
        if (empty($this->dataTableName)) {
            throw new \Exception("[QuickCsv]: You must define the table to import into!");
        }
        if (empty($this->fieldSchema)) {
            throw new \Exception("[QuickCsv]: You must define the CSV schema!");
        }
        $this->execQuery("DROP TABLE IF EXISTS {$this->dataTableName}", null, 'create:drop');
        
        // expand fieldSchema [*] ['field'] as each field schema of the table
        $schemas = array();
        foreach ($this->fieldMap as $f => $v) {
            $length = $v['maxlength'] + 1;
            $type = $length > 255 ? 'TEXT' : "VARCHAR({$length})";
            $schema_default = 'DEFAULT ' . (array_key_exists('default', $v) ? $v['default'] : "''");
            $schemas[] = array_key_exists('field', $v) ? $v['field'] : "`{$f}` {$type} {$schema_default}";
        }
        $field_lines = implode(",\n                ", $schemas);
        
        $temporary = $this->asTemporary ? 'TEMPORARY' : '';
        $sql  = "
            CREATE {$temporary} TABLE {$this->dataTableName}
            (
                `{$this->csvRecordId}`      INT(9) NOT NULL AUTO_INCREMENT,
                {$field_lines},
                PRIMARY KEY (`{$this->csvRecordId}`)
            ) ENGINE=MyISAM DEFAULT CHARSET={$this->tableCharCode};
        ";
        return $this->execQuery($sql, null, 'create');
    }

    /**
     * Specify CSV file and import data into the temporary table
     *
     * @param string $path CSV file full path
     * @param array $schema Give the schema defining each column of the imported CSV as an associative array.
     */
    public function import($path, $dataTableName = null, $schema = null) {
        if (!empty($dataTableName)) {
            $this->dataTableName = $dataTableName;
        }
        if (!empty($schema)) {
            $this->setFieldSchema($schema);
        }
        if (empty($this->dataTableName)) {
            throw new \Exception("[QuickCsv]: You must define the table to import into!");
        }
        if (empty($this->fieldSchema)) {
            throw new \Exception("[QuickCsv]: You must define the CSV schema!");
        }
        $names = array();
        $setters = array();
        foreach($this->fieldMap as $field => $v) {
            if(!array_key_exists('default', $v)) {
                $names[] = $field;
                continue;
            }
            // If the 'default' property exists, specify the initial value
            // when the CSV column is empty with that value.
            $names[] = "@var_{$field}";
            $setters[] = "{$field} = CASE @var_{$field} WHEN '' THEN {$v['default']} ELSE @var_{$field} END";
        }
        $settter_lines = empty($setters) ? '' : 'SET ' . implode(",\n                ", $setters);
        $ignoreLine = $this->hasCsvHeader ? 'IGNORE 1 LINES' : '';

        $field_lines = implode(",", $names);
        $sql  = <<<SQL
            LOAD DATA LOCAL INFILE "{$path}"
            INTO TABLE {$this->dataTableName}
            CHARACTER SET {$this->csvCharCode}
            FIELDS TERMINATED BY {$this->csvSeparator}
            OPTIONALLY ENCLOSED BY {$this->csvEncloser}
            LINES TERMINATED BY {$this->csvLineSep} STARTING BY {$this->csvLineStart}
            {$ignoreLine}
            ({$field_lines})
            {$settter_lines}
SQL;

        return $this->execQuery($sql, null, 'import');
    }

    /**
     * Validate all records all fields at once
     * @return array Validation error lines. empty if there is no error,
     */
    public function validateAllFields()
    {
        if (empty($this->fieldSchema)) {
            throw new \Exception("[QuickCsv]: You must define the CSV schema!");
        }
        $fields = array();
        foreach($this->fieldMap as $field => $v) {
            if(array_key_exists('maxlength', $v)) {
                $fields["{$field}_maxlength"] = "CHAR_LENGTH({$field}) > {$v['maxlength']}";
            }
            if(array_key_exists('required', $v) && $v['required']) {
                $fields["{$field}_required"] = "{$field} = ''";
            }
            if(array_key_exists('custom', $v)) {
                // Embed the expression specified in the custom property in the query
                if(array_key_exists('required', $v)) {
                    $fields["{$field}_custom"] = "NOT {$v['custom']}";
                } else {
                    $fields["{$field}_custom"] = "{$field} != '' AND NOT ({$v['custom']})";
                }
            }
            if(!array_key_exists('type', $v)) {
                continue;
            }
            $empty_check = $this->getNotDefaultFormula($field);
            switch($v['type']) {
                case 'varchar':
                case 'text':
                    break;
                case 'datetime':case 'date':
                    // If it is a valid date, DAYOFYEAR returns a number. Otherwise, NULL
                    $fields["{$field}_notdatetime"] = "{$empty_check} AND {$field} != '' AND DAYOFYEAR({$field}) = 0";
                    break;
                case 'alphanumeric':
                    // True if there are non-alphanumeric characters
                    $fields["{$field}_alphanumeric"] = "{$empty_check} AND {$field} != '' AND NOT {$field} REGEXP '^[a-zA-Z0-9\-]+$'";
                    break;
                default: // as DECIMAL
                    // Character strings that cannot be converted to numbers are "(field=0)=1".
                    $fields["{$field}_notinteger"] = "({$empty_check} AND {$field} = 0 AND {$field} != '0') OR CAST({$field} AS {$v['type']}) != {$field}";
                    break;
            }
        }
        return $this->validateBase('validateAllFields', $fields);
    }
    
    /** Execute the validation expression specified in the parameter and fetch the row that is true.
     *
     * @param array $fields An associative array with the validation field name as key and the validation expression as value.
     * @param string $additional_tables Query string to insert when joining other tables.
     * @return array Validation error lines. empty if there is no error,
     */
    public function validateBase($api, $fields, $additional_tables='')
    {
        $field_formula = array();
        $conditions = array();
        foreach ($fields as $f => $v) {
            $flds[] = "{$v} AS {$f}";
            $field_formula[] = $v;
        }
        $field_lines = implode(",\n                ", $flds);
        $condition_lines = implode("\n                OR ", $field_formula);
        $sql  = "
            SELECT
                {$this->csvRecordId}, 
                {$field_lines}
            FROM
                {$this->dataTableName}
            WHERE
                {$condition_lines}
            {$additional_tables}
            ORDER BY {$this->csvRecordId}
        ";
        return $this->execQuery($sql, null, $api);
    }
    
    /**
     * Returns records where the specified field does not meet foreign key constraints
     *
     * If the specified field is the primary key of an external table, the key must exist in the external table.
     *
     * @param $field string CSV field name
     * @param $foreignKey string Foreign key name (field name on the target table)
     * @param $tablename string Target table name with foreign key
     * @param $condition string Expression that searches the foreign table
     * @return array error lines
     */
    public function validateNonExistForeignKey($field, $foreignKey, $foreignTableName, $condition = '')
    {
        $sql = "
            SELECT
                {$this->csvRecordId}, {$field}
            FROM
                {$this->dataTableName}
            WHERE
                {$field} NOT IN
                (
                    SELECT
                        {$foreignKey}
                    FROM
                        {$foreignTableName}
                    WHERE
                        1 = 1
                        AND {$condition}
                )
            ORDER BY {$this->csvRecordId}
        ";
        return $this->execQuery($sql, null, 'validateNonExistForeignKey');
    }

    /**
     * Detect records with duplicate specified fields in CSV
     *
     * @param string|array $field Field to check
     * @return array error lines
     */
    public function validateDuplicatedId($field)
    {
        if (!is_array($field)) {
            $field = array($field);
        }
        $field_not_defaults = array();
        $t1_fields = array();
        $t1t2_on = array();
        foreach ($field as $f) {
            $field_not_defaults[] = $this->getNotDefaultFormula($f);
            $t1_fields[] = "t1.{$f}";
            $t1t2_on[] = "t1.{$f} = t2.{$f}";
        }
        $condition_field_not_defaults = implode(" AND\n                ", $field_not_defaults);
        $t1_fields_line = implode(", ", $t1_fields);
        $fields_line = implode(", ", $field);
        $t1t2_on_line = implode(" AND ", $t1t2_on);
        
        $sql = "
            SELECT t1.{$this->csvRecordId}, {$t1_fields_line}
            FROM {$this->dataTableName} t1
            INNER JOIN
            (
                SELECT {$fields_line}, COUNT(*) as ___count
                FROM {$this->dataTableName}
                WHERE {$condition_field_not_defaults}
                GROUP BY {$fields_line}
                ORDER BY NULL
            ) t2
            ON
                {$t1t2_on_line}
            WHERE t2.___count > 1
            ORDER BY t1.{$this->csvRecordId}
        ";
        return $this->execQuery($sql, null, 'validateDuplicatedId');
    }
    
    /** Returns a judgment expression that the value of the specified field is not the default value. */
    public function getNotDefaultFormula($field)
    {
        if (empty($this->fieldSchema)) {
            throw new \Exception("[QuickCsv]: You must define the CSV schema!");
        }
        if (!array_key_exists($field, $this->fieldMap)) {
            throw new \Exception("[QuickCsv]: field must exist in the fieldSchema!");
        }
        $v = $this->fieldMap[$field];
        if(array_key_exists('required', $v)) {
            $empty_check = "1=1";
        } elseif (array_key_exists('default', $v) && $v['default'] == 'NULL' ) {
            // True only if not the default value
            $empty_check = "{$field} IS NOT NULL";
        } elseif (array_key_exists('default', $v)) {
            // True only if not the default value
            $empty_check = "{$field} != {$v['default']}";
        } else {
            // True only if not empty
            $empty_check = "{$field} != ''";
        }
        return $empty_check;
    }

    /** Updates to the line where the numeric field of the specified required item is not entered with the automatically incremented value.
     *
     * Numbers are generated so that they do not overlap with existing records in the target table.
     * @param string $field Field name to update
     * @param int $baseNumber Minimum value to auto increment
     */
    public function updateFieldNumberByAutoCount($field, $baseNumber=0, $targetTableName=null)
    {
        if (!empty($targetTableName)) {
            $this->targetTableName = $targetTableName;
        }
        $sql = "
            UPDATE {$this->dataTableName} t10,
            (
                SELECT t2.{$this->csvRecordId}, t2.{$this->csvRecordId} + t1.{$field} AS {$field}
                FROM {$this->dataTableName} t2,
                (
                    SELECT MAX({$field}) AS {$field}
                    FROM (
                        SELECT {$baseNumber} AS {$field}
                        UNION
                        SELECT MAX(CAST(productId AS UNSIGNED)) AS {$field}
                        FROM {$this->dataTableName}
                        UNION
                        SELECT MAX(productId) AS {$field}
                        FROM {$this->targetTableName}
                    ) t0
                ) t1
            ) t20
            SET 
                t10.{$field} = t20.{$field}
            WHERE
                t10.{$field} = 0
            AND t10.{$this->csvRecordId} = t20.{$this->csvRecordId}
        ";
        return $this->execQuery($sql, null, 'updateFieldNumberByAutoCount');
    }

    /** Updates to the line where the numeric field with prefix of the specified required item is not entered with the automatically incremented value.
     *
     * Numbers are generated so that they do not overlap with existing records in the target table.
     * @param string $field Field name to update
     * @param string $prefix Prefix contained in field value
     * @param string $body The number of digits to interpolate with 0. If '0000', the value 12 must be '0012'.
     * @param int $baseNumber Minimum value to auto increment
     */
    public function updateFieldNumberByAutoCountWithPrefix($field, $prefix, $body='#', $baseNumber=0, $targetTableName=null)
    {
        if (!empty($targetTableName)) {
            $this->targetTableName = $targetTableName;
        }
        if(empty($body) || $body == '#') { // Concatenate 0 left justified
            // productCode == $prefix . $codeNum
            $newValue = "CONCAT({$prefix}, t2.{$this->csvRecordId} + IFNULL(t1.{$field}, 0))";
        } else { // Interpolate 0 to the specified number of digits and concatenate
            // productCode == $prefix . LPAD($codeNum, $bodylen, '0')
            $bodylen = strlen($body);
            $newValue = "CONCAT({$prefix}, LPAD(t2.{$this->csvRecordId} + IFNULL(t1.{$field}, 0), {$bodylen}, '0'))";
        }
        $prefixlen = strlen($prefix);
        $sql = "
            UPDATE {$this->dataTableName} t10,
            (
                SELECT t2.{$this->csvRecordId}, {$newValue} AS {$field}
                FROM {$this->dataTableName} t2,
                (
                    SELECT MAX({$field}) AS {$field}
                    FROM (
                        SELECT {$baseNumber} AS {$field}
                        UNION
                        SELECT MAX(CAST(SUBSTRING(productCode, {$prefixlen}) AS UNSIGNED)) AS {$field}
                        FROM {$this->dataTableName}
                        WHERE
                            CHAR_LENGTH(productCode) > {$prefixlen}
                        UNION
                        SELECT MAX(CAST(SUBSTRING(productCode, {$prefixlen}) AS UNSIGNED)) AS {$field}
                        FROM Product
                        WHERE
                            CHAR_LENGTH(productCode) > {$prefixlen}
                    ) t0
                ) t1
            ) t20
            SET 
                t10.productCode = t20.{$field}
            WHERE
                t10.{$field} = 0
            AND t10.{$this->csvRecordId} = t20.{$this->csvRecordId}
        ";
        return $this->execQuery($sql, null, 'updateFieldNumberByAutoCountWithPrefix');
    }

    /** Overwrite records existing in the target table with CSV
     *
     * @param array $immediates Array for overwriting specified fields with immediates
     */
    public function updateExistingRecords($immediates = array())
    {
        $schemas = array();
        $params = array();
        foreach($this->fieldMap as $f => $v) {
            if (array_key_exists('skip', $v) && $v['skip']) {
                continue;
            }
            if (array_key_exists($f, $immediates)) {
                $schemas[] = "t1.{$f} = :{$f}";
                $params[":{$f}"] = $immediates[$f];
                unset($immediates[$f]);
            } else {
                $schemas[] = "t1.{$f} = t2.{$f}";
            }
        }
        foreach($immediates as $f => $v) {
            $schemas[] = "t1.{$f} = :{$f}";
            $params[":{$f}"] = $v;
        }
        $field_lines = implode(",\n                ", $schemas);
        
        $condition = "t1.{$this->targetPrimaryKey} = t2.{$this->targetPrimaryKey}";
        if (is_array($this->targetPrimaryKey)) {
            $conditions = array();
            foreach($this->targetPrimaryKey as $f) {
                $conditions[] = "t1.{$f} = t2.{$f}";
            }
            $condition = implode(" AND\n                ", $conditions);
        }

        $sql = "
            UPDATE
                {$this->targetTableName} t1,
                {$this->dataTableName} t2
            SET
                {$field_lines}
            WHERE
                {$condition}
        ";
        return $this->execQuery($sql, $params, 'updateExistingRecords');
    }

    /** Add a new record from CSV that does not exist in the target table
     *
     * @param array $immediates Array for overwriting specified fields with immediates
     */
    public function insertNonExistingRecords($immediates = array())
    {
        $names1 = array();
        $names2 = array();
        $schemas = array();
        $params = array();
        foreach($this->fieldMap as $f => $v) {
            if (array_key_exists('skip', $v) && $v['skip']) {
                continue;
            }
            $names1[] = $f;
            if (array_key_exists($f, $immediates)) {
                $names2[] = ":{$f}";
                $params[":{$f}"] = $immediates[$f];
                unset($immediates[$f]);
            } else {
                $names2[] = "t2.{$f}";
            }
        }
        foreach($immediates as $f => $v) {
            $names1[] = $f;
            $names2[] = ":{$f}";
            $params[":{$f}"] = $v;
        }
        $field_lines1 = implode(",", $names1);
        $field_lines2 = implode(",", $names2);
        
        $condition = "t1.{$this->targetPrimaryKey} = t2.{$this->targetPrimaryKey}";
        $condition2 = "t1.{$this->targetPrimaryKey} IS NULL";
        if (is_array($this->targetPrimaryKey)) {
            $conditions = array();
            $conditions2 = array();
            foreach($this->targetPrimaryKey as $f) {
                $conditions[] = "t1.{$f} = t2.{$f}";
                $conditions2[] = "t1.{$f} IS NULL";
            }
            $condition = implode(" AND\n                ", $conditions);
            $condition2 = implode(" AND\n                ", $conditions2);
        }

        $sql = "
            INSERT INTO {$this->targetTableName}
                ({$field_lines1})
            SELECT
                {$field_lines2}
            FROM {$this->dataTableName} t2
            LEFT JOIN {$this->targetTableName} t1
              ON {$condition}
            WHERE
                {$condition2}
            ORDER BY t2.{$this->csvRecordId}
        ";
        return $this->execQuery($sql, $params, 'insertNonExistingRecords');
    }


}





