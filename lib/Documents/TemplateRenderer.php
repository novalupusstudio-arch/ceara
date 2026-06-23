<?php

declare(strict_types=1);

namespace Ceara\Documents;

final class TemplateRenderer
{
    public function render(string $html, array $variables): string
    {
        $replace = [];
        foreach ($variables as $key => $value) {
            $replace['[' . $key . ']'] = (string) $value;
        }

        return strtr($html, $replace);
    }

    public function wrapDocument(string $body): string
    {
        return '<!doctype html><html lang="ro"><head><meta charset="utf-8"><title>Document</title></head><body>' . $body . '</body></html>';
    }
}
