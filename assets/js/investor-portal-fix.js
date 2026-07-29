(function($) {
    function normalizeText(v) {
        return String(v || '').replace(/\s+/g, ' ').trim().toLowerCase();
    }

    function getStorageKey($input) {
        const id = $input.attr('id') || '';
        const name = $input.attr('name') || '';
        const key = name || id || 'confidentiality';
        return 'ip_confidentiality:' + window.location.pathname + ':' + key;
    }

    function setChecked($input, checked) {
        const v = !!checked;
        if ($input.prop('checked') === v) return;
        $input.prop('checked', v).trigger('change');
    }

    function applyState($input) {
        const k = getStorageKey($input);
        try {
            const stored = window.localStorage.getItem(k);
            if (stored === '1') setChecked($input, true);
            if (stored === '0') setChecked($input, false);
        } catch (e) {}
    }

    function bindPersistence($input) {
        if ($input.data('ip-conf-bound')) return;
        $input.data('ip-conf-bound', true);
        applyState($input);
        $input.on('change.ipConf', function() {
            const k = getStorageKey($input);
            try {
                window.localStorage.setItem(k, $input.is(':checked') ? '1' : '0');
            } catch (e) {}
        });
    }

    function findConfidentialityCheckboxes(root) {
        const $root = root ? $(root) : $(document);
        const nodes = $root.find('label, span, div, p, li, button, a').get();
        const found = [];

        nodes.forEach(node => {
            const $node = $(node);
            if ($node.data('ip-conf-scan')) return;
            $node.data('ip-conf-scan', true);
            const t = normalizeText($node.text());
            if (!t) return;
            if (!t.includes('confidentiality')) return;

            let $input = $();
            if ($node.is('label')) {
                const forId = $node.attr('for');
                if (forId) $input = $('#' + CSS.escape(forId));
                if (!$input.length) $input = $node.find('input[type="checkbox"]').first();
            }
            if (!$input.length) {
                $input = $node.closest('li, .field, .option, .elementor-field, .form-row, .control, .item, .setting, .row').find('input[type="checkbox"]').first();
            }
            if (!$input.length) return;

            bindPersistence($input);

            const $clickTarget = $node.closest('li, .field, .option, .elementor-field, .form-row, .control, .item, .setting, .row');
            const $target = $clickTarget.length ? $clickTarget : $node;
            if (!$target.data('ip-conf-click')) {
                $target.data('ip-conf-click', true);
                $target.on('click.ipConf', function(e) {
                    if ($(e.target).is('input[type="checkbox"]')) return;
                    e.preventDefault();
                    e.stopPropagation();
                    setChecked($input, !$input.is(':checked'));
                });
            }

            found.push($input.get(0));
        });

        return found;
    }

    function init() {
        findConfidentialityCheckboxes(document);

        const observer = new MutationObserver(function(mutations) {
            for (const m of mutations) {
                if (m.type === 'childList' && (m.addedNodes && m.addedNodes.length)) {
                    m.addedNodes.forEach(n => {
                        if (n.nodeType !== 1) return;
                        findConfidentialityCheckboxes(n);
                    });
                }
            }
        });

        observer.observe(document.documentElement, { childList: true, subtree: true });
    }

    $(init);
})(jQuery);

