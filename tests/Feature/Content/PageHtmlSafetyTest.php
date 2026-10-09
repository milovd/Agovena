<?php

declare(strict_types=1);

use App\Agovena\Content\PageBodySanitizer;
use App\Agovena\Content\SavePage;
use App\Models\Page;

it('rejects active markup and visual overrides while preserving the limited editorial structure', function (): void {
    $html = '<h1>Wrong title</h1><h2 style="color:red" onclick="alert(1)">Section</h2>'
        .'<p>Text <strong>strong</strong> <em>emphasis</em></p>'
        .'<script>alert(1)</script><a href="javascript:alert(1)" target="_blank">bad</a>'
        .'<a href="https://example.com" target="_blank" style="display:none">good</a>';

    $safe = app(PageBodySanitizer::class)->sanitize($html);

    expect($safe)->toContain('<h2>Section</h2>', '<strong>strong</strong>', '<em>emphasis</em>', 'https://example.com')
        ->not->toContain('<h1', 'style=', 'onclick=', '<script', 'javascript:', 'alert(1)');
    expect($safe)->toContain('noopener noreferrer');
});

it('allows only uploaded local page images and preserves their alt text', function (): void {
    $valid = '/storage/pages/'.str_repeat('a', 40).'.png';
    $safe = app(PageBodySanitizer::class)->sanitize('<img src="'.$valid.'" alt="Diagram" width="900">'
        .'<img src="https://evil.example/tracker.png" alt="Tracker">'
        .'<img src="/storage/../secrets.png" alt="Traversal">');

    expect($safe)->toContain('src="'.$valid.'"', 'alt="Diagram"')
        ->not->toContain('width=', 'evil.example', '../secrets.png');
});

it('removes images missing an accessible description or a safe source after sanitization', function (): void {
    $valid = '/storage/pages/'.str_repeat('a', 40).'.png';
    $safe = app(PageBodySanitizer::class)->sanitize('<p>Keep this</p>'
        .'<img src="'.$valid.'">'
        .'<img src="'.$valid.'" alt="  ">'
        .'<img src="'.$valid.'" alt="&nbsp;">'
        .'<img src="https://example.com/track.png" alt="Tracker">'
        .'<img src="'.$valid.'" alt="An accessible chart">');
    expect($safe)->toContain('<p>Keep this</p>', 'alt="An accessible chart"')
        ->not->toContain('Tracker', 'alt="  "', 'alt="&nbsp;"');
    expect(substr_count($safe, '<img'))->toBe(1);
});

it('keeps existing plain text escaped and sanitizes only explicitly marked HTML on the storefront', function (): void {
    $plain = Page::query()->create([
        'title' => 'Plain', 'slug' => 'plain', 'body' => '<script>alert(1)</script>'."\n".'Second line', 'status' => 'published',
    ]);
    $html = Page::query()->create([
        'title' => 'Rich', 'slug' => 'rich', 'body' => '<h2>Safe</h2><script>alert(2)</script>',
        'body_format' => 'html', 'status' => 'published',
    ]);

    $this->get('/plain')->assertOk()->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
    $this->get('/rich')->assertOk()->assertSee('<h2>Safe</h2>', false)->assertDontSee('<script>alert(2)</script>', false);
});

it('sanitizes explicitly rich content at the domain write boundary, including updates', function (): void {
    $save = app(SavePage::class);
    $page = $save->create([
        'title' => 'Rich', 'slug' => 'rich', 'status' => 'draft', 'body_format' => 'html',
        'body' => '<h2 onclick="attack()">Safe</h2><script>attack()</script>',
    ]);
    expect($page->body)->toBe('<h2>Safe</h2>');

    $save->update($page->id, [
        'title' => 'Rich', 'slug' => 'rich', 'status' => 'published', 'body_format' => 'html',
        'body' => '<a href="javascript:attack()">Unsafe link</a>',
    ]);
    expect($page->fresh()->body)->not->toContain('javascript:', 'attack()');
});
