<?php
/**
 * 2027_03_30_security_log_screen.php
 * ═══════════════════════════════════════════════════════════════════════════
 * تسجيلُ شاشةِ «سجل الأمان» (Governance/security_log.php) وربطُها بسايدبارِ
 * الدور ١٥ «إدارة الصلاحيات».
 *
 * ◆ **التسجيلُ حراسةٌ لا توثيق**: `check_page_permissions()` تردُّ منعًا كاملًا
 *   لما لا صفَّ له في `modules` — وقبلَ قرارِ المالك 2026-08-05 كانت تفتح كلَّ
 *   الصلاحياتِ لغيرِ المسجَّل (fail-open). فبلا هذا الصفِّ لا شاشةَ أصلًا.
 *   (و`modules` لا تحمل عمودَ `module_path` — الربطُ بـ`code`.)
 *
 * ◆ **لماذا المجموعةُ 3845 «المحاولات الممنوعة» لا 3844 «سجل التدقيق والاطّلاع»**
 *   قِيس محتوى كلٍّ منهما:
 *     · 3844 فيها `ActivityLogs/activity_logs.php` — سجلُّ الأفعالِ **الناجحة**
 *       من جدولِ `activity_logs`: مَن فعل ماذا ونجح. سؤالٌ آخر.
 *     · 3845 فيها `Governance/guard_denials.php` — ما **رُفض**، من جدولَي
 *       `guard_denials` و`action_execution_log`.
 *   وشاشتُنا تجيب سؤالَ 3845 نفسَه (ما رُفض وما أُنذر منه) من **مصدرٍ آخرَ
 *   لا يبلغه الأول**: ملفُّ `logs/security.log` الذي يكتب فيه حارسُ الكيانِ
 *   وحارسُ CSRF وفاحصُ ثوابتِ الأدوارِ وحاجبُ DDL — ولا يصل شيءٌ منها إلى
 *   `guard_denials`. فوضعُهما متجاورَين يجعل مَن يحقّق في منعٍ يرى نصفَيه معًا،
 *   بينما وضعُها في 3844 يفصلها عن نصفِها الآخر. ولا مجموعةَ جديدةً تُنشأ.
 *
 * ◆ **عيبُ FN-02**: `includes/unified_nav.php` يرشّح بـ
 *     `n.permission_code IS NULL OR EXISTS(... p.module_id = n.module_id ...)`
 *   فصفٌّ برمزِ صلاحيةٍ غيرِ فارغٍ و`module_id` فارغةٍ **يسقط صامتًا** — لا
 *   يظهر ولا يشتكي. فالحقلان يُملآن معًا حتمًا، والهجرةُ ترسب إن لم يُملآ.
 *
 * ◆ قراءةٌ فقط: المنحُ `can_view` وحدَه — صفرُ إضافةٍ وتعديلٍ وحذف.
 * ◆ قابلةٌ للإعادة (idempotent) بالفحصِ قبلَ كلِّ إدراج.
 *
 * بعدها إلزامًا: php database/migrate.php dump-schema
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
$ROOT = dirname(dirname(__DIR__));
require_once $ROOT . '/includes/env.php';
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
mysqli_report(MYSQLI_REPORT_OFF);
$u = ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_USER') : ems_env('DB_USER');
$p = ems_env('DB_MIGRATOR_PASS') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS');
$conn = new mysqli($host, $u, $p, ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit("تعذّر الاتصال: {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');

echo "══ تسجيلُ «سجل الأمان» وربطُها بالدور ١٥ ══\n\n";

$FILE     = 'Governance/security_log.php';
$LABEL    = 'سجل الأمان';
$ICON     = 'fa fa-shield-halved';
$ROLE     = 15;               // إدارة الصلاحيات
$GROUP    = 3845;             // «المحاولات الممنوعة» — التسبيبُ في الترويسة
$DOOR     = 'DAILY';
$SORT     = 2;                // بعد «المحاولات الممنوعة» (sort_order = 1)

/* ── ① الملفُّ موجودٌ فعلًا — لا صفَّ لشاشةٍ لا ملفَّ لها ─────────────────── */
if (!is_file($ROOT . '/' . $FILE)) {
    fwrite(STDERR, "✘ الملفُّ غيرُ موجود: {$FILE}\n");
    exit(2);
}

/* ── ② المجموعةُ قائمةٌ وفعّالةٌ وللدورِ نفسِه ─────────────────────────────── */
$st = $conn->prepare('SELECT name, owner_role_id, is_active FROM link_groups WHERE id = ? LIMIT 1');
$st->bind_param('i', $GROUP);
$st->execute();
$grp = $st->get_result()->fetch_assoc();
$st->close();
if (!$grp) { fwrite(STDERR, "✘ المجموعة {$GROUP} غيرُ موجودة — ولا تُنشأ مجموعةٌ جديدة\n"); exit(2); }
if ((int) $grp['owner_role_id'] !== $ROLE || (int) $grp['is_active'] !== 1) {
    fwrite(STDERR, "✘ المجموعة {$GROUP} ليست للدور {$ROLE} أو غيرُ فعّالة\n");
    exit(2);
}
echo "  المجموعة #{$GROUP}: «{$grp['name']}» — للدور {$ROLE} وفعّالة ✔\n";

/* ── ③ صفُّ الموديول ─────────────────────────────────────────────────────── */
$mid = null;
$st = $conn->prepare('SELECT id FROM modules WHERE code = ? LIMIT 1');
$st->bind_param('s', $FILE);
$st->execute();
if ($row = $st->get_result()->fetch_assoc()) { $mid = (int) $row['id']; }
$st->close();

if ($mid === null) {
    $ord = 247;   // بجوارِ «المحاولات الممنوعة» (246)
    $st = $conn->prepare('INSERT INTO modules (name, code, owner_role_id, is_link, is_quick, icon, display_order)
                          VALUES (?, ?, ?, 1, 0, ?, ?)');
    $st->bind_param('ssisi', $LABEL, $FILE, $ROLE, $ICON, $ord);
    if (!$st->execute()) { fwrite(STDERR, "✘ تعذّر تسجيلُ الموديول: " . $st->error . "\n"); exit(2); }
    $mid = (int) $conn->insert_id;
    $st->close();
    echo "  الموديول: أُنشئ #{$mid}\n";
} else {
    echo "  الموديول: قائمٌ سلفًا #{$mid}\n";
}
if ($mid <= 0) { fwrite(STDERR, "✘ معرِّفُ موديولٍ غيرُ صالح\n"); exit(2); }

/* ── ④ المنح — عرضٌ فقط (الشاشةُ قراءةٌ محضة) ────────────────────────────── */
$st = $conn->prepare('SELECT id, can_view FROM role_permissions WHERE role_id = ? AND module_id = ? LIMIT 1');
$st->bind_param('ii', $ROLE, $mid);
$st->execute();
$gr = $st->get_result()->fetch_assoc();
$st->close();
if (!$gr) {
    $st = $conn->prepare('INSERT INTO role_permissions (role_id, module_id, can_view, can_add, can_edit, can_delete)
                          VALUES (?, ?, 1, 0, 0, 0)');
    $st->bind_param('ii', $ROLE, $mid);
    if (!$st->execute()) { fwrite(STDERR, "✘ تعذّر المنح: " . $st->error . "\n"); exit(2); }
    $st->close();
    echo "  المنح: can_view للدور {$ROLE} — أُضيف\n";
} else {
    echo "  المنح: قائمٌ سلفًا (can_view={$gr['can_view']})\n";
}

/* ── ⑤ صفُّ السايدبار — والحقلان معًا حتمًا (عيب FN-02) ──────────────────── */
$st = $conn->prepare('SELECT id, module_id, permission_code, active FROM nav_items
                       WHERE role_id = ? AND route = ? LIMIT 1');
$st->bind_param('is', $ROLE, $FILE);
$st->execute();
$nav = $st->get_result()->fetch_assoc();
$st->close();

if (!$nav) {
    $st = $conn->prepare('INSERT INTO nav_items (role_id, door, group_id, module_id, label_ar, route,
                                                 icon, sort_order, permission_code, active)
                          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)');
    $st->bind_param('isiisssis', $ROLE, $DOOR, $GROUP, $mid, $LABEL, $FILE, $ICON, $SORT, $FILE);
    if (!$st->execute()) { fwrite(STDERR, "✘ تعذّر ربطُ السايدبار: " . $st->error . "\n"); exit(2); }
    $st->close();
    echo "  السايدبار: صفٌّ جديدٌ في المجموعة {$GROUP}\n";
} else {
    /* إصلاحُ صفٍّ قائمٍ ناقصِ الوحدةِ — وإلا سقط صامتًا (FN-02) */
    $st = $conn->prepare('UPDATE nav_items SET module_id = ?, permission_code = ?, group_id = ?,
                                 door = ?, label_ar = ?, icon = ?, active = 1
                           WHERE id = ?');
    $st->bind_param('isisssi', $mid, $FILE, $GROUP, $DOOR, $LABEL, $ICON, $nav['id']);
    $st->execute();
    $st->close();
    echo "  السايدبار: صفٌّ قائمٌ #{$nav['id']} — ضُبطت وحدتُه ورمزُه\n";
}

/* ── ⑥ التحقُّقُ الذاتي: ما بُني يُقاس، ولا يُدَّعى ───────────────────────── */
echo "\n── التحقُّق\n";
$fails = 0;

/* أ · الصفُّ يعبر مرشِّحَ `unified_nav` نفسَه حرفيًّا (لا استعلامًا مبسَّطًا) */
$sql = "SELECT n.id, n.label_ar, n.route, n.sort_order, g.name AS group_name
          FROM nav_items n
          LEFT JOIN link_groups g ON g.id = n.group_id AND g.is_active = 1
         WHERE n.role_id = {$ROLE} AND n.active = 1 AND n.route = '" . $conn->real_escape_string($FILE) . "'
           AND (n.permission_code IS NULL
                OR EXISTS (SELECT 1 FROM role_permissions p
                            WHERE p.module_id = n.module_id AND p.role_id = n.role_id AND p.can_view = 1))";
$r = $conn->query($sql);
$row = $r ? $r->fetch_assoc() : null;
if (!$row) {
    echo "  ✘ الصفُّ لا يعبر مرشِّحَ السايدبار — يسقط صامتًا (FN-02)\n"; $fails++;
} else {
    echo "  ✔ يعبر مرشِّحَ السايدبار: «{$row['label_ar']}» في مجموعة «{$row['group_name']}» (ترتيب {$row['sort_order']})\n";
}

/* ب · المجموعةُ صارت تحمل الشاشتين معًا */
$r = $conn->query("SELECT route FROM nav_items WHERE role_id = {$ROLE} AND group_id = {$GROUP} AND active = 1 ORDER BY sort_order");
$inGroup = array();
while ($r && ($x = $r->fetch_row())) { $inGroup[] = $x[0]; }
echo "  ✔ المجموعة {$GROUP} تحوي: " . implode(' · ', $inGroup) . "\n";

/* ج · الشاشةُ تُحَلُّ إلى موديولٍ بمنحِ عرضٍ للدور ١٥ */
$r = $conn->query("SELECT p.can_view, p.can_add, p.can_edit, p.can_delete
                     FROM modules m JOIN role_permissions p ON p.module_id = m.id
                    WHERE m.code = '" . $conn->real_escape_string($FILE) . "' AND p.role_id = {$ROLE}");
$pr = $r ? $r->fetch_assoc() : null;
if (!$pr || (int) $pr['can_view'] !== 1) { echo "  ✘ لا منحَ عرضٍ للدور {$ROLE}\n"; $fails++; }
else {
    echo "  ✔ المنح: عرض={$pr['can_view']} إضافة={$pr['can_add']} تعديل={$pr['can_edit']} حذف={$pr['can_delete']}\n";
    if ((int) $pr['can_add'] || (int) $pr['can_edit'] || (int) $pr['can_delete']) {
        echo "  ✘ الشاشةُ قراءةٌ فقط — ومنحُها يحمل كتابةً\n"; $fails++;
    }
}

/* د · ملفُّ السجلِّ يُقرأ من نهايته فعلًا (لا يُدَّعى) */
$logPath = $ROOT . '/logs/security.log';
if (!file_exists($logPath)) {
    echo "  ○ logs/security.log غيرُ موجود — الشاشةُ ستعلن «لا ملفَّ سجلٍّ بعد»\n";
} else {
    $fh = @fopen($logPath, 'rb');
    if (!$fh) { echo "  ✘ الملفُّ موجودٌ ولا يُفتح — راجعِ الأذونات\n"; $fails++; }
    else {
        $sz = filesize($logPath);
        if ($sz > 65536) { fseek($fh, -65536, SEEK_END); fgets($fh); }
        $n = 0;
        while (($ln = fgets($fh)) !== false) {
            if (preg_match('/^\[\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\]\s*\[[^\]]+\]/u', $ln)) { $n++; }
        }
        fclose($fh);
        printf("  ✔ الملفُّ يُقرأ من نهايته: %s حدثًا في آخرِ 64 كيلوبايت (الحجم %.1f ميجابايت)\n",
            $n, $sz / 1048576);
        if ($n === 0) { echo "  ✘ صفرُ حدثٍ مطابقٍ للصيغة — المُحلِّلُ لا يفهم الملف\n"; $fails++; }
    }
}

echo "\n── الحصيلة\n";
if ($fails > 0) { fwrite(STDERR, "\n✘ {$fails} فحصًا راسبًا — الهجرةُ راسبة\n"); exit(1); }
echo "  ✅ «سجل الأمان» مسجَّلةٌ ومحروسةٌ ومربوطةٌ بمجموعة «{$grp['name']}» للدور {$ROLE}.\n";
echo "  ◆ لا تنسَ: php database/migrate.php dump-schema\n";
