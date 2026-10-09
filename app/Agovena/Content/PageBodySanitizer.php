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
        $safe = $this->sanitizer->sanitize($html);

        // The allowlist filters attributes, but it cannot require both a source and descriptive alt text.
        return preg_replace_callback('~<img\\b[^>]*>~i', static function (array $match): string {
            if (preg_match('~\\bsrc="/storage/pages/[A-Za-z0-9]{40}\\.(?:jpg|jpeg|png|webp|gif)"~i', $match[0]) !== 1
                || preg_match('~\\balt="([^"]*)"~i', $match[0], $alt) !== 1) {
                return '';
            }

            $description = html_entity_decode($alt[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');

            return preg_match('/[^\\p{Z}\\p{C}]/u', $description) === 1 ? $match[0] : '';
        }, $safe) ?? '';
    }

    public function fromPlainText(string $text): string
    {
        if ($text === '') {
            return '';
        }

        return '<p>'.str_replace(["\r\n", "\r", "\n"], '<br>', e($text)).'</p>';
    }
}
