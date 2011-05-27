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
