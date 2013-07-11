<?php

$store = new SchemaStore();

$url = "http://example.com/test-schema";
$schema = json_decode('{
	"title": "Test schema"
}');

$store->add($url, $schema);

if (!recursiveEqual($store->get($url), $schema)) {
	throw new Exception("Not equal");
}
if (!recursiveEqual($store->get($url."#/title"), $schema->title)) {
	throw new Exception("Not equal");
}

?>