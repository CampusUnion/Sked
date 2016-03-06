# Sked
Open source PHP Calendar library.

## Installation & Setup

First, run the SQL create statements on your database:

```
sql/create.sked_events.sql
sql/create.sked_event_members.sql
sql/create.sked_event_tags.sql
```

Then instantiate Sked in your code with data connection credentials:

```php
$sked = new \CampusUnion\Sked([
    'data_connection' => [
        'name' => 'PDO',
        'options' => [
            'type' => 'mysql',
            'host' => 'localhost',
            'dbname' => 'homestead',
            'user' => 'homestead',
            'pass' => 'secret',
        ],
    ],
]);
```

## Basic usage

Build a custom calendar by iterating through a specified date range:
```php
foreach ($sked->skeDates('2016-08-05', '2016-09-21')) {
    // do something awesome
}
```

## The SkeDate Object

A `SkeDate` object is useful for populating a particular date in your calendar UI.

Print the date using the `format()` method, which accepts a formatting
string as an optional parameter (see
[formatting options](http://php.net/manual/en/function.date.php#refsect1-function.date-parameters)
for PHP's `date` function):

```php
echo $skeDate->format('j'); // default format is 'Y-m-d' (e.g., 2016-08-05)
```

Then you can iterate through its events:
```php
foreach ($skeDate->skeVents(/* Optionally pass a user ID to get events for a certain user. */)) {
    // do something awesome
}
```

## The SkeVent Object

A `SkeVent` object allows for easy access and manipulation of an event.

Retrieve any database field (see SQL queries) as a property:

```php
echo $skeVent->label;
```

Print the time using the `time()` method, which accepts a formatting
string as an optional parameter (see
[formatting options](http://php.net/manual/en/function.date.php#refsect1-function.date-parameters)
for PHP's `date` function):

```php
echo $skeVent->time('Hi'); // default format is 'g:ia' (e.g., 2:15pm)
```

`SkeVent::time()` accepts a second optional parameter for timezone adjustment.
To adjust the event's time to a particular timezone, just pass in the timezone
offset integer:

```php
echo $skeVent->time(null, -5); // outputs the time adjusted for US Eastern Standard time
```
