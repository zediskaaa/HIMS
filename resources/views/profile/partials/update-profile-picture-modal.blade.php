<div
    x-data="{
        previewUrl: null,
        fileName: '',
        fileSize: '',
        fileError: '',
        handleFileSelect(event) {
            const files = event.dataTransfer ? event.dataTransfer.files : event.target.files;
            const file = files ? files[0] : null;
            if (!file) return;
            this.fileError = '';

            if (!file.type.startsWith('image/')) {
                this.fileError = 'Please select a valid image file.';
                this.clearPreview();
                return;
            }
            if (file.size > 3 * 1024 * 1024) {
                this.fileError = 'The selected image exceeds the 3 MB maximum size limit.';
                this.clearPreview();
                return;
            }

            this.fileName = file.name;
            this.fileSize = (file.size / 1024).toFixed(0) + ' KB';
            this.previewUrl = URL.createObjectURL(file);
        },
        clearPreview() {
            this.previewUrl = null;
            this.fileName = '';
            this.fileSize = '';
            if (this.$refs.avatarInput) {
                this.$refs.avatarInput.value = '';
            }
        }
    }"
    @if ($errors->has('avatar'))
        x-init="$nextTick(() => { $dispatch('open-modal', 'update-profile-picture') })"
    @endif
>
    <x-ui.modal name="update-profile-picture" :title="__('Profile Picture')" maxWidth="md">
        @if ($errors->has('avatar'))
            <x-ui.alert variant="danger" title="Upload failed" dismissible class="mb-4">
                <ul class="list-inside list-disc space-y-1">
                    @foreach ($errors->get('avatar') as $message)
                        <li>{{ $message }}</li>
                    @endforeach
                </ul>
            </x-ui.alert>
        @endif

        {{-- Client validation error alert --}}
        <div x-show="fileError" x-cloak class="mb-4">
            <x-ui.alert variant="danger" title="Invalid file">
                <span x-text="fileError"></span>
            </x-ui.alert>
        </div>

        <div class="flex flex-col items-center px-2 py-2 text-center">
            {{-- Interactive Clickable Circular Avatar / Upload Dropzone --}}
            <div
                @click="$refs.avatarInput.click()"
                @dragover.prevent
                @drop.prevent="handleFileSelect($event)"
                class="group relative mb-3 cursor-pointer rounded-full focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-neutral-900"
                role="button"
                tabindex="0"
                title="{{ __('Click to choose a photo') }}"
                aria-label="{{ __('Click to choose a photo') }}"
                @keydown.enter="$refs.avatarInput.click()"
                @keydown.space.prevent="$refs.avatarInput.click()"
            >
                {{-- Live preview when file selected --}}
                <template x-if="previewUrl">
                    <img
                        :src="previewUrl"
                        alt="New avatar preview"
                        class="h-28 w-28 rounded-full object-cover shadow-md ring-4 ring-primary-500 transition-opacity group-hover:opacity-90 sm:h-32 sm:w-32"
                    />
                </template>

                {{-- Current avatar when not previewing --}}
                <div x-show="!previewUrl">
                    <x-ui.avatar :user="$user" size="xl" class="!h-28 !w-28 !text-3xl shadow-md ring-4 ring-neutral-200 transition-all group-hover:ring-primary-400 dark:ring-neutral-700 sm:!h-32 sm:!w-32" />
                </div>

                {{-- Hover overlay inside avatar circle --}}
                <div class="absolute inset-0 rounded-full bg-neutral-900/50 opacity-0 group-hover:opacity-100 flex flex-col items-center justify-center text-white transition-opacity" aria-hidden="true">
                    <x-ui.icon name="camera" class="h-7 w-7 mb-1 drop-shadow-xs" />
                    <span class="text-[11px] font-semibold tracking-wide" x-text="previewUrl ? '{{ __('Change Photo') }}' : '{{ __('Upload Photo') }}'">
                        {{ __('Upload Photo') }}
                    </span>
                </div>

                {{-- Floating camera badge when not previewing --}}
                <span
                    x-show="!previewUrl"
                    class="absolute bottom-0 right-0 flex h-8 w-8 items-center justify-center rounded-full bg-white text-neutral-700 shadow-md ring-1 ring-neutral-300 transition-all group-hover:bg-primary-50 group-hover:text-primary-600 group-hover:ring-primary-400 dark:bg-neutral-800 dark:text-neutral-200 dark:ring-neutral-600 dark:group-hover:bg-primary-950 dark:group-hover:text-primary-300"
                >
                    <x-ui.icon name="camera" class="h-4 w-4" />
                </span>

                {{-- Preview badge when file selected --}}
                <span
                    x-show="previewUrl"
                    x-cloak
                    class="absolute -bottom-1 -right-1 rounded-full bg-primary-600 px-2.5 py-0.5 text-[10px] font-bold text-white shadow-xs"
                >
                    PREVIEW
                </span>
            </div>

            <h3 class="text-base font-semibold text-neutral-900 dark:text-neutral-100" x-text="previewUrl ? '{{ __('Ready to save new picture?') }}' : '{{ $user->hasAvatar() ? __('Change your profile picture') : __('Add a profile picture') }}'">
                {{ $user->hasAvatar() ? __('Change your profile picture') : __('Add a profile picture') }}
            </h3>

            <p class="mt-1 max-w-sm text-xs text-neutral-600 dark:text-neutral-300">
                <span x-show="!previewUrl">{{ __('Upload a photo to personalize your avatar across the navigation panel and user directory. JPG, PNG, GIF, WebP, and BMP files up to 3 MB are supported.') }}</span>
                <span x-show="previewUrl" x-cloak>{{ __('Click Save Picture to apply your new photo, or click the photo again to pick a different one.') }}</span>
            </p>

            {{-- New file selected banner --}}
            <div
                x-show="previewUrl"
                x-cloak
                class="mt-3 flex w-full max-w-sm min-w-0 items-center gap-2 rounded-lg border border-primary-100 bg-primary-50/60 px-3 py-1.5 text-xs text-primary-900 dark:border-primary-800 dark:bg-primary-950/50 dark:text-primary-200"
            >
                <span class="min-w-0 flex-1 truncate font-medium" x-text="fileName" :title="fileName"></span>
                <span class="shrink-0 font-mono text-primary-700 dark:text-primary-300" x-text="'(' + fileSize + ')'"></span>
            </div>

            {{-- Form for upload / save --}}
            <form
                method="POST"
                action="{{ route('profile.avatar.update') }}"
                enctype="multipart/form-data"
                class="w-full"
            >
                @csrf
                <input
                    x-ref="avatarInput"
                    type="file"
                    name="avatar"
                    id="avatar"
                    accept="image/*"
                    class="sr-only"
                    @change="handleFileSelect($event)"
                />

                {{-- Confirm Save buttons (ONLY visible when new preview selected) --}}
                <div x-show="previewUrl" x-cloak class="mt-5 flex w-full flex-col gap-2 sm:flex-row sm:items-center sm:justify-center">
                    <x-ui.button
                        type="submit"
                        size="sm"
                        icon="check"
                        class="w-full sm:w-auto"
                        data-loading-text="Saving picture..."
                    >
                        {{ __('Save Picture') }}
                    </x-ui.button>

                    <x-ui.button
                        type="button"
                        variant="secondary"
                        size="sm"
                        @click="clearPreview()"
                        class="w-full sm:w-auto"
                    >
                        {{ __('Cancel') }}
                    </x-ui.button>
                </div>
            </form>

            {{-- Remove Profile Picture Action (only when avatar currently exists and not previewing) --}}
            @if ($user->hasAvatar())
                <div x-show="!previewUrl" class="mt-4 flex w-full justify-center border-t border-neutral-100 pt-3 dark:border-neutral-800">
                    <form
                        method="POST"
                        action="{{ route('profile.avatar.destroy') }}"
                        data-confirm-title="Remove profile picture?"
                        data-confirm-message="Are you sure you want to remove your profile picture and return to using your initials avatar?"
                        data-confirm-label="Remove Picture"
                    >
                        @csrf
                        @method('DELETE')
                        <button
                            type="submit"
                            class="inline-flex items-center gap-1.5 rounded p-1 text-xs font-medium text-danger-600 transition-colors hover:text-danger-700 hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-danger-500 dark:text-danger-400 dark:hover:text-danger-300"
                        >
                            <x-ui.icon name="x-mark" class="h-3.5 w-3.5" />
                            {{ __('Remove Picture') }}
                        </button>
                    </form>
                </div>
            @endif
        </div>
    </x-ui.modal>
</div>
