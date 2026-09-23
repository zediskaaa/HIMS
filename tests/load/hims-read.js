import http from 'k6/http';
import { check, sleep } from 'k6';

const baseUrl = (__ENV.HIMS_BASE_URL || '').replace(/\/$/, '');
const users = JSON.parse(__ENV.HIMS_USERS_JSON || '[]');
const peak = Math.max(1, Number(__ENV.HIMS_PEAK_VUS || 25));
const searchTerm = encodeURIComponent(__ENV.HIMS_SEARCH_TERM || 'gloves');

if (!baseUrl || users.length < peak) {
    throw new Error('Set HIMS_BASE_URL and provide at least HIMS_PEAK_VUS distinct staff accounts in HIMS_USERS_JSON.');
}

export const options = {
    scenarios: {
        staff_reads: {
            executor: 'ramping-vus',
            startVUs: 1,
            stages: [
                { duration: '1m', target: peak },
                { duration: '3m', target: peak },
                { duration: '1m', target: 0 },
            ],
        },
    },
};

let signedIn = false;

function read(path, name) {
    const response = http.get(`${baseUrl}${path}`, {
        headers: { Accept: path.startsWith('/global-search') || path === '/dashboard/live' ? 'application/json' : 'text/html' },
        tags: { name },
    });
    check(response, { [`${name} returns 200`]: (result) => result.status === 200 });
}

export default function () {
    if (!signedIn) {
        const credentials = users[(__VU - 1) % users.length];
        const page = http.get(`${baseUrl}/login`, { tags: { name: 'login_page' } });
        const token = page.html().find('input[name="_token"]').first().attr('value');
        if (!check(page, { 'login form loaded': (result) => result.status === 200 && !!token })) return;

        const login = http.post(`${baseUrl}/login`, {
            _token: token,
            email: credentials.email,
            password: credentials.password,
        }, { redirects: 0, tags: { name: 'login' } });

        signedIn = check(login, {
            'login succeeded without MFA or expired password': (result) => result.status === 302
                && (result.headers.Location || '').includes('/dashboard'),
        });
        if (!signedIn) return;
    }

    read('/dashboard', 'dashboard');
    read('/dashboard/live', 'dashboard_live');
    read(`/global-search?query=${searchTerm}`, 'global_search');
    read('/inventory/items', 'inventory_items');
    read('/inventory/items?status=low_stock', 'inventory_items_filtered');
    read('/inventory/reports?period=30', 'reports_dashboard');
    read('/inventory/demand-forecast', 'demand_forecast');
    sleep(1);
}
