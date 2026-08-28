<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Noerd\Helpers\NoerdAuth;
use Noerd\Models\NoerdUser;
use Noerd\Models\Profile;
use Noerd\Models\SetupLanguage;
use Noerd\Traits\NoerdDetail;

new class extends Component {
    use NoerdDetail;

    public ?string $detailPrimary = 'userId';

    public $detailModel = NoerdUser::class;

    public bool $isOwner = false;
    public $selectedTenant;

    public bool $sendPasswordResetMail = true;
    public string $userLocale = 'en';

    public array $tenantAccess = [];
    public array $possibleTenants = [];

    public function localeOptions(): array
    {
        return SetupLanguage::getActive()->pluck('name', 'code')->toArray();
    }

    #[Computed]
    public function tenantProfiles(): array
    {
        $profiles = Profile::where('tenant_id', auth()->user()->selected_tenant_id)->get();
        $array = [];
        foreach ($profiles as $profile) {
            $array[$profile->id] = $profile->name;
        }

        return $array;
    }

    #[Computed]
    public function assignedToCurrentTenant(): bool
    {
        if (! isset($this->modelId)) {
            return false;
        }

        $user = NoerdUser::find($this->modelId);
        if (! $user) {
            return false;
        }

        return $user->tenants->contains(auth()->user()->selected_tenant_id);
    }

    public function mount(): void
    {
        // Defense in depth: this admin-only user editor can be reached outside its
        // setup route (modal stack / generic component page). Enforce admin access
        // here too, independent of the dynamic-mount guard.
        abort_unless(NoerdAuth::user()?->isAdmin(), 403);

        $this->initDetail();

        $this->selectedTenant = auth()->user()->selectedTenant();
        $this->userLocale = SetupLanguage::getDefaultCode();

        $user = new NoerdUser();
        if ($this->modelId) {
            $user = NoerdUser::find($this->modelId);
        }

        $this->detailData = $user->toArray();

        // One query for the edited user's tenant assignments instead of one
        // per admin tenant. Mounts re-run on every modal-stack update, so this
        // loop must stay cheap.
        $profileByTenant = $user->exists
            ? $user->tenants->mapWithKeys(fn($tenant) => [$tenant->id => $tenant->pivot->profile_id])->all()
            : [];

        foreach (auth()->user()->adminTenants as $tenant) {
            $this->possibleTenants[$tenant->id] = $tenant->toArray();
            $profileId = $profileByTenant[$tenant->id] ?? null;

            $this->possibleTenants[$tenant->id]['selectedProfile'] = $profileId;
            $hasAccess = (bool) $profileId;
            $this->possibleTenants[$tenant->id]['hasAccess'] = $hasAccess;

            if (! $hasAccess) {
                // A tenant without any profile row simply offers no preselect.
                $this->possibleTenants[$tenant->id]['selectedProfile'] = $tenant->profiles->first()?->id;
            }
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
            'tenantAccess' => ['array', 'min:1', new \Noerd\Rules\AtLeastOneTrue()],
        ]);

        if (! $this->modelId) {
            $userExists = NoerdUser::where('email', $this->detailData['email'])->first();
            if ($userExists) {
                $allowedTenants = Auth::user()->adminTenants()->pluck('id');
                foreach ($this->possibleTenants as $tenantId => $value) {
                    $profileId = $value['selectedProfile'];
                    if ($value['hasAccess'] && in_array($tenantId, $allowedTenants->toArray())) {
                        $userExists->tenants()->attach($tenantId, ['profile_id' => $profileId]);
                    }
                }

                $this->finishStore($userExists);

                return;
            }
            // No password needed - user will set it via password reset link
            // Set a temporary password that will be overwritten when user resets
            $this->detailData['password'] = bcrypt(Str::random(32));
        }

        $userData = collect($this->detailData)->only(['name', 'email', 'password'])->toArray();
        $user = NoerdUser::updateOrCreate(['id' => $this->modelId], $userData);

        $allowedTenants = Auth::user()->adminTenants()->pluck('id');
        foreach ($this->possibleTenants as $tenantId => $value) {
            $user->tenants()->detach($tenantId);
            $profileId = $value['selectedProfile'];
            if ($value['hasAccess'] && in_array($tenantId, $allowedTenants->toArray())) {
                $user->tenants()->attach($tenantId, ['profile_id' => $profileId]);
            }
        }

        $this->finishStore($user);

        if ($user->wasRecentlyCreated) {
            $user->locale = $this->userLocale;

            if ($this->sendPasswordResetMail) {
                // Send password reset link instead of generated password
                NoerdAuth::broker()->sendResetLink(['email' => $user->email]);
            }

            $user->save();
        }
    }

    public function delete(): void
    {
        $user = NoerdUser::find($this->modelId);

        $user->tenants()->detach(auth()->user()->selected_tenant_id);
        $this->closeModalProcess($this->getListComponent());

        // If user has no more tenants, delete the user
        if ($user->tenants()->count() === 0) {
            $user->delete();
        }
    }
} ?>

<x-noerd::page>
    <x-slot:header>
        <x-noerd::modal-title>Benutzer</x-noerd::modal-title>
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
                                        @foreach($tenant['profiles'] as $profile)
                                            <option value="{{$profile['id']}}">{{$profile['name']}}</option>
                                        @endforeach
                                    </x-noerd::select-input>
                                </div>
                            </div>
                        </div>
                    @endforeach

                </fieldset>
                <x-noerd::input-error :messages="$errors->get('tenantAccess')" class="mt-2"/>
            </div>

            @isset($modelId)
                <x-noerd::box>
                    <div class="max-w-xl">
                        <livewire:noerd::user-update-password :userId="$modelId"/>
                    </div>
                </x-noerd::box>
            @endisset
        </x-slot:tab1>
    </x-noerd::tab-content>

    <x-slot:footer>
        <x-noerd::delete-save-bar :showDelete="isset($modelId) && !$isOwner && $this->assignedToCurrentTenant"/>
    </x-slot:footer>
</x-noerd::page>
