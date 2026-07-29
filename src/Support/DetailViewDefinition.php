<?php

namespace Noerd\Support;

final class DetailViewDefinition
{
    public function __construct(
        public string $name,
        public string $gridClasses,
        public bool $fullWidthRows = false,
        public bool $numbersRows = false,
        public string $spacerClass = 'h-16',
    ) {}
}
