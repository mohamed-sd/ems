/*
 * EMS CSRF client helper.
 * Auto-attaches the CSRF token (from <meta name="csrf-token">) as an
 * "X-CSRF-Token" header to every same-origin, state-changing request
 * (POST / PUT / PATCH / DELETE) issued via fetch or XMLHttpRequest.
 *
 * This keeps the ~90 existing AJAX call sites working untouched: they keep
 * sending FormData/JSON as-is, and the token rides along in the header that
 * the server-side guard (ems_enforce_csrf_protection) checks.
 *
 * Loaded synchronously from inheader.php <head> so the patches are installed
 * before any page script fires its first request.
 *
 * INVARIANT — one attach, ever (2026-08-01): the header must be set at most
 * ONCE per request. XHR's setRequestHeader APPENDS on repeat calls, so a
 * double attach turns the value into "token, token" and the server's
 * hash_equals rejects it (403 on every jQuery POST under CSRF_ENFORCE_PATHS —
 * that is exactly the bug that broke Approvals/hours_approval). Hence:
 *   - NO jQuery ajaxPrefilter hook: jQuery rides on native XHR, which the
 *     prototype patch below already covers. A prefilter here would be the
 *     second (fatal) attach. Do not re-add it.
 *   - The setRequestHeader patch records any manual/library attach, and
 *     send() skips its own attach when one already happened.
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
  // Covers jQuery too (jQuery transmits over native XHR) — see INVARIANT
  // above: no second hook anywhere, and never attach twice (XHR appends).
  if (window.XMLHttpRequest) {
    var origOpen = XMLHttpRequest.prototype.open;
    var origSetHeader = XMLHttpRequest.prototype.setRequestHeader;
    var origSend = XMLHttpRequest.prototype.send;

    XMLHttpRequest.prototype.open = function (method, url) {
      this.__emsMethod = method;
      this.__emsUrl = url;
      this.__emsCsrfSet = false; // reset per open() — XHR objects are reusable
      return origOpen.apply(this, arguments);
    };

    XMLHttpRequest.prototype.setRequestHeader = function (name, value) {
      // A page/library attached the token itself (e.g. jQuery jqXHR headers):
      // remember it so send() does NOT attach again — the second call would
      // APPEND ("token, token") and fail server-side hash_equals.
      if (String(name).toLowerCase() === 'x-csrf-token') {
        this.__emsCsrfSet = true;
      }
      return origSetHeader.apply(this, arguments);
    };

    XMLHttpRequest.prototype.send = function () {
      try {
        if (!this.__emsCsrfSet && UNSAFE.test(this.__emsMethod || '') && sameOrigin(this.__emsUrl)) {
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
})();
