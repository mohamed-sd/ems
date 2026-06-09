/*
 * EMS CSRF client helper.
 * Auto-attaches the CSRF token (from <meta name="csrf-token">) as an
 * "X-CSRF-Token" header to every same-origin, state-changing request
 * (POST / PUT / PATCH / DELETE) issued via fetch, XMLHttpRequest, or jQuery.
 *
 * This keeps the ~90 existing AJAX call sites working untouched: they keep
 * sending FormData/JSON as-is, and the token rides along in the header that
 * the server-side guard (ems_enforce_csrf_protection) checks.
 *
 * Loaded synchronously from inheader.php <head> so the patches are installed
 * before any page script fires its first request.
 */
(function () {
  'use strict';

  function token() {
    var m = document.querySelector('meta[name="csrf-token"]');
    return m ? m.getAttribute('content') : '';
  }

  var UNSAFE = /^(POST|PUT|PATCH|DELETE)$/i;

  // Same-origin check: relative URLs are always same-origin; absolute URLs are
  // compared against location.origin. Anything cross-origin is left untouched
  // so the token never leaks to third parties.
  function sameOrigin(url) {
    try {
      if (url === undefined || url === null || url === '') return true;
      var u = new URL(String(url), window.location.href);
      return u.origin === window.location.origin;
    } catch (e) {
      return true; // un-parseable → treat as relative/same-origin
    }
  }

  // ---- 1) fetch ----------------------------------------------------------
  if (window.fetch) {
    var origFetch = window.fetch;
    window.fetch = function (input, init) {
      try {
        init = init || {};
        var url = (typeof input === 'string') ? input : (input && input.url);
        var method = (init.method || (input && input.method) || 'GET');
        if (UNSAFE.test(method) && sameOrigin(url)) {
          var t = token();
          if (t) {
            var headers = new Headers(init.headers || (input && input.headers) || {});
            if (!headers.has('X-CSRF-Token')) {
              headers.set('X-CSRF-Token', t);
            }
            init.headers = headers;
            return origFetch.call(this, (typeof input === 'string') ? input : input, init);
          }
        }
      } catch (e) { /* fall through to original */ }
      return origFetch.call(this, input, init);
    };
  }

  // ---- 2) XMLHttpRequest --------------------------------------------------
  if (window.XMLHttpRequest) {
    var origOpen = XMLHttpRequest.prototype.open;
    var origSend = XMLHttpRequest.prototype.send;

    XMLHttpRequest.prototype.open = function (method, url) {
      this.__emsMethod = method;
      this.__emsUrl = url;
      return origOpen.apply(this, arguments);
    };

    XMLHttpRequest.prototype.send = function () {
      try {
        if (UNSAFE.test(this.__emsMethod || '') && sameOrigin(this.__emsUrl)) {
          var t = token();
          if (t) {
            // setRequestHeader throws if state isn't OPENED, hence the guard.
            this.setRequestHeader('X-CSRF-Token', t);
          }
        }
      } catch (e) { /* ignore — never block the request */ }
      return origSend.apply(this, arguments);
    };
  }

  // ---- 3) jQuery (if present, now or later) ------------------------------
  function hookJQuery($) {
    if (!$ || !$.ajaxPrefilter) return false;
    $.ajaxPrefilter(function (options, originalOptions, jqXHR) {
      try {
        var method = (options.type || options.method || 'GET');
        if (UNSAFE.test(method) && (options.crossDomain !== true) && sameOrigin(options.url)) {
          var t = token();
          if (t) jqXHR.setRequestHeader('X-CSRF-Token', t);
        }
      } catch (e) { /* ignore */ }
    });
    return true;
  }

  if (!hookJQuery(window.jQuery)) {
    // jQuery may load after this script; retry briefly until it appears.
    var tries = 0;
    var iv = setInterval(function () {
      if (hookJQuery(window.jQuery) || ++tries > 100) {
        clearInterval(iv);
      }
    }, 100);
  }
})();
