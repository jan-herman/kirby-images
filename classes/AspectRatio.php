<?php

namespace JanHerman\Images;

class AspectRatio
{
    public static function standardize(mixed $ratio): float
    {
        if (is_string($ratio)) {
            return self::fromString($ratio);
        }

        if (is_numeric($ratio)) {
            if ($ratio <= 0) {
                throw new \InvalidArgumentException('[kirby-images] Invalid ratio. Ratio must be greater than 0.');
            } elseif ($ratio <= 5) { // standard ratio
                return (float) $ratio;
            } else { // percentage
                return 1 / ($ratio / 100);
            }
        }

        throw new \InvalidArgumentException('[kirby-images] Invalid ratio.');
    }

    public static function fromString(string $ratio): float
    {
        if (!preg_match('/^\d+\/\d+$/', $ratio)) {
            throw new \InvalidArgumentException('[kirby-images] Invalid ratio format. Expected format "x/y".');
        }

        $ratio_array = explode('/', $ratio);
        $x = (float) $ratio_array[0];
        $y = (float) $ratio_array[1];

        if ($x === 0.0 || $y === 0.0) {
            throw new \InvalidArgumentException('[kirby-images] Invalid ratio format. "x" and "y" must be greater than 0.');
        }

        return  $x / $y;
    }

    public static function toPercentage(float $ratio): float
    {
        if ($ratio <= 0) {
            throw new \InvalidArgumentException('[kirby-images] Invalid ratio. Ratio must be greater than 0.');
        }

        return 1 / $ratio * 100;
    }
}
