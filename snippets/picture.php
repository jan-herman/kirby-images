<?php

/** @var JanHerman\Images\Image $image */
/** @var array $data */

use Kirby\Toolkit\Html;

$baseClass = option('jan-herman.images.baseClass', 'image');

$classes = array_filter([
    $baseClass,
    $image->hasAlphaChannel() ? $baseClass . '--has-alpha' : null,
    $image->option('class'),
]);

$styles = [
    '--object-fit' => $image->option('fit'),
    '--object-position' => $image->usesCssCrop() ? $image->objectPositionString() : null,
    '--aspect-ratio' => round($image->intendedRatio(), 4),
    '--placeholder' => $image->usesPlaceholder() ? 'url(\'' . $image->placeholder() . '\')' : null,
];

$attributes = $image->attrHelper([
    ...$image->option('attr', []),
    'class' => implode(' ', $classes),
    'style' => $image->styleHelper($styles),
]);

$childTags = [
    $image->renderSourcesToString($data),
    $image->renderImgToString($data),
];

echo Html::tag('picture', $childTags, $attributes);
