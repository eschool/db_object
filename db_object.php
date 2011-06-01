<?php
/**
 * Basic database object
 *
 * db_object is an object abstraction that, in the rawest sense, represents a
 * row from a database table.  In a more abstract sense, db_object represents an
 * object that is stored in the database - examples of objects would be student,
 * staff, course, and so on.
 *
 * Object attributes are stored in an associative array "$attributes", where the
 * key is the name of the attribute from the column in which it resides.
 *
 * The list of permitted attributes for this object is constructed at object
 * instantiation, and is retrieved from the database table. This information is
 * stored in "$acceptable_attributes".
 *
 * If data in the object is modified, $modified is set to true.  This value is
 * set in the set_ functions.  It is cleared whenever the add() or update()
 * methods are successfully called.
 *
 * The name of the primary key field for an object's table is stored in the
 * variable $primary_key_field.  This value is references in various SQL
 * queries.
 *
 * $table_name is the name of the database table in which this object exists.
 * This value should be set in child classes.
 *
 * @package Base
 * @author Basil Gohar (basil@eschoolconsultants.com)
 *
 */

require_once dirname(__FILE__) . '/lib.php';
require_once dirname(__FILE__) . '/db_recordset.php';

class db_object {

    /**
     * All attributes pulled from a database or currently set for the object
     *
     * @var array
     */
    protected $attributes;

    /**
     * An array to indictate whether or not each attribute of the object has been modified
     * and/or is in need of being updated or synchronized with the database
     *
     * @var array
     */
    protected $modified_attributes;

    /**
     * An array associating each attribute with a specific content type. This type is used to
     * determine how to sanitize data before it is inserted into/updated in the database
     *
     * @var array
     */
    protected $attribute_content_types;

    protected $force_no_filtering;

    /**
     * Whether or not the object was instantiated with a NULL value, and therefore,
     * does not represent an actual row in the database yet.  This is useful for
     * creating new rows via a db_object.
     *
     * @var boolean
     */
    public $null_instantiated = false;

    /**
     * Name of the primary key field
     *
     * @var string
     */
    protected $primary_key_field;

    /**
     * Name of the database table
     *
     * @var string
     */
    public $table_name;

    /**
     * Table information array
     *
     * @var array
     */
    protected $table_info;

    /**
     * The type of the inserted_on/updated_on columns (both should be same type)
     *
     * @var string
     */
    protected $time_column_type;

    /**
     * An array of all possible metadata fields.  Used to determine whether or not
     * a particular field is a metadata field.
     *
     * @var array
     */
    protected $possible_metadata_fields = array(
                                'inserted_on',
                                'inserted_by',
                                'inserted_ip',
                                'updated_on',
                                'updated_by',
                                'updated_ip',
                                'deleted'
                               );

    /**
     * The names of fields that hold metadata for this object (e.g., inserted_on)
     *
     * @var array
     */
    public $metadata_fields = array();

    /**
     * The names of metadata fields which should not be automatically populated
     * with their related metadata.
     *
     * This array holds a simple list of metadata fields whose values should not be
     * automatically set with their associated metadata by db_object.  A typical
     * case of when this is used is when the metadata is manually set or forced
     * by the application, such as when copying fields from one table to another,
     * where the metadata needs to be preserved.
     *
     * @var array
     */
    public $metadata_field_override = array();

    /**
     * These keep track of various types of database relationships.
     *
     * An example of the format is:
     * $this->has_one_relationship['child_table'] = 'foreign_key';
     *
     * @var array
     */
    private $has_one_relationship = array();
    private $has_many_relationship = array();
    private $belongs_to_relationship = array();

    // This allows us to cache objects so we don't
    // waste a query every time.
    protected static $object_cache = array();

    // these values are defined by SQL to be text fields
    protected $text_types = array('varchar', 'char', 'enum', 'set', 'tinytext', 'text', 'mediumtext', 'longtext');

    // Set up the callback array
    protected $callbacks = array(
                                  'before_add'      => array(),
                                  'before_update'   => array(),
                                  'before_delete'   => array(),
                                  'before_save'     => array(),
                                  'after_save'      => array(),
                                  'after_delete'    => array(),
                                  'after_update'    => array(),
                                  'after_add'       => array()
                               );

    /**
     * PHP5-style constructor
     *
     * @param integer $id
     * @param string $table_name
     * @param $table_info
     * @param $attributes
     *
     * @return db_object
     */
    public function __construct($table_name, $id = NULL, $table_info = NULL, $attributes = NULL)
    {
        $this->modified_attributes = array();
        $this->null_instantiated = false;
        $this->table_name = $table_name;
        $this->attribute_content_types = array();
        $this->force_no_filtering = false;
        $this->metadata_fields = array();
        $this->metadata_field_override = array();

        if ((isset($table_info) && (is_array($table_info) || $table_info instanceof ArrayAccess) && (sizeof($table_info) > 0))) {
            //  Utilize the dry-instantiated $table_info rather than retrieving it from
            //  session or the database
            $this->table_info = $table_info;
        }
        else {
            /**
             * Check to see if a session is active
             */
            if (session_id()) {
                /**
                 * Check to see whether or not the table information is cached in the session
                 */
                if ((isset($_SESSION['table_column_cache'][$table_name]) && (is_array($_SESSION['table_column_cache'][$table_name]) || $_SESSION['table_column_cache'][$table_name] instanceof ArrayAccess) && (sizeof($_SESSION['table_column_cache'][$table_name]) > 0))) {
                    /**
                     * Retrieve the table information from the session cache (this saves a query)
                     */
                    $this->table_info = $_SESSION['table_column_cache'][$table_name];
                }
            }
        }

        if (!$this->table_info) {
            //  Retrieve the object table information
            if (!$table_info = $this->query('SHOW COLUMNS FROM `' . mysql_real_escape_string($this->table_name) . '`', 'Field')) {
                throw new Exception('Unable to retrieve object table info for table "' . $this->table_name . '"');
            }
            //  Assign the values to the table
            $this->table_info = $table_info;
        }

        //  Cache this table information for future use (see above for cache usage)
        $_SESSION['table_column_cache'][$table_name] = $this->table_info;

        //  Populate table & column information
        foreach ($this->table_info as $row) {
            $this->attributes[$row['Field']] = '';
            if ($row['Key'] == 'PRI') {
                if (strlen($this->primary_key_field) > 0) {
                    //  We've already found a primary key field, and db_object does not support
                    //  more than one right now.

                    //throw new Exception('db_object does not support multifield primary keys', 1);
                }
                //  We can kill two birds with one stone here, and get the primary key field, as well
                $this->primary_key_field = $row['Field'];
            }
            if ($row['Field'] == 'inserted_on') {
                $this->time_column_type = $row['Type'];
            }
            //  Check if it's a metadata field
            if (in_array($row['Field'], $this->possible_metadata_fields)) {
                //  Add this field to this db_object's metadata_fields list
                $this->metadata_fields[] = $row['Field'];
            }
        }

        //Activates default filter for ALL fields
        $this->filter_all_attributes('name');

        if (is_null($id)) {
            //  No value or NULL was passed for the $id
            if ((isset($attributes) && (is_array($attributes) || $attributes instanceof ArrayAccess) && (sizeof($attributes) > 0))) {
                //  Use the dry-instantiated $attributes value
                $this->attributes = $attributes;
                return true;
            }

            $this->null_instantiated = true;

            // Set default values
            foreach ($this->table_info as $row) {
                if (is_null($row['Default'])) {
                    $this->set_attribute($row['Field'], NULL, false, false, false);
                }
                else {
                    $this->set_attribute($row['Field'], $row['Default'], false, false, false);
                }
            }

            return true;
        } else {
            //  Attempt to retrieve a record
            if ($record = $this->query("SELECT * FROM `" . mysql_real_escape_string($this->table_name) . "` WHERE `" . $this->primary_key_field . "` = '" . mysql_real_escape_string($id) . "'")) {
                // Successfully retrieved record
                $this->attributes = $record[0];

                // Ensure that this object is no longer considered null_instantiated
                $this->null_instantiated = false;
                return true;
            }
            else {
                //$this->null_instantiated = true;
                //  Could not retrieve a record with this id
                throw new Exception('Unable to instantiate object of type "' . $table_name . '" with id: "' . $id . '"');
            }
        }
    }

    /*********************************************************************
     *
     * BASIC CRUD METHODS
     *
     ********************************************************************/

    /**
     * Add this object as a new record to the database
     *
     * @param boolean $force
     * @return boolean success
     */
    public function add($force = false)
    {
        $sd_id = $this->has_soft_deleted_entry();
        if ($sd_id && !$force) {
            return $this->undelete();
        }
        if (!$this->null_instantiated) {
            throw new Exception('Attempted to add already-existing record');
        }
        /**
         * Check for any metadata overrides relating to add/insert
         */
        if (in_array('inserted_on', $this->metadata_field_override) ||
            in_array('inserted_by', $this->metadata_field_override) ||
            in_array('inserted_ip', $this->metadata_field_override)) {
                $metadata_override = true;
            }
            else {
                $metadata_override = false;
            }
        //  We check if the record has either been modified (to prevent duplicate records) or if they want to force it anyway
        if ($this->modified() || $force) {
            /**
             * Skip any automatic metadata setting if override is true
             */
            if (!$metadata_override) {
                if ($this->is_acceptable_attribute('inserted_on')) {
                    $time_string = date('Y-m-d H:i:s');
                    $this->set_attribute('inserted_on', $time_string, false, true, false);
                }
                if ($this->is_acceptable_attribute('inserted_by') && function_exists('db_object_get_user_id')) {
                    if (false !== ($current_user_id = db_object_get_user_id())) {
                        if (is_numeric($current_user_id)) {
                            $this->set_attribute('inserted_by', $current_user_id, false, true, false);
                        }
                    }
                }
                if ($this->is_acceptable_attribute('inserted_ip')  && function_exists('db_object_get_user_ip')) {
                    $this->set_attribute('inserted_ip', db_object_get_user_ip(), false, true, false);
                }
            }

            $columns = array_keys($this->attributes);
            $values = array_values($this->attributes);

            $sql_string = 'INSERT INTO `'.mysql_real_escape_string($this->table_name).'`'."\n";
            if (is_array($columns) && sizeof($columns) > 0) {
                $num_columns = sizeof($columns);
                if (sizeof($values) != $num_columns) {
                    //  Whoops!  Someone passed the wrong number of values or columns
                    throw new Exception('Columns array and values array sizes do not match');
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
            for ($i = 0; $i < $num_columns; $i++) {
                $value = $values[$i];

                // Skip this column if the value is NULL
                if (is_null($value)) {
                    continue;
                }
                else {
                    if (get_magic_quotes_gpc()) {
                        $value = stripslashes($value);
                    }

                    $value_array[] = '"' . mysql_real_escape_string($value) . '"';
                }
            }

            //  Strip off the last, unnecessary comma
            $sql_string .= implode(',', $value_array);

            $sql_string .= ')';

            // Call the callbacks
            $this->execute_callbacks('before_save');
            $this->execute_callbacks('before_add');

            if ($this->query($sql_string)) {
                /**
                 * Retrieve the auto_incremented primary key
                 */
                $this->set_attribute($this->primary_key_field, mysql_insert_id(), false);
                /**
                 * Unset status variables
                 */
                $this->modified_attributes = array();
                $this->null_instantiated = false;

                // Call the callbacks
                $this->execute_callbacks('after_add');
                $this->execute_callbacks('after_save');

                return true;
            }
            else {
                throw new Exception('Could not insert new object: ' . mysql_error());
            }
        }
        else {
            throw new Exception('Unable to add unmodified record.  Set $force parameter to true to override');
        }
    }

    /**
     * Run an UPDATE query to bring the database in sync with the object's attributes
     *
     * @param boolean $force
     * @return boolean success
     */
    public function update($force = false)
    {
        if ($this->null_instantiated) {
            throw new Exception('Unable to update a null-instantiated record');
        }

        /**
         * Check for any metadata overrides relating to update
         */
        if (in_array('updated_on', $this->metadata_field_override) ||
            in_array('updated_by', $this->metadata_field_override) ||
            in_array('updated_ip', $this->metadata_field_override)) {
                $metadata_override = true;
            }
            else {
                $metadata_override = false;
            }
        if ($this->modified() || $force) {
            /**
             * Skip any automatic metadata setting if override is true
             */
            if (!$metadata_override) {
                if ($this->is_acceptable_attribute('updated_on')) {
                    $time_string = date('Y-m-d H:i:s');
                    $this->set_attribute('updated_on', $time_string, false, true, false);
                }
                if ($this->is_acceptable_attribute('updated_by') && function_exists('db_object_get_user_id')) {
                    if (false !== ($current_user_id = db_object_get_user_id())) {
                        if (is_numeric($current_user_id)) {
                            $this->set_attribute('updated_by', $current_user_id, false, true, false);
                        }
                    }
                }
                if ($this->is_acceptable_attribute('updated_ip') && function_exists('db_object_get_user_ip')) {
                    $this->set_attribute('updated_ip', db_object_get_user_ip(), false, true, false);
                }
            }

            $columns = array_keys($this->modified_attributes);
            $values = array_values($this->modified_attributes);

            $sql_string = 'UPDATE `'.$this->table_name.'` SET';
            if (is_array($columns) && sizeof($columns) > 0) {
                $num_columns = sizeof($columns);
                if (sizeof($values) != $num_columns) {
                    //  Whoops!  Someone passed the wrong number of values or columns
                    throw new Exception('Columns array and values array sizes do not match');
                }

                for ($i = 0; $i < $num_columns; $i++) {
                    $column = $columns[$i];
                    $value  = $values[$i];

                    if (get_magic_quotes_gpc()) {
                        $column = stripslashes($column);

                        if (!is_null($value)) {
                            $value  = stripslashes($value);
                        }
                    }

                    $name_value_pair = ' `' . mysql_real_escape_string($column) . '` = ';

                    if (is_null($value)) {
                        $name_value_pair .= 'NULL, ';
                    }
                    else {
                        $name_value_pair .= '"' . mysql_real_escape_string($value) . '", ';
                    }

                    $sql_string .= $name_value_pair;
                }

                //  trim off the last remaining comma
                $sql_string = substr($sql_string, 0, -2);
            }
            else {
                if (is_string($columns) && strlen($columns) > 0) {
                    //  $columns is a string and should be treated as one value
                    if (!is_string($values)) {
                        //  If $columns is a string, then $values must also be a string
                        throw new Exception('Values must be scalar value when columns is scalar');
                        //trigger_error('Values must be scalar value when columns is scalar', E_USER_ERROR);
                    }
                }
                else {
                    //  Well, what the heck is $columns, then, if it's not a string or array?!
                    throw new Exception('Invalid value/type for columns.  Type must be array or string.');
                    //trigger_error('Invalid value/type for columns.  Type must be array or string.', E_USER_ERROR);
                }
            }

            //  Tack on the "where" clause
            $sql_string .= " WHERE `" . $this->primary_key_field . "` = '" . $this->get_attribute($this->primary_key_field) . "'";

            // Call the callbacks
            $this->execute_callbacks('before_save');
            $this->execute_callbacks('before_update');

            if ($this->query($sql_string)) {
                $this->modified_attributes = array();

                // Call the callbacks
                $this->execute_callbacks('after_update');
                $this->execute_callbacks('after_save');

                return true;
            }
            else {
                throw new Exception('Could not update attributes: ' . mysql_error());
            }
        }
        else {
            throw new Exception('Unable to update unmodified record.  Set $force parameter to true to override');
        }
    }

    /**
     * Delete the record in the database to which this object belongs
     *
     * @param boolean $force
     * @param boolean $hard Performs a hard delete instead of a soft delete.
     * @return boolean success
     */
    public function delete($force = false, $hard = false)
    {
        if ($this->null_instantiated) {
            throw new Exception('Cannot delete a null-instantiated object');
        }

        if ($this->modified() && !$force) {
            throw new Exception('Unable to delete modified record in object of type ' . $this->table_name . '.  Set $force parameter to true to overrride');
        }

        $this->remove_from_cache($this->table_name, $this->get_id());

        // Call the callbacks
        $this->execute_callbacks('before_delete');

        /**
         * Delete any declared child relationships
         */
        $related_tables = $this->get_db_relationship_tables();

        foreach ($related_tables as $related_table) {
            $relationship_type = $this->get_db_relationship_type($related_table);

            switch ($relationship_type) {
                case 'has_one':
                    if ($related_db_object = $this->get_child_object($related_table))
                        $related_db_object->delete($force, $hard);
                    break;
                case 'has_many':
                    if ($related_db_recordset = $this->get_child_object($related_table)) {
                        foreach ($related_db_recordset as $related_db_object) {
                            $related_db_object->delete($force, $hard);
                        }
                    }
                    break;
                case 'belongs_to':
                    break;
            }
        }

        if ($hard !== true && in_array('deleted', $this->metadata_fields)) {
            $return_value = $this->set_attribute('deleted', '1', $force_update=true, $check_acceptable_attribute=true, $metadata_override=false);

            // Call the callbacks
            $this->execute_callbacks('after_delete');

            return $return_value;
        }
        else {
            $sql_delete_string = "DELETE FROM `" . mysql_real_escape_string($this->table_name) . "` WHERE `" . $this->primary_key_field . "` = '" . mysql_real_escape_string($this->get_attribute($this->primary_key_field)) . "' LIMIT 1";
            if ($this->query($sql_delete_string)) {
                // Call the callbacks
                $this->execute_callbacks('after_delete');

                $this->attributes = NULL;
                return true;
            }
            else {
                throw new Exception('Could not delete record: ' . mysql_error());
            }
        }
    }

    /**
     * Dump SQL queries based on records from a backup database - based on logic in 'delete' method
     *  - For now we're just echoing the queries to be executed.
     * @return void
     */
    public function restore()
    {
        if ($this->null_instantiated) {
            throw new Exception('Cannot restore a null-instantiated object');
        }

        if ($this->modified()) {
            throw new Exception('Unable to restore a modified record in object of type ' . $this->table_name);
        }

        /**
         * Restore any declared child relationships
         */
        $related_tables = $this->get_db_relationship_tables();

        foreach ($related_tables as $related_table) {
            $relationship_type = $this->get_db_relationship_type($related_table);

            switch ($relationship_type) {
                case 'has_one':
                    if ($related_db_object = $this->get_child_object($related_table))
                        $related_db_object->restore();
                    break;
                case 'has_many':
                    if ($related_db_recordset = $this->get_child_object($related_table)) {
                        foreach ($related_db_recordset as $related_db_object) {
                            $related_db_object->restore();
                        }
                    }
                    break;
                case 'belongs_to':
                    break;
            }
        }

        $restore_sql = "SELECT * FROM `" . mysql_real_escape_string($this->table_name) . "`
            WHERE `" . $this->primary_key_field . "` = '" . $this->get_id() . "'";
        $result = current($this->query($restore_sql));
        $set_clause = array();
        foreach($result as $column_name=>$column_value) {
            $set_clause[] = '`'.mysql_real_escape_string($column_name).'`=\''.mysql_real_escape_string($column_value).'\'';
        }
        $set_sql = implode(',',$set_clause);

        // If is NOT soft delete
        if (!isset($result['deleted'])) {
            // We're doing ON DUPLICATE KEY UPDATE on the off chance that somehow delete doesn't delete, but restore attempts to restore
            $sql_out = 'INSERT INTO `'.mysql_real_escape_string($this->table_name).'` SET '.$set_sql.' ON DUPLICATE KEY UPDATE '.$set_sql.';';
        }
        else {
            $where_clause = '`'.mysql_real_escape_string($this->primary_key_field).'`="'.$this->get_id().'"';
            $sql_out = 'UPDATE `'.mysql_real_escape_string($this->table_name).'` SET '.$set_sql.' WHERE '.$where_clause.';';
        }
        echo $sql_out.PHP_EOL;
    }

    /*********************************************************************
     *
     * GETTER METHODS
     *
     ********************************************************************/

    /**
     * Retrieve an associative array with empty values whose keys are valid attributes
     * for this object
     *
     * @return array $acceptable_attributes
     */
    public function get_acceptable_attributes()
    {
        $acceptable_attributes = array_flip(array_keys($this->attributes));
        foreach ($acceptable_attributes as $name => $value) {
            $acceptable_attributes[$name] = '';
        }
        return $acceptable_attributes;
    }

    /**
     * Retrieve a single value from the object's attributes
     *
     * @param string $name
     * @return mixed attribute value or false upon not finding an attribute of the specified name
     */
    public function get_attribute($name)
    {
        if (array_key_exists($name, $this->attributes)) {
            return $this->attributes[$name];
        }
        else {
            throw new Exception('Invalid attribute specified: "'.$name.'" in object of type "'.$this->table_name.'"', 1);
        }
    }

    /**
     * Returns the id for this object
     *
     * Since primary key fields are not necessarily uniformly one name, this function abstracts the process
     * by allowing a simple method to retrieve the primary key/id with one call.  Examples where this would be
     * useful include calls to student & staff objects, where the primary key fields are/were "student_id" &
     * "staff_id", respectively.
     *
     * @return integer primary key/id
     */
    public function get_id()
    {
        if ($this->null_instantiated) {
             // There is no primary key if the object is null-instantiated
            return false;
        }
        else {
            return $this->get_attribute($this->primary_key_field);
        }
    }

    /**
     * Retrieves all ids for this object and returns them in a simple array
     *
     * @return mixed all ideas or false upon failure
     */
    public function get_all_ids()
    {
        $sql = "
        SELECT
        `$this->primary_key_field`
        FROM
        `$this->table_name`
        ";
        $result = $this->query($sql);
        if (is_array($result) && sizeof($result) > 0) {
            $ids = array();
            foreach ($result as $row) {
                $ids[] = $row[$this->primary_key_field];
            }
            return $ids;
        }
        else {
            return false;
        }
    }

    public function get_primary_key_field()
    {
        return $this->primary_key_field;
    }

    /**
     * Return all attributes for the object
     *
     * @return array $attributes
     */
    public function get_attributes()
    {
        return $this->attributes;
    }

    public function modified()
    {
        if (empty($this->modified_attributes))
            return false;
        else
            return true;
    }

    public function metadata_fields()
    {
        return $this->metadata_fields;
    }

    public function table_info()
    {
        return $this->table_info;
    }

    /*********************************************************************
     *
     * SETTER METHODS
     *
     ********************************************************************/

    /**
     * Set the value of one attribute, and optionally, update the object in the database
     *
     * @param string $name
     * @param string $value
     * @param boolean $force_update
     * @param boolean $check_acceptable_attribute
     * @param boolean $metadata_override
     * @return boolean success
     */
    public function set_attribute($name, $value = NULL, $force_update = true, $check_acceptable_attribute = true, $metadata_override = true)
    {
        if ($check_acceptable_attribute) {
            if (!array_key_exists($name, $this->attributes)) {
                throw new Exception('Attempted to set invalid attribute: "' . $name . '"');
            }
        }

        $this->remove_from_cache($this->table_name, $this->get_id());

        /**
         * Check if a metadata field is being set
         */
        if ($metadata_override && in_array($name, $this->metadata_fields) && !in_array($name, $this->metadata_field_override)) {
            /**
             * Add this field to the metadata_field_override list
             */
            $this->metadata_field_override[] = $name;
        }

        // ensure value is trimmed for text values
        if (in_array($this->get_attribute_type($name), $this->text_types)) {
            $value = trim($value);
        }

        if ($this->get_attribute($name) === $value) {
            // Unnecessary to update a value to itself
        }
        else {
            //Check to see if there's a content type set that would lead to a filter choice
            if (isset($this->attribute_content_types[$name]) and $this->attribute_content_types[$name] != 'raw') {
                $filtered_value = $this->filter_attribute($name, $value);
            }
            //If this attribute has no specified content type or it is set as raw, don't filter it.
            else {
                $filtered_value = $value;
            }

            if ($this->force_no_filtering == true) {
                $this->attributes[$name] = $value;
                $this->modified_attributes[$name] = $value;
            }
            else {
                $this->attributes[$name] = $filtered_value;
                $this->modified_attributes[$name] = $filtered_value;
            }
        }

        /**
         * Don't run a forced update if the object was null-instantiated, and only run an
         * update if the data has actually been modified
         */
        if ($force_update && !$this->null_instantiated && $this->modified()) {
            if ($this->update()) {
                $this->modified_attributes = array();
                return true;
            }
            else {
                throw new Exception('Could not execute forced update for attribute "' . $name . '"');
            }
        }
        else {
            //  We've successfully completed everything we need to by this point
            return true;
        }
    }

    /**
     * Sets several attributes at once
     *
     * @param array $attributes
     * @param boolean $force_update
     * @return boolean success
     */
    public function set_attributes($attributes, $force_update = true)
    {
        foreach ($attributes as $name => $value) {
            if (!$this->is_acceptable_attribute($name)) {
                throw new Exception('Attempted to set invalid attribute "' . $name . '"');
            }
        }
        foreach ($attributes as $name => $value) {
            if (!$this->set_attribute($name, $value, false, false)) {
                throw new Exception('Error encountered attempting to set attribute ' . $name);
            }
        }
        /**
         * Don't run a forced update if the object was null-instantiated, and only run an
         * update if the data has actually been modified
         */
        if ($force_update && !$this->null_instantiated && $this->modified()) {
            if ($this->update()) {
                $this->modified_attributes = array();
                return true;
            }
            else {
                throw new Exception('Could not execute forced update for array of attributes');
            }
        }
        else {
            //  We've successfully completed everything we need to by this point
            return true;
        }
    }

    public function set_attributes_if_default($attributes, $force_update=true) {
        foreach ($attributes as $name => $value) {
            // If the current attribute is not the default for this field (this field has been set explicitly), remove it from the attributes to be set
            if ($this->table_info[$name]['Default'] != $this->$name) {
                unset($attributes[$name]);
            }
        }

        // Set the remaining attributes
        return $this->set_attributes($attributes, $force_update);
    }
    /*********************************************************************
     *
     * METHODS THAT DETERMINE ACCEPTABLE ACTIONS
     *
     ********************************************************************/

    /**
     * Tests whether a named attribute exists for this object, and return
     * true if yes, false if no.
     *
     * By default, metadata will be acceptable. This can be overridden with the
     * $allow_metadata parameter.
     *
     * @param string $name
     * @param bool $allow_metadata
     * @return boolean exists
     */
    public function is_acceptable_attribute($name, $allow_metadata=true)
    {
        if (!array_key_exists($name, $this->attributes)) {
            return false;
        }

        if ($allow_metadata != true and in_array($name, $this->metadata_fields)) {
            return false;
        }

        return true;
    }

    /**
     * Checks the various relationship arrays for the table name.
     *
     * @return bool
     * @author Bryce Thornton
     **/
    protected function is_db_relationship_set($table_name)
    {
        if (isset($this->has_one_relationship[$table_name]) ||
            isset($this->has_many_relationship[$table_name]) ||
            isset($this->belongs_to_relationship[$table_name]))
            return true;
        else
            return false;
    }

    /*********************************************************************
     *
     * METHODS TO RETRIEVE RELATED RECORDS VIA DB_OBJECT OR DB_RECORDSET
     *
     ********************************************************************/

    /**
     * Returns a db_object if $attribute "belongs_to" $table_name in this object
     *
     * @param string $attribute
     * @param string $table_name
     * @param int $attribute_value
     *
     * @author Bryce Thornton
     * @return db_object
     **/
    public function get_parent_object($attribute, $table_name=NULL, $attribute_value=NULL)
    {
        if (! $table_name) {
            // See if it's defined explicitly in the class
            if (!$table_name = array_search($attribute, $this->belongs_to_relationship)) {
                $table_name = $this->determine_table_name_from_foreign_key($attribute);
            }
        }

        if (! $attribute_value) {
            $attribute_value = $this->get_attribute($attribute);
        }

        // Make sure we have some value to work with.
        if (! $attribute_value) {
            return false;
        }

        if ($table_name) {
            return $this->retrieve_from_cache($table_name, $attribute_value);
        }
        else {
            throw new Exception("Could not determine a proper table name from the attribute: $attribute.");
        }
    }

    /**
     * Returns a db_object for "has_one" relationships.
     * Returns a db_recordset for "has_many" relationships.
     *
     * @param string $table_name
     * @param string $foreign_key
     * @param array $constraints
     * @param string $force_object_type
     *
     * @author Bryce Thornton
     * @return db_object/db_recordset
     **/
    public function get_child_object($table_name, $foreign_key=NULL, $constraints=NULL, $force_object_type=NULL)
    {
        $foreign_key = $this->verify_foreign_key($foreign_key);

        // Figure out what type of object to return
        if ($this->is_db_relationship_set($table_name)) {
            $db_relationship_type  = $this->get_db_relationship_type($table_name);
            $db_relationship_field = $this->get_db_relationship_field($table_name);

            // See if the foreign key is defined explicitly in the class
            if (isset($this->{$db_relationship_type.'_relationship'}[$table_name])) {
                $foreign_key = $this->{$db_relationship_type.'_relationship'}[$table_name];
            }

            switch ($db_relationship_type) {
                case 'has_one':
                    if ($force_object_type === NULL)
                        $force_object_type = 'db_object';
                    if ($foreign_key == NULL)
                        $foreign_key = $db_relationship_field;
                    break;
                case 'has_many':
                    if ($force_object_type === NULL)
                        $force_object_type = 'db_recordset';
                    if ($foreign_key == NULL)
                        $foreign_key = $db_relationship_field;
                    break;
                default:
                    break;
            }
        }

        // Figure out the primary key of the table.
        $db_object = new db_object ($table_name);
        $primary_key = $db_object->get_primary_key_field();
        unset($db_object);

        // Query for the record(s)
        $wheres = array();
        $wheres[] = "$foreign_key='".$this->get_id()."'";

        // If contraints are passed in as a parameter
        if ($constraints) {
            // Allow constraints to be a string
            if (!is_array($constraints))
                $constraints = array($constraints);
            $wheres = array_merge($wheres, $constraints);
        }

        $sql = $this->get_sql($table_name, $primary_key, $wheres);
        $result = $this->query($sql);

        $num_results = count($result);

        $ids = array();

        if (is_array($result) && $num_results > 0) {
        // Get the ids to instantiate the object with
            foreach($result as $single_result) {
                $ids[] = $single_result[$primary_key];
            }
        }

        if (empty($ids))
            return false;

        if ($force_object_type == 'db_object') {
            // Allow the db_object type to be forced
            return $this->retrieve_from_cache($table_name, $ids[0]);
        }
        elseif ($force_object_type == 'db_recordset') {
            // Allow the db_recordset type to be forced
            return $this->auto_discover_db_recordset($table_name, $ids);
        }
        elseif ($num_results == 1) {
            // If there is only one return a db_object
            return $this->auto_discover_db_object($table_name, $ids[0]);
        }
        elseif ($num_results > 1) {
            // If there is more than one return a db_recordset
            return $this->auto_discover_db_recordset($table_name, $ids);
        }
    }

    /**
     * Figures out if an extended db_object exists for the $attribute.
     * If so, it'll return that.  If not, it'll return a generic db_object.
     *
     * @param string $table_name
     * @param int $attribute_value
     *
     * @author Bryce Thornton
     * @return db_object
     */
    private function auto_discover_db_object($table_name, $attribute_value=NULL)
    {
        // Figure out the attribute to instantiate the object with
        // If an attribute was passed they are looking for a parent.
        // If not, they are looking for a child and we will use this object's id
        if ($attribute_value != NULL) {
            $attribute_to_use = $attribute_value;
        }
        else {
            $attribute_to_use = $this->get_id();
        }

        // We check for this in the if statement below
        $singular_table_name = '';
        if (substr($table_name, -1) == 's') {
            $singular_table_name = substr($table_name, 0, strlen($table_name)-1);
        }

        // Create the db_object
        if (class_exists($table_name) && is_subclass_of($table_name, 'db_object')) {
            //  A class name matching the table name was autodiscovered, so return an instance of this class
            //  rather than default db_object class
            return new $table_name($attribute_to_use);
        }
        elseif ($singular_table_name != '' && class_exists($singular_table_name) && is_subclass_of($singular_table_name, 'db_object')) {
            // Sometimes it's nice to name a table pluraly, but use a singular object name.
            // This allows for that.  Example: table: grants db_object: grant
            return new $singular_table_name($attribute_to_use);
        }
        elseif ($db_object = new db_object($table_name, $attribute_to_use)) {
            return $db_object;
        }
        else {
            throw new Exception("Could not create a db_object for the attribute: $attribute_to_use.");
        }
    }

    /**
     * This attempts to create a recordset.  If it can find a derived class of db_recordset it will use it.
     *
     * @param string $table_name
     * @param array $ids
     *
     * @author Bryce Thornton
     * @return db_recordset
     */
    private function auto_discover_db_recordset($table_name, $ids=NULL)
    {
        $recordset_name = $table_name.'_recordset';

        if (class_exists($recordset_name) and is_subclass_of($recordset_name, 'db_recordset')) {
            return new $recordset_name($ids);
        }
        elseif ($db_recordset = new db_recordset($table_name, $ids)) {
            return $db_recordset;
        }
        else {
            throw new Exception("Could not create a db_recordset for the table: $table_name.");
        }
    }

    /**
     * This allows us to search for a record without having to use sql or other methods.
     * The parameter is a key/value array with the key being the field to search for and value is the value.
     * If a record is found this object will be constructed with the record.
     *
     * @param array $columns
     *
     * @author Bryce Thornton
     * @return bool
     */
    public function find($columns)
    {
        // We can only do this on null instantiated objects
        if (!$this->null_instantiated) {
            throw new Exception("The method: 'find' cannot be called on an existing object, it must be null instantiated.");
        }

        foreach ($columns as $field_name => $field_value) {
            // Make sure it's a legit attribute
            if (!$this->is_acceptable_attribute($field_name)) {
                throw new Exception("The method: 'find' tries to find by a non-existent attribute: '$field_name'.");
            }
        }

        // Try to find the record
        $found_id = $this->get_single_field_value($this->table_name, $this->primary_key_field, $columns);

        if ($found_id) {
            // If this is an extended object, then we pass the id.
            if (is_subclass_of($this, 'db_object')) {
                $this->__construct($found_id);
                return true;
            }
            else {
                $this->__construct($this->table_name, $found_id, $this->table_info);
                return true;
            }
        }
        else {
            return false;
        }
    }

    /*********************************************************************
     *
     * METHODS TO HELP DETERMINE RELATED TABLE NAMES/KEYS
     *
     ********************************************************************/

    /**
     * This just forms a foreign key if nothing is passed.
     * It saves a few lines of code here and there.
     *
     * @param string $foreign_key
     *
     * @return string
     * @author Bryce Thornton
     **/
    private function verify_foreign_key($foreign_key=NULL)
    {
        if ($foreign_key == NULL)
            $foreign_key = $this->table_name . '_id';

        return $foreign_key;
    }

    /**
     * This is very similar to the function above.
     * This one takes a table name and makes sure the default foreign key
     * is in this table.  If so, return the name of the attribute.  If not,
     * return false.
     *
     * @param string $table_name
     *
     * @return string/bool
     * @author Bryce Thornton
     **/
    private function determine_foreign_key_from_table_name($table_name)
    {
        $attribute = $table_name . '_id';
        if ($this->is_acceptable_attribute($attribute))
            return $attribute;
        else
            return false;
    }

    /**
     * This just strips the '_id' off the end of the parameter and returns it.
     *
     * @param string $attribute
     *
     * @return string/bool
     * @author Bryce Thornton
     **/
    private function determine_table_name_from_foreign_key($attribute)
    {
        if (substr($attribute, -3) == '_id') {
            $id_position = strpos($attribute, '_id');
            $table_name = substr($attribute, 0, $id_position);

            return $table_name;
        }
        else
            return false;

    }

    /*********************************************************************
     *
     * DATABASE RELATIONSHIP METHODS
     *
     ********************************************************************/

    /**
     * This returns the type of relationship the passed table has to the current one.
     * This only works if we define them in the derived class.
     *
     * @param string $table_name
     *
     * @return string/bool
     * @author Bryce Thornton
     **/
    protected function get_db_relationship_type($table_name)
    {
        if ($this->is_db_relationship_set($table_name)) {
            if (isset($this->has_one_relationship[$table_name]))
                return 'has_one';
            elseif (isset($this->has_many_relationship[$table_name]))
                return 'has_many';
            elseif (isset($this->belongs_to_relationship[$table_name]))
                return 'belongs_to';
            else
                return false;
        }
        else {
            throw new Exception("The table '$table_name' is not in the db_relationship declaration.");
        }
    }

    /**
     * This returns the field that is used to relate the passed table has to the current one.
     * This only works if we define them in the derived class.
     *
     * @param string $table_name
     *
     * @return string/bool
     * @author Bryce Thornton
     **/
    protected function get_db_relationship_field($table_name)
    {
        if ($this->is_db_relationship_set($table_name)) {
            $relationship_type = $this->get_db_relationship_type($table_name);
            $array_name = $relationship_type . '_relationship';
            $db_relationship_field = $this->{$array_name}[$table_name];
            return $db_relationship_field;
        }
        else {
            throw new Exception("The table '$table_name' is not in the db_relationship declaration.");
        }
    }

    /**
     * This just returns all the tables that have been explicitly declared related to the current one.
     *
     * @return array
     * @author Bryce Thornton
     **/
    protected function get_db_relationship_tables()
    {
        $db_relationship_tables = array();
        $db_relationship_tables = array_keys($this->has_one_relationship);
        $db_relationship_tables = array_merge($db_relationship_tables, array_keys($this->has_many_relationship));
        $db_relationship_tables = array_merge($db_relationship_tables, array_keys($this->belongs_to_relationship));

        return $db_relationship_tables;
    }

    /**
     * This is called in the constructor for related tables that will only ever have 0 or 1
     * related records.
     *
     * @param string $table_name
     * @param string $foreign_key
     *
     * @return void
     * @author Bryce Thornton
     **/
    protected function has_one($table_name, $foreign_key=NULL)
    {
        $foreign_key = $this->verify_foreign_key($foreign_key);
        $this->has_one_relationship[$table_name] = $foreign_key;
    }

    /**
     * This is called in the constructor for related tables that have 0 to ?? related records.
     *
     * @param string $table_name
     * @param string $foreign_key
     *
     * @return void
     * @author Bryce Thornton
     **/
    protected function has_many($table_name, $foreign_key=NULL)
    {
        $foreign_key = $this->verify_foreign_key($foreign_key);
        $this->has_many_relationship[$table_name] = $foreign_key;
    }

    /**
     * This is called in the constructor for related tables that are "parents" of the current one.
     *
     * @param string $table_name
     * @param string $foreign_key
     *
     * @return void
     * @author Bryce Thornton
     **/
    protected function belongs_to($table_name, $foreign_key=NULL)
    {
        if ($foreign_key === NULL) {
            $foreign_key = $this->determine_foreign_key_from_table_name($table_name);
        }

        $this->belongs_to_relationship[$table_name] = $foreign_key;
    }

    private function add_to_cache($table_name, $id)
    {
        // First, make sure we store only one record per table in the cache.
        // If this record is already there, then we don't need to do anything.
        if (!isset(self::$object_cache[$table_name][$id])) {
            unset(self::$object_cache[$table_name]);
            self::$object_cache[$table_name][$id] = $this->auto_discover_db_object($table_name, $id);
        }
    }

    private function remove_from_cache($table_name, $id)
    {
        if (isset(self::$object_cache[$table_name][$id])) {
            unset(self::$object_cache[$table_name][$id]);
        }
    }

    private function retrieve_from_cache($table_name, $id)
    {
        if (!isset(self::$object_cache[$table_name][$id])) {
            $this->add_to_cache($table_name, $id);
        }

        return self::$object_cache[$table_name][$id];
    }

    /*********************************************************************
     *
     * Magic Methods
     *
     ********************************************************************/

    public function __get($name)
    {
        return $this->get_attribute($name);
    }

    public function __set($name, $value = NULL)
    {
        return $this->set_attribute($name, $value);
    }

    /**
     * This allows methods to be called that don't explicitly exist
     *
     * @author Bryce Thornton
     **/
    public function __call($method, $arguments)
    {
        // Figure out if the method name refers to a related table.
        $related_tables = $this->get_db_relationship_tables();

        // First, allow for the "find_by_attribute" method.
        if (substr($method, 0, 7) == 'find_by') {
            // Should be after the "find_by_"
            $field_to_find_by = substr($method, 8);
            return $this->find(array($field_to_find_by => $arguments[0]));
        }
        elseif (in_array($method, $related_tables)) {
            // Grab the arguments.  In this case $arguments[0] is $constraints
            if (isset($arguments[0]) && ! empty($arguments[0]))
                $constraints = $arguments[0];
            else
                $constraints = NULL;

            // If so, figure out the type of relationship and make sure we get the appropriate object.
            $relationship_type = $this->get_db_relationship_type($method);

            switch ($relationship_type) {
                case 'has_one':
                case 'has_many':
                    return $this->get_child_object($method, NULL, $constraints);
                    break;
                case 'belongs_to':
                    $relationship_field = $this->get_db_relationship_field($method);
                    return $this->get_parent_object($relationship_field, $method, $this->get_attribute($relationship_field));
                    break;
            }
        }
        // Allow a parent table even if it's not explicitly defined.
        // The foriegn key in this table must be named "parent_table_id"
        elseif ($relationship_field = $this->determine_foreign_key_from_table_name($method)) {
            return $this->get_parent_object($relationship_field, $method, $this->get_attribute($relationship_field));
        }
        // Allow a child table even if it's not explicitly defined.
        // The foreign key must be named "child_table_id"
        elseif ($child_object = $this->get_child_object($method)) {
            return $child_object;
        }
        else {
            throw new Exception("The method: '$method' does not exist in db_object.");
        }
    }

     /*
     * Since magic isset maps to empty and isset, we have to choose our funcitonality.
     * We've chosen to implement empty
     *
     * @author John Colvin
     */
    public function __isset($name) {
        $attribute = $this->$name;

        if (empty($attribute)) {
            return false;
        }
        else {
            return true;
        }
    }

    /**
     * Creates a duplicate of the current object
     *
     * By default, only the object itself is duplicated. This can be overridden for
     * each type of relationship.
     *
     * This function returns the primary key of the newly created object.
     *
     * @param bool $has_one
     * @param bool $has_many
     * @return int
     * @author Nick Whitt
     */
    public function duplicate_object($has_one=false, $has_many=false)
    {
        // sanity check
        if ($this->null_instantiated === true or $this->modified()) {
            throw new Exception('Unable to duplicate an unstable object');
        }


        // create an array of values to not duplicate
        $not_to_duplicate = array();

        $not_to_duplicate[] = $this->primary_key_field;

        foreach ($this->metadata_fields as $field) {
            $not_to_duplicate[] = $field;
        }


        // duplicate object
        $object = new db_object($this->table_name);

        foreach ($this->attributes as $attribute => $value) {
            if (in_array($attribute, $not_to_duplicate)) {
                continue;
            }

            $object->set_attribute($attribute, $value);
        }

        if (!$object->add()) {
            return false;
        }


        // duplicate has_one_relationship types
        if ($has_one === true) {
            foreach ($this->has_one_relationship as $table => $link) {
                $old_relationship = $this->$table();

                if (is_object($old_relationship)) {
                    if (!$id = $old_relationship->duplicate_object()) {
                        return false;
                    }

                    $new_relationship = new db_object($table, $id);
                    $new_relationship->set_attribute($link, $object->get_id());
                }
            }
        }


        // duplicate has_many_relationship types
        if ($has_many === true) {
            foreach ($this->has_many_relationship as $table => $link) {
                foreach ($this->$table() as $old_relationship) {
                    if (is_object($old_relationship)) {
                        if (!$id = $old_relationship->duplicate_object()) {
                            return false;
                        }

                        $new_relationship = new db_object($table, $id);
                        $new_relationship->set_attribute($link, $object->get_id());
                    }
                }
            }
        }


        // return the ID of our newly created object
        return $object->get_id();
    }
    /**
     * Checks if the record has a soft-deleted entry
     *
     * @return mixed false or the ID of the soft-deleted entry.
     */
    public function has_soft_deleted_entry() {
        if (in_array('deleted', $this->metadata_fields)) {
            $wheres = array();
            foreach ($this->attributes as $attribute => $value) {
                if (!in_array($attribute, $this->metadata_fields) && $attribute != 'id') {
                    $wheres[] = "`$attribute` = '$value'";
                }
            }
            $where = implode(' AND ', $wheres);
            $sql = 'SELECT * FROM `' . $this->table_name . '` WHERE ' . $where  . ' AND `deleted` = true';
            if ($entry = $this->query($sql)) {
                //@todo should this return the first matching record's id??  The oldest??  The most recent??
                if (is_array($entry[0])) {
                    return $entry[0]['id'];
                }
                else {
                    return $entry['id'];
                }
            }
        }
        return false;
    }
    /**
     * Undeletes a matching entry that has been soft deleted.
     *
     * @return bool on success or failure.
     * @author Brett Profitt
     */
    public function undelete() {
        if ($sd_id = $this->has_soft_deleted_entry()) {
            $sd_obj = new db_object($this->table_name, $sd_id);

            $related_tables = $this->get_db_relationship_tables();
            foreach ($related_tables as $related_table) {
                $relationship_type = $this->get_db_relationship_type($related_table);

                switch ($relationship_type) {
                    case 'has_one':
                        if ($related_db_object = $this->get_child_object($related_table))
                            $related_db_object->undelete();
                        break;
                    case 'has_many':
                        if ($related_db_recordset = $this->get_child_object($related_table)) {
                            foreach ($related_db_recordset as $related_db_object) {
                                $related_db_object->undelete();
                            }
                        }
                        break;
                    case 'belongs_to':
                        break;
                }
            }
            $sd_obj->set_attribute('deleted', '0', $force_update=true, $check_acceptable_attribute=true, $metadata_override=false);

            // return the correct type of object.
            if (get_class($this) == 'db_object') {
                $this->__construct($sd_obj->table_name, $sd_obj->get_id());
            }
            else {
                $this->__construct($sd_obj->get_id());
            }

            return true;
        }
        return false;
    }

    /**
     * Determines the SQL type of the given attribute
     *
     * The type returned by DESCRIBE TABLE includes precision and specifications.
     * i.e. INT will be displayed as INT(11) UNSIGNED. This method will remove the
     * additional information and return only the base type.
     *
     * @param str $attribute
     * @param bool $complete
     * @return str
     * @author Nick Whitt
     */
    public function get_attribute_type($attribute, $complete=false)
    {
        if (!$this->is_acceptable_attribute($attribute)) {
            throw new Exception('Requested type of invalid attribute: "' . $attribute . '"');
        }

        $types = explode('(', $this->table_info[$attribute]['Type']);
        if ($complete == false) {
            return $types[0];
        }
        else {
            return $this->table_info[$attribute]['Type'];
        }
    }

    /**
     * Returns the inserter as a staff object
     *
     * @param void
     * @return staff
     * @author Nick Whitt
     */
    public function get_inserter_object()
    {
        // check meta field exists
        if (!in_array('inserted_by', $this->metadata_fields)) {
            return false;
        }

        // ensure valid foreign key
        if (!$this->get_single_field_value(DB_OBJECT_USER_TABLE, 'id', array('id' => $this->inserted_by))) {
            return false;
        }

        return $this->get_parent_object('inserted_by', DB_OBJECT_USER_TABLE);
    }

    /**
     * Returns the updater as a staff object
     *
     * @param void
     * @return staff
     * @author Nick Whitt
     */
    public function get_updater_object()
    {
        if (!in_array('updated_by', $this->metadata_fields)) {
            return false;
        }

        if (!$this->get_single_field_value(DB_OBJECT_USER_TABLE, 'id', array('id' => $this->updated_by))) {
            return false;
        }

        return $this->get_parent_object('updated_by', DB_OBJECT_USER_TABLE);
    }

    /**
     * Returns the most recent editor as a staff object.
     *
     * Attempts to first return the updater object, but defaults to inserter when
     * updater doesn't exist.
     *
     * @param void
     * @return staff
     * @author Nick Whitt
     */
    public function get_editor_object()
    {
        // check updater first
        if (in_array('updated_by', $this->metadata_fields) and $this->updated_by) {
            return $this->get_updater_object();
        }

        // use inserter
        return $this->get_inserter_object();
    }

    /**
     * Returns the date of the last edit
     *
     * As with get_editor_object(), attempts to first return the updated_on value,
     * falling back to inserted_on when not valid. The format of the date returned
     * is controlled with the given parameter.
     *
     * @param str
     * @return str
     * @author Nick Whitt
     */
    public function get_editor_time($format='Y-m-d')
    {
        if (in_array('updated_on', $this->metadata_fields) and ($this->updated_on == date('Y-m-d H:i:s', strtotime($this->updated_on)))) {
            return date($format, strtotime($this->updated_on));
        }
        elseif (in_array('inserted_on', $this->metadata_fields) and ($this->inserted_on == date('Y-m-d H:i:s', strtotime($this->inserted_on)))) {
            return date($format, strtotime($this->inserted_on));
        }

        return false;
    }

    /******************************************************************************
    *
    * METHODS FOR FILTERING INPUT
    *
    ******************************************************************************/

    /**
     * Updates the attribute/content type array, which is used to determine which data filters
     * to apply to the attribute before db insertions. If a null content type is passed,
     * db_object will attempt to determine one based on the table info.
     *
     * @param str $attribute
     * @param str $content_type
     * @return bool Association successful?
     * @author David Prater
     */
    public function filter_attribute_as($attribute, $content_type=null) {
    //If null was passed, automatically determine the best content type
        if ($content_type == null) {
            $this->attribute_content_types[$attribute] = $this->get_content_type_by_sql_type($attribute);
            return true;
        }
        //Else see if a valid type was entered
        elseif ($this->get_valid_filter($content_type)) {
            $this->attribute_content_types[$attribute] = $content_type;
            return true;
        }
        else return false;
    }

    /**
     * Assigns content types to each attribute of the db_object. Any attribute with
     * a content type will be filtered. If a null content type is passed, db_object will attempt to
     * determine one based on the table info.
     *
     * @param str $content_type
     * @return bool
     * @author David Prater
     */
    public function filter_all_attributes($content_type = null) {
        if ($content_type == null) {
            foreach($this->attributes as $attribute => $value) {
                $this->attribute_content_types[$attribute] = $this->get_content_type_by_sql_type($attribute);
            }
            return true;
        }
        elseif ($this->get_valid_filter($content_type)) {
            foreach($this->attributes as $attribute => $value) {
                $this->attribute_content_types[$attribute] = $content_type;
            }
            return true;
        }
        else return false;
    }

    public function force_no_filtering($bool) {
        $this->force_no_filtering = $bool;
    }

    /**
     * Takes in the name of an attribute and its value.
     * Returns its filtered value.
     *
     * @param str $attribute
     * @param any $value
     * @author David Prater
     */
    protected function filter_attribute($attribute, $value) {
        //Ensure there is a content type set for this attribute
        if (!isset($this->attribute_content_types[$attribute]) or $this->attribute_content_types[$attribute] == 'raw') {
            return $value;
        }

        //If we were passed a null value, see if that's allowed for the field,
        //and if so, return it. Otherwise, return the default
        if ($value === null and $this->table_info[$attribute]['Null'] == 'YES') {
            return $value;
        }
        elseif ($value === null) {
            return $this->table_info[$attribute]['Default'];
        }

        $filter_values = $this->get_filter_by_attribute($attribute);
        //Refer to the array by variables for "clarity"
        $filter = $filter_values['filter'];
        $flags = $filter_values['flags'];

        //  We should never HTML encode quotes by default
        $flags = $flags | FILTER_FLAG_NO_ENCODE_QUOTES;

        //This will only be set if we are dealing with an enum
        if (isset($filter_values['enum_vals'])) {
            $enum_vals = $filter_values['enum_vals'];
        }

        //Get the content type to determine how to filter the data
        $content_type = $this->attribute_content_types[$attribute];

        //Use a regex and casting to filter ints
        if ($content_type == 'int') {
            $clean_var = (int)preg_replace($filter, '', $value);
        }
        //Use a regex to filter dates
        elseif ($content_type == 'date') {
            $clean_var = preg_replace($filter, '', $value);
        }
        //If this is an enum, determine if this a valid enum value
        elseif (isset($enum_vals)) {
            if (in_array($value, $enum_vals)) {
                $clean_var = $value;
            }
            //If it's invalid, just return the default enum value
            else $clean_var = $this->table_info[$attribute]['Default'];
        }
        //If this was not a special case, sanitize with filter_var
        else {
            $clean_var = filter_var($value, $filter, $flags);
        }

        //chars need some secondary cleaning as only matching pairs of tags get filtered
        //with PHP's filter
        if ($content_type == 'char') {
            $clean_var = preg_replace('/[<>]/', '', $clean_var);
        }

        return $clean_var;
    }

    /**
     * Based on the name of a table field, determines the appropriate filter and parameters to pass
     * the field through before being inserted/updated into the database
     *
     * @param str $attribute
     * @return array
     * @author David Prater
     */
    protected function get_filter_by_attribute($attribute) {
        //Check if there is a valid filter for this content type
        $content_type = $this->attribute_content_types[$attribute];
        $filter = $this->get_valid_filter($content_type);
        if ($filter == false) {
            return false;
        }

        //If so, get any filter flags
        $flags = $this->get_flags_by_content_type($content_type);

        $filter_values['filter'] = $filter;
        $filter_values['flags'] = $flags;

        //If this is an enum, store all of its valid values
        if ($content_type == 'enum' and $filter == 'enum') {
            $filter_values['enum_vals'] = $this->tokenize_enum($attribute);
        }

        return $filter_values;
    }

    /**
     * Based on the type of the sql column, determines the overall content type
     * of the field. The content type is used to determine how to filter data passed
     * to that field.
     *
     * @param array $type
     * @author David Prater
     */
    protected function get_content_type_by_sql_type($attribute) {
        if ($attribute == null or $attribute == false) {
            return false;
        }

        $sql_type = explode(' ', $this->get_attribute_type($attribute, true));
        $content_type = '';

        if (preg_match('/^.*int[(]?/', $sql_type[0])) {
            $content_type = 'int';
        }
        elseif (preg_match('/^.*char[(]?/', $sql_type[0])) {
            $content_type = 'char';
        }
        elseif (preg_match('/^.*text[(]?/', $sql_type[0])) {
            $content_type = 'char';
        }
        elseif (preg_match('/^float[(]?/', $sql_type[0])) {
            $content_type = 'float';
        }
        elseif (preg_match('/^decimal[(]?/', $sql_type[0])) {
            $content_type = 'float';
        }
        elseif (preg_match('/^double[(]?/', $sql_type[0])) {
            $content_type = 'float';
        }
        elseif (preg_match('/^time[(]?/', $sql_type[0])) {
            $content_type = 'date';
        }
        elseif (preg_match('/^date.*[(]?/', $sql_type[0])) {
            $content_type = 'date';
        }
        elseif (preg_match('/^.*enum[(]?/', $sql_type[0])) {
            $content_type = 'enum';
        }
        elseif (preg_match('/.*blob.*/', $sql_type[0])) {
            $this->force_no_filtering(true);
            $content_type = 'raw';
        }
        else {
            $content_type = 'name'; //Basic filter that strips HTML tags, newlines, and ASCII values < 32. Quotes are not escaped.
        }

        return $content_type;
    }

    /**
     * Determines whether a given content type is valid.
     * If so, returns its corresponding filter.
     *
     * @param str $content_type
     * @return Filter or bool
     * @author David Prater
     */
    protected function get_valid_filter($content_type) {
        $valid_types['char'] = FILTER_SANITIZE_STRING;
        $valid_types['int'] = '/[^\d\.\-]/';
        $valid_types['float'] = FILTER_SANITIZE_NUMBER_FLOAT;
        $valid_types['email'] = FILTER_SANITIZE_EMAIL;
        $valid_types['url'] = FILTER_SANITIZE_URL;
        $valid_types['enum'] = 'enum';
        $valid_types['date'] = '/[^\d\s\-\:]/';
        $valid_types['name'] = FILTER_SANITIZE_STRING;
        $valid_types['raw'] = 'raw';
        $valid_types['journal'] = FILTER_SANITIZE_STRING; //Strip tags, but allow quotes and newlines

        if (array_key_exists($content_type, $valid_types)) {
            return $valid_types[$content_type];
        }
        else return false;
    }

    /**
     * Based on a specific PHP data filter, determines what flags need to
     * be set before calling filter_var. Flags can be combined with a bitwise OR
     *
     * @param PHP Filter Constant
     * @return PHP Filter Flags or null
     * @author David Prater
     */
    protected function get_flags_by_content_type($filter) {
        $valid_flags['char'] = FILTER_FLAG_STRIP_LOW | FILTER_FLAG_ENCODE_AMP;
        $valid_flags['float'] = FILTER_FLAG_ALLOW_FRACTION;
        $valid_flags['name'] = FILTER_FLAG_STRIP_LOW | FILTER_FLAG_NO_ENCODE_QUOTES;
        $valid_flags['journal'] = FILTER_FLAG_NO_ENCODE_QUOTES;

        if (array_key_exists($filter, $valid_flags)) {
            return $valid_flags[$filter];
        }
        else return null;
    }

    /**
     * Takes in the name of an enum attribute and returns its possible values
     *
     * @param str $attribute
     * @return array
     * @author David Prater
     */
    public function tokenize_enum($attribute) {
        $sql_type = $this->get_attribute_type($attribute, true);
        //Try and ensure this is actually an enum
        if (!preg_match('/enum/', $sql_type)) {
            return false;
        }

        //Prune the enum(from the beginning, the) from the end, and the string literal quotes
        $sql_type = preg_replace('/(^enum[(]|[)]$|\')/', '', $sql_type);
        $sql_type = explode(',', $sql_type);

        return $sql_type;
    }

    /**
     * Callback Methods
     */

    public function get_callbacks($callback) {
        return $this->callbacks[$callback];
    }

    protected function set_callback($callback, $method) {
        $this->callbacks[$callback][] = $method;
    }

    protected function execute_callbacks($callback) {
        foreach ($this->get_callbacks($callback) as $method) {
            $this->$method();
        }
    }

    protected function before_add($method) {
        $this->set_callback('before_add', $method);
    }

    protected function before_update($method) {
        $this->set_callback('before_update', $method);
    }

    protected function before_delete($method) {
        $this->set_callback('before_delete', $method);
    }

    protected function before_save($method) {
        $this->set_callback('before_save', $method);
    }

    protected function after_save($method) {
        $this->set_callback('after_save', $method);
    }

    protected function after_delete($method) {
        $this->set_callback('after_delete', $method);
    }
    protected function after_update($method) {
        $this->set_callback('after_update', $method);
    }

    protected function after_add($method) {
        $this->set_callback('after_add', $method);
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
     * @param string $query_string
     * @param boolean $key
     * @return mixed result set OR failure
     * @author Basil Gohar <basil@eschoolconsultants.com>
     */
    public static function query($query_string, $key = false) {
        if (is_string($query_string)) {
            $result = mysql_query($query_string);

            if (is_resource($result)) {
                $return_array = array();
                while ($row = mysql_fetch_assoc($result)) {
                    if ($key)
                        $return_array[$row[$key]] = $row;
                    else
                        $return_array[] = $row;
                }
                return $return_array;
            }
            else {
                return $result;
            }
        }
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
    public static function get_sql($tables, $fields='*', $where_clause='', $order_by='', $group_by='', $limit_by='') {
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
                }
                else {
                    $select .= ', ';
                }

                if (strpos($field, '(')) {
                    $select .= mysql_real_escape_string(str_replace(array('(', ')', '.'), array('(`', '`)', '`.`'), $field));
                }
                else {
                    if (strpos($field, '*')) {
                        $search = array('.');
                        $replace = array('`.');

                        $select .= '`' . mysql_real_escape_string(str_replace($search, $replace, $field));
                    }
                    else {
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
                    elseif (strtoupper(substr($where_clause, 0, 4)) == 'AND ') {
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
                    }
                    else {
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
                    }
                    else {
                        $order_by_clause .= ', ';
                    }

                    //$order_by .= '`'.mysql_real_escape_string(str_replace('.', '`.`', $order_by_part[0])).'`';

                    if (strpos($order_by_part[0], '(')) {
                        $order_by_clause .= mysql_real_escape_string(str_replace(array('(', ')', '.'), array('(`', '`)', '`.`'), $order_by_part[0]));
                    }
                    else {
                        $order_by_clause .= '`'.mysql_real_escape_string(str_replace('.', '`.`', $order_by_part[0])).'`';
                    }

                    if (isset($order_by_part[1])) {
                        if (strtolower($order_by_part[1]) == 'desc') {
                            $order_by_clause .= ' DESC ';
                        }
                        else {
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
    public static function get_single_field_value($table_name, $field_name, $constraints) {
        $recordset = new db_recordset($table_name, $constraints, false, null, null, true);
        if (count($recordset) === 0) {
            return false;
        }
        foreach ($recordset as $record) {
            return $record->$field_name;
        }
    }
}
