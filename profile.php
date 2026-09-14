<?php

require_once __DIR__ . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register();

require_once __DIR__ . '/footer_component.php';

use App\Controllers\ProfileController;

$controller = new ProfileController();
$controller->handle();
