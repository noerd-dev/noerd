<?php

declare(strict_types=1);

namespace Noerd\Support;

use Illuminate\Support\Str;

/**
 * The one home of the naming convention between a list and its record:
 * `{namespace}::{folder}.{entities}-list` pairs with `{entity}-detail`, emits
 * `{entity}Selected` and deep-links `?{entity}Id`. Lists, relation fields and
 * the picklist badges all derive their names here, so the list that dispatches
 * a selection and the field that waits for it can never disagree.
 *
 * It is the fallback only: a list or relation definition that declares its
 * detail, event or entity explicitly never reaches it.
 */
final class ComponentNaming
{
    /**
     * The component name without Livewire namespace and dotted subfolder:
     * `booking-members::stamp-cards.customer-stamp-cards-list` → `customer-stamp-cards-list`.
     */
    public static function bareName(string $component): string
    {
        return Str::afterLast(Str::afterLast($component, '::'), '.');
    }

    /**
     * The singular entity of a list component (`push-distribution-lists-list` →
     * `push-distribution-list`), or null when the component is no list. Only the
     * LAST `-list` is the suffix — an entity may itself end in "list".
     */
    public static function listEntity(string $listComponent): ?string
    {
        $name = self::bareName($listComponent);

        if (! Str::endsWith($name, '-list')) {
            return null;
        }

        return Str::singular(Str::beforeLast($name, '-list'));
    }

    /**
     * The `{entity}Selected` event a list dispatches for a picked row.
     */
    public static function selectEvent(string $listComponent): ?string
    {
        $entity = self::listEntity($listComponent);

        return $entity === null ? null : Str::camel($entity) . 'Selected';
    }

    /**
     * The `?{entity}Id` query parameter that deep-links a record of the list.
     */
    public static function deepLinkParam(string $listComponent): ?string
    {
        $entity = self::listEntity($listComponent);

        return $entity === null ? null : Str::camel($entity) . 'Id';
    }

    /**
     * The detail paired with a list by name, namespace and subfolder kept:
     * `crm::accounts-list` → `crm::account-detail`.
     */
    public static function pairedDetail(string $listComponent): ?string
    {
        $entity = self::listEntity($listComponent);

        if ($entity === null) {
            return null;
        }

        $prefix = Str::beforeLast($listComponent, self::bareName($listComponent));

        return $prefix . $entity . '-detail';
    }
}
