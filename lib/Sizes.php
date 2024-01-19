<?php

namespace JanHerman\Images;

use Kirby\Cms\File;

// Variable naming:
// string $breakpoint_name = 'sm', 'md' etc.
// int $breakpoint = 1024
// float $size = 0.0 - 1.0

class Sizes
{
    private int $grid_columns = 12;
    private bool $mobile_first = false;
    private array $breakpoints = [];
    private float $ratio_correction = 1.0;
    private array $sizes_array = [];

    public function __construct(
        public File|null $file                  = null,
        public int|null $width                  = null,
        public int|null $height                 = null,
        public float|null $ratio                = null,
        public bool|null $container             = null,
        public int|string|null $container_width = null,
        public string|null $classes             = null,
        public array|null $sizes                = null,
    ) {
        // set params from plugin options
        $this->grid_columns = option('jan-herman.images.grid.columns');
        $this->mobile_first = option('jan-herman.images.grid.mobileFirst');
        $this->breakpoints = option('jan-herman.images.grid.breakpoints');

        // set container default state
        if ($this->container === null) {
            $this->container = option('jan-herman.images.grid.container');
        }

        // set image dimensions from file if provided
        if ($this->file) {
            $this->width  = $this->file->width();
            $this->height = $this->file->height();
        }

        // add default breakpoint
        $this->breakpoints['default'] = $this->mobile_first ? 0 : 9999;

        // adjust breakpoints according to mobile_first setting
        if ($this->mobile_first) {
            $this->breakpoints['xxl'] = $this->breakpoints['xl'];
            $this->breakpoints['xl'] = $this->breakpoints['lg'];
            $this->breakpoints['lg'] = $this->breakpoints['md'];
            $this->breakpoints['md'] = $this->breakpoints['sm'];
            $this->breakpoints['sm'] = $this->breakpoints['xs'];
        }
    }

    public function __toString()
    {
        return $this->render();
    }

    private function setRatioCorrection(): void
    {
        if (!$this->ratio || !$this->width || !$this->height) {
            return;
        }

        $file_ratio = $this->width / $this->height;
        $correction = $file_ratio / (100 / $this->ratio);

        if ($correction < 1) {
            return;
        }

        $this->ratio_correction = $correction;
    }

    private function setContainerWidth(): void
    {
        // container is set to false => bail, we don't need the width
        if ($this->container === false) {
            return;
        }

        // container width is explicitly set => we're done
        if (is_int($this->container_width)) {
            return;
        }

        // container width is not set => set it from options
        $options = option('jan-herman.images.grid.containerWidth', 1180);

        if (is_int($options)) {
            $this->container_width = $options;

        } elseif (is_array($options)) {
            $container_size = is_string($this->container_width) ? $this->container_width : 'default';

            if (!isset($options[$container_size])) {
                trigger_error('Container size \''. $container_size . '\' is not present in \'jan.herman.images.grid.containerWidth\' option.', E_USER_ERROR);
            }

            $this->container_width = $options[$container_size];
        }
    }

    private function setSizes(): void
    {
        // get sizes from $sizes param
        $sizes = $this->sizes ?? [];

        // add sizes from $classes if provided
        if ($this->classes) {
            $sizes += $this->getSizesFromClasses($this->classes);
        }

        // add default breakpoint if not provided
        if (!isset($sizes['default'])) {
            $sizes['default'] = 1.0;
        }

        // set sizes from an array of breakpoints
        foreach ($sizes as $breakpoint_name => $size) {
            $this->setSize($breakpoint_name, $size);
        }

        // remove duplicate sizes
        $this->removeDuplicateSizes();

        // add container size
        if ($this->container) {
            $this->setSize($this->container_width, $this->getContainerSize());
            $this->sortSizes(); // sort the array again
        }
    }

    private function setSize(int|string $breakpoint_name, float $size): void
    {
        if ($size > 1.0) {
            trigger_error('Provided image size \''. $size . '\' cannot be larger then 1.0', E_USER_ERROR);
        }

        $breakpoint = is_numeric($breakpoint_name) ? $breakpoint_name : $this->getBreakpoint($breakpoint_name);

        $this->sizes_array[(int) $breakpoint] = (float) $size;
    }

    private function getBreakpoint(string $breakpoint_name): int
    {
        if (!isset($this->breakpoints[$breakpoint_name])) {
            trigger_error('Provided breakpoint name \''. $breakpoint_name . '\' is not in valid.', E_USER_ERROR);
        }

        return $this->breakpoints[$breakpoint_name];
    }

    private function getSizesFromClasses(string $classes): array
    {
        $sizes = [];
        $class_array = explode(' ', $classes);

        foreach ($class_array as $class) {
            $colspan = explode('-', $class, 2)[1];
            $size = (float) $colspan / $this->grid_columns;
            $breakpoint_name = substr($class, 0, strpos($class, ':')) ?: 'default';

            $sizes[$breakpoint_name] = $size;
        }

        return $sizes;
    }

    private function sortSizes(): void
    {
        if ($this->mobile_first) {
            krsort($this->sizes_array, SORT_NUMERIC);
        } else {
            ksort($this->sizes_array, SORT_NUMERIC);
        }
    }

    private function removeDuplicateSizes(): void
    {
        // first make sure the array is sorted
        $this->sortSizes();

        // find consecutive breakpoints with the same size and remove them
        $breakpoints_to_remove = [];

        $previous_breakpoint = null;
        $previous_size = null;

        foreach ($this->sizes_array as $breakpoint => $size) {
            if ($size === $previous_size) {
                $breakpoints_to_remove[] = $previous_breakpoint;
            }
            $previous_breakpoint = $breakpoint;
            $previous_size = $size;
        }

        foreach ($breakpoints_to_remove as $breakpoint) {
            unset($this->sizes_array[$breakpoint]);
        }
    }

    private function getContainerSize(): float
    {
        // first make sure the array is sorted
        $this->sortSizes();

        // find the closest breakpoint to container width and return it's size
        $container_size = 1.0;

        foreach ($this->sizes_array as $breakpoint => $size) {
            if (
                ($this->mobile_first && $breakpoint <= $this->container_width) ||
                (!$this->mobile_first && $breakpoint >= $this->container_width)
            ) {
                $container_size = $size;
                break;
            }
        }

        return $container_size;
    }

    private function createMediaQuery(int $breakpoint): string
    {
        if ($this->mobile_first) {
            return $breakpoint === $this->getBreakpoint('default') ? '' : '(min-width: ' . $breakpoint . 'px) ';
        } else {
            return $breakpoint === $this->getBreakpoint('default') ? '' : '(max-width: ' . $breakpoint - 0.02 . 'px) ';
        }
    }

    private function getImageWidthFromSize(int $breakpoint, float $size): string
    {
        if ($this->container) {
            if (
                ($this->mobile_first && $this->container_width <= $breakpoint) ||
                (!$this->mobile_first && $this->container_width < $breakpoint)
            ) {
                return round($this->container_width * $size * $this->ratio_correction, 0, PHP_ROUND_HALF_UP) . 'px';
            }
        }

        return round(100 * $size * $this->ratio_correction, 2, PHP_ROUND_HALF_UP) . 'vw';
    }

    public function render(): string
    {
        $this->setRatioCorrection();
        $this->setContainerWidth();
        $this->setSizes();

        // bdump($this->sizes_array);

        $parts = [];

        foreach ($this->sizes_array as $breakpoint => $size) {
            $parts[] = $this->createMediaQuery($breakpoint) . $this->getImageWidthFromSize($breakpoint, $size);
        }

        return implode(', ', $parts);
    }
}
