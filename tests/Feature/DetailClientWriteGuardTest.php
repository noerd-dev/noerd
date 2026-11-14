<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;
use Livewire\Livewire;
use Noerd\Models\NoerdUser;
use Noerd\Support\ClientWritablePaths;
use Noerd\Tests\TestCase;
use Noerd\Traits\NoerdDetail;

uses(TestCase::class, RefreshDatabase::class);

/*
 | The client may only change what the form lets the user edit: a
 | `readonly: true` field and the unbound keys below a nested root
 | (custom_attributes) are protected on the update channel, a component
 | without any declared field persists nothing. Proven against a synthetic
 | fixture table and layout — never against a shipped YAML.
 */

class ZzGuardRecord extends Model
{
    protected $table = 'zz_guard_records';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['custom_attributes' => 'array'];
    }
}

class ZzClientGuardDetail extends Component
{
    use NoerdDetail;

    public $detailModel = ZzGuardRecord::class;

    public ?string $detailPrimary = 'zzGuardRecordId';

    /** A server-side write to a readonly field, e.g. a generated number. */
    public function assignCode(): void
    {
        $this->detailData['code'] = 'SERVER-1';
    }

    public function render(): string
    {
        return '<div></div>';
    }

    protected function componentName(): string
    {
        return 'zz-client-guard-detail';
    }
}

class ZzClientGuardHandBuiltDetail extends ZzClientGuardDetail
{
    protected function additionalDetailFields(): array
    {
        return ['detailData.custom_attributes.handmade'];
    }

    protected function componentName(): string
    {
        return 'zz-client-guard-detail';
    }
}

/**
 * A layout without any readonly field and without a nested root, and a
 * hand-written store() that mass-assigns the whole form like many module
 * details do — the update channel alone has to keep undeclared keys out.
 */
class ZzClientGuardPlainDetail extends ZzClientGuardDetail
{
    public function store(): void
    {
        ZzGuardRecord::query()->updateOrCreate(['id' => $this->modelId], $this->detailData);
    }

    protected function componentName(): string
    {
        return 'zz-client-guard-plain-detail';
    }
}

class ZzClientGuardPage extends Component
{
    use NoerdDetail;

    public $detailModel = ZzGuardRecord::class;

    public function render(): string
    {
        return '<div></div>';
    }

    protected function componentName(): string
    {
        return 'zz-client-guard-page';
    }
}

class ZzClientGuardDeclaredPage extends ZzClientGuardPage
{
    protected function additionalDetailFields(): array
    {
        return ['detailData.name'];
    }
}

beforeEach(function (): void {
    Schema::create('zz_guard_records', function (Blueprint $table): void {
        $table->id();
        $table->string('name')->nullable();
        $table->string('code')->nullable();
        $table->string('notes')->nullable();
        $table->json('custom_attributes')->nullable();
        $table->timestamps();
    });

    $this->actingAs(NoerdUser::factory()->adminUser()->withSelectedApp('setup')->create());

    Livewire::component('zz-client-guard-detail', ZzClientGuardDetail::class);
    Livewire::component('zz-client-guard-hand-built-detail', ZzClientGuardHandBuiltDetail::class);
    Livewire::component('zz-client-guard-page', ZzClientGuardPage::class);
    Livewire::component('zz-client-guard-plain-detail', ZzClientGuardPlainDetail::class);

    File::put(base_path('app-configs/setup/details/zz-client-guard-plain-detail.yml'), implode("\n", [
        'title: Zz Client Guard Plain',
        'fields:',
        '  - name: detailData.name',
        '    label: Name',
        '    type: text',
    ]));
    Livewire::component('zz-client-guard-declared-page', ZzClientGuardDeclaredPage::class);

    File::put(base_path('app-configs/setup/details/zz-client-guard-detail.yml'), implode("\n", [
        'title: Zz Client Guard',
        'fields:',
        '  - name: detailData.name',
        '    label: Name',
        '    type: text',
        '  - name: detailData.code',
        '    label: Code',
        '    type: text',
        '    readonly: true',
        '  - name: detailData.custom_attributes.sap',
        '    label: SAP',
        '    type: text',
        '  - name: detailData.custom_attributes.locked',
        '    label: Locked',
        '    type: text',
        '    readonly: true',
    ]));
});

afterEach(function (): void {
    File::delete(base_path('app-configs/setup/details/zz-client-guard-detail.yml'));
    File::delete(base_path('app-configs/setup/details/zz-client-guard-plain-detail.yml'));
});

function zzGuardRecord(): ZzGuardRecord
{
    return ZzGuardRecord::query()->create([
        'name' => 'Original',
        'code' => 'C-1',
        'notes' => 'internal',
        'custom_attributes' => ['sap' => 'S-1', 'locked' => 'L-1', 'internal' => 'keep'],
    ]);
}

describe('readonly fields', function (): void {
    it('discards a client update of a readonly field', function (): void {
        $record = zzGuardRecord();

        Livewire::test('zz-client-guard-detail', ['modelId' => $record->id])
            ->set('detailData.code', 'INJECTED')
            ->assertSet('detailData.code', 'C-1')
            ->set('detailData.name', 'Renamed')
            ->call('store')
            ->assertHasNoErrors();

        expect($record->refresh()->code)->toBe('C-1')
            ->and($record->name)->toBe('Renamed');
    });

    it('discards readonly values smuggled in by replacing the whole form', function (): void {
        $record = zzGuardRecord();

        $component = Livewire::test('zz-client-guard-detail', ['modelId' => $record->id]);

        $component->set('detailData', [...$component->get('detailData'), 'code' => 'INJECTED', 'name' => 'Renamed'])
            ->call('store');

        expect($record->refresh()->code)->toBe('C-1')
            ->and($record->name)->toBe('Renamed');
    });

    it('ignores a client-dispatched setFieldValue on a readonly field', function (): void {
        $record = zzGuardRecord();

        Livewire::test('zz-client-guard-detail', ['modelId' => $record->id])
            ->dispatch('setFieldValue', field: 'detailData.code', value: 'INJECTED')
            ->assertSet('detailData.code', 'C-1');
    });

    it('keeps a value the component itself writes into a readonly field', function (): void {
        $record = zzGuardRecord();

        Livewire::test('zz-client-guard-detail', ['modelId' => $record->id])
            ->call('assignCode')
            ->call('store');

        expect($record->refresh()->code)->toBe('SERVER-1');
    });
});

describe('nested roots', function (): void {
    it('accepts a bound nested key and keeps the unbound ones of the record', function (): void {
        $record = zzGuardRecord();

        Livewire::test('zz-client-guard-detail', ['modelId' => $record->id])
            ->set('detailData.custom_attributes.sap', 'S-2')
            ->call('store');

        expect($record->refresh()->custom_attributes)
            ->toBe(['sap' => 'S-2', 'locked' => 'L-1', 'internal' => 'keep']);
    });

    it('discards client writes to unbound or readonly keys below the root', function (): void {
        $record = zzGuardRecord();

        Livewire::test('zz-client-guard-detail', ['modelId' => $record->id])
            ->set('detailData.custom_attributes.internal', 'INJECTED')
            ->set('detailData.custom_attributes.added', 'INJECTED')
            ->set('detailData.custom_attributes.locked', 'INJECTED')
            ->call('store');

        expect($record->refresh()->custom_attributes)
            ->toBe(['sap' => 'S-1', 'locked' => 'L-1', 'internal' => 'keep']);
    });

    it('takes only the bound keys from a replaced root', function (): void {
        $record = zzGuardRecord();

        Livewire::test('zz-client-guard-detail', ['modelId' => $record->id])
            ->set('detailData.custom_attributes', ['sap' => 'S-2', 'added' => 'INJECTED'])
            ->call('store');

        expect($record->refresh()->custom_attributes)
            ->toBe(['sap' => 'S-2', 'locked' => 'L-1', 'internal' => 'keep']);
    });

    it('opens a nested key a hand-built input declares', function (): void {
        $record = zzGuardRecord();

        Livewire::test('zz-client-guard-hand-built-detail', ['modelId' => $record->id])
            ->set('detailData.custom_attributes.handmade', 'H-1')
            ->call('store');

        expect($record->refresh()->custom_attributes['handmade'] ?? null)->toBe('H-1');
    });
});

describe('undeclared keys', function (): void {
    it('discards a client update of a key no field declares, also without readonly fields or nested roots', function (): void {
        $record = zzGuardRecord();

        Livewire::test('zz-client-guard-plain-detail', ['modelId' => $record->id])
            ->set('detailData.notes', 'INJECTED')
            ->set('detailData.code', 'INJECTED')
            ->set('detailData.id', 999)
            ->assertSet('detailData.notes', 'internal')
            ->assertSet('detailData.id', $record->id)
            ->set('detailData.name', 'Renamed')
            ->call('store');

        expect($record->refresh()->notes)->toBe('internal')
            ->and($record->code)->toBe('C-1')
            ->and($record->name)->toBe('Renamed')
            ->and(ZzGuardRecord::query()->count())->toBe(1);
    });

    it('drops a key the client adds that the record never had', function (): void {
        $record = zzGuardRecord();

        Livewire::test('zz-client-guard-plain-detail', ['modelId' => $record->id])
            ->set('detailData.tenant_id', 2)
            ->assertSet('detailData.tenant_id', null);
    });

    it('keeps only the declared keys of a replaced form', function (): void {
        $record = zzGuardRecord();

        $component = Livewire::test('zz-client-guard-plain-detail', ['modelId' => $record->id]);

        $component->set('detailData', [...$component->get('detailData'), 'notes' => 'INJECTED', 'added' => 'x', 'name' => 'Renamed'])
            ->call('store');

        expect($record->refresh()->notes)->toBe('internal')
            ->and($record->name)->toBe('Renamed')
            ->and($component->get('detailData'))->not->toHaveKey('added');
    });

    it('ignores a client-dispatched setFieldValue on an undeclared key', function (): void {
        $record = zzGuardRecord();

        Livewire::test('zz-client-guard-plain-detail', ['modelId' => $record->id])
            ->dispatch('setFieldValue', field: 'detailData.notes', value: 'INJECTED')
            ->assertSet('detailData.notes', 'internal')
            ->dispatch('setFieldValue', field: 'detailData.name', value: 'Renamed')
            ->assertSet('detailData.name', 'Renamed');
    });
});

describe('the record of the form', function (): void {
    it('puts back a client update repointing modelId, so store() never writes one record\'s form onto another', function (): void {
        $first = zzGuardRecord();
        $second = ZzGuardRecord::query()->create(['name' => 'Second', 'code' => 'C-2']);

        Livewire::test('zz-client-guard-detail', ['modelId' => $first->id])
            ->set('modelId', $second->id)
            ->assertSet('modelId', $first->id)
            ->set('detailData.name', 'Renamed')
            ->call('store');

        expect($second->refresh()->name)->toBe('Second')
            ->and($second->code)->toBe('C-2')
            ->and($first->refresh()->name)->toBe('Renamed');
    });

    it('keeps a client update that names the same record', function (): void {
        $record = zzGuardRecord();

        Livewire::test('zz-client-guard-detail', ['modelId' => $record->id])
            ->set('modelId', (string) $record->id)
            ->set('detailData.name', 'Renamed')
            ->call('store');

        expect($record->refresh()->name)->toBe('Renamed');
    });
});

describe('components without a field layout', function (): void {
    it('persists nothing when a hand-built page declares no field', function (): void {
        $record = zzGuardRecord();

        Livewire::test('zz-client-guard-page', ['modelId' => $record->id])
            ->set('detailData.name', 'INJECTED')
            ->set('detailData.notes', 'INJECTED')
            ->call('store');

        expect($record->refresh()->name)->toBe('Original')
            ->and($record->notes)->toBe('internal');
    });

    it('persists exactly the fields a hand-built page declares', function (): void {
        $record = zzGuardRecord();

        Livewire::test('zz-client-guard-declared-page', ['modelId' => $record->id])
            ->set('detailData.name', 'Renamed')
            ->set('detailData.notes', 'INJECTED')
            ->call('store');

        expect($record->refresh()->name)->toBe('Renamed')
            ->and($record->notes)->toBe('internal');
    });
});

describe('ClientWritablePaths', function (): void {
    it('derives readonly paths and nested roots from a layout', function (): void {
        $paths = ClientWritablePaths::fromLayout([
            ['name' => 'detailData.name', 'type' => 'text'],
            ['name' => 'detailData.code', 'type' => 'text', 'readonly' => true],
            ['type' => 'block', 'fields' => [
                ['name' => 'detailData.custom_attributes.sap', 'type' => 'text'],
            ]],
            ['name' => 'detailData.title', 'type' => 'translatableText'],
        ]);

        expect($paths->allows('detailData.name'))->toBeTrue()
            ->and($paths->allows('detailData.title.de'))->toBeTrue()
            ->and($paths->allows('detailData.code'))->toBeFalse()
            ->and($paths->allows('detailData'))->toBeFalse()
            ->and($paths->allows('detailData.tenant_id'))->toBeFalse()
            ->and($paths->allows('relationTitles.customer_id'))->toBeTrue()
            ->and($paths->declaredKeysOf('detailData'))->toEqualCanonicalizing(['name', 'code', 'custom_attributes', 'title'])
            ->and($paths->allows('detailData.custom_attributes.sap'))->toBeTrue()
            ->and($paths->allows('detailData.custom_attributes.other'))->toBeFalse()
            ->and($paths->allows('detailData.custom_attributes'))->toBeFalse()
            ->and($paths->readonlyPaths('detailData'))->toBe(['code'])
            ->and($paths->nestedRootsOf('detailData'))->toBe(['custom_attributes' => ['custom_attributes.sap']]);
    });

    it('keeps a path editable when another field binds it without readonly', function (): void {
        $paths = ClientWritablePaths::fromLayout([
            ['name' => 'detailData.code', 'type' => 'text', 'readonly' => true],
            ['name' => 'detailData.code', 'type' => 'text', 'tab' => 2],
        ]);

        expect($paths->allows('detailData.code'))->toBeTrue();
    });
});

describe('text-only fields', function (): void {
    beforeEach(function (): void {
        File::put(base_path('app-configs/setup/details/zz-client-guard-display-detail.yml'), implode("\n", [
            'title: Zz Client Guard Display',
            'fields:',
            '  - name: detailData.name',
            '    label: Name',
            '    type: text',
            '  - name: detailData.notes',
            '    label: Notes',
            '    type: text',
            '    theme: display',
            '  - type: block',
            '    theme: display',
            '    fields:',
            '      - name: detailData.code',
            '        label: Code',
            '        type: text',
        ]));

        Livewire::component('zz-client-guard-display-detail', ZzClientGuardDisplayDetail::class);
    });

    afterEach(function (): void {
        File::delete(base_path('app-configs/setup/details/zz-client-guard-display-detail.yml'));
    });

    it('discards a client update of a field rendered as text, on the field or through its block', function (): void {
        $record = zzGuardRecord();

        Livewire::test('zz-client-guard-display-detail', ['modelId' => $record->id])
            ->set('detailData.notes', 'INJECTED')
            ->set('detailData.code', 'INJECTED')
            ->assertSet('detailData.notes', 'internal')
            ->assertSet('detailData.code', 'C-1')
            ->set('detailData.name', 'Renamed')
            ->call('store');

        expect($record->refresh()->notes)->toBe('internal')
            ->and($record->code)->toBe('C-1')
            ->and($record->name)->toBe('Renamed');
    });

    it('treats every field of a text-only layout as read-only', function (): void {
        $paths = ClientWritablePaths::fromLayout(
            [['name' => 'detailData.name', 'type' => 'text'], ['name' => 'detailData.code', 'type' => 'text', 'theme' => 'default']],
            layoutTheme: 'display',
        );

        expect($paths->allows('detailData.name'))->toBeFalse()
            ->and($paths->allows('detailData.code'))->toBeTrue();
    });
});

class ZzClientGuardDisplayDetail extends ZzClientGuardDetail
{
    protected function componentName(): string
    {
        return 'zz-client-guard-display-detail';
    }
}
