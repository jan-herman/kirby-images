<?php

use JanHerman\Images\Picture;

// bugfix
if ($file === __FILE__) {
    $file = null;
}

// $image
if (isset($image)) {
    if ($image instanceof Kirby\Content\Field && $image->isNotEmpty()) { // field
        $field = $image;
    } elseif ($image instanceof Kirby\Cms\File) { // file
        $file = $image;
    } elseif (is_string($image)) { // asset path
        $path = $image;
        // process path aliases if barista is loaded
        if (function_exists('barista')) {
            $barista = barista();
            $path = $barista->getEngine()->getLoader()->getReferredName($path, __FILE__);
            $base_path = kirby()->root('index') . '/';
            if (str_starts_with($path, $base_path)) {
                $path = substr($path, strlen($base_path));
            }
        }
        $asset = asset($path);
    }
}

if (!isset($field) && !isset($file) && !isset($asset)) {
    return;
}

// crop - deprecated (will be removed in v2.0)
if (isset($crop) && !isset($object_fit)) {
    $object_fit = $crop ? 'cover' : 'contain';
}

$picture = new Picture(
    field: $field ?? null,
    file: $file ?? null,
    asset: $asset ?? null,
    thumb: $thumb ?? null,
    srcset: $srcset ?? 'default',
    src: $src ?? '',
    alt: $alt ?? '',
    ratio: $ratio ?? null,
    object_fit: $object_fit ?? 'cover',
    as: $as ?? 'picture',
    lazy: $lazy ?? false,
    sizes: $sizes ?? null,
    container: $container ?? null,
    focus: $focus ?? null,
    attr: $attr ?? [],
    img_attr: $img_attr ?? [],
    class: $class ?? ''
);

$picture->render();
