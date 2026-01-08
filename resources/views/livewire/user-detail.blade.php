<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Noerd\Noerd\Models\Profile;
use Noerd\Noerd\Models\User;
use Noerd\Noerd\Models\UserRole;
use Noerd\Noerd\Traits\Noerd;

new class extends Component {

    use Noerd;

    public const COMPONENT = 'user-detail';
    public const LIST_COMPONENT = 'users-list';
    public const ID = 'userId';
    #[Url(keep: false, except: '')]
    public $userId = null;
    public bool $isOwner = false;
    public $selectedTenant;

    public array $user;
    public array $tenantAccess = [];
    public array $userRoles = [];
    public array $possibleTenants = [];

    #[Computed]
    public function roles(): array
    {
        $roles = [];
        $tenants = auth()->user()->adminTenants;
        foreach ($tenants as $tenant) {
            $roles[$tenant->name] = UserRole::where('tenant_id', $tenant->id)->get();
        }
        return $roles;
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
        if (!isset($this->userId)) {
            return false;
        }

        $user = User::find($this->userId);
        if(!$user) {
            return false;
        }
        return $user->tenants->contains(auth()->user()->selected_tenant_id);
    }

    public function mount(User $model): void
    {
        $this->selectedTenant = auth()->user()->selectedTenant();

        if ($this->modelId) {
            $model = User::find($this->modelId);
            foreach ($model->roles as $role) {
                $this->userRoles[$role->id] = true;
            }
        }

        $this->mountModalProcess(self::COMPONENT, $model);
        $this->user = $model->toArray();

        foreach (auth()->user()->adminTenants as $tenant) {
            $this->possibleTenants[$tenant->id] = $tenant->toArray();
            $userProfile = $tenant->users()->where('user_id', $model->id)->first();
            $profileId = $userProfile?->pivot->profile_id;

            $this->possibleTenants[$tenant->id]['selectedProfile'] = $profileId;
            $hasAccess = (bool)$profileId;
            $this->possibleTenants[$tenant->id]['hasAccess'] = $hasAccess;

            if (!$hasAccess) {
                $this->possibleTenants[$tenant->id]['selectedProfile'] = $tenant->profiles->first()->id;
            }
        }
    }

    public function store(): void
    {
        foreach ($this->possibleTenants as $tenantId => $value) {
            $this->tenantAccess[$tenantId] = $value['hasAccess'];
        }

        $this->validate([
            'user.name' => ['required', 'string', 'max:255'],
            'user.email' => [
                'required',
                'string',
                'email',
                'max:255',
            ],
            'tenantAccess' => ['array', 'min:1', new \Noerd\Noerd\Rules\AtLeastOneTrue()],
        ]);

        if (!$this->userId) {
            $userExists = User::where('email', $this->user['email'])->first();
            if ($userExists) {
                $allowedTenants = Auth::user()->adminTenants()->pluck('id');
                foreach ($this->possibleTenants as $tenantId => $value) {
                    $profileId = $value['selectedProfile'];
                    if ($value['hasAccess'] && in_array($tenantId, $allowedTenants->toArray())) {
                        $userExists->tenants()->attach($tenantId, ['profile_id' => $profileId]);
                    }
                }

                return;
            }
            // No password needed - user will set it via password reset link
            // Set a temporary password that will be overwritten when user resets
            $this->user['password'] = bcrypt(Str::random(32));
        }

        $user = User::updateOrCreate(['id' => $this->userId], $this->user);
        foreach ($this->userRoles as $key => $value) {
            $user->roles()->detach($key);
            if ($value) {
                $user->roles()->attach($key);
            }
        }

        $allowedTenants = Auth::user()->adminTenants()->pluck('id');
        foreach ($this->possibleTenants as $tenantId => $value) {
            $user->tenants()->detach($tenantId);
            $profileId = $value['selectedProfile'];
            if ($value['hasAccess'] && in_array($tenantId, $allowedTenants->toArray())) {
                $user->tenants()->attach($tenantId, ['profile_id' => $profileId]);
            }
        }

        $this->showSuccessIndicator = true;

        if ($user->wasRecentlyCreated) {
            $this->userId = $user['id'];

            // Send password reset link instead of generated password
            Password::sendResetLink(['email' => $user->email]);

            $user->save();
        }
    }

    public function delete(): void
    {
        $user = User::find($this->userId);

        $user->tenants()->detach(auth()->user()->selected_tenant_id);
        $this->closeModalProcess(self::LIST_COMPONENT);

        // If user has no more tenants, delete the user
        if ($user->tenants()->count() === 0) {
            $user->delete();
        }
    }
} ?>

<x-noerd::page :disableModal="$disableModal">
    <x-slot:header>
        <x-noerd::modal-title>Benutzer</x-noerd::modal-title>
    </x-slot:header>

    <x-noerd::tab-content :layout="$pageLayout">
        <x-slot:tab1>
            @if(!isset($userId))
                <div>
                    {{ __('The user will receive a link via email to set their password.') }}
                </div>
            @endif

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

            @if(count($this->roles()) > 0)
                @foreach($this->roles() as $tenantName => $roles)
                    @if(count($roles) > 0)
                        <div class="py-8 pt-4">
                            <div class="pb-4">
                                Benutzerrollen {{$tenantName}}
                            </div>
                            <fieldset class="pl-2">
                                @foreach($roles as $role)
                                    <x-noerd::checkbox
                                        wire:model.live="userRoles.{{$role->id}}" id="r{{$role->id}}"
                                        :name="$tenant['id']">
                                        {{ $role->name }} <br/>
                                        {{$role->description}}
                                    </x-noerd::checkbox>
                                @endforeach
                            </fieldset>
                            <x-noerd::input-error :messages="$errors->get('userRoles')" class="mt-2"/>
                        </div>
                    @endif
                @endforeach
            @endif

            @isset($userId)
                <x-noerd::box>
                    <div class="max-w-xl">
                        <livewire:setup.user-update-password :userId="$userId"/>
                    </div>
                </x-noerd::box>
            @endisset
        </x-slot:tab1>
    </x-noerd::tab-content>

    <x-slot:footer>
        <x-noerd::delete-save-bar :showDelete="isset($userId) && !$isOwner && $this->assignedToCurrentTenant"/>
    </x-slot:footer>
</x-noerd::page>
