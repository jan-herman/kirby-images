<?php

/** @var JanHerman\Images\Image $image */

use Kirby\Toolkit\Html;

$attributes = $image->attrHelper([
    ...$image->option('imgAttr', []),
    'class' => $image->option('tag') === 'img' ? $image->option('class') . ' lazyload' : 'lazyload',
    'data-src' => $image->thumb()->url(),
    'data-srcset' => $image->hasImgSrcset() ? $image->srcset() : null,
    'data-sizes' => 'auto',
    'data-aspectratio' => $image->usesCssCrop() ? round($image->ratio(), 4) : null,
    'data-parent-fit' => $image->option('fit') ?? 'cover',
    'alt' => $image->alt(),
    'width' => $image->width(),
    'height' => $image->height(),
]);

echo Html::tag('img', null, $attributes);
