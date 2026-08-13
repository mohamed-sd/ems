<?php
/**
 * includes/deny_page.php — صفحةُ الحجبِ الموحَّدة (INJ-0500)
 * ═══════════════════════════════════════════════════════════════════════════
 * **العيب**: سبعُ صفحاتِ حجبٍ مبنيةٌ بتسلسلِ نصوصٍ وأنماطٍ داخلَ `body` في
 * `includes/security.php` و`app/Services/Portal/SupplierPortalGuard.php` —
 * ولا واحدةَ منها تعرض **رمزًا** يميّز سببَ الحجبِ ولا **المسارَ المطلوب**،
 * وأربعٌ منها بلا وسمِ `viewport` فتظهر مصغَّرةً على الهاتف. وهي بالضبط ما
 * يراه المستخدَمُ المحجوب: أوّلُ شاشةٍ يقابلها عند الخطأ، وأسوأُ موضعٍ يُترك فيه
 * بلا معلومةٍ يبلّغ بها.
 *
 * **المكوّن** واحدٌ لكلِّ الحالات، ويحمل ثلاثةً لا يُستغنى عن واحدٍ منها:
 *   ① **رمزٌ** يميّز الحالةَ (`CSRF-403` · `GOV-ROLE-403` · `RATE-429` …) —
 *      فبلاغُ المستخدمِ بلا رمزٍ يُطارَد بالتخمين.
 *   ② **سببٌ** بلغةِ المستخدمِ لا بلغةِ النظام.
 *   ③ **المسارُ المطلوب** — فالمحجوبُ لا يذكر أيَّ رابطٍ ضغط.
 *
 * وهو **مستقلٌّ عن القشرة**: يُصيَّر في سياقاتٍ لا جلسةَ فيها ولا اتصالَ
 * بالقاعدة (فشلُ CSRF · تجاوزُ المحاولات) — فلا يُضمِّن `inheader` ولا يعتمد
 * على أصلٍ خارجيّ. الأنماطُ موضعيةٌ **عمدًا** هنا وحدَها ولهذا السبب.
 * ═══════════════════════════════════════════════════════════════════════════
 */

if (!function_exists('ems_deny_page')) {
    /**
     * @param string $code    الرمزُ المميِّز (يظهر للمستخدمِ ويُقتبس في البلاغ)
     * @param string $title   العنوانُ القصير
     * @param string $reason  السببُ بلغةِ المستخدم
     * @param array  $opts    status:int · exit_url:string · exit_label:string · hint:string
     */
    function ems_deny_page($code, $title, $reason, array $opts = array())
    {
        $status = isset($opts['status']) ? (int) $opts['status'] : 403;
        if (!headers_sent()) {
            http_response_code($status);
            header('Content-Type: text/html; charset=UTF-8');
        }
        $esc = function ($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); };
        $path = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI']
              : (isset($_SERVER['SCRIPT_NAME']) ? (string) $_SERVER['SCRIPT_NAME'] : '');
        $exitUrl = isset($opts['exit_url']) ? (string) $opts['exit_url'] : '';
        $exitLbl = isset($opts['exit_label']) ? (string) $opts['exit_label'] : '← العودة للصفحة الرئيسية';
        $hint = isset($opts['hint']) ? (string) $opts['hint'] : '';

        echo '<!DOCTYPE html><html lang="ar" dir="rtl"><head><meta charset="UTF-8">'
           . '<meta name="viewport" content="width=device-width, initial-scale=1">'
           . '<title>' . $esc($title) . '</title><style>'
           /* لا عرضَ ثابتًا ولا حشوةَ خمسينَ بكسلًا: يعمل من 320px صعودًا */
           . ':root{--dp-bg:#f5f5f5;--dp-card:#fff;--dp-ink:#1f2937;--dp-mut:#6b7280;'
           . '--dp-red:#dc2626;--dp-line:#e5e7eb;--dp-btn:#e8b800;--dp-btnink:#0c1c3e}'
           . '@media (prefers-color-scheme:dark){:root{--dp-bg:#0f172a;--dp-card:#1e293b;'
           . '--dp-ink:#e2e8f0;--dp-mut:#94a3b8;--dp-line:#334155}}'
           . '*{box-sizing:border-box}'
           . 'body{margin:0;font-family:Cairo,Tahoma,Arial,sans-serif;background:var(--dp-bg);'
           . 'color:var(--dp-ink);display:flex;align-items:center;justify-content:center;'
           . 'min-height:100vh;padding:clamp(12px,4vw,48px)}'
           . '.dp{background:var(--dp-card);width:100%;max-width:560px;border-radius:12px;'
           . 'padding:clamp(18px,5vw,40px);box-shadow:0 2px 14px rgba(0,0,0,.10);text-align:center}'
           . '.dp h1{color:var(--dp-red);font-size:clamp(1.15rem,4.4vw,1.6rem);margin:0 0 .5rem}'
           . '.dp p{margin:.5rem 0;line-height:1.7;overflow-wrap:anywhere}'
           . '.dp-code{display:inline-block;font-family:ui-monospace,Consolas,monospace;'
           . 'background:var(--dp-bg);border:1px solid var(--dp-line);border-radius:6px;'
           . 'padding:.2rem .55rem;font-size:.85rem;letter-spacing:.02em;direction:ltr}'
           . '.dp-meta{margin-top:1rem;padding-top:.85rem;border-top:1px solid var(--dp-line);'
           . 'color:var(--dp-mut);font-size:.82rem}'
           . '.dp-path{font-family:ui-monospace,Consolas,monospace;direction:ltr;unicode-bidi:isolate;'
           . 'display:inline-block;overflow-wrap:anywhere}'
           . '.dp-exit{display:inline-block;margin-top:1.1rem;background:var(--dp-btn);'
           . 'color:var(--dp-btnink);text-decoration:none;padding:.6rem 1.4rem;border-radius:6px;font-weight:700}'
           . '</style></head><body><div class="dp" role="alert">'
           . '<h1>⛔ ' . $esc($title) . '</h1>'
           . '<p>' . $esc($reason) . '</p>'
           . ($hint !== '' ? '<p style="color:var(--dp-mut);font-size:.9rem">' . $esc($hint) . '</p>' : '')
           . '<p><span class="dp-code">' . $esc($code) . '</span></p>'
           . ($exitUrl !== '' ? '<a class="dp-exit" href="' . $esc($exitUrl) . '">' . $esc($exitLbl) . '</a>' : '')
           . '<div class="dp-meta">المسارُ المطلوب: <span class="dp-path">' . $esc($path) . '</span></div>'
           . '</div></body></html>';
    }
}
