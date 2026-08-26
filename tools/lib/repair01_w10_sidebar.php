<?php
/**
 * tools/lib/repair01_w10_sidebar.php — الخطواتُ السبعُ للسايدبارِ في نطاقِ الشقّ
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **السايدبارُ قبل الشاشات** (‏RPR-PATCH-01 ③): وهذه مرحلةُ شقٍّ لا مرحلةُ
 *   بناء — فالخطواتُ السبعُ هنا تُقاس على أسطحِ النطاقِ **الحيّةِ** كلِّها،
 *   والتصحيحُ الوحيدُ الذي تُجريه هو **الربطُ بالمُعرِّفِ المعياريّ** (‏الخطوةُ ⑦).
 *
 * ⛔ **ولا يُعطَّل بندٌ حيٌّ في هذه المرحلة**: التعطيلُ قرارُ إخفاءٍ يغيّر ما يراه
 *   المستخدم، وهو **مشروطٌ بعذرٍ مكتوبٍ في سجلِّ الإخفاء** — ولا عذرَ ينشأ عن
 *   شقِّ ملكيّةٍ وحدَه. فالحكمُ يُقاس ويُعلَن، والتعطيلُ يبقى لمرحلةِ بناءِ النطاق.
 *
 * ⛔ **ولا اسمٌ يُكتب هنا**: مصدرُ الاسمِ بعد W06 سجلٌّ واحدٌ (`repair01_ui_labels`)
 *   و`nav_canonical`، وكتابةُ اسمٍ في هذه المرحلةِ تفتح مصدرًا ثانيًا.
 * ═══════════════════════════════════════════════════════════════════════════
 */

function repair01_w10_sidebar_apply(mysqli $conn, $ROOT, array $res, $DRY)
{
    $esc = function ($s) use ($conn) { return $conn->real_escape_string((string) $s); };
    $one = function ($sql) use ($conn) { return repair01_w10_one($conn, $sql); };
    $out = array('rows' => 0, 's1_off' => 0, 's2_fix' => 0, 's3_fix' => 0,
                 's4_ok' => 0, 's5_tab' => 0, 's6_grants' => 0, 's7_linked' => 0, 's7_written' => 0,
                 'two_axes' => 0, 's6_no_guard' => 0);

    if (!$DRY) { $conn->query("DELETE FROM repair01_w10_sidebar"); }

    foreach ($res as $sid => $v) {
        $rt = (string) $v['route'];
        if ($rt === '' || !is_file($ROOT . '/' . $rt)) { continue; }     /* الحيُّ وحدَه له بندُ قائمة */
        $rtE = $esc($rt);

        $can = $conn->query("SELECT canonical_ar, group_name, sort_no, screen_id, status
                               FROM nav_canonical WHERE route = '$rtE' LIMIT 1");
        $can = $can ? $can->fetch_assoc() : null;
        $live = $conn->query("SELECT n.label_ar, g.name AS gname, n.sort_order, n.active
                                FROM nav_items n LEFT JOIN link_groups g ON g.id = n.group_id
                               WHERE n.route = '$rtE' ORDER BY n.active DESC LIMIT 1");
        $live = $live ? $live->fetch_assoc() : null;
        $vis = (string) $one("SELECT visibility_class FROM repair01_screen_registry
                               WHERE screen_id = '" . $esc($sid) . "' LIMIT 1");

        /* ① الظهورُ بالاعتماد — يُقاس ولا يُعطَّل في مرحلةِ شقّ */
        $s1 = $live === null ? 'NO_NAV_ITEM'
            : ((int) $live['active'] === 1 ? 'ACTIVE_APPROVED' : 'DORMANT_BY_DESIGN');
        if ($s1 === 'DORMANT_BY_DESIGN') { $out['s1_off']++; }

        /* ② الاسمُ من السجلِّ المركزيِّ لا من الملفّ */
        $lLive = $live ? (string) $live['label_ar'] : '';
        $lCan  = $can ? (string) $can['canonical_ar'] : '';
        $s2 = ($lCan === '') ? 'NO_CANONICAL' : (($lLive === $lCan) ? 'LABEL_FROM_REGISTRY' : 'LABEL_DRIFT');
        if ($s2 === 'LABEL_FROM_REGISTRY') { $out['s2_fix']++; }

        /* ③ المجموعةُ من مجموعاتِ الدورة
           ⚠ **ومحوران لا محور**: `link_groups.name` مجموعةُ **السايدبار** التي
             يراها الدور، و`nav_canonical.group_name` مجموعةُ **الدورة** (‏مرحلةُ
             السطحِ من دورةِ العمل). ومقارنةُ نصَّيهما تُبلغ ثمانيًا وأربعينَ
             «انحرافًا» لا وجودَ له — فالمطلوبُ أن يكون **لكلٍّ منهما مصدرُه
             المسجَّل**، لا أن يتساوى النصّان. (‏`W10-D-07`) */
        $gLive = $live ? (string) $live['gname'] : '';
        $gCan  = $can ? (string) $can['group_name'] : '';
        if ($gCan === '') { $s3 = 'NO_CANONICAL'; }
        elseif ($live === null) { $s3 = 'CYCLE_ONLY_NO_ITEM'; }
        elseif ($gLive === '') { $s3 = 'GROUP_OFF_REGISTRY'; }
        else { $s3 = 'GROUP_FROM_CYCLE'; $out['s3_fix']++; }
        if ($gLive !== '' && $gCan !== '' && $gLive !== $gCan) { $out['two_axes']++; }

        /* ④ الترتيبُ من السجلِّ لا أبجديًّا ولا بتاريخِ الإنشاء */
        $s4no = $can ? (int) $can['sort_no'] : 0;
        $s4 = $s4no > 0 ? 'ORDER_FROM_REGISTRY' : 'NO_ORDER_SOURCE';
        if ($s4 === 'ORDER_FROM_REGISTRY') { $out['s4_ok']++; }

        /* ⑤ الأبُ والتبويبُ — القرارُ يحدّد الموضع */
        $s5 = ($vis === 'TAB_CHILD') ? 'TAB_IN_PARENT' : (($vis === 'ANCHOR') ? 'ANCHOR' : 'MENU_ITEM');
        if ($s5 === 'TAB_IN_PARENT') { $out['s5_tab']++; }

        /* ⑥ الظهورُ بالصلاحيةِ لا بالإخفاء — ولكلِّ سطحٍ حارسُ عرضٍ على الخادم */
        $perm = (int) $one("SELECT COUNT(*) FROM role_permissions rp JOIN modules m ON m.id = rp.module_id
                             WHERE m.code = '$rtE' AND rp.can_view = 1");
        $guard = repair01_w10_guard_of($ROOT, $rt);
        $s6 = ($guard['kind'] !== 'NONE' && $perm > 0) ? 'GUARDED_AND_GRANTED'
            : ($guard['kind'] === 'NONE' ? 'NO_SERVER_GUARD' : 'NO_GRANT');
        if ($s6 === 'GUARDED_AND_GRANTED') { $out['s6_grants']++; }
        if ($s6 === 'NO_SERVER_GUARD') { $out['s6_no_guard']++; }

        /* ⑦ الربطُ بالمُعرِّفِ المعياريّ — **وهذا وحدَه ما تكتبه المرحلة** */
        $linked = ($can && (string) $can['screen_id'] === $sid) ? 1 : 0;
        if (!$linked && $can && (string) $can['screen_id'] === '' && !$DRY) {
            $conn->query("UPDATE nav_canonical SET screen_id = '" . $esc($sid) . "' WHERE route = '$rtE'");
            $linked = 1; $out['s7_written']++;
        }
        $out['s7_linked'] += $linked;

        if (!$DRY) {
            $conn->query("INSERT INTO repair01_w10_sidebar
                (screen_id, route, owner_code, s1_verdict, s1_rule, s2_verdict, s2_rule,
                 s3_verdict, s3_rule, s4_verdict, s4_rule, s4_order_no, s5_verdict, s5_rule,
                 s6_verdict, s6_rule, s6_perm_rows, s7_verdict, s7_rule, s7_linked, group_name)
                VALUES ('" . $esc($sid) . "', '$rtE', '" . $esc($v['resolved_code']) . "',
                        '" . $esc($s1) . "', 'W10_S1_ACTIVE_BY_TARGET',
                        '" . $esc($s2) . "', 'W10_S2_LABEL_FROM_REGISTRY',
                        '" . $esc($s3) . "', 'W10_S3_GROUP_FROM_CYCLE',
                        '" . $esc($s4) . "', 'W10_S4_ORDER_FROM_REGISTRY', " . $s4no . ",
                        '" . $esc($s5) . "', 'W10_S5_PARENT_FROM_DECISION',
                        '" . $esc($s6) . "', 'W10_S6_GRANT_NOT_HIDE', " . $perm . ",
                        '" . ($linked ? 'LINKED' : 'NOT_LINKED') . "', 'W10_S7_CANONICAL_SCREEN_ID',
                        " . $linked . ", '" . $esc($gCan !== '' ? $gCan : $gLive) . "')
                ON DUPLICATE KEY UPDATE owner_code = VALUES(owner_code)");
        }
        $out['rows']++;
    }
    return $out;
}
