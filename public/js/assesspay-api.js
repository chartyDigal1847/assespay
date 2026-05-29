/**
 * AssessPay API client
 */
(function () {
    const API = window.ASSESSPAY_API || '/api/v1';
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content;

    async function request(path, options = {}) {
        const res = await fetch(`${API}${path}`, {
            credentials: 'include',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrf,
                ...(options.headers || {}),
            },
            ...options,
        });
        const data = await res.json().catch(() => ({}));
        if (!res.ok) {
            throw new Error(data.message || data.error || res.statusText);
        }
        return data;
    }

    function showToast(msg, type) {
        let t = document.getElementById('ap-toast');
        if (!t) {
            t = document.createElement('div');
            t.id = 'ap-toast';
            t.className = 'ap-toast';
            t.setAttribute('role', 'status');
            document.body.appendChild(t);
        }
        t.textContent = msg;
        t.className = 'ap-toast is-visible' + (type === 'error' ? ' ap-toast--error' : '');
        clearTimeout(t._hideTimer);
        t._hideTimer = setTimeout(() => t.classList.remove('is-visible'), 4000);
    }

    const api = {
        getPayments: (q = '') => request(`/payments?${q}`),
        getBalances: (q = '') => request(`/balances?${q}`),
        getReceipts: (q = '') => request(`/receipts?${q}`),
        getAnalytics: () => request('/financial-analytics'),
        search: (term, type = 'all') =>
            request(`/search?q=${encodeURIComponent(term)}&type=${type}`),
        detectEnrolledStudents: () =>
            request('/enrolled-students'),
        createEnrolledAssessment: (body) =>
            request('/enrolled-students/assessment', { method: 'POST', body: JSON.stringify(body) }),
        submitPayment: (body) =>
            request('/payments', { method: 'POST', body: JSON.stringify(body) }),
        refreshBalanceCards: async () => {
            document.querySelectorAll('[data-balance-card]').forEach(async (el) => {
                try {
                    const res = await request('/balances?per_page=1');
                    const bal = res.data?.[0];
                    if (bal) {
                        el.textContent = `₱${Number(bal.current_balance).toLocaleString(undefined, { minimumFractionDigits: 2 })}`;
                    }
                } catch (e) {
                    console.warn('[AssessPay] balance refresh', e);
                }
            });
        },
        poll: (fn, ms = 15000) => {
            fn();
            return setInterval(fn, ms);
        },
        toast: showToast,
    };

    api.createAssessment = api.createEnrolledAssessment;
    window.AssessPay = api;

    if (window.ASSESSPAY_ROLE === 'cashier' || window.ASSESSPAY_ROLE === 'student') {
        window.AssessPay.poll(() => window.AssessPay.refreshBalanceCards(), 20000);
    }
})();
