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

    /**
     * The list a record component belongs to — the one refreshed when its
     * modal closes — with namespace and subfolder kept: `crm::account-detail`
     * → `crm::accounts-list`, `booking::bookings.booking-detail` →
     * `booking::bookings.bookings-list`, `account-page` → `accounts-list`. A
     * list is its own answer; only the LAST `-detail`/`-page` is the suffix.
     */
    public static function pairedList(string $component): string
    {
        $name = self::bareName($component);

        if (Str::endsWith($name, '-list')) {
            return $component;
        }

        $entity = match (true) {
            Str::endsWith($name, '-page') => Str::beforeLast($name, '-page'),
            Str::endsWith($name, '-detail') => Str::beforeLast($name, '-detail'),
            default => $name,
        };

        return Str::beforeLast($component, $name) . Str::plural($entity) . '-list';
    }

    /**
     * The detail component (and YAML name) of a model class:
     * `Customer::class` → `customer-detail`.
     *
     * @param  class-string  $modelClass
     */
    public static function detailForModel(string $modelClass): string
    {
        return Str::kebab(class_basename($modelClass)) . '-detail';
    }

    /**
     * The component name without its Livewire namespace, subfolder kept:
     * `booking::bookings.booking-detail` → `bookings.booking-detail`.
     */
    public static function withoutNamespace(string $component): string
    {
        return str_contains($component, '::') ? explode('::', $component, 2)[1] : $component;
    }

    /**
     * The app-config folder a namespaced component belongs to
     * (`communication::communications-list` → `communication`), or null for an
     * unnamespaced component. Rendering a namespaced component is an explicit
     * reference to its module, so its own configs stay resolvable even when the
     * tenant has not been granted that app.
     */
    public static function ownerApp(string $component): ?string
    {
        if (! str_contains($component, '::')) {
            return null;
        }

        return mb_strtolower(explode('::', $component, 2)[0]);
    }

    /**
     * The file path of a detail/page/settings YAML relative to its folder:
     * dots map to directories (`booking::bookings.booking-detail` →
     * `bookings/booking-detail`). DETAILS ONLY — list YAMLs resolve flat by
     * bareName().
     */
    public static function detailSubPath(string $component): string
    {
        return str_replace('.', DIRECTORY_SEPARATOR, self::withoutNamespace($component));
    }
}
