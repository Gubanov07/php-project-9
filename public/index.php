<?php

use DI\Container;
use Slim\Factory\AppFactory;
use Slim\Views\PhpRenderer;

require __DIR__ . '/../vendor/autoload.php';

$container = new Container();
AppFactory::setContainer($container);

$container->set('renderer', function () {
    return new PhpRenderer(__DIR__ . '/../templates');
});

$app = AppFactory::create();
$app->addErrorMiddleware(true, true, true);

$app->get('/', function ($request, $response) {
    return $this->get('renderer')->render($response, 'index.phtml');
});

$app->run();