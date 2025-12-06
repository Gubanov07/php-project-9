<?php

use DiDom\Document;
use DiDom\Exceptions\InvalidSelectorException;
use DI\Container;
use Slim\Factory\AppFactory;
use Slim\Views\PhpRenderer;
use Slim\Flash\Messages;
use App\Database;
use App\Models\Url;
use App\Models\UrlCheck;
use App\Validation\UrlValidator;

require __DIR__ . '/../vendor/autoload.php';

$container = new Container();
AppFactory::setContainer($container);

$container->set('httpClient', fn() => new Client([
    'timeout' => 5,
    'connect_timeout' => 5,
    'allow_redirects' => true,
]));

$container->set('renderer', fn() => new PhpRenderer(__DIR__ . '/../templates'));
$container->set('flash', fn() => new Messages());
$container->set('db', fn() => Database::getInstance()->getConnection());
$container->set('urlModel', fn($c) => new Url($c->get('db')));
$container->set('urlCheckModel', fn($c) => new UrlCheck($c->get('db')));
$container->set('renderer', function () {
    return new PhpRenderer(__DIR__ . '/../templates', ['layout.phtml']);
});

$app = AppFactory::create();
$app->addErrorMiddleware(true, true, true);

// Маршруты
$app->get('/', function ($request, $response) {
    return $this->get('renderer')->render($response, 'index.phtml', [
        'title' => 'Анализатор страниц'
    ]);
})->setName('home');

$app->get('/urls', function ($request, $response) {
    $urls = $this->get('urlModel')->all();
    $urlCheckModel = $this->get('urlCheckModel');
    
    $urlsWithChecks = array_map(fn($url) => [
        ...$url,
        'last_check' => $urlCheckModel->getLastCheck($url['id'])
    ], $urls);

    return $this->get('renderer')->render($response, 'urls/index.phtml', [
        'urls' => $urlsWithChecks,
        'flash' => $this->get('flash')
    ]);
})->setName('urls.index');

$app->get('/urls/{id}', function ($request, $response, $args) {
    $url = $this->get('urlModel')->find($args['id']);
    
    if (!$url) {
        return $response->withStatus(404)->write('Page not found');
    }

    return $this->get('renderer')->render($response, 'urls/show.phtml', [
        'url' => $url,
        'checks' => $this->get('urlCheckModel')->findByUrlId($args['id']),
        'flash' => $this->get('flash')
    ]);
})->setName('urls.show');

$app->post('/urls', function ($request, $response) {
    $urlName = $request->getParsedBody()['url']['name'] ?? '';
    $errors = UrlValidator::validate(['name' => $urlName]);
    
    if (!empty($errors)) {
        return $this->get('renderer')->render($response->withStatus(422), 'index.phtml', [
            'url' => ['name' => $urlName],
            'errors' => $errors,
            'flash' => $this->get('flash')
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
    
    return $response->withRedirect($this->get('renderer')->getRouteParser()->urlFor('urls.show', ['id' => $urlId]));
});

$app->post('/urls/{id}/checks', function ($request, $response, $args) {
    $urlId = $args['id'];
    $urlModel = $this->get('urlModel');
    $urlCheckModel = $this->get('urlCheckModel');
    $httpClient = $this->get('httpClient');
    
$url = $urlModel->find($urlId);
    if (!$url) {
        return $response->withStatus(404)->write('Page not found');
    }

    $urlName = $url['name'];
    
    try {
        $res = $httpClient->request('GET', $urlName, [
            'http_errors' => false,
            'timeout' => 10,
            'connect_timeout' => 10,
            'headers' => [
                'User-Agent' => 'Mozilla/5.0 (compatible; PageAnalyzer/1.0)'
            ]
        ]);
        
        $statusCode = $res->getStatusCode();
        $body = (string) $res->getBody();
        
        $document = new Document($body);
        
        $h1Element = optional($document->first('h1'));
        $h1 = $h1Element->text() ? trim($h1Element->text()) : null;
        
        $titleElement = optional($document->first('title'));
        $title = $titleElement->text() ? trim($titleElement->text()) : null;
        
        $description = null;
        $metaDescription = $document->first('meta[name="description"]');
        if ($metaDescription) {
            $description = trim($metaDescription->getAttribute('content') ?? '');
            if (empty($description)) {
                $description = null;
            }
        }
        
        if (!$description) {
            $metaDescription = $document->first('meta[name="Description"]');
            if ($metaDescription) {
                $description = trim($metaDescription->getAttribute('content') ?? '');
                if (empty($description)) {
                    $description = null;
                }
            }
        }
        
        $checkData = [
            'url_id' => $urlId,
            'status_code' => $statusCode,
            'h1' => $h1,
            'title' => $title,
            'description' => $description
        ];
        
        $urlCheckModel->create($checkData);
        
        if ($statusCode >= 200 && $statusCode < 300) {
            $this->get('flash')->addMessage('success', 'Страница успешно проверена');
        } else {
            $this->get('flash')->addMessage('warning', "Страница проверена, но вернула код {$statusCode}");
        }
        
    } catch (RequestException $e) {
        $this->get('flash')->addMessage('error', 'Произошла ошибка при проверке: Не удалось подключиться к сайту');
    } catch (InvalidSelectorException $e) {
        $this->get('flash')->addMessage('error', 'Произошла ошибка при анализе HTML страницы');
    } catch (Exception $e) {
        $this->get('flash')->addMessage('error', 'Произошла непредвиденная ошибка при проверке');
    }
    
    return $response->withRedirect($this->get('renderer')->getRouteParser()->urlFor('urls.show', ['id' => $urlId]));
})->setName('urls.checks');
    
function extractH1($html) {
    if (preg_match('/<h1[^>]*>(.*?)<\/h1>/is', $html, $matches)) {
        return trim(strip_tags($matches[1]));
    }
    return null;
}

function extractTitle($html) {
    if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $matches)) {
        return trim(strip_tags($matches[1]));
    }
    return null;
}

function extractDescription($html) {
    if (preg_match('/<meta[^>]*name=["\']description["\'][^>]*content=["\'](.*?)["\']/is', $html, $matches)) {
        return trim($matches[1]);
    }
    return null;
}

/**
 * Хелпер optional() для безопасного обращения к свойствам
 * 
 * @param mixed $value
 * @return mixed
 */
function optional($value)
{
    return new class($value) {
        private $value;
        
        public function __construct($value)
        {
            $this->value = $value;
        }
        
        public function __get($name)
        {
            if ($this->value && isset($this->value->$name)) {
                return $this->value->$name;
            }
            return null;
        }
        
        public function __call($name, $arguments)
        {
            if ($this->value && method_exists($this->value, $name)) {
                return call_user_func_array([$this->value, $name], $arguments);
            }
            
            return new self(null);
        }
        
        public function __toString()
        {
            return $this->value ? (string) $this->value : '';
        }
        
        public function text()
        {
            return $this->value ? $this->value->text() : '';
        }
    };
}

$app->run();