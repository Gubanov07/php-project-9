<?php

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use App\Database;
use App\Models\Url;
use App\Models\UrlCheck;
use App\Validation\UrlValidator;
use App\Services\UrlChecker;
use DI\Container;
use Slim\Factory\AppFactory;
use Slim\Views\PhpRenderer;
use Slim\Flash\Messages;
use Slim\Interfaces\RouteParserInterface;

require __DIR__ . '/../vendor/autoload.php';

// Контейнер
$container = new Container();
AppFactory::setContainer($container);
$app = AppFactory::create();

$app->add(function ($request, $handler) {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    return $handler->handle($request);
});

$container->set('httpClient', fn() => new Client([
    'timeout' => 5,
    'connect_timeout' => 5,
    'allow_redirects' => true,
]));

$container->set(RouteParserInterface::class, function () use ($app) {
    return $app->getRouteCollector()->getRouteParser();
});

// Сервисы
$container->set('flash', fn() => new Messages());
$container->set('db', fn() => Database::getInstance()->getConnection());
$container->set('urlModel', fn($c) => new Url($c->get('db')));
$container->set('urlCheckModel', fn($c) => new UrlCheck($c->get('db')));
$container->set('urlChecker', fn($c) => new UrlChecker($c->get('urlCheckModel')));
$container->set('renderer', function ($container) {
    return new PhpRenderer(__DIR__ . '/../templates');
});


$app->addErrorMiddleware(true, true, true);

// Маршруты
$app->get('/', function ($request, $response) {
    $params = [
        'itemMenu' => 'main',
        'url' => ['name' => ''],
        'errors' => [],
        'messages' => $this->get('flash')->getMessages(),
        'flash' => $this->get('flash'),
        'router' => $this->get(RouteParserInterface::class)
    ];
    return $this->get('renderer')->render($response, 'index.phtml', $params);
})->setName('home');

// Urls
$app->get('/urls', function ($request, $response) {
    $urls = $this->get('urlModel')->getAllWithLastCheck();

    $params = [
        'itemMenu' => 'urls',
        'urls' => $urls,
        'messages' => $this->get('flash')->getMessages(),
        'flash' => $this->get('flash'),
        'router' => $this->get(RouteParserInterface::class)
    ];

    return $this->get('renderer')->render($response, 'urls.phtml', $params);
})->setName('urls.index');

$app->get('/urls/{id:[0-9]+}', function ($request, $response, $args) {
    $url = $this->get('urlModel')->find($args['id']);

    if (!$url) {
        return $response->withStatus(404)->write('Page not found');
    }

    $checks = $this->get('urlCheckModel')->findByUrlId($args['id']);

    $params = [
        'url' => $url,
        'checks' => $checks,
        'messages' => $this->get('flash')->getMessages(),
        'flash' => $this->get('flash'),
        'router' => $this->get(RouteParserInterface::class)
    ];

    return $this->get('renderer')->render($response, 'show.phtml', $params);
})->setName('urls.show');

$app->post('/urls', function ($request, $response) {
    $urlName = $request->getParsedBody()['url']['name'] ?? '';
    $errors = UrlValidator::validate(['name' => $urlName]);

    if (!empty($errors)) {
        $errorMessage = $errors['name'] ?? 'Ошибка валидации';
        $this->get('flash')->addMessage('error', $errorMessage);
        return $this->get('renderer')->render($response->withStatus(422), 'index.phtml', [
            'url' => ['name' => $urlName],
            'errors' => $errors,
            'messages' => $this->get('flash')->getMessages(), // Уже передаем сообщения
            'flash' => $this->get('flash'),
            'router' => $this->get(RouteParserInterface::class)
        ]);
    }

    $normalizedUrl = UrlValidator::normalize($urlName);
    $urlModel = $this->get('urlModel');
    $existingUrl = $urlModel->findByName($normalizedUrl);

    if ($existingUrl) {
        $this->get('flash')->addMessage('success', 'Страница уже существует');
        $urlId = $existingUrl['id'];
    } else {
        $urlId = $urlModel->create($normalizedUrl);
        $this->get('flash')->addMessage('success', 'Страница успешно добавлена');
    }

    $routeParser = $this->get(RouteParserInterface::class);
    return $response
        ->withHeader('Location', $routeParser->urlFor('urls.show', ['id' => $urlId]))
        ->withStatus(302);
});

// Проверка адреса
$app->post('/urls/{id:[0-9]+}/checks', function ($request, $response, $args) {
    $urlId = $args['id'];
    $urlModel = $this->get('urlModel');
    $urlChecker = $this->get('urlChecker');

    $url = $urlModel->find($urlId);
    if (!$url) {
        return $response->withStatus(404)->write('Page not found');
    }

    $result = $urlChecker->performCheck($urlId, $url['name']);

    $messageType = $result['success'] ? ($result['status_code'] >= 200 && $result['status_code'] < 300 ?
    'success' : 'warning') : 'error';
    $this->get('flash')->addMessage($messageType, $result['message']);

    $routeParser = $this->get(RouteParserInterface::class);
    return $response
        ->withHeader('Location', $routeParser->urlFor('urls.show', ['id' => $urlId]))
        ->withStatus(302);
})->setName('urls.checks');

$app->run();
