<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;
use Livewire\Livewire;
use Noerd\Models\NoerdUser;
use Noerd\Support\FieldRules;
use Noerd\Support\FieldVisibility;
use Noerd\Tests\TestCase;
use Noerd\Traits\NoerdDetail;

uses(TestCase::class, RefreshDatabase::class);

/*
 | validateFromLayout() derives rules from the field TYPE, not only from
 | `required:` — and leaves out what the form does not let the user edit or
 | does not show. Synthetic fixture table and layout.
 */

class ZzRulesRecord extends Model
{
    protected $table = 'zz_rules_records';

    protected $guarded = [];
}

class ZzRulesDetail extends Component
{
    use NoerdDetail;

    public $detailModel = ZzRulesRecord::class;

    public ?string $detailPrimary = 'zzRulesRecordId';

    public bool $showsNote = false;

    public function mount(): void
    {
        $this->initDetail();

        $this->pageLayout = ['fields' => [
            ['name' => 'detailData.name', 'type' => 'text', 'label' => 'Name'],
            ['name' => 'detailData.email', 'type' => 'email', 'label' => 'Email'],
            ['name' => 'detailData.amount', 'type' => 'currency', 'label' => 'Amount'],
            ['name' => 'detailData.due_on', 'type' => 'date', 'label' => 'Due'],
            ['name' => 'detailData.status', 'type' => 'select', 'label' => 'Status', 'options' => [
                ['value' => 'open', 'label' => 'Open'],
                ['value' => 'done', 'label' => 'Done'],
            ]],
            ['name' => 'detailData.code', 'type' => 'text', 'label' => 'Code', 'validation' => ['alpha_num']],
            ['name' => 'detailData.locked_email', 'type' => 'email', 'label' => 'Locked', 'readonly' => true],
            ['type' => 'block', 'theme' => 'display', 'fields' => [
                ['name' => 'detailData.shown_email', 'type' => 'email', 'label' => 'Shown'],
            ]],
            ['name' => 'detailData.hidden_name', 'type' => 'text', 'label' => 'Hidden', 'required' => true, 'show' => false],
            ['name' => 'detailData.note', 'type' => 'text', 'label' => 'Note', 'required' => true, 'showIf' => 'showsNote'],
        ]];
    }

    public function render(): string
    {
        return '<div></div>';
    }

    protected function componentName(): string
    {
        return 'zz-rules-detail';
    }
}

beforeEach(function (): void {
    Schema::create('zz_rules_records', function (Blueprint $table): void {
        $table->id();
        $table->string('name', 10)->nullable();
        $table->string('email')->nullable();
        $table->decimal('amount', 10, 2)->nullable();
        $table->date('due_on')->nullable();
        $table->string('status')->nullable();
        $table->string('code')->nullable();
        $table->string('locked_email')->nullable();
        $table->string('shown_email')->nullable();
        $table->string('hidden_name')->nullable();
        $table->string('note')->nullable();
        $table->timestamps();
    });

    $this->actingAs(NoerdUser::factory()->adminUser()->withSelectedApp('setup')->create());

    Livewire::component('zz-rules-detail', ZzRulesDetail::class);
});

it('rejects values the field type cannot produce', function (): void {
    Livewire::test('zz-rules-detail')
        ->set('detailData.email', 'not-an-email')
        ->set('detailData.amount', 'abc')
        ->set('detailData.due_on', 'tomorrow-ish')
        ->set('detailData.status', 'injected')
        ->set('detailData.code', 'a-b')
        ->call('store')
        ->assertHasErrors([
            'detailData.email',
            'detailData.amount',
            'detailData.due_on',
            'detailData.status',
            'detailData.code',
        ]);
});

it('accepts valid and empty values', function (): void {
    Livewire::test('zz-rules-detail')
        ->set('detailData.name', 'Short')
        ->set('detailData.email', 'a@example.test')
        ->set('detailData.amount', '12.50')
        ->set('detailData.due_on', '2026-11-01')
        ->set('detailData.status', 'done')
        ->set('detailData.code', 'A1')
        ->call('store')
        ->assertHasNoErrors();

    Livewire::test('zz-rules-detail')
        ->set('detailData.email', '')
        ->call('store')
        ->assertHasNoErrors();
});

it('keeps accepting the stored select value that is no option any more', function (): void {
    $record = ZzRulesRecord::query()->create(['status' => 'legacy']);

    Livewire::test('zz-rules-detail', ['modelId' => $record->id])
        ->set('detailData.name', 'Short')
        ->call('store')
        ->assertHasNoErrors();
});

it('does not type-check readonly fields and fields shown as text', function (): void {
    $record = ZzRulesRecord::query()->create(['locked_email' => 'legacy value', 'shown_email' => 'legacy value']);

    Livewire::test('zz-rules-detail', ['modelId' => $record->id])
        ->call('store')
        ->assertHasNoErrors();
});

it('validates a required field only while the form shows it', function (): void {
    Livewire::test('zz-rules-detail')
        ->call('store')
        ->assertHasNoErrors(['detailData.hidden_name', 'detailData.note']);
});

it('limits a text field to the length of its varchar column', function (): void {
    // The column definition as Schema::getColumns() reports it on MySQL (sqlite has no lengths).
    $columns = ['name' => ['type_name' => 'varchar', 'type' => 'varchar(10)']];

    expect(FieldRules::for(['name' => 'detailData.name', 'type' => 'text'], $columns))->toBe(['nullable', 'string', 'max:10'])
        ->and(FieldRules::for(['name' => 'detailData.name', 'type' => 'text', 'required' => true], $columns))->toBe(['required', 'string', 'max:10'])
        ->and(FieldRules::for(['name' => 'detailData.custom_attributes.name', 'type' => 'text'], $columns))->toBe(['nullable']);
});

it('mirrors the showIf condition of the browser', function (): void {
    $component = new ZzRulesDetail();

    expect(FieldVisibility::visibleOn(['showIf' => 'showsNote'], $component))->toBeFalse();

    $component->showsNote = true;
    $component->detailData = ['kind' => 'b', 'count' => 5];

    expect(FieldVisibility::visibleOn(['showIf' => 'showsNote'], $component))->toBeTrue()
        ->and(FieldVisibility::visibleOn(['showIf' => ['field' => 'detailData.kind', 'value' => 'b']], $component))->toBeTrue()
        ->and(FieldVisibility::visibleOn(['showIfNot' => ['field' => 'detailData.kind', 'value' => 'b']], $component))->toBeFalse()
        // x-show compares strictly with a string — a number never matches.
        ->and(FieldVisibility::visibleOn(['showIf' => ['field' => 'detailData.count', 'value' => '5']], $component))->toBeFalse()
        ->and(FieldVisibility::visibleOn(['show' => false], $component))->toBeFalse();
});
