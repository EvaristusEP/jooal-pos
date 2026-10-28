<?php

namespace Tests\Unit;

use App\Support\ViteManifest;
use Tests\TestCase;

class ThemeAssetTest extends TestCase
{
    public function test_manifest_drives_the_stylesheet_url_rendered_by_the_vite_directive(): void
    {
        $manifest = json_decode(file_get_contents(public_path('build/manifest.json')), true);
        $tags = app(ViteManifest::class)->tags([
            'resources/css/app.css',
            'resources/js/app.js',
        ]);

        $this->assertStringContainsString(asset('build/'.$manifest['resources/css/app.css']['file']), $tags);
        $this->assertStringContainsString(asset('build/'.$manifest['resources/js/app.js']['file']), $tags);
        $this->assertStringNotContainsString('app-B5fCZYdi.css', $tags);
    }

    public function test_served_stylesheet_uses_neutral_dark_surfaces_and_distinct_theme_sidebars(): void
    {
        $manifest = json_decode(file_get_contents(public_path('build/manifest.json')), true);
        $cssPath = public_path('build/'.$manifest['resources/css/app.css']['file']);
        $sourceCss = file_get_contents(resource_path('css/app.css'));
        $sourceRules = preg_replace('/\A@tailwind base;\R@tailwind components;\R@tailwind utilities;\R?/', '', $sourceCss);
        $servedCss = file_get_contents($cssPath);

        $this->assertFileExists($cssPath);
        $this->assertStringContainsString($sourceRules, $servedCss);
        foreach (['--layout-content-max: none;', '.pos-filter {', '.pos-button {', 'dialog.crud-dialog {'] as $layoutRule) {
            $this->assertStringContainsString($layoutRule, $servedCss, 'The served stylesheet must include shared layout and control rules.');
        }

        $palettes = [
            ':root' => ['body' => '#f4f7f5', 'panel' => '#ffffff', 'sidebar' => '#176b50', 'accent' => '#176b50'],
            'html[data-theme="dark"]' => ['body' => '#202124', 'panel' => '#303134', 'sidebar' => '#153d34', 'accent' => '#8fe0bd'],
            'html[data-theme="dark"][data-theme-palette="slate-night"]' => ['body' => '#202124', 'panel' => '#303134', 'sidebar' => '#26374a', 'accent' => '#8ab4f8'],
            'html[data-theme="dark"][data-theme-palette="coffee-night"]' => ['body' => '#202124', 'panel' => '#303134', 'sidebar' => '#3b2b22', 'accent' => '#e0b77f'],
        ];

        $resolved = [];
        foreach ($palettes as $selector => $expected) {
            preg_match('/'.preg_quote($selector, '/').'\s*\{([^}]*)\}/', $sourceCss, $blockMatch);
            $this->assertNotEmpty($blockMatch, 'Missing palette rule: '.$selector);

            foreach ($expected as $token => $value) {
                preg_match('/--theme-'.preg_quote($token, '/').':\s*([^;]+);/', $blockMatch[1], $valueMatch);
                $this->assertSame($value, $valueMatch[1] ?? null, $selector.' token '.$token);
                $resolved[$token][] = $valueMatch[1] ?? null;
            }
        }

        foreach (['body', 'panel'] as $token) {
            $this->assertCount(2, array_unique($resolved[$token]), 'Dark themes share a neutral '.$token.' surface distinct from light mode.');
        }
        foreach (['sidebar', 'accent'] as $token) {
            $this->assertCount(4, array_unique($resolved[$token]), 'Palette token '.$token.' must differ across themes.');
        }

        $this->assertStringContainsString('background: var(--theme-body) !important;', $sourceCss);
        $this->assertStringContainsString('background: var(--theme-sidebar) !important;', file_get_contents(resource_path('views/components/pos-sidebar.blade.php')));
        $this->assertStringContainsString('background: var(--theme-panel) !important;', $sourceCss);
        $this->assertStringContainsString('background-color: var(--theme-input);', $sourceCss);
        $this->assertStringContainsString('.bg-\\[\\#123c35\\]:not(.pos-sidebar)', $sourceCss, 'Brand-colored content cards stay theme-aware without recoloring the sidebar.');
    }

    public function test_theme_choice_is_applied_to_the_root_and_persisted_for_next_page_load(): void
    {
        $settingsView = file_get_contents(resource_path('views/settings/system.blade.php'));
        $sidebar = file_get_contents(resource_path('views/components/pos-sidebar.blade.php'));

        foreach (['light', 'dark-teal', 'slate-night', 'coffee-night'] as $theme) {
            $this->assertStringContainsString('value="'.$theme.'"', $settingsView);
        }
        $this->assertStringNotContainsString('value="auto"', $settingsView);
        $this->assertStringContainsString("legacyMode === 'dark' ? 'dark-teal' : 'light'", $sidebar);
        $this->assertStringNotContainsString('prefers-color-scheme', $sidebar);
        $this->assertStringContainsString("? 'dark-teal' : 'light'", $sidebar);

        $this->assertStringContainsString('root.dataset.theme = mode;', $sidebar);
        $this->assertStringContainsString('root.dataset.themePalette = palette;', $sidebar);
        $this->assertStringContainsString("localStorage.setItem('jooal-theme-choice', choice);", $sidebar);
        $this->assertStringContainsString("var storedChoice = localStorage.getItem('jooal-theme-choice');", $sidebar);
    }
}
