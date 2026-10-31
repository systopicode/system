<?php

declare(strict_types=1);
namespace Systopic\System\Sys;

/** Shipped notices, independent of network access or project configuration. */
final class ThirdParty
{
    public static function entries(): array
    {
        $items = [
            ['Tabler Icons 3.50.0 — Outline', 'Paweł Kuna', 'MIT', 'https://github.com/tabler/tabler-icons/tree/v3.50.0', ['Tabler-MIT.txt']],
            ['Phosphor Icons 2.1.1 — Duotone', 'Phosphor Icons', 'MIT', 'https://github.com/phosphor-icons/core', ['Phosphor-MIT.txt']],
            ['Solar Icons — monochrome info-circle subset (asset data 1.2.13)', '480 Design', 'CC BY 4.0', 'https://github.com/480-Design/Solar-Icon-Set', ['Solar-CC-BY-4.0.txt']],
            ['jQuery 3.7.1', 'OpenJS Foundation and other contributors', 'MIT', 'https://github.com/jquery/jquery/tree/3.7.1', ['jquery-MIT.txt']],
            ['jsQR 1.4.0', 'Cosmo Wolfe, Jefff Nelson and contributors', 'Apache-2.0', 'https://github.com/cozmo/jsQR', ['jsqr-Apache-2.0.txt']],
            ['WebAuthn (lbuchs/webauthn)', 'Lukas Buchs; Thomas Bleeker (CBOR / ByteBuffer)', 'MIT', 'https://github.com/lbuchs/WebAuthn', ['webauthn-MIT.txt']],
            ['Inter', 'The Inter Project Authors', 'SIL Open Font License 1.1', 'https://github.com/rsms/inter', ['Inter-OFL.txt']],
            ['TCPDF', 'Nicola Asuni / Tecnick.com and the contributors identified in its source', 'LGPL-3.0-or-later', 'https://github.com/tecnickcom/TCPDF', ['tcpdf-LGPL.txt', 'GPL-3.0.txt']],
        ];
        $items[] = ['systopic/db', 'systopic GmbH', 'Package licence', '', ['systopic-db-LICENSE.txt']];
        if (class_exists(\Systopic\Relay\Relay::class)) $items[] = ['systopic/relay', 'systopic GmbH', 'Package licence', '', ['systopic-relay-LICENSE.txt']];
        if (class_exists(\Systopic\Cms\Media\SkpViewer::class)) $items[] = ['Three.js (bundled SketchUp viewer)', '2010–2026 three.js authors', 'MIT', 'https://github.com/mrdoob/three.js', ['Three-MIT.txt']];
        if (class_exists(\Systopic\Relay\Relay::class)) $items[] = ['Centrifuge JavaScript client', '2023 Centrifugal Labs LTD', 'MIT', 'https://github.com/centrifugal/centrifuge-js', ['centrifuge-MIT.txt']];
        $dir = dirname(__DIR__, 2) . '/legal/third-party/';
        return array_map(static function (array $item) use ($dir): array {
            [$name, $author, $license, $source, $files] = $item;
            $texts = [];
            foreach ($files as $file) $texts[] = (string) file_get_contents($dir . $file);
            return compact('name', 'author', 'license', 'source', 'texts');
        }, $items);
    }
}
