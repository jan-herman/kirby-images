<?php

return function (array|string|null $sizes = null): string|null {
    if (empty($sizes) === true) {
        $sizes = $this->kirby()->option('thumbs.srcsets.default', []);
    }

    if (is_string($sizes) === true) {
        $sizes = $this->kirby()->option('thumbs.srcsets.' . $sizes, []);
    }

    if (is_array($sizes) === false || empty($sizes) === true) {
        return null;
    }

    $quality = $this->kirby()->option('thumbs.qualityWebp', 85);
    foreach ($sizes as $size => $options) {
        $sizes_webp[$size] = array_merge($options, ['format' => 'webp', 'quality' => $quality]);
    }

    return $this->srcset($sizes_webp);
};
