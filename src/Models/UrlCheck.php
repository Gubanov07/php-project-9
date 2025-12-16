<?php

namespace App\Models;

use PDO;
use DiDom\Document;
use DiDom\Element;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

class UrlCheck
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function create(array $data): int
    {
        $sql = 'INSERT INTO url_checks (url_id, status_code, h1, title, description) VALUES (?, ?, ?, ?, ?)';
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            $data['url_id'],
            $data['status_code'],
            $data['h1'] ?? null,
            $data['title'] ?? null,
            $data['description'] ?? null
        ]);
        return $this->db->lastInsertId();
    }

    public function findByUrlId(int $urlId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM url_checks WHERE url_id = ? ORDER BY created_at DESC');
        $stmt->execute([$urlId]);
        return $stmt->fetchAll();
    }

    public function getLastCheck(int $urlId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM url_checks WHERE url_id = ? ORDER BY created_at DESC LIMIT 1');
        $stmt->execute([$urlId]);
        return $stmt->fetch();
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

            $this->create($checkData);

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
        return $h1Element ? trim($h1Element->text()) : null;
    }

    private function extractTitle(Document $document): ?string
    {
        $titleElement = $document->first('title');
        return $titleElement ? trim($titleElement->text()) : null;
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
