<?php

namespace App\Support;

use RuntimeException;

class ViteManifest
{
    public function tags(array $entries): string
    {
        $manifestPath = public_path('build/manifest.json');

        if (! is_file($manifestPath)) {
            throw new RuntimeException('The Vite asset manifest is missing.');
        }

        $manifest = json_decode(file_get_contents($manifestPath), true);

        if (! is_array($manifest)) {
            throw new RuntimeException('The Vite asset manifest is invalid.');
        }

        $tags = [];

        foreach ($entries as $entry) {
            $asset = $manifest[$entry] ?? null;
            $file = is_array($asset) ? ($asset['file'] ?? null) : null;

            if (! is_string($file) || $file === '') {
                throw new RuntimeException("The Vite entry [{$entry}] is missing from the manifest.");
            }

            $url = htmlspecialchars(asset('build/'.$file), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));

            if ($extension === 'css') {
                $tags[] = '<link rel="stylesheet" href="'.$url.'">';
            } elseif ($extension === 'js') {
                $tags[] = '<script type="module" src="'.$url.'"></script>';
            }

            foreach (($asset['css'] ?? []) as $cssFile) {
                $cssUrl = htmlspecialchars(asset('build/'.$cssFile), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                $tags[] = '<link rel="stylesheet" href="'.$cssUrl.'">';
            }
        }

        return implode(PHP_EOL, array_unique($tags));
    }
}
