<?php

declare(strict_types=1);

use Dom\HTMLDocument;

return function (CategoryDAO $category, int $target_favicon_size = 96): Link {
    $url = $_POST['url'];
    $querypos = strpos($url, '?');
    $startpos = strpos($url, '/') + 2;
    $trailpos = strpos($url, '/', $startpos) + 1;
    if ($trailpos < $startpos) {
        $domain = $path = $url . '/';
    } else {
        $domain = substr($url, 0, $trailpos);
        $path = substr($url, 0, $querypos ? $querypos : null);
        if ($path[strlen($path) - 1] != '/') $path .= '/';
    }
    echo 'DOMAIN: ' . $domain . PHP_EOL;
    echo 'PATH: ' . $path . PHP_EOL;
    $title = substr($url, $startpos, $querypos ? $querypos - $startpos : null);
    try {
        $page = HTMLDocument::createFromFile($url);
    } catch (Exception $ex) {
        /* Not a webpage, fail gracefuly and use default title */
        return $category->createLink(
            $url,
            $title,
            blurhash: null,
            favicon: null
        );
    }
    function mod_url(string $url, string $base, string $path)
    {
        if (
            strpos($url, 'http:') === 0 ||
            strpos($url, 'https:') === 0 ||
            strpos($url, 'data:') === 0 ||
            strpos($url, '//') === 0
        ) return $url;
        else if ($url[0] == '/') return $base . substr($url, 1);
        else return $path . $url;
    }
    ob_start();
    echo '<pre>';
    echo 'URL: ' . $url . PHP_EOL;
    // Title
    try {
        $title_candidate = $page->getElementsByTagName('title')[0]?->textContent;
        if (!empty($title_candidate)) $title = $title_candidate;
        echo 'TITLE: ' . htmlentities($title) . PHP_EOL;
    } catch (Exception $ex) {
        /* Fail gracefuly, title will be truncated URL */
        echo 'Error in title: ' . $ex->getMessage() . PHP_EOL;
    }
    // Base
    $base = $domain;
    try {
        $collection = $page->getElementsByTagName('base');
        if ($collection->length > 0) {
            foreach ($collection as $tag) {
                $target = $tag->attributes->getNamedItem('target')?->value;
                if (empty($target) || strtolower($target) == '_self' || strtolower($target) == '_top') {
                    $base .= $tag->attributes->getNamedItem('href')->value;
                    echo 'BASE: ' . $base . PHP_EOL;
                    break;
                }
            }
        }
    } catch (Exception $ex) {
        /* Fail gracefuly, base will be empty */
        echo 'Error in base: ' . $ex->getMessage() . PHP_EOL;
    }
    // Meta tags
    $images = [];
    try {
        foreach ($page->getElementsByTagName('meta') as $tag) {
            $property = $tag->attributes->getNamedItem('property')?->value;
            if ($property !== null && array_search($property, ['og:image']) !== false) {
                $images[] = mod_url(
                    url: $tag->attributes->getNamedItem('content')->value,
                    base: $base,
                    path: $path
                );
            }
        }
    } catch (Exception $ex) {
        /* Fail gracefuly, blurhash will either come from page images or be null */
        echo 'Error in meta tags: ' . $ex->getMessage() . PHP_EOL;
    }
    // Image tags
    try {
        foreach ($page->getElementsByTagName('img') as $tag) {
            $src = $tag->attributes->getNamedItem('src')?->value;
            if ($src !== null) $images[] = mod_url($src, $base, $path);
        }
    } catch (Exception $ex) {
        /* Fail gracefuly, blurhash will either come from favicon or be null */
        echo 'Error in images: ' . $ex->getMessage() . PHP_EOL;
    }
    // Link tags
    $icon = null;
    try {
        $icon_best_score = $target_favicon_size + 1;
        foreach ($page->getElementsByTagName('link') as $tag) {
            $rel = $tag->attributes->getNamedItem('rel')?->value;
            if ($rel !== null && array_search($rel, ['favicon', 'icon', 'apple-touch-icon', 'shortcut icon', 'alternate icon']) !== false) {
                $sizes = $tag->attributes->getNamedItem('sizes')?->value;
                if (!empty($sizes) && ($xpos = strpos($sizes, 'x')) !== false) $score = abs(intval(substr($sizes, 0, $xpos)) - $target_favicon_size);
                else $score = $target_favicon_size;
                if ($score < $icon_best_score) {
                    $icon_best_score = $score;
                    $icon = $tag->attributes->getNamedItem('href')->value;
                }
            }
        }
        if ($icon !== null) {
            $icon = mod_url(url: $icon, base: $base, path: $path);
            $images[] = $icon;
            echo 'ICON: ' . $icon . PHP_EOL;
        }
    } catch (Exception $ex) {
        /* Fail gracefuly, icon will be null */
        echo 'Error in links: ' . $ex->getMessage() . PHP_EOL;
    }
    $blurhash = null;
    if (!empty($images)) {
        $_blurhash = require(__DIR__ . '/generate_blurhash.php');
        $blurhash = $_blurhash($images, $from);
        echo 'BLURHASH: ' . $blurhash . ' (from ' . $from . ')' . PHP_EOL;
    }
    echo '</pre>';

    // DEBUG OUTPUT
    if (false) ob_end_flush();
    else ob_end_clean();

    return $category->createLink(
        url: $url,
        title: $title,
        blurhash: $blurhash,
        favicon: $icon
    );
};
