<?php


/**
 * These test require a DB connection. Configure this in test/db_object_test.ini.
 * An example ini (test/db_object_test_SAMPLE.ini) is provided.
 *
 * These tests require PHPUnit. You can find instructions on how to install
 * PHPUnit in the README here: https://github.com/sebastianbergmann/phpunit/
 *
 * Run this test with the command line PHPUnit:
 * >> phpunit test/DbRecordsetTest.php
 *
 * or with the .php file in the PHPUnit directory:
 * >> php phpunit.php test/DbRecordsetTest.php
 */

require_once dirname(__FILE__) . '/../db_object.php';

extract(parse_ini_file('db_object_test.ini'));
mysql_connect($db_host, $db_user, $db_password)
    or die("Unable to connect to database:".mysql_error());
mysql_select_db($test_db_name)
    or die("Unable to select database");

////////////////////////////////////////////////////////////////////////////////////////////
// Things that must be defined in projects that use db_object and want user and IP metadata
    if (!function_exists('db_object_get_user_id')) {
        eval("function db_object_get_user_id() {
                  return db_object::get_single_field_value('person', 'id', array('last_name' => 'Fakerson'));
              }");
    }

    define('DB_OBJECT_USER_TABLE', 'person');

    if (!function_exists('db_object_get_user_id')) {
        eval('function db_object_get_user_ip() {
                  return $_SERVER["REMOTE_ADDR"];
              }');
    }
////////////////////////////////////////////////////////////////////////////////////////////

class DBRecordsetTest extends PHPUnit_Framework_TestCase
{

    private $recordset;

    function setUp()
    {
        db_object::query('CREATE TABLE IF NOT EXISTS `fruits` (
                    `id`    INT UNSIGNED NOT NULL AUTO_INCREMENT,
                    `name`  VARCHAR(255),
                    `color` VARCHAR(255),
                    `season`    ENUM("spring", "summer", "fall", "winter"),
                    `taste`     ENUM("yummy", "decent"),
                    `deleted`   TINYINT(1) NOT NULL DEFAULT \'0\',
                    PRIMARY KEY (id)
               ) ENGINE=InnoDB');

        db_object::query('START TRANSACTION');

        db_object::query('INSERT INTO `fruits` (id, name, color, season, taste, deleted) VALUES
                    (1, "strawberry", "red", "spring", "yummy", false),
                    (2, "banana", "yellow", "summer", "decent", false),
                    (3, "apple", "red", "fall", "decent", false),
                    (4, "kiwi", "brown", "winter", "yummy", false)');

        $this->recordset = new db_recordset('fruits');
    }

    function tearDown() {
        db_object::query('ROLLBACK');
        db_object::query("DROP TABLE `fruits`");
    }

    function testGetFieldValuesMethod()
    {
        $rset = db_object::query(db_object::get_sql('fruits', 'name'));

        $ids = array();

        foreach ($rset as $row)
        {
            $ids[] = $row['name'];
        }

        $this->assertSame($this->recordset->get_field_values('name'), $ids);

        // Most tests will involve comparing created arrays with the recordset, so we
        // want to explicity test that get_recordset() works as intended.
        $this->assertSame($this->recordset->get_recordset(), $this->recordset->get_field_values('id'));
    }


    function testArrayAccessMethods()
    {
        $rset = db_object::query(db_object::get_sql('fruits', 'MAX(id)'));
        $id   = $rset[0]['MAX(`id`)'];

        $this->assertEquals($this->recordset[$id]->get_id(), $id);
    }

    function testCountMethod()
    {
        $rset  = db_object::query(db_object::get_sql('fruits', 'COUNT(id)'));
        $count = $rset[0]['COUNT(`id`)'];

        $this->assertEquals(count($this->recordset), $count);
    }


    function testEmptyInstantiation()
    {
        try
        {
            $broken = new db_recordset();
            $this->fail();
        }
        catch (Exception $e)
        {
            $this->assertType('Exception', $e);
        }
    }

    function testConditionalInstantiation()
    {
        $rset = db_object::query(db_object::get_sql('fruits', 'id', '`color` = "red"'));

        $ids = array();

        foreach ($rset as $row)
        {
            $ids[] = $row['id'];
        }

        $reds = new db_recordset('fruits', array('color' => 'red'));

        $this->assertSame($reds->get_recordset(), $ids);
        $this->assertNotEquals($reds->get_recordset(), $this->recordset->get_recordset());
    }

    function testOrderedInstantiation()
    {
        $rset = db_object::query(db_object::get_sql('fruits', 'id', '', array(array('name', 'ASC'))));

        $ids = array();

        foreach ($rset as $row)
        {
            $ids[] = $row['id'];
        }

        $orders = new db_recordset('fruits', NULL, TRUE, array('name' => 'ASC'));

        $this->assertSame($orders->get_recordset(), $ids);
        $this->assertNotEquals($orders->get_recordset(), $this->recordset->get_recordset());
    }

    function testOrderingAfterInstantiation()
    {
        // Ensure the recordset is unsorted
        $rs = new db_recordset('fruits');
        $this->assertSame($rs->get_recordset(), array('1','2','3','4'));

        // Ensure the recordset is now sorted by name
        $rs->set_sort_order(array('name' => 'ASC'));
        $this->assertSame($rs->get_recordset(), array('3','2','4','1'));

        // Ensure the recordset is now sorted by season desc
        $rs->set_sort_order(array('color' => 'DESC'));
        $this->assertEquals($rs->get_recordset(), array('2','1','3','4'));
    }

    function testInstantiationOfClasses()
    {
        $fruits = new db_recordset('fruits', NULL, TRUE);
        $this->assertType('fruit', $fruits->first());

        $objects = new db_recordset('fruits', NULL, FALSE);
        $this->assertType('db_object', $fruits->first());
    }

    function testInstantiationWithAlternateKeys()
    {
        $rset = db_object::query(db_object::get_sql('fruits', array('id', 'name'), '', '', '', 1));
        $id   = $rset[0]['id'];
        $name = $rset[0]['name'];

        $alternates = new db_recordset('fruits', NULL, TRUE, NULL, 'name');

        $this->assertSame($alternates->get_recordset(), $this->recordset->get_recordset());
        $this->assertSame($alternates[$name]->get_attributes(), $this->recordset[$id]->get_attributes());
    }


    function testSetConstraintsMethod()
    {
        $rset = db_object::query(db_object::get_sql('fruits', 'id', '`color` = "red"'));

        $ids = array();

        foreach ($rset as $row)
        {
            $ids[] = $row['id'];
        }

        $this->recordset->set_constraints($ids);

        $this->assertSame($this->recordset->get_recordset(), $ids);
    }

    function testSetRecordsetLimitMethodNoOffset()
    {
        $rset = db_object::query(db_object::get_sql('fruits', 'id', '', '', '', 2));

        $ids = array();

        foreach ($rset as $row)
        {
            $ids[] = $row['id'];
        }

        $this->recordset->set_recordset_limit(2);

        $this->assertSame($this->recordset->get_recordset(), $ids);
    }

    function testSetRecordsetLimitMethodWithOffset()
    {
        $rset = db_object::query(db_object::get_sql('fruits', 'id', '`id` > 2', '', '', 1));

        $ids = array();

        foreach ($rset as $row)
        {
            $ids[] = $row['id'];
        }

        $this->recordset->set_recordset_limit(1, 2);

        $this->assertSame($this->recordset->get_recordset(), $ids);
    }

    function testRecordsetLimitParameterNoOffset()
    {
        $limit = 3;
        $rs = new db_recordset('fruits', NULL, TRUE, NULL, NULL, FALSE, $limit);
        $rset = db_object::query(db_object::get_sql('fruits', 'id', '', '', '', 3));

        $ids = array();

        foreach ($rset as $row)
        {
            $ids[] = $row['id'];
        }

        $this->recordset->set_recordset_limit(3);

        $this->assertSame($rs->get_recordset(), $ids);
    }

    function testRecordsetLimitParameterWithOffset()
    {
        $limit = array(1,2);
        $rs = new db_recordset('fruits', NULL, TRUE, NULL, NULL, FALSE, $limit);
        $rset = db_object::query(db_object::get_sql('fruits', 'id', '`id` > 2', '', '', 1));

        $ids = array();

        foreach ($rset as $row)
        {
            $ids[] = $row['id'];
        }

        $this->recordset->set_recordset_limit(1, 2);

        $this->assertSame($rs->get_recordset(), $ids);
    }

    function testTableInfoInstantiatedProperly()
    {
        $table_name = $this->recordset->table_name();
        $table_info = db_object::query("SHOW COLUMNS FROM `$table_name`", 'Field');

        $this->assertSame($this->recordset->table_info(), $table_info);
    }

    function testPrimaryKeyProperlySet()
    {
        $table_name = $this->recordset->table_name();
        $table_info = db_object::query("SHOW COLUMNS FROM `$table_name`", 'Field');

        $primary_key_field_name = '';

        foreach ($table_info as $field_name => $column_data) {
            if ('PRI' === $column_data['Key']) {
                $primary_key_field_name = $field_name;
                break;
            }
        }

        $this->assertSame($this->recordset->get_primary_key_field(), $primary_key_field_name);
    }

    function testIncludeDeletedRecords()
    {
        // grab some records to set as deleted.
        $fruit = new db_object('fruits', 1);
        $fruit->delete();
        $fruit = new db_object('fruits', 3);
        $fruit->delete();

        // should match for standard rs
        $sql = "SELECT * FROM `fruits` WHERE `deleted` = false";
        $r = db_object::query($sql);
        $ids = array();
        foreach ($r as $info) {
            $ids[] = $info['id'];
        }
        $rs = new db_recordset('fruits');
        $this->assertSame($ids, $rs->get_recordset());

        // should match for a rs with include_deleted = true
        $sql = "SELECT * FROM `fruits`";
        $r = db_object::query($sql);
        $ids = array();
        foreach ($r as $info) {
            $ids[] = $info['id'];
        }
        $rs = new db_recordset('fruits', NULL, true, NULL, NULL, true);
        $this->assertSame($ids, $rs->get_recordset());
    }


    function testIncludeDeletedRecordsWithIds()
    {
        // grab some records to set as deleted.
        $fruit = new db_object('fruits', 1);
        $fruit->delete();
        $fruit = new db_object('fruits', 3);
        $fruit->delete();

        $constraints = array(1, 2, 3);
        $where_string = implode(', ', $constraints);

        // should match for standard rs
        $sql = "SELECT * FROM `fruits` WHERE `deleted` = false AND `id` IN ($where_string)";
        $r = db_object::query($sql);
        $ids = array();
        foreach ($r as $info) {
            $ids[] = $info['id'];
        }
        $rs = new db_recordset('fruits', $constraints);
        $this->assertSame($ids, $rs->get_recordset());

        // should match for a rs with include_deleted = true
        $sql = "SELECT * FROM `fruits` WHERE `id` IN ($where_string)";
        $r = db_object::query($sql);
        $ids = array();
        foreach ($r as $info) {
            $ids[] = $info['id'];
        }
        $rs = new db_recordset('fruits', $constraints, true, NULL, NULL, true);
        $this->assertSame($ids, $rs->get_recordset());
    }

    // This is to insure that we don't overwrite constraints if
    // we set them in separate method calls.  db_recordset used
    // to wipe out the old ones with new ones, even if our data
    // hadn't been refreshed.
    function testConstraintsDirectlyAfterConstructor()
    {
        // All fruits should be included in the initial recordset.
        $rs = new db_recordset('fruits', array('color' => 'red'));
        $rs->set_constraints(array('taste' => 'decent'));
        $this->assertSame(array('3'), $rs->get_recordset());
    }

    function testAddingRecordsToTheRecordset()
    {
        $rs = new db_recordset('fruits', array('color' => 'red'));
        $rs->add_records(array(2));
        $this->assertSame(array('1','2','3'), $rs->get_recordset());
    }

    public function testFirst()
    {
        // get first object
        $fruit_1 = new fruit(1);
        $this->assertSame($this->recordset->first()->get_attributes(), $fruit_1->get_attributes());

        // preserve sort order
        $fruit_3 = new fruit(3);
        $this->recordset->set_sort_order(array('name' => 'ASC'));
        $this->assertSame($this->recordset->first()->get_attributes(), $fruit_3->get_attributes());

        // empty recordset
        $rs = new db_recordset('fruits', array());
        $this->assertFalse($rs->first());
    }

    // Make sure db_recordset will look for a class named the singular
    // of the table name if the plural class doesn't exist.
    function testAutoDetectionOfSingularClassNames()
    {
        $rs = new db_recordset('fruits');
        $this->assertType('fruit', $rs->first());
    }
}

class fruit extends db_object
{
    function __construct($id=NULL, $table_info = NULL, $attributes = NULL)
    {
        parent::__construct('fruits', $id, $table_info, $attributes);
    }
}
