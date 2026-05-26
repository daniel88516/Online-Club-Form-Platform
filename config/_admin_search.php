<?php
function renderAdminSearchBar($name, $value, $placeholder, $clearHref) {
?>
<form method="GET" class="mb-3 admin-search-form">
    <div class="glow-sort-bar">
        <i class="bi bi-search text-muted flex-shrink-0" style="font-size:.9rem;"></i>
        <input type="text"
               name="<?= htmlspecialchars($name) ?>"
               class="form-control ps-1 admin-search-input"
               placeholder="<?= htmlspecialchars($placeholder) ?>"
               value="<?= htmlspecialchars($value) ?>"
               autocomplete="off">
        <button class="btn btn-glow-primary btn-sm px-3 flex-shrink-0" type="submit">搜尋</button>
        <?php if ($value !== ''): ?>
            <a href="<?= htmlspecialchars($clearHref) ?>" class="btn btn-sm btn-outline-secondary flex-shrink-0"><i class="bi bi-x"></i></a>
        <?php endif; ?>
    </div>
</form>
<?php
}

function adminSearchHighlight($text, $query) {
    $text = (string)$text;
    $query = trim((string)$query);
    if ($query === '') {
        return htmlspecialchars($text);
    }

    $parts = preg_split('/(' . preg_quote($query, '/') . ')/iu', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
    if ($parts === false) {
        return htmlspecialchars($text);
    }

    $html = '';
    foreach ($parts as $part) {
        if ($part === '') {
            continue;
        }
        if (mb_strtolower($part) === mb_strtolower($query)) {
            $html .= '<mark class="search-hl">' . htmlspecialchars($part) . '</mark>';
        } else {
            $html .= htmlspecialchars($part);
        }
    }
    return $html;
}

function renderAdminSearchScript() {
    static $rendered = false;
    if ($rendered) {
        return;
    }
    $rendered = true;
?>
<script>
(function () {
    function escapeReg(s) { return s.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'); }
    function escapeHtml(s) {
        return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }
    function highlightText($el, q) {
        const orig = $el.data('orig-text') ?? $el.text();
        $el.data('orig-text', orig);
        if (!q) { $el.html(escapeHtml(orig)); return; }
        const re = new RegExp('(' + escapeReg(q) + ')', 'gi');
        $el.html(escapeHtml(orig).replace(re, '<mark class="search-hl">$1</mark>'));
    }
    $(document).on('input', '.admin-search-input', function () {
        const q = $(this).val().toLowerCase().trim();
        let visible = 0;
        $('tr[data-admin-search]').each(function () {
            const $row = $(this);
            const match = !q || String($row.data('admin-search') || '').toLowerCase().includes(q);
            $row.toggle(match);
            if (match) {
                $row.find('.admin-search-target').each(function () {
                    highlightText($(this), q);
                });
                visible++;
            }
        });
        if (!q) {
            $('.admin-search-target').each(function () {
                const orig = $(this).data('orig-text');
                if (orig !== undefined) $(this).html(escapeHtml(orig));
            });
        }
        $('.admin-search-empty').toggleClass('d-none', visible > 0 || !q);
    });
})();
</script>
<?php
}
