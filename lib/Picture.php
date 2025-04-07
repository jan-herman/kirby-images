<?php

namespace JanHerman\Images;

use Kirby\Content\Field;
use Kirby\Cms\File;
use Kirby\Filesystem\Asset;
use Kirby\Image\Focus;
use Kirby\Toolkit\Html;

class Picture
{
    private AspectRatio $aspect_ratio;
    private string $sizes_cache;
    private array $object_position_cache;

    /**
     * Picture constructor.
     *
     * @param Field|null $field
     * @param File|null $file
     * @param Asset|null $asset
     * @param string|array|null $thumb
     * @param string|array|null $srcset
     * @param string $src
     * @param string $alt
     * @param string|float|null $ratio
     * @param string $object_fit
     * @param string $as
     * @param bool $lazy
     * @param string|array|null $sizes
     * @param int|string|bool|null $container
     * @param array|null $focus
     * @param array $attr
     * @param string $class
     */
    public function __construct(
        private Field|null $field               = null,
        private File|null $file                 = null,
        private Asset|null $asset               = null,
        private string|array|null $thumb        = null,
        private string|array|null $srcset       = 'default',
        private string $src                     = '',
        private string $alt                     = '',
        private string|float|null $ratio        = null,
        private string $object_fit              = 'cover',
        private string $as                      = 'picture',
        private bool $lazy                      = false,
        private string|array|null $sizes        = null,
        private int|string|bool|null $container = null,
        private array|null $focus               = null,
        private array $attr                     = [],
        private array $img_attr                 = [],
        private string $class                   = ''
    ) {
        // set file from field
        if ($this->field && !$this->file) {
            $this->file = $this->field->toFile();
        }
        // set aspect ratio
        $this->aspect_ratio = new AspectRatio($this->ratio, $this->getWidth(), $this->getHeight());
    }

    /**
     * Convert the Picture object to string
     *
     * @return string
     */
    public function __toString()
    {
        return $this->renderToString();
    }

    /**
     * Get the file extension
     *
     * @return string
     */
    private function getExtension(): string
    {
        if ($this->file) {
            return $this->file->extension();
        }

        if ($this->asset) {
            return $this->asset->extension();
        }

        return '';
    }

    /**
     * Check if the file is an SVG
     *
     * @return bool
     */
    private function isSvg(): bool
    {
        return $this->getExtension() === 'svg';
    }

    /**
     * Get the alt text
     *
     * @return string
     */
    private function getAlt(): string
    {
        if ($this->alt) {
            return $this->alt;
        }

        if ($this->file) {
            return $this->file->alt()->toString();
        }

        return '';
    }

    /**
     * Get the width of the image
     *
     * @return int
     */
    private function getWidth(): int
    {
        if ($this->file) {
            return $this->file->width();
        }

        if ($this->asset) {
            return $this->asset->width();
        }

        return 0;
    }

    /**
     * Get the height of the image
     *
     * @return int
     */
    private function getHeight(): int
    {
        if ($this->file) {
            return $this->file->height();
        }

        if ($this->asset) {
            return $this->asset->height();
        }

        return 0;
    }

    /**
     * Get LQIP data URI
     *
     * @return string|null
     */
    private function getLqipSrc(): string|null
    {
        if ($this->file) {
            return new Lqip($this->file, $this->thumb);
        }

        if ($this->asset) {
            return new Lqip($this->asset, $this->thumb);
        }

        return null;
    }

    /**
     * Get the source URL of the image
     *
     * @return string|null
     */
    private function getSrc(): string|null
    {
        if ($this->src) {
            return $this->src;
        }

        if ($this->file) {
            return $this->file->thumb($this->thumb)->url();
        }

        if ($this->asset) {
            return $this->asset->thumb($this->thumb)->url();
        }

        return null;
    }

    /**
     * Get the WebP source URL of the image
     *
     * @return string|null
     */
    private function getSrcWebp(): string|null
    {
        if ($this->src) {
            return $this->src;
        }

        if ($this->file) {
            return $this->file->thumbWebp($this->thumb)->url();
        }

        if ($this->asset) {
            return $this->asset->thumbWebp($this->thumb)->url();
        }

        return null;
    }

    /**
     * Get the srcset attribute
     *
     * @return string|null
     */
    private function getSrcset(): string|null
    {
        if (!$this->srcset) {
            return null;
        }

        if ($this->file) {
            return $this->file->srcset($this->srcset);
        }

        if ($this->asset) {
            return $this->asset->srcset($this->srcset);
        }

        return null;
    }

    /**
     * Get the WebP srcset attribute
     *
     * @return string|null
     */
    private function getSrcsetWebp(): string|null
    {
        if (!$this->srcset) {
            return null;
        }

        if ($this->file) {
            return $this->file->srcsetWebp($this->srcset);
        }

        if ($this->asset) {
            return $this->asset->srcsetWebp($this->srcset);
        }

        return null;
    }

    /**
     * Get the class attribute
     *
     * @return string
     */
    private function getClass()
    {
        $classes = [];

        // object-fit
        if ($this->object_fit === 'cover' || $this->object_fit === 'contain') {
            $classes[] = $this->object_fit;
        }

        // custom class
        if ($this->class) {
            $classes[] = $this->class;
        }

        return implode(' ', $classes);
    }

    /**
     * Get the aspect ratio style
     *
     * @return string
     */
    private function getAspectRatioStyle(): string
    {
        // legacy
        if (option('jan-herman.images.legacyAspectRatio')) {
            $aspect_ratio = $this->aspect_ratio->percentage();

            if ($aspect_ratio === null) {
                return '';
            }

            return '--aspect-ratio: ' . round($aspect_ratio, 4) . '%;';
        }

        // standard
        $aspect_ratio = $this->aspect_ratio->get();

        if ($aspect_ratio === null) {
            return '';
        }

        return 'aspect-ratio: ' . round($aspect_ratio, 4);
    }

    /**
     * Get the focus point array
     *
     * @return array|null
     */
    private function getFocus(): array|null
    {
        if ($this->focus) {
            return $this->focus;
        }

        if (!$this->file || $this->file->focus()->isEmpty()) {
            return null;
        }

        $focus_string = $this->file->focus()->toString();
        return Focus::parse($focus_string);
    }

    /**
     * Get the object position
     *
     * @return array
     */
    private function getObjectPosition(): array
    {
        if (isset($this->object_position_cache)) {
            return $this->object_position_cache;
        }

        $focus = $this->getFocus();
        $container_ratio = $this->aspect_ratio->get();
        $image_width = $this->getWidth();
        $image_height = $this->getHeight();

        if ($this->ratio === 'auto' || $focus === null || $container_ratio === null || $image_width === 0 || $image_height === 0) {
            $this->object_position_cache = [];
            return $this->object_position_cache;
        }

        $image_ratio = $this->getWidth() / $this->getHeight();
        $focus_x = $focus[0];
        $focus_y = $focus[1];

        if ($image_ratio === $container_ratio) {
            $this->object_position_cache = [];
            return $this->object_position_cache;
        }

        $object_position_x = $focus_x * 100;
        $object_position_y = $focus_y * 100;

        if ($image_ratio > $container_ratio) { // image is wider than the container
            $base = $image_ratio;
            $container_width_ratio = $container_ratio / $image_ratio;
            $container_width = $container_width_ratio * $base;
            $max_offset = $base - $container_width;
            $container_center_x = $base * $focus_x;
            $container_offset_x = $container_center_x - ($container_width * 0.5);

            $object_position_x = ($container_offset_x / $max_offset) * 100;
        } elseif ($image_ratio < $container_ratio) { // image is taller than the container
            $base = $container_ratio;
            $container_height_ratio = $image_ratio / $container_ratio;
            $container_height = $container_height_ratio * $base;
            $max_offset = $base - $container_height;
            $container_center_y = $base * $focus_y;
            $container_offset_y = $container_center_y - ($container_height * 0.5);

            $object_position_y = ($container_offset_y / $max_offset) * 100;
        }

        // clamp the values between 0% and 100%
        $object_position_x = max(0, min(100, $object_position_x));
        $object_position_y = max(0, min(100, $object_position_y));

        $this->object_position_cache = [
            $object_position_x,
            $object_position_y
        ];

        return $this->object_position_cache;
    }

    /**
     * Get the object position style
     *
     * @return string
     */
    private function getObjectPositionStyle(): string
    {
        $object_position = $this->getObjectPosition();

        if (!$object_position) {
            return '';
        }

        return 'object-position: ' . round($object_position[0], 4) . '% ' . round($object_position[1], 4) . '%;';
    }

    /**
     * Get the LQIP background style
     *
     * @return string
     */
    private function getLqipBackgroundStyle(): string
    {
        if ($this->lazy === false || option('jan-herman.images.lqip') === false || $this->isSvg()) {
            return '';
        }

        $lqip_src = $this->getLqipSrc();

        if (!$lqip_src) {
            return '';
        }

        $style = 'background-image: url(\'' . $lqip_src . '\');';

        $object_position = $this->getObjectPosition();
        if ($object_position) {
            $object_position_x = round($object_position[0], 4);
            $object_position_y = round($object_position[1], 4);
            $style .= 'background-position: ' . $object_position_x . '% ' . $object_position_y . '%;';
        }

        return $style;
    }

    /**
     * Get the sizes attribute
     *
     * @return string
     */
    private function getSizes(): string
    {
        if (isset($this->sizes_cache)) {
            return $this->sizes_cache;
        }

        if (!$this->sizes && !$this->container) {
            $this->sizes_cache = '';
            return $this->sizes_cache;
        }

        $sizes = new Sizes(
            width: $this->getWidth(),
            height: $this->getHeight(),
            ratio: $this->aspect_ratio->get(),
            container: $this->container ? true : ($this->container === false ? false : null),
            container_width: is_bool($this->container) ? null : $this->container,
            classes: is_string($this->sizes) ? $this->sizes : null,
            sizes: is_array($this->sizes) ? $this->sizes : null,
        );

        $this->sizes_cache = $sizes->renderToString();

        return $this->sizes_cache;
    }

    /**
     * Get <source> tag
     *
     * @return string
     */
    private function getSourceTag(): string
    {
        if ($this->as !== 'picture' || $this->isSvg()) {
            return '';
        }

        $srcset = $this->thumb ? $this->getSrcWebp() : $this->getSrcsetWebp();

        if ($srcset === null) {
            return '';
        }

        if ($this->lazy === true) {
            $attributes = [
                'data-srcset'      => $srcset,
                'data-sizes'       => 'auto',
                'data-aspectratio' => $this->aspect_ratio->getIntrinsic(),
                'data-parent-fit'  => $this->object_fit,
                'type'             => 'image/webp',
            ];
        } else {
            $attributes = [
                'srcset'           => $srcset,
                'sizes'            => $this->getSizes(),
                'type'             => 'image/webp',
            ];
        }

        $attributes = array_filter($attributes);

        return Html::tag('source', null, $attributes);
    }

    /**
     * Get <img> tag
     *
     * @return string
     */
    private function getImgTag(): string
    {
        if ($this->lazy === true) {
            $attributes = [
                'class'      => 'lazyload',
                'data-src'   => $this->getSrc(),
                'data-sizes' => 'auto',
                'style'      => $this->getObjectPositionStyle()
            ];
        } else {
            $attributes = [
                'src'        => $this->getSrc(),
                'style'      => $this->getObjectPositionStyle()
            ];
        }

        if ($this->as !== 'picture') {
            $srcset = $this->thumb || $this->isSvg() ? null : $this->getSrcset();

            if ($this->lazy === true) {
                $attributes['data-srcset']      = $srcset;
                $attributes['data-aspectratio'] = $this->aspect_ratio->getIntrinsic();
                $attributes['data-parent-fit']  = $$this->object_fit;
            } else {
                $attributes['srcset'] = $srcset;
                $attributes['sizes']  = $this->getSizes();
            }
        }

        $attributes = array_merge(array_filter($attributes), $this->img_attr);

        $attributes['alt'] = $this->getAlt();

        return Html::tag('img', null, $attributes);
    }

    /**
     * Return the picture HTML as a string
     *
     * @return string
     */
    public function renderToString(): string
    {
        if ($this->getSrc() === null) {
            return '';
        }

        $source_tag = $this->getSourceTag();
        $img_tag    = $this->getImgTag();

        $attributes = [
            'class'          => $this->getClass(),
            'data-extension' => $this->getExtension(),
            'style'          => $this->getAspectRatioStyle() . $this->getLqipBackgroundStyle()
        ];

        $attributes = array_merge(array_filter($attributes), $this->attr);

        return Html::tag($this->as, [$source_tag, $img_tag], $attributes);
    }

    /**
     * Render the picture HTML
     */
    public function render(): void
    {
        echo $this->renderToString();
    }
}
