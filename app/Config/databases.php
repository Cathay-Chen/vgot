<?php
/**
 * Created by PhpStorm.
 * User: Pader
 * Date: 2017/4/25
 * Time: 0:52
 */

return [
	'default' => [
		'dsn' => '',
		'host' => '127.0.0.1',
		'port' => 3306,
		'username' => 'root',
		'password' => '0000',
		'database' => 'test',
		'table_prefix' => '',
		'type' => 'mysql',
		'driver' => 'mysqli',
		'pconnect' => false,
		'charset' => 'utf8',
		'collate' => 'utf8_general_ci',
		'debug' => true
	],
	'sqlite3' => [
		'filename' => 'test.db',
		'driver' => 'sqlite3',
		'flags' => null, //see SQLite::__consturct() $flags
		'encryption_key' => null
	]
];
