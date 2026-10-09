<?php

declare(strict_types=1);

namespace App\Agovena\Content;

use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

final class PageBodySanitizer
{
    private readonly HtmlSanitizer $sanitizer;

    public function __construct()
    {
        $config = (new HtmlSanitizerConfig)
            ->allowElement('p')
            ->allowElement('br')
            ->allowElement('h2')
            ->allowElement('h3')
            ->allowElement('h4')
            ->allowElement('strong')
            ->allowElement('em')
            ->allowElement('ul')
            ->allowElement('ol')
            ->allowElement('li')
            ->allowElement('blockquote')
            ->allowElement('img', ['src', 'alt'])
            ->allowRelativeMedias()
            ->withAttributeSanitizer(new PageImageSourceSanitizer)
            ->allowElement('a', ['href', 'target'])
            ->allowLinkSchemes(['http', 'https', 'mailto'])
            ->allowRelativeLinks()
            ->forceAttribute('a', 'rel', 'noopener noreferrer');

        $this->sanitizer = new HtmlSanitizer($config);
    }

    public function sanitize(string $html): string
    {
        return $this->sanitizer->sanitize($html);
    }

    public function fromPlainText(string $text): string
    {
        if ($text === '') {
            return '';
        }

        return '<p>'.str_replace(["\r\n", "\r", "\n"], '<br>', e($text)).'</p>';
    }
}
