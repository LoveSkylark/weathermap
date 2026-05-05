<?php

namespace App\Plugins\Weathermap;

use App\Models\User;
use App\Plugins\Hooks\PageHook;

class Page extends PageHook
{
    public function authorize(User $user): bool
    {
        return true;
    }

    public function data(array $settings = []): array
    {
        $plugin_dir = base_path('app/Plugins/Weathermap');
        $conf_dir   = $plugin_dir . '/configs';
        $output_dir = public_path('plugins/Weathermap/output');

        $writable = is_writable($conf_dir);
        $images   = [];

        if ($writable) {
            foreach (glob($output_dir . '/*.png') ?: [] as $image) {
                $basename   = pathinfo($image, PATHINFO_FILENAME);
                $images[]   = [
                    'image_url' => asset('plugins/Weathermap/output/' . basename($image)),
                    'html_url'  => asset('plugins/Weathermap/output/' . $basename . '.html'),
                ];
            }
        }

        return [
            'writable'   => $writable,
            'images'     => $images,
            'editor_url' => asset('plugins/Weathermap/editor.php'),
            'readme'     => nl2br(file_exists($plugin_dir . '/INSTALL.md') ? file_get_contents($plugin_dir . '/INSTALL.md') : ''),
        ];
    }
}
