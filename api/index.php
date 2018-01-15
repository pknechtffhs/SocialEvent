<?php
use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;
define('IncludeCheck',TRUE);

require 'lib/autoload.php';

$settings = require __DIR__ . '/src/settings.php';
$app = new \Slim\App($settings);

// Globale Funktionen laden
require __DIR__ . '/src/globalFunctions.php';

// Container laden
require __DIR__ . '/src/containers.php';

// Routen registrieren
require __DIR__ . '/src/routes.php';

$app->run();
?>