<?php

namespace JanHerman\Images\Latte;

use Latte\Extension;

class LatteExtension extends Extension
{
    public function getTags(): array
    {
        return [
            'image' => [Nodes\ImageNode::class, 'create']
        ];
    }
}
