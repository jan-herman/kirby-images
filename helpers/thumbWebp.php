<?php

use Kirby\Cms\File;
use Kirby\Cms\FileVersion;
use Kirby\Filesystem\Asset;

return function (array|string|null $options): FileVersion|File|Asset {
    if (empty($options) === true) {
        $options = $this->kirby()->option('thumbs.presets.default');
    } elseif (is_string($options) === true) {
        $options = $this->kirby()->option('thumbs.presets.' . $options);
    }

    if (empty($options) === true || is_array($options) === false) {
        return $this;
    }

    $quality = $this->kirby()->option('thumbs.qualityWebp', 85);
    $options_webp = array_merge($options, ['format' => 'webp', 'quality' => $quality]);

    return $this->thumb($options_webp);
};
