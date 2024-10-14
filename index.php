<?php

use Kirby\Cms\App as Kirby;
use JanHerman\Images\AspectRatio;
use JanHerman\Images\Latte\LatteExtension;

@include_once __DIR__ . '/vendor/autoload.php';

Kirby::plugin('jan-herman/images', [
    'options' => [
        'legacyAspectRatio' => true, // deprecated (will change in v2.0)
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
            ],
        ],
        'lqip' => [
            'maxSize' => 16,
            'blurRadius' => 1,
            'quality' => 40,
        ],
    ],
    'snippets' => [
        'image' => __DIR__ . '/snippets/image.php'
    ],
    'hooks' => [
        'jan-herman.barista.init:after' => function ($latte) {
            $latte->addExtension(new LatteExtension());
        }
    ],
    'fileMethods' => [
        'thumbWebp' => require __DIR__ . '/helpers/thumbWebp.php',
        'srcsetWebp' => require __DIR__ . '/helpers/srcsetWebp.php',
        // deprecated (will be removed in v2.0)
        'ratioPercentage' => function (string|float|null $ratio = 'auto'): float|null {
            $aspect_ratio = new AspectRatio($ratio, $this->width(), $this->height());
            return $aspect_ratio->percentage();
        },
    ],
    'assetMethods' => [
        'thumbWebp' => require __DIR__ . '/helpers/thumbWebp.php',
        'srcsetWebp' => require __DIR__ . '/helpers/srcsetWebp.php',
        // deprecated (will be removed in v2.0)
        'ratioPercentage' => function (string|float|null $ratio = 'auto'): float|null {
            $aspect_ratio = new AspectRatio($ratio, $this->width(), $this->height());
            return $aspect_ratio->percentage();
        },
    ],
]);
