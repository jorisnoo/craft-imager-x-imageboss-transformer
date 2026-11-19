<?php
/**
 * ImageBoss transformer for Imager X
 *
 * @link      https://www.spacecat.ninja
 * @copyright Copyright (c) 2021 André Elvan
 */

namespace spacecatninja\imagebosstransformer\helpers;

use spacecatninja\imagebosstransformer\ImageBossTransformer;
use spacecatninja\imagebosstransformer\models\ImageBossProfile;
use spacecatninja\imagerx\services\ImagerService;

class ImageBossHelpers
{
    /**
     * @param string $name
     *
     * @return ImageBossProfile|null
     */
    public static function getProfile(string $name): ?ImageBossProfile
    {
        /** @var \spacecatninja\imagebosstransformer\models\Settings $settings */
        $settings = ImageBossTransformer::$plugin->getSettings();
        
        if (isset($settings->profiles[$name])) {
            return new ImageBossProfile($settings->profiles[$name]);
        }
        
        return null;
    }

    /**
     * @param array $transform
     *
     * @return string
     */
    public static function getFocalPointString(array $transform): string
    {
        $config = ImagerService::getConfig();
        $position = preg_split('/\s+/', trim($config->getSetting('position', $transform)));
        $coordinate = static fn ($value): float => round(min(100, max(0, (float)$value)) / 100, 4);
        $r = 'fp-x:'.$coordinate($position[0] === '' ? 50 : $position[0]).',fp-y:'.$coordinate($position[1] ?? 50);
        
        if (isset($transform['cropZoom'])) {
            $r .= ',fp-z:'.$transform['cropZoom'];
        }
        
        return $r;
    }
    
    /**
     * Gets letterbox params string
     *
     * @param $letterboxDef
     *
     * @return string
     */
    public static function getLetterboxColor($letterboxDef): string
    {
        $color = $letterboxDef['color'] ?? '#000';

        $color = str_replace('#', '', $color);
        
        if (strlen($color) === 6) {
            return $color;
        }

        if (strlen($color) === 3) {
            return $color[0].$color[0].$color[1].$color[1].$color[2].$color[2];
        }
        
        return $color;
    }
    
    /**
     * Gets the quality setting based on the extension.
     *
     * @param string     $ext
     * @param array|null $transform
     *
     * @return string
     */
    public static function getQualityFromExtension(string $ext, ?array $transform = null): string
    {
        $config = ImagerService::getConfig();

        $format = strtolower($transform['format'] ?? $ext);
        if (isset($transform['quality'])) {
            return (string)$transform['quality'];
        }
        // Imager normalizes generic `quality` to `jpegQuality`. An explicit
        // output-specific setting still takes precedence over that alias.
        $specificKey = match ($format) {
            'png' => 'pngCompressionLevel',
            'webp' => 'webpQuality',
            'avif' => 'avifQuality',
            default => 'jpegQuality',
        };
        if (!isset($transform[$specificKey]) && isset($transform['jpegQuality'])) {
            return (string)$transform['jpegQuality'];
        }
        switch ($format) {
            case 'png':
                $pngCompression = $config->getSetting('pngCompressionLevel', $transform);
                return (string)max(100 - ($pngCompression * 10), 1);
            case 'webp':
                return $config->getSetting('webpQuality', $transform);
            case 'avif':
                return $config->getSetting('avifQuality', $transform);
        }

        return $config->getSetting('jpegQuality', $transform);
    }

    /**
     * @param array $transform
     *
     * @return array
     */
    public static function getEffects(array $transform): array
    {
        if (!isset($transform['effects'])) {
            return [];
        }
        
        $r = [];
        $effects = $transform['effects'];
        
        if ((isset($effects['grayscale']) && $effects['grayscale'] === true) || (isset($effects['greyscale']) && $effects['greyscale'] === true)) {
            $r[] = 'grayscale:true';
        }
        
        if (isset($effects['blur'])) {
            $val = $effects['blur'];
            if ($val === true) {
                $r[] = 'blur:2';
            } else {
                $r[] = "blur:$val";
            }
        }
        
        if (isset($effects['sharpen'])) {
            $val = $effects['sharpen'];
            if ($val === true) {
                $r[] = 'sharpen:2';
            } else {
                $r[] = "sharpen:$val";
            }
        }
        
        if (isset($effects['gamma'])) {
            $val = $effects['gamma'];
            $r[] = "gamma:$val";
        }
        
        return $r;
    }
}
