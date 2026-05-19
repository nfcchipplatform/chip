/**
 * PONNU — app.js
 * Vanilla JS ユーティリティ
 */

'use strict';

/* ===== CSRF トークンを meta タグから取得 ===== */
function getCsrfToken() {
    const meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.getAttribute('content') : '';
}

/* ===== JSON fetch ラッパー ===== */
async function apiFetch(url, options = {}) {
    const defaults = {
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': getCsrfToken(),
            'X-Requested-With': 'XMLHttpRequest',
        },
        credentials: 'same-origin',
    };
    const merged = Object.assign({}, defaults, options, {
        headers: Object.assign({}, defaults.headers, options.headers || {}),
    });
    const res = await fetch(url, merged);
    if (!res.ok) {
        const text = await res.text();
        throw new Error(text || `HTTP ${res.status}`);
    }
    return res.json();
}

/* ===== フォーム送信時に CSRF トークンを hidden フィールドとして追加 ===== */
document.addEventListener('DOMContentLoaded', () => {
    // data-csrf-form 属性が付いたフォームすべてに CSRF フィールドを追加
    document.querySelectorAll('form[data-csrf-form]').forEach((form) => {
        if (!form.querySelector('input[name="_csrf"]')) {
            const input = document.createElement('input');
            input.type  = 'hidden';
            input.name  = '_csrf';
            input.value = getCsrfToken();
            form.prepend(input);
        }
    });

    // モバイルナビゲーション トグル
    const menuBtn  = document.getElementById('menu-toggle');
    const mobileNav = document.getElementById('mobile-nav');
    if (menuBtn && mobileNav) {
        menuBtn.addEventListener('click', () => {
            const isOpen = mobileNav.classList.toggle('hidden');
            menuBtn.setAttribute('aria-expanded', String(!isOpen));
        });
    }

    // フラッシュメッセージの自動非表示（3秒後）
    document.querySelectorAll('[data-flash-auto-hide]').forEach((el) => {
        setTimeout(() => {
            el.style.transition = 'opacity 0.5s ease';
            el.style.opacity = '0';
            setTimeout(() => el.remove(), 500);
        }, 3000);
    });
});

/* ===== 確認ダイアログ付き削除ボタン ===== */
document.addEventListener('click', (e) => {
    const btn = e.target.closest('[data-confirm]');
    if (!btn) return;
    const msg = btn.getAttribute('data-confirm') || '本当に削除しますか？';
    if (!confirm(msg)) {
        e.preventDefault();
        e.stopPropagation();
    }
});

/* ===== 文字数カウンター ===== */
document.querySelectorAll('[data-max-length]').forEach((input) => {
    const max     = parseInt(input.getAttribute('data-max-length'), 10);
    const counter = document.createElement('span');
    counter.className = 'text-xs text-gray-400 block text-right mt-1';
    input.after(counter);

    const update = () => {
        const remaining = max - input.value.length;
        counter.textContent = `${input.value.length} / ${max}`;
        counter.classList.toggle('text-red-500', remaining < 0);
    };
    input.addEventListener('input', update);
    update();
});
