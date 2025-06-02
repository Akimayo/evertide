<?php

declare(strict_types=1);

use kornrunner\Blurhash\Blurhash;

return function (array $candidates, &$from, int $components_x = 4, int $components_y = 5): ?string {
    // Based on https://github.com/kornrunner/php-blurhash?tab=readme-ov-file#encoding-with-gd
    foreach ($candidates as $i => $url) {
        try {
            $data = strpos($url, 'data') === 0 ? $url : file_get_contents($url);
            $img = imagecreatefromstring($data);
            if (!$img) continue;
        } catch (Throwable $ex) {
            echo 'Decoding image ' . $i . ' failed: ' . $ex->getMessage() . PHP_EOL;
            continue;
        }

        if (imagesx($img) > 192) $img = imagescale($img, 192);
        $w = imagesx($img);
        $h = imagesy($img);
        $pixels = [];
        for ($y = 0; $y < $h; $y++) {
            $row = [];
            for ($x = 0; $x < $w; $x++) {
                ['red' => $r, 'green' => $g, 'blue' => $b] = imagecolorsforindex($img, imagecolorat($img, $x, $y));
                $row[] = [$r, $g, $b];
            }
            $pixels[] = $row;
        }
        $from = $url;
        return Blurhash::encode($pixels, $components_x, $components_y);
    }
    return null;
};
