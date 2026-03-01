<?php /* 全域卡片彈跳動畫（由 footer.php 引入） */ ?>
<style>
/* ── 全域卡片基礎過渡 ── */
.card {
    transition: transform 0.28s ease, box-shadow 0.28s ease;
    will-change: transform;
}
/* 聊天視窗 / Modal 內的卡片不套動畫 */
#chat-widget .card,
.modal         .card {
    transition: none !important;
    will-change: auto !important;
}

/* ── 粒子 ── */
@keyframes ptcl-fly {
    to {
        transform: translate(var(--px), var(--py)) rotate(var(--pr)) scale(0.15);
        opacity: 0;
    }
}
.card-ptcl {
    position: fixed;
    pointer-events: none;
    z-index: 9999;
    user-select: none;
    line-height: 1;
    transform-origin: center;
    animation: ptcl-fly var(--dur) ease-out var(--delay) forwards;
}
</style>

<!-- hover / spring keyframes 由 JS 依 localStorage 動態注入 -->
<style id="card-anim-style"></style>

<script>
// ══ 全域卡片動畫：設定讀取 & CSS 注入 ══
(function () {
    var KEY      = 'cardAnim';
    var DEFAULTS = { enabled: true, amplitude: 6, speed: 6, particles: true };
    // 索引 0 = 未使用；索引 1–10 對應滑桿值
    var ANIM_H   = [0, 0.5, 1.0, 1.5, 2.0, 2.5, 3.0, 3.8, 5.0, 6.5, 9.0]; // hover 浮起(px)
    var ANIM_E   = [0, 0.4, 0.7, 1.0, 1.3, 1.6, 2.0, 2.5, 3.5, 5.0, 7.0]; // 彈跳超出量(px)
    var ANIM_DUR = [0, 1.10, 0.90, 0.75, 0.65, 0.58, 0.52, 0.44, 0.36, 0.28, 0.20]; // 動畫時長(s)

    window.getCardAnimSettings = function () {
        try { return Object.assign({}, DEFAULTS, JSON.parse(localStorage.getItem(KEY) || '{}')); }
        catch (e) { return Object.assign({}, DEFAULTS); }
    };

    window.applyCardAnimCSS = function () {
        var cfg = window.getCardAnimSettings();
        var el  = document.getElementById('card-anim-style');
        if (!el) return;
        if (!cfg.enabled) { el.textContent = ''; return; }

        var a   = Math.max(1, Math.min(10, cfg.amplitude | 0));
        var sp  = Math.max(1, Math.min(10, cfg.speed | 0));
        var H   = ANIM_H[a], E = ANIM_E[a], dur = ANIM_DUR[sp];

        // keyframe 偏移量（以 hover 靜止位置 H 為基準，阻尼振盪規律）
        var pcts  = [0,  8,     17,     26,    35,    44,    53,   62, 71,   82, 91,   100];
        var devs  = [H, -E, H*5/6, -E/2, H*2/3, -E/4, H/2,   0, H/3,  0, H/6,   0];
        var sDev0 = [0, 0.01, -0.005, 0.007, -0.003, 0.005, -0.002, 0.003, -0.001, 0.001, 0, 0];
        var sf    = E / 2;

        var kf = '@keyframes card-spring {\n';
        for (var i = 0; i < pcts.length; i++) {
            var y = (-H + devs[i]).toFixed(2);
            var s = (1 + sDev0[i] * sf).toFixed(4);
            kf += '    ' + pcts[i] + '% { transform:translateY(' + y + 'px) scale(' + s + '); }\n';
        }
        kf += '}\n';
        // 全域 .card 套用動畫
        kf += '.card.is-popping{'
            + 'animation:card-spring ' + dur + 's linear both;'
            + 'box-shadow:0 12px 30px rgba(0,0,0,.16),0 3px 7px rgba(0,0,0,.08)!important;}\n';
        kf += '.card:hover{'
            + 'transform:translateY(-' + H + 'px);'
            + 'box-shadow:0 12px 30px rgba(0,0,0,.16),0 3px 7px rgba(0,0,0,.08)!important;}\n';
        // 聊天視窗 / Modal 內重置（覆蓋上面的規則）
        kf += '#chat-widget .card:hover,#chat-widget .card.is-popping,'
            + '.modal .card:hover,.modal .card.is-popping'
            + '{transform:none!important;box-shadow:none!important;animation:none!important;}\n';

        el.textContent = kf;
    };

    window.applyCardAnimCSS();
})();

// ══ 全域卡片 hover 彈跳 + 粒子噴發 ══
(function () {
    var PTCLS   = ['⭐', '✨', '🌟', '💫', '🍬', '🍭', '⚡', '🌸'];
    var EXCLUDE = '#chat-widget, .modal';   // 不觸發動畫的容器

    function spawnParticles(card) {
        var rect  = card.getBoundingClientRect();
        var count = 12;
        var W = rect.width, H = rect.height;
        var perim = 2 * (W + H);

        for (var i = 0; i < count; i++) {
            (function (idx) {
                setTimeout(function () {
                    var el = document.createElement('span');
                    el.className = 'card-ptcl';
                    el.textContent = PTCLS[Math.floor(Math.random() * PTCLS.length)];

                    // 在卡片邊框（周長）上取隨機點
                    var t = Math.random() * perim;
                    var ex, ey;
                    if      (t < W)         { ex = t;           ey = 0; }
                    else if (t < W + H)     { ex = W;           ey = t - W; }
                    else if (t < 2*W + H)   { ex = 2*W + H - t; ey = H; }
                    else                    { ex = 0;           ey = perim - t; }

                    // 從卡片中心往外輻射，加少量隨機偏角（±20°）
                    var baseAng = Math.atan2(ey - H/2, ex - W/2);
                    var ang     = baseAng + (Math.random() - 0.5) * 0.7;
                    var dist    = 50 + Math.random() * 70;
                    var px      = Math.cos(ang) * dist;
                    var py      = Math.sin(ang) * dist;
                    var pr      = (Math.random() - 0.5) * 360;
                    var dur     = (0.5 + Math.random() * 0.35).toFixed(2) + 's';
                    var dly     = (idx * 0.025).toFixed(3) + 's';
                    var fs      = (11 + Math.random() * 11).toFixed(0);

                    el.style.cssText =
                        'left:'    + (rect.left + ex).toFixed(1) + 'px;' +
                        'top:'     + (rect.top  + ey).toFixed(1) + 'px;' +
                        'font-size:' + fs  + 'px;' +
                        '--px:'    + px.toFixed(1)  + 'px;' +
                        '--py:'    + py.toFixed(1)  + 'px;' +
                        '--pr:'    + pr.toFixed(0)  + 'deg;' +
                        '--dur:'   + dur + ';' +
                        '--delay:' + dly + ';';

                    document.body.appendChild(el);
                    setTimeout(function () { el.remove(); }, 1000);
                }, idx * 25);
            })(i);
        }
    }

    var lastCard = null;

    $(document).on('mouseenter', '.card', function () {
        // 排除聊天視窗、Modal 等容器內的卡片
        if ($(this).closest(EXCLUDE).length) return;
        if (this === lastCard) return;
        lastCard = this;

        var cfg = window.getCardAnimSettings();
        if (!cfg.enabled) return;
        var $c = $(this);

        $c.addClass('is-popping').one('animationend webkitAnimationEnd', function () {
            $c.removeClass('is-popping');
        });

        if (cfg.particles) spawnParticles(this);
    });

    $(document).on('mouseleave', '.card', function () {
        if (this === lastCard) lastCard = null;
    });
})();
</script>
