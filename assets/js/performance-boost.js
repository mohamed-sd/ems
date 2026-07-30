(function () {
    var initialized = false;

    function applyDataTableDefaults($) {
        if (!$.fn || !$.fn.dataTable || initialized) {
            return;
        }

        initialized = true;
        $.fn.dataTable.ext.errMode = 'none';

        $.extend(true, $.fn.dataTable.defaults, {
            deferRender: true,
            processing: true,
            stateSave: true,
            autoWidth: false,
            pageLength: 50, // H-22 · UI-01 §9: «خمسون صفًّا افتراضًا»
            lengthMenu: [[10, 25, 50, 100, 250], [10, 25, 50, 100, 250]],
            searchDelay: 400 // UI-01 §4: تأخيرُ البحث 400ms قبل الإرسال
        });
    }

    function applyAjaxDefaults($) {
        if (!$.ajaxSetup) {
            return;
        }

        $.ajaxSetup({
            timeout: 25000
        });
    }

    function bootstrap() {
        if (!window.jQuery) {
            return;
        }

        var $ = window.jQuery;
        applyAjaxDefaults($);
        applyDataTableDefaults($);
    }

    var tries = 0;
    var timer = setInterval(function () {
        tries++;
        bootstrap();

        if (initialized || tries > 60) {
            clearInterval(timer);
        }
    }, 250);

    if (document.readyState === 'complete' || document.readyState === 'interactive') {
        bootstrap();
    } else {
        document.addEventListener('DOMContentLoaded', bootstrap);
    }
})();
