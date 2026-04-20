// 每頁載入時套用已儲存的主題顏色（applyThemeColor 定義於 theme-color.js）
(function () {
    var c = localStorage.getItem('themeColor');
    if (c) applyThemeColor(c);
})();

/* ── 頭像彈出選單（fixed 定位，hover 瞬間觸發）── */
var _avdUid = 0, _avdUname = '', _avdAvatar = '', _avdEl = null;
var _avdHideTimer = null;

function _avdPosition() {
    if (!_avdEl) return;
    var rect = _avdEl.getBoundingClientRect();
    var $p = $('#avd-popup');
    $p.css({ top: rect.bottom + 6, left: rect.left });
    var pw = $p.outerWidth();
    if (rect.left + pw > window.innerWidth - 8) {
        $p.css('left', window.innerWidth - pw - 8);
    }
}
// 滑鼠移入頭像：瞬間顯示
$(document).on('mouseenter', '.avd-wrap', function () {
    clearTimeout(_avdHideTimer);
    _avdEl     = this;
    _avdUid    = $(this).data('uid');
    _avdUname  = $(this).data('uname');
    _avdAvatar = $(this).data('avatar') || '';
    $('#avd-go-chat').toggle($(this).data('chat') == 1);
    _avdPosition();
    $('#avd-popup').show();
});
// 滑鼠離開頭像：150ms 後關閉（讓使用者有時間移到 popup）
$(document).on('mouseleave', '.avd-wrap', function () {
    _avdHideTimer = setTimeout(function () { $('#avd-popup').hide(); }, 150);
});
// 滑鼠移入 popup：取消關閉計時
$(document).on('mouseenter', '#avd-popup', function () {
    clearTimeout(_avdHideTimer);
});
// 滑鼠離開 popup：立即關閉
$(document).on('mouseleave', '#avd-popup', function () {
    $('#avd-popup').hide();
});
$(document).on('click', '#avd-go-profile', function () {
    $('#avd-popup').hide();
    location.href = '/profile.php?id=' + _avdUid;
});
$(document).on('click', '#avd-go-chat', function () {
    $('#avd-popup').hide();
    if (typeof openChatWith === 'function') openChatWith(_avdUid, _avdUname, _avdAvatar);
});
$(document).on('click', function () {
    $('#avd-popup').hide();
});
window.addEventListener('scroll', function () {
    if (_avdEl && $('#avd-popup').is(':visible')) {
        requestAnimationFrame(_avdPosition);
    }
}, { passive: true });

$(document).ready(function () {

    // 自動關閉 alert
    setTimeout(function () {
        $('.alert-dismissible').fadeOut(500);
    }, 4000);

    // 修正 .table-responsive 內 dropdown 被 overflow 裁切的問題
    $('.table-responsive [data-bs-toggle="dropdown"]').each(function () {
        new bootstrap.Dropdown(this, {
            popperConfig: function (cfg) {
                return Object.assign({}, cfg, { strategy: 'fixed' });
            }
        });
    });

});

/**
 * 為任意 Quill 實例附加「貼上圖片自動上傳」功能
 * uploadType: 'comments'（預設）或 'forms'
 */
function attachPasteImageHandler(quill, uploadType) {
    uploadType = uploadType || 'comments';
    quill.root.addEventListener('paste', function (e) {
        var items = e.clipboardData && e.clipboardData.items;
        if (!items) return;
        for (var i = 0; i < items.length; i++) {
            if (items[i].type.indexOf('image/') === 0) {
                e.preventDefault();  /* 在捕獲階段阻止 Quill 的預設貼上處理 */
                e.stopPropagation();
                var file = items[i].getAsFile();
                if (!file) continue;
                var ext = file.type.split('/')[1] || 'png';
                var fd  = new FormData();
                fd.append('image', file, 'paste.' + ext);
                fd.append('type', uploadType);
                var _quill = quill;
                $.ajax({
                    url: '/api/upload.php', type: 'POST',
                    data: fd, contentType: false, processData: false,
                    success: function (res) {
                        if (res.success) {
                            var range = _quill.getSelection() || { index: _quill.getLength() };
                            _quill.insertEmbed(range.index, 'image', res.path);
                            _quill.setSelection(range.index + 1);
                        }
                    }
                });
                break;
            }
        }
    }, true);  /* capture phase：在 Quill bubble 處理器之前執行 */
}

/**
 * 為任意 Quill 實例附加「自動超連結」功能
 * 使用者輸入空白或 Enter 後，自動將裸 URL 格式化成連結
 */
function attachAutoLink(quill) {
    quill.on('text-change', function (delta, old, source) {
        if (source !== 'user') return;
        // 只在輸入空白 / 換行時才掃描（URL 完整後才轉）
        const hasSpace = delta.ops.some(function (op) {
            return typeof op.insert === 'string' && /[\s\n]/.test(op.insert);
        });
        if (!hasSpace) return;

        const text     = quill.getText();
        const urlRegex = /https?:\/\/[^\s]+/g;
        let match;
        while ((match = urlRegex.exec(text)) !== null) {
            const start   = match.index;
            const len     = match[0].length;
            const formats = quill.getFormat(start, len);
            if (!formats.link) {
                quill.formatText(start, len, 'link', match[0], 'silent');
            }
        }
    });
}
