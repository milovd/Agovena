<?php

$publicRoot = __DIR__.'/../public';
$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$requestPath = is_string($requestPath) ? rawurldecode($requestPath) : '/';
$relativePath = ltrim(str_replace('\\', '/', $requestPath), '/');

if (
    $relativePath !== ''
    && ! str_contains($relativePath, "\0")
    && ! preg_match('#(?:^|/)\.\.(?:/|$)#', $relativePath)
    && is_file($publicRoot.'/'.str_replace('/', DIRECTORY_SEPARATOR, $relativePath))
) {
    return false;
}

require $publicRoot.'/index.php';
