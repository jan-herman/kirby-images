<?php

namespace JanHerman\Images;

// Variable naming:
// string $breakpoint_name = 'sm', 'md' etc.
// int $breakpoint = 1024
// float $size = 0.0 - 1.0

class Sizes
{
    private float $ratioCorrection = 1.0;
    private array $breakpoints;
    private array $sizesArray = [];

    public function __construct(
        private float|null $ratio                   = null,
        private float|null $intendedRatio           = null,
        private bool|null $container                = null,
        private int|string|null $containerWidth     = null,
        private string|null $classes                = null,
        private array|null $sizes                   = null,
        private int $gridColumns                    = 12,
        private bool $mobileFirst                   = false,
        array $breakpoints                          = [],
        private int|array|null $containerWidths     = null,
    ) {
        // Prepare breakpoints from explicit config. Image owns option loading.
        $this->breakpoints = $this->normalizeBreakpoints($breakpoints);
        $this->containerWidths ??= 1180;
    }

    public function __toString(): string
    {
        return $this->renderToString();
    }

    public static function isRawString(string $sizes): bool
    {
        return preg_match('/[,()]/', $sizes) === 1
            || preg_match('/\b(?:calc|min|max|clamp)\s*\(/i', $sizes) === 1
            || preg_match('/\b\d*\.?\d+(?:px|vw|vh|vmin|vmax|rem|em|ch|ex|cm|mm|in|pt|pc|%|cqw|cqh|cqi|cqb|cqmin|cqmax)\b/i', $sizes) === 1;
    }

    private function normalizeBreakpoints(array $breakpoints): array
    {
        // Add the synthetic fallback breakpoint used for the final sizes part.
        $breakpoints['default'] = $this->mobileFirst ? 0 : 9999;

        // Preserve the legacy mobile-first breakpoint remapping.
        if ($this->mobileFirst) {
            $breakpoints['xxl'] = $breakpoints['xl'];
            $breakpoints['xl'] = $breakpoints['lg'];
            $breakpoints['lg'] = $breakpoints['md'];
            $breakpoints['md'] = $breakpoints['sm'];
            $breakpoints['sm'] = $breakpoints['xs'];
        }

        return $breakpoints;
    }

    private function resolveRatioCorrection(): void
    {
        // Correct slot width when the rendered crop is narrower than the source.
        if (!$this->ratio || !$this->intendedRatio) {
            return;
        }

        $correction = $this->ratio / $this->intendedRatio;

        if ($correction < 1) {
            return;
        }

        $this->ratioCorrection = $correction;
    }

    private function resolveContainerWidth(): void
    {
        // Container is disabled, so no container width is needed.
        if ($this->container === false) {
            return;
        }

        // Container width was explicitly provided.
        if (is_int($this->containerWidth)) {
            return;
        }

        // Configured container width is a single default value.
        if (is_int($this->containerWidths)) {
            $this->containerWidth = $this->containerWidths;
            return;
        }

        // Configured container widths are named sizes.
        if (is_array($this->containerWidths)) {
            $container_size = is_string($this->containerWidth) ? $this->containerWidth : 'default';

            if (!isset($this->containerWidths[$container_size])) {
                throw new \InvalidArgumentException('[kirby-images] Container size "' . $container_size . '" is not present in `jan-herman.images.sizes.containerWidth` option.');
            }

            $this->containerWidth = $this->containerWidths[$container_size];
        }
    }

    private function buildSizesArray(): void
    {
        // Start with explicit breakpoint sizes.
        $sizes = $this->sizes ?? [];

        // Add sizes parsed from grid classes, without overriding explicit sizes.
        if ($this->classes) {
            $sizes += $this->getSizesFromClasses($this->classes);
        }

        // Add the fallback size if it was not provided.
        if (!isset($sizes['default'])) {
            $sizes['default'] = 1.0;
        }

        // Resolve named and numeric breakpoints into the internal sizes array.
        foreach ($sizes as $breakpoint_name => $size) {
            $this->setSize($breakpoint_name, $size);
        }

        // Remove redundant consecutive rules before the container rule is added.
        $this->removeDuplicateSizes();

        // Add the container clamp rule and sort the array again.
        if ($this->container) {
            $this->setSize($this->containerWidth, $this->getContainerSize());
            $this->sortSizes();
        }
    }

    private function setSize(int|string $breakpoint_name, int|float|string $size): void
    {
        if (!is_numeric($size)) {
            throw new \InvalidArgumentException('[kirby-images] Provided image size "' . $size . '" is not numeric.');
        }

        $size = (float) $size;

        if ($size > 1.0) {
            throw new \InvalidArgumentException('[kirby-images] Provided image size "' . $size . '" cannot be larger than 1.0.');
        }

        $breakpoint = is_numeric($breakpoint_name) ? $breakpoint_name : $this->getBreakpoint($breakpoint_name);

        $this->sizesArray[(int) $breakpoint] = $size;
    }

    private function getBreakpoint(string $breakpoint_name): int
    {
        if (!isset($this->breakpoints[$breakpoint_name])) {
            throw new \InvalidArgumentException('[kirby-images] Provided breakpoint name "' . $breakpoint_name . '" is not valid.');
        }

        return $this->breakpoints[$breakpoint_name];
    }

    private function getSizesFromClasses(string $classes): array
    {
        $sizes = [];
        $class_array = preg_split('/\s+/', trim($classes)) ?: [];

        foreach ($class_array as $class) {
            // Only consume recognized grid-size tokens; ignore unrelated classes.
            if (!preg_match('/^(?:(?<breakpoint>[A-Za-z][\w-]*):)?col-(?<columns>\d+(?:\.\d+)?)$/', $class, $matches)) {
                continue;
            }

            $breakpoint_name = $matches['breakpoint'] ?: 'default';
            $sizes[$breakpoint_name] = (float) $matches['columns'] / $this->gridColumns;
        }

        return $sizes;
    }

    private function sortSizes(): void
    {
        if ($this->mobileFirst) {
            krsort($this->sizesArray, SORT_NUMERIC);
        } else {
            ksort($this->sizesArray, SORT_NUMERIC);
        }
    }

    private function removeDuplicateSizes(): void
    {
        // First make sure the array is sorted in media-query output order.
        $this->sortSizes();

        // Find consecutive breakpoints with the same size and remove the earlier one.
        $breakpoints_to_remove = [];
        $previous_breakpoint = null;
        $previous_size = null;

        foreach ($this->sizesArray as $breakpoint => $size) {
            if ($size === $previous_size) {
                $breakpoints_to_remove[] = $previous_breakpoint;
            }

            $previous_breakpoint = $breakpoint;
            $previous_size = $size;
        }

        foreach ($breakpoints_to_remove as $breakpoint) {
            unset($this->sizesArray[$breakpoint]);
        }
    }

    private function getContainerSize(): float
    {
        // First make sure the array is sorted in media-query output order.
        $this->sortSizes();

        // Find the closest breakpoint to the container width and return its size.
        $container_size = 1.0;

        foreach ($this->sizesArray as $breakpoint => $size) {
            if (
                ($this->mobileFirst && $breakpoint <= $this->containerWidth) ||
                (!$this->mobileFirst && $breakpoint >= $this->containerWidth)
            ) {
                $container_size = $size;
                break;
            }
        }

        return $container_size;
    }

    private function createMediaQuery(int $breakpoint): string
    {
        if ($this->mobileFirst) {
            return $breakpoint === $this->getBreakpoint('default') ? '' : '(min-width: ' . $breakpoint . 'px) ';
        }

        return $breakpoint === $this->getBreakpoint('default') ? '' : '(max-width: ' . $breakpoint - 0.02 . 'px) ';
    }

    private function getImageWidthFromSize(int $breakpoint, float $size): string
    {
        if ($this->container) {
            if (
                ($this->mobileFirst && $this->containerWidth <= $breakpoint) ||
                (!$this->mobileFirst && $this->containerWidth < $breakpoint)
            ) {
                return round($this->containerWidth * $size * $this->ratioCorrection, 0, PHP_ROUND_HALF_UP) . 'px';
            }
        }

        return round(100 * $size * $this->ratioCorrection, 2, PHP_ROUND_HALF_UP) . 'vw';
    }

    public function renderToString(): string
    {
        // Reset derived state so repeated rendering returns the same output.
        $this->ratioCorrection = 1.0;
        $this->sizesArray = [];

        $this->resolveRatioCorrection();
        $this->resolveContainerWidth();
        $this->buildSizesArray();

        $parts = [];

        foreach ($this->sizesArray as $breakpoint => $size) {
            $parts[] = $this->createMediaQuery($breakpoint) . $this->getImageWidthFromSize($breakpoint, $size);
        }

        return implode(', ', $parts);
    }

    public function render(): void
    {
        echo $this->renderToString();
    }
}
