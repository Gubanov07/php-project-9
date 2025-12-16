<?php

namespace App\Validation;

use Valitron\Validator;

class UrlValidator
{
    public static function validate(array $data): array
    {
        $v = new Validator($data);
        $v->rule('required', 'name')->message('URL не должен быть пустым');
        $v->rule('lengthMax', 'name', 255)->message('URL превышает 255 символов');
        $v->rule('url', 'name')->message('Некорректный URL');

        if ($v->validate()) {
            return [];
        }

        $errors = [];
        $valitronErrors = $v->errors();

        if (is_array($valitronErrors)) {
            foreach ($valitronErrors as $field => $fieldErrors) {
                if (is_array($fieldErrors) && !empty($fieldErrors[0])) {
                    $errors[$field] = $fieldErrors[0];
                }
            }
        }
        return $errors;
    }

    public static function normalize(string $url): string
    {
        $parsedUrl = parse_url($url);
        $scheme = $parsedUrl['scheme'] ?? 'https';
        $host = $parsedUrl['host'] ?? '';
        $path = $parsedUrl['path'] ?? '';

        return "{$scheme}://{$host}{$path}";
    }
}
