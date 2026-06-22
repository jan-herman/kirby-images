<?php

/** @var JanHerman\Images\Image $image */

use Kirby\Toolkit\Html;

$thumb = $image->thumb();

$attributes = $image->attrHelper([
    ...$image->option('imgAttr', []),
    'src' => $image->placeholder() ?: false,
    'data-src' => $thumb->url(),
    'data-srcset' => $image->hasImgSrcset() ? $image->srcset() : null,
    'data-sizes' => 'auto',
    'alt' => $image->alt(),
    'loading' => 'lazy',
    'width' => $thumb->width(),
    'height' => $thumb->height(),
    'class' => $image->option('tag') === 'img' ? $image->option('class') : null,
]);

echo Html::tag('img', null, $attributes);
