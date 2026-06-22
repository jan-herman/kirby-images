<?php

/** @var JanHerman\Images\Image $image */
/** @var string $format */
/** @var string $srcset */

use Kirby\Toolkit\Html;

$attributes = $image->attrHelper([
    ...$image->option('sourceAttr', []),
    'srcset' => $srcset,
    'sizes' => $image->sizesWithAuto(),
    'type' => $image->mimeType($format),
]);

echo Html::tag('source', null, $attributes);
