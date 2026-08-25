<?php
/**
 * includes/field_visibility.php — أيرى هذا المستخدمُ هذا الحقلَ الحسّاس؟
 * ═══════════════════════════════════════════════════════════════════════════
 * ⇐ INJ-0150 · INJ-0159 · INJ-0347 · INJ-0104 · INJ-0130
 *
 * **المشكلةُ المقيسة**: ستُّ شاشاتٍ يطلب نصُّ قبولِها أن يكون الحقلُ الحسّاسُ
 * **غائبًا نصًّا من استجابةِ الخادم** لمن لا منحةَ له — لا مخفيًّا بأسلوبِ عرضٍ.
 * وإخفاءٌ بـCSS ليس منعًا: القيمةُ تبقى في المصدرِ يقرؤها كلُّ من فتح «عرض
 * المصدر»، ويحملها كلُّ تصديرٍ.
 *
 * **ولا آليةَ ثانية**: القرارُ يُفوَّض إلى `VisibilityPolicyService::decide()`
 * القائمةِ (قاموسُ `portal_elements` + مفاتيحُ `visibility_keys` بنطاقاتِها
 * ومددِها). وهذا الملفُّ **واجهةٌ للشاشات** لا محرّكٌ ثانٍ: سطرٌ واحدٌ بدل
 * ستةِ أسطرٍ من التهيئةِ في كلِّ شاشة.
 *
 * ── وثلاثةُ أحكامٍ مقصودة ────────────────────────────────────────────────────
 *   ① **مغلقٌ افتراضًا**: عنصرٌ خارجَ القاموسِ لا يُرى. فحقلٌ حسّاسٌ نُسي
 *      تسجيلُه يُحجب — لا يُفتح. (الافتراضُ يحمي، لا يكشف.)
 *   ② **السوبرُ استثناءٌ معلَنٌ** — لا صامت.
 *   ③ **الاطّلاعُ المخوَّلُ يُسجَّل**: `ems_log_sensitive_read` عند السماحِ
 *      **بعده لا قبلَه** — فسطرُ اطّلاعٍ على قراءةٍ لم تقع كذبٌ في السجل.
 * ═══════════════════════════════════════════════════════════════════════════
 */

if (!function_exists('ems_may_see_field')) {
    /**
     * @param \mysqli $conn
     * @param string  $elementCode رمزُ العنصرِ في `portal_elements` (مثل `supplier.iban`)
     * @param string  $subjectRef  موضوعُ القراءةِ للسجل (مثل `supplier:12`)
     * @param string  $screen      مسارُ الشاشةِ للسجل
     * @return bool
     */
    function ems_may_see_field(\mysqli $conn, $elementCode, $subjectRef = '', $screen = '')
    {
        static $memo = array();
        $code = (string) $elementCode;
        $uid  = isset($_SESSION['user']['id']) ? (int) $_SESSION['user']['id'] : 0;
        $role = isset($_SESSION['user']['role']) ? (string) $_SESSION['user']['role'] : '';
        $co   = isset($_SESSION['user']['company_id']) ? (int) $_SESSION['user']['company_id'] : 0;

        /* ② السوبرُ يرى — استثناءٌ معلَن */
        if ($role === '-1') { $allowed = true; }
        else {
            $key = $code . '|' . $uid . '|' . $co;
            if (isset($memo[$key])) { $allowed = $memo[$key]; }
            else {
                $allowed = false;
                try {
                    $svc = '\\App\\Services\\Portal\\VisibilityPolicyService';
                    $p = dirname(__DIR__) . '/app/Services/Portal/VisibilityPolicyService.php';
                    if (is_file($p)) { require_once $p; }
                    if (class_exists($svc) && function_exists('ems_tenant_db')) {
                        $gate = ems_tenant_db();
                        $d = $svc::decide($conn, $gate, $co, $code, array('account_id' => $uid));
                        $allowed = !empty($d['visible']);
                    }
                } catch (\Throwable $e) {
                    /* ① فشلُ القرارِ = حجبٌ لا كشف */
                    error_log('field_visibility: ' . $e->getMessage());
                    $allowed = false;
                }
                $memo[$key] = $allowed;
            }
        }

        /* ③ الاطّلاعُ المخوَّلُ يُسجَّل — **بعد** السماحِ لا قبلَه.
             ◆ والكتابةُ في **جدولِ `sensitive_read_log`** لا في سجلِّ الأمنِ وحدَه:
               شاشةُ المراجعةِ `Governance/read_log.php` تقرأ الجدولَ، ونصُّ القبولِ
               يقول «يكتب صفًّا في سجل الاطّلاع». و`ems_log_sensitive_read` تكتب
               في سجلِّ الأمنِ فقط — فلو اكتفينا بها لبقيت شاشةُ المراجعةِ خاويةً
               بينما «الأثرُ موجود». (قِيس فعلًا: صفرُ صفٍّ بعد اطّلاعٍ مخوَّل.)
             ◆ وعطالةُ اليومِ الواحد: قارئٌ × عنصرٌ × موضوعٌ × يومٌ = صفٌّ واحد —
               فتصييرُ جدولٍ فيه عشرةُ صفوفٍ لا يكتب عشرةَ أسطرِ اطّلاع. */
        if ($allowed && $subjectRef !== '') {
            $memoKey = 'srl_' . md5($uid . '|' . $code . '|' . $subjectRef . '|' . date('Y-m-d'));
            if (empty($_SESSION[$memoKey])) {
                $_SESSION[$memoKey] = 1;
                try {
                    $parts = explode(':', (string) $subjectRef, 2);
                    $sType = (string) $parts[0];
                    $sId   = isset($parts[1]) ? (int) $parts[1] : 0;
                    $ip    = (string) (isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'cli');
                    $st = $conn->prepare('INSERT INTO sensitive_read_log
                            (company_id, person_id, element_code, subject_type, subject_id, ip, result, context)
                            VALUES (?, ?, ?, ?, ?, ?, \'allowed\', ?)');
                    if ($st) {
                        $ctxs = ($screen !== '') ? $screen : (string) ($_SERVER['SCRIPT_NAME'] ?? '');
                        $st->bind_param('iississ', $co, $uid, $code, $sType, $sId, $ip, $ctxs);
                        $st->execute();
                        $st->close();
                    }
                } catch (\Throwable $e) {
                    /* السجلُّ لا يقطع القراءةَ — وغيابُه يظهر في الشاهدِ فلا يصمت */
                    error_log('field_visibility log: ' . $e->getMessage());
                }
                /* ويُسجَّل في سجلِّ الأمنِ أيضًا — الطبقتانِ لا واحدة */
                $lp = __DIR__ . '/sensitive_read_log.php';
                if (is_file($lp)) {
                    require_once $lp;
                    if (function_exists('ems_log_sensitive_read')) {
                        ems_log_sensitive_read($conn, str_replace('.', '_', $code), $subjectRef,
                            $screen !== '' ? $screen : (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
                    }
                }
            }
        }
        return $allowed;
    }
}

if (!function_exists('ems_masked_or_absent')) {
    /**
     * القيمةُ إن كان يراها، وإلا **سلسلةٌ فارغةٌ** — لا القيمةُ مقنَّعةً بنجومٍ.
     * فالتقنيعُ في الخادمِ يعني أنَّ القيمةَ عبرت الشبكةَ؛ والمطلوبُ ألّا تعبر.
     *
     * @return string النصُّ الجاهزُ للطباعةِ (مهرَّبًا) أو علامةُ الحجب
     */
    function ems_masked_or_absent(\mysqli $conn, $elementCode, $value, $subjectRef = '', $screen = '')
    {
        if (ems_may_see_field($conn, $elementCode, $subjectRef, $screen)) {
            return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
        }
        return '<span class="ems-field-withheld" title="محجوب — يحتاج منحة فردية">—</span>';
    }
}
