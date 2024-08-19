<?php

namespace JanHerman\Images;

class AspectRatio
{
    private float|null $calculated_ratio = null;

    public function __construct(
        public string|float|null $ratio = null,
        public int|null $width          = null,
        public int|null $height         = null,
    ) {
        if (is_string($this->ratio)) {
            if ($this->ratio === 'auto' || $this->ratio === 'intrinsic') { // auto || intrinsic
                $this->calculated_ratio = $this->getAspectRatioFromDimensions();
            } else { // string like '16/9'
                $this->calculated_ratio = $this->getAspectRatioFromString($this->ratio);
            }
        } elseif (is_numeric($this->ratio)) {
            if ($this->ratio === 0) {
                return;
            } elseif ($this->ratio <= 5) { // standard ratio
                $this->calculated_ratio = $this->ratio;
            } else { // percentage
                $this->calculated_ratio = 1 / ($this->ratio / 100);
            }
        }
    }

    private function getAspectRatioFromDimensions(): float
    {
        if ($this->width === null || $this->height === null) {
            throw new \InvalidArgumentException('[kirby-images] You must provide width and height to calculate the aspect ratio.');
        }

        return $this->width / $this->height;
    }

    private function getAspectRatioFromString(string $ratio_string): float
    {
        if (!preg_match('/^\d+\/\d+$/', $ratio_string)) {
            throw new \InvalidArgumentException('[kirby-images] Invalid ratio format. Expected format "x/y".');
        }

        $ratio_array = explode('/', $ratio_string);
        $x = (int) $ratio_array[0];
        $y = (int) $ratio_array[1];

        if ($x === 0 || $y === 0) {
            throw new \Exception('[kirby-images] Invalid ratio format. "x" and "y" must be greater than 0.');
        }

        return  $x / $y;
    }

    public function get(): float|null
    {
        return $this->calculated_ratio;
    }

    public function percentage(): float|null
    {
        if ($this->calculated_ratio === null) {
            return null;
        }

        return 1 / $this->calculated_ratio * 100;
    }
}
