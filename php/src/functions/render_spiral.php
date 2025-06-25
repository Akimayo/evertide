<?php

declare(strict_types=1);
return function (int $count): array {
    $spiralSeries = [
        [0, 0],
        // ^-- 1
        [1, 1,],
        [-1, -1],
        [-1, 1],
        [1, -1],
        [2, 0],
        [-2, -0],
        // ^-- 7
        [3, 1],   # 1
        [-3, 1],  # 5
        [-3, -1], # 7
        [3, -1],  # 11
        [-4, 0],  # 6
        [4, 0],   # 12
        [0, 2],   # 3
        [-2, 2],  # 4
        [0, -2],  # 9
        [2, -2],  # 10
        [2, 2],   # 2
        [-2, -2], # 4
        // ^-- 12
        [5, 1],   # 13
        [4, 2],   # 14
        [3, 3],   # 15
        [1, 3],   # 16
        [-1, 3],  # 17
        [-5, -1], # 22
        [-4, -2], # 23
        [-3, -3], # 24
        [-1, -3], # 25
        [1, -3],  # 26
        [3, -3],  # 27
        [4, -2],  # 28
        [5, -1],  # 29
        [6, 0],   # 30
        [-3, 3],  # 18
        [-4, 2],  # 19
        [-5, 1],  # 20
        [-6, 0],  # 21
        // ^-- 30
    ];
    $offsets = function (int $xpos, int $ypos, int $width, int $height): array {
        $left = 1;
        $right = 1;
        $top = 1;
        $bottom = 1;
        if ($xpos < 1) {
            $left = 0;
            if ($xpos < $width - 2) $right++;
        } else if ($xpos > $width - 2) {
            $right = 0;
            if ($xpos > 1) $left++;
        }
        if ($ypos < 1) {
            $bottom = 0;
            if ($ypos < $height - 2) $top++;
        } else if ($ypos > $height - 2) {
            $top = 0;
            if ($ypos > 1) $bottom++;
        }
        // Spiral format: [column, row, expand-top, expand-right, expand-bottom, expand-left]
        return [$xpos + 1, $height - $ypos + 1, $top, $right, $bottom, $left];
    };
    if ($count <= count($spiralSeries)) {
        // If there are enough predefined positions, use them
        $spiralSeries = array_slice($spiralSeries, 0, $count);
        $xmin = 0;
        $ymin = 0;
        $xmax = 0;
        $ymax = 0;
        foreach ($spiralSeries as [$x, $y]) {
            if ($x < $xmin) $xmin = $x;
            if ($x > $xmax) $xmax = $x;
            if ($y < $ymin) $ymin = $y;
            if ($y > $ymax) $ymax = $y;
        }
        $width = $xmax - $xmin;
        $height = $ymax - $ymin;
        return [$width + 2, $height + 1, function (int $i) use ($spiralSeries, $xmin, $ymin, $offsets, $width, $height): array {
            $xpos = $spiralSeries[$i][0] - $xmin;
            $ypos = $spiralSeries[$i][1] - $ymin;
            return $offsets($xpos, $ypos, $width, $height);
        }];
    } else {
        // ...otherwise generate as a rectangle
        $width = 5; // Must be an odd number to work properly!
        $height = (int)ceil($count / (2.0 * $width));
        return [2 * ($width + 1), $height + 1, function (int $i) use ($offsets, $width, $height): array {
            $height /= 2;
            $side = ($width - 1) >> 1;
            $ypos = $i / $width;
            if ($ypos & 1 > 0) $ypos *= -1;
            $ypos += $height;
            $xpos = 2 * ((($i + $side) % $width) - $side) + $width;
            return $offsets($xpos, $ypos, 2 * ($width + 1), $height + 1);
        }];
    }
};
