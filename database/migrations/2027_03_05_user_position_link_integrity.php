<?php
/**
 * 2027_03_05 — وصلةُ المستخدمِ بمنصبِه: 49 معرِّفًا من **جدولٍ آخر**
 * ═══════════════════════════════════════════════════════════════════════════
 * **المقيسُ بالأرقام**:
 *   · القارئُ `ems_user_effective_role()` (`includes/positions.php:47`) يصل
 *     `positions p ON p.id = u.position_id` ويقرأ منه `role_id`.
 *   · والكاتبُ `tools/identity_bridge_build.php:117` يكتب في `users.position_id`
 *     **معرِّفَ `person_positions.p_id`** — جدولٌ آخرُ لا `role_id` فيه أصلًا.
 *   · فـ**49 مستخدمًا** يحملون معرِّفاتٍ في المدى **124→172** (مدى
 *     `person_positions`)، و`positions.id` مداه **314→333**.
 *
 * **وأُصحِّح مبالغةً في تشخيصٍ وصلني**: قيل «قنبلةُ صلاحياتٍ كامنة — يومَ يحمل
 * `positions` معرِّفًا في المدى 124-172 يُمنح صاحبُه دورَ صفٍّ أجنبيّ». والمقيسُ
 * أن `positions.id` **أعلى من المدى سلفًا وينمو صعودًا** — فلا تقاطعَ اليومَ
 * ولا غدًا. **صفرُ مستخدمٍ** يحلُّ إلى منصبٍ أجنبيّ، وكلُّ الـ49 يرتدُّون إلى
 * `users.role` بأمان.
 *
 * **فالعطبُ الحقيقيُّ أبسطُ وأصدق**: الوصلةُ **ميتة** — بُنيت ولم تعمل. وهذا
 * نقضٌ لِما يشترطه `tests/position_bridge_test.php` نصًّا: «جميعُ المستخدمين
 * الحقيقيين بلا منصب = سلوكُهم القائم بلا أيِّ تغيير» — أي أن الجسرَ **مبنيٌّ
 * غيرُ مُتبنًّى بقرار** (قاعدةُ MD-05: البناءُ ليس تبنّيًا).
 *
 * **العلاجُ يُغلق البابَ لا العَرَض**:
 *   ① تُصفَّر الوصلاتُ التي **لا تحلُّ** إلى منصبٍ حيٍّ — فهي مراجعُ خاطئةٌ لا
 *     «تبنٍّ مؤجَّل»، وتصفيرُها يُعيد الحالةَ المعلَنةَ في الفاحص.
 *   ② ويُنصَّب **مفتاحٌ أجنبيٌّ** `users.position_id → positions.id` — فيصير
 *     كتابةُ معرِّفٍ من جدولٍ آخرَ **مستحيلةً بنيويًّا**، لا مكشوفةً بفاحصٍ لاحق.
 *
 * وأمّا **أيُّ الجدولين يملك «منصبَ المستخدم»** فسؤالٌ معماريٌّ يُترك لقرارِ
 * المالك — ولا يُحسَم في هجرة.
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
$linked   = (int) $one('SELECT COUNT(*) FROM users WHERE position_id IS NOT NULL AND position_id > 0');
$resolves = (int) $one('SELECT COUNT(*) FROM users u JOIN positions p ON p.id = u.position_id
                         WHERE u.position_id > 0');
$dangling = (int) $one('SELECT COUNT(*) FROM users u LEFT JOIN positions p ON p.id = u.position_id
                         WHERE u.position_id > 0 AND p.id IS NULL');
echo "── ① وصلاتٌ مكتوبة: {$linked} · تحلُّ إلى منصبٍ حيّ: {$resolves} · **لا تحلُّ**: {$dangling}\n";
echo '     مدى users.position_id: ' . (string) $one('SELECT CONCAT(MIN(position_id)," → ",MAX(position_id))
                                                      FROM users WHERE position_id > 0') . "\n";
echo '     مدى positions.id: ' . (string) $one('SELECT CONCAT(MIN(id)," → ",MAX(id)) FROM positions') . "\n";
echo '     مدى person_positions.p_id: ' . (string) $one('SELECT CONCAT(MIN(p_id)," → ",MAX(p_id)) FROM person_positions') . "\n";

/* ── ② تصفيرُ ما لا يحلُّ — مرجعٌ خاطئٌ لا تبنٍّ مؤجَّل ────────────────────── */
if ($dangling > 0) {
    $ok = $db->query('UPDATE users u
                        LEFT JOIN positions p ON p.id = u.position_id
                         SET u.position_id = NULL
                       WHERE u.position_id > 0 AND p.id IS NULL');
    if (!$ok) { $fail[] = 'التصفير: ' . $db->error; }
    echo '── ② صُفِّرت ' . ($ok ? $db->affected_rows : 0) . " وصلةً لا تحلُّ (الجسرُ يعود مبنيًّا غيرَ مُتبنًّى)\n";
} else {
    echo "── ② لا وصلةَ معلَّقةً — لا تصفير\n";
}

/* ── ③ المفتاحُ الأجنبيُّ يجعل الخطأَ مستحيلًا ───────────────────────────── */
$hasFk = (int) $one("SELECT COUNT(*) FROM information_schema.key_column_usage
                      WHERE table_schema = DATABASE() AND table_name = 'users'
                        AND column_name = 'position_id' AND referenced_table_name IS NOT NULL");
if ($hasFk === 0) {
    $left = (int) $one('SELECT COUNT(*) FROM users u LEFT JOIN positions p ON p.id = u.position_id
                         WHERE u.position_id IS NOT NULL AND p.id IS NULL');
    if ($left > 0) {
        $fail[] = "لا يُنصَّب مفتاحٌ فوقَ {$left} مرجعٍ معلَّق";
    } else {
        $ok = $db->query('ALTER TABLE users ADD CONSTRAINT fk_users_position
                          FOREIGN KEY (position_id) REFERENCES positions (id)
                          ON DELETE SET NULL ON UPDATE CASCADE');
        if (!$ok) { $fail[] = 'fk_users_position: ' . $db->error; }
        echo '── ③ المفتاحُ fk_users_position: ' . ($ok ? "نُصِّب\n" : "تعذّر — {$db->error}\n");
    }
} else {
    echo "── ③ المفتاحُ قائمٌ سلفًا\n";
}

/* ── ④ الشاهدُ المُشغَّلُ والمسبارُ السالب ──────────────────────────────── */
echo "── ④ الشاهدُ المُشغَّل\n";
$after = (int) $one('SELECT COUNT(*) FROM users u LEFT JOIN positions p ON p.id = u.position_id
                      WHERE u.position_id IS NOT NULL AND p.id IS NULL');
echo "     وصلاتٌ لا تحلُّ بعد: {$after} " . ($after === 0 ? "✔\n" : "✘\n");
if ($after !== 0) { $fail[] = "بقيت {$after} وصلةً معلَّقة"; }

// المسبارُ السالب: معرِّفٌ من `person_positions` لا يُقبل بعد اليوم
$alien = (int) $one('SELECT p_id FROM person_positions
                      WHERE p_id NOT IN (SELECT id FROM positions) ORDER BY p_id LIMIT 1');
if ($alien > 0) {
    $uid = (int) $one('SELECT id FROM users WHERE position_id IS NULL ORDER BY id LIMIT 1');
    if ($uid > 0) {
        $probe = @$db->query("UPDATE users SET position_id = {$alien} WHERE id = {$uid}");
        echo "     معرِّفٌ من person_positions (#{$alien}) على مستخدمٍ: "
           . ($probe === false ? "مردودٌ ✔ (المفتاحُ يمنع)\n" : "مقبولٌ ✘ — لا حارس\n");
        if ($probe !== false) {
            $db->query("UPDATE users SET position_id = NULL WHERE id = {$uid}");
            $fail[] = 'معرِّفٌ من جدولٍ آخرَ مقبولٌ — المفتاحُ لا يحرس';
        }
    }
}
// والمسموحُ يمرُّ: منصبٌ حقيقيٌّ يُقبل ثم يُعاد
$realPos = (int) $one('SELECT id FROM positions WHERE COALESCE(is_deleted,0) = 0 ORDER BY id LIMIT 1');
$uid2 = (int) $one('SELECT id FROM users WHERE position_id IS NULL ORDER BY id LIMIT 1');
if ($realPos > 0 && $uid2 > 0) {
    $okReal = @$db->query("UPDATE users SET position_id = {$realPos} WHERE id = {$uid2}");
    echo "     ومنصبٌ حقيقيٌّ (#{$realPos}): " . ($okReal ? "مقبولٌ ✔ (المفتاحُ يمنع الغريبَ لا الصحيح)\n" : "مردودٌ ✘\n");
    if (!$okReal) { $fail[] = 'المفتاحُ يمنع منصبًا حقيقيًّا'; }
    $db->query("UPDATE users SET position_id = NULL WHERE id = {$uid2}");
}

$stillLinked = (int) $one('SELECT COUNT(*) FROM users WHERE position_id IS NOT NULL');
echo "     مستخدمون بمنصبٍ الآن: {$stillLinked} (الجسرُ مبنيٌّ غيرُ مُتبنًّى — كما ينصُّ الفاحص)\n";

echo "\n" . (empty($fail)
    ? "✅ لا وصلةَ إلى جدولٍ آخر: المفتاحُ الأجنبيُّ يجعلها مستحيلةً، والجسرُ عاد إلى حالتِه المعلَنة.\n"
    : "⚠ " . implode(' · ', $fail) . "\n");
exit(empty($fail) ? 0 : 1);
