<?php

/** @var JanHerman\Images\Image $image */
/** @var string $format */
/** @var string $srcset */

use Kirby\Toolkit\Html;

$attributes = [
    ...$image->option('sourceAttr', []),
    'data-srcset' => $srcset,
    'data-sizes' => 'auto',
    'data-aspectratio' => $image->usesCssCrop() ? round($image->ratio(), 4) : null,
    'data-parent-fit' => $image->option('fit') ?? 'cover',
    'type' => $image->mimeType($format),
];

echo Html::tag('source', null, $attributes);
