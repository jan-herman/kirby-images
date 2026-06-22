<?php

/** @var JanHerman\Images\Image $image */
/** @var string $format */
/** @var string $srcset */

use Kirby\Toolkit\Html;

$attributes = [
    ...$image->option('sourceAttr', []),
    'data-srcset' => $srcset,
    'data-sizes' => 'auto',
    'type' => $image->mimeType($format),
];

echo Html::tag('source', null, $attributes);
