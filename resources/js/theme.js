/**
 * HIMS Client-Side Theme & Dark Mode Manager
 *
 * Supports 'light', 'dark', and 'system' modes with automatic persistence to
 * localStorage, zero-flicker synchronization, OS preference reactivity, and
 * reactive Alpine.js store binding.
 */

const STORAGE_KEY = 'hims_theme';

const getStoredPreference = () => {
    try {
        return localStorage.getItem(STORAGE_KEY) || 'system';
    } catch {
        return 'system';
    }
};

const getSystemPrefersDark = () => {
    return typeof window !== 'undefined'
        && window.matchMedia
        && window.matchMedia('(prefers-color-scheme: dark)').matches;
};

const resolveEffectiveTheme = (preference) => {
    if (preference === 'dark') return 'dark';
    if (preference === 'light') return 'light';
    return getSystemPrefersDark() ? 'dark' : 'light';
};

const applyThemeToDOM = (effectiveTheme) => {
    if (typeof document === 'undefined') return;
    const isDark = effectiveTheme === 'dark';
    document.documentElement.classList.toggle('dark', isDark);
    document.documentElement.setAttribute('data-theme', effectiveTheme);
    document.documentElement.style.colorScheme = effectiveTheme;
};

export const himsTheme = {
    preference: getStoredPreference(),
    effective: resolveEffectiveTheme(getStoredPreference()),

    get isDark() {
        return this.effective === 'dark';
    },

    set(mode) {
        if (!['light', 'dark', 'system'].includes(mode)) return;
        this.preference = mode;
        try {
            localStorage.setItem(STORAGE_KEY, mode);
        } catch {
            // Guard against restricted storage
        }
        this.effective = resolveEffectiveTheme(mode);
        applyThemeToDOM(this.effective);

        window.dispatchEvent(new CustomEvent('hims:theme-changed', {
            detail: {
                preference: this.preference,
                effective: this.effective,
                isDark: this.isDark,
            },
        }));
    },

    toggle() {
        const next = this.effective === 'dark' ? 'light' : 'dark';
        this.set(next);
    },

    init() {
        this.preference = getStoredPreference();
        this.effective = resolveEffectiveTheme(this.preference);
        applyThemeToDOM(this.effective);

        if (typeof window !== 'undefined' && window.matchMedia) {
            const mediaQuery = window.matchMedia('(prefers-color-scheme: dark)');
            const handleMediaChange = () => {
                if (this.preference === 'system') {
                    this.effective = resolveEffectiveTheme('system');
                    applyThemeToDOM(this.effective);
                    window.dispatchEvent(new CustomEvent('hims:theme-changed', {
                        detail: {
                            preference: 'system',
                            effective: this.effective,
                            isDark: this.isDark,
                        },
                    }));
                }
            };

            if (mediaQuery.addEventListener) {
                mediaQuery.addEventListener('change', handleMediaChange);
            } else if (mediaQuery.addListener) {
                mediaQuery.addListener(handleMediaChange);
            }
        }
    },
};

// Expose globally
if (typeof window !== 'undefined') {
    window.himsTheme = himsTheme;
}

export const registerThemeWithAlpine = (Alpine) => {
    Alpine.store('theme', {
        preference: himsTheme.preference,
        effective: himsTheme.effective,
        get isDark() {
            return this.effective === 'dark';
        },
        set(mode) {
            himsTheme.set(mode);
            this.preference = himsTheme.preference;
            this.effective = himsTheme.effective;
        },
        toggle() {
            himsTheme.toggle();
            this.preference = himsTheme.preference;
            this.effective = himsTheme.effective;
        },
    });

    window.addEventListener('hims:theme-changed', (event) => {
        const store = Alpine.store('theme');
        if (store) {
            store.preference = event.detail.preference;
            store.effective = event.detail.effective;
        }
    });
};
