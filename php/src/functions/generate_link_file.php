<?php

declare(strict_types=1);

use chillerlan\QRCode\QRCode;

return function (string $code, array $devices): void {
    $list = implode(PHP_EOL, array_map(function (Device $device): string {
        return '- **' . $device->getName() . '** (added ' . $device->getFirstLogin() . ')';
    }, $devices));
    $link = 'http://localhost/link.php?device=' . $code; // FIXME: Add the actual URL
    $qr = (new QRCode())->render($link);

    file_put_contents(__DIR__ . '/../../opt/authentication.md', <<<PHP_EOL
    # 🌊 evertide Authentication
    > This file is re-generated each time a new device is added and contains a new authentication link every time.
    
    ## Log in to your **evertide** instance
    Click [this link]($link) or scan the QR code: ![evertide device link QR code]($qr)

    ## Devices linked to your instance
    $list
    PHP_EOL);
};
