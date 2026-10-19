<?php

declare(strict_types=1);

use DG\BypassFinals;

/*
 * Bootstrap for the Application (Feature/Integration) suites. Mirrors the
 * polis-laravel pattern: register the dummy consumer app's namespace here
 * (NOT in composer.json's autoload-dev) so it never leaks into the standalone
 * Unit suite.
 */

$loader = require __DIR__.'/../vendor/autoload.php';

$loader->addPsr4('App\\', __DIR__.'/Application/app/');

BypassFinals::enable();
