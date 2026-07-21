<?php

use Webman\Route;
use plugin\nanoadmin\app\middleware\InstallGuard;

Route::group('', function () {
    Route::get('/', function () {
        return 'Hello World';
    });

    require_once __DIR__ . '/../app/route/route.php';
    require_once __DIR__ . '/../app/library/swagger/Register.php';
})->middleware([InstallGuard::class]);
