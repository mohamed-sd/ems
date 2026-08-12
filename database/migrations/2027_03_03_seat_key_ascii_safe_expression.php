<?php
/**
 * 2027_03_03 — قيدُ المقعدِ الواحدِ كان يعمل **بالمقلوب**: حرفيةٌ عربيةٌ مشوَّهة
 * ═══════════════════════════════════════════════════════════════════════════
 * **المقيسُ في القاعدةِ الحيّة**: العمودُ المولَّد `seat_assignments.active_open_seat_key`
 * يقارن الدورَ بالسلسلةِ الحرفيةِ
 *     `'xD8xA7xD8xADxD8xAAxD9x8AxD8xA7xD8xB7xD9x8A'`
 * وهي **ترميزُ بايتاتِ «احتياطي» بلا بادئاتِ `\x`** — أي نصٌّ ASCII لا معنى له.
 * ومصدرُ الهجرةِ سليمٌ بايتًا (`2026_08_02_cap_w3_balance_flip_constraints.php:92`
 * فيه `'احتياطي'` صحيحةً) — فالتشويهُ وقع **عند التطبيق** لا في الملف.
 *
 * **والأثرُ عكسُ المقصود**: لا دورَ يساوي تلك السلسلةَ أبدًا، فشرطُ
 * `assignment_role <> 'xD8…'` **صادقٌ دائمًا** ⇒ استثناءُ «الاحتياطيِّ المعلَّق»
 * **لا يتحقق ولا مرةً**، فيعدُّ الفهرسُ الفريدُ `uq_sa_active_open` الاحتياطيَّ
 * كالأساسيِّ **فيمنع تسجيلَه** على حاويةٍ لها أساسيٌّ فعّال.
 * ⇒ قيدُ سعةٍ **يرفض الصحيحَ ويسمح بما لا يقصد**. (الرتبةُ 2 = «احتياطي» مقيسةً
 *   من `enum('أساسي','احتياطي','مؤقت')`.)
 *
 * **العلاجُ الدائم**: تعبيرٌ **بلا بايتٍ غيرِ ASCII** — `assignment_role + 0 <> 2` —
 * فلا يبقى في التعبيرِ حرفٌ عربيٌّ يمكن أن يُشوَّه في أيِّ تطبيقٍ قادم. ويُشترط
 * قبلَه إثباتُ أن الرتبةَ 2 هي «احتياطي» فعلًا، فإن تغيّر ترتيبُ الـENUM يومًا
 * توقّفت الهجرةُ ولم تُبدِّل معنًى صامتًا.
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

/* ── ① الرتبةُ تُثبَت قبل أن تُستعمل ─────────────────────────────────────── */
$standbyOrd = (int) $one("SELECT FIELD('احتياطي', 'أساسي', 'احتياطي', 'مؤقت')");
$typeStr = (string) $one("SELECT column_type FROM information_schema.columns
                           WHERE table_schema = DATABASE() AND table_name = 'seat_assignments'
                             AND column_name = 'assignment_role'");
echo "── ① نوعُ الدور: {$typeStr}\n";
$live = (int) $one("SELECT assignment_role + 0 FROM seat_assignments
                    WHERE assignment_role = 'احتياطي' LIMIT 1");
echo "     رتبةُ «احتياطي» المقيسةُ من صفٍّ حيّ: " . ($live ?: '—') . "\n";
if ($live !== 2) {
    fwrite(STDERR, "رتبةُ «احتياطي» ليست 2 (هي {$live}) — لا يُكتب تعبيرٌ برتبةٍ غيرِ مثبتة\n");
    exit(1);
}

/* ── ② التشويهُ يُقاس ولا يُفترض ─────────────────────────────────────────── */
$expr = (string) $one("SELECT generation_expression FROM information_schema.columns
                        WHERE table_schema = DATABASE() AND table_name = 'seat_assignments'
                          AND column_name = 'active_open_seat_key'");
$mangled = (bool) preg_match('~x[0-9A-Fa-f]{2}x[0-9A-Fa-f]{2}~', $expr);
$already = (strpos($expr, 'assignment_role') !== false && strpos($expr, '+ 0') !== false)
        || (strpos($expr, '(`assignment_role` + 0)') !== false);
echo '── ② التعبيرُ الحالي: ' . ($mangled ? '**مشوَّهٌ**' : 'بلا نمطِ تشويه')
   . ($already ? ' · وبتعبيرٍ رقميٍّ سلفًا' : '') . "\n";
/* لا خروجَ مبكرٌ يتخطّى الشاهد: إن كان التعبيرُ آمنًا سلفًا يُتخطّى **البناءُ**
   وحدَه ويبقى المسبارُ السالبُ يُشغَّل — فإعلانُ سلامةٍ بلا جسٍّ ليس شاهدًا. */
$needRebuild = ($mangled || !$already);
if (!$needRebuild) { echo "     ○ البناءُ غيرُ لازم — ويبقى الشاهدُ يُشغَّل\n"; }

/* ── ③ إعادةُ البناء: الفهرسُ يُرفع ثم العمودُ يُعدَّل ثم يُعاد الفهرس ────── */
if ($needRebuild) {
$hasIdx = (int) $one("SELECT COUNT(*) FROM information_schema.statistics
                       WHERE table_schema = DATABASE() AND table_name = 'seat_assignments'
                         AND index_name = 'uq_sa_active_open'");
if ($hasIdx > 0) {
    if (!$db->query('ALTER TABLE seat_assignments DROP INDEX uq_sa_active_open')) {
        $fail[] = 'رفعُ الفهرس: ' . $db->error;
    } else { echo "── ③ الفهرسُ uq_sa_active_open رُفع مؤقتًا\n"; }
}

$comment = 'CAP-01 §4-⑥/C4: تخصيصٌ مفتوحٌ فعّالٌ واحدٌ لكل مقعد — والاحتياطيُّ pending خارج القيد '
         . '(الرتبةُ 2 = احتياطي · تعبيرٌ ASCII لئلا تُشوَّه حرفيةٌ عربيةٌ في تطبيقٍ قادم)';
$sql = "ALTER TABLE seat_assignments MODIFY COLUMN active_open_seat_key VARCHAR(40)
        GENERATED ALWAYS AS (
            IF(state = 'active' AND date_to IS NULL
               AND (assignment_role + 0 <> 2 OR activation_state = 'active'),
               CONCAT(company_id, ':', container_id), NULL)) STORED
        COMMENT '" . $db->real_escape_string($comment) . "'";
if (!$db->query($sql)) { $fail[] = 'إعادةُ بناءِ العمود: ' . $db->error; }
else { echo "── ④ العمودُ أُعيد بناؤه بتعبيرٍ ASCII\n"; }

if (!$db->query('ALTER TABLE seat_assignments ADD UNIQUE KEY uq_sa_active_open (active_open_seat_key)')) {
    $fail[] = 'إعادةُ الفهرس: ' . $db->error;
} else { echo "── ⑤ الفهرسُ أُعيد\n"; }
}   // نهايةُ البناءِ المشروط

/* ── ⑥ الشاهدُ المُشغَّلُ والمسبارُ السالب ──────────────────────────────── */
echo "── ⑥ الشاهدُ المُشغَّل\n";
$expr2 = (string) $one("SELECT generation_expression FROM information_schema.columns
                         WHERE table_schema = DATABASE() AND table_name = 'seat_assignments'
                           AND column_name = 'active_open_seat_key'");
$stillMangled = (bool) preg_match('~x[0-9A-Fa-f]{2}x[0-9A-Fa-f]{2}~', $expr2);
$nonAscii = (bool) preg_match('~[^\x00-\x7F]~', $expr2);
echo '     نمطُ التشويه: ' . ($stillMangled ? "باقٍ ✘\n" : "زال ✔\n");
echo '     بايتٌ غيرُ ASCII في التعبير: ' . ($nonAscii ? "موجودٌ ✘\n" : "لا ✔\n");
if ($stillMangled || $nonAscii) { $fail[] = 'التعبيرُ ما زال قابلًا للتشويه'; }

// الاستثناءُ يعمل: احتياطيٌّ معلَّقٌ **يجلس** مع أساسيٍّ فعّالٍ على المقعدِ نفسِه
$row = $db->query("SELECT company_id, container_id, equipment_id FROM seat_assignments
                    WHERE state = 'active' AND date_to IS NULL
                      AND assignment_role + 0 = 1 LIMIT 1");
$base = $row ? $row->fetch_assoc() : null;
if ($base) {
    $co = (int) $base['company_id']; $cn = (int) $base['container_id'];
    $eq = (int) $base['equipment_id'];   // NOT NULL — يُقاس ولا يُفترض
    $db->query("DELETE FROM seat_assignments WHERE replace_reason = '__probe_seat_2027_03_03'");
    $okStandby = $db->query("INSERT INTO seat_assignments
        (company_id, container_id, equipment_id, assignment_role, activation_state, state, date_from, replace_reason)
        VALUES ({$co}, {$cn}, {$eq}, 'احتياطي', 'pending', 'active', CURDATE(), '__probe_seat_2027_03_03')");
    echo '     احتياطيٌّ معلَّقٌ مع أساسيٍّ فعّالٍ على المقعدِ نفسِه: '
       . ($okStandby ? "مقبولٌ ✔ (الاستثناءُ يعمل)\n" : 'مردودٌ ✘ — ' . mb_substr($db->error, 0, 70) . "\n");
    if (!$okStandby) { $fail[] = 'الاستثناءُ لا يعمل: الاحتياطيُّ المعلَّقُ مردود'; }

    $okDup = $db->query("INSERT INTO seat_assignments
        (company_id, container_id, equipment_id, assignment_role, activation_state, state, date_from, replace_reason)
        VALUES ({$co}, {$cn}, {$eq}, 'أساسي', 'active', 'active', CURDATE(), '__probe_seat_2027_03_03')");
    echo '     وأساسيٌّ ثانٍ فعّالٌ على المقعدِ نفسِه: '
       . ($okDup === false ? "مردودٌ ✔ (القيدُ يحرس)\n" : "مقبولٌ ✘ — القيدُ لا يمنع\n");
    if ($okDup !== false) { $fail[] = 'أساسيٌّ ثانٍ مقبولٌ — القيدُ لا يحرس'; }

    $db->query("DELETE FROM seat_assignments WHERE replace_reason = '__probe_seat_2027_03_03'");
    $left = (int) $one("SELECT COUNT(*) FROM seat_assignments WHERE replace_reason = '__probe_seat_2027_03_03'");
    echo "     كُنس المسبار: باقٍ {$left} " . ($left === 0 ? "✔\n" : "✘\n");
    if ($left !== 0) { $fail[] = "بقي {$left} صفَّ مسبار"; }
} else {
    echo "     ○ لا أساسيَّ فعّالٌ يُبنى عليه مسبار — يُعلَن\n";
}

echo "\n" . (empty($fail)
    ? "✅ قيدُ المقعدِ صار يحرس ما يقصد: الاحتياطيُّ المعلَّقُ خارجَه، وأساسيٌّ ثانٍ مردود.\n"
    : "⚠ " . implode(' · ', $fail) . "\n");
exit(empty($fail) ? 0 : 1);
