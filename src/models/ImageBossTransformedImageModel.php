<?php
/**
 * ImageBoss transformer for Imager X
 *
 * @link      https://www.spacecat.ninja
 * @copyright Copyright (c) 2021 André Elvan
 */

namespace spacecatninja\imagebosstransformer\models;

use craft\elements\Asset;
use spacecatninja\imagerx\models\BaseTransformedImageModel;
use spacecatninja\imagerx\models\TransformedImageInterface;

class ImageBossTransformedImageModel extends BaseTransformedImageModel implements TransformedImageInterface
{
    public function __construct(?string $imageUrl = null, ?Asset $source = null, array $transform = [])
    {
        if ($imageUrl !== null) {
            $this->url = $imageUrl;
        }

        $width = (int)($transform['width'] ?? 0);
        $height = (int)($transform['height'] ?? 0);
        if ($width > 0 && $height > 0 && strtolower($transform['mode'] ?? 'crop') !== 'fit') {
            $this->width = $width;
            $this->height = $height;
            return;
        }

        [$sourceWidth, $sourceHeight] = $source === null ? [0, 0] : $this->getSourceImageDimensions($source);
        if ($width > 0 && $height > 0) {
            // A fit box alone establishes neither output dimension.
            if ($sourceWidth > 0 && $sourceHeight > 0) {
                $scale = min(1, $width / $sourceWidth, $height / $sourceHeight);
                $this->width = (int)round($scale * $sourceWidth);
                $this->height = (int)round($scale * $sourceHeight);
            }
        } elseif ($width > 0 || $height > 0) {
            [$this->width, $this->height] = $this->calculateTargetSize($transform, $sourceWidth, $sourceHeight);
        } else {
            $this->width = $sourceWidth;
            $this->height = $sourceHeight;
        }
    }

    /**
     * @param Asset $source
     *
     * @return array
     */
    protected function getSourceImageDimensions(Asset $source): array
    {
        return [(int)$source->getWidth(), (int)$source->getHeight()];
    }

    /**
     * @param array $transform
     * @param int   $sourceWidth
     * @param int   $sourceHeight
     *
     * @return array
     */
    protected function calculateTargetSize(array $transform, int $sourceWidth, int $sourceHeight): array
    {
        if ($sourceWidth <= 0 || $sourceHeight <= 0) {
            return [(int)($transform['width'] ?? 0), (int)($transform['height'] ?? 0)];
        }
        $ratio = $sourceWidth / $sourceHeight;

        $w = $transform['width'] ?? null;
        $h = $transform['height'] ?? null;

        if ($w) {
            return [$w, (int)round($w / $ratio)];
        }
        if ($h) {
            return [(int)round($h * $ratio), $h];
        }

        return [0, 0];
    }
}
