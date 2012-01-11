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
> * Create a new database within your environment either using your own supplied parameters or by using the ones found in /test/db_object_test_SAMPLE.ini
> * Copy and rename the db_object_test_SAMPLE.ini file to just db_object_test.ini
> * Replace the parameter values with the ones you created or leave them as-is if you used the parameters that were supplied.

### Running The Tests

> * Make sure that you are in the root of your PHP directory (EX: C:\xampp\php>)
> * Go to your command line and type the following: phpunit "path-to-your-directory/db_object/test/name-of-test-file-without-extension"
> * EX: C:\xampp\php>phpunit "C:/xampp/htdocs/db_object/test/DBRecordsetTest"
