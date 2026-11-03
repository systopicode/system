<?php

declare(strict_types=1);
namespace Systopic\System\Sys;

/** Shipped notices, independent of network access or project configuration. */
final class ThirdParty
{
    private static function viewerPresent(): bool
    {
        if (class_exists(\Systopic\Cms\Media\SkpViewer::class)) return true;
        // Standalone projects can ship the viewer without installing systopic/cms.
        if (!class_exists('fs', false)) return false;
        foreach (['siteRoot', 'projectRoot'] as $property) {
            $root = (string) (\fs::$$property ?? '');
            if ($root === '') continue;
            if (method_exists('fs', 'toNative')) $root = \fs::toNative($root);
            foreach (['/public/js/skp-viewer.mjs', '/js/skp-viewer.mjs'] as $relative) {
                if (is_file(rtrim($root, '/\\') . $relative)) return true;
            }
        }
        return false;
    }

    public static function entries(): array
    {
        $items = [
            ['Common-password screening lists — SHA-256 subset for passwords of 15+ characters', 'Daniel Miessler; Django Software Foundation and individual contributors', 'MIT / BSD-3-Clause', 'https://github.com/danielmiessler/SecLists', ['SecLists-MIT.txt', 'Django-BSD.txt']],
            ['DiceBear PHP 10.6.0 — local restricted renderer', 'Florian Körner and DiceBear contributors; Fluxo adaptation by systopic', 'MIT', 'https://github.com/dicebear/dicebear-php/tree/06e118c61373a62e7727ad45d44574c6a33a81cd', ['DiceBear-MIT.txt']],
            ['Notionists and Bottts 10.6.0 — local avatar artwork', 'Zoish; Pablo Stanley', 'CC0 1.0 / artist permission for personal and commercial use', 'https://github.com/dicebear/styles/tree/22a9abebc54ed68275da811573204ad83db646b8', ['DiceBear-Styles-NOTICE.txt', 'CC0-1.0.txt']],
            ['flag-icons 7.3.2 — local SVG subset', 'Panayiotis Lipiridis', 'MIT', 'https://github.com/lipis/flag-icons/tree/v7.3.2', ['flag-icons-MIT.txt']],
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
        if (self::viewerPresent()) {
            $items[] = ['Three.js 0.185.0 (SketchUp viewer)', '2010–2026 three.js authors', 'MIT', 'https://github.com/mrdoob/three.js/tree/r185', ['Three-MIT.txt']];
            $items[] = ['OpenSKP 1.0.0 (SketchUp parser)', 'Ahsan Mehmood', 'MIT', 'https://github.com/iamahsanmehmood/openskp/tree/318eb474096ea3dd02bb16cc6c2cb3f56e597fc0', ['OpenSKP-MIT.txt']];
            $items[] = ['Earcut 3.2.3 (OpenSKP triangulation)', 'Mapbox', 'ISC', 'https://github.com/mapbox/earcut/tree/v3.2.3', ['earcut-ISC.txt']];
            $items[] = ['fflate 0.8.3 (OpenSKP decompression)', 'Arjun Barrett', 'MIT', 'https://github.com/101arrowz/fflate', ['fflate-MIT.txt']];
        }
        $items[] = ['FFmpeg / FFprobe (optional project-local binaries; build-dependent licence)', 'FFmpeg developers and the authors of the installed build', 'LGPL / GPL, depending on build', 'https://ffmpeg.org/legal.html', ['FFmpeg-NOTICE.txt', 'FFmpeg-LGPL-2.1.txt', 'FFmpeg-GPL-2.0.txt', 'GPL-3.0.txt']];
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
