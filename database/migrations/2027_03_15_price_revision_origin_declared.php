<?php
/**
 * 2027_03_15 — منشأُ مراجعةِ السعرِ يُصرَّحُ ولا يُستنتَجُ من صفرٍ
 * ═══════════════════════════════════════════════════════════════════════════
 * **الثغرةُ المقيسة** (أبلغَ عنها فحصٌ متوازٍ ثم تُحقِّقت بالقراءةِ سطرًا سطرًا):
 *   · `Contracts/cron_price_adjustment.php:49` يمرّر `actor = 0`.
 *   · و`PriceAdjustmentService::applyDue():420` يكتب
 *     `'created_by' => (int) $actor ?: null` ⇒ **NULL**.
 *   · وحارسُ الفصلِ في `approve():455` نصُّه
 *     `if ((int) $rev['created_by'] > 0 && … === $actor)`.
 *   ⇒ فعلى صفوفِ الكرونِ يكون الشرطُ الأولُ كاذبًا **دائمًا**، فحارسُ «من أنشأ
 *     لا يعتمد — الفصلُ بنيويٌّ لا اختياري» لا يشتعل أبدًا. حارسٌ قائمٌ نصًّا
 *     وغائبٌ فعلًا.
 *
 * **ولماذا لا يكفي منعُ الصفر**: لأنَّ `created_by = NULL` تعني اليومَ «آلةٌ»
 * **بالحادثِ لا بالتصريح** — فجلسةٌ مكسورةٌ تصل الشاشةَ بـ`uid = 0` تُنتج ذاتَ
 * الصمتِ تمامًا، ولا يُفرَّق أثرُها عن أثرِ الكرون. فالعلاجُ أن يُعلَن المنشأُ
 * عمودًا: النُّلُّ يصير «آليٌّ **مُصرَّحٌ**» لا «مجهولٌ سكتنا عنه».
 *
 * والجدولُ فارغٌ (صفرُ صفٍّ — قِيس)، فلا صفَّ موروثًا يُخمَّن منشأُه؛ والافتراضُ
 * `'user'` هو الأحوطُ لأنه **يُشغّل** الحارسَ لا يُعطّله.
 *
 * ◆ والقيدان في القاعدةِ لا في الخدمةِ وحدَها — فلا يُلتَفُّ عليهما بإدراجٍ خام.
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
mb_internal_encoding('UTF-8');
mysqli_report(MYSQLI_REPORT_OFF);
require_once dirname(__DIR__, 2) . '/includes/env.php';

$db = @new mysqli(ems_env('DB_HOST'), ems_env('DB_MIGRATOR_USER'),
                  ems_env('DB_MIGRATOR_PASS'), ems_env('DB_NAME'));
if ($db->connect_errno) { fwrite(STDERR, 'اتصال: ' . $db->connect_error . "\n"); exit(1); }
$db->set_charset('utf8mb4');
$T = 'contract_price_revisions';

/* ── ① العمودُ المُصرِّحُ بالمنشأ ────────────────────────────────────────────── */
$cols = array();
$r = $db->query("SHOW COLUMNS FROM {$T}");
while ($r && ($x = $r->fetch_assoc())) { $cols[$x['Field']] = true; }
if (isset($cols['created_origin'])) {
    echo "── ① created_origin موجودٌ سلفًا — لا تغيير\n";
} else {
    $ok = $db->query("ALTER TABLE {$T}
        ADD COLUMN created_origin ENUM('user','system') NOT NULL DEFAULT 'user'
            COMMENT 'منشأُ الصفِّ مُصرَّحًا: user=إنسانٌ بمعرِّفٍ موجب · system=كرونٌ بلا إنسان'
        AFTER created_by");
    if ($ok === false) { fwrite(STDERR, '① فشل: ' . $db->error . "\n"); exit(1); }
    echo "── ① أُضيف created_origin ENUM(user,system) DEFAULT user\n";
}

/* ── ② قيدان يمنعان عودةَ الالتباسِ من أيِّ باب ─────────────────────────────── */
$hasChk = function ($name) use ($db) {
    $r = $db->query("SELECT 1 FROM information_schema.CHECK_CONSTRAINTS
                      WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_NAME = '{$name}'");
    return $r && $r->num_rows > 0;
};
if ($hasChk('chk_price_rev_origin_actor')) {
    echo "── ② قيدُ المنشإِ موجودٌ سلفًا\n";
} else {
    $ok = $db->query("ALTER TABLE {$T} ADD CONSTRAINT chk_price_rev_origin_actor CHECK (
           (created_origin = 'user'   AND created_by IS NOT NULL AND created_by > 0)
        OR (created_origin = 'system' AND created_by IS NULL))");
    if ($ok === false) { fwrite(STDERR, '② فشل: ' . $db->error . "\n"); exit(1); }
    echo "── ② أُضيف chk_price_rev_origin_actor — لا «إنسانٌ مجهولٌ» بعد اليوم\n";
}
if ($hasChk('chk_price_rev_approver_known')) {
    echo "── ③ قيدُ المعتمِدِ موجودٌ سلفًا\n";
} else {
    $ok = $db->query("ALTER TABLE {$T} ADD CONSTRAINT chk_price_rev_approver_known CHECK (
           approved_at IS NULL OR (approved_by IS NOT NULL AND approved_by > 0))");
    if ($ok === false) { fwrite(STDERR, '③ فشل: ' . $db->error . "\n"); exit(1); }
    echo "── ③ أُضيف chk_price_rev_approver_known — لا «اعتُمد» بلا مَن اعتمد\n";
}

/* ── ④ جسٌّ: أيُّ قيدٍ لا يردُّ عند إفسادِ مفحوصِه زخرفةٌ ────────────────────── */
$probe = function ($label, $sql, $wantRefused, $errnoOk = null) use ($db, $T) {
    $db->query($sql);
    $refused = ($db->errno !== 0);
    $errno = $db->errno;
    $db->query("DELETE FROM {$T} WHERE period_key LIKE 'MIGPRB%'");
    $verdict = ($refused === $wantRefused);
    /* ◆ ولا يكفي «رُدَّ»: يُشترط أن يردَّه **قيدي** لا مفتاحٌ أجنبيٌّ عارض */
    if ($verdict && $refused && $errnoOk !== null && !$errnoOk($errno)) {
        $verdict = false;
        $label .= ' — رُدَّ لكن بغيرِ قيدي';
    }
    echo '── ④ ' . $label . ': ' . ($refused ? "مردودٌ (خطأ {$errno})" : 'مرَّ')
       . ' ' . ($verdict ? '✔' : '✘ — خلافُ المطلوب') . "\n";
    return $verdict;
};
/* ◆ **بندٌ حقيقيٌّ لا رقمٌ مخترَع**: `fk_price_revision_term` يردُّ `term_id`
     غيرَ الموجودِ بخطإِ 1452 — فيُقرأ بندٌ قائمٌ من الجدول. وأوّلُ جسٍّ لي رُدَّ
     بذاك المفتاحِ لا بقيدي، فقرأتُ «القيدُ لا يميّز» وهو يميّز؛ **رمزُ الخطإِ
     هو الذي يفرِّق**، فلا يُقنَع بـ«رُدَّ» مجرَّدًا. */
$term = $db->query("SELECT t.id, t.contract_id, t.contract_item_id
                      FROM contract_price_terms t LIMIT 1");
$term = $term ? $term->fetch_assoc() : null;
$all = true;
if ($term === null) {
    echo "── ④ لا بندَ تسعيرٍ قائمًا — فروعُ الجسِّ الموجبةُ لا تُشغَّل، وهذا يُعلَن لا يُخفى\n";
} else {
    $tid = (int) $term['id']; $cid = (int) $term['contract_id']; $iid = (int) $term['contract_item_id'];
    echo "── ④ الجسُّ على بندٍ حقيقيٍّ #{$tid} (عقدٌ {$cid} · بندٌ {$iid})\n";
    $base = "INSERT INTO {$T} (company_id,term_id,contract_id,contract_item_id,period_key,
             as_of_date,effective_from,outcome,created_by,created_origin,created_at) VALUES ";
    /* ◆ ويُشترط رمزُ خطإِ القيدِ (4025 في MariaDB · 3819 في MySQL) لا مجرَّدُ الردّ */
    $chk = function ($errno) { return $errno === 4025 || $errno === 3819; };
    $all = $probe('«إنسانٌ بلا معرِّفٍ» يُردُّ بالقيد',
        $base . "(4,{$tid},{$cid},{$iid},'MIGPRB1','2026-01-01','2026-01-01','amended',NULL,'user',NOW())",
        true, $chk) && $all;
    $all = $probe('«آلةٌ بمعرِّفِ إنسانٍ» تُردُّ بالقيد',
        $base . "(4,{$tid},{$cid},{$iid},'MIGPRB2','2026-01-01','2026-01-01','amended',7,'system',NOW())",
        true, $chk) && $all;
    $all = $probe('«آلةٌ بلا معرِّفٍ» تمرُّ',
        $base . "(4,{$tid},{$cid},{$iid},'MIGPRB3','2026-01-01','2026-01-01','amended',NULL,'system',NOW())",
        false, null) && $all;
    $all = $probe('«اعتُمد بلا معتمِدٍ» يُردُّ بالقيد',
        "INSERT INTO {$T} (company_id,term_id,contract_id,contract_item_id,period_key,as_of_date,
         effective_from,outcome,created_by,created_origin,approved_at,approved_by,created_at)
         VALUES (4,{$tid},{$cid},{$iid},'MIGPRB4','2026-01-01','2026-01-01','amended',NULL,'system',NOW(),NULL,NOW())",
        true, $chk) && $all;
}

$left = $db->query("SELECT COUNT(*) n FROM {$T} WHERE period_key LIKE 'MIGPRB%'");
$left = $left ? (int) $left->fetch_row()[0] : -1;
echo "── ⑤ باقٍ من الجسّ: {$left}\n";
if (!$all || $left !== 0) { fwrite(STDERR, "القيودُ لا تميّز أو بقي أثرُ جسٍّ — لم يكتمل\n"); exit(1); }

echo "\n✅ المنشأُ صار مُصرَّحًا لا مُستنتَجًا من صفرٍ — فحارسُ «من أنشأ لا يعتمد» يقدر أن يميّز.\n";
exit(0);
