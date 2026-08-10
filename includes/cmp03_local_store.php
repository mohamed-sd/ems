<?php
require_once __DIR__ . '/../includes/catch_log.php';
/**
 * includes/cmp03_local_store.php — محوّل شاشات CMP-03 إلى جداولها الأصلية (الموجة ٢)
 * ───────────────────────────────────────────────────────────────────────────
 * كانت الشاشات المولَّدة تكتب/تقرأ المخزن البيني cmp03_screen_rows (JSON بلا
 * فهارس ولا قيود). بعد هجرة 2026_11_14_cmp03_wave2_tables صار لكل شاشة جدولها
 * scr_* بأعمدة دلالية — وهاتان الدالتان تحفظان شكل الشاشات المولَّد نفسه
 * (payload تسمية←قيمة) فلا يعاد توليد الواجهات:
 *   cmp03_store_insert(): الحمولة ← أعمدة الجدول (الفارغ NULL — NULLIF).
 *   cmp03_store_rows():   الصفوف بشكل المخزن القديم (id·payload·status·…).
 * fail-closed: شاشة خارج السجل ترفض الكتابة (لا سقوط صامت للمخزن البيني).
 */

require_once __DIR__ . '/cmp03_registry.php';

if (!function_exists('cmp03_store_norm')) {
    /** تطبيع التسمية — مرآة مولّد السجل (فراغات + نزع التشكيل والتطويل) */
    function cmp03_store_norm($s)
    {
        $s = preg_replace('/\s+/u', ' ', trim((string) $s));
        return preg_replace('/[\x{064B}-\x{0652}\x{0640}]/u', '', $s);
    }
}

/* ═══════════════════════════════════════════════════════════════════════════
 * INJ-0219 · CS-11-ب — «لا مستندَ يُولد معتمدًا»
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ العطلُ المقيسُ عددًا: `cmp03_store_update` يمنع **تعديلَ** المعتمد (CS-11)،
 *   فبقي بابٌ خلفيٌّ واحدٌ لم يُغلَق: **الميلادُ معتمدًا**. ومنتقي «الحالة» في
 *   381 سطحًا من 404 يعرض «معتمد» خيارًا عند الإدراج — فالمنشئُ يختار لنفسِه
 *   حالةَ من مرّ بالسلّم، وأعمدةُ المعتمِدين نصوصٌ يكتبها هو.
 * ◆ والمنعُ في هذه الدالةِ لا في الشاشات: هي القناةُ الوحيدةُ لكتابةِ جداولِ
 *   الشاشات (404 مُنادٍ)، فإغلاقُ البابِ هنا يغلقه على الكلِّ بملفٍّ واحد.
 * ◆ ولا بابَ خلفيًّا لبيانِ العرض: هذه الدالةُ تكتب `is_seed = 0` دائمًا
 *   (سطرٌ ثابتٌ أدناه)، والبذرُ قناتُه غيرُها — فالاستثناءُ **بنيويٌّ لا خياريّ**.
 * ═══════════════════════════════════════════════════════════════════════════ */

if (!function_exists('cmp03_terminal_statuses')) {
    /** الحالاتُ التي لا تُنال إلا بسلّمٍ مكتمل — مصدرٌ واحدٌ يقرؤه المانعُ والفاحص. */
    function cmp03_terminal_statuses()
    {
        return array('معتمد');
    }
}

if (!function_exists('cmp03_status_is_terminal')) {
    /** أتطلب هذه الحالةُ سلّمًا؟ — تطبيعٌ خفيفٌ فلا يفلت «مُعتمد» بتشكيلٍ. */
    function cmp03_status_is_terminal($status)
    {
        $s = cmp03_store_norm((string) $status);
        foreach (cmp03_terminal_statuses() as $t) {
            if ($s !== '' && mb_strpos($s, cmp03_store_norm($t)) !== false) { return true; }
        }
        return false;
    }
}

if (!function_exists('cmp03_store_notice')) {
    /**
     * بلاغُ آخرِ كتابةٍ — ليست الدالةُ تُرجع bool وحسب فتُبتلع الحقيقة.
     * تُقرأ بعد `cmp03_store_insert` مباشرةً؛ '' يعني لا شيءَ يُعلَن.
     * (لا حالةَ عالمية تتجاوز الطلب: الوعاءُ ساكنٌ داخلَ الطلبِ الواحد.)
     */
    function cmp03_store_notice($set = null)
    {
        static $notice = '';
        if ($set !== null) { $notice = (string) $set; }
        return $notice;
    }
}

if (!function_exists('cmp03_store_insert')) {
    /**
     * حفظ صف شاشةٍ في جدولها الأصلي. الحمولة (تسمية←قيمة) تُسقط على الأعمدة
     * عبر خريطة السجل؛ تسمية خارج الخريطة تُتجاهل معلَنة في سجل الأخطاء
     * (لا تلفيق عمود). يعيد true/false.
     *
     * ◆ INJ-0219: حالةٌ نهائيةٌ عند الإدراجِ **تُحطُّ إلى «مسودة»** ويُعلَن ذلك في
     *   `cmp03_store_notice()` — حطٌّ لا رفضٌ، لأن رفضَ الحفظِ يُفقد المستخدمَ ما
     *   كتب في 381 سطحًا بلا ذنبٍ منه؛ والحطُّ يحفظ العملَ ويمنع الأثرَ الكاذب.
     */
    function cmp03_store_insert(mysqli $conn, $companyId, $canonical, array $payload, $status, $uid, $creatorName)
    {
        cmp03_store_notice('');
        $reg = cmp03_registry();
        if (!isset($reg[$canonical])) {
            error_log("cmp03_local_store: شاشة خارج السجل — {$canonical}");
            return false;
        }

        if (cmp03_status_is_terminal($status)) {
            error_log("cmp03_local_store: حالةٌ نهائيةٌ عند الإدراجِ حُطّت — {$canonical} «{$status}» (INJ-0219)");
            cmp03_store_notice('الحالةُ «' . $status . '» لا تُختار عند الإنشاء — حُفظ الصفُّ «مسودة».'
                . ' الاعتمادُ يمرُّ بسلّمِ الموافقاتِ بيدين مختلفتين، لا بمنتقٍ في يدِ المنشئ.');
            $status = 'مسودة';
            /* والحمولةُ نفسُها تحمل نسخةً من الحالةِ في عمودِ العرض — تُحَطُّ معها
               وإلا أعلن الجدولُ «معتمد» في عمودٍ والحقيقةُ «مسودة» في آخر. */
            foreach (array_keys($payload) as $lbl) {
                if (cmp03_store_norm($lbl) === cmp03_store_norm('الحالة')) { $payload[$lbl] = 'مسودة'; }
            }
        }
        $table = $reg[$canonical]['table'];
        $map   = $reg[$canonical]['map'];
        $cols = array('company_id', 'status', 'is_seed', 'created_by', 'created_by_name');
        $vals = array(intval($companyId), (string) $status, 0, intval($uid), (string) $creatorName);
        $types = 'isiis';
        foreach ($payload as $label => $v) {
            $n = cmp03_store_norm($label);
            if (!isset($map[$n])) {
                error_log("cmp03_local_store: تسمية بلا عمود في {$canonical} — «{$label}»");
                continue;
            }
            $v = trim((string) $v);
            if ($v === '') { continue; } // NULLIF — الفارغ يبقى NULL
            $cols[] = $map[$n];
            $vals[] = $v;
            $types .= 's';
        }
        $sql = "INSERT INTO `{$table}` (`" . implode('`, `', $cols) . "`)
                VALUES (" . implode(', ', array_fill(0, count($cols), '?')) . ")";
        $st = $conn->prepare($sql);
        if (!$st) { error_log("cmp03_local_store: prepare فشل — " . $conn->error); return false; }
        $st->bind_param($types, ...$vals);
        $ok = $st->execute();
        if (!$ok) { error_log("cmp03_local_store: execute فشل — " . $st->error); }
        $st->close();
        return (bool) $ok;
    }
}

if (!function_exists('cmp03_stage_insert')) {
    /**
     * حفظُ صفٍّ في **المخزنِ البينيِّ** `cmp03_screen_rows` — لا في جدولِ
     * الشاشةِ الأصليِّ (ذاك شأنُ `cmp03_store_insert`). والفرقُ جوهريّ: البينيُّ
     * يقبل حمولةً حرةً بتسمياتٍ عربية، والأصليُّ يشترط خريطةَ أعمدةٍ مسجَّلة.
     *
     * ◆ استُخرجت من `Suppliers/supplier_bank.php` امتثالًا لـCS-05 «لا عبارةَ
     *   كتابةٍ في ملفِّ سطح» (AC-F6). سطحٌ واحدٌ كان يكتبها، والباقي أدوات —
     *   فالموضعُ الطبيعيُّ لها مخزنُ CMP-03 لا الشاشة.
     *
     * @return bool
     */
    function cmp03_stage_insert(mysqli $conn, $companyId, $canonical, array $payload, $status, $uid, $creatorName)
    {
        $st = $conn->prepare(
            'INSERT INTO cmp03_screen_rows
                (company_id, canonical_file, payload, status, is_seed, created_by, created_by_name)
             VALUES (?, ?, ?, ?, 0, ?, ?)'
        );
        if (!$st) { error_log('cmp03_stage_insert: prepare فشل — ' . $conn->error); return false; }
        $cid  = (int) $companyId;
        $can  = (string) $canonical;
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
        $stt  = (string) $status;
        $u    = (int) $uid;
        $who  = (string) $creatorName;
        $st->bind_param('isssis', $cid, $can, $json, $stt, $u, $who);
        $ok = $st->execute();
        if (!$ok) { error_log('cmp03_stage_insert: execute فشل — ' . $st->error); }
        $st->close();
        return (bool) $ok;
    }
}

if (!function_exists('cmp03_store_rows')) {
    /**
     * قراءة صفوف شاشةٍ من جدولها الأصلي بشكل المخزن البيني القديم:
     * [id, payload(تسمية←قيمة), status, created_by_name, created_at, is_seed].
     * $companyId <= 0 (سوبر بلا شركة) يقرأ الكل.
     */
    function cmp03_store_rows(mysqli $conn, $canonical, $companyId, $limit = 500)
    {
        $reg = cmp03_registry();
        if (!isset($reg[$canonical])) { return array(); }
        $table = $reg[$canonical]['table'];
        // الحمولة تُعاد بالتسمية الأصلية (بتشكيلها) — فمفاتيح cmp03_cell في
        // الشاشات هي تسميات $FIELDS الحرفية لا المطبَّعة
        $rev = $reg[$canonical]['labels'];
        $co = intval($companyId);
        $lim = max(1, intval($limit));
        $sql = "SELECT * FROM `{$table}`" . ($co > 0 ? " WHERE company_id = {$co}" : '')
             . " ORDER BY id DESC LIMIT {$lim}";
        $out = array();
        $r = mysqli_query($conn, $sql);
        while ($r && ($x = mysqli_fetch_assoc($r))) {
            $payload = array();
            foreach ($rev as $col => $normLabel) {
                if (isset($x[$col]) && $x[$col] !== null && $x[$col] !== '') {
                    $payload[$normLabel] = $x[$col];
                }
            }
            $out[] = array(
                'id' => intval($x['id']),
                'payload' => $payload,
                'status' => $x['status'],
                'created_by_name' => $x['created_by_name'],
                'created_at' => $x['created_at'],
                'is_seed' => intval($x['is_seed']),
            );
        }
        return $out;
    }
}

/* ═══════════════════════════════════════════════════════════════════════════
 * FIX-03 · FN-04 — «طبقةُ التخزينِ المولَّدةِ بدالتين لا أربع»
 * ═══════════════════════════════════════════════════════════════════════════
 * كانت الطبقةُ تنفّذ **الإدراجَ والقراءةَ فقط**: لا تحديثَ ولا عكس. والوثيقةُ
 * تشترط لكلِّ فعلٍ عقدًا يشمل التصحيحَ والعكس — «ولا مستندَ يُنشأ بلا مسارٍ
 * لتصحيحه، فالمستندُ المسجونُ عيبٌ بنيويّ».
 *
 * ◆ وثلاثةُ أحكامٍ تحكم ما أُضيف:
 *   ① **لا دالةَ حذفٍ إطلاقًا** (CS-08): العكسُ حركةٌ جديدةٌ بمرجعِها لا محو.
 *   ② التحديثُ يسجّل **قبلَ وبعد** من طبقةٍ مشتركة (CS-09) لا من الشاشة.
 *   ③ والعكسُ يُنشئ صفًّا جديدًا يشير إلى الأصلِ ويُعلن حالتَه «معكوس» —
 *      والأصلُ يبقى كما هو، فالسجلُّ لا يُمحى.
 * ═══════════════════════════════════════════════════════════════════════════ */

if (!function_exists('cmp03_store_row')) {
    /** صفٌّ واحدٌ بمعرّفه — أساسُ «قبلَ» في سجلِّ التدقيق. */
    function cmp03_store_row(mysqli $conn, $canonical, $companyId, $id)
    {
        $reg = cmp03_registry();
        if (!isset($reg[$canonical])) { return null; }
        $table = $reg[$canonical]['table'];
        $st = $conn->prepare("SELECT * FROM `{$table}` WHERE id = ?" . (intval($companyId) > 0 ? ' AND company_id = ?' : '') . ' LIMIT 1');
        if (!$st) { error_log('cmp03_store_row: prepare — ' . $conn->error); return null; }
        $id = intval($id);
        if (intval($companyId) > 0) { $co = intval($companyId); $st->bind_param('ii', $id, $co); }
        else { $st->bind_param('i', $id); }
        $st->execute();
        $row = $st->get_result()->fetch_assoc();
        $st->close();
        return $row ?: null;
    }
}

if (!function_exists('cmp03_store_update')) {
    /**
     * تصحيحُ صفٍّ قائمٍ بمرجعِ الأصلِ وسجلِّ «قبلَ وبعد».
     * ◆ المعتمدُ لا رجعيةَ فيه (CS-11): صفٌّ حالتُه «معتمد» يُرفض تصحيحُه —
     *   والتصحيحُ عندئذٍ **عكسٌ ثم إنشاءٌ جديد** لا تعديلٌ في مكانه.
     *
     * @return array{ok:bool,msg:string,changed:array}
     */
    function cmp03_store_update(mysqli $conn, $companyId, $canonical, $id, array $payload, $uid, $actorName)
    {
        $reg = cmp03_registry();
        if (!isset($reg[$canonical])) {
            error_log("cmp03_store_update: شاشة خارج السجل — {$canonical}");
            return array('ok' => false, 'msg' => 'شاشةٌ خارجَ السجل — لا تصحيحَ (409)', 'changed' => array());
        }
        $before = cmp03_store_row($conn, $canonical, $companyId, $id);
        if ($before === null) { return array('ok' => false, 'msg' => 'صفٌّ غيرُ موجود (404)', 'changed' => array()); }
        if (isset($before['status']) && mb_strpos((string) $before['status'], 'معتمد') !== false) {
            return array('ok' => false, 'changed' => array(),
                'msg' => 'مستندٌ معتمدٌ لا رجعيةَ فيه — صحّحْ بحركةٍ عاكسةٍ ثم إنشاءٍ جديد (CS-11 · 409)');
        }

        $table = $reg[$canonical]['table'];
        $map   = $reg[$canonical]['map'];
        $sets = array(); $vals = array(); $types = ''; $changed = array();
        foreach ($payload as $label => $v) {
            $n = cmp03_store_norm($label);
            if (!isset($map[$n])) { error_log("cmp03_store_update: تسمية بلا عمود — «{$label}»"); continue; }
            $col = $map[$n];
            $new = trim((string) $v);
            $old = isset($before[$col]) ? (string) $before[$col] : '';
            if ($new === $old) { continue; }
            $sets[] = "`{$col}` = ?";
            $vals[] = ($new === '' ? null : $new);
            $types .= 's';
            $changed[$col] = array('before' => $old, 'after' => $new);
        }
        if (!$sets) { return array('ok' => true, 'msg' => 'لا تغييرَ — لم يُكتب شيء', 'changed' => array()); }

        $sets[] = '`updated_at` = NOW()';
        $sql = "UPDATE `{$table}` SET " . implode(', ', $sets) . ' WHERE id = ?';
        $vals[] = intval($id); $types .= 'i';
        if (intval($companyId) > 0) { $sql .= ' AND company_id = ?'; $vals[] = intval($companyId); $types .= 'i'; }

        $st = $conn->prepare($sql);
        if (!$st) { error_log('cmp03_store_update: prepare — ' . $conn->error); return array('ok' => false, 'msg' => 'تعذّر التصحيح (500)', 'changed' => array()); }
        $st->bind_param($types, ...$vals);
        $ok = $st->execute();
        if (!$ok) { error_log('cmp03_store_update: execute — ' . $st->error); }
        $st->close();
        if (!$ok) { return array('ok' => false, 'msg' => 'تعذّر التصحيح (500)', 'changed' => array()); }

        cmp03_store_audit($conn, $companyId, $canonical, $table, $id, 'update', $changed, $uid, $actorName);
        return array('ok' => true, 'msg' => 'صُحِّح الصفُّ #' . intval($id) . ' — ' . count($changed) . ' حقلًا بسجلِّ قبلَ وبعد', 'changed' => $changed);
    }
}

if (!function_exists('cmp03_store_reverse')) {
    /**
     * العكسُ حركةٌ جديدةٌ بمرجعِها — **ولا دالةَ حذفٍ في هذه الطبقة إطلاقًا**.
     * يُنشئ صفًّا جديدًا بحمولةِ الأصلِ وحالةِ «عكس» ومرجعِ الأصلِ في التسمية،
     * ويَسِمُ الأصلَ «معكوس» — فالأصلُ باقٍ والسلسلةُ مقروءة.
     *
     * @return array{ok:bool,msg:string,reversal_id:int}
     */
    function cmp03_store_reverse(mysqli $conn, $companyId, $canonical, $id, $reason, $uid, $actorName)
    {
        if (trim((string) $reason) === '') {
            return array('ok' => false, 'msg' => 'سببُ العكس إلزامي (422)', 'reversal_id' => 0);
        }
        $reg = cmp03_registry();
        if (!isset($reg[$canonical])) { return array('ok' => false, 'msg' => 'شاشةٌ خارجَ السجل (409)', 'reversal_id' => 0); }
        $before = cmp03_store_row($conn, $canonical, $companyId, $id);
        if ($before === null) { return array('ok' => false, 'msg' => 'صفٌّ غيرُ موجود (404)', 'reversal_id' => 0); }
        if (isset($before['status']) && mb_strpos((string) $before['status'], 'معكوس') !== false) {
            return array('ok' => false, 'msg' => 'الصفُّ معكوسٌ سلفًا — لا عكسَ للعكس (409)', 'reversal_id' => 0);
        }

        $table = $reg[$canonical]['table'];
        $labels = $reg[$canonical]['labels'];
        $payload = array();
        foreach ($labels as $col => $normLabel) {
            if (isset($before[$col]) && $before[$col] !== null && $before[$col] !== '') { $payload[$normLabel] = $before[$col]; }
        }

        $conn->begin_transaction();
        try {
            $okIns = cmp03_store_insert($conn, $companyId, $canonical, $payload,
                'عكس — مرجع #' . intval($id), $uid, $actorName);
            if (!$okIns) { throw new RuntimeException('تعذّر إنشاءُ الحركةِ العاكسة'); }
            $revId = (int) $conn->insert_id;

            $st = $conn->prepare("UPDATE `{$table}` SET `status` = ?, `updated_at` = NOW() WHERE id = ?");
            if (!$st) { throw new RuntimeException('prepare: ' . $conn->error); }
            $newStatus = 'معكوس — بالحركة #' . $revId;
            $idI = intval($id);
            $st->bind_param('si', $newStatus, $idI);
            if (!$st->execute()) { throw new RuntimeException('mark reversed: ' . $st->error); }
            $st->close();

            $conn->commit();
            cmp03_store_audit($conn, $companyId, $canonical, $table, $id, 'reverse',
                array('status' => array('before' => (string) ($before['status'] ?? ''), 'after' => $newStatus),
                      'reason' => array('before' => '', 'after' => (string) $reason)),
                $uid, $actorName);
            return array('ok' => true, 'reversal_id' => $revId,
                'msg' => 'عُكس الصفُّ #' . $idI . ' بحركةٍ عاكسةٍ #' . $revId . ' — والأصلُ باقٍ');
        } catch (\Throwable $e) {
            $conn->rollback();
            error_log('cmp03_store_reverse: ' . $e->getMessage());
            return array('ok' => false, 'msg' => 'تعذّر العكس — لم يُكتب شيء (ERR-CMP-1049)', 'reversal_id' => 0);
        }
    }
}

if (!function_exists('cmp03_store_audit')) {
    /**
     * CS-09 — «التدقيقُ قبلَ وبعدَ من طبقةٍ مشتركةٍ لا من الشاشة · ولا اعتمادَ
     * على قادحٍ وحدَه، فالقادحُ لا يعرف الفاعلَ ولا رمزَ الفعل».
     */
    function cmp03_store_audit(mysqli $conn, $companyId, $canonical, $table, $pk, $action, array $changed, $uid, $actorName)
    {
        $prev = mysqli_report(MYSQLI_REPORT_OFF);
        try {
            $st = $conn->prepare("INSERT INTO activity_logs
                    (company_id, user_id, role_id, role_name, screen_name, module_name,
                     action_type, field_name, record_id, old_value, new_value, created_at)
                  VALUES (?,?,?,?,?,?,?,?,?,?,?,NOW())");
            if (!$st) { throw new RuntimeException($conn->error); }
            $co     = intval($companyId);
            $user   = intval($uid);
            $roleId = isset($_SESSION['user']['role']) ? intval($_SESSION['user']['role']) : 0;
            $roleNm = (string) $actorName;
            $screen = (string) $canonical;
            $module = (string) $table;
            $act    = (string) $action;
            $fields = implode(',', array_keys($changed));
            $rec    = intval($pk);
            $old    = json_encode(array_map(static function ($c) { return $c['before']; }, $changed), JSON_UNESCAPED_UNICODE);
            $new    = json_encode(array_map(static function ($c) { return $c['after']; }, $changed), JSON_UNESCAPED_UNICODE);
            // الأنواعُ بترتيبِ الأعمدةِ حرفًا بحرف:
            // company_id(i) user_id(i) role_id(i) role_name(s) screen_name(s)
            // module_name(s) action_type(s) field_name(s) record_id(i) old(s) new(s)
            $st->bind_param('iiisssssiss',
                $co, $user, $roleId, $roleNm, $screen, $module, $act, $fields, $rec, $old, $new);
            $st->execute();
            $st->close();
        } catch (\Throwable $e) { ems_catch_ignored($e, __METHOD__, 'CS-12: لا يُبتلع — يُسجَّل ولا يُوقف الأثرَ (السجلُّ تابعٌ لا شرط).');
            // CS-12: لا يُبتلع — يُسجَّل ولا يُوقف الأثرَ (السجلُّ تابعٌ لا شرط).
            error_log('cmp03_store_audit failed: ' . $e->getMessage());
        }
        mysqli_report($prev);
    }
}
