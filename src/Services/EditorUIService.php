<?php

namespace App\Plugins\Weathermap\Services;

use Weathermap\Map\WeatherMap;

/**
 * EditorUIService - UI rendering helpers for editor
 * 
 * Extracted from lib/editor/EditorFunctions.php
 * Provides methods for building editor UI components (dropdowns, image lists, etc.)
 */
class EditorUIService
{
    protected $configPathResolver;

    public function __construct(ConfigPathResolver $configPathResolver)
    {
        $this->configPathResolver = $configPathResolver;
    }

    /**
     * Get list of image files from a directory
     */
    public function getImageList(string $imagedir): array
    {
        $imagelist = [];

        if (!is_dir($imagedir)) {
            return $imagelist;
        }

        $dh = opendir($imagedir);
        if ($dh === false) {
            return $imagelist;
        }

        while ($file = readdir($dh)) {
            $realfile = $imagedir . DIRECTORY_SEPARATOR . $file;
            $uri = $imagedir . '/' . $file;

            if (is_readable($realfile) && preg_match('/\.(gif|jpg|png)$/i', $file)) {
                $imagelist[] = $uri;
            }
        }

        closedir($dh);
        return $imagelist;
    }

    /**
     * Get list of weather maps in configuration directory
     */
    public function listWeathermaps(): array
    {
        $titles = [];
        $mapdir = $this->configPathResolver->getConfigDir();

        if (!is_dir($mapdir)) {
            return [];
        }

        $dh = opendir($mapdir);
        if ($dh === false) {
            return [];
        }

        while ($file = readdir($dh)) {
            $realfile = $mapdir . DIRECTORY_SEPARATOR . $file;

            // Skip directories, unreadable files, .files, and bad names
            if (is_file($realfile) && is_readable($realfile) && !preg_match("/^\./", $file)) {
                $title = '(no title)';

                // Read first 100 lines to find TITLE
                $fd = fopen($realfile, 'r');
                if ($fd) {
                    $count = 0;
                    while (!feof($fd) && $count < 100) {
                        $line = fgets($fd, 4096);
                        if (preg_match('/^\s*TITLE\s+(.*)/i', $line, $matches)) {
                            $title = trim($matches[1]);
                            break;
                        }
                        $count++;
                    }
                    fclose($fd);
                }

                $titles[$file] = $title;
            }
        }

        closedir($dh);
        ksort($titles);
        return $titles;
    }

    /**
     * Render font selector dropdown
     */
    public function getFontList(WeatherMap $map, string $name, string $current = ''): string
    {
        $output = '<select class="fontcombo" name="' . htmlspecialchars($name) . '">';

        $fonts = $map->fonts ?? [];
        ksort($fonts);

        foreach ($fonts as $fontnumber => $font) {
            $selected = ($current == $fontnumber) ? 'SELECTED' : '';
            $output .= '<option ' . $selected . ' value="' . htmlspecialchars($fontnumber) . '">'
                . htmlspecialchars($fontnumber) . ' (' . htmlspecialchars($font->type ?? '') . ')</option>';
        }

        $output .= '</select>';
        return $output;
    }

    /**
     * Handle inheritance updates from form (nodes or links)
     */
    public function handleInheritance(WeatherMap $map, array $inheritables, array $request_data): void
    {
        foreach ($inheritables as $inheritable) {
            [$scope, $fieldname, $formname, $validation] = $inheritable;

            if (!isset($request_data[$formname])) {
                continue;
            }

            $new = $request_data[$formname];

            // Type validation
            if ($validation === 'int') {
                $new = intval($new);
            } elseif ($validation === 'float') {
                $new = floatval($new);
            }

            // Get old value from DEFAULT
            if ($scope === 'node') {
                $old = $map->nodes['DEFAULT']->$fieldname ?? null;

                if ($old !== $new) {
                    $map->nodes['DEFAULT']->$fieldname = $new;

                    // Update all nodes that have the old value
                    foreach ($map->nodes as $node) {
                        if ($node->name !== ':: DEFAULT ::' && ($node->$fieldname ?? null) === $old) {
                            $node->$fieldname = $new;
                        }
                    }
                }
            } elseif ($scope === 'link') {
                $old = $map->links['DEFAULT']->$fieldname ?? null;

                if ($old !== $new) {
                    $map->links['DEFAULT']->$fieldname = $new;

                    // Update all links that have the old value
                    foreach ($map->links as $link) {
                        if ($link->name !== ':: DEFAULT ::' && ($link->$fieldname ?? null) === $old) {
                            $link->$fieldname = $new;
                        }
                    }
                }
            }
        }
    }
}
