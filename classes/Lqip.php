<?php

namespace JanHerman\Images;

use Kirby\Cms\File;
use Kirby\Cms\FileVersion;
use Kirby\Filesystem\Asset;

class Lqip
{
    private FileVersion|File|Asset $thumb;

    /**
    * Lqip constructor.
    *
    * @param File|Asset $file The original file or asset to generate a LQIP for.
    * @param string|array|null $options Options for generating the thumbnail. Can be a string preset or an array of options.
    * @param int $maxSize
    * @param int $blurRadius
    * @param int $quality
    */
    public function __construct(
        private File|Asset $file,
        private string|array|null $options = null,
        private int $maxSize = 16,
        private int $blurRadius = 1,
        private int $quality = 40,
    ) {
        $baseOptions = $this->resolveOptions($this->options);
        $lqipOptions = $this->lqipOptions($baseOptions);

        $this->thumb = $this->file->thumb(array_merge($baseOptions, $lqipOptions));
    }

    private function resolveOptions(string|array|null $options): array
    {
        if (is_array($options)) {
            return $options;
        }

        if (is_string($options)) {
            $preset = option('thumbs.presets.' . $options);

            return is_array($preset) ? $preset : [];
        }

        $preset = option('thumbs.presets.default');

        return is_array($preset) ? $preset : [];
    }

    private function lqipOptions(array $options): array
    {
        $lqipOptions = [
            'format' => 'webp',
            'quality' => $this->quality,
            'profiles' => [],
        ];

        if ($this->hasValidDimensions($options)) {
            $lqipOptions['width'] = $this->maxSize;
            $lqipOptions['height'] = round($this->maxSize / $options['width'] * $options['height']);
        } elseif (isset($options['height'])) {
            $lqipOptions['height'] = $this->maxSize;
        } else {
            $lqipOptions['width'] = $this->maxSize;
        }

        return $lqipOptions;
    }

    private function hasValidDimensions(array $options): bool
    {
        return isset($options['width'], $options['height']) &&
            is_numeric($options['width']) &&
            is_numeric($options['height']) &&
            $options['width'] > 0 &&
            $options['height'] > 0;
    }

    /**
     * Converts the object to a string, returning the data URI for the LQIP.
     *
     * @return string The data URI for the low-quality image placeholder.
     */
    public function __toString()
    {
        return $this->toDataUri();
    }

    /**
     * Checks whether the image contains an alpha channel (transparency).
     *
     * @return bool True if the image has an alpha channel, false otherwise.
     */
    private function hasAlphaChannel(): bool
    {
        return $this->hasAlphaChannelWithImagick() ?? $this->hasAlphaChannelWithGd();
    }

    private function hasAlphaChannelWithImagick(): ?bool
    {
        if (!extension_loaded('imagick')) {
            return null;
        }

        try {
            $image = new \Imagick();
            $image->readImageBlob($this->thumb->read());

            if (!$image->getImageAlphaChannel()) {
                $image->clear();
                $image->destroy();

                return false;
            }

            if (!method_exists($image, 'getImageChannelRange')) {
                $image->clear();
                $image->destroy();

                return null;
            }

            $range = $image->getImageChannelRange(\Imagick::CHANNEL_ALPHA);
            $quantumRange = $image->getQuantumRange();
            $max = is_array($quantumRange) ? ($quantumRange['quantumRangeLong'] ?? max($quantumRange)) : $quantumRange;

            $image->clear();
            $image->destroy();

            return isset($range['minima']) && $range['minima'] < $max;
        } catch (\Throwable) {
            return null;
        }
    }

    private function hasAlphaChannelWithGd(): bool
    {
        // Create a GD image from the thumbnail.
        $image = imagecreatefromstring($this->thumb->read());

        if (!$image) {
            return false;
        }

        $width = imagesx($image);
        $height = imagesy($image);

        for ($i = 0; $i < $width; $i++) {
            for ($j = 0; $j < $height; $j++) {
                if (imagecolorat($image, $i, $j) & 0x7F000000) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Applies an SVG filter
     * Based on https://github.com/johannschopplich/kirby-blurry-placeholder/blob/main/BlurryPlaceholder.php#L10
     *
     * @param string $uri The URI of the image to be filtered.
     * @param int $blur_radius The radius for the Gaussian blur filter.
     * @return string The SVG-encoded image as a string.
     */
    private function svgFilter(string $uri, int $blur_radius): string
    {
        $svg_width = $this->thumb->width();
        $svg_height = $this->thumb->height();

        // Wrap the blurred image in a SVG to avoid rasterizing the filter
        $alpha_filter = '';
        $has_alpha_channel = $this->options['transparent'] ?? $this->hasAlphaChannel();

        // If the image doesn't include an alpha channel itself, apply an additional filter
        // to remove the alpha channel from the blur at the edges
        if (!$has_alpha_channel) {
            $alpha_filter = <<<EOD
            <feComponentTransfer>
                <feFuncA type="discrete" tableValues="1 1"></feFuncA>
            </feComponentTransfer>
            EOD;
        }
        // Wrap the blurred image in a SVG to avoid rasterizing the filter
        $svg = <<<EOD
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 {$svg_width} {$svg_height}">
            <filter id="b" color-interpolation-filters="sRGB">
                <feGaussianBlur stdDeviation="{$blur_radius}"></feGaussianBlur>
                {$alpha_filter}
            </filter>
            <image filter="url(#b)" x="0" y="0" width="100%" height="100%" href="{$uri}"></image>
        </svg>
        EOD;

        return $svg;
    }

    /**
     * Optimizes an SVG and converts it to a URI-encoded string.
     * Based on https://github.com/johannschopplich/kirby-blurry-placeholder/blob/main/BlurryPlaceholder.php#L65
     *
     * @param string $data The raw SVG data.
     * @return string The optimized and URI-encoded SVG data.
     */
    private function svgToUri(string $data): string
    {
        // Optimizes the data URI length by deleting line breaks and
        // removing unnecessary spaces
        $data = preg_replace('/\s+/', ' ', $data);
        $data = preg_replace('/> </', '><', $data);

        $data = rawurlencode($data);

        // Back-decode certain characters to improve compression
        // except '%20' to be compliant with W3C guidelines
        $data = str_replace(
            ['%2F', '%3A', '%3D'],
            ['/', ':', '='],
            $data
        );

        return 'data:image/svg+xml;charset=utf-8,' . $data;
    }

    /**
     * Returns the thumbnail generated from the original file.
     *
     * @return FileVersion|File|Asset The thumbnail object.
     */
    public function toThumb(): FileVersion|File|Asset
    {
        return $this->thumb;
    }

    /**
     * Returns the thumbnail url.
     */
    public function toUrl(): string
    {
        return $this->thumb->url();
    }

    /**
     * Returns the low-quality image placeholder (LQIP) as a data URI.
     *
     * @param int|null $blur_radius The radius of the blur to be applied. If null, uses the default option.
     * @return string The data URI for the low-quality image placeholder.
     */
    public function toDataUri(?int $blur_radius = null): string
    {
        $uri = $this->thumb->dataUri();
        $blur_radius = $blur_radius ?? $this->blurRadius;

        if ($blur_radius !== 0) {
            $svg = $this->svgFilter($uri, $blur_radius);
            $uri = $this->svgToUri($svg);
        }

        return $uri;
    }
}
