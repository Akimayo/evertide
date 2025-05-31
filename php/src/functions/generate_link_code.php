<?php

declare(strict_types=1);
return function (int $length = 32): string {
    $keyspace = '3456789abcdefghkorstuxyzABCDGHJKLMNSTUXY';
    $pieces = [];
    $max = mb_strlen($keyspace, '8bit') - 1;
    for ($i = 0; $i < $length; $i++)
        $pieces[] = $keyspace[random_int(0, $max)];
    $link = implode('', $pieces);

    file_put_contents(__DIR__ . '/../../opt/link', $link);
    return $link;
};
