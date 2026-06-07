function applyThemeColor(hex) {
    if (!hex || !/^#[0-9a-fA-F]{6}$/.test(hex)) return;

    var n = parseInt(hex.slice(1), 16);
    var r = (n >> 16) & 255;
    var g = (n >> 8) & 255;
    var b = n & 255;
    var darken = '#' + [r, g, b].map(function (v) {
        return ('0' + Math.max(0, v - 30).toString(16)).slice(-2);
    }).join('');

    function channel(v) {
        v = v / 255;
        return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4);
    }

    function luminance(rr, gg, bb) {
        return 0.2126 * channel(rr) + 0.7152 * channel(gg) + 0.0722 * channel(bb);
    }

    function contrastText(rr, gg, bb) {
        var bg = luminance(rr, gg, bb);
        var white = 1.05 / (bg + 0.05);
        var black = (bg + 0.05) / 0.05;
        return white >= black ? '#ffffff' : '#000000';
    }

    function rgbaOnBase(v, base, alpha) {
        return Math.round(v * alpha + base * (1 - alpha));
    }

    var root = document.documentElement;
    var isDarkTheme = root.getAttribute('data-bs-theme') === 'dark';
    var navAlpha = isDarkTheme ? 0.18 : 0.88;
    var navBase = isDarkTheme ? 0 : 255;
    var navR = rgbaOnBase(r, navBase, navAlpha);
    var navG = rgbaOnBase(g, navBase, navAlpha);
    var navB = rgbaOnBase(b, navBase, navAlpha);

    var textColor = contrastText(r, g, b);
    var navTextColor = contrastText(navR, navG, navB);
    var navUsesLightText = navTextColor === '#ffffff';
    var textMuted = navUsesLightText ? 'rgba(255,255,255,0.55)' : 'rgba(0,0,0,0.55)';
    var textHover = navUsesLightText ? 'rgba(255,255,255,0.75)' : 'rgba(0,0,0,0.75)';
    var togglerBd = navUsesLightText ? 'rgba(255,255,255,0.15)' : 'rgba(0,0,0,0.15)';

    var dR = Math.min(255, Math.round(r + (255 - r) * 0.42));
    var dG = Math.min(255, Math.round(g + (255 - g) * 0.42));
    var dB = Math.min(255, Math.round(b + (255 - b) * 0.42));
    var darkOutline = '#' + [dR, dG, dB].map(function (v) {
        return ('0' + v.toString(16)).slice(-2);
    }).join('');
    var darkDarken = '#' + [dR, dG, dB].map(function (v) {
        return ('0' + Math.max(0, v - 30).toString(16)).slice(-2);
    }).join('');
    var darkTextColor = contrastText(dR, dG, dB);

    var togglerSvg = "<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 30 30'><path stroke='"
        + textMuted
        + "' stroke-linecap='round' stroke-miterlimit='10' stroke-width='2' d='M4 7h22M4 15h22M4 23h22'/></svg>";
    var togglerIcon = 'url("data:image/svg+xml;base64,' + btoa(togglerSvg) + '")';

    root.style.setProperty('--bs-primary', hex);
    root.style.setProperty('--bs-primary-rgb', r + ',' + g + ',' + b);
    root.style.setProperty('--bs-primary-text', textColor);
    root.style.setProperty('--bs-primary-nav-text', navTextColor);
    root.style.setProperty('--bs-link-color', hex);
    root.style.setProperty('--bs-link-hover-color', darken);
    root.style.setProperty('--bs-focus-ring-color', 'rgba(' + r + ',' + g + ',' + b + ',0.25)');

    var el = document.getElementById('_theme-color-style');
    if (!el) {
        el = document.createElement('style');
        el.id = '_theme-color-style';
        document.head.appendChild(el);
    }
    el.textContent =
        '.btn-primary{--bs-btn-bg:' + hex + ';--bs-btn-border-color:' + hex + ';--bs-btn-hover-bg:' + darken + ';--bs-btn-hover-border-color:' + darken + ';--bs-btn-active-bg:' + darken + ';--bs-btn-active-border-color:' + darken + ';--bs-btn-color:' + textColor + ';--bs-btn-hover-color:' + textColor + ';--bs-btn-active-color:' + textColor + '}' +
        '.btn-outline-primary{--bs-btn-color:' + hex + ';--bs-btn-border-color:' + hex + ';--bs-btn-hover-bg:' + hex + ';--bs-btn-hover-border-color:' + hex + ';--bs-btn-active-bg:' + hex + ';--bs-btn-hover-color:' + textColor + ';--bs-btn-active-color:' + textColor + '}' +
        '[data-bs-theme="dark"] .btn-outline-primary{--bs-btn-color:' + darkOutline + ';--bs-btn-border-color:' + darkOutline + ';--bs-btn-hover-bg:' + darkOutline + ';--bs-btn-hover-border-color:' + darkOutline + ';--bs-btn-active-bg:' + darkDarken + ';--bs-btn-hover-color:' + darkTextColor + ';--bs-btn-active-color:' + darkTextColor + '}' +
        '.navbar.bg-primary{--bs-navbar-color:' + textMuted + ';--bs-navbar-hover-color:' + textHover + ';--bs-navbar-disabled-color:' + textMuted + ';--bs-navbar-active-color:' + navTextColor + ';--bs-navbar-brand-color:' + navTextColor + ';--bs-navbar-brand-hover-color:' + navTextColor + ';--bs-navbar-toggler-icon-bg:' + togglerIcon + ';--bs-navbar-toggler-border-color:' + togglerBd + '}' +
        '.badge.bg-primary{color:' + textColor + '}' +
        '.nav-pills .nav-link.active,.nav-pills .show>.nav-link{color:' + textColor + '}';
}
