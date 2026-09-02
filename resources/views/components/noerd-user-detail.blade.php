<?php

use Illuminate\Support\Str;
use Livewire\Component;
use Noerd\Helpers\NoerdAuth;
use Noerd\Models\NoerdUser;
use Noerd\Rules\AtLeastOneTrue;
use Noerd\Enums\Profile;
use Noerd\Models\SetupLanguage;
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

    public bool $sendPasswordResetMail = true;
    public string $userLocale = 'en';
    public string $userFormatLocale = Locales::DEFAULT;

    public array $tenantAccess = [];
    public array $possibleTenants = [];

    public function localeOptions(): array
    {
        return SetupLanguage::getActive()->pluck('name', 'code')->toArray();
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
        // setup route (modal stack / generic component page). Enforce admin access
        // here too, independent of the dynamic-mount guard.
        abort_unless(NoerdAuth::user()?->isAdmin(), 403);

        $this->authorizeTargetUser();

        $this->initDetail();

        $this->isOwner = $this->modelId && (int) $this->modelId === NoerdAuth::id();
        $this->userLocale = SetupLanguage::getDefaultCode();
        $this->userFormatLocale = Locales::defaultFor($this->userLocale);

        $user = $this->modelId ? NoerdUser::find($this->modelId) ?? new NoerdUser() : new NoerdUser();

        // One query for the edited user's tenant assignments instead of one
        // per admin tenant. Mounts re-run on every modal-stack update, so this
        // loop must stay cheap.
        $profileByTenant = $user->exists
            ? $user->tenants->mapWithKeys(fn($tenant) => [$tenant->id => $tenant->pivot->profile_key])->all()
            : [];

        foreach (NoerdAuth::user()->adminTenants as $tenant) {
            $this->possibleTenants[$tenant->id] = $tenant->toArray();
            $profileKey = $profileByTenant[$tenant->id] ?? null;

            $this->possibleTenants[$tenant->id]['selectedProfile'] = $profileKey ?? Profile::User->value;
            $this->possibleTenants[$tenant->id]['hasAccess'] = (bool) $profileKey;
        }
    }

    public function store(): void
    {
        foreach ($this->possibleTenants as $tenantId => $value) {
            $this->tenantAccess[$tenantId] = $value['hasAccess'];
        }

        $this->validate([
            'detailData.name' => ['required', 'string', 'max:255'],
            'detailData.email' => [
                'required',
                'string',
                'email',
                'max:255',
            ],
            'tenantAccess' => ['array', 'min:1', new AtLeastOneTrue()],
        ]);

        if (! $this->modelId) {
            $userExists = NoerdUser::where('email', $this->detailData['email'])->first();
            if ($userExists) {
                // Granting access to an EXISTING account by email must obey the
                // same limits as editing it: a super admin is never capturable
                // this way, and a profile must belong to the tenant it is
                // granted for (this branch returns early, so store()'s own
                // checks below never run).
                abort_if($userExists->isSuperAdmin(), 403);

                $this->attachTenantAccess($userExists);

                $this->finishStore($userExists);

                return;
            }
            // No password needed - user will set it via password reset link
            // Set a temporary password that will be overwritten when user resets
            $this->detailData['password'] = bcrypt(Str::random(32));
        }

        // $modelId is URL-bound and therefore client-writable — re-assert that the
        // edited account is one this admin may touch before writing to it.
        $this->authorizeTargetUser();

        // 'password' is deliberately NOT writable here: an existing account's
        // password is set through the dedicated user-update-password form (which
        // authorizes the target itself). Only a NEW user carries the generated
        // placeholder set above.
        $writable = $this->modelId ? ['name', 'email'] : ['name', 'email', 'password'];
        $userData = collect($this->detailData)->only($writable)->toArray();
        $user = NoerdUser::updateOrCreate(['id' => $this->modelId], $userData);

        $this->attachTenantAccess($user, detachFirst: true);

        $this->finishStore($user);

        if ($user->wasRecentlyCreated) {
            $user->locale = $this->userLocale;
            $user->format_locale = Locales::isSupported($this->userFormatLocale) ? $this->userFormatLocale : Locales::defaultFor($this->userLocale);

            if ($this->sendPasswordResetMail) {
                // Send password reset link instead of generated password
                NoerdAuth::broker()->sendResetLink(['email' => $user->email]);
            }

            $user->save();
        }
    }

    /**
     * Apply the requested tenant access, restricted to the tenants this admin
     * actually administers and to the fixed profile keys. Shared by both
     * store() branches so neither can drift.
     */
    private function attachTenantAccess(NoerdUser $user, bool $detachFirst = false): void
    {
        $allowedTenants = array_map('intval', NoerdAuth::user()->adminTenants()->pluck('tenants.id')->all());

        foreach ($this->possibleTenants as $tenantId => $value) {
            // Both detach AND attach stay inside the allow-list: detaching
            // unconditionally let an admin of one tenant strip a user from any
            // other tenant (and, once the last one was gone, delete the account).
            if (! in_array((int) $tenantId, $allowedTenants, true)) {
                continue;
            }

            if ($detachFirst) {
                $user->tenants()->detach($tenantId);
            }

            $profile = Profile::tryFrom((string) ($value['selectedProfile'] ?? ''));

            if (($value['hasAccess'] ?? false) && $profile !== null) {
                $user->tenants()->detach($tenantId);
                $user->tenants()->attach($tenantId, ['profile_key' => $profile->value]);
            }
        }
    }

    public function delete(): void
    {
        $this->deleteUserAccount();
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

            <x-noerd::detail-slot name="user-below-form" :modelId="$modelId" />

            <div class="py-8 pt-4">
                <div class="pb-4">
                    {{ __('Access to the following tenants:') }}
                </div>
                <fieldset class="pl-2">
                    @foreach($possibleTenants as $tenant)
                        <div class="space-y-5 max-w-2xl">
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
        <x-noerd::delete-save-bar :showDelete="isset($modelId) && !$isOwner && $this->assignedToCurrentTenant"/>
    </x-slot:footer>
</x-noerd::page>
