<?php

namespace JanHerman\Images\Latte\Nodes;

use Latte\CompileException;
use Latte\Compiler\Nodes\StatementNode;
use Latte\Compiler\Nodes\Php\Expression\ArrayNode;
use Latte\Compiler\Nodes\Php\IdentifierNode;
use Latte\Compiler\PrintContext;
use Latte\Compiler\Tag;

class ImageNode extends StatementNode
{
    public ArrayNode $args;

    public static function create(Tag $tag): self
    {
        if ($tag->isNAttribute()) {
            throw new CompileException('Attribute n:image is not supported.', $tag->position);
        }

        $tag->expectArguments();
        $node = new static();
        $args = $tag->parser->parseArguments();

        foreach ($args as $item) {
            if ($item->key === null) {
                $item->key = new IdentifierNode('field');
            }
        }

        $node->args = $args;

        return $node;
    }

    public function print(PrintContext $context): string
    {
        return $context->format(
            'snippet(\'image\', %node) %line;',
            $this->args,
            $this->position
        );
    }

    public function &getIterator(): \Generator
    {
        false && yield;
    }
}
