<?php
/**
 * tests/client_profile_activities_test.php — فرصُ العميلِ وأنشطتُه تصلُ بطاقتَه
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ ما يقيسه: استعلاماتُ «فرصِ العميل» و«أنشطتِه» في
 *   Clients/client_profile.php تُنتزع **من الملفِّ نفسِه** (لا نسخةً مكتوبةً
 *   هنا تتعفّن) وتُمرَّر على بوابةِ المستأجرِ الحيّة، ثم تُقابَل بنتيجةٍ
 *   محسوبةٍ **بآليةٍ أخرى**: مجموعاتٌ في PHP لا وصلٌ في SQL. فما يتفق عليه
 *   مسلكانِ مختلفانِ ليس صدفةَ استعلام.
 *
 * ◆ **ولا رقمَ مجمَّدٌ في هذا الفاحص**: القاعدةُ حيّةٌ وجلساتٌ متوازيةٌ تكتب
 *   فيها (رُصد حيًّا: فرصةٌ أُضيفت لعميل 17 أثناءَ الجولة). فالفاحصُ الذي
 *   يقول «العميلُ 17 له فرصةٌ واحدة» يرسب غدًا بلا عطبٍ في الشيفرة —
 *   وذاك عيبُ قياسٍ لا كشفُ خلل. فكلُّ عددٍ هنا يُقرأ من المصدرِ لحظتَه.
 *
 * ◆ ما لا يقيسه — مُعلَنٌ لا مسكوتٌ عنه: لا يفتح الشاشةَ في المتصفحِ فلا
 *   يشهد للتصيير (قِيس بـcURL في الجولة)؛ ولا يقيس صلاحيةَ الوحدةِ
 *   (تُقاس بالدورِ لا بالاستعلام).
 *
 *   php tests/client_profile_activities_test.php
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');

$ROOT = dirname(__DIR__);
require_once $ROOT . '/config.php';
while (ob_get_level() > 0) { ob_end_clean(); }

$CO = 4; // شركةُ العرضِ — نفسُ نطاقِ الجلسةِ أدناه، وبها يُقيَّد المرجعُ الخام
$_SESSION['user'] = array('id' => 1, 'role' => '12', 'company_id' => $CO, 'name' => 'client profile test');

$PASS = 0; $FAIL = 0;
function ok($m)  { global $PASS; $PASS++; fwrite(STDOUT, "  ✔ {$m}\n"); }
function bad($m) { global $FAIL; $FAIL++; fwrite(STDOUT, "  ✘ FAIL: {$m}\n"); }

/* ── مرجعٌ خامٌّ مستقلٌّ عن البوابة (mysqli مباشرةً) ─────────────────────── */
function raw_all($conn, $sql, $params = array(), $types = '')
{
    $st = mysqli_prepare($conn, $sql);
    if (!$st) { throw new \RuntimeException('prepare: ' . mysqli_error($conn)); }
    if (!empty($params)) {
        if ($types === '') { $types = str_repeat('i', count($params)); }
        mysqli_stmt_bind_param($st, $types, ...$params);
    }
    mysqli_stmt_execute($st);
    $res = mysqli_stmt_get_result($st);
    $out = array();
    while ($res && $row = mysqli_fetch_assoc($res)) { $out[] = $row; }
    mysqli_stmt_close($st);
    return $out;
}

/* ── ① انتزاعُ بناءِ الوصلِ من الشاشةِ نفسِها — لا نسخةَ تتعفّن ─────────── */
$src  = file_get_contents($ROOT . '/Clients/client_profile.php');
$grab = function ($name) use ($src) {
    return preg_match('/\$' . $name . '\s*=\s*"(.*?)";/s', $src, $m) ? $m[1] : null;
};
$joins = $grab('acts_link_joins');
$cond  = $grab('acts_link_cond');
if ($joins === null || $cond === null) {
    bad('تعذّر انتزاعُ بناءِ الأنشطةِ من الشاشة');
    fwrite(STDOUT, "\n══ PASS={$PASS} FAIL={$FAIL} ══\n"); exit(1);
}
ok('انتُزع بناءُ وصلِ الأنشطةِ من Clients/client_profile.php');
if (strpos($src, 'o.client_id = ?') !== false) { ok('انتُزع شرطُ وصلِ الفرصِ (opportunities.client_id) من الشاشة'); }
else { bad('لم يُعثر على شرطِ وصلِ الفرصِ في الشاشة'); }

$gate = ems_tenant_db();

/* ── الاستعلامانِ كما في الشاشةِ حرفًا ───────────────────────────────────── */
$run_acts = function ($cid) use ($gate, $joins, $cond) {
    return $gate->scopedQuery(array(
        'scope'  => array('a' => 'activities'),
        'enrich' => array('o' => 'opportunities', 'ct' => 'contracts', 'pj' => 'project',
                          'u' => 'users', 'au' => 'users'),
    ), "SELECT a.id, a.activity_code, a.entity_type, a.entity_id, a.activity_date
        $joins
        LEFT JOIN users u  ON u.id = a.created_by
        LEFT JOIN users au ON au.id = a.assigned_user_id
        WHERE {TENANT_SCOPE} AND $cond
        ORDER BY a.activity_date DESC, a.id DESC
        LIMIT 200", array($cid, $cid, $cid));
};
$run_opps = function ($cid) use ($gate) {
    return $gate->scopedQuery(array(
        'scope'  => array('o' => 'opportunities'),
        'enrich' => array('u' => 'users'),
    ), "SELECT o.id, o.opp_code, o.stage, o.expected_revenue, o.currency, o.expected_close_date
        FROM opportunities o
        LEFT JOIN users u ON u.id = o.created_by
        WHERE {TENANT_SCOPE} AND o.client_id = ? AND COALESCE(o.is_deleted,0) = 0
        ORDER BY o.expected_close_date DESC, o.id DESC", array($cid));
};
$run_tnds = function ($cid) use ($gate) {
    return $gate->scopedQuery(array(
        'scope'  => array('t' => 'tenders'),
        'enrich' => array('o' => 'opportunities', 'ca' => 'clients', 'u' => 'users'),
    ), "SELECT t.id, t.tender_code, t.name, t.authority_id, t.opportunity_id,
               t.closing_date, t.participation_state, t.result,
               o.opp_code AS opp_code, o.client_id AS opp_client_id,
               ca.client_name AS authority_name, u.name AS creator_name
        FROM tenders t
        LEFT JOIN opportunities o ON o.id = t.opportunity_id AND COALESCE(o.is_deleted,0) = 0
        LEFT JOIN clients ca ON ca.id = t.authority_id
        LEFT JOIN users u ON u.id = t.created_by
        WHERE {TENANT_SCOPE} AND COALESCE(t.is_deleted,0) = 0
          AND (t.authority_id = ? OR o.client_id = ?)
        ORDER BY t.closing_date DESC, t.id DESC", array($cid, $cid));
};

/* ── ② مرجعٌ مستقلٌّ: المجموعاتُ تُبنى في PHP لا بوصلٍ في SQL ───────────── */
$oracle_acts = function ($cid) use ($conn, $CO) {
    // ثلاثةُ استعلاماتٍ بسيطةٍ ثم ترشيحٌ في PHP — آليةٌ غيرُ آليةِ الشاشة
    $oppIds = array();
    foreach (raw_all($conn, "SELECT id FROM opportunities WHERE client_id=? AND company_id=? AND COALESCE(is_deleted,0)=0", array($cid, $CO)) as $r) {
        $oppIds[intval($r['id'])] = true;
    }
    $ctIds = array();
    foreach (raw_all($conn, "SELECT ct.id FROM contracts ct INNER JOIN project pj ON pj.id=ct.project_id
                             WHERE pj.client_id=? AND ct.company_id=? AND COALESCE(ct.is_deleted,0)=0 AND COALESCE(pj.is_deleted,0)=0",
                     array($cid, $CO)) as $r) {
        $ctIds[intval($r['id'])] = true;
    }
    $hit = array();
    foreach (raw_all($conn, "SELECT id, entity_type, entity_id FROM activities WHERE company_id=? AND COALESCE(is_deleted,0)=0", array($CO)) as $a) {
        $eid = intval($a['entity_id']);
        $t   = $a['entity_type'];
        if ($t === 'client'      && $eid === intval($cid))  { $hit[intval($a['id'])] = 'client'; }
        if ($t === 'opportunity' && isset($oppIds[$eid]))   { $hit[intval($a['id'])] = 'opportunity'; }
        if ($t === 'contract'    && isset($ctIds[$eid]))    { $hit[intval($a['id'])] = 'contract'; }
    }
    return $hit;
};
$oracle_opps = function ($cid) use ($conn, $CO) {
    $ids = array();
    foreach (raw_all($conn, "SELECT id FROM opportunities WHERE client_id=? AND company_id=? AND COALESCE(is_deleted,0)=0", array($cid, $CO)) as $r) {
        $ids[intval($r['id'])] = true;
    }
    return $ids;
};
/* مناقصاتُ العميل: جهةً طارحةً (authority_id) أو عبرَ فرصةٍ من فرصِه.
   الفرصُ هنا بلا مرشِّحِ شركةٍ — محاكاةً لسلوكِ البوابة (enrich لا يُنطَّق)،
   وتكاملُ الشركةِ يُقاس بندًا مستقلًّا أدناه. */
$oracle_tnds = function ($cid) use ($conn, $CO) {
    $oppIds = array();
    foreach (raw_all($conn, "SELECT id FROM opportunities WHERE client_id=? AND COALESCE(is_deleted,0)=0", array($cid)) as $r) {
        $oppIds[intval($r['id'])] = true;
    }
    $hit = array();
    foreach (raw_all($conn, "SELECT id, authority_id, opportunity_id FROM tenders WHERE company_id=? AND COALESCE(is_deleted,0)=0", array($CO)) as $t) {
        if (intval($t['authority_id']) === intval($cid))            { $hit[intval($t['id'])] = 'authority'; }
        elseif (isset($oppIds[intval($t['opportunity_id'])]))        { $hit[intval($t['id'])] = 'opportunity'; }
    }
    return $hit;
};

/* ── ③ المقابلةُ على كلِّ عميلٍ له فرصٌ أو أنشطةٌ — لا على عميلٍ مختار ──── */
$targets = array();
foreach (raw_all($conn, "SELECT DISTINCT client_id c FROM opportunities WHERE company_id=? AND COALESCE(is_deleted,0)=0 AND client_id IS NOT NULL", array($CO)) as $r) {
    $targets[intval($r['c'])] = true;
}
foreach (raw_all($conn, "SELECT DISTINCT entity_id c FROM activities WHERE company_id=? AND COALESCE(is_deleted,0)=0 AND entity_type='client' AND entity_id IS NOT NULL", array($CO)) as $r) {
    $targets[intval($r['c'])] = true;
}
foreach (raw_all($conn, "SELECT DISTINCT authority_id c FROM tenders WHERE company_id=? AND COALESCE(is_deleted,0)=0 AND authority_id IS NOT NULL", array($CO)) as $r) {
    $targets[intval($r['c'])] = true;
}
$targets[17] = true; // بطاقةُ الطلبِ الأصلي — تُقاس دائمًا ولو خلت
$targets = array_keys($targets);
sort($targets);
fwrite(STDOUT, "  · العملاءُ المقيسون: " . count($targets) . " (" . implode(', ', $targets) . ")\n");

$actsMismatch = array(); $oppsMismatch = array(); $tndsMismatch = array();
$seenVia = array(); $seenTndVia = array();
foreach ($targets as $cid) {
    try {
        $g = $run_acts($cid);
        $gIds = array(); foreach ($g as $r) { $gIds[intval($r['id'])] = true; }
        $o = $oracle_acts($cid);
        // مقارنةُ مجموعَين لا قائمتَين: الترتيبُ يختلف عمدًا (البوابةُ بالتاريخِ
        // والمرجعُ بالمعرِّف) — والفرقُ في الترتيبِ ليس فرقًا في المحتوى
        $gk = array_keys($gIds); sort($gk);
        $ok_ = array_keys($o);   sort($ok_);
        if ($gk !== $ok_) {
            $actsMismatch[] = "عميل {$cid} (بوابة=" . count($gk) . " مرجع=" . count($ok_)
                . " · زائدٌ في البوابة: " . implode(',', array_diff($gk, $ok_))
                . " · ناقصٌ عنها: " . implode(',', array_diff($ok_, $gk)) . ")";
        }
        foreach ($o as $via) { $seenVia[$via] = (isset($seenVia[$via]) ? $seenVia[$via] : 0) + 1; }
    } catch (\Throwable $t) { $actsMismatch[] = "عميل {$cid} رمى: " . $t->getMessage(); }

    try {
        $g = $run_opps($cid);
        $gIds = array(); foreach ($g as $r) { $gIds[intval($r['id'])] = true; }
        $o = $oracle_opps($cid);
        $gk = array_keys($gIds); sort($gk);
        $ok_ = array_keys($o);   sort($ok_);
        if ($gk !== $ok_) {
            $oppsMismatch[] = "عميل {$cid} (بوابة=" . count($gk) . " مرجع=" . count($ok_)
                . " · زائدٌ في البوابة: " . implode(',', array_diff($gk, $ok_))
                . " · ناقصٌ عنها: " . implode(',', array_diff($ok_, $gk)) . ")";
        }
    } catch (\Throwable $t) { $oppsMismatch[] = "عميل {$cid} رمى: " . $t->getMessage(); }

    try {
        $g = $run_tnds($cid);
        $gIds = array(); foreach ($g as $r) { $gIds[intval($r['id'])] = true; }
        $o = $oracle_tnds($cid);
        $gk = array_keys($gIds); sort($gk);
        $ok_ = array_keys($o);   sort($ok_);
        if ($gk !== $ok_) {
            $tndsMismatch[] = "عميل {$cid} (بوابة=" . count($gk) . " مرجع=" . count($ok_)
                . " · زائدٌ في البوابة: " . implode(',', array_diff($gk, $ok_))
                . " · ناقصٌ عنها: " . implode(',', array_diff($ok_, $gk)) . ")";
        }
        foreach ($o as $via) { $seenTndVia[$via] = (isset($seenTndVia[$via]) ? $seenTndVia[$via] : 0) + 1; }
    } catch (\Throwable $t) { $tndsMismatch[] = "عميل {$cid} رمى: " . $t->getMessage(); }
}

if (empty($actsMismatch)) { ok('الأنشطة: البوابةُ تطابق المرجعَ المستقلَّ على كلِّ عميلٍ مقيس'); }
else { bad('الأنشطة: انحرافٌ عن المرجع — ' . implode(' · ', $actsMismatch)); }

if (empty($oppsMismatch)) { ok('الفرص: البوابةُ تطابق المرجعَ المستقلَّ على كلِّ عميلٍ مقيس'); }
else { bad('الفرص: انحرافٌ عن المرجع — ' . implode(' · ', $oppsMismatch)); }

if (empty($tndsMismatch)) { ok('المناقصات: البوابةُ تطابق المرجعَ المستقلَّ على كلِّ عميلٍ مقيس'); }
else { bad('المناقصات: انحرافٌ عن المرجع — ' . implode(' · ', $tndsMismatch)); }

/* مسارا المناقصةِ حيّانِ ويصلان */
foreach (array('authority' => 'جهةٌ طارحة', 'opportunity' => 'عبر فرصة') as $k => $lbl) {
    if (!empty($seenTndVia[$k])) { ok("المناقصات · مسارُ «{$lbl}» يصل ({$seenTndVia[$k]} مناقصةً عبرَ العملاءِ المقيسين)"); }
    else { bad("المناقصات · مسارُ «{$lbl}» لا يصل — والمصدرُ فيه صفوفٌ من نوعِه"); }
}

/* ── مناقصةٌ تصل عميلَين بمسارَين مختلفَين: ظهورٌ مزدوجٌ **مقصود** ──────────
   قِيس حيًّا أن جهةَ مناقصةٍ عميلٌ وفرصتَها لعميلٍ آخر. فالصفُّ يظهر في
   بطاقتَين، وكلُّ ظهورٍ صادقٌ في بابِه — وعمودُ «مصدر الوصل» هو ما يمنع
   قراءتَه تضخُّمًا. البندُ يشهد أن الحالةَ قائمةٌ في المصدرِ ومُفسَّرةٌ في العرض. */
$dual = array();
foreach (raw_all($conn, "SELECT t.id, t.tender_code, t.authority_id, o.client_id opp_client
                         FROM tenders t INNER JOIN opportunities o ON o.id=t.opportunity_id AND COALESCE(o.is_deleted,0)=0
                         WHERE t.company_id=? AND COALESCE(t.is_deleted,0)=0
                           AND o.client_id IS NOT NULL AND t.authority_id IS NOT NULL
                           AND o.client_id <> t.authority_id", array($CO)) as $r) {
    $dual[] = $r;
}
if (empty($dual)) {
    fwrite(STDOUT, "  · لا مناقصةَ تتباعد جهتُها عن عميلِ فرصتِها الآن — البندُ لا ينطبق (لا يُحتسب نجاحًا)\n");
} else {
    $d = $dual[0];
    $inAuth = array_key_exists(intval($d['id']), $oracle_tnds(intval($d['authority_id'])));
    $inOpp  = array_key_exists(intval($d['id']), $oracle_tnds(intval($d['opp_client'])));
    if ($inAuth && $inOpp) {
        ok("«{$d['tender_code']}» تصل العميلَ {$d['authority_id']} جهةً والعميلَ {$d['opp_client']} فرصةً — ظهورٌ مزدوجٌ مقصودٌ ومُفسَّر (" . count($dual) . " حالةً)");
    } else {
        bad("«{$d['tender_code']}» يفترض وصولَها لعميلَين ولم تصلهما (جهة=" . ($inAuth?'نعم':'لا') . " فرصة=" . ($inOpp?'نعم':'لا') . ")");
    }
    // وعمودُ «مصدر الوصل» موجودٌ في الشاشةِ فعلًا لا في النيّة
    if (strpos($src, 'مصدر الوصل') !== false && strpos($src, "'جهةٌ طارحة'") !== false) {
        ok('عمودُ «مصدر الوصل» ووسمُ «جهةٌ طارحة» مُصيَّرانِ في الشاشة');
    } else {
        bad('الظهورُ المزدوجُ قائمٌ بلا عمودٍ يفسّره في الشاشة');
    }
}

/* تكاملُ الشركة: لا مناقصةَ تشير إلى فرصةٍ من شركةٍ أخرى (جداولُ enrich لا تُنطَّق) */
$xco = raw_all($conn, "SELECT t.tender_code FROM tenders t INNER JOIN opportunities o ON o.id=t.opportunity_id
                       WHERE t.company_id=? AND COALESCE(t.is_deleted,0)=0 AND o.company_id <> t.company_id", array($CO));
if (empty($xco)) { ok('لا مناقصةَ تشير إلى فرصةٍ من شركةٍ أخرى'); }
else { bad('مناقصاتٌ تشير عبرَ الشركات: ' . implode(', ', array_column($xco, 'tender_code'))); }

/* ── ④ المساراتُ الثلاثةُ للأنشطةِ حيّةٌ في المصدرِ وتصل ─────────────────── */
foreach (array('client' => 'المباشر', 'opportunity' => 'عبر فرصة', 'contract' => 'عبر عقد') as $k => $lbl) {
    if (!empty($seenVia[$k])) { ok("مسارُ «{$lbl}» يصل ({$seenVia[$k]} نشاطًا عبرَ العملاءِ المقيسين)"); }
    else { bad("مسارُ «{$lbl}» لا يصل — والمصدرُ فيه صفوفٌ من نوعِه"); }
}

/* ── ⑤ لا تسرُّبَ عبرَ العملاء ───────────────────────────────────────────── */
$leakA = array(); $leakO = array(); $ownerA = array(); $ownerO = array();
foreach ($targets as $cid) {
    try {
        foreach ($run_acts($cid) as $r) {
            $id = intval($r['id']);
            if (isset($ownerA[$id]) && $ownerA[$id] !== $cid) {
                // نشاطٌ واحدٌ لعميلَين = تسرُّب (إلا أن يكون المرجعُ يقرّه)
                $o1 = $oracle_acts($ownerA[$id]); $o2 = $oracle_acts($cid);
                if (!isset($o1[$id]) || !isset($o2[$id])) { $leakA[] = $r['activity_code']; }
            }
            $ownerA[$id] = $cid;
        }
        foreach ($run_opps($cid) as $r) {
            $id = intval($r['id']);
            if (isset($ownerO[$id]) && $ownerO[$id] !== $cid) { $leakO[] = $r['opp_code']; }
            $ownerO[$id] = $cid;
        }
    } catch (\Throwable $t) { /* رُصد أعلاه */ }
}
if (empty($leakA)) { ok('لا نشاطَ يظهر في بطاقةِ عميلٍ لا يملكه'); }
else { bad('تسرُّبُ أنشطةٍ: ' . implode(', ', array_unique($leakA))); }
if (empty($leakO)) { ok('لا فرصةَ تظهر في بطاقةِ عميلٍ لا يملكها'); }
else { bad('تسرُّبُ فرصٍ: ' . implode(', ', array_unique($leakO))); }

/* ── ⑥ القاعدةُ الحاكمة: لا تُجمع عملتانِ في رقم ─────────────────────────── */
$multiCur = null;
foreach ($targets as $cid) {
    $curs = array();
    foreach ($run_opps($cid) as $r) {
        $c = ($r['currency'] !== '' && $r['currency'] !== null) ? $r['currency'] : '—';
        $curs[$c] = true;
    }
    if (count($curs) > 1) { $multiCur = array($cid, array_keys($curs)); break; }
}
if ($multiCur === null) {
    fwrite(STDOUT, "  · لا عميلَ بعملتَين في المصدرِ الآن — بندُ العملتَين لا ينطبق (لا يُحتسب نجاحًا)\n");
} else {
    list($mcId, $mcList) = $multiCur;
    /* الثابتُ المقيسُ هو **الفصلُ بالعملةِ مفتاحًا**، لا نصُّ تنفيذٍ بعينه:
       الدلوُ يُبنى بـcp_money_add (العملةُ مفتاحًا) ويُصيَّر بـcp_money_fmt
       (سطرٌ لكلِّ مفتاح). فلو عاد أحدُهما مجموعًا واحدًا سقط البند. */
    $addsByCur = (strpos($src, 'function cp_money_add') !== false)
              && (strpos($src, 'cp_money_add($opp_pipeline_by_cur, $opp[\'currency\']') !== false
                  || preg_match('/cp_money_add\(\$opp_pipeline_by_cur/', $src));
    $rendersEachCur = (strpos($src, 'function cp_money_fmt') !== false)
              && preg_match('/foreach \(cp_money_fmt\(\$opp_pipeline_by_cur\) as/', $src);
    if ($addsByCur && $rendersEachCur) {
        ok("عميل {$mcId} له عملتانِ (" . implode(' · ', $mcList) . ") والشاشةُ تجمعُ بالعملةِ مفتاحًا وتصيّر سطرًا لكلِّ عملة");
    } else {
        bad("عميل {$mcId} له عملتانِ والشاشةُ لا تفصلُهما (تجميع=" . ($addsByCur ? 'نعم' : 'لا') . " تصيير=" . ($rendersEachCur ? 'نعم' : 'لا') . ")");
    }
    /* ولا تُلحق العملةُ الفارغةُ بعملةٍ حقيقية: لها دلوُها المُعلَن */
    if (preg_match("/\\\$k = \(trim\(\(string\) \\\$cur\) !== ''\) \? trim\(\(string\) \\\$cur\) : 'بلا عملة';/", $src)) {
        ok('العملةُ الفارغةُ لها دلوٌ مُعلَنٌ «بلا عملة» — لا تُلحق بعملةٍ ولا تُطرح صامتة');
    } else {
        bad('العملةُ الفارغةُ بلا دلوٍ مُعلَن — راجعْ cp_money_add');
    }
    // ولا مجموعَ عامٍّ عابرٍ للعملاتِ في الشاشة
    if (preg_match('/\$opp_pipeline_value\s*\+=/', $src)) {
        bad('الشاشةُ تحمل مجموعًا عابرًا للعملاتِ ($opp_pipeline_value)');
    } else {
        ok('لا مجموعَ عابرًا للعملاتِ في الشاشة');
    }
}

/* ── ⑦ معدَّلُ التحويلِ يُقاس على المحسومِ وحدَه ──────────────────────────── */
if (preg_match('/\$opp_decided\s*=\s*\$opp_won\s*\+\s*\$opp_lost/', $src)
    && preg_match('/\$opp_conversion\s*=\s*\$opp_decided\s*>\s*0\s*\?\s*round\(\(\$opp_won\s*\/\s*\$opp_decided\)/', $src)) {
    ok('معدَّلُ التحويلِ مقامُه المحسومُ (فوز+خسارة) لا الكلُّ — والمفتوحُ لم يُحسم');
} else {
    bad('معدَّلُ التحويلِ مقامُه ليس المحسومَ — راجعْ $opp_conversion');
}

/* ── ⑧ عميلٌ لا وجودَ له ⇒ فراغٌ نظيفٌ لا خطأ ────────────────────────────── */
try {
    $a = $run_acts(999999); $o = $run_opps(999999); $tn = $run_tnds(999999);
    if (count($a) === 0 && count($o) === 0 && count($tn) === 0) { ok('عميلٌ غيرُ موجودٍ ⇒ فراغٌ نظيفٌ في الجداولِ الثلاثةِ لا خطأ'); }
    else { bad('عميلٌ غيرُ موجودٍ أرجع صفوفًا: أنشطة=' . count($a) . ' فرص=' . count($o) . ' مناقصات=' . count($tn)); }
} catch (\Throwable $t) { bad('عميلٌ غيرُ موجودٍ رمى: ' . $t->getMessage()); }

/* ══ الأقسامُ المالية والتنفيذية: بوابةٌ مقابلَ مرجعٍ خام ══════════════════ */
fwrite(STDOUT, "\n── الأقسامُ المالية والتنفيذية ──\n");

$secs = array(
    'عروض الأسعار' => array(
        array('scope' => array('q' => 'quotations'), 'enrich' => array('o' => 'opportunities', 'u' => 'users')),
        "SELECT q.id FROM quotations q
         LEFT JOIN opportunities o ON o.id=q.opportunity_id AND COALESCE(o.is_deleted,0)=0
         LEFT JOIN users u ON u.id=q.created_by
         WHERE {TENANT_SCOPE} AND q.client_id = ? AND COALESCE(q.is_deleted,0)=0",
        "SELECT id FROM quotations WHERE client_id=? AND company_id=? AND COALESCE(is_deleted,0)=0"),
    'كشوف الحساب' => array(
        array('scope' => array('s' => 'fin_client_statements')),
        "SELECT s.id FROM fin_client_statements s WHERE {TENANT_SCOPE} AND s.client_id = ?",
        "SELECT id FROM fin_client_statements WHERE client_id=? AND company_id=?"),
    'المستخلصات' => array(
        array('scope' => array('k' => 'claims'), 'enrich' => array('p' => 'project')),
        "SELECT k.id FROM claims k LEFT JOIN project p ON p.id=k.project_id AND COALESCE(p.is_deleted,0)=0
         WHERE {TENANT_SCOPE} AND k.client_id = ? AND COALESCE(k.is_deleted,0)=0",
        "SELECT id FROM claims WHERE client_id=? AND company_id=? AND COALESCE(is_deleted,0)=0"),
    'الفواتير' => array(
        array('scope' => array('v' => 'tax_invoices')),
        "SELECT v.id FROM tax_invoices v WHERE {TENANT_SCOPE} AND v.client_id = ?",
        "SELECT id FROM tax_invoices WHERE client_id=? AND company_id=?"),
    'الحجوزات' => array(
        array('scope' => array('f' => 'fleet_reservations'), 'enrich' => array('e' => 'equipments')),
        "SELECT f.id FROM fleet_reservations f LEFT JOIN equipments e ON e.id=f.equipment_id
         WHERE {TENANT_SCOPE} AND f.client_id = ? AND COALESCE(f.is_deleted,0)=0",
        "SELECT id FROM fleet_reservations WHERE client_id=? AND company_id=? AND COALESCE(is_deleted,0)=0"),
);

/* عملاءُ الحزمةِ المالية يُضافون للمقيسين — وإلا قِيست أقسامٌ فارغةٌ فقط */
$moneyTargets = $targets;
foreach (raw_all($conn, "SELECT DISTINCT client_id c FROM claims WHERE company_id=? AND COALESCE(is_deleted,0)=0 AND client_id IS NOT NULL LIMIT 12", array($CO)) as $r) {
    $moneyTargets[] = intval($r['c']);
}
foreach (raw_all($conn, "SELECT DISTINCT client_id c FROM quotations WHERE company_id=? AND COALESCE(is_deleted,0)=0 AND client_id IS NOT NULL LIMIT 12", array($CO)) as $r) {
    $moneyTargets[] = intval($r['c']);
}
$moneyTargets = array_values(array_unique($moneyTargets));
fwrite(STDOUT, "  · العملاءُ المقيسون هنا: " . count($moneyTargets) . "\n");

foreach ($secs as $name => $spec) {
    $bad = array(); $seen = 0;
    foreach ($moneyTargets as $cid) {
        try {
            $g = $gate->scopedQuery($spec[0], $spec[1], array($cid));
            $gk = array(); foreach ($g as $r) { $gk[] = intval($r['id']); } sort($gk);
            $ok_ = array(); foreach (raw_all($conn, $spec[2], array($cid, $CO)) as $r) { $ok_[] = intval($r['id']); } sort($ok_);
            if ($gk !== $ok_) { $bad[] = "عميل {$cid} (بوابة=" . count($gk) . " مرجع=" . count($ok_) . ")"; }
            $seen += count($ok_);
        } catch (\Throwable $t) { $bad[] = "عميل {$cid} رمى: " . $t->getMessage(); }
    }
    if (empty($bad)) { ok("{$name}: البوابةُ تطابق المرجعَ على كلِّ عميلٍ مقيس ({$seen} صفًّا)"); }
    else { bad("{$name}: انحرافٌ — " . implode(' · ', array_slice($bad, 0, 4))); }
}

/* ══ العملةُ الفارغةُ ليست عملة ══════════════════════════════════════════════
   120 عرضًا من 140 في هذه القاعدةِ بعملةٍ فارغة. فإن أُلحقت بعملةٍ أخرى أفسدتها،
   وإن طُرحت صامتةً اختفى مالٌ موجود. الشاشةُ تجمعها في دلوٍ مُعلَنٍ «بلا عملة». */
$blank = raw_all($conn, "SELECT client_id, COUNT(*) n FROM quotations
                         WHERE company_id=? AND COALESCE(is_deleted,0)=0 AND state='مقبول'
                           AND (currency IS NULL OR currency='') GROUP BY client_id ORDER BY n DESC LIMIT 1", array($CO));
if (empty($blank)) {
    fwrite(STDOUT, "  · لا عرضَ مقبولًا بعملةٍ فارغةٍ الآن — البندُ لا ينطبق (لا يُحتسب نجاحًا)\n");
} else {
    if (strpos($src, "'بلا عملة'") !== false && strpos($src, 'function cp_money_add') !== false) {
        ok("عرضٌ مقبولٌ بعملةٍ فارغةٍ موجودٌ (عميل {$blank[0]['client_id']}) والشاشةُ تُفرده في دلوِ «بلا عملة»");
    } else {
        bad('عروضٌ بعملةٍ فارغةٍ في المصدرِ والشاشةُ لا تُفردها');
    }
}

/* الفواتيرُ الملغاةُ لا تُجمع في «إجمالي المفوتر» */
if (preg_match("/if \(trim\(\(string\) \\\$v\['state'\]\) === 'cancelled'\) \{ \\\$inv_cancelled\+\+; continue; \}/", $src)) {
    ok('الفواتيرُ الملغاةُ تُعدُّ ولا تُجمع في الإجمالي');
} else {
    bad('الفواتيرُ الملغاةُ تدخل الإجماليَّ — راجعْ حلقةَ $client_invoices');
}

/* الرصيدُ القائمُ من نسخةٍ نافذةٍ (issued) لا من مُعادةٍ (superseded) */
if (strpos($src, "if (trim((string) \$s['state']) === 'issued') { \$stmt_current = \$s; break; }") !== false) {
    ok('الرصيدُ القائمُ يُقرأ من كشفٍ نافذٍ (issued) لا من مُعادٍ (superseded)');
} else {
    bad('الرصيدُ القائمُ قد يُقرأ من كشفٍ مُعادٍ — راجعْ $stmt_current');
}

/* المجموعاتُ الأربعُ تختفي بكاملِها بلا مصادر */
$grpOk = 0;
foreach (array('cp_grp_pipe', 'cp_grp_deliver', 'cp_grp_money', 'cp_grp_rel') as $gname) {
    if (preg_match('/\$' . $gname . '\s*=/', $src) && strpos($src, 'if ($' . $gname . '):') !== false) { $grpOk++; }
}
if ($grpOk === 4) { ok('المجموعاتُ الأربعُ مشروطةٌ بمصادرِها — لا عنوانَ فوقَ فراغ'); }
else { bad("مجموعاتٌ بلا شرطِ مصدر: {$grpOk}/4"); }

/* معدَّلُ الفوزِ بالمناقصاتِ مقامُه المحسومُ وحدَه — «قيد التقييم» و«إلغاء» ليسا حكمًا */
if (preg_match('/\$tnd_decided\s*=\s*\$tnd_won\s*\+\s*\$tnd_lost/', $src)
    && preg_match('/\$tnd_win_rate\s*=\s*\$tnd_decided\s*>\s*0\s*\?\s*round\(\(\$tnd_won\s*\/\s*\$tnd_decided\)/', $src)) {
    ok('معدَّلُ الفوزِ بالمناقصاتِ مقامُه المحسومُ (فوز+خسارة) لا الكلُّ');
} else {
    bad('معدَّلُ الفوزِ بالمناقصاتِ مقامُه ليس المحسومَ — راجعْ $tnd_win_rate');
}

fwrite(STDOUT, "\n══ PASS={$PASS} FAIL={$FAIL} ══\n");
exit($FAIL > 0 ? 1 : 0);
