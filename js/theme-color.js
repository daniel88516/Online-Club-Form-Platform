/**
 * applyThemeColor(hex)
 * 載入於 <head>，確保所有頁面最早期即可使用
 */
function applyThemeColor(hex) {
    if (!hex || !/^#[0-9a-fA-F]{6}$/.test(hex)) return;
    var n = parseInt(hex.slice(1), 16);
    var r = (n >> 16) & 255, g = (n >> 8) & 255, b = n & 255;
    var darken = '#' + [r, g, b].map(function (v) {
        return ('0' + Math.max(0, v - 30).toString(16)).slice(-2);
    }).join('');

    // 計算亮度，決定文字顏色（確保主題色背景上的文字可讀）
    var brightness = (r * 299 + g * 587 + b * 114) / 1000;
    var isLight    = brightness > 140;
    var textColor  = isLight ? '#000000' : '#ffffff';
    var textMuted  = isLight ? 'rgba(0,0,0,0.55)'  : 'rgba(255,255,255,0.55)';
    var textHover  = isLight ? 'rgba(0,0,0,0.75)'  : 'rgba(255,255,255,0.75)';
    var togglerBd  = isLight ? 'rgba(0,0,0,0.15)'  : 'rgba(255,255,255,0.15)';

    // 深色模式 outline 按鈕：將主題色往白色調亮 40%，確保在深色背景上可讀
    var dR = Math.min(255, Math.round(r + (255 - r) * 0.42));
    var dG = Math.min(255, Math.round(g + (255 - g) * 0.42));
    var dB = Math.min(255, Math.round(b + (255 - b) * 0.42));
    var darkOutline  = '#' + [dR, dG, dB].map(function(v){ return ('0' + v.toString(16)).slice(-2); }).join('');
    var darkDarken   = '#' + [dR, dG, dB].map(function(v){ return ('0' + Math.max(0, v - 30).toString(16)).slice(-2); }).join('');
    var darkBright   = (dR * 299 + dG * 587 + dB * 114) / 1000;
    var darkTextColor = darkBright > 140 ? '#000000' : '#ffffff';

    // 漢堡選單圖示（配合文字亮暗切換）
    var togglerSvg = "<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 30 30'><path stroke='"
        + (isLight ? 'rgba(0,0,0,0.55)' : 'rgba(255,255,255,0.55)')
        + "' stroke-linecap='round' stroke-miterlimit='10' stroke-width='2' d='M4 7h22M4 15h22M4 23h22'/></svg>";
    var togglerIcon = 'url("data:image/svg+xml;base64,' + btoa(togglerSvg) + '")';

    var root = document.documentElement;
    root.style.setProperty('--bs-primary',      hex);
    root.style.setProperty('--bs-primary-rgb',  r + ',' + g + ',' + b);
    root.style.setProperty('--bs-primary-text', textColor);
    root.style.setProperty('--bs-link-color',        hex);
    root.style.setProperty('--bs-link-hover-color',  darken);
    root.style.setProperty('--bs-focus-ring-color', 'rgba(' + r + ',' + g + ',' + b + ',0.25)');

    var el = document.getElementById('_theme-color-style');
    if (!el) {
        el = document.createElement('style');
        el.id = '_theme-color-style';
        document.head.appendChild(el);
    }
    el.textContent =
        /* btn-primary：背景色 + 對比文字色 */
        '.btn-primary{--bs-btn-bg:' + hex + ';--bs-btn-border-color:' + hex + ';--bs-btn-hover-bg:' + darken + ';--bs-btn-hover-border-color:' + darken + ';--bs-btn-active-bg:' + darken + ';--bs-btn-active-border-color:' + darken + ';--bs-btn-color:' + textColor + ';--bs-btn-hover-color:' + textColor + ';--bs-btn-active-color:' + textColor + '}' +

        /* btn-outline-primary（淺色模式）：主題色文字，hover 填滿主題色 */
        '.btn-outline-primary{--bs-btn-color:' + hex + ';--bs-btn-border-color:' + hex + ';--bs-btn-hover-bg:' + hex + ';--bs-btn-hover-border-color:' + hex + ';--bs-btn-active-bg:' + hex + ';--bs-btn-hover-color:' + textColor + ';--bs-btn-active-color:' + textColor + '}' +

        /* btn-outline-primary（深色模式）：調亮後的主題色，確保深色背景上可讀 */
        '[data-bs-theme="dark"] .btn-outline-primary{--bs-btn-color:' + darkOutline + ';--bs-btn-border-color:' + darkOutline + ';--bs-btn-hover-bg:' + darkOutline + ';--bs-btn-hover-border-color:' + darkOutline + ';--bs-btn-active-bg:' + darkDarken + ';--bs-btn-hover-color:' + darkTextColor + ';--bs-btn-active-color:' + darkTextColor + '}' +

        /* navbar.bg-primary：文字、連結、toggler 全部跟隨對比色 */
        '.navbar.bg-primary{--bs-navbar-color:' + textMuted + ';--bs-navbar-hover-color:' + textHover + ';--bs-navbar-disabled-color:' + textMuted + ';--bs-navbar-active-color:' + textColor + ';--bs-navbar-brand-color:' + textColor + ';--bs-navbar-brand-hover-color:' + textColor + ';--bs-navbar-toggler-icon-bg:' + togglerIcon + ';--bs-navbar-toggler-border-color:' + togglerBd + '}' +

        /* badge / nav-pills active */
        '.badge.bg-primary{color:' + textColor + '}' +
        '.nav-pills .nav-link.active,.nav-pills .show>.nav-link{color:' + textColor + '}';
}
