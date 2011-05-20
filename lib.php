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
 * query is a wrapper for mysql_query and mysql_fetch_assoc, which does basically what
 * everyone wants to do with mysql (aside from connnecting, see below) with one function call, instead of several.
 *
 * The logic should be good for pretty much any kind of query.  I have used this structure for querying
 * for years without problems.
 *
 * Since we should have a connection already open, I won't include configuration information here
 *
 * query returns either true, false or an array in the following format, depending on the style
 * of the query (see the documentation of the PHP function mysql_query().
 *
 * The format of a returned array is as follows:
 *
 * Array {
 *  [row# or field name] => Array {
 *                  name => value
 *                  name => value
 *                  name => value
 *                  name => value
 *                  ....
 *              }
 *  [row# or field name] => Array {
 *                  name => value
 *                  name => value
 *                  name => value
 *                  name => value
 *                  ....
 *              }
 *  ....
 * }
 *
 * Which means you can loop over the output (after checking it to be an array), and every element
 * of that loop will be an associative array with the key being the column name, and the value
 * being the value of that column for that particular record row.  Row# is numbered from 0 to num_rows -1,
 * and is not necessarily related to any values within the record set.  It is just the number of that
 * row in this particular record set, which can vary from query-to-query.  Instead of row#, you can have
 * the key be a field name by passing in the name via the $key param.
 *
 * Calling debug() on the results of this function are very helpful for debugging and understanding
 * the output, if the definition was a little hard to understand.
 *
 * @param string $query_string
 * @param boolean $debug
 * @param boolean $key
 * @return mixed result set OR failure
 * @author Basil Gohar <basil@eschoolconsultants.com>
 */
function query($query_string, $debug = false, $key = false) {
    if (is_string($query_string)) {
        if ($debug) {
          echo $query_string . "\n";
        }
        if (false !== stripos($query_string, 'DELETE')) {
            if (isset($GLOBALS['delete_queries'])) {
                ++$GLOBALS['delete_queries'];
            } else {
                $GLOBALS['delete_queries'] = 1;
            }
        }

        $result = mysql_query($query_string);

        // increment query check variable for reporting purposes
        // but first, make sure it's set
        if (!isset($GLOBALS['query_check']['query()']))
            $GLOBALS['query_check']['query()'] = 0;

        $GLOBALS['query_check']['query()']++;

        if (is_resource($result)) {
            $return_array = array();
            while ($row = mysql_fetch_assoc($result)) {
                if ($key)
                    $return_array[$row[$key]] = $row;
                else
                    $return_array[] = $row;
            }
            return $return_array;
        } else {
            return $result;
        }
    }
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
 */
function get_single_field_value($table_name, $field_name, $constraints, $debug=false)
{
    $where_clauses = get_where_clause_from_constraints($constraints);

    $sql = get_sql($table_name, $field_name, $where_clauses, '', '', 1);

    $result = query($sql, $debug);

    if (is_array($result) && count($result) > 0) {
        return $result[0][$field_name];
    } else {
        return false;
    }
}

function get_where_clause_from_constraints($constraints = null)
{
    $where_clause = array();
        // populate based on given constraints
    if (null !== $constraints) {
        foreach ($constraints as $field_name => $values)
        {
            // If operator is not specified, then assume "="
            if ( strpos( $field_name, ' ' ) === FALSE )
            {
                if (null === $values) {
                    //  Handle a special case where checking to see if something is exactly NULL
                    $where_clause[] = '`' . mysql_real_escape_string($field_name) . '` IS NULL';
                } else if (! is_array($values)) {
                    //  $values is a scalar value
                    $where_clause[] = '`' . mysql_real_escape_string($field_name) . "` = '" . mysql_real_escape_string($values) . "'";
                } else if ($clause = get_sql_in_string($values, $field_name)) {
                    $where_clause[] = $clause;
                }
            }
            else
            {
                // get the field and operator which is separated by a space
                $field = substr( $field_name, 0, strpos( $field_name, ' ' ));
                $operator = substr( $field_name, strpos( $field_name, ' ' ) + 1 );

                // allow for NOT IN
                if ( is_array( $values ))
                {
                    if ( $operator == '!=' and $clause = get_sql_in_string( $values, $field, TRUE ))
                    {
                        $where_clause[] = $clause;
                    }
                    else
                    {
                        throw new Exception( 'Invalid constraint values' );
                    }
                }
                // attempt to create a query using operators such as "<" or ">="
                else {
                    $value = $values;

                    $field = trim($field);
                    $value = trim($value);
                    $operator = trim($operator);

                    $clause = mysql_real_escape_string($field) . " $operator ";

                    if (false !== strpos($value, "'", 0) && false !== strpos($value, "'", strlen($value) - 1)) {
                        //  Value is already quoted
                        $clause .= $value;
                    }
                    else {
                        $clause .= "'" . $value . "'";
                    }

                    if (empty($clause)) {
                        throw new Exception('Uknown constraint');
                    }
                    else {
                        $where_clause[] = $clause;
                    }
                }
            }
        }
    }

    return $where_clause;
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
