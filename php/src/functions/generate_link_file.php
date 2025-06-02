<?php

declare(strict_types=1);

use chillerlan\QRCode\QRCode;

return function (string $code, array $devices): void {
    $list = implode(PHP_EOL, array_map(function (Device $device): string {
        return '- **' . $device->getName() . '** (added ' . $device->getFirstLogin() . ')';
    }, $devices));
    $cfg = Config::get_config();
    $link = $cfg->instance->getLink() . 'link?device=' . $code;
    $qr = (new QRCode())->render($link);

    file_put_contents($cfg->get_data_location() . 'authentication.md', <<<PHP_EOL
    # 🌊 evertide Authentication
    > This file is re-generated each time a new device is added and contains a new authentication link every time.
    
    ## Log in to your **evertide** instance
    Click [this link]($link) or scan the QR code: ![evertide device link QR code]($qr)

    ## Devices linked to your instance
    $list
    PHP_EOL);
};
