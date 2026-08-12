<?php
/**
 * 2027_03_06 — خمسةَ عشرَ بلاغًا بلا مسارٍ: كلُّها آثارُ تحقُّقٍ يدويٍّ لا عملٌ حقيقيّ
 * ═══════════════════════════════════════════════════════════════════════════
 * **تشخيصٌ وصلني قال**: «بلاغٌ يُسجَّل من الشاشةِ يخرج بلا مسارٍ ولا مالك —
 * `Tickets/ticket_form.php` لا تفتح `ticket_workstreams` — **15 بلاغًا حيًّا**
 * بلا مكلَّفٍ ولا مهلةٍ ولا تصعيد · نقضُ TKT-14».
 *
 * **وقياسي يقول غيرَ ذلك في موضعين**:
 *   ① **الشاشةُ لا تُنشئ بلاغًا أصلًا**: كلُّ أفعالِ `ticket_form.php` مشروطةٌ
 *      بـ`$ticket` قائمٍ (تحويل · نقل · إلغاء · تحسين · تفريع · أمرُ صيانة ·
 *      تعليق) — فهي **شاشةُ تفصيلٍ وأفعالٍ** لا شاشةُ تسجيل. والتسجيلُ يمرُّ
 *      بـ`TicketRouter::open()` وهو **يفتح المسارات** (`TicketRouter.php:165`).
 *   ② **والخمسةَ عشرَ ليست بلاغاتِ عملٍ**: مُبلِّغوها «اختبار مدير البلاغات» ·
 *      «تحقق 1/2/3» · «تحقق CSRF» · «انحدار الترقيم 0/1» (عشرةٌ منها) — كلُّها
 *      من جلسةِ تحقُّقٍ يدويٍّ يومَ 2026-08-08، ونصوصُها تقول ذلك بنفسها.
 *
 * فلا عطبَ في المنتجِ هنا — بل **بقايا** تُفسد حكمًا صحيحًا:
 * `tkt_state_effect_test:161` يشترط «صفرَ بلاغٍ بلا مسار» وهو شرطٌ سليم.
 *
 * **القرار**: لا حذف — تُنقل إلى `cancelled` بسببٍ مكتوب، فتخرج من اللوحاتِ
 * الحيّةِ وتبقى شاهدةً على ما جرى. وبلاغٌ ملغًى **لا عملَ له يُوجَّه**، فلا
 * يُلفَّق له مسارٌ ولا مكلَّف.
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
mb_internal_encoding('UTF-8');
mysqli_report(MYSQLI_REPORT_OFF);
require_once dirname(__DIR__, 2) . '/includes/env.php';

$db = @new mysqli(ems_env('DB_HOST'), ems_env('DB_MIGRATOR_USER'),
                  ems_env('DB_MIGRATOR_PASS'), ems_env('DB_NAME'));
if ($db->connect_errno) { fwrite(STDERR, 'اتصال: ' . $db->connect_error . "\n"); exit(1); }
$db->set_charset('utf8mb4');

$fail = array();
$one  = function ($sql) use ($db) { $r = $db->query($sql); return $r ? $r->fetch_row()[0] : null; };

/* ── ① القياسُ قبل المسّ ─────────────────────────────────────────────────── */
$NOPATH = 'NOT EXISTS (SELECT 1 FROM ticket_workstreams w WHERE w.tk_id = t.id)';
$before = (int) $one("SELECT COUNT(*) FROM tickets t WHERE {$NOPATH}");
$total  = (int) $one('SELECT COUNT(*) FROM tickets');
echo "── ① بلاغاتٌ بلا مسار: {$before} من {$total}\n";

/* وسومُ التحقُّقِ **تُقاس من الصفوفِ نفسِها** ولا تُفترض */
$MARKS = array('اختبار', 'تحقق', 'انحدار');
$conds = array();
foreach ($MARKS as $m) {
    $e = $db->real_escape_string($m);
    $conds[] = "t.reporting_person LIKE '%{$e}%'";
    $conds[] = "t.complaint LIKE '%{$e}%'";
}
$markSql = '(' . implode(' OR ', $conds) . ')';

$verif = (int) $one("SELECT COUNT(*) FROM tickets t WHERE {$NOPATH} AND {$markSql}");
$real  = (int) $one("SELECT COUNT(*) FROM tickets t WHERE {$NOPATH} AND NOT {$markSql}");
echo "     منها بوسمِ تحقُّقٍ صريحٍ في نصِّها: {$verif} · وبلا وسم: {$real}\n";

$r = $db->query("SELECT t.id, t.ticket_no, t.reporting_person, t.stage
                   FROM tickets t WHERE {$NOPATH} ORDER BY t.id");
while ($r && ($x = $r->fetch_assoc())) {
    echo "     #{$x['id']} {$x['ticket_no']} · «" . mb_substr((string) $x['reporting_person'], 0, 22)
       . "» · {$x['stage']}\n";
}
if ($real > 0) {
    echo "     ⚠ {$real} بلاغًا بلا وسمِ تحقُّقٍ **لا يُمَسّ** — يُعلَن لقرارٍ بشريّ\n";
}
if ($verif === 0) { echo "\n✅ لا بقايا تحقُّقٍ بلا مسار — لا عمل.\n"; exit(0); }

/* ── ② النقلُ إلى «ملغى» بسببٍ مكتوب ────────────────────────────────────── */
$hasCloseBy = (int) $one("SELECT COUNT(*) FROM information_schema.columns
                           WHERE table_schema = DATABASE() AND table_name = 'tickets'
                             AND column_name = 'closed_by'");
$setClose = $hasCloseBy > 0 ? ", closed_by = 0, close_date = CURDATE(), close_time = CURTIME()" : '';
$reason = '2027_03_06: أثرُ تحقُّقٍ يدويٍّ من 2026-08-08 — لا عملَ حقيقيًّا يُوجَّه (لم يُحذف)';
$ok = $db->query("UPDATE tickets t
                     SET t.stage = 'cancelled'
                         , t.complaint = CONCAT(COALESCE(t.complaint,''), ' · ', '"
                         . $db->real_escape_string($reason) . "')
                         {$setClose}
                   WHERE {$NOPATH} AND {$markSql} AND t.stage <> 'cancelled'");
if (!$ok) { $fail[] = 'النقل: ' . $db->error; }
echo '── ② نُقل ' . ($ok ? $db->affected_rows : 0) . " بلاغًا إلى «ملغى» بسببٍ مكتوبٍ في نصِّه\n";

/* ── ③ الشاهدُ المُشغَّل ─────────────────────────────────────────────────── */
echo "── ③ الشاهدُ المُشغَّل\n";
$liveNoPath = (int) $one("SELECT COUNT(*) FROM tickets t
                           WHERE {$NOPATH} AND t.stage <> 'cancelled'");
echo "     بلاغاتٌ **غيرُ ملغاةٍ** بلا مسار: {$liveNoPath} " . ($liveNoPath === 0 ? "✔\n" : "✘\n");
if ($liveNoPath !== 0) { $fail[] = "بقي {$liveNoPath} بلاغًا حيًّا بلا مسار"; }

$stillAll = (int) $one("SELECT COUNT(*) FROM tickets t WHERE {$NOPATH}");
echo "     وبلا مسارٍ بأيِّ مرحلةٍ (الملغاةُ داخلةٌ): {$stillAll} — والملغى لا عملَ له يُوجَّه\n";

// ولا بلاغَ حيًّا فقد مسارَه بهذه الهجرة
$brokeLive = (int) $one("SELECT COUNT(*) FROM tickets t
                          WHERE t.stage NOT IN ('cancelled','closed','done')
                            AND {$NOPATH}");
echo "     بلاغٌ عاملٌ بلا مسار: {$brokeLive} " . ($brokeLive === 0 ? "✔\n" : "✘\n");
if ($brokeLive !== 0) { $fail[] = "{$brokeLive} بلاغًا عاملًا بلا مسار"; }

$r = $db->query("SELECT stage, COUNT(*) n FROM tickets GROUP BY stage ORDER BY n DESC");
echo "     مراحلُ البلاغاتِ الآن: ";
$parts = array();
while ($r && ($x = $r->fetch_assoc())) { $parts[] = $x['stage'] . '=' . $x['n']; }
echo implode(' · ', $parts) . "\n";

echo "\n" . (empty($fail)
    ? "✅ لا بلاغَ عاملًا بلا مسار — وآثارُ التحقُّقِ اليدويِّ أُلغيت بسببٍ مكتوبٍ لا حُذفت.\n"
    : "⚠ " . implode(' · ', $fail) . "\n");
exit(empty($fail) ? 0 : 1);
