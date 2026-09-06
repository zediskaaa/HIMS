<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-[var(--text)]">
            Storage Locations
        </h2>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
            @if (session('success'))
                <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
                    {{ session('success') }}
                </div>
            @endif

            <div class="rounded-2xl border border-[var(--border)] bg-[var(--card)] p-6 shadow-sm">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h3 class="text-lg font-semibold text-[var(--text)]">Storage Locations</h3>
                        <p class="text-sm text-[var(--muted)]">Manage warehouse zones, bins, racks, and storage points.</p>
                    </div>
                    @can(\App\Enums\Permission::ManageLocations->value)
                        <button type="button" onclick="document.getElementById('add-location-form').classList.toggle('hidden')" class="rounded-xl bg-[var(--primary)] px-4 py-2 text-sm font-semibold text-white transition hover:bg-[var(--primary-dark)]">
                            Add Location
                        </button>
                    @endcan
                </div>

                @can(\App\Enums\Permission::ManageLocations->value)
                <div id="add-location-form" class="mt-5 hidden rounded-2xl border border-[var(--border)] bg-[var(--background)]/50 p-5">
                    <form method="POST" action="{{ route('inventory.storage-locations.store') }}" class="grid gap-4 md:grid-cols-2">
                        @csrf
                        <div>
                            <label class="block text-sm font-medium text-[var(--muted)]">Name</label>
                            <input type="text" name="name" required class="mt-1 w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-[var(--muted)]">Code</label>
                            <input type="text" name="code" required class="mt-1 w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-[var(--muted)]">Zone</label>
                            <input type="text" name="zone" class="mt-1 w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-[var(--muted)]">Capacity</label>
                            <input type="number" name="capacity" class="mt-1 w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-[var(--muted)]">Status</label>
                            <select name="status" class="mt-1 w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-[var(--muted)]">Description</label>
                            <input type="text" name="description" class="mt-1 w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2" />
                        </div>
                        <div class="md:col-span-2">
                            <button type="submit" class="rounded-xl bg-[var(--primary)] px-4 py-2 text-sm font-semibold text-white transition hover:bg-[var(--primary-dark)]">Save Location</button>
                        </div>
                    </form>
                </div>
                @endcan

                <div class="mt-6 overflow-x-auto">
                    <table class="min-w-full divide-y divide-[var(--border)] text-sm">
                        <thead class="bg-[var(--background)]">
                            <tr>
                                <th class="px-3 py-2 text-left font-semibold text-[var(--muted)]">Name</th>
                                <th class="px-3 py-2 text-left font-semibold text-[var(--muted)]">Code</th>
                                <th class="px-3 py-2 text-left font-semibold text-[var(--muted)]">Zone</th>
                                <th class="px-3 py-2 text-left font-semibold text-[var(--muted)]">Capacity</th>
                                <th class="px-3 py-2 text-left font-semibold text-[var(--muted)]">Status</th>
                            </tr>
                        </thead>
                        <tbody id="storage-locations-table-body" class="divide-y divide-[var(--border)]">
                            @forelse ($locations as $location)
                                <tr>
                                    <td class="px-3 py-2">{{ $location->name }}</td>
                                    <td class="px-3 py-2">{{ $location->code }}</td>
                                    <td class="px-3 py-2">{{ $location->zone }}</td>
                                    <td class="px-3 py-2">{{ $location->capacity }}</td>
                                    <td class="px-3 py-2">{{ $location->status }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-3 py-4 text-[var(--muted)]">No storage locations yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                    <x-ui.loader id="locations-api-status" size="sm" label="Loading storage locations from API..." class="mt-3 text-sm text-[var(--muted)]" />
                </div>
            </div>
        </div>
    </div>

    <script>
        async function loadStorageLocationsFromApi() {
            const status = document.getElementById('locations-api-status');
            const tbody = document.getElementById('storage-locations-table-body');

            try {
                const csrfResponse = await fetch('/sanctum/csrf-cookie', { credentials: 'same-origin' });
                if (!csrfResponse.ok) {
                    throw new Error(`CSRF cookie request failed with status ${csrfResponse.status}`);
                }

                const response = await fetch('/api/v1/storage-locations?per_page=100', {
                    credentials: 'include',
                    headers: {
                        'Accept': 'application/json'
                    }
                });

                if (!response.ok) {
                    const body = await response.text();
                    console.error('Storage locations API error body:', body);
                    throw new Error(`API request failed with status ${response.status}: ${body}`);
                }

                const payload = await response.json();
                const locations = payload.data || [];

                if (locations.length === 0) {
                    tbody.innerHTML = `<tr><td colspan="5" class="px-3 py-4 text-[var(--muted)]">No storage locations found via API.</td></tr>`;
                } else {
                    tbody.innerHTML = locations.map(location => `
                        <tr>
                            <td class="px-3 py-2">${location.name}</td>
                            <td class="px-3 py-2">${location.code}</td>
                            <td class="px-3 py-2">${location.zone || '—'}</td>
                            <td class="px-3 py-2">${location.capacity ?? '—'}</td>
                            <td class="px-3 py-2">${location.status || 'unknown'}</td>
                        </tr>
                    `).join('');
                }

                status.textContent = 'Storage locations loaded from API.';
            } catch (error) {
                console.error('Storage locations load failed:', error);
                status.textContent = `Unable to load storage locations from API (${error.message}). Check console for details.`;
            }
        }

        document.addEventListener('DOMContentLoaded', loadStorageLocationsFromApi);
    </script>
</x-app-layout>
