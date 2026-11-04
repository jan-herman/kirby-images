<?php

/** @var JanHerman\Images\Image $image */

use Kirby\Toolkit\Html;

$attributes = $image->attrHelper([
    'decoding' => 'async',
    ...$image->option('imgAttr', []),
    'src' => $image->placeholder() ?: false,
    'data-src' => $image->thumb()->url(),
    'data-srcset' => $image->hasImgSrcset() ? $image->srcset() : null,
    'data-sizes' => 'auto',
    'alt' => $image->alt(),
    'loading' => 'lazy',
    'width' => $image->width(),
    'height' => $image->height(),
    'class' => $image->option('tag') === 'img' ? $image->option('class') : null,
]);

echo Html::tag('img', null, $attributes);
