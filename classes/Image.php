<?php

namespace JanHerman\Images;

use Kirby\Content\Field;
use Kirby\Cms\File;
use Kirby\Cms\FileVersion;
use Kirby\Filesystem\Asset;
use Kirby\Image\Focus;
use Kirby\Toolkit\A;
use Kirby\Template\Snippet;

class Image
{
    private array $options;

    private ?File $file = null;
    private ?Asset $asset = null;
    private ?string $placeholder = null;
    private string|null|false $sizes = false;
    private ?array $formats = null;

    /**
     * Stores options and prepares the image source and rendering options.
     */
    public function __construct(array $options)
    {
        $this->options = $options;

        $this->resolveSource();
        $this->normalizeAttributes();
        $this->normalizeLoading();
        $this->normalizeThumbOptions();
    }

    /**
     * Resolves the preferred source option into a file or asset.
     */
    private function resolveSource(): void
    {
        $image = $this->option('image');

        if ($image) {
            $this->setSource($image);
            return;
        }

        $field = $this->option('field');
        $file = $this->option('file');
        $asset = $this->option('asset');

        if ($field instanceof Field) {
            $this->setSource($field);
            return;
        }

        if ($file instanceof File) {
            $this->setSource($file);
            return;
        }

        if ($asset instanceof Asset) {
            $this->setSource($asset);
        }
    }

    /**
     * Stores a field, file, asset or asset path as the active source.
     */
    private function setSource(mixed $source): void
    {
        if ($source instanceof Field) {
            $this->file = $source->toFile();
            return;
        }

        if ($source instanceof File) {
            $this->file = $source;
            return;
        }

        if ($source instanceof Asset) {
            $this->asset = $source;
            return;
        }

        if (is_string($source)) {
            $this->asset = asset($this->resolveAssetPath($source));
        }
    }

    /**
     * Resolves Barista path aliases before creating a Kirby asset.
     */
    private function resolveAssetPath(string $path): string
    {
        // Process path aliases if barista is loaded.
        if (function_exists('barista')) {
            $path = barista()->resolvePathAlias($path);
            $basePath = kirby()->root('index') . '/';

            if (str_starts_with($path, $basePath)) {
                $path = substr($path, strlen($basePath));
            }
        }

        return $path;
    }

    /**
     * Moves shared attributes to imgAttr when rendering a bare img tag.
     */
    private function normalizeAttributes(): void
    {
        if ($this->option('tag') !== 'img') {
            return;
        }

        $attr = $this->option('attr', []);
        $imgAttr = $this->option('imgAttr', []);

        $this->options['imgAttr'] = array_merge($attr, $imgAttr);
        unset($this->options['attr']);
    }

    /**
     * Converts lazy and eager shortcut options into the loading option.
     */
    private function normalizeLoading(): void
    {
        if ($this->option('lazy') === true) {
            $this->options['loading'] = 'lazy';
            unset($this->options['lazy']);
        }

        if ($this->option('eager') === true) {
            $this->options['loading'] = 'eager';
            unset($this->options['lazy']);
        }
    }

    /**
     * Collects thumbnail shorthand options into the thumb option.
     */
    private function normalizeThumbOptions(): void
    {
        $shorthands = [
            'autoOrient',
            'crop',
            'blur',
            'grayscale',
            'height',
            'quality',
            'sharpen',
            'width',
            'format',
            'profiles',
        ];

        $thumbOptions = $this->option('thumb');
        $shorthandOptions = array_intersect_key($this->options(), array_flip($shorthands));

        if (!$shorthandOptions) {
            return;
        }

        if (!empty($thumbOptions)) {
            throw new \Exception('[kirby-images] You cannot use combination of \'thumb\' and \'' . implode('\', \'', array_keys($shorthandOptions)) . '\' shorthand options.');
        }

        $this->options['thumb'] = $shorthandOptions;
        $this->options = array_diff_key($this->options, $shorthandOptions);
    }

    /**
     * Renders the image when the object is cast to a string.
     */
    public function __toString(): string
    {
        return $this->renderToString();
    }

    /**
     * Forwards unknown method calls to the resolved file or asset.
     */
    public function __call(string $name, array $arguments): mixed
    {
        return $this->fileOrAsset()->$name(...$arguments);
    }

    /**
     * Returns an option value or a default fallback.
     */
    public function option(string $key, $default = null): mixed
    {
        return $this->options[$key] ?? $default;
    }

    /**
     * Checks whether an option exists, including null values.
     */
    public function hasOption(string $key): bool
    {
        return array_key_exists($key, $this->options);
    }

    /**
     * Returns all normalized options.
     */
    public function options(): array
    {
        return $this->options;
    }

    /**
     * Checks whether the source is a Kirby file.
     */
    public function isFile(): bool
    {
        return $this->file !== null;
    }

    /**
     * Checks whether the source is a Kirby asset.
     */
    public function isAsset(): bool
    {
        return $this->asset !== null;
    }

    /**
     * Checks whether any source could be resolved.
     */
    public function hasSource(): bool
    {
        return $this->file !== null || $this->asset !== null;
    }

    /**
     * Returns the resolved file or asset.
     */
    public function fileOrAsset(): File|Asset
    {
        if ($this->file !== null) {
            return $this->file;
        }

        if ($this->asset !== null) {
            return $this->asset;
        }

        throw new \LogicException('[kirby-images] No image source could be resolved.');
    }

    /**
     * Checks whether the image is lazy loaded.
     */
    public function isLazy(): bool
    {
        return $this->option('loading') === 'lazy';
    }

    /**
     * Checks whether the source is an SVG.
     */
    public function isSvg(): bool
    {
        return $this->extension() === 'svg';
    }

    /**
     * Checks whether the generated thumbnail is cropped in the backend.
     */
    public function isCropped(): bool
    {
        $options = $this->thumbOptions($this->fallbackFormat());

        return ($options['crop'] ?? false) !== false;
    }

    /**
     * Checks whether CSS must crop the image to the requested ratio.
     */
    public function usesCssCrop(): bool
    {
        if ($this->option('crop', false) !== false || $this->isCropped()) {
            return false;
        }

        return abs($this->intendedRatio() - $this->ratio()) > 0.0001;
    }

    /**
     * Checks whether the source format may contain transparency.
     */
    public function hasAlphaChannel(): bool
    {
        return in_array($this->extension(), ['png', 'svg', 'gif'], true);
    }

    /**
     * Returns the explicit alt option, file alt text or an empty alt.
     */
    public function alt(): string
    {
        if ($this->hasOption('alt')) {
            return (string) $this->option('alt');
        }

        if ($this->isFile()) {
            return $this->file->alt()->toString();
        }

        return '';
    }

    /**
     * Builds and caches the configured output formats.
     */
    public function formats(): array
    {
        if ($this->formats !== null) {
            return $this->formats;
        }

        $defaultFormat = option('thumbs.format', 'original');
        $thumb = $this->option('thumb');
        $option = $this->option('formats');
        $configuredFormats = option('jan-herman.images.formats', []);

        if (is_array($thumb) && array_key_exists('format', $thumb)) {
            $option = $thumb['format'] ?? 'original';
        } elseif (!$option && $configuredFormats) {
            $option = $configuredFormats;
        } elseif (!$option) {
            $option = $defaultFormat;
        }

        $formats = [];

        foreach (A::wrap($option) as $format) {
            if (is_string($format)) {
                if ($format === 'original') {
                    $formats[$format] = ['format' => null];
                } else {
                    $formats[$format]['format'] = $format;
                }
                continue;
            }

            if (is_array($format) && array_key_exists('format', $format)) {
                $formats[$format['format'] ?? 'original'] = $format;
                continue;
            }

            throw new \Exception('[kirby-images] Invalid formats option.');
        }

        return $this->formats = $formats;
    }

    /**
     * Returns the configured options for one output format.
     */
    private function formatOptions(string $format): array
    {
        $formats = $this->formats();

        if (!array_key_exists($format, $formats)) {
            throw new \InvalidArgumentException('[kirby-images] Format "' . $format . '" is not configured.');
        }

        return $formats[$format];
    }

    /**
     * Returns the format used by the fallback img tag.
     */
    public function fallbackFormat(): string
    {
        $format = array_key_last($this->formats());

        return is_string($format) ? $format : 'original';
    }

    /**
     * Returns picture source formats without the fallback format.
     */
    public function sources(): array
    {
        if ($this->isSvg()) {
            return [];
        }

        $formats = $this->formats();

        if (count($formats) < 2) {
            return [];
        }

        return array_slice($formats, 0, -1);
    }

    /**
     * Returns the MIME type for an output format.
     */
    public function mimeType(string $format): string
    {
        return match (strtolower($format)) {
            'jpg', 'jpeg' => 'image/jpeg',
            'svg' => 'image/svg+xml',
            default => 'image/' . strtolower($format),
        };
    }

    /**
     * Checks whether the image should use a single thumbnail instead of srcset.
     */
    private function usesSingleThumb(): bool
    {
        $thumb = $this->option('thumb');

        if (is_string($thumb)) {
            return $thumb !== '';
        }

        return is_array($thumb) && (($thumb['width'] ?? null) !== null || ($thumb['height'] ?? null) !== null);
    }

    /**
     * Creates a thumbnail for the requested format.
     */
    public function thumb(?string $format = null): FileVersion|File|Asset
    {
        $format ??= $this->fallbackFormat();

        return $this->fileOrAsset()->thumb($this->thumbOptions($format));
    }

    /**
     * Creates a srcset for the requested format.
     */
    public function srcset(?string $format = null): ?string
    {
        $format ??= $this->fallbackFormat();

        return $this->fileOrAsset()->srcset($this->srcsetOptions($format));
    }

    /**
     * Combines thumb options with the requested format options.
     */
    private function thumbOptions(string $format): array|string|null
    {
        $defaultOptions = option('thumbs.presets.default', []);
        $formatOptions = $this->formatOptions($format);
        $overrides = $this->option('thumb', []);

        if (is_array($overrides)) {
            unset($overrides['format']);
            $options = array_merge($defaultOptions, $formatOptions, $overrides);
        } elseif (is_string($overrides)) {
            $preset = option('thumbs.presets.' . $overrides, []);
            $options = array_merge($preset, $formatOptions);
        } else {
            $options = array_merge($defaultOptions, $formatOptions);
        }

        return $this->completeCropDimensions($options);
    }

    /**
     * Combines srcset sizes with the requested format options.
     */
    private function srcsetOptions(string $format): array|string|null
    {
        $srcset = $this->option('srcset', 'default');
        $formatOptions = $this->formatOptions($format);
        $thumbOptions = $this->option('thumb', []);

        if (is_array($thumbOptions)) {
            unset($thumbOptions['width'], $thumbOptions['height'], $thumbOptions['format']);
        } else {
            $thumbOptions = [];
        }

        $sizes = $srcset;

        if (empty($sizes)) {
            $sizes = option('thumbs.srcsets.default', []);
        } elseif (is_string($sizes)) {
            $sizes = option('thumbs.srcsets.' . $sizes, []);
        }

        if (!is_array($sizes)) {
            return $srcset;
        }

        $options = [];

        foreach ($sizes as $key => $value) {
            if (is_array($value)) {
                $condition = $key;
                $sizeOptions = array_merge($value, $formatOptions, $thumbOptions);
            } elseif (is_string($value)) {
                $condition = $value;
                $sizeOptions = array_merge(['width' => $key], $formatOptions, $thumbOptions);
            } else {
                $condition = $value . 'w';
                $sizeOptions = array_merge(['width' => $value], $formatOptions, $thumbOptions);
            }

            $options[$condition] = $this->completeCropDimensions($sizeOptions);
        }

        return $options;
    }

    /**
     * Completes cropped thumbnail dimensions using an explicit custom ratio.
     */
    private function completeCropDimensions(array $options): array
    {
        if (
            ($options['crop'] ?? false) === false ||
            in_array($this->option('ratio'), [null, false, 'auto', 'intrinsic'], true)
        ) {
            return $options;
        }

        $ratio = $this->intendedRatio();
        $width = $options['width'] ?? null;
        $height = $options['height'] ?? null;

        if ($width === null && $height === null) {
            if ($this->ratio() > $ratio) {
                $options['height'] = $height = $this->height();
                $options['width'] = (int) round($height * $ratio);
            } else {
                $options['width'] = $width = $this->width();
                $options['height'] = (int) round($width / $ratio);
            }
        } elseif ($width !== null && $height === null) {
            $options['height'] = (int) round($width / $ratio);
        } elseif ($height !== null && $width === null) {
            $options['width'] = (int) round($height * $ratio);
        }

        return $options;
    }

    /**
     * Checks whether the fallback img tag should include srcset.
     */
    public function hasImgSrcset(): bool
    {
        if ($this->usesSingleThumb() || $this->isSvg()) {
            return false;
        }

        if ($this->option('tag') === 'img' || count($this->formats()) < 2) {
            return true;
        }

        return option('jan-herman.images.fallbackImgSrcset', false) === true;
    }

    /**
     * Checks whether a placeholder should be rendered.
     */
    public function usesPlaceholder(): bool
    {
        if (in_array($this->extension(), ['svg', 'gif'], true)) {
            return false;
        }

        if ($this->hasOption('placeholder')) {
            return !in_array($this->option('placeholder'), [false, null, ''], true);
        }

        return $this->isLazy() && option('jan-herman.images.placeholder.enabled') === true;
    }

    /**
     * Returns the explicit or generated placeholder.
     */
    public function placeholder(): string
    {
        if ($this->placeholder !== null) {
            return $this->placeholder;
        }

        $option = $this->hasOption('placeholder')
            ? $this->option('placeholder')
            : option('jan-herman.images.placeholder.enabled');

        if (in_array($option, [false, null, ''], true) || in_array($this->extension(), ['svg', 'gif'], true)) {
            return $this->placeholder = '';
        }

        if (is_string($option)) {
            return $this->placeholder = $option;
        }

        $generator = option('jan-herman.images.placeholder.generator');

        if (!is_callable($generator)) {
            throw new \Exception('[kirby-images] `jan-herman.images.placeholder.generator` option must be a callable.');
        }

        return $this->placeholder = $generator($this);
    }

    /**
     * Returns normalized focus coordinates.
     */
    public function focus(): array
    {
        $customFocus = $this->option('focus');

        if ($customFocus !== null) {
            return $this->parseFocus($customFocus);
        }

        if ($this->isFile() && $this->file->focus()->isNotEmpty()) {
            return Focus::parse($this->file->focus()->toString());
        }

        return [0.5, 0.5];
    }

    /**
     * Parses and validates custom focus coordinates.
     */
    private function parseFocus(mixed $focus): array
    {
        if (is_string($focus)) {
            return Focus::parse($focus);
        }

        if (is_array($focus)) {
            $focus = array_values($focus);

            if (
                count($focus) !== 2 ||
                !is_numeric($focus[0]) ||
                !is_numeric($focus[1]) ||
                $focus[0] < 0.0 ||
                $focus[0] > 1.0 ||
                $focus[1] < 0.0 ||
                $focus[1] > 1.0
            ) {
                throw new \InvalidArgumentException('[kirby-images] Focus must be an array with two values between 0.0 and 1.0');
            }

            return [(float) $focus[0], (float) $focus[1]];
        }

        throw new \InvalidArgumentException('[kirby-images] Focus must be an array with two values between 0.0 and 1.0');
    }

    /**
     * Returns the requested output ratio or the intrinsic source ratio.
     */
    public function intendedRatio(): float
    {
        $ratio = $this->option('ratio');

        if (in_array($ratio, [null, false, 'auto', 'intrinsic'], true)) {
            return $this->ratio();
        }

        return AspectRatio::standardize($ratio);
    }

    /**
     * Calculates CSS object-position values for the configured focus.
     */
    public function objectPosition(): array
    {
        [$focusX, $focusY] = $this->focus();

        if ($focusX === 0.5 && $focusY === 0.5) {
            return [50.0, 50.0];
        }

        $ratio         = $this->ratio();
        $intendedRatio = $this->intendedRatio();

        $objectPositionX = $focusX * 100;
        $objectPositionY = $focusY * 100;

        if ($ratio > $intendedRatio) { // image is wider than the container
            $base = $ratio;
            $containerWidthRatio = $intendedRatio / $ratio;
            $containerWidth = $containerWidthRatio * $base;
            $maxOffset = $base - $containerWidth;
            $containerCenterX = $base * $focusX;
            $containerOffsetX = $containerCenterX - ($containerWidth * 0.5);

            $objectPositionX = ($containerOffsetX / $maxOffset) * 100;
        } elseif ($ratio < $intendedRatio) { // image is taller than the container
            $base = $intendedRatio;
            $containerHeightRatio = $ratio / $intendedRatio;
            $containerHeight = $containerHeightRatio * $base;
            $maxOffset = $base - $containerHeight;
            $containerCenterY = $base * $focusY;
            $containerOffsetY = $containerCenterY - ($containerHeight * 0.5);

            $objectPositionY = ($containerOffsetY / $maxOffset) * 100;
        }

        // Clamp the values between 0% and 100%.
        $objectPositionX = (float) max(0, min(100, $objectPositionX));
        $objectPositionY = (float) max(0, min(100, $objectPositionY));

        return [
            $objectPositionX,
            $objectPositionY,
        ];
    }

    /**
     * Formats the object-position values for CSS.
     */
    public function objectPositionString(): string
    {
        [$objectPositionX, $objectPositionY] = $this->objectPosition();

        return round($objectPositionX, 4) . '% ' . round($objectPositionY, 4) . '%';
    }

    /**
     * Renders and caches the sizes attribute.
     */
    public function sizes(): ?string
    {
        if ($this->sizes !== false) {
            return $this->sizes;
        }

        if (!$this->hasOption('sizes')) {
            return $this->sizes = null;
        }

        $sizes = $this->option('sizes');

        if (is_string($sizes) && Sizes::isRawString($sizes)) {
            return $this->sizes = $sizes;
        }

        if (!is_string($sizes) && !is_array($sizes)) {
            throw new \InvalidArgumentException('[kirby-images] Sizes option must be a string or array.');
        }

        $container = $this->option('container', option('jan-herman.images.sizes.container'));
        $containerWidth = null;

        if (is_int($container)) {
            $containerWidth = $container;
            $container = true;
        } elseif ($container !== null && !is_bool($container)) {
            $containerWidth = $container;
            $container = true;
        }

        return $this->sizes = (new Sizes(
            ratio: $this->ratio(),
            intendedRatio: $this->usesCssCrop() ? $this->intendedRatio() : null,
            container: $container,
            containerWidth: $containerWidth,
            classes: is_string($sizes) ? $sizes : null,
            sizes: is_array($sizes) ? $sizes : null,
            gridColumns: option('jan-herman.images.sizes.columns', 12),
            mobileFirst: option('jan-herman.images.sizes.mobileFirst', false),
            breakpoints: option('jan-herman.images.sizes.breakpoints', []),
            containerWidths: option('jan-herman.images.sizes.containerWidth', 1180),
        ))->renderToString();
    }

    /**
     * Adds native automatic sizing for lazy-loaded responsive images.
     */
    public function sizesWithAuto(): ?string
    {
        $sizes = $this->sizes();

        if ($this->isLazy() === false) {
            return $sizes;
        }

        if (!$sizes) {
            return 'auto';
        }

        if (preg_match('/^\s*auto(?:\s*,|\s*$)/i', $sizes) === 1) {
            return $sizes;
        }

        return 'auto, ' . $sizes;
    }

    /**
     * Removes null and false values from HTML attributes.
     */
    public function attrHelper(array $attributes): array
    {
        return array_filter(
            $attributes,
            fn ($value) => $value !== null && $value !== false
        );
    }

    /**
     * Serializes a CSS declaration map.
     */
    public function styleHelper(array $styles): string
    {
        $output = [];

        foreach ($styles as $property => $value) {
            if ($value === null || $value === false || $value === '') {
                continue;
            }

            if (!is_scalar($value)) {
                throw new \InvalidArgumentException('[kirby-images] Style value for "' . $property . '" must be scalar.');
            }

            $output[] = $property . ': ' . $value . ';';
        }

        return implode(' ', $output);
    }

    /**
     * Returns the source snippet for the configured lazy loading library.
     */
    private function sourceTemplate(): string
    {
        $lazyLoadingLibrary = option('jan-herman.images.lazyLoadingLibrary');

        if ($lazyLoadingLibrary && $this->isLazy()) {
            return 'images/source.' . $lazyLoadingLibrary;
        }

        return 'images/source';
    }

    /**
     * Returns the img snippet for the configured lazy loading library.
     */
    private function imgTemplate(): string
    {
        $lazyLoadingLibrary = option('jan-herman.images.lazyLoadingLibrary');

        if ($lazyLoadingLibrary && $this->isLazy()) {
            return 'images/img.' . $lazyLoadingLibrary;
        }

        return 'images/img';
    }

    /**
     * Renders the img snippet.
     */
    public function renderImgToString(array $data = []): string
    {
        if (!$this->hasSource()) {
            return '';
        }

        $data = array_merge($data, [
            'image' => $this
        ]);

        return Snippet::load(Snippet::file($this->imgTemplate()), $data);
    }

    /**
     * Echoes the img snippet.
     */
    public function renderImg(array $data = []): void
    {
        echo $this->renderImgToString($data);
    }

    /**
     * Renders the picture source snippets.
     */
    public function renderSourcesToString(array $data = []): string
    {
        if (!$this->hasSource()) {
            return '';
        }

        $output = [];
        $snippet = Snippet::file($this->sourceTemplate());

        foreach ($this->sources() as $formatName => $options) {
            $format = $options['format'] ?? null;

            if ($format === null) {
                continue;
            }

            $srcset = $this->usesSingleThumb()
                ? $this->thumb($format)->url()
                : $this->srcset($format);

            if (!$srcset) {
                continue;
            }

            $output[] = Snippet::load($snippet, array_merge($data, [
                'image' => $this,
                'format' => $format,
                'srcset' => $srcset,
            ]));
        }

        return implode('', $output);
    }

    /**
     * Echoes the picture source snippets.
     */
    public function renderSources(array $data = []): void
    {
        echo $this->renderSourcesToString($data);
    }

    /**
     * Renders either a bare img tag or a picture tag.
     */
    public function renderToString(array $data = []): string
    {
        if (!$this->hasSource()) {
            return '';
        }

        if ($this->option('tag') === 'img') {
            return $this->renderImgToString($data);
        }

        $data = array_merge($data, [
            'image' => $this
        ]);

        return Snippet::load(Snippet::file('images/picture'), $data);
    }

    /**
     * Echoes the rendered image markup.
     */
    public function render(array $data = []): void
    {
        echo $this->renderToString($data);
    }
}
