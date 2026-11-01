<?php

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Noerd\Enums\Profile;
use Noerd\Helpers\NoerdAuth;
use Noerd\Helpers\TenantHelper;
use Noerd\Models\NoerdUser;
use Noerd\Models\SetupLanguage;
use Noerd\Models\Tenant;
use Noerd\Notifications\AddedToTenantNotification;
use Noerd\Rules\AtLeastOneTrue;
use Noerd\Services\ProfileRegistry;
use Noerd\Support\LayoutFields;
use Noerd\Support\Locales;
use Noerd\Traits\AdministersNoerdUsers;
use Noerd\Traits\NoerdDetail;

new class extends Component {
    use AdministersNoerdUsers;
    use NoerdDetail;

    public ?string $detailPrimary = 'userId';

    public $detailModel = NoerdUser::class;

    /** The edited account is the administrator's own — it cannot remove itself. */
    public bool $isOwner = false;

    /**
     * Whether name and e-mail of the edited account may be changed here: always
     * for a new account, otherwise only by a super admin or the person
     * themselves (see AdministersNoerdUsers::canManageAccount()).
     */
    #[Locked]
    public bool $accountEditable = true;

    public bool $sendPasswordResetMail = true;
    public string $userLocale = 'en';
    public string $userFormatLocale = Locales::DEFAULT;

    public array $tenantAccess = [];
    public array $possibleTenants = [];

    public function localeOptions(): array
    {
        return SetupLanguage::active()->pluck('name', 'code')->toArray();
    }

    /**
     * The formatting locale (numbers, dates, amounts) — separate from the
     * interface language above.
     *
     * @return array<string, string>
     */
    public function formatLocaleOptions(): array
    {
        return Locales::options();
    }

    public function mount(): void
    {
        // Defense in depth: this admin-only user editor can be reached outside its
        // setup route (the modal stack). Enforce admin access
        // here too, independent of the dynamic-mount guard.
        abort_unless(NoerdAuth::user()?->isAdmin(), 403);

        $this->authorizeTargetUser();

        $this->initDetail();

        $this->isOwner = $this->modelId && (int) $this->modelId === NoerdAuth::id();
        $this->userLocale = SetupLanguage::defaultCode();
        $this->userFormatLocale = Locales::defaultFor($this->userLocale);

        $user = $this->modelId ? NoerdUser::find($this->modelId) ?? new NoerdUser() : new NoerdUser();

        $this->accountEditable = ! $user->exists || $this->canManageAccount();

        if (! $this->accountEditable) {
            $this->lockAccountFields();
        }

        // One query for the edited user's tenant assignments instead of one
        // per tenant. Mounts re-run on every modal-stack update, so this
        // loop must stay cheap.
        $profileByTenant = $user->exists
            ? $user->tenants->mapWithKeys(fn($tenant) => [$tenant->id => $tenant->pivot->profile_key])->all()
            : [];

        foreach ($this->assignableTenants($user) as $tenant) {
            $isMember = array_key_exists($tenant->id, $profileByTenant);

            $this->possibleTenants[$tenant->id] = $tenant->toArray();
            $this->possibleTenants[$tenant->id]['selectedProfile'] = $profileByTenant[$tenant->id] ?? Profile::User->value;
            $this->possibleTenants[$tenant->id]['hasAccess'] = $isMember;
        }
    }

    public function store(): void
    {
        // Rebuilt from scratch: a leftover entry from an earlier submit would
        // otherwise keep satisfying the AtLeastOneTrue rule.
        $this->tenantAccess = [];

        foreach ($this->possibleTenants as $tenantId => $value) {
            $this->tenantAccess[$tenantId] = (bool) ($value['hasAccess'] ?? false);
        }

        // "Add User" with an address that already has an account adds that
        // account (like an invitation that applies at once) — its name and
        // password stay the person's.
        $existing = $this->modelId
            ? null
            : NoerdUser::where('email', (string) ($this->detailData['email'] ?? ''))->first();

        $this->validate($this->storeRules($existing));

        if (! $this->modelId) {
            $existing === null ? $this->createAccount() : $this->addExistingAccount($existing);

            return;
        }

        // $modelId is URL-bound and therefore client-writable — re-assert that the
        // edited account is one this admin may touch before writing to it.
        $this->authorizeTargetUser();

        // Never create an account under an id that does not resolve: a stale or
        // invented $modelId stores nothing (a super admin skips the target check).
        $user = NoerdUser::find($this->modelId);

        if (! $user instanceof NoerdUser) {
            return;
        }

        // 'password' is deliberately NOT writable here: an existing account's
        // password is set through the dedicated user-update-password form. Name
        // and e-mail belong to the person — only a super admin or the person
        // changes them.
        if ($this->accountEditable && $this->canManageAccount()) {
            $user->fill(collect($this->detailData)->only(['name', 'email'])->toArray())->save();
        }

        $this->applyTenantAccess($user, removeUnchecked: true, notify: true);

        $this->finishStore($user);
    }

    public function delete(): void
    {
        $this->removeFromSelectedTenant();
    }

    /**
     * @return array<string, mixed>
     */
    private function storeRules(?NoerdUser $existing): array
    {
        $rules = [
            'detailData.email' => ['required', 'string', 'email', 'max:255'],
        ];

        if (! $this->modelId) {
            $rules['tenantAccess'] = ['array', 'min:1', new AtLeastOneTrue()];

            // A name is only needed for an account that does not exist yet.
            if ($existing === null) {
                $rules['detailData.name'] = ['required', 'string', 'max:255'];
            }
        } elseif ($this->accountEditable) {
            $rules['detailData.name'] = ['required', 'string', 'max:255'];
            $rules['detailData.email'][] = Rule::unique('noerd_users', 'email')->ignore($this->modelId);
        }

        // Every registered profile is assignable — the built-in ones and the
        // ones a module registered (the select renders the same registry).
        $profileKeys = array_keys(app(ProfileRegistry::class)->options());

        foreach ($this->possibleTenants as $tenantId => $value) {
            if ($value['hasAccess'] ?? false) {
                $rules["possibleTenants.{$tenantId}.selectedProfile"] = ['required', Rule::in($profileKeys)];
            }
        }

        return $rules;
    }

    private function createAccount(): void
    {
        $user = NoerdUser::create([
            'name' => $this->detailData['name'],
            'email' => $this->detailData['email'],
            // No password needed - the person sets it through the reset link;
            // this placeholder is never known to anybody.
            'password' => bcrypt(Str::random(32)),
        ]);

        $this->applyTenantAccess($user, removeUnchecked: false, notify: false);

        $this->finishStore($user);

        $user->locale = $this->userLocale;
        $user->format_locale = Locales::isSupported($this->userFormatLocale) ? $this->userFormatLocale : Locales::defaultFor($this->userLocale);

        if ($this->sendPasswordResetMail) {
            NoerdAuth::broker()->sendResetLink(['email' => $user->email]);
        }

        $user->save();
    }

    private function addExistingAccount(NoerdUser $user): void
    {
        // A super admin is never capturable this way.
        abort_if($user->isSuperAdmin(), 403);

        $this->applyTenantAccess($user, removeUnchecked: false, notify: true);

        $this->finishStore($user);
    }

    /**
     * Apply the requested memberships, restricted to the tenants this admin
     * may assign. Every write goes through the tenant's membership API, so the
     * modules hear it. A person added to a tenant of an EXISTING account is told
     * by mail; nobody ever removes their own membership here.
     */
    private function applyTenantAccess(NoerdUser $user, bool $removeUnchecked, bool $notify): void
    {
        $admin = NoerdAuth::user();
        $assignable = $this->assignableTenants($user)->keyBy('id');

        foreach ($this->possibleTenants as $tenantId => $value) {
            $tenant = $assignable->get((int) $tenantId);

            if (! $tenant instanceof Tenant) {
                continue;
            }

            if ($value['hasAccess'] ?? false) {
                $profileKey = (string) ($value['selectedProfile'] ?? '');

                if ($tenant->addMember($user, $profileKey)) {
                    if ($notify && ! $user->is($admin)) {
                        $user->notify(new AddedToTenantNotification($tenant->name, (string) ($admin->name ?: $admin->email)));
                    }
                } else {
                    $tenant->setMemberProfile($user, $profileKey);
                }
            } elseif ($removeUnchecked && ! $user->is($admin)) {
                $tenant->removeMember($user);
            }
        }
    }

    /**
     * The tenants whose membership this administrator may set on the form: the
     * tenants they administer as a member — a super admin additionally the
     * selected tenant and every tenant the account already belongs to (to
     * reach any other tenant, the super admin selects it first).
     *
     * @return Collection<int, Tenant>
     */
    private function assignableTenants(NoerdUser $user): Collection
    {
        $admin = NoerdAuth::user();
        $tenants = $admin->adminTenants()->get();

        if ($admin->isSuperAdmin()) {
            $ids = array_filter([TenantHelper::getSelectedTenantId()]);

            if ($user->exists) {
                $ids = array_merge($ids, $user->tenants()->pluck('tenants.id')->all());
            }

            $tenants = $tenants->merge(Tenant::query()->whereKey($ids)->get());
        }

        return $tenants->unique('id')->sortBy('name')->values()->toBase();
    }

    /**
     * Name and e-mail are shown, not edited, when the account is not this
     * administrator's to manage — the YAML cannot say "read-only once saved".
     */
    private function lockAccountFields(): void
    {
        $this->pageLayout['fields'] = LayoutFields::map(
            $this->pageLayout['fields'] ?? [],
            fn(array $field): array => in_array($field['name'] ?? null, ['detailData.name', 'detailData.email'], true)
                ? array_merge($field, ['readonly' => true])
                : $field,
        );
    }
} ?>

<x-noerd::page>
    <x-slot:header>
        <x-noerd::modal-title>{{ __('User') }}</x-noerd::modal-title>
    </x-slot:header>

    <x-noerd::tab-content :layout="$pageLayout">
        <x-slot:tab1>
            @if(!isset($modelId))
                <x-noerd::checkbox
                    wire:model="sendPasswordResetMail"
                    name="sendPasswordResetMail">
                    {{ __('Send the user an email with a link to set their password.') }}
                </x-noerd::checkbox>
            @endif

            @unless($accountEditable)
                <p class="text-sm text-gray-500">
                    {{ __('Name and e-mail belong to the person — they change them in their profile.') }}
                </p>
            @endunless

            <x-noerd::detail-slot name="user-below-form" :modelId="$modelId" />

            <div class="py-8 pt-4">
                <div class="pb-4">
                    {{ __('Access to the following tenants:') }}
                </div>
                <fieldset class="pl-2">
                    @foreach($possibleTenants as $tenant)
                        <div class="space-y-5 max-w-2xl" wire:key="tenant-access-{{ $tenant['id'] }}">
                            <div class="relative flex items-start py-1">
                                <div class="flex my-auto h-6 items-center">
                                    <x-noerd::checkbox
                                        wire:model.live="possibleTenants.{{$tenant['id']}}.hasAccess"
                                        :name="$tenant['id']">
                                        {{$tenant['name']}}
                                    </x-noerd::checkbox>
                                </div>

                                <div class="ml-auto">
                                    <x-noerd::select-input
                                        wire:model.live="possibleTenants.{{$tenant['id']}}.selectedProfile"
                                        @class([
                                            "w-48! mt-0!",
                                            "opacity-50" => !$tenant['hasAccess']
                                        ])
                                    >
                                        @foreach(app(\Noerd\Services\ProfileRegistry::class)->options() as $profileKey => $profileLabel)
                                            <option value="{{$profileKey}}">{{$profileLabel}}</option>
                                        @endforeach
                                    </x-noerd::select-input>
                                </div>
                            </div>
                        </div>
                    @endforeach

                </fieldset>
                <x-noerd::input-error :messages="$errors->get('tenantAccess')" class="mt-2"/>
            </div>
        </x-slot:tab1>
    </x-noerd::tab-content>

    <x-slot:footer>
        <x-noerd::delete-save-bar
            :showDelete="isset($modelId) && !$isOwner && $this->assignedToCurrentTenant"
            :deleteLabel="__('Remove from tenant')"
        />
    </x-slot:footer>
</x-noerd::page>
