<?php

use Webman\Route;

Route::get('/', function () {
    return 'Hello World';
});

require_once __DIR__ . '/../app/route/route.php';

require_once public_path() . '/install/route.php';

require_once __DIR__ . '/../app/library/swagger/Register.php';
