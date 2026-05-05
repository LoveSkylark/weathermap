<?php

namespace App\Plugins\Weathermap\Hooks;

use App\Models\User;
use App\Plugins\Hooks\PageHook;
use App\Plugins\Weathermap\Services\ConfigPathResolver;

class PageHook extends PageHook
{
    protected ConfigPathResolver $pathResolver;

    public function __construct()
    {
        parent::__construct();
        $this->pathResolver = app(ConfigPathResolver::class);
    }

    public function authorize(User $user): bool
    {
        return $user->can('global-read');
    }

    public function data(array $settings = []): array
    {
        $configDir = $this->pathResolver->getConfigDir();
        $outputDir = $this->pathResolver->getOutputDir();

        $writable = is_writable($configDir);
        $images = [];

        if ($writable) {
            foreach (glob($outputDir . '/*.png') ?: [] as $imagePath) {
                $basename = pathinfo($imagePath, PATHINFO_FILENAME);
                $images[] = [
                    'image_url' => asset('plugins/Weathermap/output/' . basename($imagePath)),
                    'html_url'  => asset('plugins/Weathermap/output/' . $basename . '.html'),
                ];
            }
        }

        $pluginDir = $this->pathResolver->getPluginDir();
        $installPath = $pluginDir . '/INSTALL.md';

        return [
            'writable'   => $writable,
            'images'     => $images,
            'editor_url' => url('plugin/Weathermap/editor'),
            'readme'     => nl2br(file_exists($installPath) ? file_get_contents($installPath) : ''),
        ];
    }
}
