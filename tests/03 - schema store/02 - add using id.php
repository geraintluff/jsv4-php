<?php

$store = new SchemaStore();

$urlBase = "http://example.com/";
$url = $urlBase."test-schema";
$schema = json_decode('{
	"title": "Test schema",
	"properties": {
		"foo": {
			"id": "#foo"
		},
		"bar": {
			"id": "/bar"
		},
		"baz": {
			"id": "?baz=1"
		},
		"foobar": {
			"id": "test-schema/foobar"
		},
		"nestedSchema": {
			"id": "/test-schema/foo",
			"nested": {
				"id": "#bar"
			}
		},
		"testSchemaFoo": {
		    "id": "/test-schema-foo"
		},
		"somewhereElse": {
			"id": "http://somewhere-else.com/test-schema"
		}
	}
}');

$store->add($url, $schema);

if (!recursiveEqual($store->get($url."#foo"), $schema->properties->foo)) {
	throw new Exception("#foo not found");
}

if (!recursiveEqual($store->get($url."?baz=1"), $schema->properties->baz)) {
	throw new Exception("?baz=1 not found");
}

if (!recursiveEqual($store->get($url."/foobar"), $schema->properties->foobar)) {
	throw new Exception("/foobar not found");
}

if (!recursiveEqual($store->get($url."/foo#bar"), $schema->properties->nestedSchema->nested)) {
	throw new Exception("/foo#bar not found");
}

if ($store->get($urlBase."bar")) {
	throw new Exception("/bar should not be indexed, as it should not be trusted");
}

if ($store->get($url."-foo")) {
	throw new Exception("/test-schema-foo should not be indexed, as it should not be trusted");
}

if ($store->get("http://somewhere-else.com/test-schema")) {
	throw new Exception("http://somewhere-else.com/test-schema should not be indexed, as it should not be trusted");
}

$store->add($url, $schema, TRUE);

if (!recursiveEqual($store->get($urlBase."bar"), $schema->properties->bar)) {
	throw new Exception("/bar not found");
}

?>