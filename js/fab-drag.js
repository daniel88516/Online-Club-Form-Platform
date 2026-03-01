/**
 * FAB 按鈕拖曳定位
 * 位置以視窗比例 (x, y) 儲存於 localStorage
 */
(function () {
    var KEY = 'fab_positions';

    // 預設位置（視窗比例 0~1）
    var DEFAULTS = {
        fab:  { x: 0.03, y: 0.86 },   // 左下
        chat: { x: 0.92, y: 0.86 }    // 右下
    };

    function load() {
        try { return JSON.parse(localStorage.getItem(KEY)) || {}; }
        catch (e) { return {}; }
    }

    function save(d) { localStorage.setItem(KEY, JSON.stringify(d)); }

    // 套用位置到實際元素
    function applyToEl(el, x, y, size) {
        var maxX = (window.innerWidth  - size) / window.innerWidth;
        var maxY = (window.innerHeight - size) / window.innerHeight;
        x = Math.max(0, Math.min(maxX, x));
        y = Math.max(0, Math.min(maxY, y));
        el.style.left   = (x * 100) + '%';
        el.style.top    = (y * 100) + '%';
        el.style.right  = 'auto';
        el.style.bottom = 'auto';
    }

    /**
     * 讓元素可拖曳
     * @param {Element} el      - 要移動位置的元素
     * @param {string}  key     - localStorage 鍵名（'fab' | 'chat'）
     * @param {number}  size    - 元素尺寸 px（用於邊界計算）
     * @param {Element} [handle] - 拖曳觸發區域，預設與 el 相同
     *                            （聊天按鈕傳入 #chat-toggle-btn，避免面板內部操作觸發拖曳）
     */
    function makeDraggable(el, key, size, handle) {
        var handleEl = handle || el;
        var startX, startY, startElLeft, startElTop, moved = false;
        var origTransition = '';

        function start(e) {
            // 阻止瀏覽器原生拖曳行為（<a> 連結拖曳、文字選取拖曳）
            if (!e.touches) e.preventDefault();
            var p = e.touches ? e.touches[0] : e;
            startX = p.clientX; startY = p.clientY;
            var r = el.getBoundingClientRect();
            startElLeft = r.left; startElTop = r.top;
            moved = false;
            // 關閉 transition 避免跟手延遲
            origTransition = el.style.transition;
            el.style.transition = 'none';
            handleEl.style.cursor = 'grabbing';
            document.addEventListener('mousemove', move);
            document.addEventListener('mouseup',   end);
            document.addEventListener('touchmove', move, { passive: false });
            document.addEventListener('touchend',  end);
        }

        function move(e) {
            var p = e.touches ? e.touches[0] : e;
            var dx = p.clientX - startX, dy = p.clientY - startY;
            if (!moved && dx * dx + dy * dy < 36) return;
            moved = true;
            if (e.cancelable) e.preventDefault();
            applyToEl(el,
                (startElLeft + dx) / window.innerWidth,
                (startElTop  + dy) / window.innerHeight,
                size
            );
        }

        function end() {
            document.removeEventListener('mousemove', move);
            document.removeEventListener('mouseup',   end);
            document.removeEventListener('touchmove', move);
            document.removeEventListener('touchend',  end);
            // 恢復 transition 與 cursor
            el.style.transition = origTransition;
            handleEl.style.cursor = '';
            if (!moved) return;
            // 通知其他元件（如聊天 toggle）本次是拖曳不是 click
            window._fabLastDragEnd = Date.now();
            var r = el.getBoundingClientRect();
            var pos = load();
            pos[key] = { x: r.left / window.innerWidth, y: r.top / window.innerHeight };
            save(pos);
        }

        // 有拖曳時阻止 <a> 跳頁 / button 觸發
        handleEl.addEventListener('click', function (e) {
            if (moved) { e.preventDefault(); moved = false; }
        });
        // 阻止瀏覽器原生 HTML drag（<a> 標籤會產生連結幽靈圖）
        el.addEventListener('dragstart',       function (e) { e.preventDefault(); });
        handleEl.addEventListener('dragstart', function (e) { e.preventDefault(); });

        handleEl.addEventListener('mousedown',  start);
        handleEl.addEventListener('touchstart', start, { passive: true });
    }

    // 初始化
    document.addEventListener('DOMContentLoaded', function () {
        var pos = load();

        // 建立新表單 FAB — 元素本身即為 handle
        var fab = document.querySelector('.fab-btn');
        if (fab) {
            var fp = pos.fab || DEFAULTS.fab;
            applyToEl(fab, fp.x, fp.y, 72);
            makeDraggable(fab, 'fab', 72);
        }

        // 聊天 Widget — 以 toggle 按鈕作為 handle，移動整個 #chat-widget
        // 這樣面板內部的點擊、捲動、輸入都不會意外觸發拖曳
        var chat    = document.getElementById('chat-widget');
        var chatBtn = document.getElementById('chat-toggle-btn');
        if (chat && chatBtn) {
            var cp = pos.chat || DEFAULTS.chat;
            applyToEl(chat, cp.x, cp.y, 72);
            makeDraggable(chat, 'chat', 72, chatBtn);
        }
    });

    // 公開 API 給設定頁使用
    window.FAB_DRAG = {
        DEFAULTS: DEFAULTS,
        load:      load,
        save:      save,
        applyToEl: applyToEl,
        reset: function () { localStorage.removeItem(KEY); location.reload(); }
    };
})();
