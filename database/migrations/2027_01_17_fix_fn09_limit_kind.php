<?php
/**
 * 2027_01_17_fix_fn09_limit_kind.php
 * ═══════════════════════════════════════════════════════════════════════════
 * FIX-03 · FN-09 — «الحدُّ المطلقُ يُوصَل والمشروطُ لا · ووصلُ المشروطِ مطلقًا
 * **يقلب الحكم**».
 *
 * ◆ الفرقُ الذي يقلب الحكمَ إن أُهمل:
 *   • **المطلق**: الفعلُ ممنوعٌ على صاحبِ الحدِّ مهما كان السياق («تنفيذُ الدفع»
 *     لرئيسِ الحسابات). ⇒ يُوصَل برمزِ الفعلِ فيمنعه الحارسُ قبلَ التنفيذ.
 *   • **المشروط**: الفعلُ مسموحٌ إلا في حالةٍ بعينِها («إغلاقُ ملاحظةِ مراجعةٍ
 *     **تخصُّه**» · «تنفيذُ التحويلِ **الذي اعتمده**» · «اعتمادُ قيدٍ **أعده
 *     بنفسه**»). ⇒ **لا يُوصَل**: يُسجَّل منفِّذُه ويُفحص شرطُه في الخدمة.
 *   ◆ فلو وُصل المشروطُ برمزِ الفعلِ لمُنع الفعلُ **دائمًا** — فيُمنع المراجعُ
 *     من إغلاقِ أيِّ ملاحظةٍ ولو بدليلٍ قبِله. وهذا قلبٌ للحكمِ لا تشديدٌ له.
 *
 * ◆ التصنيفُ بعلامةٍ لغويةٍ مُعلَنة (لا برأي): الحدُّ **مشروطٌ** إن حمل نصُّه
 *   إسنادًا نسبيًّا («الذي …» · «أعده بنفسه» · «تخصُّه» · «شارك في» · «نيابةً
 *   عن» · «بلا …» · «منفردًا» · «ما تجاوز …»). وما عداه **مطلق**.
 *   ◆ والافتراضُ عند الشكِّ **مشروطٌ** — فالمشروطُ لا يُوصَل، وعدمُ الوصلِ أسلمُ
 *     من وصلٍ يقلب الحكم (فشلٌ مغلقٌ في الاتجاهِ الصحيح).
 *
 * ◆ وقيدُ القاعدةِ يمنع عودةَ الخلط: مشروطٌ + رمزُ فعلٍ = مرفوض.
 */
if (PHP_SAPI !== 'cli') { exit(1); }
error_reporting(E_ALL & ~E_DEPRECATED);
mysqli_report(MYSQLI_REPORT_OFF);
require_once dirname(__DIR__, 2) . '/includes/env.php';

$db = new mysqli(ems_env('DB_HOST'), ems_env('DB_MIGRATOR_USER'), ems_env('DB_MIGRATOR_PASS'), ems_env('DB_NAME'));
if ($db->connect_errno) { fwrite(STDERR, 'اتصال المرحِّل فشل: ' . $db->connect_error . "\n"); exit(1); }
$db->set_charset('utf8mb4');

$one = static function ($sql) use ($db) {
    $r = $db->query($sql);
    if (!$r) { throw new RuntimeException('SQL: ' . $db->error . ' — ' . $sql); }
    $x = $r->fetch_row();
    return $x ? $x[0] : null;
};
$run = static function ($sql, $label) use ($db) {
    if (!$db->query($sql)) { throw new RuntimeException($label . ': ' . $db->error); }
    echo "[FN-09] {$label} ✔\n";
};

/* ── ① العمودُ المستقلُّ للتصنيف ────────────────────────────────────────── */
$has = (int) $one("SELECT COUNT(*) FROM information_schema.COLUMNS
                    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gov_authority_limits'
                      AND COLUMN_NAME = 'limit_kind'");
if ($has === 0) {
    $run("ALTER TABLE gov_authority_limits
            ADD COLUMN `limit_kind` ENUM('absolute','conditional') NOT NULL DEFAULT 'conditional'
                COMMENT 'FN-09 · مطلقٌ يُوصَل برمزِ فعلٍ · مشروطٌ لا يُوصَل ويُفحص شرطُه في الخدمة'
                AFTER `enforce_kind`,
            ADD COLUMN `condition_note` VARCHAR(300) NOT NULL DEFAULT ''
                COMMENT 'الشرطُ الذي يجعل الفعلَ ممنوعًا — يُفحص في الخدمةِ لا في الحارس'
                AFTER `limit_kind`",
         'عمودا التصنيفِ والشرط');
} else {
    echo "[FN-09] العمودُ موجودٌ سلفًا — يُتخطّى\n";
}

/* ── ② التصنيفُ بالعلاماتِ اللغويةِ المُعلَنة ────────────────────────────── */
$MARKERS = array(
    'الذي اعتمده', 'الذي أعدّه', 'أعده بنفسه', 'أعدّه بنفسه', 'بنفسه',
    'تخصُّه', 'تخصه', 'شارك في', 'نيابةً عن', 'نيابة عن', 'منفردًا', 'منفردا',
    'ما تجاوز', 'بلا ', 'ينفّذ عليه', 'ينفذ عليه', 'يملكه',
);

$db->begin_transaction();
try {
    $rows = array();
    $rs = $db->query("SELECT id, forbidden, action_codes FROM gov_authority_limits");
    while ($rs && ($r = $rs->fetch_assoc())) { $rows[] = $r; }

    $abs = 0; $cond = 0; $unwired = 0;
    foreach ($rows as $r) {
        $txt = (string) $r['forbidden'];
        $isConditional = false;
        $marker = '';
        foreach ($MARKERS as $m) {
            if (mb_strpos($txt, $m) !== false) { $isConditional = true; $marker = $m; break; }
        }
        $kind = $isConditional ? 'conditional' : 'absolute';
        $note = $isConditional ? ('شرطٌ نسبيٌّ في النص: «' . $marker . '» — يُفحص في الخدمةِ على السلسلةِ لا على الفعل') : '';

        $st = $db->prepare("UPDATE gov_authority_limits SET limit_kind = ?, condition_note = ? WHERE id = ?");
        if (!$st) { throw new RuntimeException('prepare: ' . $db->error); }
        $id = (int) $r['id'];
        $st->bind_param('ssi', $kind, $note, $id);
        if (!$st->execute()) { throw new RuntimeException('update: ' . $st->error); }
        $st->close();

        if ($isConditional) {
            $cond++;
            // ◆ المشروطُ لا يُوصَل — وإن كان موصولًا فُصل، فوصلُه يقلب الحكم.
            if (trim((string) $r['action_codes']) !== '') {
                if (!$db->query("UPDATE gov_authority_limits SET action_codes = '' WHERE id = " . $id)) {
                    throw new RuntimeException('unwire: ' . $db->error);
                }
                $unwired++;
                echo "[FN-09] ◆ فُصل رمزُ فعلٍ عن حدٍّ مشروط #{$id}: «" . mb_substr($txt, 0, 40) . "»\n";
            }
        } else {
            $abs++;
        }
    }
    $db->commit();
    echo "[FN-09] مطلق: {$abs} · مشروط: {$cond} · فُصل رمزُه: {$unwired}\n";
} catch (Throwable $e) {
    $db->rollback();
    throw $e;
}

/* ── ③ قيدُ القاعدة: مشروطٌ + رمزُ فعلٍ = مرفوض ─────────────────────────── */
$hasChk = (int) $one("SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
                       WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gov_authority_limits'
                         AND CONSTRAINT_NAME = 'chk_gal_conditional_unwired'");
if ($hasChk === 0) {
    $bad = (int) $one("SELECT COUNT(*) FROM gov_authority_limits
                        WHERE limit_kind = 'conditional' AND action_codes IS NOT NULL AND action_codes <> ''");
    if ($bad > 0) { throw new RuntimeException("لا يُضاف القيدُ وفي الجدولِ {$bad} حدًّا مشروطًا موصولًا"); }
    $run("ALTER TABLE gov_authority_limits ADD CONSTRAINT chk_gal_conditional_unwired CHECK (
            limit_kind <> 'conditional' OR action_codes IS NULL OR action_codes = '')",
         'قيدُ «المشروطُ لا يُوصَل»');
} else {
    echo "[FN-09] القيدُ موجودٌ سلفًا — يُتخطّى\n";
}

/* ── ④ إثباتٌ وظيفيّ: محاولةُ وصلِ مشروطٍ تُرفض من القاعدة ──────────────── */
$victim = $one("SELECT id FROM gov_authority_limits WHERE limit_kind = 'conditional' ORDER BY id LIMIT 1");
if ($victim === null) {
    echo "[FN-09] ⚠ لا حدَّ مشروطًا لجسِّ القيد — القيدُ مُضافٌ ولم يُختبر وظيفيًّا (مُعلَنٌ لا مسكوتٌ عنه)\n";
} else {
    $prev = mysqli_report(MYSQLI_REPORT_OFF);
    $db->query("UPDATE gov_authority_limits SET action_codes = 'fn09.probe' WHERE id = " . (int) $victim);
    $rejected = ($db->errno !== 0);
    if (!$rejected) { $db->query("UPDATE gov_authority_limits SET action_codes = '' WHERE id = " . (int) $victim); }
    mysqli_report($prev);
    if (!$rejected) { throw new RuntimeException('القيدُ لم يمنع وصلَ مشروطٍ — الترحيلُ يرسب صراحةً'); }
    echo "[FN-09] الإثباتُ الوظيفي: وصلُ حدٍّ مشروطٍ برمزِ فعلٍ رُفض من القاعدة ✔\n";
}
