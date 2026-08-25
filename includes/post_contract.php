<?php
/**
 * includes/post_contract.php — العقدُ السبعيُّ لمعالجاتِ POST (CS-03)
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ الحكم (FIXA-0004 · FIXC-0072): «كلُّ معالجٍ يتحقق: طريقةً ← رمزَ حمايةٍ ←
 *   فعلًا مسجَّلًا ← صلاحيةً ← مفتاحَ منعِ تكرارٍ ← مدخلاتٍ ← ثم يفتح المعاملة ·
 *   **والخروجُ عند أيِّ فشلٍ قبلَ أيِّ كتابة**».
 *
 * ◆ ولماذا موضعٌ واحد (CS-05): «فالحكمُ في موضعٍ واحدٍ يُختبر مرةً واحدة».
 *   نسخُ البنودِ السبعةِ في كلِّ شاشةٍ يعني سبعَ فرصٍ للنسيانِ في كلِّ واحدة —
 *   وهو بالضبط سببُ العطلِ RF-02: أربعُ شاشاتٍ كتبت قبلَ أن تسأل.
 *
 * الاستعمال في أعلى ملفِّ السطح — **قبلَ أيِّ استعلامِ كتابة**:
 *
 *   require_once __DIR__ . '/../includes/post_contract.php';
 *   $post = ems_post_contract($conn, [
 *       'action'   => 'proc.stock.count_adjust',   // رمزٌ مسجَّلٌ في القاموس
 *       'perm'     => 'can_edit',                  // الصلاحيةُ المطلوبة
 *       'trigger'  => 'adjust_item',               // مفتاحُ الحقلِ الذي يُطلق المعالج
 *       'idem'     => ['item' => $item, 'wh' => $wh, 'qty' => $qty],
 *       'validate' => function (array $in) { ... return ['ok'=>true,'data'=>[...]]; },
 *   ]);
 *   if ($post['run']) { ... الكتابةُ داخلَ معاملةٍ ... }
 *
 * القيمةُ المُرجَعة:
 *   run     bool   أيُنفَّذ الأثر؟ (false إن لم يكن POST أو لم يُطلَق المعالج)
 *   ok      bool   أنجحت البنودُ السبعة؟
 *   msg     string رسالةٌ للمستخدمِ عند الرفضِ أو التكرار
 *   data    array  المدخلاتُ بعدَ التحقق
 *   idem    string مفتاحُ منعِ التكرارِ المحسوب
 *   replay  bool   أهذا تكرارٌ لطلبٍ نُفِّذ سلفًا؟
 */

if (!function_exists('ems_post_contract')) {

    /** ② رمزُ الحمايةِ — يُرفض الطلبُ بلا رمزٍ صالحٍ (لا رصدَ بل حجب). */
    function ems_pc_csrf_ok()
    {
        $token = isset($_POST['csrf_token']) ? (string) $_POST['csrf_token']
               : (isset($_SERVER['HTTP_X_CSRF_TOKEN']) ? (string) $_SERVER['HTTP_X_CSRF_TOKEN'] : '');
        if (!function_exists('verify_csrf_token')) { return false; } // غيابُ الحارسِ منعٌ
        return (bool) verify_csrf_token($token);
    }

    /**
     * ③ الفعلُ مسجَّلٌ في القاموسِ بصنفِ كتابةٍ معلَن — وغيرُ المسجَّلِ يُحجب.
     *
     * ◆ گوتشا التقطها الفحصُ الحيُّ قبلَ النشر: مفتاحُ القاموسِ عمودُه
     *   ‎canonical_code‎ لا ‎action_code‎. والاستعلامُ بعمودٍ غيرِ موجودٍ يُرجع
     *   ‎prepare‎ كاذبًا فيُقرأ «فعلٌ غيرُ مسجَّل» ⇒ **كلُّ** معالجٍ يُرفض. أي أن
     *   خطأً في اسمِ عمودٍ كان سيشلُّ ستةَ معالجاتٍ شللًا صامتًا يبدو «تشديدَ
     *   حراسة». ولذلك يُميَّز هنا بين «القاموسُ لا يعرف الرمز» (منعٌ صحيح)
     *   و«تعذّر سؤالُ القاموس» (خللٌ يُسجَّل ويُمنع أيضًا — لكن برمزٍ مختلف).
     *
     * @return bool|null true مسجَّل · false غيرُ مسجَّل · null تعذّر السؤال
     */
    function ems_pc_action_registered(mysqli $conn, $code)
    {
        $code = trim((string) $code);
        if ($code === '') { return false; }
        $st = $conn->prepare("SELECT 1 FROM nav09_action_map WHERE canonical_code = ? LIMIT 1");
        if (!$st) {
            error_log('EMS post_contract: تعذر سؤال قاموس الأفعال — ' . $conn->error);
            return null;
        }
        $st->bind_param('s', $code);
        $st->execute();
        $ok = (bool) $st->get_result()->fetch_row();
        $st->close();
        return $ok;
    }

    /** ④ الصلاحيةُ على شاشةِ الطلبِ نفسِها — تُقرأ من الحارسِ المركزيِّ لا محليًّا. */
    function ems_pc_permission_ok(mysqli $conn, $perm)
    {
        if (!function_exists('get_current_page_permissions')) { return false; }
        $p = get_current_page_permissions($conn);
        $perm = in_array($perm, array('can_view', 'can_add', 'can_edit', 'can_delete'), true) ? $perm : 'can_edit';
        return !empty($p[$perm]);
    }

    /** ⑤ مفتاحُ منعِ التكرارِ من **محتوى الطلبِ لا من وقتِه** (CS-07). */
    function ems_pc_idem_key($actionCode, array $parts)
    {
        $actor = isset($_SESSION['user']['id']) ? (int) $_SESSION['user']['id'] : 0;
        ksort($parts);
        $flat = array();
        foreach ($parts as $k => $v) { $flat[] = $k . '=' . (is_scalar($v) ? (string) $v : json_encode($v)); }
        return sha1($actor . '|' . $actionCode . '|' . implode('|', $flat));
    }

    /**
     * أسُجِّل هذا المفتاحُ سلفًا؟ يُستعمل جدولُ العملياتِ المعالَجةِ المركزيّ.
     * ◆ گوتشا موثَّقة: العطالةُ لا تعمل إلا بإطفاءِ الإبلاغِ الرامي — وإلا صار
     *   الخطأ 1062 استثناءً فضاع (FIXA-0008-د). لذا نُغلقه حولَ الإدراجِ وحدَه.
     */
    function ems_pc_idem_seen(mysqli $conn, $key)
    {
        $st = $conn->prepare("SELECT 1 FROM ems_post_idempotency WHERE idem_key = ? LIMIT 1");
        if (!$st) { return false; }
        $st->bind_param('s', $key);
        $st->execute();
        $seen = (bool) $st->get_result()->fetch_row();
        $st->close();
        return $seen;
    }

    /** يُثبِّت المفتاحَ بعدَ نجاحِ الأثر — خارجَ المعاملةِ ليصمد لو ارتدّت. */
    function ems_pc_idem_mark(mysqli $conn, $key, $actionCode, $result = '')
    {
        // ◆ گوتشا مرصودةٌ في الاختبار: العمودُ ‎CHAR(40)‎ و‎INSERT IGNORE‎ **يبتلع
        //   البترَ صامتًا** — فمفتاحٌ أطولُ يُكتب مبتورًا ثم لا يجده البحثُ بالمفتاحِ
        //   الكامل، فتنكسر العطالةُ بلا أيِّ خطأٍ ظاهر. الطولُ يُتحقق منه صراحةً.
        if (strlen((string) $key) !== 40) {
            error_log('EMS post_contract: مفتاح عطالة بطول غير 40 — يرفض بدل أن يبتر: ' . strlen((string) $key));
            return false;
        }
        $prev = mysqli_report(MYSQLI_REPORT_OFF);
        $st = $conn->prepare("INSERT IGNORE INTO ems_post_idempotency
                                (idem_key, action_code, actor_user_id, result_ref, created_at)
                              VALUES (?,?,?,?,NOW())");
        if ($st) {
            $actor = isset($_SESSION['user']['id']) ? (int) $_SESSION['user']['id'] : 0;
            $res = mb_substr((string) $result, 0, 190);
            $st->bind_param('ssis', $key, $actionCode, $actor, $res);
            $st->execute();
            $st->close();
        }
        mysqli_report($prev);
    }

    /**
     * العقدُ السبعيُّ كاملًا. يُنادى **قبلَ أيِّ كتابة** — وإخفاقُ أيِّ بندٍ
     * يُرجع ‎run=false‎ فلا تُنفَّذ الكتابةُ أصلًا.
     */
    function ems_post_contract(mysqli $conn, array $spec)
    {
        $out = array('run' => false, 'ok' => false, 'msg' => '', 'data' => array(),
                     'idem' => '', 'replay' => false, 'code' => '');

        // ① الطريقة
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { return $out; }

        // مُطلِقُ المعالج: حقلٌ بعينِه — فالملفُّ قد يحمل أكثرَ من معالج
        $trigger = isset($spec['trigger']) ? (string) $spec['trigger'] : '';
        if ($trigger !== '' && !isset($_POST[$trigger])) { return $out; }

        $out['run'] = true;
        $action = isset($spec['action']) ? (string) $spec['action'] : '';
        $out['code'] = $action;

        // ② رمزُ الحماية
        if (!ems_pc_csrf_ok()) {
            $out['msg'] = 'رمز الحماية غير صالح — حدث الصفحة وأعد المحاولة (CSRF-403)';
            return $out;
        }

        // ③ الفعلُ مسجَّل — والفشلُ مغلقٌ في الحالتين، والرمزُ يميّز السبب.
        $registered = ems_pc_action_registered($conn, $action);
        if ($registered !== true) {
            $out['msg'] = ($registered === null)
                ? 'تعذر سؤال قاموس الأفعال — الفعل محجوب حتى يتاح القاموس (ACT-500)'
                : 'فعل غير مسجل في قاموس الأفعال — محجوب (ACT-403)';
            if (function_exists('log_security_event')) {
                log_security_event($registered === null ? 'ACTION_MAP_UNAVAILABLE' : 'UNREGISTERED_ACTION_DENY',
                    'code=' . $action . ' path=' . ($_SERVER['SCRIPT_NAME'] ?? '') . ' role='
                    . (isset($_SESSION['user']['role']) ? (int) $_SESSION['user']['role'] : 0));
            }
            return $out;
        }

        // ④ الصلاحية
        $perm = isset($spec['perm']) ? (string) $spec['perm'] : 'can_edit';
        if (!ems_pc_permission_ok($conn, $perm)) {
            $out['msg'] = 'لا تملك صلاحية تنفيذ هذا الفعل (GOV-PERM-403)';
            return $out;
        }

        // ⑤ منعُ التكرار
        $key = ems_pc_idem_key($action, isset($spec['idem']) && is_array($spec['idem']) ? $spec['idem'] : array());
        $out['idem'] = $key;
        if (ems_pc_idem_seen($conn, $key)) {
            $out['replay'] = true;
            $out['ok'] = true;   // ◆ التكرارُ ليس خطأً: يُرجع مرجعَ الأثرِ الأولِ ولا يولّد ثانيًا
            $out['msg'] = 'نفذ هذا الطلب سلفا — لم يكرر الأثر';
            $out['run'] = false;
            return $out;
        }

        // ⑥ المدخلات
        if (isset($spec['validate']) && is_callable($spec['validate'])) {
            $v = call_user_func($spec['validate'], $_POST);
            if (empty($v['ok'])) {
                $out['msg'] = (string) ($v['msg'] ?? 'مدخلات غير صالحة (422)');
                return $out;
            }
            $out['data'] = isset($v['data']) && is_array($v['data']) ? $v['data'] : array();
        }

        // ⑦ الإذنُ بفتحِ المعاملة — المناداةُ تفتحها بنفسها
        $out['ok'] = true;
        return $out;
    }
}
