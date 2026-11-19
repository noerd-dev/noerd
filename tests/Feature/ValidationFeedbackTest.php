<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;
use Livewire\Livewire;
use Noerd\Attributes\ReportsSaveOutcome;
use Noerd\Livewire\Hooks\ValidationFeedbackHook;
use Noerd\Models\NoerdUser;
use Noerd\Support\LayoutFields;
use Noerd\Tests\TestCase;
use Noerd\Traits\NoerdDetail;

uses(TestCase::class, RefreshDatabase::class);

/*
 | A refused save is never silent: the saving component tells the page which
 | tabs hold a field with an error, and the message names the field by its
 | label. Synthetic fixture table and layout.
 */

class ZzFeedbackRecord extends Model
{
    protected $table = 'zz_feedback_records';

    protected $guarded = [];
}

class ZzFeedbackDetail extends Component
{
    use NoerdDetail;

    public static bool $denySave = false;

    public $detailModel = ZzFeedbackRecord::class;

    public ?string $detailPrimary = 'zzFeedbackRecordId';

    public function mount(): void
    {
        $this->initDetail();

        $this->pageLayout = ['fields' => [
            ['name' => 'detailData.name', 'type' => 'text', 'label' => 'Zz Company Name', 'required' => true],
            ['type' => 'block', 'tab' => 2, 'fields' => [
                ['name' => 'detailData.code', 'type' => 'text', 'label' => 'Code', 'required' => true],
            ]],
            ['name' => 'detailData.note', 'type' => 'text', 'label' => 'Note', 'tab' => 3],
        ]];
    }

    public function refuseUnknownField(): void
    {
        $this->addError('somethingElse', 'Refused.');
    }

    public function save(): void
    {
        $this->addError('somethingElse', 'Refused.');
    }

    public function flagNote(): void
    {
        $this->addError('detailData.note', 'Live error.');
    }

    #[ReportsSaveOutcome]
    public function confirm(): void
    {
        if (($this->detailData['note'] ?? null) === 'refuse') {
            $this->addError('detailData.note', 'Refused.');
        }
    }

    public function canSaveObject(): bool
    {
        return ! self::$denySave;
    }

    public function render(): string
    {
        return '<div></div>';
    }

    protected function componentName(): string
    {
        return 'zz-feedback-detail';
    }
}

beforeEach(function (): void {
    Schema::create('zz_feedback_records', function (Blueprint $table): void {
        $table->id();
        $table->string('name')->nullable();
        $table->string('code')->nullable();
        $table->string('note')->nullable();
        $table->timestamps();
    });

    Livewire::component('zz-feedback-detail', ZzFeedbackDetail::class);
    ZzFeedbackDetail::$denySave = false;

    $this->actingAs(NoerdUser::factory()->adminUser()->create());
});

it('maps field names to the tabs that hold them', function (): void {
    $fields = [
        ['name' => 'detailData.name'],
        ['type' => 'block', 'tab' => 2, 'fields' => [
            ['name' => 'detailData.code', 'tab' => 9],
            ['type' => 'block', 'fields' => [['name' => 'detailData.positions']]],
        ]],
        ['name' => 'detailData.note', 'tab' => 3],
    ];

    expect(LayoutFields::tabsOf($fields, ['detailData.note', 'detailData.name']))->toBe([1, 3])
        ->and(LayoutFields::tabsOf($fields, ['detailData.code']))->toBe([2])
        ->and(LayoutFields::tabsOf($fields, ['detailData.positions.0.amount']))->toBe([2])
        ->and(LayoutFields::tabsOf($fields, ['detailData.names', 'unknown']))->toBe([]);
});

it('reports the tabs of the failing fields when a save is refused', function (): void {
    Livewire::test('zz-feedback-detail')
        ->set('detailData.note', 'only a note')
        ->call('store')
        ->assertHasErrors(['detailData.name', 'detailData.code'])
        ->assertDispatched(ValidationFeedbackHook::FAILED_EVENT, tabs: [1, 2])
        ->assertNotDispatched(ValidationFeedbackHook::PASSED_EVENT);

    expect(ZzFeedbackRecord::count())->toBe(0);
});

it('reports a refused save of a hosting page the same way', function (): void {
    Livewire::test('zz-feedback-detail')
        ->set('detailData.name', 'Acme')
        ->dispatch('storeDetail-zz-feedback-detail')
        ->assertHasErrors(['detailData.code'])
        ->assertDispatched(ValidationFeedbackHook::FAILED_EVENT, tabs: [2]);
});

it('reports a passed save so the page drops its marks', function (): void {
    Livewire::test('zz-feedback-detail')
        ->set('detailData.name', 'Acme')
        ->set('detailData.code', 'A1')
        ->call('store')
        ->assertHasNoErrors()
        ->assertDispatched(ValidationFeedbackHook::PASSED_EVENT)
        ->assertNotDispatched(ValidationFeedbackHook::FAILED_EVENT);

    expect(ZzFeedbackRecord::count())->toBe(1);
});

it('reports a refused save whose error the layout does not declare without a tab', function (): void {
    Livewire::test('zz-feedback-detail')
        ->call('save')
        ->assertDispatched(ValidationFeedbackHook::FAILED_EVENT, tabs: []);
});

it('reports the outcome of a method marked as a save', function (): void {
    Livewire::test('zz-feedback-detail')
        ->set('detailData.note', 'refuse')
        ->call('confirm')
        ->assertDispatched(ValidationFeedbackHook::FAILED_EVENT, tabs: [3]);

    Livewire::test('zz-feedback-detail')
        ->call('confirm')
        ->assertDispatched(ValidationFeedbackHook::PASSED_EVENT);
});

it('judges a save by its own outcome, not by an error left from before', function (): void {
    Livewire::test('zz-feedback-detail')
        ->call('flagNote')
        ->assertHasErrors(['detailData.note'])
        ->call('confirm')
        ->assertHasNoErrors()
        ->assertDispatched(ValidationFeedbackHook::PASSED_EVENT)
        ->assertNotDispatched(ValidationFeedbackHook::FAILED_EVENT);
});

it('reports nothing for a save the user may not run', function (): void {
    ZzFeedbackDetail::$denySave = true;

    Livewire::test('zz-feedback-detail')
        ->call('flagNote')
        ->call('store')
        ->assertHasErrors(['detailData.note'])
        ->assertNotDispatched(ValidationFeedbackHook::PASSED_EVENT)
        ->assertNotDispatched(ValidationFeedbackHook::FAILED_EVENT);

    expect(ZzFeedbackRecord::count())->toBe(0);
});

it('stays silent for an action that is no save', function (): void {
    Livewire::test('zz-feedback-detail')
        ->call('refuseUnknownField')
        ->assertHasErrors(['somethingElse'])
        ->assertNotDispatched(ValidationFeedbackHook::FAILED_EVENT)
        ->assertNotDispatched(ValidationFeedbackHook::PASSED_EVENT);
});

it('names a field by its label in the validation message', function (): void {
    $component = Livewire::test('zz-feedback-detail')->call('store');

    $message = $component->errors()->first('detailData.name');

    expect($message)->toContain('Zz Company Name')
        ->and(mb_strtolower($message))->not->toContain('detail data');
});
