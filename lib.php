<?php

require_once 'db_object.php';
require_once 'db_recordset.php';

/**
 * Returns a basic query of the form "SELECT FROM WHERE"
 *
 * Additional options exist for ordering, grouping and limiting within the query.
 *
 * @param array $tables
 * @param array $fields
 * @param array $where_clause
 * @param array $order_by
 * @param array $group_by
 * @param str $limit_by
 * @return str
 * @author Nick Whitt
 */
function get_sql($tables, $fields='*', $where_clause='', $order_by='', $group_by='', $limit_by='') {
    if (! is_array($fields)) {
        $fields = array($fields);
    }

    if (in_array($fields[0], array('', '*'))) {
        $select = 'SELECT * ';
    }
    else {
        $select = '';
        foreach ($fields as $field) {
            if ($select == '') {
                $select = 'SELECT ';
            } else {
                $select .= ', ';
            }

            if (strpos($field, '(')) {
                $select .= mysql_real_escape_string(str_replace(array('(', ')', '.'), array('(`', '`)', '`.`'), $field));
            } else {
                if (strpos($field, '*'))
                {
                    $search = array('.');
                    $replace = array('`.');

                    $select .= '`' . mysql_real_escape_string(str_replace($search, $replace, $field));
                }
                else
                {
                    $search = array('.', ' as ', ' AS ');
                    $replace = array('`.`', '` AS `', '` AS `');

                    $select .= '`' . mysql_real_escape_string(str_replace($search, $replace, $field)) . '`';
                }
            }
        }
    }

    if (! is_array($tables)) {
        $tables = array($tables);
    }

    $from = ' FROM ';
    foreach ($tables as $table) {
        if ($from != ' FROM ') {
            $from .= ', ';
        }

        $from .= '`'.$table.'`';
    }

    $sql = $select."\n".$from;

    $where = '';
    if ($where_clause != '') {

        $where_clauses = $where_clause;
        if (! is_array($where_clauses)) {
            $where_clauses = array($where_clauses);
        }

        if (! isset($where_clauses[0]) || $where_clauses[0] == '') {
            $where = ' WHERE 1=1';
        }
        else {
            $where = ' WHERE ';
            foreach ($where_clauses as $where_clause) {
                $where_clause = trim($where_clause);

                if (strtoupper(substr($where_clause, 0, 6)) == 'WHERE ') {
                    $where_clause = trim(substr($where_clause, 6));
                }
                elseif (strtoupper(substr($where_clause, 0, 4 )) == 'AND ') {
                    $where_clause = trim(substr($where_clause, 4));
                }
                elseif (strtoupper(substr($where_clause, 0, 3)) == 'OR ') {
                    $where_clause = trim(substr($where_clause, 3));

                    $trimmed_sql = trim($where);
                    if ((strtoupper(substr($trimmed_sql, -5)) == 'WHERE') or (strtoupper(substr($trimmed_sql, -2)) == 'OR') or (substr($trimmed_sql, -1) == '(')) {
                        $where .= " $where_clause";
                    }
                    else {
                        $where .= " OR $where_clause";
                    }
                    continue;
                }

                $trimmed_sql = trim($where);
                if ((strtoupper(substr($trimmed_sql, -5)) == 'WHERE') or (strtoupper(substr($trimmed_sql, -3)) == 'AND') or substr($trimmed_sql, -1) == '(') {
                    $where .= " $where_clause";
                }
                else {
                    $where .= " AND $where_clause";
                }
            }
        }
        $sql .= "\n".$where;

    }

    if ($group_by != '') {
        if (! is_array($group_by)) {
            $group_by = array($group_by);
        }

        if ($group_by[0] == '') {
            $group_by_clause = '';
        }
        else {
            $group_by_clause = '';
            foreach ($group_by as $group_by_part) {
                if ($group_by_clause == '') {
                    $group_by_clause = ' GROUP BY ';
                } else {
                    $group_by_clause .= ', ';
                }

                $group_by_clause .= '`'.mysql_real_escape_string(str_replace('.', '`.`', $group_by_part)).'`';
            }
        }

        $sql .= "\n".$group_by_clause;
    }

    if ($order_by != '') {
        if (! is_array($order_by)) {
            $order_by = array($order_by);
        }

        $order_by_clause = '';
        if (!empty($order_by[0])) {
            $order_by_clause = '';
            foreach ($order_by as $order_by_part) {
                if (! is_array($order_by_part)) {
                    $order_by_part = array($order_by_part);
                }

                if ($order_by_clause == '') {
                    $order_by_clause = ' ORDER BY ';
                } else {
                    $order_by_clause .= ', ';
                }

                //$order_by .= '`'.mysql_real_escape_string(str_replace('.', '`.`', $order_by_part[0])).'`';

                if (strpos($order_by_part[0], '(')) {
                    $order_by_clause .= mysql_real_escape_string(str_replace(array('(', ')', '.'), array('(`', '`)', '`.`'), $order_by_part[0]));
                } else {
                    $order_by_clause .= '`'.mysql_real_escape_string(str_replace('.', '`.`', $order_by_part[0])).'`';
                }

                if (isset($order_by_part[1])) {
                    if (strtolower($order_by_part[1]) == 'desc') {
                        $order_by_clause .= ' DESC ';
                    } else {
                        $order_by_clause .= ' ASC ';
                    }
                }
            }
        }

        $sql .= "\n".$order_by_clause;
    }

    if ($limit_by != '') {
        $limit_by = ' LIMIT '.mysql_real_escape_string($limit_by);
        $sql .= "\n".$limit_by;
    }

    return $sql;
}

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
