/**
 * TecninaBotLabUiTest.js
 *
 * Browser/DOM runtime regression for Bot Lab Automated Tests rendering.
 * Exercises panel activation, DOM hierarchy integrity, catalog rendering,
 * error state rendering, repeated activation idempotency, and single-binding
 * listener semantics under a lightweight DOM environment without heavy external dependencies.
 */

'use strict';

const fs = require('fs');
const path = require('path');

let assertions = 0;
function expect(cond, msg) {
    assertions++;
    if (!cond) {
        console.error('FAIL: ' + msg);
        process.exit(1);
    }
}

const root = path.resolve(__dirname, '..');
const viewHtml = fs.readFileSync(path.join(root, 'application/views/tecnina_whatsapp/bot_lab.php'), 'utf8');
const jsCode = fs.readFileSync(path.join(root, 'assets/tecnina/js/bot-lab.js'), 'utf8');

// =========================================================================
// Suite 1: Structural DOM Hierarchy Integrity
// =========================================================================
console.log('--- Suite 1: DOM Hierarchy Integrity ---');

// 1.1 Verify #panel-interactive-mode closes BEFORE #panel-scenarios-mode opens
const interactivePos = viewHtml.indexOf('id="panel-interactive-mode"');
const scenariosPos = viewHtml.indexOf('id="panel-scenarios-mode"');
expect(interactivePos !== -1, 'id="panel-interactive-mode" must exist in view HTML');
expect(scenariosPos !== -1, 'id="panel-scenarios-mode" must exist in view HTML');
expect(interactivePos < scenariosPos, 'panel-interactive-mode must be defined before panel-scenarios-mode');

// Check that panel-interactive-mode close marker exists between interactivePos and scenariosPos
const interactiveStart = viewHtml.lastIndexOf('<div', interactivePos);
const between = viewHtml.substring(interactiveStart, scenariosPos);
expect(between.includes('End #panel-interactive-mode'), 'End #panel-interactive-mode marker must occur before panel-scenarios-mode');
expect(between.includes('End #bot-lab-workbench'), 'End #bot-lab-workbench marker must occur before panel-scenarios-mode');

// 1.2 Tag balance check: Count all <div> and </div> tokens across viewHtml
const divOpenTokens = viewHtml.match(/<div\b[^>]*>/gi) || [];
const divCloseTokens = viewHtml.match(/<\/div>/gi) || [];
expect(divOpenTokens.length === divCloseTokens.length,
    'DIV tags must be perfectly balanced: opened=' + divOpenTokens.length + ', closed=' + divCloseTokens.length);

// Verify that within the substring between interactivePos and scenariosPos, all inner divs are closed
const tokensBetween = between.match(/<div\b[^>]*>|<\/div>/gi) || [];
let depthBetween = 0;
for (const t of tokensBetween) {
    if (t.toLowerCase().startsWith('</')) depthBetween--;
    else depthBetween++;
}
expect(depthBetween === 0, 'All divs inside panel-interactive-mode must be closed before panel-scenarios-mode (depth=' + depthBetween + ')');

// =========================================================================
// Suite 2: Lightweight DOM & Script Runtime Regression
// =========================================================================
console.log('--- Suite 2: Runtime Initialization & State Rendering ---');

class MockElement {
    constructor(tagName, id, className) {
        this.tagName = (tagName || 'DIV').toUpperCase();
        this.id = id || '';
        this.className = className || '';
        this.children = [];
        this.parentElement = null;
        this.style = {};
        this.attributes = {};
        this.events = {};
        this.textContent_ = '';
        this.value_ = '';
        this.disabled = false;
    }

    appendChild(child) {
        if (!child) return child;
        child.parentElement = this;
        this.children.push(child);
        return child;
    }

    append() {
        for (let i = 0; i < arguments.length; i++) {
            const c = arguments[i];
            if (typeof c === 'string') {
                const tn = new MockElement('#text');
                tn.textContent_ = c;
                this.appendChild(tn);
            } else if (c instanceof MockElement) {
                this.appendChild(c);
            }
        }
        return this;
    }

    empty() {
        this.children = [];
        this.textContent_ = '';
        return this;
    }

    addClass(cls) {
        const parts = (this.className || '').split(/\s+/).filter(Boolean);
        if (parts.indexOf(cls) === -1) parts.push(cls);
        this.className = parts.join(' ');
        return this;
    }

    removeClass(cls) {
        const parts = (this.className || '').split(/\s+/).filter(Boolean);
        this.className = parts.filter(c => c !== cls).join(' ');
        return this;
    }

    hasClass(cls) {
        return (this.className || '').split(/\s+/).indexOf(cls) !== -1;
    }

    attr(name, val) {
        if (val === undefined) return this.attributes[name];
        this.attributes[name] = String(val);
        return this;
    }

    prop(name, val) {
        if (val === undefined) {
            if (name === 'disabled') return this.disabled;
            return this[name];
        }
        this[name] = val;
        return this;
    }

    val(val) {
        if (val === undefined) return this.value_;
        this.value_ = String(val);
        return this;
    }

    text(val) {
        if (val === undefined) return this.textContent_;
        this.textContent_ = String(val);
        return this;
    }

    show() {
        this.style.display = 'block';
        return this;
    }

    hide() {
        this.style.display = 'none';
        return this;
    }

    css(prop, val) {
        if (val === undefined) return this.style[prop];
        this.style[prop] = val;
        return this;
    }

    on(event, handler) {
        if (!this.events[event]) this.events[event] = [];
        this.events[event].push(handler);
        return this;
    }

    trigger(event, data) {
        const handlers = this.events[event] || [];
        for (let i = 0; i < handlers.length; i++) {
            handlers[i].call(this, { preventDefault: () => {}, stopPropagation: () => {} }, data);
        }
        return this;
    }

    // Critical: Checks effective visibility considering ancestor display
    isEffectivelyVisible() {
        let cur = this;
        while (cur) {
            if (cur.style && cur.style.display === 'none') {
                return false;
            }
            cur = cur.parentElement;
        }
        return true;
    }

    closest(selector) {
        let cur = this.parentElement;
        while (cur) {
            if (selector.startsWith('.') && cur.hasClass(selector.slice(1))) return cur;
            if (selector.startsWith('#') && cur.id === selector.slice(1)) return cur;
            cur = cur.parentElement;
        }
        return null;
    }

    find(selector) {
        const res = [];
        function walk(node) {
            for (let i = 0; i < node.children.length; i++) {
                const c = node.children[i];
                if (selector.startsWith('#') && c.id === selector.slice(1)) res.push(c);
                else if (selector.startsWith('.') && c.hasClass(selector.slice(1))) res.push(c);
                else if (c.tagName.toLowerCase() === selector.toLowerCase()) res.push(c);
                walk(c);
            }
        }
        walk(this);
        return res;
    }
}

function createTestHarness() {
    const registry = {};

    function getOrCreate(id, tag, cls) {
        if (!registry[id]) {
            registry[id] = new MockElement(tag || 'div', id, cls || '');
        }
        return registry[id];
    }

    // Root container
    const rootBox = getOrCreate('widget-content', 'div', 'widget-content');

    // Navigation
    const navInteractiveLi = new MockElement('li', '', 'active');
    const tabInteractive = getOrCreate('tab-nav-interactive', 'a');
    navInteractiveLi.appendChild(tabInteractive);

    const navScenariosLi = new MockElement('li', '', '');
    const tabScenarios = getOrCreate('tab-nav-scenarios', 'a');
    navScenariosLi.appendChild(tabScenarios);

    const scNavBadge = getOrCreate('sc-nav-badge', 'span', 'badge badge-info');
    tabScenarios.appendChild(scNavBadge);

    // Panel: Interactive
    const panelInteractive = getOrCreate('panel-interactive-mode', 'div');
    panelInteractive.style.display = 'block';
    rootBox.appendChild(panelInteractive);

    // Panel: Scenarios (Sibling, NOT child!)
    const panelScenarios = getOrCreate('panel-scenarios-mode', 'div');
    panelScenarios.style.display = 'none';
    rootBox.appendChild(panelScenarios);

    // Scenario components inside panelScenarios
    const scHeaderCount = getOrCreate('sc-header-count', 'span');
    const scFilterText = getOrCreate('sc-filter-text', 'input');
    const scFilterTag = getOrCreate('sc-filter-tag', 'select');
    const btnRunSelected = getOrCreate('btn-run-selected', 'button');
    const btnRunAll = getOrCreate('btn-run-all-scenarios', 'button');
    const scWarning = getOrCreate('sc-selection-warning', 'div');
    scWarning.style.display = 'none';
    const scCatalogBox = getOrCreate('sc-catalog-box', 'div');
    const scCatalogList = getOrCreate('sc-catalog-list', 'div');
    const scSummaryBox = getOrCreate('sc-summary-box', 'div');
    scSummaryBox.style.display = 'none';
    const scResults = getOrCreate('sc-results-container', 'div');

    panelScenarios.append(scHeaderCount, scFilterText, scFilterTag, btnRunSelected, btnRunAll, scWarning, scCatalogBox, scSummaryBox, scResults);
    scCatalogBox.append(scCatalogList);

    // Config
    const configEl = getOrCreate('bot-lab-config', 'div');
    configEl.attr('data-base', 'http://gestao.tecnina.com/index.php/tecnina_whatsapp');
    configEl.attr('data-csrf-name', 'csrf_test');
    configEl.attr('data-csrf-hash', 'hash_test');

    const errorEl = getOrCreate('bot-lab-error', 'div');
    errorEl.style.display = 'none';
    const successEl = getOrCreate('bot-lab-success', 'div');
    successEl.style.display = 'none';

    let lastAjax = null;
    let lastDeferred = null;

    // Lightweight jQuery wrapper
    function $(target) {
        if (typeof target === 'string') {
            if (target.includes('sc-select-checkbox:checked')) {
                const catalog = registry['sc-catalog-list'];
                const checkedBoxes = [];
                if (catalog) {
                    function findChecked(node) {
                        for (let i = 0; i < node.children.length; i++) {
                            const c = node.children[i];
                            if (c.hasClass('sc-select-checkbox') && c.prop('checked')) {
                                checkedBoxes.push(c);
                            }
                            findChecked(c);
                        }
                    }
                    findChecked(catalog);
                }
                const wrapper = {
                    length: checkedBoxes.length,
                    each: fn => {
                        checkedBoxes.forEach((item, idx) => fn.call(item, idx, item));
                        return wrapper;
                    }
                };
                return wrapper;
            }
            if (target.startsWith('#')) {
                const id = target.slice(1);
                const el = registry[id] || getOrCreate(id);
                const wrapper = {
                    0: el,
                    length: 1,
                    parent: () => $(el.parentElement),
                    addClass: cls => { el.addClass(cls); return wrapper; },
                    removeClass: cls => { el.removeClass(cls); return wrapper; },
                    show: () => { el.show(); return wrapper; },
                    hide: () => { el.hide(); return wrapper; },
                    empty: () => { el.empty(); return wrapper; },
                    append: function() {
                        for (let i = 0; i < arguments.length; i++) {
                            const arg = arguments[i];
                            if (arg && arg[0]) el.append(arg[0]);
                            else el.append(arg);
                        }
                        return wrapper;
                    },
                    text: val => val !== undefined ? (el.text(val), wrapper) : el.text(),
                    val: val => val !== undefined ? (el.val(val), wrapper) : el.val(),
                    attr: (k, v) => v !== undefined ? (el.attr(k, v), wrapper) : el.attr(k),
                    prop: (k, v) => v !== undefined ? (el.prop(k, v), wrapper) : el.prop(k),
                    on: (ev, fn) => { el.on(ev, fn); return wrapper; },
                    trigger: (ev, d) => { el.trigger(ev, d); return wrapper; },
                    closest: sel => $(el.closest(sel)),
                    find: sel => {
                        const res = el.find(sel);
                        return {
                            length: res.length,
                            each: fn => res.forEach((item, i) => fn.call(item, i, item))
                        };
                    },
                    each: fn => { fn.call(el, 0, el); return wrapper; }
                };
                return wrapper;
            }
            if (target.startsWith('<') && target.endsWith('>')) {
                const tagMatch = target.match(/<([a-z0-9]+)/i);
                const tag = tagMatch ? tagMatch[1] : 'div';
                const el = new MockElement(tag);
                const wrapper = {
                    0: el,
                    length: 1,
                    addClass: cls => { el.addClass(cls); return wrapper; },
                    attr: (k, v) => { if (k === 'id') el.id = v; el.attr(k, v); return wrapper; },
                    text: val => { el.text(val); return wrapper; },
                    append: function() {
                        for (let i = 0; i < arguments.length; i++) {
                            const arg = arguments[i];
                            if (arg && arg[0]) el.append(arg[0]);
                            else el.append(arg);
                        }
                        return wrapper;
                    },
                    val: val => { el.val(val); return wrapper; },
                    css: (k, v) => { el.css(k, v); return wrapper; },
                    prop: (k, v) => { el.prop(k, v); return wrapper; }
                };
                return wrapper;
            }
        }
        if (target instanceof MockElement || (target && target.tagName)) {
            const wrapper = {
                0: target,
                length: 1,
                parent: () => $(target.parentElement),
                addClass: cls => { target.addClass(cls); return wrapper; },
                removeClass: cls => { target.removeClass(cls); return wrapper; },
                show: () => { target.show(); return wrapper; },
                hide: () => { target.hide(); return wrapper; },
                empty: () => { target.empty(); return wrapper; },
                append: function() {
                    for (let i = 0; i < arguments.length; i++) {
                        const arg = arguments[i];
                        if (arg && arg[0]) target.append(arg[0]);
                        else target.append(arg);
                    }
                    return wrapper;
                },
                text: val => val !== undefined ? (target.text(val), wrapper) : target.text(),
                val: val => val !== undefined ? (target.val(val), wrapper) : target.val(),
                attr: (k, v) => v !== undefined ? (target.attr(k, v), wrapper) : target.attr(k),
                hasClass: cls => target.hasClass(cls),
                prop: (k, v) => v !== undefined ? (target.prop(k, v), wrapper) : target.prop(k),
                closest: sel => $(target.closest(sel))
            };
            return wrapper;
        }
        const emptyWrapper = {
            length: 0,
            on: () => emptyWrapper,
            trigger: () => emptyWrapper,
            show: () => emptyWrapper,
            hide: () => emptyWrapper,
            addClass: () => emptyWrapper,
            removeClass: () => emptyWrapper,
            empty: () => emptyWrapper,
            append: () => emptyWrapper,
            text: () => '',
            val: () => '',
            attr: () => undefined,
            prop: () => undefined,
            hasClass: () => false,
            closest: () => emptyWrapper,
            parent: () => emptyWrapper,
            find: () => emptyWrapper,
            each: () => emptyWrapper
        };
        return emptyWrapper;
    }

    $.ajax = function (opts) {
        lastAjax = opts;
        const deferred = {
            done: function (cb) {
                deferred._done = cb;
                return deferred;
            },
            fail: function (cb) {
                deferred._fail = cb;
                return deferred;
            }
        };
        lastDeferred = deferred;
        return deferred;
    };

    return {
        $,
        registry,
        getLastAjax: () => lastAjax,
        respondAjaxSuccess: (data) => {
            if (lastDeferred && lastDeferred._done) {
                lastDeferred._done({ ok: true, status: 200, data, csrf: 'csrf_rot' });
            }
        },
        respondAjaxFail: (status, reason) => {
            if (lastDeferred && lastDeferred._fail) {
                lastDeferred._fail({ status, responseJSON: { ok: false, status, reason } });
            }
        }
    };
}

// =========================================================================
// Execute Regression Tests
// =========================================================================

// Test 2.1: Automated Tests Activation & Loading Indicator
console.log('Test 2.1: Automated Tests Activation & Loading Indicator...');
const harness = createTestHarness();

const vm = require('vm');
const sandbox = {
    console: console,
    jQuery: harness.$,
    $: harness.$,
    window: { location: { search: '', href: 'http://gestao.tecnina.com' }, setTimeout: setTimeout, clearTimeout: clearTimeout },
    document: {
        createElement: tag => new MockElement(tag),
        createTextNode: txt => { const tn = new MockElement('#text'); tn.textContent_ = txt; return tn; }
    }
};

vm.createContext(sandbox);

vm.runInContext(jsCode, sandbox);


const pInteractive = harness.registry['panel-interactive-mode'];
const pScenarios = harness.registry['panel-scenarios-mode'];

expect(pInteractive.isEffectivelyVisible() === true, 'Initial state: panel-interactive-mode must be visible');
expect(pScenarios.isEffectivelyVisible() === false, 'Initial state: panel-scenarios-mode must be hidden');

// Click Automated Tests tab
harness.registry['tab-nav-scenarios'].trigger('click');

expect(pInteractive.isEffectivelyVisible() === false, 'After activation: panel-interactive-mode must be hidden');
expect(pScenarios.isEffectivelyVisible() === true, 'After activation: panel-scenarios-mode must be visible');

// Verify loading state is visible in sc-catalog-list while request is pending
const catalogList = harness.registry['sc-catalog-list'];
expect(catalogList.children.length > 0, 'sc-catalog-list must not be blank while loading');
const loadingNode = catalogList.children[0];
expect(loadingNode.attributes['id'] === 'sc-catalog-loading', 'Loading indicator #sc-catalog-loading must be rendered');

// Test 2.2: Catalog Success Renders Cards and Eliminates Blank Area
console.log('Test 2.2: Catalog Render Test...');
const mockScenarios = [
    { id: 'sc-1', title: 'Cenario 1', description: 'Desc 1', tags: ['t1'], valid: true, case_count: 2 },
    { id: 'sc-2', title: 'Cenario 2', description: 'Desc 2', tags: ['t2'], valid: false, validation_error: 'Bad YAML', case_count: 1 }
];

harness.respondAjaxSuccess({
    scenarios: mockScenarios,
    scenario_count: 2,
    case_count: 3,
    tags: ['t1', 't2'],
    invalid_count: 1
});

expect(pScenarios.isEffectivelyVisible() === true, 'Panel must remain visible after catalog response');

expect(catalogList.children.length === 2, 'Catalog must render exactly 2 items (found ' + catalogList.children.length + ')');
expect(harness.registry['sc-nav-badge'].textContent_ === '3', 'Nav badge must display case_count (3)');
expect(harness.registry['sc-header-count'].textContent_.includes('2 cenários'), 'Header count must display 2 cenários');

// Test 2.3: Idempotent Repeated Activation (No Duplication)
console.log('Test 2.3: Repeated Activation Idempotency Test...');
harness.registry['tab-nav-interactive'].trigger('click');
expect(pInteractive.isEffectivelyVisible() === true, 'Switch to INTERACTIVE makes interactive visible');
expect(pScenarios.isEffectivelyVisible() === false, 'Switch to INTERACTIVE makes scenarios hidden');

harness.registry['tab-nav-scenarios'].trigger('click');
expect(pInteractive.isEffectivelyVisible() === false, 'Switch back to SCENARIOS makes interactive hidden');
expect(pScenarios.isEffectivelyVisible() === true, 'Switch back to SCENARIOS makes scenarios visible');

expect(catalogList.children.length === 2, 'Repeated activation must NOT duplicate scenario cards');

// Test 2.4: Catalog Error State Renders Visible Alert (Not Blank)
console.log('Test 2.4: Catalog Error State Rendering...');
const errHarness = createTestHarness();
const errSandbox = {
    console: console,
    jQuery: errHarness.$,
    $: errHarness.$,
    window: {
        location: { search: '', href: 'http://gestao.tecnina.com' },
        setTimeout: fn => fn(),
        clearTimeout: () => {}
    },
    document: {
        createElement: tag => new MockElement(tag),
        createTextNode: txt => { const tn = new MockElement('#text'); tn.textContent_ = txt; return tn; }
    }
};
vm.createContext(errSandbox);
vm.runInContext(jsCode, errSandbox);

// Trigger activation and simulate 503 gateway error
errHarness.registry['tab-nav-scenarios'].trigger('click');
errHarness.respondAjaxFail(503, 'gateway_unavailable'); // Initial GET failure triggers retry
errHarness.respondAjaxFail(503, 'gateway_unavailable'); // Retry failure triggers fail callback

const errPanel = errHarness.registry['panel-scenarios-mode'];
const errList = errHarness.registry['sc-catalog-list'];
expect(errPanel.isEffectivelyVisible() === true, 'Panel must remain visible upon catalog failure');
expect(errList.children.length > 0, 'Catalog list must NOT be empty upon error');
const errorItem = errList.children[0];
expect(errorItem.attributes['id'] === 'sc-catalog-error', 'Error notice #sc-catalog-error must be rendered in list');
const errorText = errorItem.children.map(c => c.textContent_).join(' ');
expect(errorText.includes('indisponível') || errorText.includes('gateway'),
    'Safe formatted error message must be visible');

// Test 2.5: Single Binding for Execution Handlers & Payload Contracts
console.log('Test 2.5: Single Event Listener Binding & Payload Contracts...');
let runExecutionCalled = 0;
const origAjax = harness.$.ajax;
harness.$.ajax = function(opts) {
    if (opts.url && opts.url.includes('simulador_executar_cenarios')) {
        runExecutionCalled++;
    }
    return origAjax(opts);
};

harness.registry['btn-run-all-scenarios'].trigger('click');
expect(runExecutionCalled === 1, 'Run all click must execute exactly once (called ' + runExecutionCalled + ')');

// Section 13: Assert Run All sends payload = "{}"
const runAllAjax = harness.getLastAjax();
expect(runAllAjax && runAllAjax.data, 'Run All must issue AJAX request with data');
expect(runAllAjax.data.payload === '{}', 'Run All click must send payload = "{}" (found ' + (runAllAjax.data ? runAllAjax.data.payload : 'none') + ')');

harness.respondAjaxSuccess({ status: 'success', total_scenarios: 2, total_cases: 2, suite_status: 'SUCCESS', summary: {}, results: [] });

// Section 13: Assert Run Selected sends payload JSON containing scenario_ids
const catalogListEl = harness.registry['sc-catalog-list'];
expect(catalogListEl.children.length > 0, 'Catalog list must contain scenario items for selection test');
const checkboxes = catalogListEl.find('input');
expect(checkboxes.length > 0, 'Scenario item must have input checkbox');
checkboxes[0].prop('checked', true);

harness.registry['btn-run-selected'].trigger('click');
expect(runExecutionCalled === 2, 'Run selected click must trigger execution request');
const runSelectedAjax = harness.getLastAjax();
expect(runSelectedAjax && runSelectedAjax.data, 'Run Selected must issue AJAX request with data');
expect(typeof runSelectedAjax.data.payload === 'string', 'Run Selected payload must be string');
expect(runSelectedAjax.data.payload.includes('scenario_ids'), 'Run Selected payload must contain scenario_ids');
const parsedSelected = JSON.parse(runSelectedAjax.data.payload);
expect(Array.isArray(parsedSelected.scenario_ids), 'Run Selected parsed payload must contain scenario_ids array');
expect(parsedSelected.scenario_ids.includes('sc-1'), 'Run Selected scenario_ids must include selected scenario id');
harness.respondAjaxSuccess({ status: 'success', total_scenarios: 1, total_cases: 2, suite_status: 'SUCCESS', summary: {}, results: [] });

// Switching tabs and clicking again
harness.registry['tab-nav-interactive'].trigger('click');
harness.registry['tab-nav-scenarios'].trigger('click');
harness.registry['btn-run-all-scenarios'].trigger('click');
expect(runExecutionCalled === 3, 'Click after tab switch must execute exactly once more (total ' + runExecutionCalled + ')');

console.log('\nTecninaBotLabUiTest: ' + assertions + ' assertions passed cleanly.');
