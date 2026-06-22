<?php

/** @var JanHerman\Images\Image $image */

use Kirby\Toolkit\Html;

$thumb = $image->thumb();
$srcset = $image->hasImgSrcset() ? $image->srcset() : null;

$attributes = $image->attrHelper([
    ...$image->option('imgAttr', []),
    'src' => $thumb->url(),
    'srcset' => $srcset,
    'sizes' => $srcset ? $image->sizesWithAuto() : null,
    'loading' => $image->option('loading'),
    'fetchpriority' => $image->option('loading') === 'eager' ? 'high' : null,
    'alt' => $image->alt(),
    'width' => $thumb->width(),
    'height' => $thumb->height(),
    'class' => $image->option('tag') === 'img' ? $image->option('class') : null,
]);

echo Html::tag('img', null, $attributes);
