<?php

function profile_photo_url(?string $path): ?string
{
    if (empty($path)) {
        return null;
    }

    $relative = ltrim(str_replace('\\', '/', $path), '/');
    if (strpos($relative, '..') !== false) {
        return null;
    }

    $absolute = realpath(__DIR__ . '/../' . str_replace('/', DIRECTORY_SEPARATOR, $relative));
    $root = realpath(__DIR__ . '/../uploads/profiles');
    if (!$absolute || !$root || strpos($absolute, $root) !== 0 || !is_file($absolute)) {
        return null;
    }

    return app_url($relative) . '?v=' . filemtime($absolute);
}

function avatar_initials(?string $first, ?string $last, ?string $fallback = ''): string
{
    $first = trim((string) $first);
    $last = trim((string) $last);
    if ($first !== '' || $last !== '') {
        return strtoupper(substr($first, 0, 1) . substr($last, 0, 1));
    }

    $fallback = trim($fallback);
    return strtoupper(substr($fallback !== '' ? $fallback : 'U', 0, 2));
}

function render_avatar(?string $first, ?string $last, ?string $photoPath, ?string $fallback = '', string $class = 'avatar', string $style = ''): string
{
    $url = profile_photo_url($photoPath);
    $label = trim(trim((string) $first) . ' ' . trim((string) $last));
    if ($label === '') {
        $label = $fallback ?: 'User';
    }

    $styleAttr = $style !== '' ? ' style="' . htmlspecialchars($style, ENT_QUOTES, 'UTF-8') . '"' : '';
    $classAttr = htmlspecialchars($class, ENT_QUOTES, 'UTF-8');
    $titleAttr = htmlspecialchars($label, ENT_QUOTES, 'UTF-8');

    if ($url) {
        return '<div class="' . $classAttr . ' avatar-photo"' . $styleAttr . ' title="' . $titleAttr . '"><img src="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '" alt="' . $titleAttr . '"></div>';
    }

    return '<div class="' . $classAttr . '"' . $styleAttr . ' title="' . $titleAttr . '">' . htmlspecialchars(avatar_initials($first, $last, $fallback), ENT_QUOTES, 'UTF-8') . '</div>';
}
