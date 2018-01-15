<?php
if(!defined('IncludeCheck')) {
   die('Direct access not permitted');
}

$debug = false;  //Entwicklungsmodus an/aus
error_reporting(E_ERROR | E_WARNING | E_PARSE);

return [
    'settings' => [
        'displayErrorDetails' => $debug, 
        'addContentLengthHeader' => false, 
		
		// Datenbankeinstellungen
		'db' => [
			'host' => 'localhost',
			'user' =>  'bzoqdpus_db',
			'pass' =>  'Asdf1234',
			'dbname' => 'bzoqdpus_socialevent',
		],

        // Monolog-Einstellungen
        'logger' => [
            'name' => 'socialevent-api',
            'path' => __DIR__ . '/../logs/socialevent-api.log',
            'level' => ($debug) ? \Monolog\Logger::DEBUG : \Monolog\Logger::WARNING,
        ],
    ],
];
?>