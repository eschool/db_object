<?php

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
