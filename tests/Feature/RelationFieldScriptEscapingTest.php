<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;
use Livewire\Livewire;
use Noerd\Models\NoerdUser;
use Noerd\Services\RelationFieldRegistry;
use Noerd\Support\RelationFieldDefinition;
use Noerd\Tests\TestCase;
use Noerd\Traits\NoerdDetail;

uses(TestCase::class, RefreshDatabase::class);

/*
 | The relation field renders the hosting record's id, its list component and
 | its picker context into an Alpine @click expression. Each value is encoded
 | as a JavaScript literal, so an id taken from the URL can never become code,
 | and the detail keeps the record's real key once it is loaded.
 */

class ZzEscapingRecord extends Model
{
    protected $table = 'zz_escaping_records';

    protected $guarded = [];
}

class ZzEscapingDetail extends Component
{
    use NoerdDetail;

    public $detailModel = ZzEscapingRecord::class;

    public ?string $detailPrimary = 'zzEscapingRecordId';

    public function render(): string
    {
        return '<div></div>';
    }

    protected function componentName(): string
    {
        return 'zz-escaping-detail';
    }
}

beforeEach(function (): void {
    Schema::create('zz_escaping_records', function (Blueprint $table): void {
        $table->id();
        $table->string('name')->nullable();
        $table->timestamps();
    });

    $this->actingAs(NoerdUser::factory()->adminUser()->withSelectedApp('setup')->create());

    Livewire::component('zz-escaping-detail', ZzEscapingDetail::class);

    app(RelationFieldRegistry::class)->register('zzEscapingRelation', RelationFieldDefinition::model(
        listComponent: "zz-list'-x",
        detailComponent: 'zz-escaping-detail',
        modelClass: null,
        titleResolver: 'name',
    ));
});

it('encodes the hosting id, the list component and the context as JavaScript literals', function (string $theme): void {
    $html = Livewire::test('noerd::noerd-relation-field', [
        'relationType' => 'zzEscapingRelation',
        'fieldName' => "detailData.guard'_id",
        'label' => 'Guard',
        'modelId' => '1,a:alert(1)',
        'theme' => $theme,
    ])->html();

    expect($html)->not->toContain('{id: 1,a:alert(1)')
        ->and($html)->not->toContain("\$modal('zz-list'-x'")
        ->and($html)->not->toContain("context: 'detailData.guard'_id'")
        ->and($html)->toContain('alert(1)');
})->with(['default', 'compact', 'numbered']);

it('keeps the real key of the loaded record instead of the raw id', function (): void {
    $record = ZzEscapingRecord::query()->create(['name' => 'One']);

    Livewire::test('zz-escaping-detail', ['modelId' => (string) $record->id])
        ->assertSet('modelId', $record->id);
});
