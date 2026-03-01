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
