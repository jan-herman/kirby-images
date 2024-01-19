<?php

use Kirby\Cms\App as Kirby;
use Kirby\Toolkit\Config;

use JanHerman\Images\Latte\LatteExtension;

@include_once __DIR__ . '/vendor/autoload.php';

Kirby::plugin('jan-herman/images', [
    'options' => [
        'grid' => [
            'container' => true,
            'containerWidth' => 1180,
            'columns' => 12,
            'mobileFirst' => false,
            'breakpoints' => [
                'xl' => 1400,
                'lg' => 1280,
                'md' => 992,
                'sm' => 768,
                'xs' => 576
            ]
        ]
    ],
    'snippets' => [
        'image' => __DIR__ . '/snippets/image.latte'
    ],
    'hooks' => [
        'jan-herman.barista.init:after' => function ($latte) {
            $latte->addExtension(new LatteExtension());
        }
    ],
    'fileMethods' => [
        'thumbWebp' => function (array|string|null $options): \Kirby\Cms\FileVersion|\Kirby\Cms\File {
            $presets = Config::get('thumbs.presets');

            if (is_null($options)) {
                $options = $presets['default'] ?? null;
            } elseif (is_string($options)) {
                $options = $presets[$options] ?? null;
            }

            if (!$options || !is_array($options)) {
                return $this;
            }

            $quality = Config::get('thumbs.qualityWebp', 85);
            $options_webp = array_merge($options, ['format' => 'webp', 'quality' => $quality]);

            return $this->thumb($options_webp);
        },
        'srcsetWebp' => function (array|string|null $sizes = null): string|null {
            if (is_string($sizes)) {
                $srcsets = Config::get('thumbs.srcsets');
                $sizes = $srcsets[$sizes] ?? null;
            }

            if (!$sizes || !is_array($sizes)) {
                return null;
            }

            $quality = Config::get('thumbs.qualityWebp', 85);
            foreach ($sizes as $size => $options) {
                $sizes_webp[$size] = array_merge($options, ['format' => 'webp', 'quality' => $quality]);
            }

            return $this->srcset($sizes_webp);
        },
        'ratioPercentage' => function (string|int|float $ratio = 'auto'): float {
            if ($ratio === 'auto') {
                return 1 / $this->ratio() * 100;
            }

            if (is_string($ratio)) {
                $parts = explode('/', $ratio);
                if (count($parts) !== 2 || !is_numeric($parts[0]) || !is_numeric($parts[1])) {
                    return 0.0;
                }
                return (float) $parts[1] / $parts[0] * 100;
            }

            return (float) $ratio;
        }
    ]
]);
