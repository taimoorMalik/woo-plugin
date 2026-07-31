jQuery(document).ready(function($) {
    let rowCount = 0;
    const maxRows = 20; // Limit for performance

    // Initialize
    function init() {
        fetchProducts();
        addDefaultRows();
        setupEventHandlers();
        setupSortable();
        updateRowClipboardUI();
        
        // Ensure Quote Ref exists on load
        if ($('#wq-quote-ref').text().trim() === '') {
            $('#wq-quote-ref').text(generateQuoteRef());
        }
        
        // Check for edit_quote in URL
        const urlParams = new URLSearchParams(window.location.search);
        const editQuoteId = urlParams.get('edit_quote');
        if (editQuoteId) {
            loadQuote(editQuoteId);
        }
    }

    function getLabelDropdownOptions() {
        if (typeof wqBuilder !== 'undefined' && wqBuilder && wqBuilder.label_dropdown_options) {
            const raw = wqBuilder.label_dropdown_options;
            const list = Array.isArray(raw) ? raw : (typeof raw === 'object' ? Object.values(raw) : []);
            const cleaned = list
                .filter(v => typeof v === 'string')
                .map(v => v.trim())
                .filter(v => v !== '');
            return Array.from(new Set(cleaned));
        }
        return [];
    }

    function setupLabelDropdown($row) {
        const $select = $row.find('.wq-label-predefined-select');
        if (!$select.length) return;

        const currentValue = $select.val();
        const options = getLabelDropdownOptions();
        if (!options || options.length === 0) {
            if (currentValue) {
                $select.val(currentValue);
            }
            return;
        }

        $select.empty();
        $select.append($('<option>').val('').text('-- Select --'));
        options.forEach(opt => {
            $select.append($('<option>').val(opt).text(opt));
        });

        if (currentValue) {
            $select.val(currentValue);
        }
    }

    function normalizeObject(value) {
        if (!value) return {};
        if (typeof value === 'object') return value;
        if (typeof value === 'string') {
            const s = value.trim();
            if ((s.startsWith('{') && s.endsWith('}')) || (s.startsWith('[') && s.endsWith(']'))) {
                try {
                    const parsed = JSON.parse(s);
                    if (parsed && typeof parsed === 'object') return parsed;
                } catch (e) {}
            }
        }
        return {};
    }

    function normalizeIdList(value) {
        if (!value) return [];
        if (Array.isArray(value)) return value;
        if (typeof value === 'object') {
            const values = Object.values(value);
            const looksLikeIdValues = values.every(v => typeof v === 'number' || typeof v === 'string');
            if (looksLikeIdValues) return values;
            return Object.keys(value);
        }
        if (typeof value === 'string') {
            const s = value.trim();
            if (s === '') return [];
            if ((s.startsWith('[') && s.endsWith(']')) || (s.startsWith('{') && s.endsWith('}'))) {
                try {
                    const parsed = JSON.parse(s);
                    return normalizeIdList(parsed);
                } catch (e) {
                    return [];
                }
            }
            if (s.includes(',')) return s.split(',').map(v => v.trim()).filter(Boolean);
        }
        return [];
    }

    function normalizeSearchText(v) {
        return String(v || '')
            .toLowerCase()
            .replace(/[_\-]+/g, ' ')
            .replace(/\s+/g, ' ')
            .trim();
    }

    function matchesSearch(hay, query) {
        const h = normalizeSearchText(hay);
        const q = normalizeSearchText(query);
        if (!q) return true;

        const h2 = h.replace(/\s+/g, '');
        const q2 = q.replace(/\s+/g, '');

        const tokens = q.split(' ').filter(Boolean);
        if (tokens.length > 1) {
            return tokens.every(tok => {
                const tok2 = tok.replace(/\s+/g, '');
                return (tok && h.includes(tok)) || (tok2 && h2.includes(tok2));
            });
        }

        if (h.includes(q)) return true;
        if (q2 !== '' && h2.includes(q2)) return true;

        if (q2 !== '') {
            let i = 0;
            for (let j = 0; j < h2.length && i < q2.length; j++) {
                if (h2[j] === q2[i]) i++;
            }
            if (i === q2.length) return true;
        }

        return false;
    }

    function replaceToken(template, key, value) {
        const token = '{' + key + '}';
        return String(template).split(token).join('(' + String(value) + ')');
    }

    function evaluateMathExpression(expr) {
        const withoutTokens = String(expr).replace(/\{[^}]+\}/g, '0');
        const sanitized = withoutTokens.replace(/[^0-9\.\+\-\*\/\(\)\s]/g, '');
        if (sanitized.trim() === '') return 0;
        if (sanitized.includes('**')) return 0;
        try {
            const result = new Function('return (' + sanitized + ')')();
            return typeof result === 'number' && isFinite(result) ? result : 0;
        } catch (e) {
            return 0;
        }
    }

    function getDimensionUnit() {
        const unit = (typeof wqBuilder !== 'undefined' && wqBuilder && wqBuilder.dimension_unit) ? String(wqBuilder.dimension_unit) : 'mm';
        if (unit === 'cm' || unit === 'm' || unit === 'in' || unit === 'mm') return unit;
        return 'mm';
    }

    function getDimensionFactor(unit) {
        if (unit === 'm') return 1000;
        if (unit === 'cm') return 10;
        if (unit === 'in') return 25.4;
        return 1;
    }

    function dimensionToMm(value) {
        const unit = getDimensionUnit();
        const factor = getDimensionFactor(unit);
        const v = parseFloat(value);
        if (isNaN(v)) return 0;
        return v * factor;
    }

    function mmToDimension(mm) {
        const unit = getDimensionUnit();
        const factor = getDimensionFactor(unit);
        const v = parseFloat(mm);
        if (isNaN(v)) return 0;
        return v / factor;
    }

    function formatDimensionFromMm(mm) {
        const unit = getDimensionUnit();
        const val = mmToDimension(mm);
        if (unit === 'm') return parseFloat(val.toFixed(3));
        if (unit === 'cm') return parseFloat(val.toFixed(1));
        if (unit === 'in') return parseFloat(val.toFixed(2));
        return Math.round(val);
    }

    function formatDimensionValue(value) {
        const unit = getDimensionUnit();
        const v = parseFloat(value);
        if (isNaN(v)) return 0;
        if (unit === 'm') return parseFloat(v.toFixed(3));
        if (unit === 'cm') return parseFloat(v.toFixed(1));
        if (unit === 'in') return parseFloat(v.toFixed(2));
        return Math.round(v);
    }

    function getDimensionUnitLabel() {
        return getDimensionUnit();
    }

    function getOperationByIndex(index) {
        if (typeof wqBuilder === 'undefined' || !wqBuilder || !Array.isArray(wqBuilder.edge_operations)) return null;
        const i = parseInt(index);
        if (isNaN(i) || i < 0 || i >= wqBuilder.edge_operations.length) return null;
        const op = wqBuilder.edge_operations[i];
        if (!op || typeof op !== 'object') return null;
        return { index: i, name: op.name || ('Operation ' + i), type: op.type || 'fixed', price: parseFloat(op.price) || 0 };
    }

    function calculateOperationCost(op, qty, ctx) {
        if (!op || !op.price || op.price <= 0) return 0;
        const q = parseInt(qty) || 0;
        if (q <= 0) return 0;
        const context = (ctx && typeof ctx === 'object') ? ctx : { perimeterM: ctx };
        if (op.type === 'meter') {
            const p = parseFloat(context.perimeterM) || 0;
            return op.price * p * q;
        }
        if (op.type === 'm2' || op.type === 'sqm' || op.type === 'area') {
            const a = parseFloat(context.areaM2) || 0;
            return op.price * a * q;
        }
        return op.price * q;
    }

    function getOperationsByIndexes(indexes) {
        const normalized = normalizeObject(indexes);
        const list = Array.isArray(normalized) ? normalized : [];
        const ops = [];
        list.forEach(idx => {
            const op = getOperationByIndex(idx);
            if (op) ops.push(op);
        });
        return ops;
    }

    function calculateOperationsCost(ops, qty, ctx) {
        if (!ops || !Array.isArray(ops) || ops.length === 0) return { total: 0, lines: [] };
        const lines = [];
        let total = 0;
        ops.forEach(op => {
            const cost = calculateOperationCost(op, qty, ctx);
            total += cost;
            lines.push({ index: op.index, name: op.name, type: op.type, price: op.price, cost: cost });
        });
        return { total: total, lines: lines };
    }

    function buildEdgeFormulaForSide(side, lengthMm, widthMm, sideLenMm) {
        const edgeFormulas = wqBuilder && wqBuilder.edge_formulas ? wqBuilder.edge_formulas : null;
        const perSideFormula = edgeFormulas && edgeFormulas[side] !== undefined ? String(edgeFormulas[side] || '').trim() : '';
        const usePerSide = perSideFormula !== '';

        const defaultPerSide = (() => {
            if (side === 'w1' || side === 'w2') return '{width_m} * {price} * {qty}';
            return '{length_m} * {price} * {qty}';
        })();
        const baseFormula = usePerSide ? perSideFormula : defaultPerSide;
        const lengthM = (parseFloat(lengthMm) || 0) / 1000;
        const widthM = (parseFloat(widthMm) || 0) / 1000;
        const sideM = (parseFloat(sideLenMm) || 0) / 1000;

        let f = baseFormula;
        f = replaceToken(f, 'side_mm', sideLenMm);
        f = replaceToken(f, 'side_m', sideM);
        f = replaceToken(f, 'length_mm', lengthMm);
        f = replaceToken(f, 'length_m', lengthM);
        f = replaceToken(f, 'width_mm', widthMm);
        f = replaceToken(f, 'width_m', widthM);

        return f;
    }

    function setEdgeSearchEnabled(enabled) {
        const $input = $('.wq-edge-search-input');
        $input.prop('disabled', !enabled);
        if (!enabled) {
            $('.wq-edge-search-panel').hide();
        }
    }

    function hideEdgePanels() {
        $('.wq-edge-search-panel').hide();
        $('.wq-preferred-edge-panel').hide();
    }

    function renderEdgeSearchResults(term, highlightServiceId) {
        const $results = $('.wq-edge-search-results');
        $results.empty();

        const qRaw = String(term || '').trim();
        if (qRaw === '') {
            return;
        }

        if (!window.activeEdgeRow) {
            return;
        }

        if (!window.wqEdgeData || !window.wqEdgeData.services) {
            return;
        }

        const servicesMap = window.wqEdgeData.services || {};
        const services = Array.isArray(servicesMap) ? servicesMap : Object.values(servicesMap);
        const qNorm = normalizeSearchText(qRaw);

        const matches = [];
        services.forEach(service => {
            if (!service) return;

            const name = String(service.name || '');
            const code = String(service.code || '');
            const label = (code && name) ? `${code} - ${name}` : (code || name || String(service.id || ''));

            if (!matchesSearch(label, qRaw)) return;

            const hayNorm = normalizeSearchText(label);
            const starts = qNorm !== '' && hayNorm.startsWith(qNorm);

            matches.push({
                id: service.id,
                label: label,
                starts: starts ? 0 : 1
            });
        });

        matches.sort((a, b) => {
            if (a.starts !== b.starts) return a.starts - b.starts;
            return String(a.label).localeCompare(String(b.label));
        });

        const maxResults = 1000;
        matches.slice(0, maxResults).forEach(m => {
            const $li = $(`<li data-service-id="${m.id}"></li>`).text(m.label);
            if (highlightServiceId && String(m.id) === String(highlightServiceId)) {
                $li.addClass('active');
            }
            $results.append($li);
        });

        if (matches.length === 0) {
            $results.append('<li style="color:#999; cursor:default;">No edging found.</li>');
        }
    }

    function renderPreferredEdgeResults(term) {
        const $results = $('.wq-preferred-edge-results');
        $results.empty();

        const $input = $('.wq-preferred-edge-search');
        if ($input.prop('disabled')) {
            return;
        }

        const t = String(term || '').trim();

        if (!window.activeEdgeRow) {
            return;
        }

        const preferredIds = normalizeIdList($('#wq-edge-diagram-popup').data('preferred-ids'));
        if (!preferredIds || preferredIds.length === 0 || !window.wqEdgeData || !window.wqEdgeData.services) {
            return;
        }

        let shown = 0;
        const maxResults = 200;
        preferredIds.forEach(serviceId => {
            if (shown >= maxResults) return;
            const id = String(serviceId);
            const service = window.wqEdgeData.services[id] || window.wqEdgeData.services[parseInt(id, 10)];
            if (!service) return;

            const name = String(service.name || '');
            const code = String(service.code || '');
            const label = (code && name) ? `${code} - ${name}` : (code || name || String(service.id || ''));
            if (t !== '' && !matchesSearch(label, t)) return;
            const $li = $(`<li data-service-id="${service.id}"></li>`).text(label);
            $results.append($li);
            shown++;
        });

        if (shown === 0) {
            $results.append('<li style="color:#999; cursor:default;">No edging found.</li>');
        }
    }

    function updateDiagramActiveEdges() {
        const $box = $('.wq-diagram-box');
        $box.removeClass('wq-d-active-edge-l1 wq-d-active-edge-w1 wq-d-active-edge-l2 wq-d-active-edge-w2');

        $('.wq-highlight-l1, .wq-highlight-w1, .wq-highlight-l2, .wq-highlight-w2').hide();
        $('.wq-d-angle-l1, .wq-d-angle-w1, .wq-d-angle-l2, .wq-d-angle-w2').hide();

        const unit = getDimensionUnitLabel();
        const lengthVal = window.activeEdgeRow ? String(window.activeEdgeRow.find('.wq-row-length input').val() || '').trim() : '';
        const widthVal = window.activeEdgeRow ? String(window.activeEdgeRow.find('.wq-row-width input').val() || '').trim() : '';

        $('.wq-edge-tab.active').each(function() {
            const edge = $(this).data('edge');
            $box.addClass(`wq-d-active-edge-${edge}`);
            $(`.wq-highlight-${edge}`).show();
            const $angleLabel = $(`.wq-d-angle-${edge}`);
            const sideVal = (edge === 'l1' || edge === 'l2') ? lengthVal : widthVal;
            if (sideVal !== '') $angleLabel.text(`${sideVal}${unit}`).show();
            else $angleLabel.hide();
        });
    }

    function selectEdgeServiceInPopup(serviceId, fromPreferred) {
        if (!serviceId || !window.wqEdgeData || !window.wqEdgeData.services || !window.activeEdgeRow) return;
        const service = window.wqEdgeData.services[serviceId];
        if (!service) return;

        const $preferredDisplay = $('.wq-preferred-edge-display');
        const $preferredSearch = $('.wq-preferred-edge-search');
        const $edgeSearchDisplay = $('.wq-edge-search-display');
        const $edgeSearchInput = $('.wq-edge-search-input');

        const selectedLabel = (() => {
            const name = String(service.name || '');
            const code = String(service.code || '');
            if (code && name) return `${code} - ${name}`;
            return code || name || String(service.id || '');
        })();

        let activeProfileId = $('.wq-visual-option.active').data('profile-id');
        if (!activeProfileId || !service.profiles || !service.profiles[activeProfileId]) {
            const firstProfileId = service.profiles ? Object.keys(service.profiles)[0] : null;
            if (firstProfileId) {
                const $profileBtn = $(`.wq-visual-option[data-profile-id="${firstProfileId}"]`);
                if ($profileBtn.length) {
                    $profileBtn.trigger('click');
                }
                activeProfileId = firstProfileId;
            }
        }

        if (fromPreferred) {
            $preferredDisplay.val(selectedLabel);
            $preferredSearch.val('');
            $edgeSearchDisplay.val('');
        } else {
            $edgeSearchDisplay.val(selectedLabel);
            $edgeSearchInput.val('');
            $preferredDisplay.val('');
            $preferredSearch.val('');
        }

        $('.wq-edge-search-results li').removeClass('active');
        $(`.wq-edge-search-results li[data-service-id="${serviceId}"]`).addClass('active');

        const code = service.code || service.name.substring(0, 5);
        $('.wq-current-edge-info .wq-edge-code').text(code);
        $('.wq-current-edge-info .wq-edge-thick').text('');

        window.activeEdgeRow.data('selected-edge-service', serviceId);
        window.activeEdgeRow.data('selected-edge-profile', activeProfileId);

        const profileConfig = service.profiles ? service.profiles[activeProfileId] : null;
        const allowedSides = profileConfig && Array.isArray(profileConfig.sides) ? profileConfig.sides : [];
        const prices = profileConfig ? (profileConfig.prices || {}) : {};

        window.activeEdgeRow.data('edge-prices', prices);
        window.activeEdgeRow.data('edge-default-price', service.default_price);

        const allowedSet = new Set((allowedSides || []).map(v => String(v)));
        let assignments = window.activeEdgeRow.data('edge-assignments') || {};
        let assignmentsChanged = false;
        ['l1', 'w1', 'l2', 'w2'].forEach(edge => {
            const isAllowed = allowedSet.size > 0 && allowedSet.has(edge);
            if (isAllowed) return;

            $(`.wq-edge-tab[data-edge="${edge}"]`).removeClass('active');
            window.activeEdgeRow.find(`.wq-edge-${edge}`).prop('checked', false);
            window.activeEdgeRow.find(`.wq-code-${edge}`).text('');
            $(`.wq-code-display-${edge}`).val('');

            if (assignments && assignments[edge]) {
                delete assignments[edge];
                assignmentsChanged = true;
            }
        });
        if (assignmentsChanged) window.activeEdgeRow.data('edge-assignments', assignments);

        ['l1', 'w1', 'l2', 'w2'].forEach(edge => {
            const $tab = $(`.wq-edge-tab[data-edge="${edge}"]`);
            const $checkbox = window.activeEdgeRow.find(`.wq-edge-${edge}`);
            const isEdgeActive = $checkbox.is(':checked') || $tab.hasClass('active');
            const isAllowed = allowedSet.size > 0 && allowedSet.has(edge);
            if (!isAllowed && !isEdgeActive) {
                $tab.prop('disabled', true).addClass('disabled');
            } else {
                $tab.prop('disabled', false).removeClass('disabled');
            }
        });

        updateDiagramActiveEdges();
    }

    function syncSelectedEdgeServiceForCurrentProfile(serviceId) {
        if (!serviceId || !window.wqEdgeData || !window.wqEdgeData.services || !window.activeEdgeRow) return;
        const id = String(serviceId);
        const service = window.wqEdgeData.services[id] || window.wqEdgeData.services[parseInt(id, 10)];
        if (!service) return;

        const activeProfileId = $('.wq-visual-option.active').data('profile-id');
        const profileConfig = activeProfileId && service.profiles ? service.profiles[activeProfileId] : null;
        const allowedSides = profileConfig && Array.isArray(profileConfig.sides) ? profileConfig.sides : [];
        const prices = profileConfig ? (profileConfig.prices || {}) : {};

        const pruneUnavailableEdges = () => {
            const $row = window.activeEdgeRow;
            const allowedSet = new Set((allowedSides || []).map(v => String(v)));
            const sides = ['l1', 'w1', 'l2', 'w2'];
            let assignments = $row.data('edge-assignments') || {};
            let assignmentsChanged = false;

            sides.forEach(edge => {
                const isAllowed = allowedSet.size > 0 && allowedSet.has(edge);
                if (isAllowed) return;

                $(`.wq-edge-tab[data-edge="${edge}"]`).removeClass('active').prop('disabled', true).addClass('disabled');
                $row.find(`.wq-edge-${edge}`).prop('checked', false);
                $row.find(`.wq-code-${edge}`).text('');
                $(`.wq-code-display-${edge}`).val('');

                if (assignments && assignments[edge]) {
                    delete assignments[edge];
                    assignmentsChanged = true;
                }
            });

            if (assignmentsChanged) {
                $row.data('edge-assignments', assignments);
            }
        };

        const code = service.code || (service.name ? service.name.substring(0, 5) : '');
        if (code) {
            $('.wq-current-edge-info .wq-edge-code').text(code);
            $('.wq-current-edge-info .wq-edge-thick').text('');
        }

        window.activeEdgeRow.data('selected-edge-service', service.id || serviceId);
        window.activeEdgeRow.data('selected-edge-profile', activeProfileId || '');
        window.activeEdgeRow.data('edge-prices', prices);
        window.activeEdgeRow.data('edge-default-price', service.default_price);

        pruneUnavailableEdges();

        ['l1', 'w1', 'l2', 'w2'].forEach(edge => {
            const $tab = $(`.wq-edge-tab[data-edge="${edge}"]`);
            const $checkbox = window.activeEdgeRow.find(`.wq-edge-${edge}`);
            const isEdgeActive = $checkbox.is(':checked') || $tab.hasClass('active');
            const isAllowed = allowedSides.length === 0 ? false : allowedSides.includes(edge);
            if (!isAllowed && !isEdgeActive) {
                $tab.prop('disabled', true).addClass('disabled');
            } else {
                $tab.prop('disabled', false).removeClass('disabled');
            }
        });

        updateDiagramActiveEdges();
    }

    function applyEdgeServiceSelection(serviceId) {
        selectEdgeServiceInPopup(serviceId, true);
    }
    
    // Load Quote for Editing
    function loadQuote(quoteId) {
        console.log('Loading Quote:', quoteId);
        // Wait for products to be fetched first? 
        // We might need to wait until window.wqProducts is populated.
        // Or simply chain it.
        
        // Let's use a poller or a promise?
        // Simple: check every 500ms if products are loaded.
        const checkProducts = setInterval(function() {
            if (window.wqProducts && window.wqProducts.length > 0) {
                clearInterval(checkProducts);
                
                $.ajax({
                    url: wqBuilder.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'wq_get_quote_data',
                        nonce: wqBuilder.nonce,
                        quote_id: quoteId
                    },
                    success: function(response) {
                        if (response.success) {
                            populateBuilder(response.data);
                        } else {
                            alert('Error loading quote: ' + response.data.message);
                        }
                    }
                });
            }
        }, 500);
    }
    
    function populateBuilder(data) {
        // 1. Set Project Info
        $('#wq-project-name').val(data.project);
        $('#wq-client-name').val(data.client);
        $('#wq-client-email').val(data.email);
		$('#wq-client-phone').val(data.phone);
        $('#wq-project-notes').val(data.notes);
        if (data.notes) {
            $('.wq-notes-status').text('Note Added').show();
        }
        $('#wq-quote-ref').text(data.quote_ref);
        
        // 2. Clear Existing Rows
        $('#wq-rows-container').empty();
        rowCount = 0;
        
        // 3. Add Rows
        if (data.items && data.items.length > 0) {
            data.items.forEach(item => {
                addRow();
                const $row = $('#wq-rows-container .wq-row').last();
                
                // Set Values
                $row.find('.wq-row-length input').val(formatDimensionFromMm(item.length));
                $row.find('.wq-row-width input').val(formatDimensionFromMm(item.width));
                $row.find('.wq-row-qty input').val(item.qty);
                // Thickness is auto-set by product selection
                
                // Select Product
                // We need to simulate the click or manually set data
                const product = window.wqProducts.find(p => p.id == item.product_id);
                if (product) {
                    // Trigger selection logic
                    // We can reuse the click handler logic manually
                    $row.find('.wq-product-search').val(product.name);
                    $row.find('.wq-selected-product-id')
                        .val(product.id)
                        .data('price', product.price)
                        .data('price-mm', product.price_per_mm)
                        .data('has-edgebanding', product.has_edgebanding)
                        .data('has-operations', product.has_operations)
                        .data('operation-indexes', product.operation_indexes || [])
                        .data('has-preferred-edging', product.has_preferred_edging)
                        .data('preferred-edge-services', product.preferred_edge_services || [])
                        .data('image', product.image);

                    const map = wqBuilder.field_map || {};
                    const keyMaxLen = map.max_len || 'wq_max_length';
                    const keyMaxWid = map.max_wid || 'wq_max_width';
                    const keyMinLen = map.min_len || 'wq_min_length';
                    const keyMinWid = map.min_wid || 'wq_min_width';
                    const attrs = normalizeObject(product.custom_attributes);
                    const maxLen = parseFloat(attrs[keyMaxLen]) || 0;
                    const maxWid = parseFloat(attrs[keyMaxWid]) || 0;
                    const minLen = parseFloat(attrs[keyMinLen]) || 0;
                    const minWid = parseFloat(attrs[keyMinWid]) || 0;
                    $row.find('.wq-selected-product-id')
                        .data('max-len', maxLen)
                        .data('max-wid', maxWid)
                        .data('min-len', minLen)
                        .data('min-wid', minWid);
                        
                    $row.find('.wq-selected-product-id').data('allowed-edge-services', null);
                     
                                 var thicknessContainer = $row.find('.wq-row-thickness');
            var thicknessInput = thicknessContainer.find('.wq-thickness-input');

            thicknessInput.show().val((typeof item !== 'undefined' && item.thickness ? item.thickness : product.thickness) || '18').prop('readonly', true);

            // Set variables dynamically to the hidden input state
            $row.find('.wq-selected-product-id').data('is_variable', typeof item !== 'undefined' && item.is_variable);
            if (typeof item !== 'undefined' && item.is_variable) {
                $row.find('.wq-selected-product-id').data('variation-id', item.product_id);
            }
                     
                     // Enable Checkboxes if needed
                     if (product.has_edgebanding) {
                         $row.find('.wq-row-edge input[type="checkbox"]').prop('disabled', false);
                     }
                }

                const predefinedLabel = item.custom_fields && item.custom_fields.label_predefined ? item.custom_fields.label_predefined : '';
                const labelDescription = item.custom_fields && item.custom_fields.label_description ? item.custom_fields.label_description : '';
                const labelFallback = item.custom_fields && item.custom_fields.label ? item.custom_fields.label : '';
                if (predefinedLabel) {
                    $row.find('.wq-label-predefined-select').val(predefinedLabel);
                }
                if (labelDescription) {
                    $row.find('.wq-label-input').val(labelDescription);
                } else if (labelFallback) {
                    $row.find('.wq-label-input').val(labelFallback);
                }
                if ((predefinedLabel && predefinedLabel.trim() !== '') || ($row.find('.wq-label-input').val() || '').trim() !== '') {
                    $row.find('.wq-label-status').show();
                }
                
                const assignments = (() => {
                    if (item.edge_meta && item.edge_meta.assignments && typeof item.edge_meta.assignments === 'object') {
                        return item.edge_meta.assignments;
                    }
                    const a = {};
                    const breakdown = (item.edge_breakdown && Array.isArray(item.edge_breakdown)) ? item.edge_breakdown : (item.edges && Array.isArray(item.edges) ? item.edges : []);
                    breakdown.forEach(edge => {
                        const side = String(edge.side || '').toLowerCase();
                        if (!side) return;
                        if (edge.service_id) {
                            a[side] = {
                                serviceId: edge.service_id,
                                code: edge.code || ''
                            };
                        }
                    });
                    return a;
                })();

                const restoredServiceId = (item.edge_meta && item.edge_meta.service_id) ? item.edge_meta.service_id : (assignments.l1 && assignments.l1.serviceId ? assignments.l1.serviceId : (assignments.w1 && assignments.w1.serviceId ? assignments.w1.serviceId : (assignments.l2 && assignments.l2.serviceId ? assignments.l2.serviceId : (assignments.w2 && assignments.w2.serviceId ? assignments.w2.serviceId : ''))));
                const restoredProfileId = item.edge_meta && item.edge_meta.profile_id ? item.edge_meta.profile_id : '';

                if (restoredServiceId) $row.data('selected-edge-service', restoredServiceId);
                if (restoredProfileId) $row.data('selected-edge-profile', restoredProfileId);
                $row.data('edge-assignments', assignments);

                ['l1', 'w1', 'l2', 'w2'].forEach(side => {
                    const $checkbox = $row.find(`.wq-edge-${side}`);
                    const $rowDisplay = $row.find(`.wq-code-${side}`);
                    const hasAssign = assignments && assignments[side] && assignments[side].serviceId;
                    $checkbox.prop('checked', !!hasAssign);
                    if (hasAssign) {
                        let code = assignments[side].code || '';
                        if (!code && window.wqEdgeData && window.wqEdgeData.services && window.wqEdgeData.services[assignments[side].serviceId]) {
                            const svc = window.wqEdgeData.services[assignments[side].serviceId];
                            code = svc.code || (svc.name ? svc.name.substring(0, 5) : '');
                        }
                        $rowDisplay.text(code);
                    } else {
                        $rowDisplay.text('');
                    }
                });
            });
            
            $('#wq-quote-summary').hide();
        }
    }

    // Setup jQuery UI Sortable
    function setupSortable() {
        $('#wq-rows-container').sortable({
            handle: '.wq-row-num', // Use the number column as handle
            cursor: 'move',
            update: function(event, ui) {
                updateRowNumbers();
            }
        });
    }

    // Fetch products from API
    function fetchProducts() {
        console.log('Fetching products...');
        $.ajax({
            url: wqBuilder.ajax_url,
            type: 'POST',
            data: {
                action: 'wq_builder_get_products',
                nonce: wqBuilder.nonce
            },
            success: function(response) {
                if (response.success) {
                    console.log('Products fetched:', response.data);
                    populateProductDropdowns(response.data);
                } else {
                    console.error('Error fetching products:', response.data.message);
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', error);
            }
        });
    }

    // Populate dropdowns (placeholder for when rows are added)
    function populateProductDropdowns(data) {
        window.wqProducts = data.products;
        window.wqCategories = data.categories;
        window.wqEdgeData = data.edge_data; // Store global edge data
        
        // Populate existing rows if any
        $('#wq-rows-container .wq-row').each(function() {
            // Check if already setup to avoid duplicates if called multiple times (though unlikely here)
            if (!$(this).data('setup-complete')) {
                setupRowDropdown($(this));
                $(this).data('setup-complete', true);
            }
        });
    }

    // Add a new row
    function addRow() {
        rowCount++;
        const template = $('#wq-row-template').html();
        const $row = $(template); // Convert string to jQuery object

        // Set row ID and number
        $row.attr('data-id', rowCount);
        $row.find('.wq-row-num').text(rowCount);
        
        // Disable edgebanding checkboxes by default for new rows
        $row.find('.wq-row-edge input[type="checkbox"]').prop('disabled', true);
        
        // Setup Custom Dropdown for this row (if data is ready)
        if (window.wqProducts && window.wqCategories) {
             setupRowDropdown($row);
             $row.data('setup-complete', true);
        }

        setupLabelDropdown($row);

        $('#wq-rows-container').append($row);
        updateRowNumbers(); // Update UI state (hide/show remove buttons)
    }
    
    function setupRowDropdown($row) {
        const $input = $row.find('.wq-product-search');
        const $dropdown = $row.find('.wq-product-dropdown');
        const $catContainer = $row.find('.wq-category-filters');
        const $subcatContainer = $row.find('.wq-subcategory-filters');
        const $listContainer = $row.find('.wq-product-list');
        const $searchInput = $row.find('.wq-search-input');
        
        // Populate Categories
        if (window.wqCategories) {
            $catContainer.empty();
            $catContainer.append(`<button class="wq-cat-btn active" data-cat="all">All</button>`);
            window.wqCategories.forEach(cat => {
                $catContainer.append(`<button class="wq-cat-btn" data-cat="${cat.id}">${cat.name}</button>`);
            });
        }
        
        // Populate Initial Products
        renderProductList($listContainer, window.wqProducts);

        // Toggle Dropdown
        $input.on('click', function(e) {
            e.stopPropagation();
            $('.wq-product-dropdown').not($dropdown).hide(); // Close others
            const willShow = !$dropdown.is(':visible');
            if (!willShow) {
                $dropdown.hide();
                $row.closest('.wq-row-container').removeClass('wq-product-open');
                return;
            }

            const $container = $row.closest('.wq-row-container');
            $('.wq-row-container.wq-product-open').not($container).removeClass('wq-product-open');
            $container.addClass('wq-product-open');

            if (!$dropdown.parent().is($container)) {
                $dropdown.detach().appendTo($container);
            }

            $dropdown.show();
            setTimeout(() => {
                const $anchor = $row.find('.wq-product-search');
                if (!$anchor.length || !$dropdown.is(':visible')) return;

                $dropdown.css({ top: '100%', bottom: 'auto' });

                const dropdownTop = $dropdown.offset().top;
                const dropdownBottom = dropdownTop + $dropdown.outerHeight();
                const viewTop = $(window).scrollTop();
                const viewBottom = viewTop + $(window).height();
                const pad = 16;

                if (dropdownBottom > viewBottom - pad) {
                    $('html, body').stop(true).animate(
                        { scrollTop: Math.max(0, dropdownBottom - $(window).height() + pad) },
                        200
                    );
                }
            }, 0);
        });

        // Prevent closing when clicking inside
        $dropdown.on('click', function(e) {
            e.stopPropagation();
        });

        // Filter by Category
        $catContainer.on('click', '.wq-cat-btn', function() {
            const catId = $(this).data('cat');
            $catContainer.find('.wq-cat-btn').removeClass('active');
            $(this).addClass('active');
            
            // Handle Subcategories
            $subcatContainer.empty().hide();
            
            if (catId !== 'all') {
                const selectedCat = window.wqCategories.find(c => c.id == catId);
                if (selectedCat && selectedCat.children && selectedCat.children.length > 0) {
                    $subcatContainer.append(`<button class="wq-subcat-btn active" data-cat="${catId}">All ${selectedCat.name}</button>`);
                    selectedCat.children.forEach(child => {
                         $subcatContainer.append(`<button class="wq-subcat-btn" data-cat="${child.id}">${child.name}</button>`);
                    });
                    $subcatContainer.show();
                }
            }

            const term = $searchInput.val().toLowerCase();
            filterProducts($listContainer, catId, term);
        });
        
        // Filter by Subcategory
        $subcatContainer.on('click', '.wq-subcat-btn', function() {
            const catId = $(this).data('cat');
            $subcatContainer.find('.wq-subcat-btn').removeClass('active');
            $(this).addClass('active');
            
            const term = $searchInput.val().toLowerCase();
            filterProducts($listContainer, catId, term);
        });

        // Search Filter
        $searchInput.on('keyup', function() {
            const term = $(this).val().toLowerCase();
            let catId = $subcatContainer.find('.wq-subcat-btn.active').data('cat');
            if (!catId) {
                 catId = $catContainer.find('.wq-cat-btn.active').data('cat');
            }
            filterProducts($listContainer, catId, term);
        });

        // Select Product
        $listContainer.on('click', '.wq-product-item', function() {
            const id = $(this).data('id');
            const variationId = $(this).data('variation-id');
            const isVariable = $(this).data('is-variable');
            const name = $(this).data('name');
            const thickness = $(this).data('thickness');
            const price = $(this).data('price');
            const customAttributes = $(this).data('custom-attributes'); // New Object
            
            const hasEdgebanding = $(this).data('has-edgebanding');
            const hasOperations = $(this).data('has-operations');
            const operationIndexes = normalizeObject($(this).data('operation-indexes'));
            const hasPreferredEdging = $(this).data('has-preferred-edging');
            const preferredEdgeServices = normalizeObject($(this).data('preferred-edge-services'));
            const image = $(this).data('image');
            
            $row.find('.wq-product-search').val(name);
            $row.find('.wq-selected-product-id')
                .val(id)
                .data('price', price)
                .data('variation-id', variationId)
                .data('is_variable', isVariable)
                .data('custom-attributes', customAttributes) // Store object
                .data('has-edgebanding', hasEdgebanding)
                .data('has-operations', hasOperations)
                .data('operation-indexes', operationIndexes)
                .data('has-preferred-edging', hasPreferredEdging)
                .data('preferred-edge-services', preferredEdgeServices)
                .data('image', image);


                
            $row.find('.wq-thickness-input').val(thickness);
            
            // Set Tooltips for Min/Max Dimensions
            // Use mapped fields if available, otherwise fallback to defaults (though defaults should be passed from backend)
            const map = wqBuilder.field_map || {};
            const keyMaxLen = map.max_len || 'wq_max_length';
            const keyMaxWid = map.max_wid || 'wq_max_width';
            const keyMinLen = map.min_len || 'wq_min_length';
            const keyMinWid = map.min_wid || 'wq_min_width';

            const maxLen = parseFloat(customAttributes[keyMaxLen]) || 0;
            const maxWid = parseFloat(customAttributes[keyMaxWid]) || 0;
            const minLen = parseFloat(customAttributes[keyMinLen]) || 0;
            const minWid = parseFloat(customAttributes[keyMinWid]) || 0;

            $row.find('.wq-selected-product-id')
                .data('min-len', minLen)
                .data('max-len', maxLen)
                .data('min-wid', minWid)
                .data('max-wid', maxWid);
            
            let lenTitle = 'Length';
            if (minLen > 0 || maxLen > 0) {
                lenTitle += ' (';
                if (minLen > 0) lenTitle += `Min: ${formatDimensionValue(minLen)}${getDimensionUnitLabel()}`;
                if (minLen > 0 && maxLen > 0) lenTitle += ', ';
                if (maxLen > 0) lenTitle += `Max: ${formatDimensionValue(maxLen)}${getDimensionUnitLabel()}`;
                lenTitle += ')';
            }
            $row.find('.wq-row-length input').attr('title', lenTitle);
            
            let widTitle = 'Width';
            if (minWid > 0 || maxWid > 0) {
                widTitle += ' (';
                if (minWid > 0) widTitle += `Min: ${formatDimensionValue(minWid)}${getDimensionUnitLabel()}`;
                if (minWid > 0 && maxWid > 0) widTitle += ', ';
                if (maxWid > 0) widTitle += `Max: ${formatDimensionValue(maxWid)}${getDimensionUnitLabel()}`;
                widTitle += ')';
            }
            $row.find('.wq-row-width input').attr('title', widTitle);

            // Enable/Disable Edgebanding Checkboxes
            const $edgeCheckboxes = $row.find('.wq-row-edge input[type="checkbox"]');
            if (hasEdgebanding) {
                $edgeCheckboxes.prop('disabled', false);
            } else {
                $edgeCheckboxes.prop('disabled', true).prop('checked', false);
            }
            
            $dropdown.hide();
            updateRowClipboardUI();
        });
    }

    function renderProductList($container, products) {
        $container.empty();
        if (!products || products.length === 0) {
            $container.append('<div style="padding:10px; color:#999;">No products found.</div>');
            return;
        }
        
        products.forEach(product => {
            const imageHtml = product.image ? `<img src="${product.image}" class="wq-p-image" alt="${product.name}">` : '';
            const customAttributes = product.custom_attributes ? JSON.stringify(product.custom_attributes) : '{}';
            
            const thickness = product.custom_attributes && product.custom_attributes.thickness ? product.custom_attributes.thickness : (product.thickness || '');
            const code = product.sku || ''; 
            
            if (product.is_variable && product.variations && product.variations.length > 0) {
                product.variations.forEach(variation => {
                    const varName = product.name + (variation.thickness ? ' - ' + variation.thickness + 'mm' : ' - ' + variation.sku);
                    const varCode = variation.sku || '';
                    $container.append(`
                        <div class="wq-product-item" data-id="${product.id}" data-variation-id="${variation.id}" data-is-variable="true" data-name="${varName}" data-thickness="${variation.thickness}" data-price="${variation.price}" data-custom-attributes='${customAttributes}' data-has-edgebanding="${product.has_edgebanding}" data-has-operations="${product.has_operations}" data-operation-indexes='${JSON.stringify(product.operation_indexes || [])}' data-has-preferred-edging="${product.has_preferred_edging}" data-preferred-edge-services='${JSON.stringify(product.preferred_edge_services || [])}' data-image="${product.image || ''}">
                            <div class="wq-p-left">
                                ${imageHtml}
                                <span class="wq-p-name">${varName}</span>
                            </div>
                            <span class="wq-p-meta">${varCode} ${variation.thickness ? variation.thickness + 'mm' : ''}</span>
                        </div>
                    `);
                });
            } else {
                $container.append(`
                    <div class="wq-product-item" data-id="${product.id}" data-is-variable="false" data-name="${product.name}" data-thickness="${thickness}" data-price="${product.price}" data-custom-attributes='${customAttributes}' data-has-edgebanding="${product.has_edgebanding}" data-has-operations="${product.has_operations}" data-operation-indexes='${JSON.stringify(product.operation_indexes || [])}' data-has-preferred-edging="${product.has_preferred_edging}" data-preferred-edge-services='${JSON.stringify(product.preferred_edge_services || [])}' data-image="${product.image || ''}">
                        <div class="wq-p-left">
                            ${imageHtml}
                            <span class="wq-p-name">${product.name}</span>
                        </div>
                        <span class="wq-p-meta">${code}</span>
                    </div>
                `);
            }
        });
    }

    // Helper: Get all descendant category IDs
    function getAllDescendantIds(catId, categories) {
        let ids = [parseInt(catId)];
        
        // Helper to find the node
        function findNode(id, nodes) {
            if (!nodes) return null;
            for (let node of nodes) {
                if (node.id == id) return node;
                if (node.children && node.children.length > 0) {
                    const found = findNode(id, node.children);
                    if (found) return found;
                }
            }
            return null;
        }
        
        // Helper to collect all children IDs
        function collectIds(node) {
            if (node.children && node.children.length > 0) {
                node.children.forEach(child => {
                    ids.push(parseInt(child.id));
                    collectIds(child);
                });
            }
        }
        
        const node = findNode(catId, categories);
        if (node) {
            collectIds(node);
        }
        
        console.log('Category Filter Debug:', { catId, ids });
        return ids;
    }

    function filterProducts($container, catId, term) {
        if (!window.wqProducts) return;
        
        // Get all relevant category IDs (selected + children)
        let relevantCatIds = [];
        if (catId !== 'all') {
            relevantCatIds = getAllDescendantIds(catId, window.wqCategories);
        }
        
        // Render filtered list
        const $listContainer = $container; // Reuse container reference
        $listContainer.empty();
        
        const filtered = window.wqProducts.filter(product => {
            // Check category (allowing for 'all' or matching category ID or children)
            let matchesCat = false;
            if (catId === 'all') {
                matchesCat = true;
            } else {
                 // Check if ANY of the product's categories are in the relevantCatIds list
                 if (product.categories) {
                     matchesCat = product.categories.some(cId => relevantCatIds.includes(parseInt(cId)));
                 }
            }
            
            // Check search term (name or SKU)
            const matchesTerm = !term || 
                                (product.name && product.name.toLowerCase().includes(term)) || 
                                (product.sku && product.sku.toLowerCase().includes(term));
                                
            return matchesCat && matchesTerm;
        });
        
        renderProductList($container, filtered);
    }

    // Add default rows (e.g., 1 row)
    function addDefaultRows() {
        for (let i = 0; i < 1; i++) {
            addRow();
        }
    }

    // Generate Random Quote Ref
    function generateQuoteRef() {
        // Generate a random 7-character alphanumeric string
        return Math.random().toString(36).substring(2, 9);
    }

    // Get Pricing
    function getPricing() {
        // --- Validation: Project & Client Name ---
        const projectName = $('#wq-project-name').val().trim();
        const clientName = $('#wq-client-name').val().trim();
        const email = $('#wq-client-email').val().trim();
        const phone = $('#wq-client-phone').val().trim();
		
		const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/;
        const ukPhoneRegex = /^(\+44\s?7\d{3}|\(?07\d{3}\)?)\s?\d{3}\s?\d{3}$/;
        
        if (projectName === '') {
            alert('Please enter the project name before getting pricing.');
            $('#wq-project-name').focus();
            return;
        }
        
        if (clientName === '') {
            alert('Please enter the client name before getting pricing.');
            $('#wq-client-name').focus();
            return;
        }
        
        if (email === '') {
            alert('Please enter the email before getting pricing.');
            $('#wq-client-email').focus();
            return;
        }
		
		if (!emailRegex.test(email)) {
            alert('Please enter a valid email address.');
			$('#wq-client-email').focus();
            return;
         }
        
        if (phone === '') {
            alert('Please enter the phone before getting pricing.');
            $('#wq-client-phone').focus();
            return;
        }
		
		if (!ukPhoneRegex.test(phone)) {
           alert('Please enter a valid UK phone number (e.g. +44 7XXX XXX XXX or 07XXX XXXXXX).');
		   $('#wq-client-phone').focus();
           return;
        }
        
        let total = 0;
        let rows = [];
        let items = []; // Define items array here
        let hasError = false;

        $('#wq-rows-container .wq-row').each(function() {
            const row = $(this);
            const productId = row.find('.wq-selected-product-id').val();
            const productName = row.find('.wq-product-search').val();
            
            // Get data from selected product
            const productData = row.find('.wq-selected-product-id');
            const standardPrice = parseFloat(productData.data('price')) || 0;
            const customAttributes = normalizeObject(productData.data('custom-attributes'));
            const image = productData.data('image'); // Defined here
            
            // Use mapped fields
            const map = wqBuilder.field_map || {};
            const keyMaxLen = map.max_len || 'wq_max_length';
            const keyMaxWid = map.max_wid || 'wq_max_width';
            const keyMinLen = map.min_len || 'wq_min_length';
            const keyMinWid = map.min_wid || 'wq_min_width';

            const maxLen = parseFloat(customAttributes[keyMaxLen]) || 0;
            const maxWid = parseFloat(customAttributes[keyMaxWid]) || 0;
            const minLen = parseFloat(customAttributes[keyMinLen]) || 0;
            const minWid = parseFloat(customAttributes[keyMinWid]) || 0;
            
            const length = parseFloat(row.find('.wq-row-length input').val()) || 0;
            const width = parseFloat(row.find('.wq-row-width input').val()) || 0;
            const qty = parseInt(row.find('.wq-row-qty input').val()) || 0;
            const thickness = row.find('.wq-thickness-input').val(); // DEFINED HERE
            const lengthMm = dimensionToMm(length);
            const widthMm = dimensionToMm(width);
            
            // Check if product is selected
            if (!productId) return; // Skip if no product

            // Validation: Check if fields are filled
            if (length <= 0 || width <= 0 || qty <= 0) {
                alert(`Please enter valid Length, Width, and Quantity for row ${row.find('.wq-row-num').text()}.`);
                hasError = true;
                return false; // Break loop
            }

            if (productId && qty > 0) {
                // Validation: Check Max Dimensions
            if (maxLen > 0 && length > maxLen) {
                alert(`Length for ${productName} exceeds maximum allowed (${formatDimensionValue(maxLen)}${getDimensionUnitLabel()}).`);
                hasError = true;
                return false; // Break loop
            }
            if (maxWid > 0 && width > maxWid) {
                alert(`Width for ${productName} exceeds maximum allowed (${formatDimensionValue(maxWid)}${getDimensionUnitLabel()}).`);
                hasError = true;
                return false; // Break loop
            }
            
            // Validation: Check Min Dimensions
            if (minLen > 0 && length < minLen) {
                alert(`Length for ${productName} must be at least ${formatDimensionValue(minLen)}${getDimensionUnitLabel()}.`);
                hasError = true;
                return false; // Break loop
            }
            if (minWid > 0 && width < minWid) {
                alert(`Width for ${productName} must be at least ${formatDimensionValue(minWid)}${getDimensionUnitLabel()}.`);
                hasError = true;
                return false; // Break loop
            }

                // Calculation: Dynamic Formula
                // Formula is stored in wqBuilder.pricing_formula
                // Variables: {length}, {width}, {qty}, {slugs...}
                
                // Get Dynamic Values
                // const customAttributes = productData.data('custom-attributes'); // Should be object // Already defined above
                
                let formula = wqBuilder.pricing_formula;
                
                // Replace System Variables
                const lengthM = lengthMm / 1000;
                const widthM = widthMm / 1000;
                const areaMm2 = lengthMm * widthMm;
                const areaM2 = lengthM * widthM;
                const perimeterMm = (2 * lengthMm) + (2 * widthMm);
                const perimeterM = perimeterMm / 1000;
                let basePriceVal = parseFloat(row.find('.wq-selected-product-id').data('price')) || 0;

                const pricePerMm2 = (() => {
                    const raw = customAttributes && customAttributes.wq_pricing_per_mm !== undefined ? customAttributes.wq_pricing_per_mm : (customAttributes ? customAttributes.price_per_mm2 : undefined);
                    const v = parseFloat(raw);
                    return isNaN(v) ? 0 : v;
                })();
                const pricePerM2 = (() => {
                    const raw = customAttributes ? customAttributes.price_per_m2 : undefined;
                    const v = parseFloat(raw);
                    if (!isNaN(v)) return v;
                    return pricePerMm2 * 1000000;
                })();

                formula = replaceToken(formula, 'length', length);
                formula = replaceToken(formula, 'width', width);
                formula = replaceToken(formula, 'qty', qty);
                formula = replaceToken(formula, 'length_mm', lengthMm);
                formula = replaceToken(formula, 'width_mm', widthMm);
                formula = replaceToken(formula, 'length_m', lengthM);
                formula = replaceToken(formula, 'width_m', widthM);
                formula = replaceToken(formula, 'area_mm2', areaMm2);
                formula = replaceToken(formula, 'area_m2', areaM2);
                formula = replaceToken(formula, 'perimeter_mm', perimeterMm);
                formula = replaceToken(formula, 'perimeter_m', perimeterM);
                formula = replaceToken(formula, 'price', basePriceVal);
                formula = replaceToken(formula, 'price_per_mm2', pricePerMm2);
                formula = replaceToken(formula, 'price_per_m2', pricePerM2);
                
                // Replace Custom Variables
                if (wqBuilder.custom_fields && wqBuilder.custom_fields.length > 0) {
                    wqBuilder.custom_fields.forEach(field => {
                        const slug = field.slug;
                        let val = 0;
                        
                        // Check if we have this value in product data
                        let raw = customAttributes && customAttributes[slug] !== undefined ? customAttributes[slug] : undefined;
                        if (raw === undefined || raw === null || raw === '') {
                            raw = field && field.placeholder !== undefined ? field.placeholder : 0;
                        }
                        val = parseFloat(raw);
                        if (isNaN(val)) val = 0;
                        if (slug === 'wq_pricing_per_mm' && val <= 0) {
                            val = parseFloat(field && field.placeholder !== undefined ? field.placeholder : 0);
                            if (isNaN(val)) val = 0;
                        }
                        
                        // If the field type is text, it shouldn't be in formula usually, 
                        // but if it is, we replace it. If it's number, we use the value.
                        
                        formula = replaceToken(formula, slug, val);
                    });
                }
                
                let rowTotal = evaluateMathExpression(formula);
                if (isNaN(rowTotal)) rowTotal = 0;
                
                // Legacy Fallback if formula is broken or not set?
                // No, we rely on the formula now.
                
                // --- Edgebanding Calculation ---
                // ... (Edge calculation code follows, unchanged in this replacement block)
                // ... But wait, I need to include the rest of the block to match the search/replace range?
                // No, I can just replace the top part where `thickness` was missing.
                
                // Actually, I need to make sure I don't cut off the rest of the function logic if I only replace the top.
                // The Read output stopped at line 849.
                // The SearchReplace should target the start of the function and include the definition of thickness.
                
                // I will target the top of the getPricing function down to "if (productId && qty > 0) {"
                
            // ... (rest of logic)
            // Wait, the error is at `items.push({ ..., thickness: thickness, ... })`
            // So `thickness` needs to be defined in the scope where `items.push` is called.
            // In my previous `Read` output (lines 550-849), `const thickness` was NOT defined in the variable block.
            // It was missing!
            


                
                // --- Edgebanding Calculation ---
                let edgeCost = 0;
                let edgeDescription = '';
                let edgeBreakdown = [];
                // const edgePrices = row.data('edge-prices'); // Legacy/Global prices (might be stale for mixed services)
                // const defaultEdgePrice = parseFloat(row.data('edge-default-price')) || 0;
                const selectedServiceId = row.data('selected-edge-service'); // Global fallback
                const selectedProfileId = row.data('selected-edge-profile'); // Capture Profile ID
                const assignments = row.data('edge-assignments') || {};
                
                let edgeMetaSides = {l1: false, w1: false, l2: false, w2: false};
                
                // Check if any edging is active (either global selectedServiceId or assignments)
                const hasAssignments = Object.keys(assignments).length > 0;
                
                if (selectedServiceId || hasAssignments) {
                    const sides = ['l1', 'w1', 'l2', 'w2'];
                    
                    sides.forEach(side => {
                        if (row.find(`.wq-edge-${side}`).is(':checked')) {
                            edgeMetaSides[side] = true;
                            
                            // Determine Service for this side
                            let sideServiceId = selectedServiceId;
                            let sideCode = '';
                            
                            if (assignments[side] && assignments[side].serviceId) {
                                sideServiceId = assignments[side].serviceId;
                            }
                            
                            if (!sideServiceId || !window.wqEdgeData || !window.wqEdgeData.services[sideServiceId]) {
                                return; // Skip if no valid service found
                            }
                            
                            const service = window.wqEdgeData.services[sideServiceId];
                            let serviceName = service.name;
                            
                            const sideLenMm = (side === 'l1' || side === 'l2') ? lengthMm : widthMm;
                            const sideLenM = sideLenMm / 1000;
                            
                            // Determine Price
                            let p = parseFloat(service.default_price) || 0;
                            
                            // Check profile specific price
                            if (selectedProfileId && service.profiles && service.profiles[selectedProfileId]) {
                                const profileConfig = service.profiles[selectedProfileId];
                                if (profileConfig.prices && profileConfig.prices[side]) {
                                    const pp = parseFloat(profileConfig.prices[side]);
                                    if (!isNaN(pp)) p = pp;
                                }
                            }

                            let opCost = 0;
                            let opName = '';
                            let opIdxVal = '';
                            if (selectedProfileId && service.profiles && service.profiles[selectedProfileId]) {
                                const profileConfig = service.profiles[selectedProfileId];
                                if (profileConfig.operations && profileConfig.operations[side] !== undefined && profileConfig.operations[side] !== '') {
                                    const opIdx = profileConfig.operations[side];
                                    opIdxVal = opIdx;
                                    const op = getOperationByIndex(opIdx);
                                    if (op) {
                                        opName = op.name;
                                        if (op.type === 'meter') {
                                            opCost = op.price * sideLenM;
                                        } else {
                                            opCost = op.price;
                                        }
                                    }
                                }
                            }
                            
                            // Calculate Cost using Dynamic Formula
                            let eFormula = buildEdgeFormulaForSide(side, lengthMm, widthMm, sideLenMm);
                            eFormula = replaceToken(eFormula, 'price', p);
                            eFormula = replaceToken(eFormula, 'qty', qty);
                            const sideCostBase = evaluateMathExpression(eFormula);
                            const totalOpCost = opCost * qty;
                            const sideCost = sideCostBase + totalOpCost;
                            
                            // Add to Total Edge Cost
                            edgeCost += sideCost;
                            
                            // Push breakdown
                            edgeBreakdown.push({
                                service_id: sideServiceId,
                                code: sideCode || service.code || (service.name ? service.name.substring(0, 5) : ''),
                                name: serviceName,
                                op_name: opName,
                                op_idx: opIdxVal,
                                side: side.toUpperCase(),
                                cost: sideCost.toFixed(2),
                                op_cost: totalOpCost.toFixed(2)
                            });
                        }
                    });
                }

                edgeCost = edgeBreakdown.reduce((sum, edge) => sum + (parseFloat(edge.cost) || 0), 0);
                
                // --- Labelling Info ---
                const labelPredefined = row.find('.wq-label-predefined-select').val() || '';
                const labelText = row.find('.wq-label-input').val() || '';
                const labelCombined = [labelPredefined.trim(), labelText.trim()].filter(Boolean).join(' - ');
                let labelDescription = '';
                if (labelCombined) {
                    labelDescription = `<br><small>Label: ${labelCombined}</small>`;
                }

                const hasOperations = productData.data('has-operations');
                const operationIndexes = productData.data('operation-indexes');
                const ops = hasOperations ? getOperationsByIndexes(operationIndexes) : [];
                const opsCalc = calculateOperationsCost(ops, qty, { perimeterM, areaM2 });
                const operationsCost = opsCalc.total;
                
                // Calculate Final Row Total (Base + Extras)
                const rowFinalTotal = rowTotal + edgeCost + operationsCost;
                
                // Add to Grand Total Accumulator
                total += rowFinalTotal;

                items.push({
                    product_id: productId,
                    product_name: productName,
                    length: lengthMm,
                    width: widthMm,
                    qty: qty,
                    thickness: thickness,
                    price: rowFinalTotal.toFixed(2), // Correct Total
                    edges: edgeBreakdown,
                    operations: opsCalc.lines,
                    operations_total: operationsCost,
                    image: image,
                    // Save Meta for Restoration
                    edge_meta: {
                        service_id: selectedServiceId,
                        profile_id: selectedProfileId,
                        sides: edgeMetaSides,
                        label: labelCombined,
                        label_predefined: labelPredefined,
                        label_description: labelText
                    }
                });

                rows.push({
                    product: productName + labelDescription,
                    dimensions: `${length} x ${width}`,
                    qty: qty,
                    price: rowTotal.toFixed(2),
                    length_mm: lengthMm,
                    width_mm: widthMm,
                    edges: edgeBreakdown,
                    operations: opsCalc.lines,
                    operations_total: operationsCost
                });
            }
        });

        if (hasError) return;
        
        // Items empty check moved inside getPricing or here?
        if (items.length === 0) {
             // It's okay if just calculating, but maybe alert?
             // Actually, if calculating, we just show empty summary or alert.
             // Let's alert.
             alert('Please select products and enter valid dimensions.');
             return;
        }
        
        // Define quoteRef locally
        let quoteRef = $('#wq-quote-ref').text();
        if (!quoteRef) {
             quoteRef = generateQuoteRef();
             $('#wq-quote-ref').text(quoteRef); // Update Header
        }

        displaySummary(rows, total, quoteRef, projectName, clientName);
    }

    function displaySummary(rows, total, quoteRef, projectName, clientName) {
        const container = $('#wq-quote-summary');
        container.empty();
        container.removeClass().addClass('wq-summary-container');
        
        const currency = wqBuilder.currency_symbol || '$';
        const keepOffcutsEnabled = String(wqBuilder.keep_offcuts_enable || '0') === '1';
        const keepOffcutsTooltip = String(wqBuilder.keep_offcuts_tooltip || '');
        const keepOffcutsTooltipAttr = keepOffcutsTooltip
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
        // VAT removed as per request
        // const vatPercent = parseFloat(wqBuilder.vat_percentage) || 0;
        // const vatAmount = total * (vatPercent / 100);
        const grandTotal = rows.reduce((sum, row) => {
            const base = parseFloat(row.price) || 0;
            const edgesTotal = (row.edges && Array.isArray(row.edges)) ? row.edges.reduce((s, e) => s + (parseFloat(e.cost) || 0), 0) : 0;
            const opsTotal = parseFloat(row.operations_total) || 0;
            return sum + base + edgesTotal + opsTotal;
        }, 0);
        
        // Get Project Notes
        const projectNotes = $('#wq-project-notes').val();

        if (rows.length === 0) {
            alert('Please select products and enter valid dimensions.');
            return;
        }

        let html = `
            <div class="wq-summary-header">Quote Pricing: ${quoteRef}</div>
            <div style="margin-bottom: 15px; padding-bottom: 10px; border-bottom: 1px solid #eee;">
                <strong>Project:</strong> ${projectName}<br>
                <strong>Client:</strong> ${clientName}
        `;
        
        if (projectNotes) {
            html += `<br><strong>Notes:</strong> <span style="color:#555;">${projectNotes}</span>`;
        }
        
        html += `
            </div>
            
            <div class="wq-summary-section-title">BOARD</div>
        `;

        const edgingAgg = {};
        const opsAgg = {};

        const formatLen = (m) => {
            const v = parseFloat(m) || 0;
            const s = v.toFixed(3);
            return s.replace(/\.?0+$/, '');
        };

        rows.forEach(row => {
            html += `
                <div class="wq-summary-table-row">
                    <div class="wq-summary-item-name">
                        ${row.product} <br>
                        <small>Dims: ${row.dimensions} | Qty: ${row.qty}</small>
                    </div>
                    <div class="wq-summary-item-price">
                        ${currency}${row.price}
                    </div>
                </div>
            `;

            if (row.edges && Array.isArray(row.edges) && row.edges.length > 0) {
                row.edges.forEach(edge => {
                    const code = edge && edge.code ? String(edge.code) : '';
                    const name = edge && edge.name ? String(edge.name) : '';
                    const key = `${code}||${name}`;
                    const totalSideCost = parseFloat(edge && edge.cost) || 0;
                    const opCost = parseFloat(edge && edge.op_cost) || 0;
                    const pureEdgeCost = Math.max(0, totalSideCost - opCost);

                    if (!edgingAgg[key]) edgingAgg[key] = { code: code, name: name, cost: 0, meters: 0 };
                    edgingAgg[key].cost += pureEdgeCost;

                    const side = edge && edge.side ? String(edge.side).toUpperCase() : '';
                    const qty = parseInt(row.qty) || 0;
                    const lenMm = (side === 'L1' || side === 'L2') ? (parseFloat(row.length_mm) || 0) : (parseFloat(row.width_mm) || 0);
                    edgingAgg[key].meters += (lenMm / 1000) * qty;

                    if (edge && edge.op_name) {
                        const opName = String(edge.op_name);
                        if (!opsAgg[opName]) opsAgg[opName] = 0;
                        opsAgg[opName] += opCost;
                    }
                });
            }

            if (row.operations && Array.isArray(row.operations) && row.operations.length > 0) {
                row.operations.forEach(op => {
                    const name = op && op.name ? String(op.name) : '';
                    const cost = op && op.cost ? parseFloat(op.cost) : 0;
                    if (!name || cost <= 0) return;
                    if (!opsAgg[name]) opsAgg[name] = 0;
                    opsAgg[name] += cost;
                });
            }
        });

        const edgingEntries = Object.values(edgingAgg).filter(e => (parseFloat(e.cost) || 0) > 0);
        if (edgingEntries.length > 0) {
            html += `<div class="wq-summary-section-title" style="margin-top:15px;">EDGING</div>`;
            edgingEntries
                .sort((a, b) => String(a.code || a.name).localeCompare(String(b.code || b.name)))
                .forEach(e => {
                    const label = `${e.code ? (e.code + ' - ') : ''}${e.name}${e.meters > 0 ? ' (' + formatLen(e.meters) + 'm)' : ''}`;
                    html += `
                        <div class="wq-summary-table-row" style="background-color: #f9f9f9; padding-left: 10px; border-bottom: 1px solid #eee;">
                            <div class="wq-summary-item-name" style="font-size: 0.9em; color: #555;">
                                ${label}
                            </div>
                            <div class="wq-summary-item-price" style="font-size: 0.9em; color: #555;">
                                ${currency}${parseFloat(e.cost).toFixed(2)}
                            </div>
                        </div>
                    `;
                });
        }

        const opsEntries = Object.entries(opsAgg).filter(([, c]) => (parseFloat(c) || 0) > 0);
        if (opsEntries.length > 0) {
            html += `<div class="wq-summary-section-title" style="margin-top:15px;">OPERATIONS</div>`;
            opsEntries
                .sort(([a], [b]) => String(a).localeCompare(String(b)))
                .forEach(([name, cost]) => {
                    html += `
                        <div class="wq-summary-table-row" style="background-color: #f9f9f9; padding-left: 10px; border-bottom: 1px solid #eee;">
                            <div class="wq-summary-item-name" style="font-size: 0.9em; color: #555;">
                                ${name}
                            </div>
                            <div class="wq-summary-item-price" style="font-size: 0.9em; color: #555;">
                                ${currency}${parseFloat(cost).toFixed(2)}
                            </div>
                        </div>
                    `;
                });
        }
        
        // Removed static Operations
        
        // Totals Section
        html += `
            <div style="margin-top: 20px; border-top: 2px solid #ddd; padding-top: 10px;">
                <div class="wq-summary-total-row wq-keep-offcuts-line" style="${window.wqKeepOffcuts ? '' : 'display:none;'}">
                    <span class="wq-summary-total-label">KEEP OFFCUTS</span>
                    <span>Yes</span>
                </div>
                <div class="wq-summary-total-row">
                    <span class="wq-summary-total-label">TOTAL (Excluding VAT) = </span>
                    <span>${currency}${grandTotal.toFixed(2)}</span>
                </div>
            </div>
            
            <div class="wq-summary-actions">
                 ${keepOffcutsEnabled ? `
                    <label class="wq-offcuts-option">
                        <input type="checkbox" id="wq-keep-offcuts" ${window.wqKeepOffcuts ? 'checked' : ''}>
                        <span>Keep Offcuts</span>
                        <span class="dashicons dashicons-info-outline wq-info-icon" data-tooltip="${keepOffcutsTooltipAttr}"></span>
                    </label>
                 ` : ''}
                 <button class="wq-btn-basket">ADD QUOTE TO BASKET</button>
                 ${wqBuilder.is_user_logged_in === '1' ? '<button class="wq-btn-save">SAVE QUOTE</button>' : ''}
                 <button class="wq-btn-pdf" id="wq-generate-pdf">GENERATE PDF</button>
            </div>
        `;

        container.html(html);
        container.slideDown();
        
        // Scroll to summary
        $('html, body').animate({
            scrollTop: container.offset().top
        }, 500);
        
        // Re-bind events for new buttons (Use delegated events to ensure they work even if elements are re-created)
        container.off('click', '.wq-btn-basket').on('click', '.wq-btn-basket', function(e) {
            e.preventDefault();
            addToBasket();
        });
        
        container.off('click', '.wq-btn-save').on('click', '.wq-btn-save', function(e) {
            e.preventDefault();
            submitQuote();
        });

        container.off('click', '#wq-generate-pdf').on('click', '#wq-generate-pdf', function(e) {
            e.preventDefault();
            generatePDF();
        });

        container.off('change', '#wq-keep-offcuts').on('change', '#wq-keep-offcuts', function() {
            window.wqKeepOffcuts = $(this).is(':checked');
            container.find('.wq-keep-offcuts-line').toggle(!!window.wqKeepOffcuts);
        });
    }
    
    // Helper: Gather Quote Data (Consolidated)
    function gatherQuoteData() {
        const $clientName = $('#wq-client-name');
        const $projectName = $('#wq-project-name');
        
        const clientName = $clientName.length ? ($clientName.val() || '').trim() : '';
        const projectName = $projectName.length ? ($projectName.val() || '').trim() : '';
        const email = ''; 
        
        const $notes = $('#wq-project-notes');
        const notesVal = $notes.length ? $notes.val() : '';
        
        let quoteRef = $('#wq-quote-ref').text();
        if (!quoteRef) {
             quoteRef = generateQuoteRef();
             $('#wq-quote-ref').text(quoteRef);
        }

        // Use the centralized item gathering logic to ensure price consistency
        const items = getQuoteDataRows();
        
        if (!items || items.length === 0) {
            alert('Please add at least one item.');
            return null;
        }

        return {
            project: projectName,
            client: clientName,
            email: email,
            notes: notesVal,
            quote_ref: quoteRef,
            keep_offcuts: window.wqKeepOffcuts ? 1 : 0,
            items: items
        };
    }
    
    // Submit Quote (Save)
    function submitQuote() {
        const quoteData = gatherQuoteData();
        if (!quoteData) return;
        
        const $btn = $('.wq-btn-save'); // Target the summary button
        const originalText = $btn.text();
        $btn.prop('disabled', true).text('Saving...');

        $.ajax({
            url: wqBuilder.ajax_url,
            type: 'POST',
            data: {
                action: 'wq_builder_submit_quote',
                nonce: wqBuilder.nonce,
                quote_data: quoteData
            },
            success: function(response) {
                 $btn.prop('disabled', false).text(originalText);
                if (response.success) {
                    showNotification('Quote has been saved under My Account > Saved Quotes.', 'success');
                } else {
                    showNotification('Error: ' + (response.data ? response.data.message : 'Unknown Error'), 'error');
                }
            },
            error: function(xhr, status, error) {
                 $btn.prop('disabled', false).text(originalText);
                 console.error("AJAX Error:", status, error, xhr.responseText);
                 showNotification('Server Error: ' + error, 'error');
            }
        });
    }
    
    // Add to Basket
    function addToBasket() {
        const quoteData = gatherQuoteData();
        if (!quoteData) return;
        
        const $btn = $('.wq-btn-basket');
        const originalText = $btn.text();
        $btn.prop('disabled', true).text('Adding...');
        
        $.ajax({
            url: wqBuilder.ajax_url,
            type: 'POST',
            data: {
                action: 'wq_builder_add_to_basket',
                nonce: wqBuilder.nonce,
                quote_data: quoteData
            },
            success: function(response) {
                 $btn.prop('disabled', false).text(originalText);
                if (response.success) {
                    showNotification('Quote added to basket!', 'success');
                    if(response.data.redirect_url) {
                        setTimeout(function() {
                            window.location.href = response.data.redirect_url;
                        }, 1000);
                    }
                } else {
                    showNotification('Error: ' + (response.data ? response.data.message : 'Unknown Error'), 'error');
                }
            },
            error: function(xhr, status, error) {
                 $btn.prop('disabled', false).text(originalText);
                 console.error("AJAX Error:", status, error, xhr.responseText);
                 showNotification('Server Error: ' + error, 'error');
            }
        });
    }

    // Add to Basket
    $(document).on('click', '#wq-add-to-basket', function(e) {
        e.preventDefault();
        const rows = getQuoteDataRows(); // Helper function to get data
        if (!rows || rows.length === 0) {
            alert('No items to add.');
            return;
        }
        
        const $btn = $(this);
        const originalText = $btn.text();
        $btn.prop('disabled', true).text('ADDING...');
        
        // Prepare Data
        const quoteData = {
            project: $('#wq-project-name').val() || 'Untitled Project',
            client: $('#wq-client-name').val() || 'Guest',
            quote_ref: $('#wq-quote-ref').text(),
            items: rows
        };

        $.ajax({
            url: wqBuilder.ajax_url,
            type: 'POST',
            data: {
                action: 'wq_builder_add_to_basket',
                nonce: wqBuilder.nonce,
                quote_data: quoteData
            },
            success: function(response) {
                if (response.success) {
                    // Redirect to Cart
                    window.location.href = response.data.redirect_url;
                } else {
                    alert(response.data.message || 'Error adding to basket.');
                    $btn.text(originalText).prop('disabled', false);
                }
            },
            error: function() {
                alert('Server error.');
                $btn.text(originalText).prop('disabled', false);
            }
        });
    });

    // Helper to extract row data for submission
    function getQuoteDataRows() {
        let items = [];
        $('#wq-rows-container .wq-row').each(function() {
            const row = $(this);
            const pid = row.find('.wq-selected-product-id').val();
            const qty = parseInt(row.find('.wq-row-qty input').val()) || 0;
            
            if (pid && qty > 0) {
                // Re-calculate row data to ensure fresh breakdown
                const length = parseFloat(row.find('.wq-row-length input').val()) || 0;
                const width = parseFloat(row.find('.wq-row-width input').val()) || 0;
                const thickness = row.find('.wq-thickness-input').val();
                const lengthMm = dimensionToMm(length);
                const widthMm = dimensionToMm(width);
                
                // Get Label / Notes
                const labelPredefined = row.find('.wq-label-predefined-select').val() || '';
                const labelText = row.find('.wq-label-input').val() || '';
                const labelCombined = [labelPredefined.trim(), labelText.trim()].filter(Boolean).join(' - ');
                
                // --- Recalculate Edging Breakdown for this row ---
                let edgeBreakdown = [];
                let edgeCost = 0;
                
                const selectedServiceId = row.data('selected-edge-service');
                const selectedProfileId = row.data('selected-edge-profile');
                const assignments = row.data('edge-assignments') || {};
                
                const hasAssignments = Object.keys(assignments).length > 0;
                
                // Force check even if selectedServiceId is null but assignments exist
                if (selectedServiceId || hasAssignments) {
                    const sides = ['l1', 'w1', 'l2', 'w2'];
                    sides.forEach(side => {
                        // Check if the side is "active" via assignment OR global checkbox
                        // The checkbox might be unchecked visually but assignment data exists?
                        // Or vice versa. The checkbox is the source of truth for "is active".
                        const isChecked = row.find(`.wq-edge-${side}`).is(':checked');
                        
                        if (isChecked) {
                            let sideServiceId = selectedServiceId; // Fallback to global
                            let sideCode = '';
                            
                            // Prefer specific assignment
                            if (assignments[side] && assignments[side].serviceId) {
                                sideServiceId = assignments[side].serviceId;
                                sideCode = assignments[side].code;
                            }
                            
                            if (sideServiceId && window.wqEdgeData && window.wqEdgeData.services[sideServiceId]) {
                                const service = window.wqEdgeData.services[sideServiceId];
                                const sideLenMm = (side === 'l1' || side === 'l2') ? lengthMm : widthMm;
                                const sideLenM = sideLenMm / 1000;
                                let p = parseFloat(service.default_price) || 0;
                                
                                if (selectedProfileId && service.profiles && service.profiles[selectedProfileId]) {
                                    const profileConfig = service.profiles[selectedProfileId];
                                    if (profileConfig.prices && profileConfig.prices[side]) {
                                        const pp = parseFloat(profileConfig.prices[side]);
                                        if (!isNaN(pp)) p = pp;
                                    }
                                }

                                let opCost = 0;
                                let opName = '';
                                let opIdxVal = '';
                                if (selectedProfileId && service.profiles && service.profiles[selectedProfileId]) {
                                    const profileConfig = service.profiles[selectedProfileId];
                                    if (profileConfig.operations && profileConfig.operations[side] !== undefined && profileConfig.operations[side] !== '') {
                                        const opIdx = profileConfig.operations[side];
                                        opIdxVal = opIdx;
                                        const op = getOperationByIndex(opIdx);
                                        if (op) {
                                            opName = op.name;
                                            if (op.type === 'meter') opCost = op.price * sideLenM;
                                            else opCost = op.price;
                                        }
                                    }
                                }

                                // Formula
                                let eFormula = buildEdgeFormulaForSide(side, lengthMm, widthMm, sideLenMm);
                                eFormula = replaceToken(eFormula, 'price', p);
                                eFormula = replaceToken(eFormula, 'qty', qty);
                                const sideCostBase = evaluateMathExpression(eFormula);
                                const totalOpCost = opCost * qty;
                                const sideCost = sideCostBase + totalOpCost;
                                edgeCost += sideCost;
                                
                                edgeBreakdown.push({
                                    service_id: sideServiceId,
                                    code: sideCode || service.code || (service.name ? service.name.substring(0, 5) : ''),
                                    name: service.name,
                                    op_name: opName,
                                    op_idx: opIdxVal,
                                    side: side.toUpperCase(),
                                    cost: parseFloat(sideCost).toFixed(2),
                                    op_cost: parseFloat(totalOpCost).toFixed(2)
                                });
                            }
                        }
                    });
                }

                edgeCost = edgeBreakdown.reduce((sum, edge) => sum + (parseFloat(edge.cost) || 0), 0);
                
                // Calculate Base Price (Product Price via Formula)
                const productData = row.find('.wq-selected-product-id');
                const customAttributes = normalizeObject(productData.data('custom-attributes'));
                let formula = wqBuilder.pricing_formula || '0';

                const lengthM = lengthMm / 1000;
                const widthM = widthMm / 1000;
                const areaMm2 = lengthMm * widthMm;
                const areaM2 = lengthM * widthM;
                const perimeterMm = (2 * lengthMm) + (2 * widthMm);
                const perimeterM = perimeterMm / 1000;
                let basePriceVal = parseFloat(row.find('.wq-selected-product-id').data('price')) || 0;

                const pricePerMm2 = (() => {
                    const raw = customAttributes && customAttributes.wq_pricing_per_mm !== undefined ? customAttributes.wq_pricing_per_mm : (customAttributes ? customAttributes.price_per_mm2 : undefined);
                    const v = parseFloat(raw);
                    return isNaN(v) ? 0 : v;
                })();
                const pricePerM2 = (() => {
                    const raw = customAttributes ? customAttributes.price_per_m2 : undefined;
                    const v = parseFloat(raw);
                    if (!isNaN(v)) return v;
                    return pricePerMm2 * 1000000;
                })();

                formula = replaceToken(formula, 'length', length);
                formula = replaceToken(formula, 'width', width);
                formula = replaceToken(formula, 'qty', qty);
                formula = replaceToken(formula, 'length_mm', lengthMm);
                formula = replaceToken(formula, 'width_mm', widthMm);
                formula = replaceToken(formula, 'length_m', lengthM);
                formula = replaceToken(formula, 'width_m', widthM);
                formula = replaceToken(formula, 'area_mm2', areaMm2);
                formula = replaceToken(formula, 'area_m2', areaM2);
                formula = replaceToken(formula, 'perimeter_mm', perimeterMm);
                formula = replaceToken(formula, 'perimeter_m', perimeterM);
                formula = replaceToken(formula, 'price', basePriceVal);
                formula = replaceToken(formula, 'price_per_mm2', pricePerMm2);
                formula = replaceToken(formula, 'price_per_m2', pricePerM2);

                if (wqBuilder.custom_fields && wqBuilder.custom_fields.length > 0) {
                    wqBuilder.custom_fields.forEach(field => {
                        const slug = field.slug;
                        let val = 0;
                        let raw = customAttributes && customAttributes[slug] !== undefined ? customAttributes[slug] : undefined;
                        if (raw === undefined || raw === null || raw === '') {
                            raw = field && field.placeholder !== undefined ? field.placeholder : 0;
                        }
                        val = parseFloat(raw);
                        if (isNaN(val)) val = 0;
                        if (slug === 'wq_pricing_per_mm' && val <= 0) {
                            val = parseFloat(field && field.placeholder !== undefined ? field.placeholder : 0);
                            if (isNaN(val)) val = 0;
                        }
                        formula = replaceToken(formula, slug, val);
                    });
                }

                let basePrice = evaluateMathExpression(formula);
                if (isNaN(basePrice)) basePrice = 0;
                
                const hasOperations = productData.data('has-operations');
                const operationIndexes = productData.data('operation-indexes');
                const ops = hasOperations ? getOperationsByIndexes(operationIndexes) : [];
                const opsCalc = calculateOperationsCost(ops, qty, { perimeterM, areaM2 });
                const operationsCost = opsCalc.total;

                const totalPrice = basePrice + edgeCost + operationsCost;
                const edgeMetaSides = {
                    l1: row.find('.wq-edge-l1').is(':checked'),
                    w1: row.find('.wq-edge-w1').is(':checked'),
                    l2: row.find('.wq-edge-l2').is(':checked'),
                    w2: row.find('.wq-edge-w2').is(':checked')
                };
                
                items.push({
                    product_id: row.find('.wq-selected-product-id').data('is_variable') && row.find('.wq-selected-product-id').data('variation-id') ? parseInt(row.find('.wq-selected-product-id').data('variation-id')) : pid,
                    parent_id: pid,
                    product_name: row.find('.wq-product-search').val() || '',
                    qty: qty,
                    length: lengthMm,
                    width: widthMm,
                    thickness: thickness,
                    price: totalPrice, // Grand Total for this line to pass to Cart
                    // Use 'edge_breakdown' key consistently
                    edge_breakdown: edgeBreakdown,
                    edges: edgeBreakdown,
                    edge_meta: {
                        service_id: selectedServiceId || (assignments.l1 && assignments.l1.serviceId ? assignments.l1.serviceId : (assignments.w1 && assignments.w1.serviceId ? assignments.w1.serviceId : (assignments.l2 && assignments.l2.serviceId ? assignments.l2.serviceId : (assignments.w2 && assignments.w2.serviceId ? assignments.w2.serviceId : '')))),
                        profile_id: selectedProfileId,
                        sides: edgeMetaSides,
                        assignments: assignments
                    },
                    operations: opsCalc.lines,
                    operations_total: operationsCost,
                    operation: opsCalc.lines && opsCalc.lines.length > 0 ? opsCalc.lines[0] : null,
                    custom_fields: {
                        label: labelCombined,
                        label_predefined: labelPredefined,
                        label_description: labelText
                    }
                });
            }
        });
        return items;
    }

    // Generate PDF
    function generatePDF() {
        const quoteData = gatherQuoteData();
        if (!quoteData) return;
        
        const $btn = $('#wq-generate-pdf');
        const originalText = $btn.text();
        $btn.prop('disabled', true).text('Generating...');
        
        $.ajax({
            url: wqBuilder.ajax_url,
            type: 'POST',
            data: {
                action: 'wq_builder_generate_pdf',
                nonce: wqBuilder.nonce,
                quote_data: quoteData
            },
            success: function(response) {
                 $btn.prop('disabled', false).text(originalText);
                if (response.success && response.data.pdf_url) {
                    window.open(response.data.pdf_url, '_blank');
                } else {
                    showNotification('Error generating PDF: ' + (response.data ? response.data.message : 'Unknown Error'), 'error');
                }
            },
            error: function(xhr, status, error) {
                 $btn.prop('disabled', false).text(originalText);
                 console.error("AJAX Error:", status, error, xhr.responseText);
                 showNotification('Server Error: ' + error, 'error');
            }
        });
    }

    // Helper: Show Notification
    function showNotification(message, type) {
        let $notification = $('#wq-notification');
        if ($notification.length === 0) {
            $('body').append('<div id="wq-notification"></div>');
            $notification = $('#wq-notification');
        }
        
        $notification.text(message)
            .removeClass('wq-notify-success wq-notify-error')
            .addClass('wq-notify-' + type)
            .fadeIn()
            .delay(3000)
            .fadeOut();
    }

    function ensureConfirmDialog() {
        let $overlay = $('#wq-confirm-overlay');
        if ($overlay.length) return $overlay;

        $('body').append(`
            <div id="wq-confirm-overlay" class="wq-confirm-overlay" style="display:none;">
                <div class="wq-confirm-dialog" role="dialog" aria-modal="true">
                    <div class="wq-confirm-message"></div>
                    <div class="wq-confirm-actions">
                        <button type="button" class="wq-confirm-btn wq-confirm-yes">YES</button>
                        <button type="button" class="wq-confirm-btn wq-confirm-no">NO</button>
                    </div>
                </div>
            </div>
        `);

        $overlay = $('#wq-confirm-overlay');

        $overlay.on('click', function(e) {
            if ($(e.target).is('#wq-confirm-overlay')) {
                $overlay.find('.wq-confirm-no').trigger('click');
            }
        });

        $(document).on('keydown.wqConfirm', function(e) {
            if (e.key === 'Escape' && $overlay.is(':visible')) {
                $overlay.find('.wq-confirm-no').trigger('click');
            }
        });

        return $overlay;
    }

    function showConfirm(message, onYes) {
        const $overlay = ensureConfirmDialog();
        const yesHandler = typeof onYes === 'function' ? onYes : function() {};

        $overlay.find('.wq-confirm-message').text(message || '');
        $overlay.show();

        $overlay.find('.wq-confirm-yes').off('click').on('click', function() {
            $overlay.hide();
            yesHandler();
        });

        $overlay.find('.wq-confirm-no').off('click').on('click', function() {
            $overlay.hide();
        });

        setTimeout(() => $overlay.find('.wq-confirm-yes').trigger('focus'), 0);
    }

    function resetRow($rowContainer) {
        const $row = $rowContainer.find('.wq-row');

        $row.find('.wq-product-search').val('');
        const $pid = $row.find('.wq-selected-product-id');
        $pid.val('');
        $pid.data('price', null)
            .data('custom-attributes', null)
            .data('has-edgebanding', null)
            .data('has-operations', null)
            .data('operation-indexes', null)
            .data('allowed-edge-services', null)
            .data('has-preferred-edging', null)
            .data('preferred-edge-services', null)
            .data('image', null)
            .data('max-len', null)
            .data('max-wid', null)
            .data('min-len', null)
            .data('min-wid', null);

        if (!$row.find('.wq-selected-product-id').data('is_variable')) { $row.find('.wq-thickness-input').val(''); }
        $row.find('.wq-row-length input').val('');
        $row.find('.wq-row-width input').val('');
        $row.find('.wq-row-qty input').val(1);

        ['l1', 'w1', 'l2', 'w2'].forEach(edge => {
            $row.find(`.wq-edge-${edge}`).prop('checked', false).prop('disabled', true);
            $row.find(`.wq-code-${edge}`).text('');
        });

        $row.removeData('selected-edge-service')
            .removeData('selected-edge-profile')
            .removeData('edge-angles')
            .removeData('edge-prices')
            .removeData('edge-default-price')
            .removeData('edge-assignments');

        $row.find('.wq-label-predefined-select').val('');
        $row.find('.wq-label-input').val('');
        $row.find('.wq-label-status').hide();
        $row.find('.wq-label-popup').hide();
        $row.find('.wq-edit-label').show();
    }

    function moveEdgePopupToBody() {
        const $popup = $('#wq-edge-diagram-popup');
        if (!$popup.length) return;
        $popup.stop(true, true).hide().detach().appendTo('body');
        $('.wq-row-container.wq-edge-open').removeClass('wq-edge-open');
        window.activeEdgeRow = null;
    }

    function moveTourToBody() {
        const $overlay = $('#wq-tour-overlay');
        const $modal = $('#wq-tour-modal');
        if ($overlay.length) $overlay.detach().appendTo('body');
        if ($modal.length) $modal.detach().appendTo('body');
    }

    window.wqRowClipboard = null;
    window.wqRowClipboardSourceId = null;
    window.wqKeepOffcuts = !!window.wqKeepOffcuts;

    function placeRowClipboardControls($container) {
        const $controls = $container.find('.wq-row-clipboard-controls').first();
        if (!$controls.length) return;

        const isMobile = window.matchMedia && window.matchMedia('(max-width: 768px)').matches;
        const $desktopCell = $container.find('.wq-row-clipboard-cell').first();
        const $desktopTarget = $desktopCell.length ? $desktopCell : $container.find('.wq-product-selector').first();
        const $mobileTarget = $container.find('.wq-row-label').first();

        if (isMobile && $mobileTarget.length) {
            if (!$controls.parent().is($mobileTarget)) {
                $controls.detach().appendTo($mobileTarget);
            }
            $container.addClass('wq-clipboard-in-label');
            $container.removeClass('wq-clipboard-in-cell');
        } else if ($desktopTarget.length) {
            if (!$controls.parent().is($desktopTarget)) {
                $controls.detach().appendTo($desktopTarget);
            }
            $container.removeClass('wq-clipboard-in-label');
            $container.toggleClass('wq-clipboard-in-cell', $desktopCell.length > 0);
        }
    }

    function updateRowClipboardUI() {
        const hasClip = !!window.wqRowClipboard;
        $('body').toggleClass('wq-has-row-clipboard', hasClip);

        $('.wq-row-container').each(function() {
            const $container = $(this);
            const $row = $container.find('.wq-row');
            const rowId = $row.attr('data-id') || $row.data('id') || '';
            const isSource = hasClip && String(rowId) === String(window.wqRowClipboardSourceId);
            $container.toggleClass('wq-row-clipboard-source', isSource);

            const hasProduct = !!($row.find('.wq-selected-product-id').val() || '');
            $container.toggleClass('wq-row-has-product', hasProduct);
            placeRowClipboardControls($container);
        });
    }

    function applyProductToRow($row, clip) {
        if (!clip) return;

        $row.find('.wq-product-search').val(clip.name || '');
        const $pid = $row.find('.wq-selected-product-id');
        $pid.val(clip.id || '')
            .data('price', clip.price)
            .data('custom-attributes', clip.customAttributes)
            .data('has-edgebanding', !!clip.hasEdgebanding)
            .data('has-operations', !!clip.hasOperations)
            .data('operation-indexes', clip.operationIndexes || [])
            .data('has-preferred-edging', !!clip.hasPreferredEdging)
            .data('preferred-edge-services', clip.preferredEdgeServices || [])
            .data('allowed-edge-services', null)
            .data('image', clip.image || '');

        const customAttributes = clip.customAttributes || {};
        const map = wqBuilder.field_map || {};
        const keyMaxLen = map.max_len || 'wq_max_length';
        const keyMaxWid = map.max_wid || 'wq_max_width';
        const keyMinLen = map.min_len || 'wq_min_length';
        const keyMinWid = map.min_wid || 'wq_min_width';

        const maxLen = parseFloat(customAttributes[keyMaxLen]) || 0;
        const maxWid = parseFloat(customAttributes[keyMaxWid]) || 0;
        const minLen = parseFloat(customAttributes[keyMinLen]) || 0;
        const minWid = parseFloat(customAttributes[keyMinWid]) || 0;

        $pid.data('min-len', minLen)
            .data('max-len', maxLen)
            .data('min-wid', minWid)
            .data('max-wid', maxWid);

        let lenTitle = 'Length';
        if (minLen > 0 || maxLen > 0) {
            lenTitle += ' (';
            if (minLen > 0) lenTitle += `Min: ${formatDimensionValue(minLen)}${getDimensionUnitLabel()}`;
            if (minLen > 0 && maxLen > 0) lenTitle += ', ';
            if (maxLen > 0) lenTitle += `Max: ${formatDimensionValue(maxLen)}${getDimensionUnitLabel()}`;
            lenTitle += ')';
        }
        $row.find('.wq-row-length input').attr('title', lenTitle);

        let widTitle = 'Width';
        if (minWid > 0 || maxWid > 0) {
            widTitle += ' (';
            if (minWid > 0) widTitle += `Min: ${formatDimensionValue(minWid)}${getDimensionUnitLabel()}`;
            if (minWid > 0 && maxWid > 0) widTitle += ', ';
            if (maxWid > 0) widTitle += `Max: ${formatDimensionValue(maxWid)}${getDimensionUnitLabel()}`;
            widTitle += ')';
        }
        $row.find('.wq-row-width input').attr('title', widTitle);

        $row.find('.wq-row-thickness input').val(clip.thickness || '');

        const $edgeCheckboxes = $row.find('.wq-row-edge input[type="checkbox"]');
        if (clip.hasEdgebanding) {
            $edgeCheckboxes.prop('disabled', false);
        } else {
            $edgeCheckboxes.prop('disabled', true);
        }

        ['l1', 'w1', 'l2', 'w2'].forEach(edge => {
            $row.find(`.wq-edge-${edge}`).prop('checked', false);
            $row.find(`.wq-code-${edge}`).text('');
        });

        $row.removeData('selected-edge-service')
            .removeData('selected-edge-profile')
            .removeData('edge-angles')
            .removeData('edge-prices')
            .removeData('edge-default-price')
            .removeData('edge-assignments');
    }

    // Event Handlers
    function setupEventHandlers() {
        // Add Row Button
        $('#wq-add-row').on('click', function(e) {
            e.preventDefault();
            addRow();
            updateRowClipboardUI();
        });

        $(document).on('click', '.wq-copy-row-btn', function(e) {
            e.preventDefault();
            e.stopPropagation();

            const $container = $(this).closest('.wq-row-container');
            const $row = $container.find('.wq-row');
            const $pid = $row.find('.wq-selected-product-id');
            const id = $pid.val();
            if (!id) {
                showNotification('Please select a material to copy.', 'error');
                return;
            }

            const clip = {
                id: id,
                name: $row.find('.wq-product-search').val() || '',
                thickness: $row.find('.wq-row-thickness input').val() || '',
                price: $pid.data('price'),
                customAttributes: normalizeObject($pid.data('custom-attributes')) || {},
                hasEdgebanding: !!$pid.data('has-edgebanding'),
                hasOperations: !!$pid.data('has-operations'),
                operationIndexes: normalizeObject($pid.data('operation-indexes')) || [],
                hasPreferredEdging: !!$pid.data('has-preferred-edging'),
                preferredEdgeServices: normalizeObject($pid.data('preferred-edge-services')) || [],
                image: $pid.data('image') || ''
            };

            window.wqRowClipboard = clip;
            window.wqRowClipboardSourceId = $row.attr('data-id') || $row.data('id') || null;
            updateRowClipboardUI();

            const $status = $container.find('.wq-copy-status');
            $status.stop(true, true).show().text('Copied!');
            setTimeout(() => $status.fadeOut(200), 1200);
        });

        $(document).on('click', '.wq-paste-row-btn', function(e) {
            e.preventDefault();
            e.stopPropagation();
            if (!window.wqRowClipboard) return;

            const $container = $(this).closest('.wq-row-container');
            const $row = $container.find('.wq-row');
            applyProductToRow($row, window.wqRowClipboard);
        });

        // Remove Row Button
        $(document).on('click', '.wq-remove-btn', function(e) {
            e.preventDefault();
            const $rowContainer = $(this).closest('.wq-row-container');
            showConfirm('Delete Row?', function() {
                moveEdgePopupToBody();
                if ($('.wq-row').length <= 1) {
                    resetRow($rowContainer);
                } else {
                    $rowContainer.remove();
                    updateRowNumbers();
                }
                updateRowClipboardUI();
            });
        });

        // Clear All Rows
        $('#wq-clear-rows').on('click', function(e) {
            e.preventDefault();
            showConfirm('Delete all rows & create a new quote?', function() {
                moveEdgePopupToBody();
                $('#wq-rows-container').empty();
                rowCount = 0;
                $('#wq-quote-ref').text(generateQuoteRef());
                addDefaultRows();
                updateRowClipboardUI();
                $('#wq-quote-summary').hide();
            });
        });

        $(window).on('resize.wqRowClipboard', function() {
            updateRowClipboardUI();
        });

        // Download Template Button
        $('#wq-download-template').on('click', function(e) {
            e.preventDefault();
            const pdfUrl = wqBuilder.template_pdf_url;
            if (pdfUrl) {
                window.open(pdfUrl, '_blank');
            } else {
                alert('No template PDF available.');
            }
        });
        
        // Product Selection Change - Update Thickness
        // (Now handled in setupRowDropdown)
        
        // Close Dropdowns on Click Outside
        $(document).on('click', function() {
             $('.wq-product-dropdown').hide();
             $('.wq-row-container.wq-product-open').removeClass('wq-product-open');
        });

        // Get Pricing Button
        $('#wq-get-pricing').on('click', function(e) {
            e.preventDefault();
            getPricing();
        });
        
        $(document).on('change', '.wq-row-length input, .wq-row-width input, .wq-row-qty input', function() {
            if ($('#wq-edge-diagram-popup').is(':visible')) {
                const $row = $(this).closest('.wq-row');
                if (window.activeEdgeRow && window.activeEdgeRow.get(0) === $row.get(0)) {
                    updateDiagramActiveEdges();
                }
            }
        });
        
        // Real-time Validation for Required Fields
        $('#wq-project-name, #wq-client-name').on('input', function() {
            const val = $(this).val().trim();
            const group = $(this).closest('.wq-form-group');
            
            if (val !== '') {
                $(this).removeClass('error-border');
                group.find('.required-label').hide();
            } else {
                $(this).addClass('error-border');
                group.find('.required-label').show();
            }
        });

        $('#wq-project-name, #wq-client-name').removeClass('error-border');
        $('.wq-form-group .required-label').hide();

        // Submit Quote Button
        $('#wq-submit-quote').on('click', function(e) {
            e.preventDefault();
            submitQuote();
        });

        // Project Notes Toggle
        $('.wq-edit-notes').on('click', function(e) {
            e.preventDefault();
            $('#wq-project-notes').toggle();
            if ($('#wq-project-notes').is(':visible')) {
                $('#wq-project-notes').focus();
            }
        });

        // Project Notes Blur (Save)
        $('#wq-project-notes').on('blur', function() {
            const notes = $(this).val();
            if (notes.trim() !== '') {
                $('.wq-notes-status').text('Note Added').show();
                $('.wq-hidden-notes').hide();
            } else {
                $('.wq-notes-status').hide();
                $('.wq-hidden-notes').hide();
            }
        });
        
        // --- Edgebanding Popup Logic ---
        
        // Helper to update active edges in popup diagram
        function updateDiagramActiveEdges() {
            // Reset all active edge highlighting classes on the diagram box
            const $box = $('.wq-diagram-box');
            $box.removeClass('wq-d-active-edge-l1 wq-d-active-edge-w1 wq-d-active-edge-l2 wq-d-active-edge-w2');
            
            // Hide all highlights and angles first
            $('.wq-highlight-l1, .wq-highlight-w1, .wq-highlight-l2, .wq-highlight-w2').hide();
            $('.wq-d-angle-l1, .wq-d-angle-w1, .wq-d-angle-l2, .wq-d-angle-w2').hide();
            
            const unit = getDimensionUnitLabel();
            const lengthVal = window.activeEdgeRow ? String(window.activeEdgeRow.find('.wq-row-length input').val() || '').trim() : '';
            const widthVal = window.activeEdgeRow ? String(window.activeEdgeRow.find('.wq-row-width input').val() || '').trim() : '';

            // Highlight active edges based on tabs
            $('.wq-edge-tab.active').each(function() {
                const edge = $(this).data('edge');
                $box.addClass(`wq-d-active-edge-${edge}`);
                
                // Show specific highlight
                $(`.wq-highlight-${edge}`).show();
                
                // Show side length in diagram
                const $angleLabel = $(`.wq-d-angle-${edge}`);
                const sideVal = (edge === 'l1' || edge === 'l2') ? lengthVal : widthVal;
                if (sideVal !== '') $angleLabel.text(`${sideVal}${unit}`).show();
                else $angleLabel.hide();
            });
        }

        // Open Popup
        $(document).on('click', '.wq-row-edge input[type="checkbox"]', function(e) {
            e.preventDefault(); // Prevent default toggle behavior
            
            if ($(this).prop('disabled')) return;
            
            const row = $(this).closest('.wq-row');
            
            // Set current active row context
            window.activeEdgeRow = row;
            
            // Populate Popup Data
            const productName = row.find('.wq-product-search').val();
            $('#wq-diagram-material-name').text(productName);
            
            // --- DYNAMIC EDGEBANDING LOGIC START ---
            
            // 1. Use all available edge services
            const $preferredDisplay = $('.wq-preferred-edge-display');
            const $preferredPanel = $('.wq-preferred-edge-panel');
            const $preferredSearch = $('.wq-preferred-edge-search');
            const $preferredResults = $('.wq-preferred-edge-results');
            const $edgeSearchDisplay = $('.wq-edge-search-display');
            const $edgeSearchInput = $('.wq-edge-search-input');
            const $edgeSearchPanel = $('.wq-edge-search-panel');
            const $edgeSearchResults = $('.wq-edge-search-results');

            $preferredResults.empty();
            $preferredPanel.hide();
            $preferredSearch.val('');
            $edgeSearchResults.empty();
            $edgeSearchPanel.hide();
            $edgeSearchInput.val('');
            
            // 2. Identify Valid Profiles (Visuals) based on allowed services
            const validProfiles = new Set();
            const validServices = [];
            
            const $productData = row.find('.wq-selected-product-id');
            const hasPreferredEdging = $productData.data('has-preferred-edging');
            const preferredEdgeServices = normalizeObject($productData.data('preferred-edge-services'));
            const preferredOnly = hasPreferredEdging && preferredEdgeServices && Array.isArray(preferredEdgeServices) && preferredEdgeServices.length > 0;
            const preferredSet = preferredOnly ? new Set(preferredEdgeServices.map(v => String(v))) : null;
            $('#wq-edge-diagram-popup').data('preferred-ids', preferredOnly ? preferredEdgeServices : []);

            const servicesMap = window.wqEdgeData && window.wqEdgeData.services ? window.wqEdgeData.services : {};
            Object.keys(servicesMap).forEach(serviceId => {
                const service = servicesMap[serviceId];
                if (!service) return;
                validServices.push(service);
                if (service.profiles) {
                    Object.keys(service.profiles).forEach(profileId => {
                        validProfiles.add(profileId);
                    });
                }
            });
            
            const hasAssignmentsStored = (() => {
                const assigns = row.data('edge-assignments') || {};
                return Object.keys(assigns).length > 0;
            })();

            const currentSelectedServiceId = (() => {
                const assigns = row.data('edge-assignments') || {};
                for (const k of ['l1', 'w1', 'l2', 'w2']) {
                    if (assigns[k] && assigns[k].serviceId) return assigns[k].serviceId;
                }
                return row.data('selected-edge-service') || '';
            })();

            if (preferredOnly) {
                $preferredDisplay.prop('disabled', false).attr('placeholder', 'Preferred edging...');
                $preferredSearch.prop('disabled', false);
            } else {
                $preferredDisplay.prop('disabled', true).val('').attr('placeholder', 'No preferred edging');
                $preferredSearch.prop('disabled', true).val('');
            }

            if (currentSelectedServiceId) {
                const svc = window.wqEdgeData && window.wqEdgeData.services ? window.wqEdgeData.services[currentSelectedServiceId] : null;
                if (svc && svc.name) {
                    $edgeSearchDisplay.val(svc.name);
                    if (preferredOnly && preferredSet && preferredSet.has(String(currentSelectedServiceId))) {
                        $preferredDisplay.val(svc.name);
                    } else {
                        $preferredDisplay.val('');
                    }
                }
            } else {
                $edgeSearchDisplay.val('');
                $preferredDisplay.val('');
            }
            
            // Get stored profile/service
            const storedProfileId = row.data('selected-edge-profile');
            const storedServiceId = row.data('selected-edge-service');
            
            // 3. Render Visual Options (Profiles)
            const $visualsContainer = $('.wq-edge-visuals');
            $visualsContainer.empty();
            
            if (validProfiles.size > 0) {
                validProfiles.forEach(profileId => {
                    const profile = window.wqEdgeData.profiles[profileId];
                    if (profile) {
                        const html = `
                            <div class="wq-visual-option" data-profile-id="${profile.id}">
                                <img src="${profile.image}" alt="${profile.name}">
                                <span>${profile.name}</span>
                            </div>
                        `;
                        $visualsContainer.append(html);
                    }
                });
                
                // Select Profile (Restoring State)
                if (storedProfileId && validProfiles.has(storedProfileId.toString())) {
                     $visualsContainer.find(`.wq-visual-option[data-profile-id="${storedProfileId}"]`).trigger('click');
                } else {
                     $visualsContainer.find('.wq-visual-option').first().trigger('click');
                }
            } else {
                $visualsContainer.html('<div style="color:#fff; padding:10px;">No edge profiles available.</div>');
            }
            
            // Restore Service Selection (Staging)
            // Note: We now store assignments per-edge. But we still need a "current staging" service 
            // to highlight in the list, perhaps the one from the first active edge?
            // Or we just don't highlight any service if multiple are different?
            // Let's check assignments first.
            const assignments = row.data('edge-assignments') || {};
            
            // Try to find a staging service from L1, W1, L2, W2 in that order
            let stagingServiceId = null;
            ['l1', 'w1', 'l2', 'w2'].some(edge => {
                if (assignments[edge] && assignments[edge].serviceId) {
                    stagingServiceId = assignments[edge].serviceId;
                    return true;
                }
                return false;
            });
            
            // If no assignments, fallback to row-level stored service (legacy/default)
            if (!stagingServiceId && storedServiceId) {
                stagingServiceId = storedServiceId;
            }

            if (stagingServiceId) {
                 // The visual option click (above) has already populated the search results
                 const $serviceLi = $('.wq-edge-search-results').find(`li[data-service-id="${stagingServiceId}"]`);
                 
                 // If service is found in list, select it as "Staging"
                 if ($serviceLi.length > 0) {
                     $serviceLi.addClass('active'); // Manually highlight
                     
                     // Update Info Text (Current Staging)
                     const service = window.wqEdgeData.services[stagingServiceId];
                     if (service) {
                         const code = service.code || service.name.substring(0,5);
                         $('.wq-current-edge-info .wq-edge-code').text(code);
                     }
                 }
            } else {
                // No service selected yet
                $('.wq-current-edge-info .wq-edge-code').text('None');
                $('.wq-current-edge-info .wq-edge-thick').text('');
            }
            
            // Restore Checkbox States and Codes from Assignments
            ['l1', 'w1', 'l2', 'w2'].forEach(edge => {
                const $tab = $(`.wq-edge-tab[data-edge="${edge}"]`);
                const $checkbox = row.find(`.wq-edge-${edge}`);
                const $popupDisplay = $(`.wq-code-display-${edge}`);
                const $rowDisplay = row.find(`.wq-code-${edge}`);
                
                // Clear first
                $popupDisplay.val('');
                $rowDisplay.text('');
                $checkbox.prop('checked', false);
                $tab.removeClass('active');
                
                if (assignments[edge]) {
                     // Check if it's allowed by the SPECIFIC service assigned to this edge
                     // Wait, validation is complex with mixed services. 
                     // Let's assume if it's assigned, it was allowed when assigned.
                     
                     $checkbox.prop('checked', true);
                     $tab.addClass('active');
                     
                     // Display Code
                     const code = assignments[edge].code;
                     $popupDisplay.val(code);
                     $rowDisplay.text(code);
                }
            });
            
            // Update Diagram Visuals
            updateDiagramActiveEdges();
            
            // 5. Open Dropdown
            const $popup = $('#wq-edge-diagram-popup');
            const $container = row.closest('.wq-row-container');
            const $placeholder = $container.find('.wq-edge-dropdown-placeholder').first();

            if ($popup.is(':visible') && $popup.parent().get(0) === $placeholder.get(0)) {
                $popup.stop(true, true).fadeOut(200, function() {
                    moveEdgePopupToBody();
                });
                return;
            }

            $('.wq-row-container.wq-edge-open').removeClass('wq-edge-open');
            $container.addClass('wq-edge-open');
            $popup.hide().detach().appendTo($placeholder);
            $popup.fadeIn(200).css('display', 'flex');

            setTimeout(() => {
                if (!$popup.is(':visible')) return;
                const popupTop = $popup.offset().top;
                const popupBottom = popupTop + $popup.outerHeight();
                const viewTop = $(window).scrollTop();
                const viewBottom = viewTop + $(window).height();
                const pad = 20;
                if (popupBottom > viewBottom - pad) {
                    $('html, body').stop(true).animate({ scrollTop: popupBottom - $(window).height() + pad }, 200);
                } else if (popupTop < viewTop + pad) {
                    $('html, body').stop(true).animate({ scrollTop: popupTop - pad }, 200);
                }
            }, 50);
            
            // NEW: Try to restore the staging service selection if we have assignments
            // Find the most recently assigned service or just the first one
            let restoreStagingId = null;
            if (validProfiles.size > 0 && row.data('edge-assignments')) {
                const assigns = row.data('edge-assignments');
                // Pick one from active edges if possible
                ['l1', 'w1', 'l2', 'w2'].some(edge => {
                    if (assigns[edge] && assigns[edge].serviceId) {
                        restoreStagingId = assigns[edge].serviceId;
                        return true;
                    }
                    return false;
                });
            }
            // If we found one, make sure it's highlighted in the list right away
            if (restoreStagingId) {
                // Wait for search results to be populated? 
                // The logic above populates search results AFTER visual click?
                // Wait, visuals click triggers search results.
                // So we need to ensure this happens after the initial visual click simulation.
                setTimeout(() => {
                    const $serviceLi = $('.wq-edge-search-results').find(`li[data-service-id="${restoreStagingId}"]`);
                    if ($serviceLi.length) {
                        $('.wq-edge-search-results li').removeClass('active');
                        $serviceLi.addClass('active');
                        // Update Info Text
                        const service = window.wqEdgeData.services[restoreStagingId];
                        if (service) {
                            const code = service.code || service.name.substring(0,5);
                            $('.wq-current-edge-info .wq-edge-code').text(code);
                        }
                    }
                }, 50);
            }
            
        });
        
        // Close Popup
        $('.wq-popup-close-btn').on('click', function() {
            const $popup = $('#wq-edge-diagram-popup');
            if (!$popup.length) return;
            $popup.stop(true, true).fadeOut(200, function() {
                moveEdgePopupToBody();
            });
        });
        
        // Tab Toggle Logic (Multiple Selection with Per-Side Assignments)
        $('.wq-edge-tab').on('click', function() {
            if ($(this).hasClass('disabled')) return;
            
            const edgeType = $(this).data('edge');
            const $row = window.activeEdgeRow;
            
            // Get Current Staging Service (from popup list selection)
            // We need to know what service is currently "active" in the picker
            const $activeServiceLi = $('.wq-edge-search-results li.active');
            let stagingServiceId = $activeServiceLi.length ? $activeServiceLi.data('service-id') : null;
            
            // Fallback: If no service highlighted in list, check if row has a global default
            if (!stagingServiceId) {
                stagingServiceId = $row.data('selected-edge-service');
            }
            
            // If still no service, maybe alert user? Or just toggle off if already on?
            // If turning ON and no service, we can't do anything.
            if (!$(this).hasClass('active') && !stagingServiceId) {
                //alert('Please select an edge service from the list first.');
                return;
            }
            
            // Toggle active class
            $(this).toggleClass('active');
            const isActive = $(this).hasClass('active');
            
            // Get Assignments Data
            let assignments = $row.data('edge-assignments') || {};
            
            // Update corresponding checkbox in the row immediately
            $row.find(`.wq-edge-${edgeType}`).prop('checked', isActive);
            
            const $codeContainer = $row.find(`.wq-code-${edgeType}`);
            const $popupDisplay = $(`.wq-code-display-${edgeType}`);
            
            if (isActive) {
                // Apply Staging Service to this Edge
                const service = window.wqEdgeData.services[stagingServiceId];
                if (service) {
                    const code = service.code || service.name.substring(0,5);
                    
                    // Save Assignment
                    assignments[edgeType] = {
                        serviceId: stagingServiceId,
                        code: code
                    };
                    
                    // Update UI
                    $codeContainer.text(code);
                    $popupDisplay.val(code);
                }
            } else {
                // Remove Assignment
                delete assignments[edgeType];
                
                // Clear UI
                $codeContainer.text('');
                $popupDisplay.val('');
            }
            
            // Save Assignments back to row
            $row.data('edge-assignments', assignments);
            
            // Update Diagram Visuals
            updateDiagramActiveEdges();
            
            // *** NEW: Ensure the staging service REMAINS highlighted/active ***
            // Sometimes clicking away might blur the list, but we want it to stay 'selected' visually
            // so the user knows what "brush" they are holding.
            if (stagingServiceId) {
                const $serviceLi = $('.wq-edge-search-results').find(`li[data-service-id="${stagingServiceId}"]`);
                if ($serviceLi.length && !$serviceLi.hasClass('active')) {
                    $('.wq-edge-search-results li').removeClass('active');
                    $serviceLi.addClass('active');
                    
                    // Also ensure "Selected:" text matches this staging service
                    const service = window.wqEdgeData.services[stagingServiceId];
                    if (service) {
                         const code = service.code || service.name.substring(0,5);
                         $('.wq-current-edge-info .wq-edge-code').text(code);
                    }
                }
            }
        });
        
        // Select Match / Search Result (Handled by Search Result Click)
        $(document).on('click', '.wq-match-item', function() {
             // ... placeholder if needed ...
        });
        
        // Visual Selection
        $(document).on('click', '.wq-visual-option', function() {
            $('.wq-visual-option').removeClass('active');
            $('.wq-visual-option .wq-check-mark').remove();
            
            $(this).addClass('active');
            $(this).append('<span class="wq-check-mark">&#10003;</span>');
            
            // Clear current selection on row until a service is picked (Reset)
            // But only if this is a manual click? 
            // The programmatic click also triggers this. 
            // If programmatic, we follow up with a Service Click which restores data.
            // If manual, this correctly resets the state.
            // MODIFICATION: Do NOT clear everything if we are just switching visuals.
            // But usually switching visuals means new compatible services.
            // However, to fix "reset when selecting picture", we should only reset if the current selection is invalid for the new profile.
            // For now, let's comment out the RESET block entirely when clicking visuals, 
            // and instead let the subsequent filtering of search results handle validity.
            // If the currently selected service is still valid for this new profile, keep it!
            
            // Re-highlight the "Staging" service if one was previously selected (from assignments or general)
            // But which one? The row might have multiple assignments now.
            // Let's check if there is an active service in the search list first (if user just clicked it).
            // If not, check assignments.
            
            const $row = window.activeEdgeRow;
            let stagingServiceId = null;
            
            // 1. Check current list selection
            const $activeLi = $('.wq-edge-search-results li.active');
            if ($activeLi.length) {
                stagingServiceId = $activeLi.data('service-id');
            }
            
            // 2. If not, check if we have any assignments on the row that we can "pick up"
            if (!stagingServiceId && $row && $row.data('edge-assignments')) {
                const assigns = $row.data('edge-assignments');
                // Pick the first valid assignment
                ['l1', 'w1', 'l2', 'w2'].some(edge => {
                    if (assigns[edge] && assigns[edge].serviceId) {
                        stagingServiceId = assigns[edge].serviceId;
                        return true;
                    }
                    return false;
                });
            }
            
            // 3. Fallback to row global (legacy)
            if (!stagingServiceId && $row) {
                stagingServiceId = $row.data('selected-edge-service');
            }
            
            // Now, AFTER the search results are repopulated (below), we need to re-select this staging ID
            // because the .empty() call below wipes the list.
            
            // Filter Search Results based on selected Profile
            const profileId = $(this).data('profile-id');
            const term = ($('.wq-edge-search-input').val() || '').trim();
            renderEdgeSearchResults(term, stagingServiceId);
            if (stagingServiceId) {
                syncSelectedEdgeServiceForCurrentProfile(stagingServiceId);
            }
        });
        
        // Service Selection in Search
        $(document).on('click', '.wq-edge-search-results li', function() {
            if ($(this).css('cursor') === 'default') return;
            const serviceId = $(this).data('service-id');
            selectEdgeServiceInPopup(serviceId, false);
            hideEdgePanels();
        });

        $(document).on('input', '.wq-edge-search-input', function() {
            const term = ($(this).val() || '').trim();
            renderEdgeSearchResults(term, null);
        });

        $(document).on('focus', '.wq-edge-search-input', function() {
            const term = ($(this).val() || '').trim();
            renderEdgeSearchResults(term, null);
        });

        $(document).on('input', '.wq-preferred-edge-search', function() {
            renderPreferredEdgeResults($(this).val() || '');
        });

        $(document).on('focus', '.wq-preferred-edge-search', function() {
            renderPreferredEdgeResults($(this).val() || '');
        });

        $(document).on('click', '.wq-preferred-edge-results li', function() {
            if ($(this).css('cursor') === 'default') return;
            const serviceId = $(this).data('service-id');
            const service = window.wqEdgeData && window.wqEdgeData.services ? window.wqEdgeData.services[serviceId] : null;
            if (service && service.name) $('.wq-preferred-edge-display').val(service.name);
            applyEdgeServiceSelection(serviceId);
            hideEdgePanels();
        });

        $(document).on('click', function() {
            hideEdgePanels();
        });

        $(document).on('click', '.wq-edge-service-selector', function(e) {
            e.stopPropagation();
        });

        $(document).on('click', '.wq-edge-search-display, .wq-edge-search-toggle', function(e) {
            e.preventDefault();
            e.stopPropagation();
            const $panel = $('.wq-edge-search-panel');
            if ($panel.is(':visible')) {
                hideEdgePanels();
                return;
            }
            hideEdgePanels();
            $panel.show();
            const $input = $('.wq-edge-search-input');
            $input.trigger('focus');
            renderEdgeSearchResults(($input.val() || '').trim(), null);
        });

        $(document).on('click', '.wq-preferred-edge-display, .wq-preferred-edge-toggle', function(e) {
            e.preventDefault();
            e.stopPropagation();
            const $display = $('.wq-preferred-edge-display');
            if ($display.prop('disabled')) return;
            const $panel = $('.wq-preferred-edge-panel');
            if ($panel.is(':visible')) {
                hideEdgePanels();
                return;
            }
            hideEdgePanels();
            $panel.show();
            const $input = $('.wq-preferred-edge-search');
            $input.trigger('focus');
            renderPreferredEdgeResults($input.val() || '');
        });



        // Label Edit
        $(document).on('click', '.wq-edit-label', function(e) {
            e.preventDefault();
            const container = $(this).closest('.wq-row-label');
            setupLabelDropdown(container.closest('.wq-row'));
            container.find('.wq-label-popup').show();
            container.find('.wq-label-input').focus();
            $(this).hide();
        });

        // Label Save
        $(document).on('click', '.wq-save-label', function(e) {
            e.preventDefault();
            const container = $(this).closest('.wq-row-label');
            const val = container.find('.wq-label-input').val() || '';
            const predefined = container.find('.wq-label-predefined-select').val() || '';
            
            container.find('.wq-label-popup').hide();
            container.find('.wq-edit-label').show();
            
            if (val.trim() !== '' || predefined.trim() !== '') {
                container.find('.wq-label-status').show();
            } else {
                container.find('.wq-label-status').hide();
            }
        });

        $(document).on('change', '.wq-label-predefined-select', function() {
            const $container = $(this).closest('.wq-row-label');
            const predefined = ($(this).val() || '').trim();
            const val = ($container.find('.wq-label-input').val() || '').trim();
            if (predefined !== '' || val !== '') {
                $container.find('.wq-label-status').show();
            } else {
                $container.find('.wq-label-status').hide();
            }
        });
        
        // Label Input Enter Key (Allow Shift+Enter for newline, Enter to save)
        $(document).on('keypress', '.wq-label-input', function(e) {
            if (e.which == 13 && !e.shiftKey) {
                e.preventDefault();
                $(this).closest('.wq-label-popup').find('.wq-save-label').click();
            }
        });

        // --- Tooltip Logic for Min/Max Dimensions ---
        if (!$('#wq-tooltip').length) {
            $('body').append('<div id="wq-tooltip"></div>');
        }

        $(document).on('mouseenter focus', '.wq-row-length input, .wq-row-width input', function() {
            const $input = $(this);
            const $row = $input.closest('.wq-row');
            const $productData = $row.find('.wq-selected-product-id');
            
            // Check if product is selected
            if (!$productData.val()) return;

            // Determine if Length or Width
            const isLength = $input.closest('.wq-row-length').length > 0;
            
            const min = parseFloat(isLength ? $productData.data('min-len') : $productData.data('min-wid')) || 0;
            const max = parseFloat(isLength ? $productData.data('max-len') : $productData.data('max-wid')) || 0;
            
            if (min > 0 || max > 0) {
                let text = '';
                if (min > 0) text += `Min: ${formatDimensionValue(min)}${getDimensionUnitLabel()}`;
                if (min > 0 && max > 0) text += ' | ';
                if (max > 0) text += `Max: ${formatDimensionValue(max)}${getDimensionUnitLabel()}`;
                
                const $tooltip = $('#wq-tooltip');
                $tooltip.text(text).show();
                
                // Position
                const offset = $input.offset();
                $tooltip.css({
                    top: offset.top - $tooltip.outerHeight() - 8,
                    left: offset.left + ($input.outerWidth() / 2) - ($tooltip.outerWidth() / 2)
                });
            }
        });

        $(document).on('mouseleave blur', '.wq-row-length input, .wq-row-width input', function() {
            $('#wq-tooltip').hide();
        });

        $(document).on('mouseenter focus', '.wq-info-icon[data-tooltip]', function() {
            const text = String($(this).attr('data-tooltip') || '').trim();
            if (text === '') return;

            const $tooltip = $('#wq-tooltip');
            $tooltip.text(text).show();

            const offset = $(this).offset();
            $tooltip.css({
                top: offset.top - $tooltip.outerHeight() - 10,
                left: offset.left - ($tooltip.outerWidth() / 2) + ($(this).outerWidth() / 2)
            });
        });

        $(document).on('mouseleave blur', '.wq-info-icon[data-tooltip]', function() {
            $('#wq-tooltip').hide();
        });
    }

    // --- Tour Guide Logic ---
    let tourStep = 0;
    
    // Default Fallback Steps
    let tourSteps = [
        {
            target: '.wq-info-icon:first',
            title: 'Context Help',
            content: 'Whenever you see the information icon, select it to view relevant help or information on that item.',
            position: 'bottom'
        },
        // ... (other defaults) ...
    ];

    // If localized data exists, override defaults
    if (wqBuilder.tour_data) {
        tourSteps = [
            {
                target: '.wq-info-icon:first',
                title: wqBuilder.tour_data.step_1.title,
                content: wqBuilder.tour_data.step_1.content,
                position: 'bottom'
            },
            {
                target: '.wq-project-info',
                title: wqBuilder.tour_data.step_2.title,
                content: wqBuilder.tour_data.step_2.content,
                position: 'bottom'
            },
            {
                target: '#wq-rows-container .wq-row:first-child',
                title: wqBuilder.tour_data.step_3.title,
                content: wqBuilder.tour_data.step_3.content,
                position: 'bottom'
            },
            {
                target: '#wq-rows-container .wq-row:first-child .wq-product-search',
                title: wqBuilder.tour_data.step_4.title,
                content: wqBuilder.tour_data.step_4.content,
                position: 'top'
            },
            {
                target: '#wq-rows-container .wq-row:first-child .wq-row-length',
                title: wqBuilder.tour_data.step_5.title,
                content: wqBuilder.tour_data.step_5.content,
                position: 'top'
            },
            {
                target: '#wq-rows-container .wq-row:first-child .wq-row-edge',
                title: wqBuilder.tour_data.step_6.title,
                content: wqBuilder.tour_data.step_6.content,
                position: 'top'
            },
            {
                target: '#wq-edge-diagram-popup .wq-popup-content',
                title: wqBuilder.tour_data.step_7.title,
                content: wqBuilder.tour_data.step_7.content,
                position: 'top',
                onShow: function() {
                    const $firstRow = $('#wq-rows-container .wq-row:first-child');
                    const $checkbox = $firstRow.find('.wq-edge-l1');
                    if ($checkbox.prop('disabled')) {
                        $('#wq-edge-diagram-popup').fadeIn();
                    } else {
                        $checkbox.trigger('click');
                    }
                }
            },
            {
                target: '.wq-edge-visuals',
                title: wqBuilder.tour_data.step_8.title,
                content: wqBuilder.tour_data.step_8.content,
                position: 'left'
            },
            {
                target: '#wq-rows-container .wq-row:first-child .wq-row-label',
                title: wqBuilder.tour_data.step_9.title,
                content: wqBuilder.tour_data.step_9.content,
                position: 'top',
                onShow: function() {
                    moveEdgePopupToBody();
                }
            },
            {
                target: '#wq-get-pricing',
                title: wqBuilder.tour_data.step_10.title,
                content: wqBuilder.tour_data.step_10.content,
                position: 'top'
            },
            {
                target: '.wq-footer-text',
                title: wqBuilder.tour_data.step_11.title,
                content: wqBuilder.tour_data.step_11.content,
                position: 'top',
                fallbackTarget: '.wq-actions'
            }
        ];
    }

    function startTour() {
        tourStep = 0;
        moveTourToBody();
        $('#wq-tour-overlay').fadeIn();
        $('#wq-tour-modal').fadeIn();
        showTourStep();
    }

    function endTour() {
        $('#wq-tour-overlay').fadeOut();
        $('#wq-tour-modal').fadeOut();
        $('.wq-tour-highlight').removeClass('wq-tour-highlight');
        moveEdgePopupToBody();
        moveTourToBody();
    }

    function showTourStep() {
        // Cleanup previous highlight
        $('.wq-tour-highlight').removeClass('wq-tour-highlight');
        
        const step = tourSteps[tourStep];
        
        // Execute onShow callback if exists
        if (step.onShow) {
            step.onShow();
        }
        
        // Allow a small delay for UI updates
        setTimeout(() => {
            let $target = $(step.target);
            
            // Handle fallback if main target is missing/hidden
            if (($target.length === 0 || $target.is(':hidden')) && step.fallbackTarget) {
                 $target = $(step.fallbackTarget);
            }
            
            if ($target.length === 0 || $target.is(':hidden')) {
                // Skip if target not found or hidden
                if (tourStep < tourSteps.length - 1) {
                    tourStep++;
                    showTourStep();
                } else {
                    endTour();
                }
                return;
            }

            // Highlight Target
            $target.addClass('wq-tour-highlight');
            
            // Scroll to target (if not inside popup, or handle popup scroll)
            if ($target.closest('#wq-edge-diagram-popup').length === 0) {
                $('html, body').animate({
                    scrollTop: $target.offset().top - 100
                }, 300);
            }

            // Update Modal Content
            $('#wq-tour-title').text(step.title);
            $('#wq-tour-content').html(step.content); // Use html() for bold tags
            
            // Buttons
            if (tourStep === 0) {
                $('#wq-tour-back').hide();
            } else {
                $('#wq-tour-back').show();
            }
            
            if (tourStep === tourSteps.length - 1) {
                $('#wq-tour-next').text('CLOSE');
            } else {
                $('#wq-tour-next').text('NEXT');
            }

            // Position Modal
            positionModal($target, step.position);
        }, 200); // 200ms delay
    }

    function positionModal($target, preferredPosition) {
        const $modal = $('#wq-tour-modal');
        const targetOffset = $target.offset();
        const targetWidth = $target.outerWidth();
        const targetHeight = $target.outerHeight();
        const modalWidth = $modal.outerWidth();
        const modalHeight = $modal.outerHeight();
        const windowWidth = $(window).width();
        const windowHeight = $(window).height();
        const scrollTop = $(window).scrollTop();
        
        let top, left, position = preferredPosition;
        const arrowSize = 10;
        
        // Force Top/Bottom on Mobile to ensure visibility
        if (windowWidth < 768) {
            // Prefer bottom usually, unless at bottom of screen
            if (preferredPosition === 'left' || preferredPosition === 'right') {
                position = 'bottom';
            }
        }
        
        // Reset Arrow
        $('.wq-tour-arrow').removeClass('wq-arrow-top wq-arrow-bottom wq-arrow-left wq-arrow-right').removeAttr('style');

        // Helper to check if fits
        function checkFit(pos) {
            if (pos === 'top') {
                return (targetOffset.top - modalHeight - arrowSize) >= scrollTop;
            } else if (pos === 'bottom') {
                return (targetOffset.top + targetHeight + modalHeight + arrowSize) <= (scrollTop + windowHeight);
            } else if (pos === 'left') {
                return (targetOffset.left - modalWidth - arrowSize) >= 0;
            } else if (pos === 'right') {
                return (targetOffset.left + targetWidth + modalWidth + arrowSize) <= windowWidth;
            }
            return false;
        }

        // Smart Positioning Logic (Flip if collision)
        if (!checkFit(position)) {
            if (position === 'top' && checkFit('bottom')) position = 'bottom';
            else if (position === 'bottom' && checkFit('top')) position = 'top';
            else if (position === 'left' && checkFit('right')) position = 'right';
            else if (position === 'right' && checkFit('left')) position = 'left';
            // Else keep preferred or default to bottom if completely stuck?
        }

        // Calculate Coordinates
        if (position === 'top') {
            top = targetOffset.top - modalHeight - arrowSize;
            left = targetOffset.left + (targetWidth / 2) - (modalWidth / 2);
            $('.wq-tour-arrow').addClass('wq-arrow-top').css({top: 'auto', bottom: -arrowSize + 'px', left: '50%', right: 'auto', margin: '0 0 0 -10px'});
        } else if (position === 'bottom') {
            top = targetOffset.top + targetHeight + arrowSize;
            left = targetOffset.left + (targetWidth / 2) - (modalWidth / 2);
            $('.wq-tour-arrow').addClass('wq-arrow-bottom').css({top: -arrowSize + 'px', bottom: 'auto', left: '50%', right: 'auto', margin: '0 0 0 -10px'});
        } else if (position === 'left') {
            top = targetOffset.top + (targetHeight / 2) - (modalHeight / 2);
            left = targetOffset.left - modalWidth - arrowSize;
            $('.wq-tour-arrow').addClass('wq-arrow-left').css({left: 'auto', right: -arrowSize + 'px', top: '50%', bottom: 'auto', margin: '-10px 0 0 0'});
        } else if (position === 'right') {
            top = targetOffset.top + (targetHeight / 2) - (modalHeight / 2);
            left = targetOffset.left + targetWidth + arrowSize;
            $('.wq-tour-arrow').addClass('wq-arrow-right').css({left: -arrowSize + 'px', right: 'auto', top: '50%', bottom: 'auto', margin: '-10px 0 0 0'});
        }

        // Keep within viewport (Horizontal clamping for top/bottom, Vertical clamping for left/right)
        const margin = (windowWidth < 768) ? 20 : 10; // More margin on mobile
        
        if (position === 'top' || position === 'bottom') {
            if (left < margin) left = margin;
            if (left + modalWidth > windowWidth - margin) left = windowWidth - modalWidth - margin;
            
            // Adjust arrow to point to target if modal shifted
            const arrowLeft = targetOffset.left + (targetWidth / 2) - left;
            // Clamp arrow position within modal
            const minArrow = 20; // corner radius + buffer
            const maxArrow = modalWidth - 20;
            const clampedArrow = Math.min(Math.max(arrowLeft, minArrow), maxArrow);
            
            $('.wq-tour-arrow').css({left: clampedArrow + 'px', marginLeft: '-10px'});
        } else if (position === 'left' || position === 'right') {
            // Keep within viewport vertically
            if (top < scrollTop + margin) top = scrollTop + margin;
            if (top + modalHeight > scrollTop + windowHeight - margin) top = scrollTop + windowHeight - modalHeight - margin;
            
            // Adjust arrow to point to target if modal shifted
            const arrowTop = targetOffset.top + (targetHeight / 2) - top;
            // Clamp arrow position within modal
            const minArrow = 20; 
            const maxArrow = modalHeight - 20;
            const clampedArrow = Math.min(Math.max(arrowTop, minArrow), maxArrow);
            
            $('.wq-tour-arrow').css({top: clampedArrow + 'px', marginTop: '-10px'});
        }
        
        $modal.css({
            top: top + 'px',
            left: left + 'px'
        });
    }

    // Tour Events
    $(document).off('click', '#wq-show-tour').on('click', '#wq-show-tour', function(e) {
        e.preventDefault();
        startTour();
    });

    $(document).off('click', '#wq-tour-next').on('click', '#wq-tour-next', function() {
        if (tourStep < tourSteps.length - 1) {
            tourStep++;
            showTourStep();
        } else {
            endTour();
        }
    });

    $(document).off('click', '#wq-tour-back').on('click', '#wq-tour-back', function() {
        if (tourStep > 0) {
            tourStep--;
            showTourStep();
        }
    });

    $(document).off('click', '#wq-tour-close').on('click', '#wq-tour-close', endTour);

    // Update row numbers after deletion
    function updateRowNumbers() {
        let count = 1;
        $('#wq-rows-container .wq-row').each(function(index) {
            $(this).find('.wq-row-num').text(count);
            
            // Hide remove button for the first row, show for others
            if (index === 0) {
                $(this).find('.wq-remove-btn').hide();
            } else {
                $(this).find('.wq-remove-btn').show();
            }
            
            count++;
        });
        rowCount = count - 1;
    }

    // Run Init
    init();
});
