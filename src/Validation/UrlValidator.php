<?php

namespace App\Validation;

use Valitron\Validator;

class UrlValidator
{
    public static function validate($data)
    {
        $v = new Validator($data);
        $v->rule('required', 'name')->message('URL не должен быть пустым');
        $v->rule('lengthMax', 'name', 255)->message('Некорректный URL');
        $v->rule('url', 'name')->message('Некорректный URL');

        if ($v->validate()) {
            return [];
        }

        $errors = [];
        foreach ($v->errors() as $field => $fieldErrors) {
            $errors[$field] = $fieldErrors[0] ?? '';
        }
        return $errors;
    }

    public static function normalize($url)
    {
        $parsedUrl = parse_url($url);
        $scheme = $parsedUrl['scheme'] ?? 'https';
        $host = $parsedUrl['host'] ?? '';
        $path = $parsedUrl['path'] ?? '';

        return "{$scheme}://{$host}{$path}";
    }
}
