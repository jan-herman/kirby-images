<?php

namespace JanHerman\Images;

use Kirby\Cms\File;
use Kirby\Filesystem\Asset;
use Kirby\Cms\FileVersion;

class Lqip
{
    private FileVersion $thumb;

     /**
     * Lqip constructor.
     *
     * @param File|Asset $file The original file or asset to generate a LQIP for.
     * @param string|array|null $options Options for generating the thumbnail. Can be a string preset or an array of options.
     */
    public function __construct(
        private File|Asset $file,
        private string|array|null $options = null,
    ) {
        $options = $this->options;

        if (empty($options) === true) {
            $options = option('thumbs.presets.default');
        } elseif (is_string($options) === true) {
            $options = option('thumbs.presets.' . $options);
        }

        $max_size = option('jan-herman.images.lqip.maxSize', 16);
        $quality = option('jan-herman.images.lqip.quality', 40);

        $lqip_options = [
            'format' => 'webp',
            'quality' => $quality
        ];

        if (isset($options['width']) && isset($options['height'])) {
            $lqip_options['width'] = $max_size;
            $lqip_options['height'] = round($max_size / $options['width'] * $options['height']);
        } elseif (isset($options['height'])) {
            $lqip_options['height'] = $max_size;
        } else {
            $lqip_options['width'] = $max_size;
        }

        $options = array_merge($options, $lqip_options);

        $this->thumb = $this->file->thumb($options);
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
        // Create a GD image from the thumbnail
        $image = imagecreatefromstring($this->thumb->read());
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
     * @return Kirby\Cms\FileVersion The thumbnail object.
     */
    public function toThumb(): FileVersion
    {
        return $this->thumb;
    }

    /**
     * Returns the low-quality image placeholder (LQIP) as a data URI.
     *
     * @param int|null $blur_radius The radius of the blur to be applied. If null, uses the default option.
     * @return string The data URI for the low-quality image placeholder.
     */
    public function toDataUri(int $blur_radius = null): string
    {
        $uri = $this->thumb->dataUri();
        $blur_radius = $blur_radius ?? option('jan-herman.images.lqip.blurRadius');

        if ($blur_radius !== 0) {
            $svg = $this->svgFilter($uri, $blur_radius);
            $uri = $this->svgToUri($svg);
        }

        return $uri;
    }
}
