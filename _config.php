<?php

global $sourceDatabaseConfig;
$sourceDatabaseConfig = array (
	'type' => defined('SS_SOURCE_DATABASE_TYPE') ? SS_SOURCE_DATABASE_TYPE : null,
	'username' => defined('SS_SOURCE_DATABASE_USERNAME') ? SS_SOURCE_DATABASE_USERNAME: null,
	'password' => defined('SS_SOURCE_DATABASE_PASSWORD') ? SS_SOURCE_DATABASE_PASSWORD : null,
	'server' => defined('SS_SOURCE_DATABASE_SERVER') ?  SS_SOURCE_DATABASE_SERVER : null,
	'database' => defined('SS_SOURCE_DATABASE_NAME') ? SS_SOURCE_DATABASE_NAME : null
);