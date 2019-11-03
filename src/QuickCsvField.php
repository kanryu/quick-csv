<?php

namespace Kanryu\QuickCsv;

/** When setting fieldSchema to CSV column definition in QuickCsvImporter,
 *  you can provide an array of instances of this class instead of a double array.
 */
class QuickCsvField
{
    /** The name of the CSV column. (required, partial optional)
     *
     * It becomes the field name of the temporary table.
     * If the whole is given as an associative array,
     * the name property of each column can be given as a key of the outer array.
     * In that case, it can be omitted.
     */
    public $name;

    /** The data type that the field assumes. (required)
     *
     * The temporary table is once imported as VARCHAR and it is determined whether it can be CAST() to the given type.
     *
     * - Available: *varchar, alphanumeric, datetime, date, decimal(n), decimal(n,m)*
     * - Note: *int, tinyint* fields as *decimal(n)*
     * - If varchar, do nothing. For other types, some confirmation is made.
     * - 'alphanumeric' is `REGEXP '^[a-zA-Z0-9\-]+$'`. e.g. `'abcde123'`, `'123-456'`
     * - Errors: *XXX_alphanumeric, XXX_notdatetime, XXX_notinteger*
     */
    public $type;

    /** Determine the length of the string entered in the field. (required)
     *
     * - Each field of the temporary table is defined as a column of maxlength + 1 characters.
     * - Errors: *XXX_maxlength*
     */
    public $maxlength;

    /** Define the field schema of the temporary table by manual. (optional)
     *
     * - However, since the DEFAULT option of 'CREATE TABLE' has no effect during CSV import, specify the **default** key.
     * - Without **field** key, auto generated as `{$name} VARCHAR({$maxlength+1}) DEFAULT {$default}`
     */
    public $field;

    /** The CSV column should be required input. (optional)
     *
     * - If true, and an empty value cause an error.
     * - Errors: *XXX_required*
     */
    public $required;

    /** Change the initial value actually entered in the temporary table if the value of the CSV column is empty. (optional)
     *
     * - Since it is inserted with SQL as it is, it is necessary to write "'abc'" when giving a character string.
     * - e.g. `'0'`, `'NULL'`, `"'abc'"`, `'NOW()'` 
     */
    public $default;

    /** Describe formula to validate the field value directly. (optional)
     *
     * - Give an SQL expression so that the canonical value returns TRUE.
     * - You can put all fields you set and `id` field(as CSV row number) on the expression.
     * - Errors: *XXX_custom*
     * - e.g. `deleteFlag BETWEEN '0' AND '1'`
     */
    public $custom;





}





