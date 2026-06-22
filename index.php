<?php

use Kirby\Cms\App as Kirby;
use Kirby\Image\Darkroom;
use JanHerman\Images\Image;
use JanHerman\Images\Lqip;
use JanHerman\Images\Latte\LatteExtension;
use JanHerman\Images\Darkroom\GdWithImagickForGifs;

@include_once __DIR__ . '/vendor/autoload.php';

Darkroom::$types['gd-with-imagick-for-gifs'] = GdWithImagickForGifs::class;

Kirby::plugin('jan-herman/images', [
    'options' => [
        'formats' => [],
        'baseClass' => 'image',
        'fallbackImgSrcset' => false,
        'lazyLoadingLibrary' => null,
        'placeholder' => [
            'enabled' => true,
            'generator' => fn (Image $image): string => new Lqip($image->fileOrAsset(), $image->option('thumb'))->toDataUri(),
        ],
        'sizes' => [
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
    ],
    'snippets' => [
        'images/picture'          => __DIR__ . '/snippets/picture.php',
        'images/source'           => __DIR__ . '/snippets/source.php',
        'images/source.lazysizes' => __DIR__ . '/snippets/source.lazysizes.php',
        'images/source.unlazy'    => __DIR__ . '/snippets/source.unlazy.php',
        'images/img'              => __DIR__ . '/snippets/img.php',
        'images/img.lazysizes'    => __DIR__ . '/snippets/img.lazysizes.php',
        'images/img.unlazy'       => __DIR__ . '/snippets/img.unlazy.php',
    ],
    'hooks' => [
        'jan-herman.barista.init:after' => function ($latte) {
            $latte->addExtension(new LatteExtension());
        }
    ],
]);

if (!function_exists('picture')) {
    function picture(array $options, array $data = []): void
    {
        $image = new Image($options);
        $image->render($data);
    }
}

if (!function_exists('img')) {
    function img(array $options, array $data = []): void
    {
        $options['tag'] = 'img';

        $image = new Image($options);
        $image->render($data);
    }
}
