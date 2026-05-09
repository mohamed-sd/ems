(function () {
    'use strict';

    var inlineActionsId = 'emsInlineActions';
    var sourceHiddenClass = 'ems-source-action-hidden';

    function normalizeArabic(text) {
        return (text || '')
            .toString()
            .trim()
            .replace(/[\u064B-\u0652]/g, '')
            .replace(/[\u0640]/g, '')
            .replace(/[أإآ]/g, 'ا')
            .replace(/\s+/g, ' ')
            .toLowerCase();
    }

    function getElementText(el) {
        if (!el) {
            return '';
        }

        if (el.tagName === 'INPUT') {
            return el.value || el.getAttribute('value') || '';
        }

        return el.textContent || '';
    }

    function isVisible(el) {
        if (!el) {
            return false;
        }

        var style = window.getComputedStyle(el);
        return style.display !== 'none' && style.visibility !== 'hidden' && el.offsetParent !== null;
    }

    function isCandidate(el) {
        if (!isVisible(el)) {
            return false;
        }

        if (el.closest('#' + inlineActionsId)) {
            return false;
        }

        if (el.closest('.modal, .dropdown-menu, .dataTables_paginate, .dt-buttons')) {
            return false;
        }

        return true;
    }

    function findActionElement(type) {
        if (type === 'add') {
            var preferredAddSelectors = [
                '.main #toggleForm',
                '.main #toggleFormBtn',
                '.main .add-btn',
                '.main a.add',
                '.main button.add'
            ];

            for (var pa = 0; pa < preferredAddSelectors.length; pa++) {
                var preferredAdd = document.querySelector(preferredAddSelectors[pa]);
                if (preferredAdd && isCandidate(preferredAdd)) {
                    return preferredAdd;
                }
            }
        }

        if (type === 'back') {
            var preferredBackSelectors = [
                '.main .back-btn',
                '.main a[href*="dashboard.php"]'
            ];

            for (var pb = 0; pb < preferredBackSelectors.length; pb++) {
                var preferredBack = document.querySelector(preferredBackSelectors[pb]);
                if (preferredBack && isCandidate(preferredBack)) {
                    return preferredBack;
                }
            }
        }

        var selectors = '.main a, .main button, .main input[type="button"], .main input[type="submit"], a.add, button.add';
        var elements = document.querySelectorAll(selectors);

        for (var i = 0; i < elements.length; i++) {
            var el = elements[i];
            if (!isCandidate(el)) {
                continue;
            }

            var text = normalizeArabic(getElementText(el));
            if (!text) {
                continue;
            }

            if (type === 'add') {
                if (text.indexOf('اضافة') !== -1 || text.indexOf('اضف') !== -1) {
                    return el;
                }
            }

            if (type === 'back') {
                if (text.indexOf('رجوع') !== -1 || text.indexOf('الرجوع') !== -1 || text.indexOf('عودة') !== -1 || text.indexOf('back') !== -1) {
                    return el;
                }
            }
        }

        return null;
    }

    function createActionButton(type, label, iconClass) {
        var button = document.createElement('button');
        button.type = 'button';
        button.className = 'ems-top-action-btn ems-top-action-' + type;

        var icon = document.createElement('i');
        icon.className = iconClass;

        var span = document.createElement('span');
        span.textContent = label;

        button.appendChild(icon);
        button.appendChild(span);
        return button;
    }

    function findHeaderActionsContainer(header) {
        if (!header) {
            return null;
        }

        var existing = header.querySelector('.page-header-actions, .pu-page-header-actions, .ems-page-header-actions');
        if (existing) {
            existing.classList.add('ems-page-header-actions');
            return existing;
        }

        var created = document.createElement('div');
        created.className = 'ems-page-header-actions';
        header.appendChild(created);
        return created;
    }

    function normalizeHeaderActions() {
        var headers = document.querySelectorAll('.main .page-header');
        if (!headers.length) {
            return false;
        }

        headers.forEach(function (header) {
            var title = header.querySelector('.page-title, h1, h2, h3');
            var titleHost = null;
            if (title) {
                title.classList.add('ems-page-title');

                titleHost = title;
                while (titleHost && titleHost.parentElement !== header) {
                    titleHost = titleHost.parentElement;
                }
            }

            var actionsContainer = findHeaderActionsContainer(header);
            if (!actionsContainer) {
                return;
            }

            var backInHeader = header.querySelector('.back-btn');
            if (backInHeader) {
                backInHeader.classList.add('ems-unified-back');

                // Place back button at the far right, directly before the title in RTL flow.
                if (titleHost && backInHeader.parentNode !== header) {
                    header.insertBefore(backInHeader, titleHost);
                }
            }

            var actionItems = actionsContainer.querySelectorAll('a, button, input[type="button"], input[type="submit"]');
            if (!actionItems.length) {
                return;
            }

            var primaryAdd = null;

            actionItems.forEach(function (item) {
                item.classList.add('ems-header-action');

                if (item.classList.contains('back-btn') || item.classList.contains('ems-unified-back')) {
                    return;
                }

                var isAdd = item.classList.contains('add-btn')
                    || item.id === 'toggleForm'
                    || item.id === 'toggleFormBtn'
                    || item.classList.contains('add')
                    || normalizeArabic(getElementText(item)).indexOf('اضافة') !== -1
                    || normalizeArabic(getElementText(item)).indexOf('اضف') !== -1;

                if (isAdd && !primaryAdd) {
                    primaryAdd = item;
                    item.classList.add('ems-unified-add');
                }
            });

            if (primaryAdd && primaryAdd.parentNode === actionsContainer) {
                // Keep add action at far left by making it the highest-order item.
                primaryAdd.style.order = '100';
            }
        });

        return true;
    }

    function decorateSourceButton(el, type) {
        if (!el) {
            return;
        }

        el.classList.add('ems-btn-unified');

        if (type === 'add') {
            el.classList.add('ems-btn-add');
        }

        if (type === 'back') {
            el.classList.add('ems-btn-back');
        }
    }

    function clickSource(source, fallback) {
        return function () {
            if (source && source.isConnected) {
                source.click();

                // Resilient fallback for pages where source click might not wire correctly.
                setTimeout(function () {
                    if (!ensureKnownFormVisible()) {
                        return;
                    }
                }, 0);
                return;
            }

            fallback();
        };
    }

    function ensureKnownFormVisible() {
        var projectForm = document.getElementById('projectForm');
        if (projectForm) {
            var madeVisible = false;

            if (projectForm.classList.contains('fleet-hidden')) {
                projectForm.classList.remove('fleet-hidden');
                madeVisible = true;
            }

            if (projectForm.classList.contains('pu-hidden')) {
                projectForm.classList.remove('pu-hidden');
                madeVisible = true;
            }

            if (projectForm.classList.contains('allforms') && !projectForm.classList.contains('allforms-visible')) {
                projectForm.classList.add('allforms-visible');
                madeVisible = true;
            }

            if (projectForm.style && projectForm.style.display === 'none') {
                projectForm.style.display = 'block';
                madeVisible = true;
            }

            if (madeVisible && typeof projectForm.scrollIntoView === 'function') {
                projectForm.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }

            if (madeVisible) {
                return true;
            }
        }

        var addEditCard = document.getElementById('addEditCard');
        if (addEditCard && addEditCard.classList.contains('fc-hidden')) {
            addEditCard.classList.remove('fc-hidden');
            if (typeof addEditCard.scrollIntoView === 'function') {
                addEditCard.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
            return true;
        }

        return false;
    }

    function ensureActionBar() {
        if (document.querySelector('.movement-page')) {
            return;
        }

        var root = document.querySelector('.main') || document.body;
        if (!root) {
            return;
        }

        // Preferred mode: normalize existing page header actions.
        if (normalizeHeaderActions()) {
            var oldInlineActions = document.getElementById(inlineActionsId);
            if (oldInlineActions) {
                oldInlineActions.remove();
            }
            return;
        }

        // Reset any hidden source actions from previous render before rebuilding.
        var previouslyHidden = document.querySelectorAll('.' + sourceHiddenClass);
        for (var ph = 0; ph < previouslyHidden.length; ph++) {
            previouslyHidden[ph].classList.remove(sourceHiddenClass);
        }

        var existingInlineActions = document.getElementById(inlineActionsId);
        if (existingInlineActions) {
            existingInlineActions.remove();
        }

        var addSource = findActionElement('add');
        var backSource = findActionElement('back');

        if (!addSource && !backSource) {
            return;
        }

        decorateSourceButton(addSource, 'add');
        decorateSourceButton(backSource, 'back');

        var inlineActions = document.createElement('div');
        var addWrap = document.createElement('div');
        var backWrap = document.createElement('div');
        var title = root.querySelector('h1, h2, h3');

        inlineActions.id = inlineActionsId;
        inlineActions.className = 'ems-inline-actions';
        addWrap.className = 'ems-inline-actions-left';
        backWrap.className = 'ems-inline-actions-right';

        if (addSource) {
            var addButton = createActionButton('add', 'إضافة', 'fa fa-plus');
            addButton.addEventListener('click', clickSource(addSource, function () {}));
            addWrap.appendChild(addButton);
            addSource.classList.add(sourceHiddenClass);
        }

        if (backSource) {
            var backButton = createActionButton('back', 'رجوع', 'fa fa-arrow-right');

            backButton.addEventListener('click', clickSource(backSource, function () {
                if (window.history.length > 1) {
                    window.history.back();
                } else {
                    window.location.href = '/ems/main/dashboard.php';
                }
            }));
            backWrap.appendChild(backButton);
            backSource.classList.add(sourceHiddenClass);
        }

        inlineActions.appendChild(addWrap);
        inlineActions.appendChild(backWrap);

        if (title) {
            title.parentNode.insertBefore(inlineActions, title);
        } else {
            root.insertBefore(inlineActions, root.firstChild);
        }
    }

    function debounce(fn, delay) {
        var timer;
        return function () {
            clearTimeout(timer);
            timer = setTimeout(fn, delay);
        };
    }

    var refreshActionBar = debounce(ensureActionBar, 120);

    function boot() {
        ensureActionBar();

        // Retry a few times after load for pages that render controls late.
        setTimeout(ensureActionBar, 200);
        setTimeout(ensureActionBar, 700);
        setTimeout(ensureActionBar, 1400);

        window.addEventListener('resize', refreshActionBar);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
