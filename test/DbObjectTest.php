<?php


/**
 * These test require a DB connection. Configure this in test/db_object_test.ini.
 * An example ini (test/db_object_test_SAMPLE.ini) is provided.
 *
 * These tests require PHPUnit. You can find instructions on how to install
 * PHPUnit in the README here: https://github.com/sebastianbergmann/phpunit/
 *
 * Run this test with the command line PHPUnit:
 * >> phpunit test/DbObjectTest.php
 *
 * or with the .php file in the PHPUnit directory:
 * >> php phpunit.php test/DbObjectTest.php
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

class DBObjectTest extends PHPUnit_Framework_TestCase {

    function setUp()
    {
        $sql = "CREATE TABLE `person` (
            `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY ,
            `first_name` VARCHAR(255) NOT NULL,
            `last_name` VARCHAR(255) NOT NULL,
            `inserted_by` INT NOT NULL ,
            `inserted_on` DATETIME NOT NULL ,
            `updated_by` INT NOT NULL ,
            `updated_on` DATETIME NOT NULL,
            `deleted` TINYINT(1) NULL DEFAULT '0'
       )  ENGINE=InnoDB";
        db_object::query($sql);

        $sql = "CREATE TABLE `farm` (
            `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY ,
            `name` VARCHAR(255) NOT NULL DEFAULT 'Hillshire',
            `entered_by` INT NOT NULL ,
            `entered_on` DATETIME NOT NULL ,
            `updated_by` INT NOT NULL ,
            `updated_on` DATETIME NOT NULL,
            `deleted` TINYINT(1) NULL DEFAULT '0'
       )  ENGINE=InnoDB";
        db_object::query($sql);

        $sql = "CREATE TABLE `barn` (
            `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY ,
            `farm_id` INT NOT NULL ,
            `name` VARCHAR(255) NOT NULL ,
            `inserted_by` INT NOT NULL ,
            `inserted_on` DATETIME NOT NULL ,
            `updated_by` INT NOT NULL ,
            `updated_on` DATETIME NOT NULL,
            `inserted_ip` VARCHAR(15) NOT NULL,
            `updated_ip` VARCHAR(15) NOT NULL
       )  ENGINE=InnoDB";
        db_object::query($sql);

        $sql = "CREATE TABLE `animals` (
            `animal_id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY ,
            `farm_id` INT NOT NULL ,
            `barn` INT NULL,
            `name` VARCHAR(255) NOT NULL ,
            `inserted_by` INT NOT NULL ,
            `inserted_on` DATETIME NOT NULL ,
            `updated_by` INT NOT NULL ,
            `updated_on` DATETIME NOT NULL,
            `deleted` TINYINT(1) NULL DEFAULT '0'
       ) ENGINE=InnoDB";
        db_object::query($sql);


        $sql = "CREATE TABLE `bandit` (
            `bandit_id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY ,
            `name` VARCHAR(255) NOT NULL ,
            `farms_plundered` INT(11) NOT NULL DEFAULT '0',
            `money_stolen` FLOAT DEFAULT '0' ,
            `dangerous` ENUM('yes', 'no', 'maybe so', 'enum()') NOT NULL DEFAULT 'yes',
            `birthday` DATE,
            `email` VARCHAR(255) ,
            `inserted_by` INT NOT NULL ,
            `inserted_on` DATETIME NOT NULL ,
            `updated_by` INT NOT NULL ,
            `updated_on` DATETIME NOT NULL,
            `deleted` TINYINT(1) NULL DEFAULT '0'
       ) ENGINE=InnoDB";
        db_object::query($sql);

        $sql = 'CREATE TABLE `db_object_log` (
              `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
              `table` varchar(100) NOT NULL,
              `record_id` int(10) unsigned NOT NULL,
              `attribute` varchar(100) NOT NULL,
              `value` text NOT NULL,
              `changed_on` datetime NOT NULL,
              `changed_by` int(10) unsigned NOT NULL,
              `changed_ip` varchar(15) NOT NULL,
              PRIMARY KEY (`id`),
              KEY `table` (`table`),
              KEY `record_id` (`record_id`)
            )';
        db_object::query($sql);

        db_object::query('START TRANSACTION');

        $sql = "INSERT INTO `farm` VALUES (1, 'eSchool Farms', 0, '0000-00-00 00:00:00', 0, '0000-00-00 00:00:00', 0)";
        db_object::query($sql);

        $sql = "INSERT INTO `farm` VALUES (2, 'eSchool Farm #2', 0, '0000-00-00 00:00:00', 0, '0000-00-00 00:00:00', 0)";
        db_object::query($sql);

        $sql = "INSERT INTO `barn` VALUES (1, 1, 'The Barn', 0, '0000-00-00 00:00:00', 0, '0000-00-00 00:00:00', '', '')";
        db_object::query($sql);

        $sql = "INSERT INTO `animals` VALUES (1, 1, 1, 'Horse', 0, '0000-00-00 00:00:00', 0, '0000-00-00 00:00:00', 0)";
        db_object::query($sql);

        $sql = "INSERT INTO `animals` VALUES (2, 1, 1, 'Cow', 0, '0000-00-00 00:00:00', 0, '0000-00-00 00:00:00', 0)";
        db_object::query($sql);

        $sql = "INSERT INTO `animals` VALUES (3, 2, 1, 'Goat', 0, '0000-00-00 00:00:00', 0, '0000-00-00 00:00:00', 0)";
        db_object::query($sql);

        $sql = "INSERT INTO `bandit` VALUES (1, 'Juan Bandito', 7, 2567.32, 'yes', '1962-11-11', 'schladiesman@groupx.com', 0, '0000-00-00 00:00:00', 0, '0000-00-00 00:00:00', 0)";
        db_object::query($sql);

        $sql = "INSERT INTO `bandit` VALUES (2, 'Carlos Mojito', 1, 12.99, 'no', '2008-05-22', 'catsgomeow@thelitterboxdepot.org', 0, '0000-00-00 00:00:00', 0, '0000-00-00 00:00:00', 0)";
        db_object::query($sql);

        $person = new db_object('person');
        $person->first_name = 'Fake';
        $person->last_name  = 'Fakerson';
        $person->add();
    }

    function tearDown() {
        db_object::query('ROLLBACK');
        db_object::query("DROP TABLE `farm`");
        db_object::query("DROP TABLE `barn`");
        db_object::query("DROP TABLE `animals`");
        db_object::query("DROP TABLE `bandit`");
        db_object::query("DROP TABLE `db_object_log`");
    }

    /**
     * These test the constructor's functionality
     */

    function testSettingTableInfo()
    {
        // Clear out session data for this table.
        unset($_SESSION['table_column_cache']['farm']);

        // Set up an incorrect table_info array
        $table_info = array();
        $table_info['id'] = array(
            'Field' => 'id',
            'Type' => 'int(11)',
            'Null' => 'NO',
            'Key' => 'PRI',
            'Default' => '',
            'Extra' => 'auto_increment'
       );

        // Instantiate a new db_object with the array an make sure
        // db_object respects the bad info.
        $farm = new farm(1, $table_info);
        $this->assertEquals($farm->table_info(), $table_info);

        // Make sure the data was stored in the session
        $this->assertEquals($_SESSION['table_column_cache']['farm'], $table_info);

        // Clear out session data for this table.
        unset($_SESSION['table_column_cache']['farm']);

        // Instantiate a new db_object without the bad info and make sure
        // db_object gets it's own info
        $farm = new farm(1);
        $this->assertNotEquals($farm->table_info(), $table_info);
    }

    function testPrimaryKeyFieldIsSetCorrectly()
    {
        $animal = new db_object('animals', 1);
        $this->assertEquals($animal->get_primary_key_field(), 'animal_id');
    }

    function testMetaDataFieldsAreCorrectlyDetected()
    {
        $farm = new farm(1);
        $correct_farm_metadata = array('updated_by','updated_on','deleted');

        $this->assertEquals($farm->metadata_fields(), $correct_farm_metadata);

        $barn = new barn(1);
        $correct_barn_metadata = array('inserted_by','inserted_on','updated_by','updated_on','inserted_ip','updated_ip');

        $this->assertEquals($barn->metadata_fields(), $correct_barn_metadata);
    }

    function testNullInstantiatedIsSetProperly()
    {
        $farm = new farm(1);
        $this->assertFalse($farm->null_instantiated);

        $farm = new farm();
        $this->assertTrue($farm->null_instantiated);
    }

    function testDefaultsAreSetWhenNullInstantiated()
    {
        $farm = new farm();
        $this->assertEquals($farm->get_attribute('name'), 'Hillshire');
    }

    function testAttributesAreSetWhenInstantiated()
    {
        $farm = new farm(1);
        $this->assertEquals($farm->get_attribute('name'), 'eSchool Farms');
    }

    function testExceptionIsThrownWhenRecordNotFound()
    {
        try {
            $farm = new farm(3);
            $this->fail(); // Fail if the above line doesn't throw an exception
        }
        catch (Exception $e) {
            $this->assertType('Exception', $e);
        }
    }

    function testExceptionIsThrownWhenNonExistantTableIsUsed()
    {
        try {
            $bryce = new db_object('bryce');
            $this->fail(); // Fail if the above line doesn't throw an exception
        }
        catch (Exception $e) {
            $this->assertType('Exception', $e);
        }
    }

    function testGetAttributeType()
    {
        $animal = new animal();

        $this->assertEquals($animal->get_attribute_type('farm_id'), 'int');
        $this->assertEquals($animal->get_attribute_type('name'), 'varchar');
    }

    /**
     * These test the add method
     */

    function testInstantiatedRecordCannotBeAdded()
    {
        $farm = new farm(1);

        try {
            $farm->add();
            $this->fail(); // Fail if the above line doesn't throw an exception
        }
        catch (Exception $e) {
            $this->assertType('Exception', $e);
        }
    }

    function testMetaDataIsAdded()
    {
        $barn = new barn();
        $barn->set_attribute('farm_id', 1);
        $barn->set_attribute('name', 'New Barn');
        $barn->add();

        $this->assertEquals(db_object_get_user_id(), $barn->get_attribute('inserted_by'));
        $this->assertEquals(date('M-d-y'), date('M-d-y', strtotime($barn->get_attribute('inserted_on'))));
        $this->assertEquals($_SERVER['REMOTE_ADDR'], $barn->get_attribute('inserted_ip'));
        $this->assertEquals(null, $barn->get_attribute('updated_by'));
        $this->assertEquals(null, $barn->get_attribute('updated_on'));
        $this->assertEquals('', $barn->get_attribute('updated_ip'));
    }

    // Same as above, only creating a new object.
    function testDataIsSaved()
    {
        $barn = new barn();
        $barn->set_attribute('farm_id', 1);
        $barn->set_attribute('name', 'New Barn');
        $barn->add();
        $barn_id = $barn->get_id();

        $barn = new barn($barn_id);
        $this->assertEquals('New Barn', $barn->get_attribute('name'));
    }

    /**
     * These test the update method
     */

    function testNullInstantiatedObjectCannotBeUpdated()
    {
        $barn = new barn();
        $barn->set_attribute('name', 'New Barn');

        try {
            $barn->update();
            $this->fail(); // Fail if the above line doesn't throw an exception
        }
        catch(Exception $e) {
            $this->assertType('Exception', $e);
        }
    }

    function testMetaDataIsUpdated()
    {
        $barn = new barn();
        $barn->set_attribute('name', 'New Barn');
        $barn->add();
        $inserted_by = $barn->get_attribute('inserted_by');
        $inserted_on = $barn->get_attribute('inserted_on');
        $inserted_ip = $barn->get_attribute('inserted_ip');

        $barn->set_attribute('name', 'Newer Barn', false);
        $barn->update();

        // Make sure the "inserted" metadata didn't change
        $this->assertEquals($inserted_by, $barn->get_attribute('inserted_by'));
        $this->assertEquals($inserted_on, $barn->get_attribute('inserted_on'));
        $this->assertEquals($inserted_ip, $barn->get_attribute('inserted_ip'));

        // Make sure the "updated" fields are set
        $this->assertEquals(db_object_get_user_id(), $barn->get_attribute('updated_by'));
        $this->assertEquals(date('M-d-y'), date('M-d-y', strtotime($barn->get_attribute('updated_on'))));
        $this->assertEquals($_SERVER['REMOTE_ADDR'], ($barn->get_attribute('updated_ip')));
    }

    /**
     * These test the delete method
     */

    function testNullInstantiatedObjectCannotBeDeleted()
    {
        $barn = new barn();

        try {
            $barn->delete();
            $this->fail(); // Fail if the above line doesn't throw an exception
        }
        catch(Exception $e) {
            $this->assertType('Exception', $e);
        }
    }

    function testModifiedObjectCannotBeDeletedUnlessForced()
    {
        $barn = new barn(1);
        $barn->set_attribute('name', 'New Barn', false);

        // Deleting should throw an exception
        try {
            $barn->delete();
            $this->fail(); // Fail if the above line doesn't throw an exception
        }
        catch(Exception $e) {
            $this->assertType('Exception', $e);
        }

        // Forcing a delete should be allowed
        $barn->delete(true, true);

        // Now try to instantiate the deleted record.
        try {
            $barn = new barn(1);
            $this->fail(); // Fail if the above line doesn't throw an exception
        }
        catch(Exception $e) {
            $this->assertType('Exception', $e);
        }
    }

    function testSoftDelete()
    {
        $farm = new farm(1);
        $farm_id = $farm->get_id();
        $farm->delete();

        $sql = "SELECT * FROM `farm` WHERE `id` = '$farm_id'";
        list($r) = db_object::query($sql);
        $this->assertEquals($r['deleted'], 1);
    }

    function testHardDelete()
    {
        $farm = new farm(1);
        $farm_id = $farm->get_id();
        $farm->delete(false, true);

        // record should be completely gone.
        $sql = "SELECT * FROM `farm` WHERE `id` = '$farm_id'";
        list($r) = db_object::query($sql);
        $this->assertEquals($r, false);
    }

    function testTableWithoutDeletedMetadata() {
        $barn = new barn(1);
        $barn_id = $barn->get_id();
        $barn->delete();

        // record should be completely gone.
        $sql = "SELECT * FROM `barn` WHERE `id` = '$barn_id'";
        list($r) = db_object::query($sql);
        $this->assertEquals($r, false);
    }

    function testUndelete()
    {
        // get a farm
        $farm = new farm(1);

        // delete farm
        $farm->delete();
        $this->assertEquals('1', db_object::get_single_field_value('farm', 'deleted', array('id' => $farm->get_id())));

        // undelete farm
        $farm->undelete();
        $this->assertEquals('0', db_object::get_single_field_value('farm', 'deleted', array('id' => $farm->get_id())));

        // check against original
        $check = new farm(1);
        $this->assertEquals($farm->get_attributes(), $check->get_attributes());
    }

    function testUndeleteOnAdd()
    {
        // delete a farm
        $farm = new farm(1);
        $this->assertTrue($farm->delete());

        // add same farm
        $same = new farm();
        $same->name = $farm->name;
        $same->entered_on = $farm->entered_on;
        $same->entered_by = $farm->entered_by;
        $this->assertTrue($same->add());

        // check same farm
        $check = new farm(1);
        $this->assertEquals($same->get_attributes(), $check->get_attributes());
    }

    function testAddAfterUndeleteByAdd() {
        $farm = new farm(1);
        $farm_id = $farm->get_id();
        $farm_entered_by = $farm->entered_by;
        $farm_entered_on = $farm->entered_on;
        $farm->delete();

        $sql = "SELECT * FROM `farm` WHERE `id` = '$farm_id'";
        list($r) = db_object::query($sql);
        $this->assertEquals($r['deleted'], 1);

        $farm = new farm();
        $farm->name = "eSchool Farms";
        $farm->entered_by = $farm_entered_by;
        $farm->entered_on = $farm_entered_on;
        $farm->add();

        $sql = "SELECT * FROM `farm` WHERE `name` = 'eSchool Farms'";
        $r = db_object::query($sql);
        $this->assertEquals(count($r), 1);
        $this->assertEquals($r[0]['deleted'], 0);

        $farm = new farm();
        $farm->name = "eSchool Farms";
        $farm->entered_by = $farm_entered_by;
        $farm->entered_on = $farm_entered_on;
        $farm->add();

        $sql = "SELECT * FROM `farm` WHERE `name` = 'eSchool Farms'";
        $r = db_object::query($sql);
        $this->assertEquals(count($r), 2);
        $this->assertEquals($r[0]['deleted'], 0);
        $this->assertEquals($r[1]['deleted'], 0);
    }

    /**
     * These test the get_acceptable_attributes method
     */

    function testAcceptableAttributesArray()
    {
        $acceptable_attributes = array();
        $acceptable_attributes['animal_id']     = NULL;
        $acceptable_attributes['farm_id']       = NULL;
        $acceptable_attributes['barn']          = NULL;
        $acceptable_attributes['name']          = NULL;
        $acceptable_attributes['inserted_by']   = NULL;
        $acceptable_attributes['inserted_on']   = NULL;
        $acceptable_attributes['updated_by']    = NULL;
        $acceptable_attributes['updated_on']    = NULL;
        $acceptable_attributes['deleted']       = NULL;

        $animal = new db_object('animals');
        $this->assertEquals($acceptable_attributes, $animal->get_acceptable_attributes());
    }


    /**
     * These test the get_attribute method
     */

    function testGetAttribute()
    {
        $animal = new db_object('animals', 1);

        $this->assertEquals('Horse', $animal->get_attribute('name'));
    }

    /**
     * These test the get_id method
     */

    function testGetID()
    {
        $animal = new db_object('animals', 2);
        $this->assertEquals(2, $animal->get_id());
    }

    /**
     * These test the get_all_ids method
     */

    function testGetAllIDs()
    {
        $all_animal_ids = array(1,2,3);

        $animal = new db_object('animals');
        $this->assertEquals($all_animal_ids, $animal->get_all_ids());
    }

    /**
     * These test the get_attributes method
     */

    function testGetAttributes()
    {
        $animal = new db_object('animals', 2);

        $expected_attributes = array();
        $expected_attributes['animal_id'] = 2;
        $expected_attributes['farm_id'] = 1;
        $expected_attributes['barn'] = 1;
        $expected_attributes['name'] = 'Cow';
        $expected_attributes['inserted_by'] = 0;
        $expected_attributes['inserted_on'] = '0000-00-00 00:00:00';
        $expected_attributes['updated_by'] = 0;
        $expected_attributes['updated_on'] = '0000-00-00 00:00:00';
        $expected_attributes['deleted'] = 0;

        $this->assertEquals($expected_attributes, $animal->get_attributes());
    }

    /**
     * These test the set_attribute method
     */

    function testSetAttributeDetectsInvalidAttributes()
    {
        $animal = new db_object('animals', 1);

        try {
            $animal->set_attribute('hair_type', 'silky smooth');
            $this->fail(); // Fail if the above line doesn't throw an exception
        }
        catch(Exception $e) {
            $this->assertType('Exception', $e);
        }
    }

    function testSetAttributeOnlyUpdatesIfForced()
    {
        $animal = new db_object('animals', 1);
        $animal->set_attribute('name', 'Cat', false);

        // Make sure an update didn't happen
        $animal = new db_object('animals', 1);
        $this->assertEquals('Horse', $animal->get_attribute('name'));

        $animal = new db_object('animals', 1);
        $animal->set_attribute('name', 'Cat');

        // Make sure an update did happen
        $animal = new db_object('animals', 1);
        $this->assertEquals('Cat', $animal->get_attribute('name'));
    }

    function testTrimOnSetAttribute()
    {
        // whitespace should be trimmed from text values
        $bad_value = '  Llama ';

        $animal = new animal(1);
        $animal->name = $bad_value;
        $this->assertEquals($animal->name, trim($bad_value));
    }

    /**
     * These test the set_attributes method
     */

    function testSetAttributes()
    {
        $animal = new db_object('animals', 1);
        $new_attributes = array('farm_id' => 2, 'barn' => '5', 'name' => 'Zebra');
        $animal->set_attributes($new_attributes);

        $animal = new db_object('animals', 1);
        foreach($new_attributes as $attribute => $value)
        {
            $this->assertEquals($value, $animal->get_attribute($attribute));
        }
    }

    /**
     * These test the is_acceptable_attribute method
     */

    function testIsAcceptableAttribute()
    {
        $animal = new db_object('animals', 1);
        $this->assertFalse($animal->is_acceptable_attribute('height'));
        $this->assertTrue($animal->is_acceptable_attribute('name'));
    }

    /**
     * These test the get_parent_object method
     */

    function testGetParentObjectWhenEverythingIsNamedProperly()
    {
        // Make sure it works in this case
        $barn = new barn(1);
        $parent = $barn->get_parent_object('farm_id');
        $this->assertType('farm', $parent);

        // Make sure it returned the correct record
        $this->assertEquals(1, $parent->get_id());
    }

    function testGetParentObjectWhenThingsAreNamedFunny()
    {
        // Make sure it works when passing the parent table name
        $animal = new db_object('animals', 1);
        $parent = $animal->get_parent_object('barn', 'barn');
        $this->assertType('barn', $parent);

        // Make sure it fails when not using the standard naming conventions.
        // A table name must be passed in this case.
        $animal = new db_object('animals', 1);
        try {
            $parent = $animal->get_parent_object('barn');
            $this->fail(); // Fail if the above line doesn't throw an exception
        }
        catch(Exception $e) {
            $this->assertType('Exception', $e);
        }
    }

    function testModifiedAttributesWhenSetAttributeTrue()
    {
        $farm = new db_object('farm');
        $farm->set_attribute('name','My Farm');
        $this->assertSame($farm->modified(), true);
    }

    /*  SHOULD THIS BE THE 'true'? or 'false'?  We're leaving it for now.  */
    function testModifiedAttributesWhenNewObject()
    {
        $farm = new db_object('farm');
        $this->assertSame($farm->modified(), true);
    }

    function testModifiedAttributesWhenExistingSetAttributeTrue()
    {
        $farm = new db_object('farm',1);
        $farm->set_attribute('name', 'New Name', false);
        $this->assertSame($farm->modified(), true);
    }

    function testModifiedAttributesWhenExistingSetAttributeFalse()
    {
        $farm = new db_object('farm',1);
        $this->assertSame($farm->modified(), false);
    }

    function testModifiedAttributesAfterAddRecord()
    {
        $farm = new db_object('farm');
        $farm->set_attribute('name','My Farm');
        $farm->add();
        $this->assertSame($farm->modified(), false);
    }

    function testModifiedAttributesAfterUpdateRecord()
    {
        $farm = new db_object('farm',1);
        $farm->set_attribute('name','New Name', false);
        $farm->update();
        $this->assertSame($farm->modified(), false);

        /**
         *  Then we set the value to existing value to confirm it's still not 'modified'
         */
        $farm = new db_object('farm',1);
        $farm->set_attribute('name','New Name', false);
        $this->assertSame($farm->modified(), false);
    }

    function testModifiedAttributesAfterSetAttributes()
    {
        $farm = new db_object('farm',1);
        $farm->set_attributes(array('name'=>'New Name'),false);
        $this->assertSame($farm->modified(), true);
    }

    function testModifiedAttributesAfterForceSetAttributes()
    {
        $farm = new db_object('farm',1);
        $farm->set_attributes(array('name'=>'New Name'));
        $this->assertSame($farm->modified(), false);
    }

    function testGetParentObjectWhenAbnormalRelationshipIsDefinedInTheClass()
    {
        $animal = new animal(1);
        $parent = $animal->get_parent_object('barn');
        $this->assertType('barn', $parent);
    }

    function testGetParentObjectWithMagicSyntaxWhenNamedOddly()
    {
        // Should work if we've set the relationship properly in the extended class.
        $animal = new animal(1);
        $parent = $animal->barn();
        $this->assertType('barn', $parent);

        // Shouldn't work if we're using a generic db_object
        $animal = new db_object('animals', 1);
        try {
            $parent = $animal->barn();
            $this->fail(); // Fail if the above line doesn't throw an exception
        }
        catch (Exception $e) {
            $this->assertType('Exception', $e);
        }
    }

    function testGetParentObjectWithMagicSyntaxWhenNamedNormally()
    {
        // Everything should work fine in this case.
        $barn = new barn(1);
        $parent = $barn->farm();
        $this->assertType('farm', $parent);

        $this->assertEquals(1, $parent->get_id());
    }

    function testGetParentObjectCachingWithMagicSyntax()
    {
        $barn = new barn(1);
        $barn->farm()->name;  // This is just to cache the parent record in db_object.
        $farm = new farm(1);
        $farm->name = 'temp name';

        // The update above should have cleared the cache of the "farm" info.
        $this->assertEquals($barn->farm()->name, $farm->name);
    }

    /**
     * These test the get_child_object method
     */

    function testGetChildObjectWhenEverythingIsNamedProperly()
    {
        $farm = new db_object('farm', 1);
        $child = $farm->get_child_object('barn');
        $this->assertType('barn', $child);
    }

    function testGetChildObjectWhenThingsAreNamedFunny()
    {
        // There are three animals for this barn, so it automitically will return a recordset
        // What we're really testing is the fact that the foreign key is not named properly.
        $barn = new db_object('barn', 1);
        $child = $barn->get_child_object('animals', 'barn');
        $this->assertType('db_recordset', $child);
    }

    function testGetChildObjectConstraints()
    {
        // Should only find one, so return a db_object (extended if possible)
        $barn = new db_object('barn', 1);
        $child = $barn->get_child_object('animals', 'barn', "animals.name = 'Cow'");
        $this->assertType('animal', $child);

        // Should find two, so return a db_recorset
        $child = $barn->get_child_object('animals', 'barn', "animals.farm_id = 1");
        $this->assertType('db_recordset', $child);

        // Make sure it returned the correct records
        $this->assertEquals(array(1,2), $child->get_recordset());
    }

    function testGetChildObjectWithForcedObjectType()
    {
        // Should only find one, but we'll force it to be a db_recordset
        $barn = new db_object('barn', 1);
        $child = $barn->get_child_object('animals', 'barn', "animals.name = 'Cow'", 'db_recordset');
        $this->assertType('db_recordset', $child);
    }

    function testGetChildObjectWhenRelationshipExplicitlyDeclaredInClass()
    {
        $farm = new farm(1);
        $child = $farm->barn();
        $this->assertType('barn', $child);

        $barn = new barn(1);
        $child = $barn->animals();
        $this->assertType('db_recordset', $child);
    }

    function testGetChildObjectWithMagicSyntaxOnGenericDbObjects()
    {
        // This should work since all the fields are named properly
        $farm = new db_object('farm', 1);
        $child = $farm->animals();
        $this->assertType('db_recordset', $child);

        // This shouldn't work since we have "barn" instead of "barn_id" in the animals table.
        $barn = new db_object('barn', 1);
        try {
            $child = $barn->animals();
            $this->fail(); // Fail if the above line doesn't throw an exception
        }
        catch (Exception $e) {
            $this->assertType('Exception', $e);
        }

    }

    /**
     * These test the find method
     */

    function testFindWhenUsedNormally()
    {
        $animal = new db_object('animals');
        $this->assertFalse($animal->get_id());

        $animal->find(array('name' => 'Cow'));
        $this->assertEquals(2, $animal->get_id());
    }

    function testFindOnAnExistingObject()
    {
        $animal = new db_object('animals', 1);

        // Find should throw an exception when called on an instantiated object
        try {
            $animal->find(array('name' => 'Cow'));
            $this->fail(); // Fail if the above line doesn't throw an exception
        }
        catch (Exception $e) {
            $this->assertType('Exception', $e);
        }
    }

    function testFindWithABadAttribute()
    {
        $animal = new db_object('animals');

        // Find should throw an exception when called an incorrect attribute
        try {
            $animal->find(array('type' => 'Cow'));
            $this->fail(); // Fail if the above line doesn't throw an exception
        }
        catch (Exception $e) {
            $this->assertType('Exception', $e);
        }
    }

    function testFindWithNonUniqueAttributes()
    {
        $animal = new db_object('animals');

        // Find just returns the first found record if there is more than one.
        $animal->find(array('barn' => 1));
        $this->assertEquals(1, $animal->get_id());
    }

    function testFindWhenUsingTheMagicSyntax()
    {
        $animal = new db_object('animals');
        $animal->find_by_name('Cow');
        $this->assertEquals(2, $animal->get_id());

        // Find a goat by the farm id
        $animal = new db_object('animals');
        $animal->find_by_farm_id(2);
        $this->assertEquals(3, $animal->get_id());
    }

    /**
     * These test other magic aspects of db_object
     */

    function testGetAttributeMagicSyntaxWorks()
    {
        $farm = new farm(1);
        $this->assertEquals($farm->get_attribute('name'), $farm->name);
    }

    function testSetAttributeMagicSyntaxWorks()
    {
        $farm = new farm(1);
        $farm->name = 'blah';
        $this->assertEquals($farm->get_attribute('name'), 'blah');
    }

    function testExceptionIsThrownForUnknownMethod()
    {
        // We want to make sure our use of "__call" isn't too ambitious
        $animal = new db_object('animals');
        try {
            $animal->not_a_method('huh?');
            $this->fail(); // Fail if the above line doesn't throw an exception
        }
        catch (Exception $e) {
            $this->assertType('Exception', $e);
        }
    }

    // test the metadata tools

    public function testGetInserterObject()
    {
        $animal = new animal(1);
        $editor = new db_object(DB_OBJECT_USER_TABLE, db_object_get_user_id());

        // check inserter
        $this->assertFalse($animal->get_inserter_object());
        $this->assertTrue($animal->set_attribute('inserted_by', $editor->get_id(), FALSE));
        $this->assertEquals($animal->get_inserter_object()->get_attributes(), $editor->get_attributes());
        $this->assertEquals($animal->get_editor_object()->get_attributes(), $editor->get_attributes());

        // check updater -- uses session data set above in testMetaDataIsAdded()
        $this->assertFalse($animal->get_updater_object());
        $this->assertTrue($animal->update());
        $this->assertEquals($animal->get_updater_object()->get_id(), db_object_get_user_id());
        $this->assertSame($animal->get_editor_object(), $animal->get_updater_object());

        // check with bad values
        $fake_id = 120533623;
        $this->assertFalse(db_object::get_single_field_value(DB_OBJECT_USER_TABLE, 'id', array('id' => $fake_id)));
        $this->assertTrue($animal->set_attribute('inserted_by', $fake_id, FALSE));
        $this->assertFalse($animal->get_inserter_object());
    }

    public function testGetEditorTime()
    {
        $animal = new animal(1);
        $editor = new db_object(DB_OBJECT_USER_TABLE, db_object_get_user_id());

        $this->assertFalse($animal->get_editor_time());
        $this->assertTrue($animal->set_attribute('inserted_by', $editor->get_id()));
        $this->assertSame($animal->get_editor_time(), date('Y-m-d', strtotime($animal->updated_on)));
        $this->assertSame($animal->get_editor_time('Y-m-d H:i:s'), $animal->updated_on);
    }

    public function testFilterAttributeAs() {
        /*
         * Each of these test blocks follows the same prototype:
         * 1. Turn on filtering
         * 2. Set attribute to a garbage value
         * 3. Ensure attribute was filtered correctly
         */

        $bandit = new db_object('bandit', 1);

        $bandit->filter_attribute_as('email', 'email');
        $bandit->set_attribute('email', '<h6>thisisanemail@)()(people.org');
        $this->assertEquals($bandit->email, 'h6thisisanemail@people.org');

        $garbage_name = 'GARBAGE98y9283y<b>d<script></script></b>;:"":AS{FAS}??><ASF5,...""";;\'\'\'\'\'';
        $bandit->filter_attribute_as('name', 'raw');
        $bandit->set_attribute('name', $garbage_name . 'a');
        $this->assertEquals($bandit->name, $garbage_name . 'a');

        $garbage_name = 'pa>blo""<script>>\'&';
        $bandit->filter_attribute_as('name', 'char');
        $bandit->set_attribute('name', 'o' . $garbage_name);
        $this->assertEquals($bandit->name, 'opablo""\'&#38;');

        $bandit->filter_attribute_as('money_stolen', 'int');
        $bandit->set_attribute('money_stolen', 'kjadhs&&#;ix+++-iuasd{:{"1}}|2?>3<<?4?<b>.05q');
        $this->assertEquals($bandit->money_stolen, -1234);

        //Make sure setting to null on nullable fields works
        $bandit->set_attribute('money_stolen', null);
        $this->assertNull($bandit->money_stolen);

        $bandit->filter_attribute_as('dangerous', 'enum');
        $bandit->set_attribute('dangerous', 'shazamasdh9asdPLAD{A:>?<?<');
        $this->assertEquals($bandit->dangerous, 'yes');
        $bandit->set_attribute('dangerous', 'maybe so');
        $this->assertEquals($bandit->dangerous, 'maybe so');

        $bandit->set_attribute('farms_plundered', '23skidoo!');
        $this->assertEquals($bandit->farms_plundered, '23skidoo!');
        $bandit->filter_attribute_as('farms_plundered', 'int');

        //Make sure setting null on non-nullable fields results in the default value
        $bandit->set_attribute('farms_plundered', null);
        $this->assertEquals($bandit->farms_plundered, 0);

        $bandit->filter_attribute_as('birthday', 'date');
        $bandit->set_attribute('birthday', '186oihiaus??2<script>-06-&&&YH&YHas;s;l07');
        $this->assertEquals($bandit->birthday, '1862-06-07');

        $bandit->filter_attribute_as('inserted_on', 'date');
        $bandit->set_attribute('inserted_on', 'joijoih*&*200oihiaus??2<script>-07-&&&YH&YHas;s;l08 1iuh2:1><>..//1:1"{}0');
        $this->assertEquals($bandit->inserted_on, '2002-07-08 12:11:10');

    }

    public function testFilterAllAttributes(){
        $bandit = new db_object('bandit', 2);
        $bandit->filter_all_attributes();

        $bandit->set_attribute('email', '<h6>thisisanemail@)()(people.org');
        $this->assertEquals($bandit->email, 'thisisanemail@)()(people.org');

        $garbage_name = 'pa>blo""<script>>\'&';
        $bandit->set_attribute('name', $garbage_name);
        $this->assertEquals($bandit->name, 'pablo""\'&#38;');

        $bandit->set_attribute('money_stolen', 'kjadhs&&#;ix-iuasd{:{"1}}|2?>3<<?4?<b>.05q');
        $this->assertEquals($bandit->money_stolen, -1234.05);
        $bandit->set_attribute('money_stolen', null);
        $this->assertNull($bandit->money_stolen);

        $bandit->set_attribute('dangerous', 'shazamasdh9asdPLAD{A:>?<?<');
        $this->assertEquals($bandit->dangerous, 'yes');
        $bandit->set_attribute('dangerous', 'maybe so');
        $this->assertEquals($bandit->dangerous, 'maybe so');

        $bandit->set_attribute('farms_plundered', 'iahdiua4oiahsfoasf5&*(**&(*&6<><>S/dsa.');
        $this->assertEquals($bandit->farms_plundered, 456);
        $bandit->set_attribute('farms_plundered', null);
        $this->assertEquals($bandit->farms_plundered, 0);

        $bandit->set_attribute('birthday', '186oihiaus??2<script>-06-&&&YH&YHas;s;l07');
        $this->assertEquals($bandit->birthday, '1862-06-07');

        $bandit->set_attribute('inserted_on', 'joijoih*&*200oihiaus??2<script>-07-&&&YH&YHas;s;l08 1iuh2:1><>..//1:1"{}0');
        $this->assertEquals($bandit->inserted_on, '2002-07-08 12:11:10');

    }

    public function testTokenizeEnum() {
        $bandit = new db_object('bandit', 1);
        $enum_vals = $bandit->tokenize_enum('dangerous');

        //Ensure array contents are exactly as expected
        $this->assertTrue(in_array('yes', $enum_vals));
        $this->assertTrue(in_array('no', $enum_vals));
        $this->assertTrue(in_array('maybe so', $enum_vals));
        $this->assertTrue(in_array('enum()', $enum_vals)); //Weird edge case! The tokenize_enum() regex should leave this value intact.
        $this->assertEquals(4, count($enum_vals));
    }

    public function testMagicEmpty() {
        // This object has a name
        $animal = new animal(1);
        $this->assertFalse(empty($animal->name));

        // Values that should return true for empty
        $names = array('', 0, '0', null, FALSE, $garbage);

        // Test all of those values above
        foreach ($names as $name) {
            $animal->name = $name;
            $this->assertTrue(empty($animal->name));
        }

        // If we call the magic method on an attribute that does not exist, we throw an exception
        try {
            empty($animal->junk);
            $this->fail(); // Fail if the above line doesn't throw an exception
        }
        catch (Exception $e) {
            $this->assertType('Exception', $e);
        }

        // A null instantiated db object should have empty attributes
        $new_animal = new animal();

        $this->assertTrue(empty($new_animal->name));
    }

    /*
     * Test Callbacks
     */

    public function testCallbacksAreEmptyForBasicObject() {
        $animal = new animal();
        $callbacks = array('before_add',
            'before_update',
            'before_save',
            'after_save',
            'after_update',
            'after_add');

        // Make sure each callback array is empty for a new object
        foreach($callbacks as $callback) {
            $callback_array = $animal->get_callbacks($callback);
            $this->assertTrue(empty($callback_array));
        }
    }

    public function testCallbacksCanBeSet() {
        $horse = new horse();

        $before_add_callbacks = $horse->get_callbacks('before_add');
        $this->assertEquals($before_add_callbacks, array('check_saddle', 'give_name'));

        $after_save_callbacks = $horse->get_callbacks('after_save');
        $this->assertEquals($after_save_callbacks, array('brush_mane'));
    }

    public function testCallbacksAreCalledOnAdd() {
        $horse = new horse();

        // Make sure the default values are set
        $this->assertEquals($horse->saddle_checked, false);
        $this->assertEquals($horse->name_given, false);
        $this->assertEquals($horse->mane_brushed, false);
        $this->assertEquals($horse->tail_measured, false);
        $this->assertEquals($horse->weighed, false);

        // These shouldn't change after add
        $this->assertEquals($horse->hooves_checked, false);
        $this->assertEquals($horse->height_measured, false);

        $horse->add();

        // Make sure the callback sets the new values
        $this->assertEquals($horse->saddle_checked, true);
        $this->assertEquals($horse->name_given, true);
        $this->assertEquals($horse->mane_brushed, true);
        $this->assertEquals($horse->tail_measured, true);
        $this->assertEquals($horse->weighed, true);

        // Make sure attributes didn't change if they weren't supposed to
        $this->assertEquals($horse->hooves_checked, false);
        $this->assertEquals($horse->height_measured, false);
    }

    public function testCallbacksAreCalledOnUpdate() {
        $temp_horse = new horse();
        $temp_horse->add();

        $horse = new horse($temp_horse->get_id());

        // Make sure the default values are set
        $this->assertEquals($horse->hooves_checked, false);
        $this->assertEquals($horse->height_measured, false);
        $this->assertEquals($horse->mane_brushed, false);
        $this->assertEquals($horse->tail_measured, false);

        // These shouldn't change after update
        $this->assertEquals($horse->saddle_checked, false);
        $this->assertEquals($horse->name_given, false);
        $this->assertEquals($horse->weighed, false);

        // Update the record
        $horse->name = 'Ed';

        // Make sure the callback sets the new values
        $this->assertEquals($horse->mane_brushed, true);
        $this->assertEquals($horse->tail_measured, true);
        $this->assertEquals($horse->hooves_checked, true);
        $this->assertEquals($horse->height_measured, true);

        // Make sure attributes didn't change if they weren't supposed to
        $this->assertEquals($horse->saddle_checked, false);
        $this->assertEquals($horse->name_given, false);
        $this->assertEquals($horse->weighed, false);
    }

    public function testCallbacksAreCalledOnDelete() {
        $temp_horse = new horse();
        $temp_horse->add();

        $horse = new horse($temp_horse->get_id());

        // Make sure the default values are set
        $this->assertEquals($horse->stable_cleaned, false);
        $this->assertEquals($horse->teeth_brushed, false);

        $horse->delete();

        // Make sure the callback sets the new values
        $this->assertEquals($horse->stable_cleaned, true);
        $this->assertEquals($horse->teeth_brushed, true);
    }

    public function testSetAttributesIfDefault() {
        $bandit = new db_object('bandit');

        $bandit->name ='Fantastic Mr. Fox';
        $bandit->farms_plundered = 2;
        $bandit->birthday = '2011-05-21';

        $bandit->add();

        $this->assertEquals('yes', $bandit->dangerous);

        $attributes = array(
            'name'            => 'Clyde',
            'dangerous'       => 'no',
            'farms_plundered' => 0,
            'birthday'        => '2012-12-21'
       );

        $bandit->set_attributes_if_default($attributes);

        $this->assertEquals('Fantastic Mr. Fox', $bandit->name);
        $this->assertEquals(2, $bandit->farms_plundered);
        $this->assertEquals('2011-05-21', $bandit->birthday);
        $this->assertEquals('no', $bandit->dangerous);
    }

    public function testGetAttributeOnDate() {

    }

    public function testGetObjectRestoredToDate() {

    }

    public function testGetFirstLoggedDate() {
        // Logging is not enabled for the farm class
        $farm = new farm;
        $this->assertFalse($farm->get_first_logged_date());

        // Logging is enabled for the bandit class
        $swiper = new bandit;
        $this->assertFalse($swiper->get_first_logged_date());

        $swiper->name = 'Swiper';
        $swiper->add();
        $this->assertEquals($swiper->inserted_on, $swiper->get_first_logged_date());

        $swiper->dangerous = 'no';
        $this->assertEquals($swiper->inserted_on, $swiper->get_first_logged_date());

        // Do not use the class in order to simulate a record existing before logging was enabled for the class
        $bandit = new db_object('bandit');
        $bandit->name = 'John';
        $bandit->add();

        $this->assertFalse($bandit->get_first_logged_date());

        // Get this same object with the actual class
        $dillinger = new bandit($bandit->get_id());
        $dillinger->birthday = '1903-06-22';

        // The first logged date should be set to the update done to the object of the bandit class
        $this->assertEquals($dillinger->updated_on, $dillinger->get_first_logged_date());
    }

    public function testLogAttributeChange() {
        // soft delete
        $bonnie = new bandit();
        $bonnie->name = 'Bonnie';
        $bonnie->add();

        $bonnie->delete();

         $log = db_object::find( array('table' => 'bandit', 'attribute' => 'deleted', 'record_id' => $bonnie->get_id()), 'db_object_log');
         $this->assertNotEquals(false, $log);
         $this->asssert

        // undelete
    }

    public function testLogChanges() {
        // Add

        // Update
    }
}

        $sql = "CREATE TABLE `bandit` (
            `bandit_id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY ,
            `name` VARCHAR(255) NOT NULL ,
            `farms_plundered` INT(11) NOT NULL DEFAULT '0',
            `money_stolen` FLOAT DEFAULT '0' ,
            `dangerous` ENUM('yes', 'no', 'maybe so', 'enum()') NOT NULL DEFAULT 'yes',
            `birthday` DATE,
            `email` VARCHAR(255) ,
            `inserted_by` INT NOT NULL ,
            `inserted_on` DATETIME NOT NULL ,
            `updated_by` INT NOT NULL ,
            `updated_on` DATETIME NOT NULL,
            `deleted` TINYINT(1) NULL DEFAULT '0'
       ) ENGINE=InnoDB";

class farm extends db_object
{
    function __construct($id=NULL, $table_info = NULL, $attributes = NULL)
    {
        parent::__construct('farm', $id, $table_info, $attributes);

        $this->has_one('barn');
    }
}

class barn extends db_object
{
    function __construct($id=NULL, $table_info = NULL, $attributes = NULL)
    {
        parent::__construct('barn', $id, $table_info, $attributes);

        $this->belongs_to('farm');
        $this->has_many('animals', 'barn');
    }
}


class animal extends db_object
{
    function __construct($id=NULL, $table_info = NULL, $attributes = NULL)
    {
        parent::__construct('animals', $id, $table_info, $attributes);

        $this->belongs_to('barn', 'barn');
    }
}

class bandit extends db_object {
    function __construct($id=NULL, $table_info = NULL, $attributes = NULL) {
        parent::__construct('bandit', $id, $table_info, $attributes);
        $this->logging_enabled = true;
    }
}

class horse extends animal
{
    public $saddle_checked = false;
    public $name_given = false;
    public $mane_brushed = false;
    public $hooves_checked = false;
    public $tail_measured = false;
    public $height_measured = false;
    public $weighed = false;
    public $teeth_brushed = false;
    public $stable_cleaned = false;

    function __construct($id=NULL, $table_info = NULL, $attributes = NULL) {
        parent::__construct($id, $table_info, $attributes);

        $this->before_add('check_saddle');
        $this->before_add('give_name');
        $this->before_update('check_hooves');
        $this->before_delete('brush_teeth');
        $this->before_save('measure_tail');
        $this->after_save('brush_mane');
        $this->after_delete('clean_stable');
        $this->after_update('measure_height');
        $this->after_add('weigh');
    }

    function check_saddle() {
        $this->saddle_checked = true;
    }

    function give_name() {
        $this->name_given = true;
    }

    function brush_mane() {
        $this->mane_brushed = true;
    }

    function check_hooves() {
        $this->hooves_checked = true;
    }

    function measure_tail() {
        $this->tail_measured = true;
    }

    function measure_height() {
        $this->height_measured = true;
    }

    function weigh() {
        $this->weighed = true;
    }

    function clean_stable() {
        $this->stable_cleaned = true;
    }

    function brush_teeth() {
        $this->teeth_brushed = true;
    }
}
?>
