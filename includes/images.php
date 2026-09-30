<?php
declare(strict_types=1);
function localPhoto(?string $path): ?string
{
    if ($path === null || !preg_match('~^assets/images/[a-zA-Z0-9_-]+\.(jpg|jpeg|png|webp)$~D', $path)) { return null; }
    return is_file(dirname(__DIR__) . '/' . $path) ? $path : null;
}
