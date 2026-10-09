<?php

declare(strict_types=1);

namespace App\Agovena\Content;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportFileUploads\FileUploadConfiguration;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Throwable;

final class SavePageWithImages
{
    public function __construct(
        private readonly SavePage $savePage,
        private readonly PageBodySanitizer $sanitizer,
    ) {}

    /**
     * @param  array{title: string, slug: string, body?: string|null, body_format?: string, status: string}  $data
     * @param  array<string, string>  $stagedImages  Signed preview URL to Livewire temporary filename.
     */
    public function handle(?int $pageId, array $data, array $stagedImages): void
    {
        $published = [];

        try {
            DB::transaction(function () use ($pageId, &$data, $stagedImages, &$published): void {
                $data['body'] = $this->promoteRetainedImages((string) ($data['body'] ?? ''), $stagedImages, $published);
                if ($pageId === null) {
                    $this->savePage->create($data);
                } else {
                    abort_if($this->savePage->update($pageId, $data) === null, 404);
                }
            });
        } catch (Throwable $exception) {
            // The filesystem cannot join the DB transaction; remove only files from this attempt.
            foreach ($published as $path) {
                Storage::disk('public')->delete($path);
            }
            throw $exception;
        }
    }

    /**
     * @param  array<string, string>  $stagedImages
     * @param  list<string>  $published
     */
    private function promoteRetainedImages(string $body, array $stagedImages, array &$published): string
    {
        if ($stagedImages === []) {
            return $body;
        }

        $markers = [];
        $body = preg_replace_callback('~<img\b(?:[^>"\']|"[^"]*"|\'[^\']*\')*>~i', static function (array $image) use ($stagedImages, &$markers): string {
            return preg_replace_callback('~\bsrc\s*=\s*(["\'])(.*?)\1~is', static function (array $source) use ($stagedImages, &$markers): string {
                $preview = html_entity_decode($source[2], ENT_QUOTES | ENT_HTML5, 'UTF-8');
                if (! isset($stagedImages[$preview])) {
                    return $source[0];
                }
                $marker = '/storage/pages/'.Str::random(40).'.png';
                $markers[$marker] = $stagedImages[$preview];

                return 'src='.$source[1].$marker.$source[1];
            }, $image[0]) ?? $image[0];
        }, $body) ?? $body;

        // Only sanitized images with descriptive alt text may become public files.
        $body = $this->sanitizer->sanitize($body);
        $promoted = [];

        return preg_replace_callback('~<img\b[^>]*>~i', static function (array $image) use ($markers, &$promoted, &$published): string {
            return preg_replace_callback('~\bsrc="([^"]*)"~i', static function (array $source) use ($markers, &$promoted, &$published): string {
                $marker = $source[1];
                if (! isset($markers[$marker])) {
                    return $source[0];
                }
                if (! isset($promoted[$markers[$marker]])) {
                    $file = TemporaryUploadedFile::createFromLivewire($markers[$marker]);
                    $temporaryPath = FileUploadConfiguration::path($markers[$marker], false);
                    if (! $file->exists() || Storage::disk(FileUploadConfiguration::disk())->size($temporaryPath) === 0) {
                        throw ValidationException::withMessages(['image' => __('validation.image', ['attribute' => 'image'])]);
                    }
                    Validator::make(['image' => $file], [
                        'image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:4096'],
                    ])->validate();
                    $extension = match ($file->getMimeType()) {
                        'image/jpeg' => 'jpg',
                        'image/png' => 'png',
                        'image/webp' => 'webp',
                        'image/gif' => 'gif',
                        default => throw ValidationException::withMessages(['image' => __('validation.image', ['attribute' => 'image'])]),
                    };
                    do {
                        $path = 'pages/'.Str::random(40).'.'.$extension;
                    } while (Storage::disk('public')->exists($path));
                    $stream = $file->readStream();
                    if (! is_resource($stream)) {
                        throw new \RuntimeException('Page image storage failed.');
                    }
                    $published[] = $path;
                    try {
                        if (! Storage::disk('public')->put($path, $stream)) {
                            throw new \RuntimeException('Page image storage failed.');
                        }
                    } finally {
                        fclose($stream);
                    }
                    $promoted[$markers[$marker]] = '/storage/'.$path;
                }

                return 'src="'.$promoted[$markers[$marker]].'"';
            }, $image[0]) ?? $image[0];
        }, $body) ?? $body;
    }
}
