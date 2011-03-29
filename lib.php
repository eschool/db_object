<?php

/**
 * This is the name of the table that contains IDs returned by get_user_id.
 * db_object uses this value to return a db_object of the updater or inserter of a record
 */
define('USER_TABLE', 'staff');

/**
 * db_object uses this function to assign the updated_by and inserted_by metadata.
 * This function should return the numeric ID of the user that changed a DB record.
 * It can return false, which will result in no change to the updated_by or inserted_by metadata.
 *
 * @author John Colvin <john.colvin@eschoolconsultants.com>
 */
function get_user_id() {
    if (isset($_SESSION['login_user']) && $_SESSION['login_user'] instanceof db_object) {
        return $_SESSION['login_user']->get_id();
    }
    return false;
}

require_once 'db_object.php';
require_once 'db_recordset.php';

/**
 * Returns true if $var is a nonempty array, otherwise returns false
 *
 * @param mixed $var
 * @return boolean is non-empty array
 */
function is_nonempty_array($var)
{
    return (isset($var) && (is_array($var) || $var instanceof ArrayAccess) && (sizeof($var) > 0));
}

function get_sql_insert_string($table, $columns, $values, $where = '') {
    $sql_string = 'INSERT INTO `'.mysql_real_escape_string($table).'`'."\n";
    if (is_array($columns) && sizeof($columns) > 0) {
        $num_columns = sizeof($columns);
        if (sizeof($values) != $num_columns) {
            //  Whoops!  Someone passed the wrong number of values or columns
            trigger_error('Columns array and values array sizes do not match', E_USER_ERROR);
            return false; //  This should never be reached
        }
    }
    $sql_string .= ' (';
    $column_array = array();

    //  Generate the columns list
    for ($i = 0; $i < $num_columns; $i++) {

        // Skip this column if the value is NULL
        if (is_null($values[$i]))
            continue;

        $column = $columns[$i];
        if (get_magic_quotes_gpc()) {
            $column = stripslashes($column);
        }
        $column_array[] .= '`'.mysql_real_escape_string($column).'`';
    }
    //  Strip off the last, unnecessary comma
    $sql_string .= implode(',', $column_array);
    $sql_string .= ') VALUES (';
    $value_array = array();

    //  Generate the values list
    for ($i = 0; $i < $num_columns; $i++)
    {
        $value = $values[$i];

        // Skip this column if the value is NULL
        if ( is_null( $value ))
        {
            continue;
        }
        else
        {
            if ( get_magic_quotes_gpc() )
            {
                $value = stripslashes( $value );
            }

            $value_array[] = '"' . mysql_real_escape_string( $value ) . '"';
        }
    }

    //  Strip off the last, unnecessary comma
    $sql_string .= implode(',', $value_array);

    $sql_string .= ') ';
    //  Tack on the "where" clause
    $sql_string .= $where;
    return $sql_string;
}

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
    $select = get_select_clause($fields);

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
        $where = get_where_clause($where_clause);
        $sql .= "\n".$where;
    }

    if ($group_by != '') {
        $group_by = get_group_by_clause($group_by);
        $sql .= "\n".$group_by;
    }

    if ($order_by != '') {
        $order_by = get_order_by_clause($order_by);
        $sql .= "\n".$order_by;
    }

    if ($limit_by != '') {
        $limit_by = ' LIMIT '.mysql_real_escape_string($limit_by);
        $sql .= "\n".$limit_by;
    }

    return $sql;
}

/**
 * Returns the "select" string for the sql statement
 *
 * The fields are automatically quoted and escaped by the function. MYSQL functions
 * can be passed as array elements. For example:
 *
 *     get_select_clause(array("student_id AS student", "DISTINCT(entering_grade)"));
 *
 * returns the string "SELECT `student_id` AS `student`, DISTINCT(`entering_grade`)".
 *
 * @param array $fields
 * @return str
 * @author Nick Whitt
 */
function get_select_clause($fields='*') {
    if (! is_array($fields)) {
        $fields = array($fields);
    }

    if (in_array($fields[0], array('', '*'))) {
        return 'SELECT * ';
    }

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

    return $select;
}

/**
 * Returns the "where" string for the sql statement
 *
 * Each value in the clauses array is a seporate where clause. Each clause is
 * assumed to be an "and" clause if not specified otherwise. The fields are neither
 * quoted nor escaped by the function, so they must be valid before being passed.
 *
 * @todo This function depends heavily on both add_and_to_sql() and add_or_to_sql()
 * which currently reside in sis_lib. Both should be moved to data_lib.
 * @todo Figure a way to quote and escape the fields.
 *
 * @param array $clauses
 * @return str
 * @author Nick Whitt
 */
function get_where_clause($clauses='1=1') {
    if (! is_array($clauses)) {
        $clauses = array($clauses);
    }

    if (! isset($clauses[0]) || $clauses[0] == '') {
        return ' WHERE 1=1';
    }

    $where = ' WHERE ';
    foreach ($clauses as $clause) {
        $clause = trim($clause);

        if (strtoupper(substr($clause, 0, 6)) == 'WHERE ') {
            $clause = trim(substr($clause, 6));
        } elseif (strtoupper(substr($clause, 0, 4 )) == 'AND ') {
            $clause = trim(substr($clause, 4));
        } elseif (strtoupper(substr($clause, 0, 3)) == 'OR ') {
            $clause = trim(substr($clause, 3));
            $where = add_or_to_sql($where, $clause);
            continue;
        }

        $where = add_and_to_sql($where, $clause);
    }

    return $where;
}

/**
 * Returns the "group by" string for the sql statement
 *
 * The fields are automatically quoted and escaped by the function.
 *
 * @param array $clauses
 * @return str
 * @author Nick Whitt
 */
function get_group_by_clause($clauses='') {
    if (! is_array($clauses)) {
        $clauses = array($clauses);
    }

    if ($clauses[0] == '') {
        return false;
    }

    $group_by = '';
    foreach ($clauses as $clause) {
        if ($group_by == '') {
            $group_by = ' GROUP BY ';
        } else {
            $group_by .= ', ';
        }

        $group_by .= '`'.mysql_real_escape_string(str_replace('.', '`.`', $clause)).'`';
    }

    return $group_by;
}

/**
 * Returns the "order by" string for the sql statement
 *
 * Each value in the clauses array can be either a string or an array. By default,
 * the clauses are sorted in ascending order. To override this, pass the sort order
 * as the second array value of each clause in the clauses array. For example:
 *
 *      get_order_by_clause(array(array('last_name', 'desc'), 'first_name'));
 *
 * returns the string " ORDER BY `last_name` DESC , `first_name` ".
 *
 * The fields are automatically quoted and escaped by the function.
 *
 * @param array $clauses
 * @return str
 * @author Nick Whitt
 */
function get_order_by_clause($clauses='') {
    if (! is_array($clauses)) {
        $clauses = array($clauses);
    }

    if (empty($clauses[0])) {
        return false;
    }

    $order_by = '';
    foreach ($clauses as $clause) {
        if (! is_array($clause)) {
            $clause = array($clause);
        }

        if ($order_by == '') {
            $order_by = ' ORDER BY ';
        } else {
            $order_by .= ', ';
        }

        //$order_by .= '`'.mysql_real_escape_string(str_replace('.', '`.`', $clause[0])).'`';

        if (strpos($clause[0], '(')) {
            $order_by .= mysql_real_escape_string(str_replace(array('(', ')', '.'), array('(`', '`)', '`.`'), $clause[0]));
        } else {
            $order_by .= '`'.mysql_real_escape_string(str_replace('.', '`.`', $clause[0])).'`';
        }

        if (isset($clause[1])) {
            if (strtolower($clause[1]) == 'desc') {
                $order_by .= ' DESC ';
            } else {
                $order_by .= ' ASC ';
            }
        }
    }

    return $order_by;
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
          debug($query_string);
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

/**
 * debug is a function that "displays" any kind of variable, wrapped in <pre> tags to facilitate
 * a nice view, preformatted, mono-spaced view.
 * It is good for debugging simple variable values, objects, and arrays.
 *
 * @param string $debug_thing
 * @author Basil Mohamed Gohar <basil.gohar@eschoolconsultants.com>
 */
function debug($debug_thing, $return = false, $nopre = false) {
    if (defined('DEBUG')) {
        if (DEBUG) {
            $continue = true;
        } else {
            $continue = false;
        }
    } else {
        $continue = true;
    }

    if ($continue) {
        $output = '';
        if (! $nopre && isset($_SERVER['REQUEST_METHOD'])) {
            //  Only output "pre" tags if this is a web request
            $output .= '<pre>';
        }
        if ($debug_thing === true) {
            $output .= 'true';
        } elseif ($debug_thing === false) {
            $output .= 'false';
        } elseif (is_string($debug_thing)) {
            $output .= '"'.$debug_thing.'"';
        } elseif (is_integer($debug_thing)) {
            $output .= '='.$debug_thing;
        } elseif (is_null($debug_thing)) {
            $output .= 'null';
        } elseif (empty($debug_thing)) {
            $output .= 'Empty!';
        } else {
            $output .= print_r($debug_thing, true);
        }
        if (! $nopre && isset($_SERVER['REQUEST_METHOD'])) {
            $output .=  '</pre>';
        }
        $output .= "\n";

        if ($return) {
            return $output;
        } else {
            echo $output;
            flush();
        }
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
                elseif ( $clause = get_sql_where_string( $field, $values, $operator ))
                {
                    $where_clause[] = $clause;
                }

                // report error
                else
                {
                    throw new Exception( 'Uknown constraint' );
                }
            }
        }
    }

    return $where_clause;
}

/**
 * This function adds an "AND" to a SQL string if it needs it.  This is
 * handy if you are appending a lot of SQL together but aren't keeping
 * track of the exact where clauses that are being added.
 *
 * Paramters:
 *  $sql          = This is the sql statement up to the point where you want to add
 *                another where clause.
 *  $where_sql    = This is the where clause you want to add.
 */
function add_and_to_sql($sql, $where_sql)
{
   $trimmed_sql = trim($sql);
   if ((strtoupper(substr($trimmed_sql, -5)) == 'WHERE') or (strtoupper(substr($trimmed_sql, -3)) == 'AND') or substr($trimmed_sql, -1) == '(')
      return $sql . " $where_sql";
   else
      return $sql . " AND $where_sql";
}

/**
 * This function adds an "OR" to a SQL string if it needs it.  This is
 * handy if you are appending a lot of SQL together but aren't keeping
 * track of the exact where clauses that are being added.
 *
 * Paramters:
 *  $sql          = This is the sql statement up to the point where you want to add
 *                another where clause.
 *  $where_sql    = This is the where clause you want to add.
 */
function add_or_to_sql($sql, $where_sql)
{
   $trimmed_sql = trim($sql);
   if ((strtoupper(substr($trimmed_sql, -5)) == 'WHERE') or (strtoupper(substr($trimmed_sql, -2)) == 'OR') or (substr($trimmed_sql, -1) == '('))
      return $sql . " $where_sql";
   else
      return $sql . " OR $where_sql";
}

function get_sql_where_string($field_name, $value, $operator='=')
{
    $field_name = trim($field_name);
    $value = trim($value);
    $operator = trim($operator);

    $sql_string = mysql_real_escape_string($field_name) . " $operator ";

    if (false !== strpos($value, "'", 0) && false !== strpos($value, "'", strlen($value) - 1)) {
        //  Value is already quoted
        $sql_string .= $value;
    } else {
        $sql_string .= "'" . $value . "'";
    }

    return $sql_string;
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

/**
 * Simply calls mysql_query() and returns the resource.
 * The only reason I have abstracted it is to log the query in $GLOBALS['query_check'].
 *
 * @param string $query_string
 * @param boolean $debug
 * @return resource OR boolean
 * @author Bryce Thornton
 */
function query_resource($query_string, $debug = false)
{
    if (is_string($query_string)) {
        if ($debug) {
          debug($query_string);
        }

        $result = mysql_query($query_string);

        // increment query check variable for reporting purposes
        // but first, make sure it's set
        if (!isset($GLOBALS['query_check']['query_resource()']))
            $GLOBALS['query_check']['query_resource()'] = 0;

        $GLOBALS['query_check']['query_resource()']++;

        if (is_resource($result)) {
            return $result;
        } else {
            return FALSE;
        }
    }
}
