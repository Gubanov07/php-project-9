<?php

namespace App\Validation;

use Valitron\Validator;

class UrlValidator
{
    public static function validate($data)
    {
        $v = new Validator($data);
        $v->rule('required', 'name')->message('URL не должен быть пустым');
        $v->rule('lengthMax', 'name', 255)->message('URL превышает 255 символов');
        $v->rule('url', 'name')->message('Некорректный URL');

        return $v->validate() ? [] : $v->errors();
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