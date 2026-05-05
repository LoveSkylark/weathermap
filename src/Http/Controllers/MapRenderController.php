<?php

namespace App\Plugins\Weathermap\Http\Controllers;

use App\Plugins\Weathermap\Services\ConfigPathResolver;
use Illuminate\Routing\Controller;
use Illuminate\Http\Response;

/**
 * MapRenderController - Handle map rendering operations
 * 
 * Extracted from EditorApiController
 * Handles image generation (PNG renders, font samples)
 */
class MapRenderController extends Controller
{
    protected $pathResolver;

    public function __construct(ConfigPathResolver $pathResolver)
    {
        $this->pathResolver = $pathResolver;
    }

    /**
     * Generate font samples image
     * GET /plugin/Weathermap/api/editor/font-samples/{map}
     */
    public function fontSamples($map): Response
    {
        if (!$this->isValidMapName($map)) {
            return response('Invalid map name', 400);
        }

        $mapfile = $this->pathResolver->getMapConfigPath($map);
        if (!file_exists($mapfile) || !is_readable($mapfile)) {
            return response('Map not found', 404);
        }

        try {
            $map_obj = new \Weathermap\Map\WeatherMap();
            $map_obj->ReadConfig($mapfile);

            // Create font samples image
            $im = $this->generateFontSamples($map_obj);

            ob_start();
            imagepng($im);
            $image_data = ob_get_clean();
            imagedestroy($im);

            return response($image_data)
                ->header('Content-Type', 'image/png')
                ->header('Cache-Control', 'no-cache, must-revalidate');
        } catch (\Exception $e) {
            return response('Error generating font samples: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Render/draw the map
     * GET /plugin/Weathermap/api/editor/draw/{map}
     */
    public function draw($map): Response
    {
        if (!$this->isValidMapName($map)) {
            return response('Invalid map name', 400);
        }

        $mapfile = $this->pathResolver->getMapConfigPath($map);
        if (!file_exists($mapfile) || !is_readable($mapfile)) {
            return response('Map not found', 404);
        }

        try {
            $map_obj = new \Weathermap\Map\WeatherMap();
            $map_obj->context = 'editor';
            $map_obj->ReadConfig($mapfile);
            $map_obj->DrawMap('editor');

            ob_start();
            imagepng($map_obj->image);
            $image_data = ob_get_clean();

            return response($image_data)
                ->header('Content-Type', 'image/png')
                ->header('Cache-Control', 'no-cache, must-revalidate');
        } catch (\Exception $e) {
            return response('Error drawing map: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Generate font samples image
     */
    protected function generateFontSamples($map): \GdImage
    {
        $keyfont = 2;
        $keyheight = imagefontheight($keyfont) + 2;
        $sampleheight = 32;

        $im = imagecreate(2000, $sampleheight);
        $imkey = imagecreate(2000, $keyheight);

        $white = imagecolorallocate($im, 255, 255, 255);
        $black = imagecolorallocate($im, 0, 0, 0);
        $whitekey = imagecolorallocate($imkey, 255, 255, 255);
        $blackkey = imagecolorallocate($imkey, 0, 0, 0);

        $x = 3;
        $fonts = $map->fonts ?? [];
        ksort($fonts);

        foreach ($fonts as $fontnumber => $font) {
            $string = 'Abc123%';
            $keystring = "Font $fontnumber";
            list($width, $height) = $map->myimagestringsize($fontnumber, $string);
            list($kwidth, $kheight) = $map->myimagestringsize($keyfont, $keystring);

            if ($kwidth > $width) {
                $width = $kwidth;
            }

            $y = ($sampleheight / 2) + $height / 2;
            $map->myimagestring($im, $fontnumber, $x, $y, $string, $black);
            $map->myimagestring($imkey, $keyfont, $x, $keyheight, "Font $fontnumber", $blackkey);

            $x = $x + $width + 6;
        }

        $im2 = imagecreate($x, $sampleheight + $keyheight);
        imagecopy($im2, $im, 0, 0, 0, 0, $x, $sampleheight);
        imagecopy($im2, $imkey, 0, $sampleheight, 0, 0, $x, $keyheight);
        imagedestroy($im);
        imagedestroy($imkey);

        return $im2;
    }

    /**
     * Validate map filename
     */
    protected function isValidMapName($name): bool
    {
        return preg_match('/^[a-zA-Z0-9_\-]+\.conf$/', $name) === 1;
    }
}
