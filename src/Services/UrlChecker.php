<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use DiDom\Document;
use App\Models\UrlCheck;

class UrlChecker
{
    private ?Client $client;
    private $urlCheckModel;

    public function __construct($urlCheckModel)
    {
        $this->client = null;
        $this->urlCheckModel = $urlCheckModel;
    }

    public function performCheck(int $urlId, string $url): array
    {
        $client = new Client([
        'timeout' => 10,
        'connect_timeout' => 10,
        'allow_redirects' => true,
        'headers' => [
            'User-Agent' => 'Mozilla/5.0 (compatible; PageAnalyzer/1.0)'
        ]
        ]);

        try {
            $response = $client->request('GET', $url, ['http_errors' => false]);
            $statusCode = $response->getStatusCode();
            $body = (string) $response->getBody();

            $document = new Document($body);

            // Извлекаем данные
            $h1 = $this->extractH1($document);
            $title = $this->extractTitle($document);
            $description = $this->extractDescription($document);

            $checkData = [
                'url_id' => $urlId,
                'status_code' => $statusCode,
                'h1' => $h1,
                'title' => $title,
                'description' => $description
            ];

            $this->urlCheckModel->create($checkData);

            return [
                'success' => true,
                'status_code' => $statusCode,
                'message' => $statusCode >= 200 && $statusCode < 300
                    ? 'Страница успешно проверена'
                    : "Страница проверена, но вернула код {$statusCode}"
            ];
        } catch (RequestException $e) {
            return [
                'success' => false,
                'message' => 'Произошла ошибка при проверке: Не удалось подключиться к сайту'
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Произошла непредвиденная ошибка при проверке'
            ];
        }
    }

    private function extractH1(Document $document): ?string
    {
        $h1Element = $document->first('h1');
        if ($h1Element && method_exists($h1Element, 'text')) {
            return trim($h1Element->text());
        }
        return null;
    }

    private function extractTitle(Document $document): ?string
    {
        $titleElement = $document->first('title');
        if ($titleElement && method_exists($titleElement, 'text')) {
            return trim($titleElement->text());
        }
        return null;
    }

    private function extractDescription(Document $document): ?string
    {
        $metaDescription = $document->first('meta[name="description"], meta[name="Description"]');
        if ($metaDescription) {
            $description = trim($metaDescription->getAttribute('content') ?? '');
            return empty($description) ? null : $description;
        }
        return null;
    }
}
