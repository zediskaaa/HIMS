{{--
    Shared by the create and edit screens. The only differences are whether a
    password is required and whether the status field is offered, so the two
    pages pass $user and everything else follows from it. Included rather than
    made a component: it belongs to this module, not to the design system.

    Alpine mirrors the role list so picking a role immediately shows what that
    role can do — assigning access blind is how people end up over-permissioned.
--}}
@php
    $user = $user ?? null;
    $isEdit = $user !== null;
    $nameComponents = $user?->nameComponents() ?? [
        'surname' => null,
        'first_name' => null,
        'middle_name' => null,
    ];
    $roleDescriptions = collect($roles)->mapWithKeys(fn ($role) => [
        $role->value => [
            'description' => $role->description(),
            'permissions' => collect($role->permissions())->map->label()->values(),
        ],
    ]);
@endphp

<div x-data="{
    role: '{{ old('role', $user?->role?->value ?? \App\Enums\UserRole::Viewer->value) }}',
    roles: {{ \Illuminate\Support\Js::from($roleDescriptions) }},
    password: '',
    passwordConfirmation: '',
    get detail() { return this.roles[this.role] ?? null },
}">
    <div class="grid gap-4 md:grid-cols-2">
        <div class="grid gap-4 sm:grid-cols-2 md:col-span-2 lg:grid-cols-3">
            <x-ui.field
                name="surname"
                label="Surname"
                :value="$nameComponents['surname']"
                required
                autocomplete="family-name"
                placeholder="e.g. Dela Cruz" />

            <x-ui.field
                name="first_name"
                label="First Name"
                :value="$nameComponents['first_name']"
                required
                autocomplete="given-name"
                placeholder="e.g. Juan" />

            <x-ui.field
                name="middle_name"
                label="Middle Name"
                :value="$nameComponents['middle_name']"
                autocomplete="additional-name"
                placeholder="e.g. Santos"
                hint="Optional." />
        </div>

        <x-ui.field
            name="email"
            label="Email"
            type="email"
            :value="$user?->email"
            required
            placeholder="e.g. maria.cruz@djnrmhs.gov.ph"
            hint="Used to sign in. Must be unique." />

        <x-ui.field
            name="employee_id"
            label="Employee ID"
            :value="$user?->employee_id ?? 'Generated automatically after creation'"
            disabled
            hint="Assigned automatically by the system and cannot be changed." />

        <x-ui.field
            name="department"
            label="Department"
            type="select"
            :value="$user?->department"
            :options="$departments"
            placeholder="Select a department"
            required />

        <x-ui.field
            name="phone"
            label="Contact Number"
            type="tel"
            :value="$user?->phone"
            required
            inputmode="numeric"
            autocomplete="tel"
            minlength="11"
            maxlength="11"
            pattern="09[0-9]{9}"
            title="Enter exactly 11 digits beginning with 09."
            x-on:input="$el.value = $el.value.replace(/[^0-9]/g, '').slice(0, 11)"
            placeholder="09XXXXXXXXX"
            hint="Exactly 11 digits beginning with 09." />

        <x-ui.field
            name="role"
            label="Role"
            type="select"
            required
            x-model="role"
            hint="Decides every screen and action this account can reach.">
            @foreach ($roles as $role)
                <option value="{{ $role->value }}"
                        @selected(old('role', $user?->role?->value) === $role->value)>
                    {{ $role->label() }}
                </option>
            @endforeach
        </x-ui.field>

        @if ($isEdit)
            <x-ui.field
                name="status"
                label="Status"
                type="select"
                required
                :value="$user->status->value"
                :options="\App\Enums\UserStatus::options()"
                hint="Inactive accounts are signed out and cannot sign back in." />
        @endif

        <x-ui.field
            name="password"
            label="{{ $isEdit ? 'New Password' : 'Password' }}"
            type="password"
            :required="! $isEdit"
            autocomplete="new-password"
            minlength="8"
            pattern="(?=.*[a-z])(?=.*[A-Z])(?=.*[0-9])(?=.*[^A-Za-z0-9\s]).{8,}"
            title="{{ \App\Rules\PasswordStandard::REQUIREMENTS }}"
            x-model="password"
            hint="{{ $isEdit ? 'Leave blank to keep the current password. When changed, all requirements below apply.' : \App\Rules\PasswordStandard::REQUIREMENTS }}" />

        <x-ui.field
            name="password_confirmation"
            label="Confirm Password"
            type="password"
            :required="! $isEdit"
            autocomplete="new-password"
            x-model="passwordConfirmation" />
    </div>

    <div class="mt-4">
        <x-auth.password-requirements />
    </div>

    {{-- Reflects the selection above so the effect of the choice is visible
         before the form is submitted, not after. --}}
    <div class="mt-5 rounded-md border border-neutral-200 bg-neutral-50 px-4 py-3" x-show="detail" x-cloak>
        <p class="text-xs font-semibold uppercase tracking-wide text-neutral-500">This role can</p>
        <p class="mt-1 text-sm text-neutral-700" x-text="detail?.description"></p>
        <ul class="mt-2 flex flex-wrap gap-x-4 gap-y-1">
            <template x-for="permission in (detail?.permissions ?? [])" :key="permission">
                <li class="flex items-center gap-1.5 text-xs text-neutral-600">
                    <x-ui.icon name="check-circle" class="w-3.5 h-3.5 shrink-0 text-success-600" />
                    <span x-text="permission"></span>
                </li>
            </template>
        </ul>
    </div>
</div>
