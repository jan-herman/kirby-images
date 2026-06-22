<?php

namespace JanHerman\Images\Darkroom;

use Kirby\Filesystem\F;
use Kirby\Image\Darkroom;
use Kirby\Image\Darkroom\GdLib;
use Kirby\Image\Darkroom\Imagick;

class GdWithImagickForGifs extends Darkroom
{
    public function process(string $file, array $options = []): array
    {
        return $this->driver($file, $options)->process($file, $options);
    }

    private function driver(string $file, array $options): Darkroom
    {
        $source = $options['filename'] ?? $file;

        if (F::extension($source) === 'gif' && class_exists(\Imagick::class)) {
            return new Imagick($this->settings);
        }

        return new GdLib($this->settings);
    }
}
