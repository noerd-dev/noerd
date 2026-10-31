<?php

declare(strict_types=1);

use Noerd\Models\NoerdUser;
use Noerd\Support\ComponentNaming;
use Noerd\Support\ListColumnIntrospection;
use Noerd\Tests\TestCase;

uses(TestCase::class);

it('pairs a record component with its list, namespace and subfolder kept', function (string $component, string $list): void {
    expect(ComponentNaming::pairedList($component))->toBe($list);
})->with([
    'detail' => ['customer-detail', 'customers-list'],
    'page' => ['account-page', 'accounts-list'],
    'namespaced detail' => ['crm::account-detail', 'crm::accounts-list'],
    'dotted detail' => ['booking::bookings.booking-detail', 'booking::bookings.bookings-list'],
    'a list is its own list' => ['crm::accounts-list', 'crm::accounts-list'],
    // Only the LAST suffix is cut — an entity may itself contain "-detail".
    'entity containing -detail' => ['order-detail-line-detail', 'order-detail-lines-list'],
    'no suffix at all' => ['dashboard', 'dashboards-list'],
]);

it('derives the detail component of a model class', function (): void {
    expect(ComponentNaming::detailForModel(NoerdUser::class))->toBe('noerd-user-detail');
});

it('splits a component name into namespace, owner app and YAML path', function (): void {
    expect(ComponentNaming::withoutNamespace('booking::bookings.booking-detail'))->toBe('bookings.booking-detail')
        ->and(ComponentNaming::withoutNamespace('booking-detail'))->toBe('booking-detail')
        ->and(ComponentNaming::ownerApp('Communication::communications-list'))->toBe('communication')
        ->and(ComponentNaming::ownerApp('communications-list'))->toBeNull()
        ->and(ComponentNaming::detailSubPath('booking::bookings.booking-detail'))->toBe('bookings' . DIRECTORY_SEPARATOR . 'booking-detail')
        ->and(ComponentNaming::bareName('booking-members::stamp-cards.customer-stamp-cards-list'))->toBe('customer-stamp-cards-list');
});

it('tells a dotted (nested) column field from a real column', function (): void {
    expect(ListColumnIntrospection::isDottedField('custom_attributes.sap_number'))->toBeTrue()
        ->and(ListColumnIntrospection::isDottedField('customer.name'))->toBeTrue()
        ->and(ListColumnIntrospection::isDottedField('name'))->toBeFalse();
});
