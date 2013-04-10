<?php

require_once dirname(__FILE__) . '/db_object.php';

class db_recordset implements ArrayAccess, Iterator, Countable
{
    //  Table structure attributes
    protected $table_name = '';
    protected $table_info = array();
    protected $primary_key_field_name = '';
    protected $columns = array();

    //  Recordset attributes
    /**
     * Stores the constraints used when fetching data from the database.
     *
     * When it is simple, single-dimensioned non-associative array, $constraints is assumed
     * to be a list of primary keys.
     *
     * When it is a single-dimensioned associative array, $constraints is assumed to be
     * a list of column-value pairs.
     *
     * When it is a multi-dimensioned associative array, each 1st-dimension key is assumed to be a
     * a column name with its 2nd-dimension array assumed to be its listing of values.
     *
     * @var mixed
     */
    protected $constraints = NULL;

    // Generic, empty db_object for this table. We use it to find the primary key, etc.
    private $db_object;

    // Boolean: When set to true, db_recordset will attempt to discover
    // the existence of a class by the same name as the table.
    protected $autodiscover_class = false;

    // this variable is set to the discovered class name
    protected $discovered_class = NULL;

    // Array: Stores the sort order for the data.
    // The key should be the name of the field and the value should be either "ASC" or "DESC".
    // The data will be sorted in the same order they are put into the array.
    protected $sort_order = NULL;

    // String: This is the field to use as the array key in the $data array.
    // If no value is passed this the $data array will be a common array.
    protected $array_key_field_name = NULL;

    // Resource: Stores the resource returned from the mysql_query() function.
    protected $db_result = NULL;

    //  Status attributes

    // Boolean: When set to true, db_recordset will first process the recordset
    // constraints & fetch new data before returning any results.
    protected $dirty_data = true;
    protected $dirty_index = true;
    protected $fetch_row_success = true;

    // Array: Allows us to tie the chosen db field, typically the primary key, to a simple array structure.
    // We will only use this when the object is accessed as an array.
    protected $index_array_key_map = NULL;

    // String: Holds the last SQL query to be executed in this object.
    // Made an attribute for debugging purposes.
    private $sql = '';

    // Array: Stores the result of mysql_fetch_assoc().
    protected $current_row_data = array();

    protected $limit = NULL;
    protected $offset = NULL;

    /**
     * Sets-up db_recordset object with object type & initial conditions
     *
     * @param string $table_name
     * @param array $constraints
     * @param boolean $autodiscover_class
     * @param array $sort_order (array('first_name' => 'DESC'))
     * @param string $array_key
     * @param boolen $include_deleted
     * @param integer/array $limit_results_by (EX: "$limit_results_by = 100" OR "$limit_results_by = array(100, 50)". This will be passed to set_recordset_limit() )
     * @return boolean success or failure
     */
    public function __construct($table_name=NULL, $constraints=NULL, $autodiscover_class=TRUE, $sort_order=NULL, $array_key_field_name=NULL, $include_deleted=FALSE, $limit_results_by=NULL)
    {
        if (strlen(trim($table_name)) == 0) {
            throw new Exception('Table name cannot be empty');
        }

        $this->table_name = $table_name;
        $this->db_object = new db_object($table_name);

        $this->table_info = $this->db_object->table_info();

        $this->columns = array_keys($this->table_info);

        $this->primary_key_field_name = $this->db_object->get_primary_key_field();

        // set the discovered class
        $this->autodiscover_class = $autodiscover_class;
        if ($this->autodiscover_class) {
            $this->set_discovered_class($this->table_name);
        }

        $this->set_sort_order($sort_order);

        if($limit_results_by){
            $limit_by = $this->limit_ensure_array($limit_results_by);
            $this->set_recordset_limit($limit_by[0], $limit_by[1]);
        }

        if ($array_key_field_name) {
            $this->array_key_field_name = $array_key_field_name;
        }
        else {
            $this->array_key_field_name = $this->primary_key_field_name;
        }

        $constraints = $this->format_constraints($constraints);

        if ($include_deleted==FALSE && in_array('deleted', $this->columns)) {
            $constraints['deleted'] = '0';  // a string zero because this value is quoted.
        }

        $this->set_constraints($constraints);

        // Reset the internal pointer. This allows current to work on a new db_recordset
        $this->rewind();
    }

    /**
     * Sets the "discovered_class" property
     *
     * The discovered class property is used to create db_objects within the
     * recordset. All db_objects created will be of the discovered class (ex
     * student, staff, etc.) when this is set.
     *
     * @param str $class_name
     * @return bool
     * @author Nick Whitt
     */
    public function set_discovered_class($class_name)
    {
        // We check for this in the if statement below
        $singular_class_name = '';
        if (substr($class_name, -1) == 's') {
            $singular_class_name = substr($class_name, 0, strlen($class_name)-1);
        }

        if (class_exists($class_name) && is_subclass_of($class_name, 'db_object')) {
            $this->discovered_class = $class_name;
            return TRUE;
        }
        elseif ($singular_class_name != '' && class_exists($singular_class_name) && is_subclass_of($singular_class_name, 'db_object')) {
            $this->discovered_class = $singular_class_name;
            return TRUE;
        }

        return FALSE;
    }

    /**
     * Allows for setting of limit (and offset) for the query in fetch_data()
     *
     * A successful execution will result in the dirty_data flag being set to
     * true, forcing a new call to fetch_data().
     *
     * @param int $limit
     * @param int $offset
     * @return void
     * @author Nick Whitt
     */
    public function set_recordset_limit($limit, $offset=NULL)
    {
        if ($limit < 1) {
            throw new Exception('Limit must be greater than 0');
        }

        /**
         * This causes the data to be fetched and screws up the limit.
         * TODO: get this to work properly.
         */
        // if ($offset > count($this))
        // {
        //     throw new Exception('Offset must be less than the maximum recordset size');
        // }

        $this->limit = $limit;

        if (! is_null($offset)) {
            $this->offset = $offset;
        }

        $this->dirty_data = true;
        $this->dirty_index = true;
    }

    /**
     * Executes data retrieval based on the current constraints
     *
     * @return void
     */
    protected function fetch_data($record_ids_to_add=NULL)
    {
        if ($this->dirty_data === false)
            return;

        $this->dirty_data = false;

        // Construct the where clause
        $where_clause = array();

        // If this isn't the first time we're calling fetch_data then we'll use the
        // constraints and limit the possible results to the subset we've already fetched.
        if (!is_null($this->db_result)) {
            $ids = $this->get_field_values('');

            // Allows us to add in other records to the recordset
            if (is_array($record_ids_to_add)) {
                $ids = array_merge($ids, $record_ids_to_add);
            }

            if ($clause = get_sql_in_string($ids, $this->db_object->get_primary_key_field()))
                $where_clause[] = $clause;
        }

        $potential_wheres = $this->get_where_clause_from_constraints($this->constraints);
        $current_wheres = $where_clause;
        // We need to strip out empty where in's
        // Ex: "id IN ('')"
        if(!empty($current_wheres) && !empty($potential_wheres)){
            $to_current_wheres = array();
            foreach($current_wheres as $cw){
                $cw_array = explode(' ', $cw);
                if($cw_array[1] == 'IN'){
                    foreach($potential_wheres as $pw){
                        $pw_array = explode(' ', $pw);
                        if($pw_array[1] == 'IN' && $pw_array[0] == $cw_array[0] && $pw_array[2] != "('')"){
                            $to_current_wheres[] = $pw;
                        }
                    }
                }
            }
            $where_clause = array_merge($where_clause, $to_current_wheres);
        }else{
            // populate based on given constraints
            $where_clause = array_merge($where_clause, $potential_wheres);
        }

        // Construct the sort by clause
        $sort_array = array();
        if (!empty($this->sort_order)) {
            foreach($this->sort_order as $sort_field => $sort_type) {
                $sort_array[] = array($sort_field, $sort_type);
            }
        }

        // Construct the limit clause
        $limit_by = '';
        if (! is_null($this->limit)) {
            if (! is_null($this->offset)) {
                $limit_by .= $this->offset.', ';
            }

            $limit_by .= $this->limit;
        }

        $this->sql = db_object::get_sql($this->table_name, '*', $where_clause, $sort_array, '', $limit_by);
        $this->db_result = mysql_query($this->sql);

        // Final initialization of data & setting of current state
        $this->constraints = NULL;
        $this->dirty_data = false;
        $this->dirty_index = true;
    }

    protected function get_where_clause_from_constraints($constraints = null)
    {
        $where_clause = array();
            // populate based on given constraints
        if (null !== $constraints) {
            foreach ($constraints as $field_name => $values) {
                // If operator is not specified, then assume "="
                if (strpos($field_name, ' ') === FALSE) {
                    if (null === $values) {
                        // Handle a special case where checking to see if something is exactly NULL
                        $where_clause[] = '`' . mysql_real_escape_string($field_name) . '` IS NULL';
                    }
                    else if (! is_array($values)) {
                        // $values is a scalar value
                        $where_clause[] = '`' . mysql_real_escape_string($field_name) . "` = '" . mysql_real_escape_string($values) . "'";
                    }
                    else if ($clause = get_sql_in_string($values, $field_name)) {
                        $where_clause[] = $clause;
                    }
                }
                else {
                    // get the field and operator which is separated by a space
                    $field = substr($field_name, 0, strpos($field_name, ' '));
                    $operator = substr($field_name, strpos($field_name, ' ') + 1);

                    // allow for NOT IN
                    if (is_array($values)) {
                        if ($operator == '!=' and $clause = get_sql_in_string($values, $field, TRUE)) {
                            $where_clause[] = $clause;
                        }
                        else {
                            throw new Exception('Invalid constraint values');
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
     * Allows us to add in arbitrary records to the recordset. This is handy when you want
     * to limit by one thing or another.  By default, db_recordset can't do this since the the
     * first limit will reduce the possible ids down to a certain subset.
     *
     * @param array $record_ids_to_add
     * @return void
     */
    function add_records($record_ids_to_add) {
        // Make sure any outstanding constraints are processed
        $this->fetch_data();

        // Call fetch_data again with our ids.
        $this->dirty_data = true;
        $this->fetch_data($record_ids_to_add);
    }

    /**
     * This function takes an array in the same format as the constructor (array('student_id' => 'DESC'))
     */
    function set_sort_order($sort_array = NULL)
    {
        if (is_array($sort_array)) {
            foreach ($sort_array as $attribute => $order) {
                if (!$this->db_object->is_acceptable_attribute($attribute)) {
                    throw new Exception('Trying to sort with an invalid attribute: "' . $attribute . '"');
                }
            }
        }

        $this->sort_order = $sort_array;
        $this->dirty_data = true;
    }

    /**
     * Return an instance of a db_object or subclass of db_object whose array key matches the value of $id
     *
     * @param mixed $id array key value
     * @return db_object object
     */
    protected function fetch_db_object($id = NULL)
    {
        // If an id was passed
        if ($id != NULL)
            $this->fetch_row($id);

        if (!is_null($this->discovered_class)) {
            return new $this->discovered_class(NULL, $this->table_info, $this->current_row_data);
        }
        $db_object = new db_object($this->table_name, NULL, $this->table_info, $this->current_row_data);
        return $db_object;
    }

    /**
     * Retrieve data from the db_result.
     * If we are not passing a parameter then we are probably iterating and should move the pointer
     * to the next row after we're done.  If we do pass an $id then we don't move the pointer at all.
     *
     * @param str $id
     * @return bool
     * @author Bryce Thornton
     */
    protected function fetch_row($id = NULL)
    {
        // Make sure the array is populated if they pass an ID
        // We only want to create the array if we HAVE to. (for memory reasons)
        if ($id != NULL) {
            if ($this->dirty_index === true)
                $this->refresh_index();
        }

        // If we are using the index array and it needs to be rebuilt
        if (!empty($this->index_array_key_map) && $this->dirty_index === true) {
            $this->refresh_index();
        }

        if ($this->count() > 0) {
            // If we are using this like an array then use the map to determine what rows we are dealing with.
            if (!empty($this->index_array_key_map)) {
                if ($id != NULL) {
                    // Get the position we are in right now so we can return the mysql result pointer to it later.
                    $original_position = key($this->index_array_key_map);
                    $array_key = array_search($id, $this->index_array_key_map);

                    // The record doesn't exist.
                    if ($array_key === FALSE) {
                        $this->fetch_row_success = FALSE;
                        return $this->fetch_row_success;
                    }

                    mysql_data_seek($this->db_result, $array_key);
                }
                elseif (current($this->index_array_key_map) !== FALSE) {
                    mysql_data_seek($this->db_result, key($this->index_array_key_map));
                    next($this->index_array_key_map);
                }
                // If we're at the end of the array
                else {
                    $this->fetch_row_success = FALSE;
                    return $this->fetch_row_success;   // We don't want to grab any more data.
                }
            }

            if ($this->current_row_data = mysql_fetch_assoc($this->db_result))
                $this->fetch_row_success = TRUE;
            else
                $this->fetch_row_success = FALSE;

            // We need to move the mysql result pointer back to where it was originally.
            if ($id != NULL)
                mysql_data_seek($this->db_result, $original_position);
        }
        else
            $this->fetch_row_success = FALSE;

        return $this->fetch_row_success;
    }

    /**
     * Refreshes the primary key-to-index value array map.
     *
     */
    protected function refresh_index()
    {
        if ($this->dirty_data === true)
            $this->fetch_data();

        // Make sure it really needs refreshed.
        if ($this->dirty_index === true) {
            if ($this->count() > 0) {
                $this->index_array_key_map = array();

                mysql_data_seek($this->db_result, 0);

                while ($data_array = mysql_fetch_assoc($this->db_result)) {
                    $this->index_array_key_map[] = $data_array[$this->array_key_field_name];
                }
            }
            else {
                $this->index_array_key_map = array();
            }

            $this->dirty_index = false;
        }
    }

    /**
     * Allows for updates to the constraints.
     */
    public function set_constraints($constraints)
    {
        if ($this->constraints != NULL) {
            if ($this->dirty_data === true)
                $this->fetch_data();
            if ($this->dirty_index === true)
                $this->refresh_index();
        }

        $this->constraints = $this->format_constraints($constraints);

        $this->dirty_data = true;
    }

    public function format_constraints($constraints) {
        if (is_null($constraints)) {
            // Do nothing here.
            // Just leave the constraints as they were.
        }
        // If we just have a list of ids then figure out the primary key
        // and set up the contraints array properly.
        // Also, if we were passed an empty array then we assume it means that this recordset is to be "empty".
        elseif (is_numeric(key($constraints)) || (is_array($constraints) && empty($constraints))) {
            $constraints = array($this->db_object->get_primary_key_field() => $constraints);
        }
        return $constraints;
    }

    /**
     * Builds an array by calling the closure on each object in the recordset
     * and collecting the returned value. The array will be index w/ the same
     * keys as the recordset which created
     *
     * @author Kyle Decot <kyle.decot@eschoolconsultants.com>  July 11, 2012
     *
     */
    public function collect($callback) {

        $collection = array();

        // If we've passed in an anonymous function then we'll just call that function for each
        // one of the objects in the recordset...

        if ($callback instanceof Closure) {
            foreach ($this as $index => $object) {
                $collection[$index] = $callback($object);
            }
        }

        // If we've passed in a string then we'll first look to see if there is an attribute w/
        // named this. If there is we'll just return that; Otherwise, we'll see if there is a
        // method that we can call w/ this name. If there is then we'll return the result of
        // that method. If we can do either of those things then we'll throw an Exception because
        // we've obviously passed in some kind of invalid callback...

        else if (is_string($callback)) {

            // Create a null instantiated object so that we can see what kind of attributes/methods
            // we have to pick from...

            $blueprint = new db_object($this->table_name());

            // Attribute
            if (in_array($callback, array_keys($blueprint->get_attributes()))) {
                foreach ($this as $index => $object) {
                    $collection[$index] = $object->get_attribute($callback);
                }
            }
            // Method
            else if (method_exists($blueprint, $callback)) {
                foreach ($this as $index => $object) {
                    $collection[$index] = call_user_func(array($object, $callback));
                }
            }
            // Exception
            else {
                throw new Exception('Invalid callback specified: ' . $callback);
            }
        }



        return $collection;
    }

    /*******************************************
     * ArrayAccess interface methods
     ******************************************/

    public function offsetExists($offset)
    {
        if ($this->dirty_data === true)
            $this->fetch_data();
        if ($this->dirty_index === true)
            $this->refresh_index();

        return in_array($offset, $this->index_array_key_map);
    }

    public function offsetGet($offset)
    {
        if ($this->dirty_data === true)
            $this->fetch_data();
        if ($this->dirty_index === true)
            $this->refresh_index();

        if ($this->offsetExists($offset))
            return $this->fetch_db_object($offset);
        else
            return false;
    }

    public function offsetSet($offset, $value)
    {
        throw new Exception('Recordsets cannot be appended to at this time');
        return false;
    }

    public function offsetUnset($offset)
    {
        if ($this->dirty_data === true)
            $this->fetch_data();
        if ($this->dirty_index === true)
            $this->refresh_index();

        $key = array_search($offset, $this->index_array_key_map);
        unset($this->index_array_key_map[$key]);
    }

    /*******************************************************************
     * Iterator interface methods
     *
     * While iterating, these methods are called in the following order:
     *
     * Rewind (Always called once when beginning the iteration)
     * Valid (Always called after rewind())
     * Current (This is end of the first iteration)
     *
     * Next
     * Valid (If this returns false, iteration ends)
     * Current
     ******************************************************************/

    public function rewind()
    {
        if ($this->dirty_data === true)
            $this->fetch_data();

        if ($this->count() > 0) {
            if ($this->index_array_key_map)
                reset($this->index_array_key_map);
            else
                mysql_data_seek($this->db_result, 0);
        }

        // This gets the data and moves the pointer to the next row.
        $this->fetch_row();
    }

    public function key()
    {
        if ($this->dirty_data === true)
            $this->fetch_data();

        return $this->current_row_data[$this->primary_key_field_name];
    }

    public function current()
    {
        if ($this->dirty_data === true)
            $this->fetch_data();

        return $this->fetch_db_object();
    }

    public function next()
    {
        if ($this->dirty_data === true)
            $this->fetch_data();

        // This gets the data and moves the pointer to the next row.
        $this->fetch_row();
    }

    public function valid()
    {
        if ($this->dirty_data === true)
            $this->fetch_data();

        return $this->fetch_row_success;
    }

    /*******************************************
     * Countable interface methods
     ******************************************/

    public function count()
    {
        if ($this->dirty_data === true)
            $this->fetch_data();

        // Make sure we have some data in the recordset
        if (is_array($this->index_array_key_map))
            return count($this->index_array_key_map);
        elseif ($this->db_result)
            return mysql_num_rows($this->db_result);
        else
            return 0;
    }

    /**
     * Returns the values contained in the recordset for the given column
     *
     * By default, all values of the primary key will be returned.
     *
     * @param str $field
     * @return array
     * @author Nick Whitt
     */
    public function get_field_values($field='')
    {
        if ($field == '') {
            $field = $this->primary_key_field_name;
        }
        elseif (! in_array($field, $this->columns)) {
            // throw error? incorrect field name
            return false;
        }

        if ($this->dirty_data === true) {
            $this->fetch_data();
        }

        if ($this->index_array_key_map && $field == $this->primary_key_field_name) {
            if ($this->dirty_index === true)
                $this->refresh_index();

            return $this->index_array_key_map;
        }
        else {
            $values = array();
            $count = $this->count();

            for ($i=0; $i<$count; $i++) {
                $values[] = mysql_result($this->db_result, $i, $field);
            }

            return $values;
        }
    }

    /**
     * Alias for get_field_values in order to maintain some backwards compatibility.
     *
     * @param void
     * @return array
     */
    public function get_recordset()
    {
        return $this->get_field_values();
    }

    /**
     * Simply returns the table_info array
     *
     * @return array table_info
     */
    public function table_info()
    {
        return $this->table_info;
    }

    /**
     * Simply returns the table_name value
     */
    public function table_name()
    {
        return $this->table_name;
    }

    public function get_primary_key_field()
    {
        return $this->primary_key_field_name;
    }

    /**
     * Returns the first object in the recordset
     *
     * @todo Establish what to do when recordset is empty.
     *
     * @param void
     * @return db_object
     * @author Nick Whitt
     */
    public function first()
    {
        // preserve internal sort order with get_field_values()
        if (!$ids = $this->get_field_values()) {
            // invalid recordset
            return FALSE;
        }

        if (empty($ids)) {
            // what about empty recordset?
        }

        return $this[$ids[0]];
    }

    /**
     * Makes sure that a variable is indeed an array.
     *
     * @author David Varney <david.varney@eschoolconsultants.com>
     *
     * @param array/string $input This can be an array or a string
     * @return array $input
     */
    protected function limit_ensure_array($input){
        if(!is_array($input)){
            $input = array($input, null);
        }
        return $input;
    }
}
