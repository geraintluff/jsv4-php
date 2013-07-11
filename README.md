# jsv4-php

## A (coercive) JSON Schema v4 Validator for PHP

`jsv4-php` is a data validator, using version 4 JSON Schemas.

Just include `jsv4.php` from your code, and use the static methods on the `Jsv4` class it defines.

Usage:

### `Jsv4::validate($data, $schema)`

This usage returns an object of the following shape.
```json
{
    "valid": true/false,
    "errors": [
        {...}
    ]
}
```

The values in the `errors` array are similar to those for [tv4](https://github.com/geraintluff/tv4) (a similar project):

```json
{
    "code": 0,
    "message": "Invalid type: string",
    "dataPath": "/intKey",
    "schemaKey": "/properties/intKey/type"
}
```

The `code` property corresponds to a constant corresponding to the nature of the validation error, e.g. `JSV4_INVALID_TYPE`.  The names of these constants (and their values) match up exactly with the [constants from tv4](https://github.com/geraintluff/tv4/blob/master/source/api.js).

### `Jsv4::isValid($data, $schema)`

If you just want to know the validation status, and don't care what the errors actually are, then this is a more concise way of getting it.

It returns a boolean indicating whether the data correctly followed the schema.

### `Jsv4::coerce($data, $schema)`

Sometimes, the data is not quite the correct shape - but it could be *made* the correct shape by simple modifications.

If you call `Jsv4::coerce($data, $schema)`, then it will attempt to change the data.

If it is successful, then a modified version of the data can be found in `$result->value`.

It's not psychic - in fact, it's quite limited.  What it currently does is:

### Type-coercion for scalar types

Perhaps you are using data from `$_GET`, so everything's a string, but the schema says certain values should be integers or booleans.

`Jsv4::coerce()` will attempt to convert strings to numbers/booleans *only where the schema says*, leaving other numerically-value strings as strings.

### Missing properties

Perhaps the API needs a complete object (described using `"required"` in the schema), but only a partial one was supplied.

`Jsv4::coerce()` will attempt to insert appropriate values for the missing properties, using a default (if it is defined in a nearby `"properties"` entry) or by creating a value if it knows the type.

## The `SchemaStore` class

This class represents a collection of schemas.  You include it from `schema-store.php`, and use it like this:
```php
$store = new SchemaStore();
$store->add($url, $schema);
$retrieved = $store->get($url);
```

It can handle:

* Fragments in URLs, using both JSON Pointer fragments and identification using `"id"`
* Converts URIs in `"id"` and `"$ref"` to absolute (where possible)
* Resolves `"$ref"`s, splicing the resulting value into the schema
* Converts associative PHP arrays to objects - you can express your schema natively, but what you retrieve from the store is always an object.
* Adds sub-schemas according to their `"id"` - by default, this only happens if `"id"` is a sub-path of the current schema URL.

If the schemas being added are "trusted", then an extra argument can be supplied: `$store->add($url, $schema, TRUE)`.  In that case, the value of `"id"` is *always* believed for all sub-schemas.

A list of "missing" schemas (unresolved `"$ref"`s) can be retrieved used `$store->missing()`.

This class does not depend on `jsv4.php` at all - it just deals with raw schema objects.  As such, it could (hopefully) be used with other validators with minimal fuss.

## Tests

The tests can be run using `test.php` (from the command-line or via the web).

## License

This code is released under a do-anything-you-like "public domain" license (see `LICENSE.txt`).

It is also released under an MIT-style license (see `LICENSE-MIT.txt`) because there is sometimes benefit in having a recognised open-source license.