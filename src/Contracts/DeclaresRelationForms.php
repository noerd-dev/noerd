<?php

declare(strict_types=1);

namespace Noerd\Contracts;

/**
 * A model that declares "relation forms": detail YAML fields bound into a
 * RELATED model (e.g. `detailData.invoiceAddress.address_line_1`). Declared
 * forms are hydrated into $detailData on load and persisted after every save
 * by the framework — detail components need no code for them.
 */
interface DeclaresRelationForms
{
    /**
     * @return array<string, \Noerd\Support\RelationFormDefinition> form key => definition
     */
    public static function relationForms(): array;
}
