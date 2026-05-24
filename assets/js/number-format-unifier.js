(function () {
  var targetSystem = String(window.EMS_DIGIT_SYSTEM || 'latin').toLowerCase();
  var hasArabicDigits = /[٠-٩]/;
  var hasLatinDigits = /[0-9]/;
  var arabicDigits = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
  var blockedTags = {
    SCRIPT: true,
    STYLE: true,
    NOSCRIPT: true,
    TEXTAREA: true,
    PRE: true,
    CODE: true,
    KBD: true,
    SAMP: true,
    INPUT: true,
    SELECT: true,
    OPTION: true
  };
  var attributeNames = ['placeholder', 'title', 'aria-label', 'data-bs-original-title'];
  var scheduled = false;
  var pendingNodes = [];

  function toArabicIndic(value) {
    return value.replace(/[0-9]/g, function (digit) {
      return arabicDigits[digit];
    });
  }

  function toLatin(value) {
    return value.replace(/[٠-٩]/g, function (digit) {
      return String(arabicDigits.indexOf(digit));
    });
  }

  function normalizeDigits(value) {
    if (typeof value !== 'string' || value === '') {
      return value;
    }

    if (targetSystem === 'latin') {
      return hasArabicDigits.test(value) ? toLatin(value) : value;
    }

    return hasLatinDigits.test(value) ? toArabicIndic(value) : value;
  }

  function shouldSkipNode(node) {
    var parent = node.parentElement;

    while (parent) {
      if (blockedTags[parent.tagName] || parent.isContentEditable) {
        return true;
      }
      parent = parent.parentElement;
    }

    return false;
  }

  function processTextNode(node) {
    var originalValue;
    var normalizedValue;

    if (!node || node.nodeType !== Node.TEXT_NODE || shouldSkipNode(node)) {
      return;
    }

    originalValue = node.nodeValue;
    if (!originalValue || (!hasLatinDigits.test(originalValue) && !hasArabicDigits.test(originalValue))) {
      return;
    }

    normalizedValue = normalizeDigits(originalValue);
    if (normalizedValue !== originalValue) {
      node.nodeValue = normalizedValue;
    }
  }

  function processElementAttributes(element) {
    var i;
    var attrName;
    var originalValue;
    var normalizedValue;

    if (!element || element.nodeType !== Node.ELEMENT_NODE) {
      return;
    }

    if (blockedTags[element.tagName]) {
      return;
    }

    for (i = 0; i < attributeNames.length; i++) {
      attrName = attributeNames[i];
      if (!element.hasAttribute(attrName)) {
        continue;
      }

      originalValue = element.getAttribute(attrName);
      normalizedValue = normalizeDigits(originalValue);
      if (normalizedValue !== originalValue) {
        element.setAttribute(attrName, normalizedValue);
      }
    }
  }

  function processTree(root) {
    var walker;
    var currentNode;
    var elements;
    var i;

    if (!root) {
      return;
    }

    if (root.nodeType === Node.TEXT_NODE) {
      processTextNode(root);
      return;
    }

    if (root.nodeType !== Node.ELEMENT_NODE && root !== document) {
      return;
    }

    if (root === document) {
      root = document.body || document.documentElement;
    }

    if (!root) {
      return;
    }

    processElementAttributes(root);
    elements = root.querySelectorAll('[placeholder],[title],[aria-label],[data-bs-original-title]');
    for (i = 0; i < elements.length; i++) {
      processElementAttributes(elements[i]);
    }

    walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT, null);
    currentNode = walker.nextNode();
    while (currentNode) {
      processTextNode(currentNode);
      currentNode = walker.nextNode();
    }
  }

  function flushPending() {
    var nodes = pendingNodes.slice();
    var i;

    pendingNodes = [];
    scheduled = false;

    for (i = 0; i < nodes.length; i++) {
      processTree(nodes[i]);
    }
  }

  function scheduleProcess(node) {
    pendingNodes.push(node || document);
    if (scheduled) {
      return;
    }

    scheduled = true;
    if (window.requestAnimationFrame) {
      window.requestAnimationFrame(flushPending);
    } else {
      setTimeout(flushPending, 16);
    }
  }

  function initObserver() {
    if (!document.documentElement || typeof MutationObserver === 'undefined') {
      return;
    }

    var observer = new MutationObserver(function (mutations) {
      var i;
      var j;
      var mutation;

      for (i = 0; i < mutations.length; i++) {
        mutation = mutations[i];

        if (mutation.type === 'characterData' && mutation.target) {
          scheduleProcess(mutation.target);
        }

        if (mutation.type === 'attributes' && mutation.target) {
          scheduleProcess(mutation.target);
        }

        if (mutation.addedNodes && mutation.addedNodes.length) {
          for (j = 0; j < mutation.addedNodes.length; j++) {
            scheduleProcess(mutation.addedNodes[j]);
          }
        }
      }
    });

    observer.observe(document.documentElement, {
      childList: true,
      subtree: true,
      characterData: true,
      attributes: true,
      attributeFilter: attributeNames
    });
  }

  function boot() {
    processTree(document);
    initObserver();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})();
