<!-- ===================== 全域可愛確認視窗 ===================== -->
<style>
#cute-confirm-overlay {
    display: none;
    position: fixed;
    inset: 0;
    z-index: 99999;
    background: rgba(0,0,0,0);
    backdrop-filter: blur(0px);
    -webkit-backdrop-filter: blur(0px);
    align-items: center;
    justify-content: center;
    transition: background .22s ease, backdrop-filter .22s ease, -webkit-backdrop-filter .22s ease;
}
#cute-confirm-overlay.show {
    display: flex;
    background: rgba(0,0,0,.48);
    backdrop-filter: blur(4px);
    -webkit-backdrop-filter: blur(4px);
}
#cute-confirm-box {
    background: var(--bs-body-bg, #fff);
    border-radius: 28px;
    padding: 30px 26px 22px;
    max-width: 290px;
    width: 88%;
    box-shadow: 0 16px 56px rgba(0,0,0,.22), 0 2px 8px rgba(0,0,0,.1);
    text-align: center;
    transform: scale(0.78) translateY(24px);
    opacity: 0;
    transition: transform .3s cubic-bezier(.34,1.56,.64,1), opacity .2s ease;
}
#cute-confirm-overlay.show #cute-confirm-box {
    transform: scale(1) translateY(0);
    opacity: 1;
}
#cute-confirm-icon {
    font-size: 2.8rem;
    line-height: 1;
    margin-bottom: 14px;
    display: block;
}
#cute-confirm-msg {
    font-size: 1rem;
    font-weight: 700;
    color: var(--bs-body-color);
    margin-bottom: 6px;
    line-height: 1.3;
}
#cute-confirm-sub {
    font-size: 0.8rem;
    color: var(--bs-secondary-color, #6c757d);
    margin-bottom: 22px;
    line-height: 1.45;
    min-height: 0;
}
.cute-confirm-btns {
    display: flex;
    gap: 10px;
}
#cute-confirm-cancel,
#cute-confirm-ok {
    flex: 1;
    border-radius: 50px;
    padding: 10px 0;
    font-size: 0.88rem;
    font-weight: 700;
    cursor: pointer;
    border: none;
    transition: filter .15s, transform .12s, box-shadow .15s;
}
#cute-confirm-cancel:hover { filter: brightness(.92); transform: scale(1.03); }
#cute-confirm-ok:hover    { filter: brightness(1.1);  transform: scale(1.03); }
#cute-confirm-cancel:active, #cute-confirm-ok:active { transform: scale(0.97); }
#cute-confirm-cancel {
    border: 2px solid var(--bs-border-color, #dee2e6);
    background: var(--bs-secondary-bg, #e9ecef);
    color: var(--bs-body-color, #212529);
}
#cute-confirm-ok {
    background: linear-gradient(135deg, #ef4444, #dc2626);
    color: #fff;
    box-shadow: 0 3px 12px rgba(239,68,68,.38);
}
</style>

<div id="cute-confirm-overlay">
    <div id="cute-confirm-box">
        <span id="cute-confirm-icon">🗑️</span>
        <div id="cute-confirm-msg">確定要刪除嗎？</div>
        <div id="cute-confirm-sub"></div>
        <div class="cute-confirm-btns">
            <button id="cute-confirm-cancel">取消</button>
            <button id="cute-confirm-ok">刪除</button>
        </div>
    </div>
</div>

<script>
(function () {
    'use strict';
    var _callback = null;
    var overlay   = document.getElementById('cute-confirm-overlay');
    var box       = document.getElementById('cute-confirm-box');
    var elIcon    = document.getElementById('cute-confirm-icon');
    var elMsg     = document.getElementById('cute-confirm-msg');
    var elSub     = document.getElementById('cute-confirm-sub');
    var elOk      = document.getElementById('cute-confirm-ok');
    var elCancel  = document.getElementById('cute-confirm-cancel');

    function show() {
        overlay.style.display = 'flex';
        /* 讓瀏覽器渲染一幀後再加 .show，觸發 transition */
        requestAnimationFrame(function () {
            requestAnimationFrame(function () {
                overlay.classList.add('show');
            });
        });
    }

    function hide() {
        overlay.classList.remove('show');
        /* 等 transition 結束後才 display:none */
        setTimeout(function () {
            if (!overlay.classList.contains('show')) {
                overlay.style.display = 'none';
            }
        }, 300);
    }

    elOk.addEventListener('click', function () {
        hide();
        if (_callback) { var cb = _callback; _callback = null; cb(true); }
    });
    elCancel.addEventListener('click', function () {
        hide();
        if (_callback) { var cb = _callback; _callback = null; cb(false); }
    });
    overlay.addEventListener('click', function (e) {
        if (e.target === overlay) {
            hide();
            if (_callback) { var cb = _callback; _callback = null; cb(false); }
        }
    });
    document.addEventListener('keydown', function (e) {
        if (overlay.classList.contains('show') && e.key === 'Escape') {
            hide();
            if (_callback) { var cb = _callback; _callback = null; cb(false); }
        }
    });

    /**
     * window.cuteConfirm(opts, callback)
     *
     * opts (string | object):
     *   msg     — 主要文字
     *   sub     — 補充說明（灰色小字）
     *   icon    — emoji，預設 🗑️
     *   okText  — 確認按鈕文字，預設「刪除」
     *   okColor — 按鈕漸層 CSS（可選）
     *
     * callback(ok: boolean)
     */
    window.cuteConfirm = function (opts, callback) {
        if (typeof opts === 'string') opts = { msg: opts };
        elIcon.textContent = opts.icon   || '🗑️';
        elMsg.textContent  = opts.msg    || '確定要刪除嗎？';
        elSub.textContent  = opts.sub    != null ? opts.sub : '此操作無法復原。';
        elOk.textContent   = opts.okText || '刪除';
        elOk.style.background = opts.okColor || 'linear-gradient(135deg,#ef4444,#dc2626)';
        elOk.style.boxShadow  = '';
        _callback = callback || null;
        show();
    };

    /* ── 攔截帶有 data-cute-confirm 屬性的 <form> 送出 ── */
    document.addEventListener('submit', function (e) {
        var form = e.target;
        var msg  = form.getAttribute('data-cute-confirm');
        if (!msg) return;
        if (form._cuteOk) { form._cuteOk = false; return; }   // 已確認，放行
        e.preventDefault();
        e.stopImmediatePropagation();
        var icon = form.getAttribute('data-cute-icon') || '🗑️';
        var sub  = form.getAttribute('data-cute-sub');
        if (sub === null) sub = '此操作無法復原。';
        window.cuteConfirm({ msg: msg, sub: sub, icon: icon }, function (ok) {
            if (!ok) return;
            form._cuteOk = true;
            form.submit();
        });
    }, true);   /* capture = true，確保在 onsubmit 之前攔截 */

})();
</script>

<!-- ===================== 全域可愛檢舉視窗 ===================== -->
<style>
#cute-report-overlay {
    display: none;
    position: fixed;
    inset: 0;
    z-index: 99998;
    background: rgba(0,0,0,0);
    backdrop-filter: blur(0px);
    -webkit-backdrop-filter: blur(0px);
    align-items: center;
    justify-content: center;
    transition: background .22s ease, backdrop-filter .22s ease;
}
#cute-report-overlay.show {
    display: flex;
    background: rgba(0,0,0,.48);
    backdrop-filter: blur(4px);
    -webkit-backdrop-filter: blur(4px);
}
#cute-report-box {
    background: var(--bs-body-bg, #fff);
    border-radius: 28px;
    padding: 26px 22px 20px;
    max-width: 300px;
    width: 88%;
    box-shadow: 0 16px 56px rgba(0,0,0,.22), 0 2px 8px rgba(0,0,0,.1);
    transform: scale(0.78) translateY(24px);
    opacity: 0;
    transition: transform .3s cubic-bezier(.34,1.56,.64,1), opacity .2s ease;
}
#cute-report-overlay.show #cute-report-box {
    transform: scale(1) translateY(0);
    opacity: 1;
}
#cute-report-header {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 16px;
}
#cute-report-header-icon { font-size: 1.4rem; line-height: 1; }
#cute-report-title {
    flex: 1;
    font-size: 1rem;
    font-weight: 700;
    color: var(--bs-body-color);
}
#cute-report-close {
    background: none;
    border: none;
    font-size: 1.2rem;
    line-height: 1;
    color: var(--bs-secondary-color, #6c757d);
    cursor: pointer;
    padding: 2px 6px;
    border-radius: 50%;
    transition: background .15s;
}
#cute-report-close:hover { background: var(--bs-secondary-bg, #e9ecef); }
#cute-report-sub {
    font-size: 0.8rem;
    color: var(--bs-secondary-color, #6c757d);
    margin-bottom: 14px;
}
.cute-report-reason {
    display: flex;
    align-items: center;
    gap: 10px;
    width: 100%;
    padding: 11px 14px;
    margin-bottom: 8px;
    border-radius: 14px;
    border: none;
    background: var(--bs-secondary-bg, #f1f3f5);
    color: var(--bs-body-color);
    font-size: 0.9rem;
    font-weight: 600;
    cursor: pointer;
    text-align: left;
    transition: background .15s, transform .12s;
}
.cute-report-reason:last-child { margin-bottom: 0; }
.cute-report-reason:hover {
    background: var(--bs-tertiary-bg, #e2e6ea);
    transform: translateX(3px);
}
.cute-report-reason:active { transform: scale(0.98); }
.cute-report-reason-icon { font-size: 1.1rem; flex-shrink: 0; }
</style>

<div id="cute-report-overlay">
    <div id="cute-report-box">
        <div id="cute-report-header">
            <span id="cute-report-header-icon">🚩</span>
            <span id="cute-report-title">檢舉</span>
            <button id="cute-report-close">✕</button>
        </div>
        <div id="cute-report-sub">請選擇檢舉原因：</div>
        <div id="cute-report-reasons">
            <button class="cute-report-reason" data-reason="騷擾內容">
                <span class="cute-report-reason-icon">👤</span> 騷擾內容
            </button>
            <button class="cute-report-reason" data-reason="詐騙">
                <span class="cute-report-reason-icon">⚠️</span> 詐騙
            </button>
        </div>
    </div>
</div>

<script>
(function () {
    var overlay  = document.getElementById('cute-report-overlay');
    var elTitle  = document.getElementById('cute-report-title');
    var elClose  = document.getElementById('cute-report-close');
    var _type = '', _id = 0;

    function showReport() {
        overlay.style.display = 'flex';
        requestAnimationFrame(function () {
            requestAnimationFrame(function () { overlay.classList.add('show'); });
        });
    }
    function hideReport() {
        overlay.classList.remove('show');
        setTimeout(function () {
            if (!overlay.classList.contains('show')) overlay.style.display = 'none';
        }, 300);
    }

    elClose.addEventListener('click', hideReport);
    overlay.addEventListener('click', function (e) { if (e.target === overlay) hideReport(); });
    document.addEventListener('keydown', function (e) {
        if (overlay.classList.contains('show') && e.key === 'Escape') hideReport();
    });

    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.cute-report-reason');
        if (!btn || !overlay.classList.contains('show')) return;
        var reason = btn.getAttribute('data-reason');
        hideReport();
        $.post('/api/report.php', { type: _type, target_id: _id, reason: reason }, function (res) {
            window.cuteToast({ type: res.success ? 'success' : 'error', icon: res.success ? '🚩' : '❌', msg: res.message });
        }, 'json').fail(function () {
            window.cuteToast({ type: 'error', msg: '網路錯誤，請稍後再試' });
        });
    });

    /**
     * window.cuteReport(type, id, title)
     * type  — 'form' | 'comment'
     * id    — target id
     * title — 顯示標題，如「檢舉表單」
     */
    window.cuteReport = function (type, id, title) {
        _type  = type;
        _id    = id;
        elTitle.textContent = title || '檢舉';
        showReport();
    };
})();
</script>

<!-- ===================== 全域可愛 Toast 通知 ===================== -->
<style>
#cute-toast {
    position: fixed;
    top: 24px;
    left: 50%;
    transform: translateX(-50%) translateY(-20px);
    z-index: 999999;
    min-width: 220px;
    max-width: min(420px, 88vw);
    padding: 14px 20px;
    border-radius: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 0.92rem;
    font-weight: 600;
    box-shadow: 0 8px 32px rgba(0,0,0,.18), 0 2px 8px rgba(0,0,0,.10);
    opacity: 0;
    pointer-events: none;
    transition: opacity .25s ease, transform .35s cubic-bezier(.34,1.56,.64,1);
    white-space: normal;
    word-break: break-word;
}
#cute-toast.show {
    opacity: 1;
    transform: translateX(-50%) translateY(0);
    pointer-events: auto;
}
#cute-toast.type-success {
    background: linear-gradient(135deg, #22c55e, #16a34a);
    color: #fff;
    box-shadow: 0 0 16px rgba(34,197,94,.45), 0 4px 20px rgba(0,0,0,.15);
}
#cute-toast.type-error {
    background: linear-gradient(135deg, #ef4444, #dc2626);
    color: #fff;
    box-shadow: 0 0 16px rgba(239,68,68,.45), 0 4px 20px rgba(0,0,0,.15);
}
#cute-toast.type-info {
    background: linear-gradient(135deg, #3b82f6, #2563eb);
    color: #fff;
    box-shadow: 0 0 16px rgba(59,130,246,.45), 0 4px 20px rgba(0,0,0,.15);
}
#cute-toast-icon { font-size: 1.2rem; line-height: 1; flex-shrink: 0; }
</style>

<div id="cute-toast">
    <span id="cute-toast-icon">✅</span>
    <span id="cute-toast-msg"></span>
</div>

<script>
(function () {
    var toast    = document.getElementById('cute-toast');
    var toastIcon = document.getElementById('cute-toast-icon');
    var toastMsg  = document.getElementById('cute-toast-msg');
    var _timer   = null;

    /**
     * window.cuteToast(opts)
     * opts.type  — 'success' | 'error' | 'info'（預設 success）
     * opts.icon  — emoji（選填，有預設）
     * opts.msg   — 訊息文字
     * opts.duration — 顯示毫秒（預設 2800）
     */
    window.cuteToast = function (opts) {
        if (typeof opts === 'string') opts = { msg: opts };
        var type = opts.type || 'success';
        var icons = { success: '✅', error: '❌', info: 'ℹ️' };
        toastIcon.textContent = opts.icon || icons[type] || '✅';
        toastMsg.textContent  = opts.msg  || '';
        toast.className = 'show type-' + type;
        if (_timer) clearTimeout(_timer);
        _timer = setTimeout(function () {
            toast.classList.remove('show');
        }, opts.duration || 2800);
    };

    toast.addEventListener('click', function () {
        toast.classList.remove('show');
        if (_timer) clearTimeout(_timer);
    });
})();
</script>
