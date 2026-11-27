<?php

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Noerd\Enums\Profile;
use Noerd\Helpers\FormatHelper;
use Noerd\Helpers\NoerdAuth;
use Noerd\Helpers\TenantHelper;
use Noerd\Models\NoerdUser;
use Noerd\Models\NoerdSettings;
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

    /**
     * The password of an account created without an e-mail address — nobody
     * could send it a link, so the administrator hands this one over. Shown
     * once, never stored in readable form.
     */
    #[Locked]
    public ?string $initialPassword = null;
    public string $userLocale = 'en';
    public string $userFormatLocale = Locales::DEFAULT;

    public array $tenantAccess = [];
    public array $possibleTenants = [];

    public function localeOptions(): array
    {
        return NoerdSettings::userLanguageOptions();
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
        // A new account starts on the tenant defaults of System Settings.
        $this->userLocale = NoerdSettings::defaultUserLanguage();
        $this->userFormatLocale = FormatHelper::tenantLocale();

        $user = $this->modelId ? NoerdUser::find($this->modelId) ?? new NoerdUser() : new NoerdUser();

        $this->accountEditable = ! $user->exists || $this->canManageAccount();

        if (! $this->accountEditable) {
            $this->lockAccountFields();
        }

        if (! NoerdAuth::usernameLoginEnabled()) {
            $this->dropUsernameField();
        }

        // One query for the edited user's tenant assignments instead of one
        // per tenant. Mounts re-run on every modal-stack update, so this
        // loop must stay cheap.
        $profileByTenant = $user->exists
            ? $user->tenants->mapWithKeys(fn($tenant) => [$tenant->id => $tenant->pivot->profile_key])->all()
            : [];

        // Only what the form shows and edits goes to the browser — never the
        // whole tenant row.
        foreach ($this->assignableTenants($user) as $tenant) {
            $this->possibleTenants[$tenant->id] = [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'selectedProfile' => $profileByTenant[$tenant->id] ?? Profile::User->value,
                'hasAccess' => array_key_exists($tenant->id, $profileByTenant),
            ];
        }
    }

    public function store(): void
    {
        // A username is stored lowercase — compare and validate what will be
        // stored. While usernames are switched off the form has no such field,
        // and a crafted one is ignored.
        if (! NoerdAuth::usernameLoginEnabled()) {
            unset($this->detailData['username']);
        } elseif (is_string($this->detailData['username'] ?? null)) {
            $this->detailData['username'] = mb_strtolower(mb_trim($this->detailData['username']));
        }

        // "Add User" with an address or a username that already has an account
        // adds that account (like an invitation that applies at once) — its
        // name and password stay the person's.
        $existing = $this->modelId
            ? null
            : (NoerdUser::findForLogin((string) ($this->detailData['email'] ?? ''))
                ?? NoerdUser::findForLogin((string) ($this->detailData['username'] ?? '')));

        // $possibleTenants is client state: only the tenants this administrator
        // may assign count, so a crafted entry can neither satisfy the
        // AtLeastOneTrue rule nor create an account without a tenant.
        $target = $this->modelId
            ? (NoerdUser::find($this->modelId) ?? new NoerdUser())
            : ($existing ?? new NoerdUser());
        $this->possibleTenants = $this->assignableTenantEntries($target, $this->possibleTenants);

        // Rebuilt from scratch: a leftover entry from an earlier submit would
        // otherwise keep satisfying the AtLeastOneTrue rule.
        $this->tenantAccess = [];

        foreach ($this->possibleTenants as $tenantId => $value) {
            $this->tenantAccess[$tenantId] = (bool) ($value['hasAccess'] ?? false);
        }

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

        // A tenant keeps at least one administrator — also against the
        // administrator demoting themselves.
        if (! $this->keepsEveryTenantAdministered($user)) {
            return;
        }

        // 'password' is deliberately NOT writable here: an existing account's
        // password is set through the dedicated user-update-password form. Name
        // e-mail and username belong to the person — only a super admin or the
        // person changes them.
        if ($this->accountEditable && $this->canManageAccount()) {
            $user->fill(collect($this->detailData)->only(['name', 'email', ...(NoerdAuth::usernameLoginEnabled() ? ['username'] : [])])->toArray())->save();
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
        // With usernames switched on an account signs in with its e-mail
        // address or its username — one of the two is enough, and an "@"
        // belongs to the address only. Otherwise the address is required.
        $rules = NoerdAuth::usernameLoginEnabled()
            ? [
                'detailData.email' => ['nullable', 'required_without:detailData.username', 'string', 'email', 'max:255'],
                'detailData.username' => ['nullable', 'string', 'max:' . NoerdUser::USERNAME_MAX_LENGTH, 'regex:' . NoerdUser::USERNAME_PATTERN],
            ]
            : ['detailData.email' => ['required', 'string', 'email', 'max:255']];

        if (! $this->modelId) {
            $rules['tenantAccess'] = ['array', 'min:1', new AtLeastOneTrue()];

            // Name, language and locale are only needed for an account that does
            // not exist yet. Language and locale come from the client — only
            // offered values count.
            if ($existing === null) {
                $rules['detailData.name'] = ['required', 'string', 'max:255'];
                $rules['userLocale'] = ['required', Rule::in(array_keys($this->localeOptions()))];
                $rules['userFormatLocale'] = ['required', Rule::in(Locales::SUPPORTED)];
            }
        } elseif ($this->accountEditable) {
            $rules['detailData.name'] = ['required', 'string', 'max:255'];
            $rules['detailData.email'][] = Rule::unique('noerd_users', 'email')->ignore($this->modelId);

            if (isset($rules['detailData.username'])) {
                $rules['detailData.username'][] = Rule::unique('noerd_users', 'username')->ignore($this->modelId);
            }
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
        $email = mb_trim((string) ($this->detailData['email'] ?? ''));

        // With an address the person sets the password through the reset link
        // and the placeholder is never known to anybody. Without one there is
        // no link to send: the account starts with a password the administrator
        // hands over (the person changes it in their profile).
        $password = $email === '' ? Str::password(12, symbols: false) : Str::random(32);

        $user = NoerdUser::create([
            'name' => $this->detailData['name'],
            'email' => $email,
            'username' => $this->detailData['username'] ?? null,
            'password' => bcrypt($password),
        ]);

        $this->initialPassword = $email === '' ? $password : null;

        $this->applyTenantAccess($user, removeUnchecked: false, notify: false);

        $this->finishStore($user);

        $user->locale = $this->userLocale;
        $user->format_locale = Locales::isSupported($this->userFormatLocale) ? $this->userFormatLocale : FormatHelper::tenantLocale();

        if ($this->sendPasswordResetMail && $user->email !== null) {
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
                        $user->notify(new AddedToTenantNotification($tenant->name, (string) ($admin->name ?: $admin->loginName())));
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
     * The submitted tenant entries reduced to the tenants this administrator
     * may assign to $user, each with only the keys the form edits; the name
     * comes from the server, not from the client.
     *
     * @param  array<array-key, mixed>  $submitted
     * @return array<int, array{id: int, name: string, selectedProfile: string, hasAccess: bool}>
     */
    private function assignableTenantEntries(NoerdUser $user, array $submitted): array
    {
        $entries = [];

        foreach ($this->assignableTenants($user) as $tenant) {
            $value = $submitted[$tenant->id] ?? null;

            if (! is_array($value)) {
                continue;
            }

            $entries[(int) $tenant->id] = [
                'id' => (int) $tenant->id,
                'name' => (string) $tenant->name,
                'selectedProfile' => (string) ($value['selectedProfile'] ?? Profile::User->value),
                'hasAccess' => (bool) ($value['hasAccess'] ?? false),
            ];
        }

        return $entries;
    }

    /**
     * Whether the requested memberships leave every assignable tenant with an
     * administrator (Tenant::wouldLoseLastAdmin()). A violation is reported on
     * the profile select of that tenant and nothing is written.
     */
    private function keepsEveryTenantAdministered(NoerdUser $user): bool
    {
        $admin = NoerdAuth::user();
        $keeps = true;

        foreach ($this->assignableTenants($user)->keyBy('id') as $tenantId => $tenant) {
            $value = $this->possibleTenants[$tenantId] ?? null;

            if (! is_array($value)) {
                continue;
            }

            $hasAccess = (bool) ($value['hasAccess'] ?? false);

            // An unchecked tenant of one's own account is never removed (see applyTenantAccess()).
            if (! $hasAccess && $user->is($admin)) {
                continue;
            }

            $profileKey = $hasAccess ? (string) ($value['selectedProfile'] ?? '') : null;

            if ($tenant->wouldLoseLastAdmin($user, $profileKey)) {
                $this->addError(
                    "possibleTenants.{$tenantId}.selectedProfile",
                    __('This is the only administrator of :tenant. Assign another administrator first.', ['tenant' => $tenant->name]),
                );
                $keeps = false;
            }
        }

        return $keeps;
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
     * The username field only exists while usernames are switched on
     * (NoerdAuth::usernameLoginEnabled()) — the YAML cannot ask a feature flag.
     */
    private function dropUsernameField(): void
    {
        $this->pageLayout['fields'] = array_values(array_filter(
            $this->pageLayout['fields'] ?? [],
            fn(array $field): bool => ($field['name'] ?? null) !== 'detailData.username',
        ));
    }

    /**
     * Name, e-mail and username are shown, not edited, when the account is not
     * this administrator's to manage — the YAML cannot say "read-only once saved".
     */
    private function lockAccountFields(): void
    {
        $this->pageLayout['fields'] = LayoutFields::map(
            $this->pageLayout['fields'] ?? [],
            fn(array $field): array => in_array($field['name'] ?? null, ['detailData.name', 'detailData.email', 'detailData.username'], true)
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
            @if ($initialPassword !== null)
                <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
                    <p>{{ __('This account has no e-mail address, so no link could be sent. Hand this password to the person — it is shown only once:') }}</p>
                    <p class="mt-2 font-mono text-base font-semibold select-all">{{ $initialPassword }}</p>
                </div>
            @endif

            @if(!isset($modelId))
                <x-noerd::checkbox
                    wire:model="sendPasswordResetMail"
                    name="sendPasswordResetMail">
                    {{ __('Send the user an email with a link to set their password (accounts with an e-mail address).') }}
                </x-noerd::checkbox>
            @endif

            @unless($accountEditable)
                <p class="text-sm text-gray-500">
                    {{ __('Name, e-mail and username belong to the person — they change them in their profile.') }}
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
