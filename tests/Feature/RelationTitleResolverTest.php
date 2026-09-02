<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Noerd\Models\NoerdUser;
use Noerd\Services\RelationFieldRegistry;
use Noerd\Services\RelationTitleResolver;
use Noerd\Support\RelationFieldDefinition;
use Noerd\Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

class RelationTitleFixtureWidget extends Model
{
    protected $table = 'fixture_widgets';

    protected $guarded = [];
}

beforeEach(function (): void {
    $this->actingAs(NoerdUser::factory()->withExampleTenant()->create());

    foreach (['fixture_widgets', 'gadgets'] as $table) {
        Schema::create($table, function (Blueprint $blueprint): void {
            $blueprint->id();
            $blueprint->string('name')->nullable();
            $blueprint->timestamps();
        });
    }

    $this->resolver = app(RelationTitleResolver::class);
});

afterEach(function (): void {
    Schema::dropIfExists('fixture_widgets');
    Schema::dropIfExists('gadgets');
});

it('resolves the title through a registered relation type', function (): void {
    $widget = RelationTitleFixtureWidget::create(['name' => 'Erika Musterfrau']);

    app(RelationFieldRegistry::class)->register('fixtureWidgetRelation', RelationFieldDefinition::model(
        'fixture-widgets-list',
        null,
        RelationTitleFixtureWidget::class,
    ));

    expect($this->resolver->title('fixture_widget_id', $widget->id))->toBe('Erika Musterfrau');
});

it('resolves the title by naming convention when no relation type is registered', function (): void {
    $gadgetId = DB::table('gadgets')->insertGetId(['name' => 'Pizza Salami']);

    expect($this->resolver->title('gadget_id', $gadgetId))->toBe('Pizza Salami');
});

it('falls back to the id when the name is empty or the row is missing', function (): void {
    $gadgetId = DB::table('gadgets')->insertGetId(['name' => '']);

    expect($this->resolver->title('gadget_id', $gadgetId))->toBe((string) $gadgetId)
        ->and($this->resolver->title('gadget_id', 999999))->toBe('999999');
});

it('falls back to the id when no matching table exists', function (): void {
    expect($this->resolver->title('gizmo_id', 42))->toBe('42');
});

it('returns null for empty values and non-foreign-key columns', function (): void {
    expect($this->resolver->title('gadget_id', null))->toBeNull()
        ->and($this->resolver->title('gadget_id', ''))->toBeNull()
        ->and($this->resolver->title('name', 5))->toBeNull();
});

it('prefers a registered relation type over the table convention', function (): void {
    $widget = RelationTitleFixtureWidget::create(['name' => 'Erika Musterfrau']);

    app(RelationFieldRegistry::class)->register('fixtureWidgetRelation', RelationFieldDefinition::model(
        'fixture-widgets-list',
        null,
        RelationTitleFixtureWidget::class,
        titleResolver: fn(mixed $model): string => 'WIDGET:' . $model->name,
    ));

    expect(app(RelationTitleResolver::class)->title('fixture_widget_id', $widget->id))->toBe('WIDGET:Erika Musterfrau');
});

it('primes a whole page of convention-table ids with a single query', function (): void {
    $ids = [];
    foreach (['Alpha', 'Beta', 'Gamma'] as $name) {
        $ids[] = DB::table('gadgets')->insertGetId(['name' => $name]);
    }

    $this->resolver->prime('gadget_id', $ids);

    DB::enableQueryLog();
    expect($this->resolver->title('gadget_id', $ids[0]))->toBe('Alpha')
        ->and($this->resolver->title('gadget_id', $ids[1]))->toBe('Beta')
        ->and($this->resolver->title('gadget_id', $ids[2]))->toBe('Gamma')
        ->and(DB::getQueryLog())->toBeEmpty();
    DB::disableQueryLog();
});

it('primes through a registered relation type and falls back per id', function (): void {
    $widget = RelationTitleFixtureWidget::create(['name' => 'Erika Musterfrau']);

    app(RelationFieldRegistry::class)->register('fixtureWidgetRelation', RelationFieldDefinition::model(
        'fixture-widgets-list',
        null,
        RelationTitleFixtureWidget::class,
    ));

    $this->resolver->prime('fixture_widget_id', [$widget->id, 999999, null, '']);

    expect($this->resolver->title('fixture_widget_id', $widget->id))->toBe('Erika Musterfrau')
        ->and($this->resolver->title('fixture_widget_id', 999999))->toBe('999999');
});
