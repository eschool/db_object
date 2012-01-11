db_object is a PHP ORM built for MySQL.
db_recordset provides a way to fetch and manipulate a set of db_objects

Released under FREEBSD license
Copyright eSchool Consultants 2010-2011

Just include db_object.php in your project to start using db_object

# Overview

## db_object

### Create a new record
```php
$horse = new db_object('animal');
$horse->name = 'Mr. Ed';
$horse->add();
```

### Modify an existing record
```php
$cat = new db_object('animal', 12);
$cat->name = 'Keyboard Cat';
```

### Delete a record
```php
$cat = new db_object('animal', 12);
$cat->delete();
```

## db_recordset

### Fetch a set of records
```php
$animals_in_barn_3 = new db_recordset('animal', array('barn' => 3));
foreach ($animals_in_barn_3 as $animal) {
    $animal->barn = 4;
}
```

## Testing

### Installing PHPUnit
> * Make sure you have installed the latest version of PEAR
> * Go to PHPUnit's installation instructions page and follow the instructions: http://www.phpunit.de/manual/current/en/installation.html
> * Make sure that you are in the root of your PHP directory and type "phpunit" to make sure that everything has installed correctly

### Create a New Database
> * Create a new database within your environment either using your own supplied parameters or by using the ones found in /test/db_object_test_SAMPLE.ini (make sure to use InnoDB)
> * Copy and rename the db_object_test_SAMPLE.ini file to just db_object_test.ini
> * Replace the parameter values with the ones you created or leave them as-is if you used the parameters that were supplied.

### Running The Tests
> * The db_object tests were setup using PHPUnit v3.4 so if you're using 3.6 or newer there will be some PHPUnit methods that are no longer valid such as assertType();
> * If there is an error when running one of the tests and it says something about not being able to find the "PHPUnit_Framework_TestCase" class just make sure that you include the following line and modify the path if necessary:
```php
require_once 'PHPUnit/Autoload.php';
```
> * Make sure that you are in the root of your PHP directory (EX: C:\xampp\php>)
> * Go to your command line and type the following: phpunit "path-to-your-directory/db_object/test/name-of-test-file-without-extension"
> * EX: C:\xampp\php>phpunit "C:/xampp/htdocs/db_object/test/DBRecordsetTest"
