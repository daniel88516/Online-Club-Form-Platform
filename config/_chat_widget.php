<?php if (!isLoggedIn()) return; ?>

<!-- ===================== 浮動聊天視窗 ===================== -->
<style>
#chat-widget {
    position: fixed;
    bottom: 2.5rem;
    right: 2rem;
    z-index: 1100;
}
#chat-toggle-btn {
    width: 72px; height: 72px;
    border-radius: 50%;
    background: rgba(var(--bs-primary-rgb, 13,110,253), 0.15);
    color: #fff;
    border: none;
    font-size: 2rem;
    box-shadow: 0 0 22px rgba(var(--bs-primary-rgb, 13,110,253), 0.60), 0 0 55px rgba(var(--bs-primary-rgb, 13,110,253), 0.25);
    display: flex; align-items: center; justify-content: center;
    cursor: pointer;
    position: relative;
    transition: transform .15s ease, box-shadow .15s ease;
}
#chat-toggle-btn:hover { transform: scale(1.1); box-shadow: 0 0 32px rgba(var(--bs-primary-rgb, 13,110,253), 0.80), 0 0 70px rgba(var(--bs-primary-rgb, 13,110,253), 0.38); }
#chat-unread-badge {
    position: absolute;
    top: -2px; right: -2px;
    background: #dc3545;
    color: #fff;
    font-size: 0.65rem;
    padding: 3px 6px;
    border-radius: 999px;
    display: none;
}
#chat-panel {
    position: absolute;
    width: 320px;
    height: 460px;
    background: var(--bs-body-bg);
    border: 1px solid var(--bs-border-color);
    border-radius: 14px;
    box-shadow: 0 8px 32px rgba(0,0,0,.18);
    display: none;
    flex-direction: column;
    overflow: hidden;
}
#chat-panel.open { display: flex; }
.chat-panel-header {
    padding: 12px 14px;
    font-weight: 600;
    font-size: 0.95rem;
    border-bottom: 1px solid var(--bs-border-color);
    display: flex;
    align-items: center;
    gap: 8px;
    flex-shrink: 0;
}
#chat-conv-list {
    flex: 1;
    overflow-y: auto;
    overscroll-behavior: contain;
}
.chat-conv-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 14px;
    cursor: pointer;
    border-bottom: 1px solid var(--bs-border-color);
    transition: background .1s;
}
.chat-conv-item:hover { background: var(--bs-secondary-bg); }
.chat-avatar {
    width: 38px; height: 38px;
    border-radius: 50%;
    background: var(--bs-primary, #0d6efd);
    color: var(--bs-primary-text, #fff);
    font-weight: bold;
    font-size: 1rem;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
    box-shadow: 0 0 0 2px var(--bs-body-bg, #f8f9fa), 0 0 0 4px var(--bs-primary, #0d6efd);
}
#chat-messages-panel { display: none; flex-direction: column; flex: 1; overflow: hidden; }
#chat-messages-panel.active { display: flex; }
#chat-msg-body {
    flex: 1;
    overflow-y: auto;
    overscroll-behavior: contain;
    padding: 10px 12px;
    display: flex;
    flex-direction: column;
    gap: 8px;
}
.chat-bubble-wrap { display: flex; }
.chat-bubble-wrap.me { flex-direction: column; align-items: flex-end; }
.chat-bubble {
    max-width: 72%;
    padding: 7px 12px;
    border-radius: 18px;
    font-size: 0.85rem;
    word-break: break-word;
    line-height: 1.4;
}
.chat-bubble.me  { background: var(--bs-primary, #0d6efd); color: var(--bs-primary-text, #fff); border-bottom-right-radius: 4px; }
.chat-bubble.other { background: var(--bs-secondary-bg); border-bottom-left-radius: 4px; }
.chat-bubble.system {
    max-width: 92%;
    padding: 0;
    border-radius: 10px;
    overflow: hidden;
    font-size: 0.82rem;
    border: 1px solid #fcd34d;
    box-shadow: 0 2px 8px rgba(245,158,11,.15);
}
.sys-bubble-head {
    background: linear-gradient(90deg, #f59e0b, #fbbf24);
    color: #fff;
    padding: 5px 10px;
    font-size: 0.72rem;
    font-weight: 700;
    letter-spacing: 0.5px;
    display: flex;
    align-items: center;
    gap: 5px;
}
.sys-bubble-body {
    background: #fffbeb;
    color: #78350f;
    padding: 9px 12px;
    white-space: pre-line;
    line-height: 1.5;
}
[data-bs-theme="dark"] .chat-bubble.system { border-color: #5d4a00; }
[data-bs-theme="dark"] .sys-bubble-body { background: #2a2200; color: #fde68a; }
.sys-bubble-body .time { font-size: 0.63rem; opacity: .6; margin-top: 5px; }
.chat-bubble .time { font-size: 0.65rem; opacity: .65; margin-top: 3px; }
.read-receipt {
    font-size: 0.63rem;
    color: var(--bs-secondary-color, #6c757d);
    margin-top: 2px;
    display: none;
}
#chat-input-area {
    border-top: 1px solid var(--bs-border-color);
    padding: 8px 10px;
    display: flex;
    gap: 6px;
    flex-shrink: 0;
    position: relative;
}
#chat-input-area textarea {
    flex: 1;
    resize: none;
    border-radius: 18px;
    padding: 6px 12px;
    font-size: 0.85rem;
    border: 1px solid var(--bs-border-color);
    background: var(--bs-body-bg);
    color: var(--bs-body-color);
}
#chat-send-btn {
    width: 36px; height: 36px;
    border-radius: 50%;
    background: var(--bs-primary, #0d6efd);
    color: var(--bs-primary-text, #fff);
    border: none;
    font-size: 1rem;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
    cursor: pointer;
    transition: filter .15s ease;
}
#chat-send-btn:hover { filter: brightness(0.88); }
.chat-close-btn {
    margin-left: auto;
    background: none;
    border: none;
    padding: 2px 4px;
    color: var(--bs-secondary-color);
    cursor: pointer;
    font-size: 1rem;
    line-height: 1;
    border-radius: 4px;
}
.chat-close-btn:hover { color: var(--bs-body-color); background: var(--bs-secondary-bg); }

/* ── 訊息刪除按鈕 ── */
.chat-bubble-row {
    display: flex;
    align-items: flex-end;
    gap: 4px;
}
/* 自己的訊息：row 撐滿後 flex-end，bubble 永遠貼右邊 */
.chat-bubble-wrap.me .chat-bubble-row {
    width: 100%;
    justify-content: flex-end;
}
.msg-del-btn {
    opacity: 0;
    pointer-events: none;
    background: none;
    border: none;
    color: var(--bs-secondary-color, #6c757d);
    font-size: 0.78rem;
    width: 22px; height: 22px;
    padding: 0;
    cursor: pointer;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    transition: opacity .15s, color .15s, background .15s;
}
.chat-bubble-wrap:hover .msg-del-btn {
    opacity: 1;
    pointer-events: auto;
}
.msg-del-btn:hover {
    color: var(--bs-danger, #dc3545);
    background: var(--bs-secondary-bg);
}

/* ── Emoji / Sticker Picker ── */
.chat-picker-btn {
    width: 30px; height: 30px;
    border-radius: 50%;
    background: none;
    border: none;
    color: var(--bs-secondary-color, #6c757d);
    font-size: 1.05rem;
    display: flex; align-items: center; justify-content: center;
    cursor: pointer;
    flex-shrink: 0;
    transition: color .15s, background .15s;
    align-self: center;
}
.chat-picker-btn:hover { color: var(--bs-primary); background: var(--bs-secondary-bg); }
.chat-picker-btn.active { color: var(--bs-primary); }
.chat-picker-panel {
    position: absolute;
    bottom: calc(100% + 6px);
    right: 0;
    width: 276px;
    background: var(--bs-body-bg);
    border: 1px solid var(--bs-border-color);
    border-radius: 12px;
    box-shadow: 0 4px 20px rgba(0,0,0,.18);
    padding: 10px;
    display: none;
    z-index: 20;
}
.chat-picker-panel.open { display: block; }
.chat-picker-tabs {
    display: flex;
    gap: 4px;
    margin-bottom: 8px;
}
.chat-picker-tab {
    flex: 1;
    padding: 4px 0;
    font-size: 0.78rem;
    border: 1px solid var(--bs-border-color);
    border-radius: 6px;
    background: none;
    cursor: pointer;
    color: var(--bs-secondary-color);
    transition: background .1s, color .1s;
}
.chat-picker-tab.active { background: var(--bs-primary); color: #fff; border-color: var(--bs-primary); }
.emoji-grid {
    display: grid;
    grid-template-columns: repeat(8, 1fr);
    gap: 2px;
}
.emoji-item {
    font-size: 1.25rem;
    cursor: pointer;
    text-align: center;
    padding: 3px 0;
    border-radius: 6px;
    line-height: 1.4;
    transition: background .1s;
}
.emoji-item:hover { background: var(--bs-secondary-bg); }
.sticker-grid {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap: 4px;
}
.sticker-item {
    font-size: 2rem;
    cursor: pointer;
    text-align: center;
    padding: 5px 0;
    border-radius: 8px;
    line-height: 1.2;
    transition: background .12s, transform .1s;
}
.sticker-item:hover { background: var(--bs-secondary-bg); transform: scale(1.18); }
/* 貼圖泡泡：透明背景，大 emoji */
.chat-bubble.sticker {
    background: transparent !important;
    padding: 2px 4px !important;
    font-size: 3rem;
    line-height: 1.1;
    border-radius: 0 !important;
    box-shadow: none !important;
}
.chat-bubble.sticker .time { font-size: 0.62rem; opacity: .6; margin-top: 2px; }
</style>

<div id="chat-widget">
    <!-- 浮動按鈕 -->
    <button id="chat-toggle-btn" title="訊息">
        <i class="bi bi-chat-dots-fill"></i>
        <span id="chat-unread-badge"></span>
    </button>

    <!-- 聊天面板 -->
    <div id="chat-panel">

        <!-- 對話列表 -->
        <div id="chat-conv-view" style="display:flex;flex-direction:column;flex:1;overflow:hidden;">
            <div class="chat-panel-header">
                <i class="bi bi-chat-dots text-primary"></i> 訊息
                <button class="chat-close-btn" title="關閉" onclick="chatPanelClose()"><i class="bi bi-x-lg"></i></button>
            </div>
            <div id="chat-conv-list">
                <div class="text-center text-muted py-4 small" id="chat-conv-empty" style="display:none;">
                    還沒有任何對話
                </div>
            </div>
        </div>

        <!-- 訊息對話 -->
        <div id="chat-messages-panel">
            <div class="chat-panel-header">
                <button id="chat-back-btn" style="background:none;border:none;padding:0;color:inherit;cursor:pointer;">
                    <i class="bi bi-arrow-left"></i>
                </button>
                <div class="chat-avatar" id="chat-other-avatar" style="width:28px;height:28px;font-size:0.8rem;"></div>
                <span id="chat-other-name"></span>
                <button class="chat-close-btn" title="關閉" onclick="chatPanelClose()"><i class="bi bi-x-lg"></i></button>
            </div>
            <div id="chat-msg-body"></div>
            <div id="chat-input-area">
                <textarea id="chat-input" rows="1" placeholder="輸入訊息…"></textarea>
                <button class="chat-picker-btn" id="chat-emoji-btn" title="表情 / 貼圖"><i class="bi bi-emoji-smile"></i></button>
                <button id="chat-send-btn"><i class="bi bi-send-fill"></i></button>
                <!-- 表情 / 貼圖選擇器 -->
                <div class="chat-picker-panel" id="chat-picker-panel">
                    <div class="chat-picker-tabs">
                        <button class="chat-picker-tab active" id="tab-emoji">😀 表情</button>
                        <button class="chat-picker-tab" id="tab-sticker">🖼 貼圖</button>
                        <button class="chat-close-btn" id="picker-close-btn" title="關閉" style="margin-left:auto;"><i class="bi bi-x-lg"></i></button>
                    </div>
                    <div id="picker-emoji-grid" class="emoji-grid"></div>
                    <div id="picker-sticker-grid" class="sticker-grid" style="display:none;"></div>
                </div>
            </div>
        </div>

    </div>
</div>

<script>

(function () {
    const ME         = <?= $_SESSION['user_id'] ?>;
    const SYSTEM_UID = <?= getSystemUserId($conn) ?>;
    let currentWith = null;
    let lastMsgId   = 0;
    let pollTimer   = null;
    let lastReadAckId = 0;

    // ── 視覺刪除（僅本機 localStorage，訊息本身不刪除） ──
    const DEL_KEY = 'chatDeletedMsgs';
    let _deletedIds = null;
    function loadDeletedIds() {
        if (_deletedIds !== null) return _deletedIds;
        try { _deletedIds = new Set(JSON.parse(localStorage.getItem(DEL_KEY) || '[]')); }
        catch (e) { _deletedIds = new Set(); }
        return _deletedIds;
    }
    function saveDeletedId(id) {
        const ids = loadDeletedIds();
        ids.add(parseInt(id));
        localStorage.setItem(DEL_KEY, JSON.stringify([...ids]));
    }
    function isDeleted(id) { return loadDeletedIds().has(parseInt(id)); }

    const $panel       = $('#chat-panel');
    const $convView    = $('#chat-conv-view');
    const $msgPanel    = $('#chat-messages-panel');
    const $msgBody     = $('#chat-msg-body');
    const $badge       = $('#chat-unread-badge');
    const $navBadge    = $('#nav-msg-badge');

    // 根據按鈕位置決定面板展開方向，盡量水平置中對齊按鈕
    function positionPanel() {
        const rect   = document.getElementById('chat-widget').getBoundingClientRect();
        const btnW   = 72, btnH = 72, panelW = 320, panelH = 460;
        const vw     = window.innerWidth;
        const margin = 8;
        // 水平：以按鈕為中心，超出邊界時夾住
        let idealLeft = (btnW - panelW) / 2;               // 相對 widget 的理想 left
        const absLeft = rect.left + idealLeft;              // 轉成視窗絕對座標
        const clamped = Math.min(Math.max(absLeft, margin), vw - panelW - margin);
        $panel.css({ left: (clamped - rect.left) + 'px', right: 'auto' });
        // 垂直：上方夠放就往上展開，否則往下展開
        if (rect.top >= panelH + margin) {
            $panel.css({ bottom: '0', top: 'auto' });
        } else {
            $panel.css({ top: btnH + 'px', bottom: 'auto' });
        }
    }

    // 開關面板（拖曳結束後 300ms 內忽略 click，避免拖曳觸發開關）
    $('#chat-toggle-btn').on('click', function () {
        if (window._fabLastDragEnd && Date.now() - window._fabLastDragEnd < 300) return;
        const isOpen = $panel.hasClass('open');
        if (isOpen) {
            $panel.removeClass('open');
            stopPoll();
        } else {
            positionPanel();
            $panel.addClass('open');
            showConvList();
        }
    });

    // 關閉面板（叉叉按鈕呼叫的全域函式）
    window.chatPanelClose = function () {
        $panel.removeClass('open');
        stopPoll();
    };

    // 返回列表
    $('#chat-back-btn').on('click', function () {
        stopPoll();
        currentWith = null;
        lastMsgId   = 0;
        $msgPanel.removeClass('active');
        $convView.show();
        showConvList();
    });

    // 建立頭像 HTML（有圖片用圖片，否則用首字母圓圈）
    function buildAvatarHtml(name, avatar, size) {
        const ring = 'box-shadow:0 0 0 2px var(--bs-body-bg,#f8f9fa),0 0 0 4px var(--bs-primary,#0d6efd);';
        if (avatar) {
            const src = $('<span>').text(assetUrl(avatar)).html();
            return `<img src="${src}" style="width:${size}px;height:${size}px;border-radius:50%;object-fit:cover;flex-shrink:0;${ring}" alt="">`;
        }
        const initial = (name || '?').charAt(0).toUpperCase();
        return `<div class="chat-avatar" style="width:${size}px;height:${size}px;font-size:${Math.round(size*0.44)}px;">${initial}</div>`;
    }

    // 顯示對話列表
    function showConvList() {
        $.get(REL_BASE + 'api/get_conversations.php', function (res) {
            if (!res.success) return;
            const $list = $('#chat-conv-list');
            $list.find('.chat-conv-item').remove();
            if (res.conversations.length === 0) {
                $('#chat-conv-empty').show();
                updateBadge(0);
                return;
            }
            $('#chat-conv-empty').hide();
            let totalUnread = 0;
            res.conversations.forEach(function (c) {
                totalUnread += parseInt(c.unread_count) || 0;
                const isSystem   = (parseInt(c.other_id) === SYSTEM_UID);
                const avatarHtml = isSystem
                    ? `<div class="chat-avatar" style="background:#f59e0b;font-size:1rem;"><i class="bi bi-bell-fill"></i></div>`
                    : buildAvatarHtml(c.other_name, c.other_avatar, 38);
                const unreadHtml = c.unread_count > 0
                    ? `<span class="badge bg-danger rounded-pill ms-auto">${c.unread_count}</span>` : '';
                const $item = $(`
                    <div class="chat-conv-item" data-uid="${c.other_id}" data-name="${c.other_name}">
                        ${avatarHtml}
                        <div style="flex:1;overflow:hidden;">
                            <div style="font-weight:600;font-size:0.88rem;">${$('<span>').text(c.other_name).html()}</div>
                            <div class="text-muted" style="font-size:0.76rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                                ${$('<span>').text(c.last_content.substring(0,35)).html()}
                            </div>
                        </div>
                        ${unreadHtml}
                    </div>`);
                $item.on('click', function () {
                    openChat(c.other_id, c.other_name, c.other_avatar);
                });
                $list.append($item);
            });
            updateBadge(totalUnread);
        }, 'json');
    }

    // 開啟對話
    function openChat(uid, name, avatar) {
        currentWith   = uid;
        lastMsgId     = 0;
        lastReadAckId = 0;
        $convView.hide();
        $msgPanel.addClass('active');
        $('#chat-other-name').text(name);
        const isSystem   = (parseInt(uid) === SYSTEM_UID);
        const $hdrAvatar = $('#chat-other-avatar');
        if (isSystem) {
            $hdrAvatar.css('background', '#f59e0b').html('<i class="bi bi-bell-fill"></i>');
        } else if (avatar) {
            const src = $('<span>').text(assetUrl(avatar)).html();
            $hdrAvatar.css('background', 'transparent')
                      .html(`<img src="${src}" style="width:100%;height:100%;border-radius:50%;object-fit:cover;" alt="">`);
        } else {
            $hdrAvatar.css('background', '').text(name.charAt(0).toUpperCase());
        }
        // 系統對話：隱藏輸入框
        $('#chat-input-area').toggle(!isSystem);
        $msgBody.empty();
        loadMessages(true);
        startPoll();
    }

    // 建立訊息泡泡
    function buildBubble(msg) {
        const senderId = parseInt(msg.sender_id);
        const isMe     = (senderId === ME);
        const isSys    = (senderId === SYSTEM_UID);

        if (isSys) {
            const reportMatch  = msg.content.match(/\[REPORT:(\d+)\]/);
            const clubMatch    = msg.content.match(/\[CLUB:(\d+)\]/);
            const pendingMatch = msg.content.match(/\[CLUBS_PENDING\]/);
            const exploreMatch = msg.content.match(/\[CLUBS_EXPLORE\]/);
            const cleanText = $('<span>').text(msg.content.replace(/\[REPORT:\d+\]|\[CLUB:\d+\]|\[CLUBS_PENDING\]|\[CLUBS_EXPLORE\]/g, '').trim()).html().replace(/\n/g, '<br>');
            const btnHtml   = reportMatch
                ? `<a href="${REL_BASE}admin/reports.php?highlight=${reportMatch[1]}" class="btn btn-sm w-100 mt-2 fw-semibold d-flex align-items-center justify-content-center gap-1" style="background:#f59e0b;border-color:#f59e0b;color:#fff;font-size:0.8rem;"><i class="bi bi-arrow-right-circle"></i> 前往處理此檢舉</a>`
                : clubMatch
                ? `<a href="${REL_BASE}club.php?id=${clubMatch[1]}" class="btn btn-glow-primary btn-sm w-100 mt-2 d-flex align-items-center justify-content-center gap-1"><i class="bi bi-arrow-right-circle"></i> 前往社團</a>`
                : pendingMatch
                ? `<a href="${REL_BASE}clubs.php?tab=pending" class="btn btn-glow-amber btn-sm w-100 mt-2 d-flex align-items-center justify-content-center gap-1"><i class="bi bi-hourglass-split"></i> 前往待處理社團</a>`
                : exploreMatch
                ? `<a href="${REL_BASE}clubs.php" class="btn btn-glow-dark btn-sm w-100 mt-2 d-flex align-items-center justify-content-center gap-1"><i class="bi bi-compass"></i> 探索社團</a>`
                : '';
            return `<div class="chat-bubble-wrap">
                <div class="chat-bubble system">
                    <div class="sys-bubble-head">
                        <i class="bi bi-bell-fill"></i> 系統通知
                    </div>
                    <div class="sys-bubble-body">
                        ${cleanText}
                        ${btnHtml}
                        <div class="time">${msg.created_at}</div>
                    </div>
                </div>
            </div>`;
        }

        // 貼圖偵測
        const stickerMatch = msg.content.match(/^\[sticker:([\s\S]+)\]$/);
        if (stickerMatch) {
            if (isDeleted(msg.id)) return '';
            const emo = $('<span>').text(stickerMatch[1]).html();
            if (isMe) {
                return `<div class="chat-bubble-wrap me" data-msg-id="${msg.id}">
                    <div class="chat-bubble-row">
                        <button class="msg-del-btn" data-del-id="${msg.id}" title="刪除訊息"><i class="bi bi-trash3"></i></button>
                        <div class="chat-bubble me sticker">${emo}<div class="time">${msg.created_at}</div></div>
                    </div>
                    <span class="read-receipt">已讀</span>
                </div>`;
            }
            return `<div class="chat-bubble-wrap" data-msg-id="${msg.id}">
                <div class="chat-bubble-row">
                    <div class="chat-bubble other sticker">${emo}<div class="time">${msg.created_at}</div></div>
                    <button class="msg-del-btn" data-del-id="${msg.id}" title="刪除訊息"><i class="bi bi-trash3"></i></button>
                </div>
            </div>`;
        }

        const text = $('<span>').text(msg.content).html().replace(/\n/g, '<br>');
        if (isMe) {
            if (isDeleted(msg.id)) return '';   // 已被視覺刪除，不渲染
            return `<div class="chat-bubble-wrap me" data-msg-id="${msg.id}">
                <div class="chat-bubble-row">
                    <button class="msg-del-btn" data-del-id="${msg.id}" title="刪除訊息"><i class="bi bi-trash3"></i></button>
                    <div class="chat-bubble me">
                        ${text}
                        <div class="time">${msg.created_at}</div>
                    </div>
                </div>
                <span class="read-receipt">已讀</span>
            </div>`;
        }
        if (isDeleted(msg.id)) return '';   // 已被視覺刪除，不渲染
        return `<div class="chat-bubble-wrap" data-msg-id="${msg.id}">
            <div class="chat-bubble-row">
                <div class="chat-bubble other">
                    ${text}
                    <div class="time">${msg.created_at}</div>
                </div>
                <button class="msg-del-btn" data-del-id="${msg.id}" title="刪除訊息"><i class="bi bi-trash3"></i></button>
            </div>
        </div>`;
    }

    // 更新已讀顯示（只在最後一則已讀的自己訊息上顯示「已讀」）
    function updateReadReceipt(maxReadId) {
        if (!maxReadId || maxReadId <= lastReadAckId) return;
        lastReadAckId = maxReadId;
        $msgBody.find('.read-receipt').hide();
        let bestId = 0, $best = null;
        $msgBody.find('.chat-bubble-wrap[data-msg-id]').each(function () {
            const id = parseInt($(this).attr('data-msg-id')) || 0;
            if (id <= maxReadId && id > bestId) { bestId = id; $best = $(this); }
        });
        if ($best) $best.find('.read-receipt').show();
    }

    // 載入訊息
    function loadMessages(scroll) {
        if (!currentWith) return;
        $.get(REL_BASE + 'api/get_messages.php', { with: currentWith, last_id: lastMsgId }, function (res) {
            if (!res.success) return;
            if (res.messages && res.messages.length > 0) {
                res.messages.forEach(function (m) {
                    // 已視覺刪除的訊息：只推進 lastMsgId，不渲染
                    if (isDeleted(m.id)) {
                        lastMsgId = m.id;
                        return;
                    }
                    $msgBody.append(buildBubble(m));
                    lastMsgId = m.id;
                });
                if (scroll) $msgBody.scrollTop($msgBody[0].scrollHeight);
            }
            updateReadReceipt(res.max_read_id || 0);
        }, 'json');
    }

    // 送出訊息
    function sendMessage() {
        const content = $('#chat-input').val().trim();
        if (!content || !currentWith) return;
        $('#chat-input').val('');
        $.post(REL_BASE + 'api/send_message.php', { receiver_id: currentWith, content: content }, function (res) {
            if (res.success) {
                $msgBody.append(buildBubble({
                    id: res.message_id, sender_id: ME,
                    content: content, created_at: res.created_at
                }));
                lastMsgId = res.message_id;
                $msgBody.scrollTop($msgBody[0].scrollHeight);
            }
        }, 'json');
    }

    // 刪除按鈕：先問確認，再視覺移除 + 寫入 localStorage
    $msgBody.on('click', '.msg-del-btn', function () {
        const id   = parseInt($(this).data('del-id'));
        if (!id) return;
        const $wrap = $(this).closest('.chat-bubble-wrap');
        window.cuteConfirm({
            icon: '💬',
            msg:  '刪除這則訊息？',
            sub:  '僅在您的裝置上隱藏，不影響對方。'
        }, function (ok) {
            if (!ok) return;
            saveDeletedId(id);
            $wrap.fadeOut(200, function () { $(this).remove(); });
        });
    });

    $('#chat-send-btn').on('click', sendMessage);
    $('#chat-input').on('keydown', function (e) {
        if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendMessage(); }
    });

    // ── Emoji / Sticker Picker ──
    const EMOJIS = [
        '😀','😂','🥰','😍','😎','🥳','😭','😡','🤔','🤩',
        '😴','🥺','🤣','😅','😬','😱','👍','👎','❤️','🔥',
        '💯','🎉','🙏','💪','✨','💀','👀','🤝','🫶','💔',
        '🌟','🤯'
    ];
    const STICKERS = [
        '🥰','😍','🤩','🥳','😊',
        '😭','😤','🤬','😱','🥹',
        '👍','👏','🙏','💪','🫶',
        '❤️','🔥','💯','🎉','🌟',
        '🐱','🐶','🐼','🦊','🐸'
    ];

    // 初始化格子
    $('#picker-emoji-grid').html(EMOJIS.map(e => `<span class="emoji-item">${e}</span>`).join(''));
    $('#picker-sticker-grid').html(STICKERS.map(s => `<span class="sticker-item">${s}</span>`).join(''));

    // 分頁切換
    let pickerTab = 'emoji';
    $('#tab-emoji').on('click', function () {
        pickerTab = 'emoji';
        $(this).addClass('active'); $('#tab-sticker').removeClass('active');
        $('#picker-emoji-grid').show(); $('#picker-sticker-grid').hide();
    });
    $('#tab-sticker').on('click', function () {
        pickerTab = 'sticker';
        $(this).addClass('active'); $('#tab-emoji').removeClass('active');
        $('#picker-sticker-grid').show(); $('#picker-emoji-grid').hide();
    });

    // 開關 picker
    function closePicker() {
        $('#chat-picker-panel').removeClass('open');
        $('#chat-emoji-btn').removeClass('active');
    }
    $('#chat-emoji-btn').on('click', function (e) {
        e.stopPropagation();
        const isOpen = $('#chat-picker-panel').hasClass('open');
        closePicker();
        if (!isOpen) { $('#chat-picker-panel').addClass('open'); $(this).addClass('active'); }
    });
    $(document).on('click', function () { closePicker(); });
    $('#chat-picker-panel').on('click', function (e) { e.stopPropagation(); });

    // 叉叉關閉
    $('#picker-close-btn').on('click', function (e) { e.stopPropagation(); closePicker(); });

    // Emoji → 插入到輸入框（不自動關閉）
    $('#picker-emoji-grid').on('click', '.emoji-item', function () {
        const emoji = $(this).text();
        const $inp  = $('#chat-input')[0];
        const s = $inp.selectionStart, e2 = $inp.selectionEnd;
        const val = $inp.value;
        $inp.value = val.slice(0, s) + emoji + val.slice(e2);
        $inp.setSelectionRange(s + emoji.length, s + emoji.length);
        $inp.focus();
    });

    // Sticker → 直接送出（不自動關閉）
    $('#picker-sticker-grid').on('click', '.sticker-item', function () {
        const sticker = $(this).text().trim();
        if (!currentWith) return;
        const content = `[sticker:${sticker}]`;
        $.post(REL_BASE + 'api/send_message.php', { receiver_id: currentWith, content: content }, function (res) {
            if (res.success) {
                $msgBody.append(buildBubble({ id: res.message_id, sender_id: ME, content: content, created_at: res.created_at }));
                lastMsgId = res.message_id;
                $msgBody.scrollTop($msgBody[0].scrollHeight);
            }
        }, 'json');
    });

    // 輪詢
    function startPoll() {
        stopPoll();
        pollTimer = setInterval(function () { loadMessages(true); }, 3000);
    }
    function stopPoll() {
        if (pollTimer) { clearInterval(pollTimer); pollTimer = null; }
    }

    // 更新徽章
    function updateBadge(count) {
        if (count > 0) {
            $badge.text(count).show();
            $navBadge.text(count).show();
        } else {
            $badge.hide();
            $navBadge.hide();
        }
    }

    // 外部觸發：openChatWith(uid, name, avatar)
    window.openChatWith = function (uid, name, avatar) {
        positionPanel();
        $panel.addClass('open');
        $convView.hide();
        $msgPanel.addClass('active');
        openChat(uid, name, avatar || null);
    };

    // 更新未讀徽章（可重複呼叫）
    function refreshBadge() {
        $.get(REL_BASE + 'api/get_conversations.php', function (res) {
            if (!res.success) return;
            let total = 0;
            res.conversations.forEach(function (c) { total += parseInt(c.unread_count) || 0; });
            updateBadge(total);
        }, 'json');
    }

    // 初始化未讀數
    refreshBadge();

    // 面板關閉時每 15 秒自動更新徽章（系統通知也會算進來）
    setInterval(function () {
        if (!$panel.hasClass('open')) refreshBadge();
    }, 15000);

})();
</script>
