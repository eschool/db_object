<?php

require_once 'db_object.php';
require_once 'db_recordset.php';

/**
 * Retrieves a single field value from a row in the table specified that matches
 * the given column constraints. Returns false on no value being found.  In the case that multiple values
 * are matched, returns only the first value from the result set.
 *
 * @param string $table_name
 * @param string $field_name
 * @param array $columns
 * @param bool $debug
 * @return mixed result
 * @author Basil Mohamed Gohar <basil@eschoolconsultants.com>
 * @author Nick Whitt
 * @author John Colvin
 */
function get_single_field_value($table_name, $field_name, $constraints) {
    $recordset = new db_recordset($table_name, $constraints, false, null, null, true);
    if (count($recordset) === 0) {
        return false;
    }
    foreach ($recordset as $record) {
        return $record->$field_name;
    }
}

/**
 * This function takes an array and puts into the following SQL format:
 * ex.: field_name IN ('value1', 'value2', 'value3')
 *
 * By default, the sql string will look return an IN query. To negate, set the $negative
 * flag to TRUE.
 *
 * Paramters:
 *  $data         = An array that contains various values that you need to check
 *                to see if the field is equal to.
 *  $field_name   = The name of the database field to compare the values
 *                in the $data array to.
 * @param bool $negative
 */
function get_sql_in_string( $data, $field_name, $negative=FALSE )
{
    /**
     * WARNING: passing empty $data will cause a string like:
     * student_id IN ('') to be returned.
     */

    if (!is_array($data))
       $data = array($data);

    $in_array = array();
    foreach ($data as $element) {
        $in_array[] = "'" . mysql_real_escape_string($element) . "'";
    }
    $in_string = implode(',', $in_array);

    // Making sure an empty string doesn't cause problems
    if (strlen(trim($in_string)) == 0)
        $in_string = "''";

    if ( $negative === FALSE )
    {
        $sql_string = '' . mysql_real_escape_string($field_name) . ' IN (' . $in_string . ')';
    }
    else
    {
        $sql_string = mysql_real_escape_string( $field_name ) . ' NOT IN (' . $in_string . ')';
    }

    return $sql_string;
}
