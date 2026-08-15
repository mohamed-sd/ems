<?php
/**
 * 2027_04_07_workflow_stage_links_backfill.php
 * ═══════════════════════════════════════════════════════════════════════════
 * مجموعاتُ السايدبار تطابق مراحلَ الوثيقة — ⇐ INJ-0184 · INJ-0552 · INJ-0376
 *
 * ── العلّةُ المقيسة ────────────────────────────────────────────────────────
 * الوثيقةُ `NAV-09-current.xlsx` تُعلن لكلِّ إدارةٍ مراحلَها وشاشاتِ كلِّ مرحلة.
 * والقاعدةُ تحمل المجموعاتِ **وبعضُها خالٍ من الروابط**:
 *   · المالية (الدور ١٧): مراحلُ ٢ و٤ و٨ و٩ ينقصها **٧ روابط**.
 *   · المشتريات (الدور ١٦): مراحلُ ٢ و٥ و٦ ينقصها **٤ روابط**.
 * فالمستخدمُ يفتح مجموعةً مرحليةً فيجدها فارغةً — والمرحلةُ في الوثيقةِ لها
 * شاشاتُها. وهذا هو نصُّ INJ-0184 بعينه: «تظهر مجموعتا التحليل والمخاطر
 * **بمحتواهما**، وكلُّ رابطٍ فيهما **يفتح شاشتَه**».
 *
 * ── القاعدةُ الحاكمة ──────────────────────────────────────────────────────
 * **الوثيقةُ مصدرُ الحقيقةِ والقوائمُ تُولَّد** — فالناقصُ يُشتقُّ منها لا يُكتب
 * يدويًّا. والاسمُ القانونيُّ في الوثيقةِ (`pr.php`) يُحَلُّ إلى مسارٍ حقيقيٍّ
 * عبر `nav09_file_map`.
 *
 * ◆ **ولا يُضاف رابطٌ لملفٍّ لا وجودَ له**: الشرطُ «كلُّ رابطٍ يفتح شاشتَه» —
 *   ورابطٌ إلى ملفٍّ مفقودٍ أسوأُ من غيابِه، فيُعلَن ولا يُضاف.
 * ◆ **ولا تُنشأ مجموعةٌ جديدة**: المجموعاتُ قائمةٌ — الناقصُ روابطُها.
 * ◆ والإضافةُ عاطلة: تشغيلٌ ثانٍ لا يضاعف (يُفحص وجودُ الصفِّ بالمسارِ والدور).
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
$ROOT = dirname(dirname(__DIR__));
require_once $ROOT . '/includes/env.php';
require_once $ROOT . '/tools/nav09_read.php';
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
mysqli_report(MYSQLI_REPORT_OFF);
$u = ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_USER') : ems_env('DB_USER');
$p = ems_env('DB_MIGRATOR_PASS') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS');
$conn = new mysqli($host, $u, $p, ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit("تعذّر الاتصال: {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');

echo "══ روابطُ المراحلِ الناقصةُ من الوثيقة ══\n\n";

/* الإداراتُ المقصودةُ ودورُ كلٍّ — مشتقٌّ من البنود */
$TARGETS = array(
    array('dept' => 'المالية',    'role' => 17),
    array('dept' => 'المشتريات', 'role' => 16),
);

$doc = Nav09Reader::load($ROOT . '/docs/files/NAV-09-current.xlsx');

/* خريطةُ الاسمِ القانونيِّ ⇐ المسارِ الحقيقيّ */
$map = array();
$r = $conn->query('SELECT canonical_file, real_path, state FROM nav09_file_map');
while ($r && ($x = $r->fetch_assoc())) {
    $map[strtolower(trim((string) $x['canonical_file']))] = array(
        'path' => trim((string) $x['real_path']), 'state' => (string) $x['state']);
}

$added = 0; $skipMissing = array(); $skipHave = 0;

foreach ($TARGETS as $t) {
    $d = null;
    foreach ($doc['depts'] as $k => $v) {
        if (mb_strpos($v['name'], $t['dept']) !== false) { $d = $v; break; }
    }
    if (!$d) { echo "  ⚠ لم أجد «{$t['dept']}» في الوثيقة\n"; continue; }
    $role = (int) $t['role'];
    echo "▸ {$d['name']} — الدور {$role}\n";

    /* مجموعاتُ الدورِ بمرحلتِها */
    $groups = array();
    $q = $conn->query("SELECT id, name, stage_no FROM link_groups
                        WHERE owner_role_id = {$role} AND stage_no IS NOT NULL
                          AND stage_no BETWEEN 1 AND 90");
    while ($q && ($x = $q->fetch_assoc())) {
        $groups[(int) $x['stage_no']][] = array('id' => (int) $x['id'], 'name' => (string) $x['name']);
    }

    /* الروابطُ القائمةُ للدور — بالمسارِ المجرَّد */
    $have = array();
    $q = $conn->query("SELECT route FROM nav_items WHERE role_id = {$role} AND active = 1");
    while ($q && ($x = $q->fetch_row())) {
        $have[strtolower(basename(preg_replace('~[?#].*$~', '', (string) $x[0])))] = true;
    }

    foreach ($d['rows'] as $row) {
        if (($row['kind'] ?? '') !== 'screen') { continue; }
        $stage = (int) ($row['stage'] ?? 0);
        if ($stage < 1) { continue; }                      /* لوحةُ المساحةِ ليست مرحلة */
        $canon = strtolower(trim((string) ($row['file'] ?? '')));
        if ($canon === '' || !isset($map[$canon])) { $skipMissing[] = $canon . ' (لا خريطة)'; continue; }
        $real = $map[$canon]['path'];
        if ($real === '' || !is_file($ROOT . '/' . $real)) {
            $skipMissing[] = $canon . ' ⇒ ' . ($real !== '' ? $real : '—') . ' (لا ملف)';
            continue;
        }
        $base = strtolower(basename($real));
        if (isset($have[$base])) { $skipHave++; continue; }

        /* ══ الصفُّ قد يكون **قائمًا ومُطفأً** ═══════════════════════════════════
             هجرةُ `2027_04_06` أطفأت ٢٤٦ صفَّ تنقّلٍ فعّالٍ يحجبها الحارسُ —
             وفيها صفوفٌ **تُعلنها الوثيقةُ من مراحلِ الإدارة**. فالعلاجُ ليس
             إيقادًا أعمى: الوثيقةُ تقول إنَّ الشاشةَ من مراحلِ هذه الإدارة،
             فتُمنح **قراءةً** ثم يُوقَد الصفّ. ولا تُمنح كتابةٌ بحال — الوثيقةُ
             تُسمّي الشاشةَ ولا تُسمّي سلطةَ الكتابةِ فيها.
           ◆ ورابطٌ يُوقَد بلا منحةٍ يعود ٤٠٣ — فالمنحُ شرطُ الإيقادِ لا تابعُه. */
        $exists = 0; $isActive = 0;
        $eq = $conn->prepare('SELECT id, active FROM nav_items WHERE role_id = ? AND route = ? LIMIT 1');
        if ($eq) {
            $eq->bind_param('is', $role, $real);
            $eq->execute();
            $ex = $eq->get_result()->fetch_row();
            $eq->close();
            if ($ex) { $exists = (int) $ex[0]; $isActive = (int) $ex[1]; }
        }
        if ($exists > 0) {
            if ($isActive === 1) { $skipHave++; continue; }
            /* ① المنحةُ أولًا */
            $modId2 = 0;
            $mq2 = $conn->prepare('SELECT id FROM modules WHERE code = ? LIMIT 1');
            if ($mq2) {
                $mq2->bind_param('s', $real);
                $mq2->execute();
                $mr2 = $mq2->get_result()->fetch_row();
                $mq2->close();
                if ($mr2) { $modId2 = (int) $mr2[0]; }
            }
            if ($modId2 > 0) {
                $gq = $conn->prepare('SELECT id, can_view FROM role_permissions WHERE role_id = ? AND module_id = ? LIMIT 1');
                if ($gq) {
                    $gq->bind_param('ii', $role, $modId2);
                    $gq->execute();
                    $gr = $gq->get_result()->fetch_row();
                    $gq->close();
                    if (!$gr) {
                        $iq = $conn->prepare('INSERT INTO role_permissions (role_id, module_id, can_view, can_add, can_edit, can_delete)
                                              VALUES (?, ?, 1, 0, 0, 0)');
                        if ($iq) { $iq->bind_param('ii', $role, $modId2); $iq->execute(); $iq->close(); }
                    } elseif ((int) $gr[1] !== 1) {
                        $conn->query('UPDATE role_permissions SET can_view = 1 WHERE id = ' . (int) $gr[0]);
                    }
                }
            }
            /* ② ثم الإيقاد */
            if ($conn->query('UPDATE nav_items SET active = 1 WHERE id = ' . $exists)
                && $conn->affected_rows > 0) {
                $added++;
                $have[$base] = true;
                echo "  ↑ م{$stage} «" . mb_substr((string) ($row['title'] ?? $canon), 0, 34)
                   . "» ⇐ {$real}  (أُوقد بمنحةِ قراءة)\n";
            }
            continue;
        }

        /* المجموعةُ: بالاسمِ إن طابق، وإلا أوّلُ مجموعةِ المرحلة */
        if (empty($groups[$stage])) { $skipMissing[] = $canon . ' (لا مجموعةَ للمرحلة ' . $stage . ')'; continue; }
        $gid = 0;
        $wantG = trim((string) ($row['group'] ?? ''));
        foreach ($groups[$stage] as $g) {
            if ($wantG !== '' && (mb_strpos($g['name'], $wantG) !== false || mb_strpos($wantG, $g['name']) !== false)) {
                $gid = $g['id']; break;
            }
        }
        if ($gid === 0) { $gid = $groups[$stage][0]['id']; }

        /* ◆ **والمسارُ يُخزَّن مجرَّدًا**: القاعدةُ تحمل `chk_nav_route_not_relative`
             فبادئةُ `../` تُردُّ — والمولِّدُ يضيف البادئةَ عند التصييرِ لا في التخزين. */
        $route = $real;
        $label = mb_substr(trim((string) ($row['title'] ?? $canon)), 0, 120);
        /* ◆ **والبابُ إلزاميٌّ بقيدٍ** (`chk_nav_door`): تُورَث قيمتُه من صفوفِ
             المجموعةِ نفسِها — فالرابطُ الجديدُ يسكن بابَ إخوتِه لا بابًا يُخترع. */
        /* ◆ **ورمزُ الصلاحيةِ لا يقوم بلا وحدتِه** (`chk_nav_items_module_or_code`
             وقاعدةُ FN-02): يُحَلُّ المودولُ بالرمزِ نفسِه، وبلا مودولٍ **لا رمز**
             — فصفٌّ برمزٍ بلا وحدةٍ يجعل الحارسَ يقرأ منحةً لا وجودَ لها. */
        $modId = null;
        $mq = $conn->prepare('SELECT id FROM modules WHERE code = ? LIMIT 1');
        if ($mq) { $mq->bind_param('s', $real); $mq->execute();
                   $mr = $mq->get_result()->fetch_row(); $mq->close();
                   if ($mr) { $modId = (int) $mr[0]; } }
        $door = 'DAILY';
        $dq = $conn->query("SELECT door FROM nav_items WHERE group_id = {$gid} AND door IS NOT NULL LIMIT 1");
        if ($dq && ($dx = $dq->fetch_row())) { $door = (string) $dx[0]; }
        $st = $conn->prepare('INSERT INTO nav_items (role_id, door, group_id, module_id, label_ar, route, icon,
                                                     sort_order, permission_code, active, created_at)
                              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())');
        if (!$st) { echo "  ⚠ تعذّر التحضير: {$conn->error}\n"; continue; }
        $icon = 'fa fa-file-lines';
        $sort = 900 + $added;
        $perm = ($modId !== null) ? $real : null;
        $st->bind_param('isiisssis', $role, $door, $gid, $modId, $label, $route, $icon, $sort, $perm);
        if ($st->execute() && $conn->affected_rows > 0) {
            $added++;
            $have[$base] = true;
            echo "  ✔ م{$stage} «" . mb_substr($label, 0, 34) . "» ⇐ {$real}\n";
        } else {
            echo "  ⚠ لم يُدرَج «{$label}»: {$conn->error}\n";
        }
        $st->close();
    }
    echo "\n";
}

echo "  أُضيف: {$added} · قائمٌ سلفًا: {$skipHave}\n";
if ($skipMissing) {
    /* ◆ ما لم يُضف **يُعلَن**: رابطٌ إلى ملفٍّ مفقودٍ أسوأُ من غيابِه */
    echo '  ◆ لم يُضف (يُعلَن ولا يُخفى): ' . count($skipMissing) . "\n";
    foreach (array_slice(array_unique($skipMissing), 0, 10) as $m) { echo "     · {$m}\n"; }
}
