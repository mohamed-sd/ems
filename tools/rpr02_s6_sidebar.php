<?php
/**
 * tools/rpr02_s6_sidebar.php — `RPR-02` §٦ · السايدبارُ قبلَ الشاشات · قياسُ السبع
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **المطلوبُ بنصِّه** — §٦ سبعةُ تصحيحاتٍ: تعطيلُ ما ليس في الملفِّ **بعذرٍ
 *   مكتوبٍ ومرجعٍ — ولا حذفَ صامت** · وتصحيحُ الأسماءِ على التسميةِ المعتمدة ·
 *   والمجموعاتُ **اسمًا وعددًا** · والترتيبُ **على دورةِ العملِ لا على تاريخِ
 *   الإضافة** · والأبُ والتبويب · والظهورُ بالصلاحيةِ **ولا تُضحَّى الصلاحيةُ
 *   لأجلِ ترتيبٍ أجمل** · والربطُ بسجلِّ الملاحةِ المعياريِّ **ولا ترتيبَ يدويٌّ
 *   موازٍ**.
 *
 * ◆ **ولماذا قياسٌ قبلَ تصحيح**: §٦ يسبق §٧ في الأمر، **والتصحيحُ بلا قياسٍ
 *   قبليٍّ يُفقد الفرقَ الذي أحدثه**. ومقياسُ §١٢ الثامنُ («تطابقُ ترتيبِ
 *   السايدبارِ ومجموعاتِه») **محجوبٌ على هذا القياسِ بعينِه**.
 *
 * ◆ **والمقامُ حيٌّ لا مُعلَن**: `nav_items` النشِطةُ كما تُصيَّر — لا كما
 *   تُعلن في بيانٍ. و`repair01_w4_sidebar` قاس **ستّةً وعشرين** سطحًا هي نطاقُ
 *   موجتِه، **ولا يُقرأ ذلك تغطيةً للسايدبار كلِّه** — والفرقُ يُسمّى بعددِه.
 *
 * ◆ ⛔ **وسبعةُ أحكامٍ لا حكمٌ واحد**: بندٌ قد يستقيم اسمًا ويختلَّ مجموعةً.
 *   وجمعُها في «مطابق/غير مطابق» يُخفي أيَّ الخطواتِ تحتاج عملًا.
 *
 * ⛔ **وهذا يقيس ولا يصحّح** — والتصحيحُ يمسّ ملاحةً حيّةً يراها المستخدمون،
 *   فيُبنى على هذا السجلِّ بعد قراءتِه لا قبلَها.
 *
 * التشغيل:
 *   php tools/rpr02_s6_sidebar.php [--md] [--list] [--selftest]
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
define('EMS_CLI', true);
$ROOT = str_replace(DIRECTORY_SEPARATOR, '/', dirname(__DIR__));
require_once $ROOT . '/includes/session_bootstrap.php';
require_once $ROOT . '/config.php';
while (ob_get_level()) { ob_end_clean(); }
$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');

$MD   = in_array('--md', $argv, true);
$LIST = in_array('--list', $argv, true);
$SELF = in_array('--selftest', $argv, true);

$snap = null;
$r = $conn->query("SELECT * FROM repair01_freeze_snapshot WHERE released_at IS NULL
                    ORDER BY frozen_at DESC LIMIT 1");
if ($r && $r->num_rows) { $snap = $r->fetch_assoc(); }
$sid = $snap ? $snap['snapshot_id'] : 'DRY';

/* ⛔ **استعلامٌ يسقط يجب أن يُسمع**: `$conn->query` تُرجع `false` فيصير النداءُ
   عليها فادحًا صامتًا (‏خرجَ ٢٥٥ بلا سطرِ خطأٍ واحد، لأنَّ `config.php` يبتلع
   مخرجَ CLI). فكلُّ استعلامٍ يمرُّ من هنا ويموت باسمِه. */
$q = function ($sql) use ($conn) {
    $r = $conn->query($sql);
    if (!$r) { fwrite(STDERR, "✘ استعلامٌ سقط: {$conn->error}\n   $sql\n"); exit(2); }
    return $r;
};
$one = function ($sql) use ($conn) {
    $x = $conn->query($sql); if (!$x) { return null; }
    $y = $x->fetch_row(); return $y ? $y[0] : null;
};

$norm = function ($s) {
    $s = preg_replace('~[\x{064B}-\x{0652}\x{0670}\x{0640}]~u', '', (string) $s);
    $s = preg_replace('~[\x{0622}\x{0623}\x{0625}]~u', "\u{0627}", $s);
    $s = preg_replace('~\x{0649}~u', "\u{064A}", $s);
    $s = preg_replace('~\x{0629}~u', "\u{0647}", $s);
    $s = preg_replace('~[«»"\'\[\]\-–—/·،,\.]~u', ' ', $s);
    return trim(preg_replace('~\s+~u', ' ', $s));
};

/* ═══ ① البنودُ الحيّةُ كما تُصيَّر ═══════════════════════════════════════ */
$items = array();
$r = $q("SELECT n.id, n.role_id, n.group_id, n.route, n.label_ar, n.sort_order,
                          n.permission_code, g.name AS group_name
                     FROM nav_items n
                     LEFT JOIN link_groups g ON g.id = n.group_id
                    WHERE n.active = 1
                    ORDER BY n.role_id, n.group_id, n.sort_order");
while ($x = $r->fetch_assoc()) { $items[] = $x; }
$N = count($items);

/* السجلُّ المبنيُّ بمسارِه — الجسرُ من الرابطِ إلى السطح */
$byRoute = array();
$r = $q("SELECT screen_id, route, screen_file, owner_code, canonical_label_ar,
                          parent_screen_id, parent_rule, guard_kind, ownership_verdict, on_disk
                     FROM repair01_screen_registry WHERE route <> ''");
while ($x = $r->fetch_assoc()) { $byRoute[strtolower(trim($x['route'], '/'))] = $x; }

/* السجلُّ بمعرِّفِه — **والأبُ يُطلب بمعرِّفِه لا بمساره**: أبٌ بلا مسارٍ مبنيٍّ
   بعدُ له صفٌّ في السجلِّ ومعرِّفٌ، فالبحثُ بالمسارِ وحدَه يُيتِّمه زورًا. */
$byId = array();
$r = $q("SELECT screen_id, route, owner_code, parent_screen_id, parent_rule, on_disk
           FROM repair01_screen_registry");
while ($x = $r->fetch_assoc()) { $byId[$x['screen_id']] = $x; }

/* **مصادرُ الأبِ المُعلَنة** — سجلُّ تبويباتِ الكياناتِ وعذرُ الإخفاءِ المسجَّل.
   ⛔ وما عداهما (`ANCHOR_MENU_PARENT` · `ANCHOR_SCREEN_PARENT`) **استنباطٌ من
      رابطٍ في صفحةٍ** لا إعلانُ بنية — وهو «أبٌ ليس أبَه المعماريّ» بعينِه. */
$PARENT_DECLARED = array('ENTITY_TABS_REGISTRY' => 1, 'HIDDEN_LOG_TAB_IN_PARENT' => 1);

/* **التسميةُ المعتمدةُ وموضعُها** — و`nav_canonical` تحمل الأربعةَ معًا:
   الاسمَ (`canonical_ar`) والمجموعةَ (`group_name`) والترتيبَ (`sort_no`)
   والمعرِّفَ (`screen_id`). ⇒ فالخطواتُ ٢ و٣ و٤ و٧ تُقاس على **مصدرٍ معتمَدٍ
   واحدٍ** لا على اشتقاقٍ من دفترِ المتطلبات. */
$canon = array();
$r = $q("SELECT route, canonical_ar, group_name, sort_no, screen_id, status FROM nav_canonical");
while ($x = $r->fetch_assoc()) { $canon[strtolower(trim((string) $x['route'], '/'))] = $x; }

/* مجموعاتُ الملفِّ التصميميِّ لكلِّ إدارة */
$specGroup = array();
$r = $q("SELECT unit, group_name FROM repair01_requirements WHERE group_name <> ''");
while ($x = $r->fetch_row()) {
    if (preg_match('~^(\d{2}|AS|E1|E2|WS)~u', $x[0], $m)) {
        $u = ($m[1] === 'AS') ? 'IAF' : (($m[1] === 'E1') ? 'EX-CEO'
           : (($m[1] === 'E2') ? 'EX-DVP' : (($m[1] === 'WS') ? 'WS-MY' : 'DEP-' . $m[1])));
        $specGroup[$u][$norm($x[1])] = 1;
    }
}

/* ترتيبُ دورةِ العملِ التصميميّ: النطاق ⇐ [اسمُ السطحِ مطبَّعًا ⇒ seq] */
$specOrder = array();
$r = $q("SELECT unit, surface, seq FROM repair01_requirements WHERE surface <> ''");
while ($x = $r->fetch_row()) {
    if (preg_match('~^(\d{2}|AS|E1|E2|WS)~u', $x[0], $m)) {
        $u = ($m[1] === 'AS') ? 'IAF' : (($m[1] === 'E1') ? 'EX-CEO'
           : (($m[1] === 'E2') ? 'EX-DVP' : (($m[1] === 'WS') ? 'WS-MY' : 'DEP-' . $m[1])));
        $specOrder[$u][$norm($x[1])] = (int) $x[2];
    }
}

/* ═══ ② الخطواتُ السبعُ — حكمٌ لكلِّ بندٍ في كلِّ خطوة ═══════════════════ */
$S = array();
for ($i = 1; $i <= 7; $i++) { $S[$i] = array('ok' => 0, 'bad' => 0, 'na' => 0, 'ex' => array(), 'rt' => array()); }
$S[5]['cross'] = 0; $S[5]['crossRt'] = array();   /* خبرٌ لا حكم — أبٌ معماريٌّ عابرٌ للإدارات */
$ord = array();

foreach ($items as $it) {
    $rt  = strtolower(trim((string) $it['route'], '/'));
    /* **ورمزُ `?view=`/`?tab=` تبويبٌ مُعلَنٌ لا مسارٌ يتيم**: خمسةٌ وثلاثون
       مسارًا حيًّا بلا صفٍّ في السجلِّ، **وكلُّها تُحلُّ بأساسِها** ⇒ فهي
       صيغُ عرضٍ لسطحٍ مسجَّلٍ لا أسطحًا مفقودة. ولولا الردُّ إلى الأساسِ
       لعُدَّت «ليست في الملف» وهي فيه ([[nav-view-param-ownership]]). */
    /* ◆ **وصفةُ «متغاير» من المسارِ نفسِه لا من نجاحِ الجسر** — كما يقرؤها
         المُصيِّرُ (`$isVariant = strpbrk($raw,'#?')`) ولوحةُ #١٦ حرفًا: مسارٌ
         بصيغةِ عرضٍ **يبقى متغايرًا وإن لم يُحَلَّ أساسُه**، فربطُ الصفةِ
         بنجاحِ الجسرِ يجعل بندًا واحدًا يُحكَم عليه بحكمَين. */
    $variant = (strpbrk((string) $it['route'], '#?') !== false);
    if (!isset($byRoute[$rt])) {
        $base = preg_replace('~[?#].*$~', '', $rt);
        if ($base !== $rt && isset($byRoute[$base])) { $rt = $base; }
    }
    $scr = isset($byRoute[$rt]) ? $byRoute[$rt] : null;
    $lbl = $norm($it['label_ar']);

    /* س١ — بندٌ حيٌّ بلا سطحٍ مسجَّلٍ على القرصِ = «ما ليس في الملف» */
    if ($scr && (int) $scr['on_disk'] === 1 && $scr['ownership_verdict'] !== 'RETIRE') {
        $S[1]['ok']++;
    } else {
        $S[1]['bad']++;
        if (count($S[1]['ex']) < 8) {
            $S[1]['ex'][] = $it['route'] . ' «' . $it['label_ar'] . '» دور ' . $it['role_id'];
        }
    }

    /* س٢ — الاسمُ المعروضُ على التسميةِ المعتمدة (`canonical_ar` بحالةِ APPROVED)
       ⛔ **وصيغةُ العرضِ خارجَ هذا الحكم — بقرارِ المُصيِّرِ نفسِه** (‏بند ٧):
          `unified_nav.php` يعطي المدخلَ الثاني (`?view=`/`#`) اسمَه هو صراحةً
          (`if ($isVariant) { $name = $it['label_ar']; }`) **ولا يفرض عليه
          المعتمَدَ** — فهو مدخلٌ مقصودٌ وله تسميتُه.
       ⚠ **وقِيس الضررُ لا ظُنَّ** (2026-08-30): طُبِّقت المحاذاةُ على المتغايرِ
          مرّةً فسقط **أحدَ عشرَ رابطًا حيًّا** من التصييرِ (١٬٧٧٦ ⇒ ١٬٧٦٥ موضعًا
          في تسعةِ أدوار)، لأنَّ الأصلَ والمتغايرَ صارا باسمٍ واحدٍ فابتلع حارسُ
          التكرارِ ثانيَهما — فرُدَّت الدفعةُ. ⇒ **حكمٌ يأمر بما يُسقِط روابطَ
          حيّةً حكمٌ على غيرِ مَحلِّه.**
       ◆ **ومقياسُ اللوحةِ #١٦ يستثنيها سلفًا بالنصِّ نفسِه** («و١٠٤ صيغةَ عرضٍ
          تحتفظ بوسمِها بقرارِ المُصيِّرِ نفسِه فلا تُحاسَب») — فهذا **مصالحةُ
          مقياسَين تفرَّقا** لا إرخاءُ أحدِهما ([[counter-parity-two-readers]]).
       ⛔ **والاستثناءُ لا يمتدُّ إلى س٣ وس٤ وس٧**: المُصيِّرُ **يُسري القسمَ
          والرتبةَ على المتغايرِ** ويُبقي له الاسمَ وحدَه — فهويّةُ الشاشةِ
          مسارُها الأساس. */
    $cn = isset($canon[$rt]) ? $canon[$rt] : null;
    if ($variant) { $S[2]['na']++; }
    elseif ($cn && $cn['status'] === 'APPROVED') {
        if ($norm($cn['canonical_ar']) === $lbl) { $S[2]['ok']++; }
        else {
            $S[2]['bad']++; $S[2]['rt'][$rt] = 1;
            if (count($S[2]['ex']) < 8) {
                $S[2]['ex'][] = $it['route'] . ' معروضٌ «' . $it['label_ar'] . '» ومعتمَدٌ «' . $cn['canonical_ar'] . '»';
            }
        }
    } else {
        $S[2]['bad']++; $S[2]['rt'][$rt] = 1;
        if (count($S[2]['ex']) < 8) {
            $S[2]['ex'][] = $it['route'] . ' «' . $it['label_ar'] . '» — '
                          . ($cn ? 'تسميتُه بحالةِ `' . $cn['status'] . '` لا `APPROVED`' : 'لا صفَّ تسميةٍ معتمدة');
        }
    }

    /* س٣ — المجموعةُ المعروضةُ تطابق مجموعةَ التسميةِ المعتمدة */
    $g = $norm($it['group_name']);
    $u = $scr ? $scr['owner_code'] : '';
    if (!$cn || trim((string) $cn['group_name']) === '') { $S[3]['na']++; }
    elseif ($norm($cn['group_name']) === $g) { $S[3]['ok']++; }
    else {
        $S[3]['bad']++; $S[3]['rt'][$rt] = 1;
        if (count($S[3]['ex']) < 8) {
            $S[3]['ex'][] = $it['route'] . ' معروضةٌ «' . $it['group_name'] . '» ومعتمَدةٌ «' . $cn['group_name'] . '»';
        }
    }

    /* س٤ — الترتيبُ: يُجمع الآن ويُحكَم بعدُ **ترتيبيًّا داخلَ كلِّ مجموعةٍ**
       ⛔ ولا يُقارَن `sort_order` بـ`sort_no` عددًا لعدد: المقياسُ **الترتيبُ**
          لا القيمة، فرقمان مختلفان قد يُنتجان الترتيبَ نفسَه. */
    if ($cn && (int) $cn['sort_no'] > 0) {
        $ord[$it['role_id'] . '|' . (int) $it['group_id']][] =
            array((int) $it['sort_order'], (int) $cn['sort_no'], $it['route']);
        $S[4]['ok']++;   /* يُعاد حسمُه بعد الفرزِ الترتيبيّ */
    } else { $S[4]['na']++; }
    /* س٥ — الأبُ والتبويب · **بنصِّ الأمرِ حرفًا**: §٦ *«صحِّحْ علاقاتِ الأبِ
       والتبويبِ — **ولا دمجَ في أبٍ ليس أبَه المعماريّ**»*.
       ⛔ **وكان يُقاس «أبٌ من إدارتِه» — وهو شرطٌ لا وجودَ له في الأمر**، وأنتج
          **١١٥** موضعًا حمراءَ على ١٨ مسارًا كلُّها `ENTITY_TABS_REGISTRY`:
          تبويباتُ **بطاقةِ الكيان**، وهي عابرةٌ للإداراتِ **بالتصميم** (ربحيّةُ
          المشروعِ تبويبٌ ماليٌّ في بطاقةِ مشروعٍ تجاريّة). ⇒ **«معماريّ» ليس
          «إداريًّا»** ([[entity-profile-card-system]]).
       ◆ **والقاعدةُ الحاكمةُ مُنفَّذةً**: الأبُ **مُعلَنٌ في سجلِّ التبويباتِ**
          (`ENTITY_TABS_REGISTRY` أو `HIDDEN_LOG_TAB_IN_PARENT`) · **وله صفٌّ**
          في السجل · **وليس الشاشةَ نفسَها** · **ومبنيٌّ على القرص**.
          ⇒ فالمردودُ **أبٌ مستنبَطٌ من رابطٍ** (`ANCHOR_*` — ٣٢ صفًّا في
          السجلّ) أو أبٌ يتيمٌ أو غيرُ مبنيّ: **وهذه هي «أبٌ ليس أبَه المعماريّ»**.
       ◆ **والعابرُ للإداراتِ يبقى مقيسًا ومُعلَنًا خبرًا** أدناه — فلا يختفي
          رقمٌ تغيَّر معناه، ولا يُقرأ تغييرُ محلِّ القياسِ إخفاءً لمخالفة. */
    if (!$scr) { $S[5]['na']++; }
    elseif ($scr['parent_screen_id'] === '') { $S[5]['ok']++; }
    else {
        $par = isset($byId[$scr['parent_screen_id']]) ? $byId[$scr['parent_screen_id']] : null;
        $err = '';
        if (!$par)                                            { $err = 'أبٌ لا صفَّ له في السجل'; }
        elseif ($scr['parent_screen_id'] === $scr['screen_id']) { $err = 'أبٌ هو الشاشةُ نفسُها'; }
        elseif (!isset($PARENT_DECLARED[$scr['parent_rule']])) {
            $err = 'أبٌ **مستنبَطٌ** لا مُعلَن (`' . ($scr['parent_rule'] !== '' ? $scr['parent_rule'] : 'بلا قاعدة') . '`)';
        } elseif ((int) $par['on_disk'] !== 1)                 { $err = 'أبٌ غيرُ مبنيٍّ على القرص'; }
        if ($err === '') {
            $S[5]['ok']++;
            if ($par['owner_code'] !== $scr['owner_code']) {
                $S[5]['cross']++; $S[5]['crossRt'][$rt] = $scr['owner_code'] . ' ⇐ ' . $par['owner_code'];
            }
        } else {
            $S[5]['bad']++; $S[5]['rt'][$rt] = 1;
            if (count($S[5]['ex']) < 8) { $S[5]['ex'][] = $scr['screen_id'] . ' أبوه ' . $scr['parent_screen_id'] . ' — ' . $err; }
        }
    }

    /* س٦ — الظهورُ بالصلاحيةِ وحارسٌ خادميّ */
    $hasPerm  = trim((string) $it['permission_code']) !== '';
    $hasGuard = $scr && trim((string) $scr['guard_kind']) !== '';
    if ($hasPerm && $hasGuard) { $S[6]['ok']++; }
    else {
        $S[6]['bad']++; $S[6]['rt'][$rt] = 1;
        if (count($S[6]['ex']) < 8) {
            $S[6]['ex'][] = $it['route'] . ($hasPerm ? '' : ' بلا رمزِ صلاحية') . ($hasGuard ? '' : ' بلا حارسٍ خادميّ');
        }
    }

    /* س٧ — مربوطٌ بسجلِّ الملاحةِ المعياريِّ **بمعرِّفٍ لا بمسار** */
    if ($cn && trim((string) $cn['screen_id']) !== '' && $scr
        && $cn['screen_id'] === $scr['screen_id']) { $S[7]['ok']++; }
    else {
        $S[7]['bad']++; $S[7]['rt'][$rt] = 1;
        if (count($S[7]['ex']) < 8) {
            $S[7]['ex'][] = $it['route'] . ' — '
                . (!$cn ? 'لا صفَّ في سجلِّ الملاحةِ المعياريّ'
                   : (trim((string) $cn['screen_id']) === '' ? 'صفُّه بلا `screen_id`'
                      : (!$scr ? 'لا سطحَ في السجلِّ بهذا المسار'
                         : 'معرِّفُه ' . $cn['screen_id'] . ' والسجلُّ ' . $scr['screen_id'])));
        }
    }
}

/* ═══ ②·ب س٤ يُحسم ترتيبيًّا — لا عددًا لعدد ═══════════════════════════
     §٦: *«رتِّبِ الأسطحَ على دورةِ العملِ في الملفِّ لا على تاريخِ الإضافة»*.
     ⇒ المقياسُ **تطابقُ الترتيبِ داخلَ المجموعةِ الواحدة**: يُرتَّب بنودُها
     بالمعروضِ ثمَّ بالمعتمَد، فكلُّ بندٍ اختلف موضعُه بين الترتيبَين يُعدّ
     محتاجًا. ⛔ **ولا يُقارَن الرقمان قيمةً**: `sort_order=10` و`sort_no=3`
     قد ينتجان الموضعَ نفسَه، فمقارنةُ القيمةِ تُنتج حُمرةً كاذبةً كاسحة. */
$S[4]['ok'] = 0; $S[4]['bad'] = 0;
foreach ($ord as $key => $rows) {
    $live = $rows; $spec = $rows;
    usort($live, function ($a, $b) { return $a[0] === $b[0] ? strcmp($a[2], $b[2]) : $a[0] - $b[0]; });
    usort($spec, function ($a, $b) { return $a[1] === $b[1] ? strcmp($a[2], $b[2]) : $a[1] - $b[1]; });
    foreach ($live as $pos => $x) {
        if ($spec[$pos][2] === $x[2]) { $S[4]['ok']++; }
        else {
            $S[4]['bad']++; $S[4]['rt'][$x[2]] = 1;
            if (count($S[4]['ex']) < 8) {
                $S[4]['ex'][] = 'مجموعة ' . $key . ' موضع ' . ($pos + 1)
                              . ': معروضٌ ' . $x[2] . ' والدورةُ تضع ' . $spec[$pos][2];
            }
        }
    }
}

/* ═══ ③ الاختبارُ السالب ═══════════════════════════════════════════════ */
if ($SELF) {
    $fail = 0;
    if ($N < 100) { echo "  X المقامُ الحيُّ $N — يبدو أنَّ القراءةَ لم تتمّ\n"; $fail++; }
    for ($i = 1; $i <= 7; $i++) {
        $sum = $S[$i]['ok'] + $S[$i]['bad'] + $S[$i]['na'];
        if ($sum !== $N) { echo "  X الخطوةُ $i مجموعُها $sum والمقامُ $N\n"; $fail++; }
    }
    /* **الكاسرُ**: بندٌ بمسارٍ لا وجودَ له يجب أن يسقط في س١ وس٧ معًا */
    $probe = strtolower('zzq/absent_route_probe.php');
    if (isset($byRoute[$probe])) { echo "  X مسارٌ وهميٌّ وُجد في السجلّ\n"; $fail++; }
    /* ولو كان `$byRoute` فارغًا لسقط كلُّ شيءٍ ومرَّ الفحصُ أخضرَ كاذبًا */
    if (count($byRoute) < 100) { echo '  X جسرُ المساراتِ ' . count($byRoute) . " صفًّا — مصفاةٌ عمياء\n"; $fail++; }
    /* ⛔ **وحكمُ س٥ الجديدُ يجب أن يكون له ما يمسكه** — وإلّا فقاعدةٌ بلا صيد:
       سجلُّ الأسطحِ يحمل **٣٢ أبًا مستنبَطًا** (`ANCHOR_*`)، فلو صار أحدُها
       بندًا حيًّا لسقط في س٥. ولو كانت القائمةُ فارغةً لكان الأخضرُ بلا معنى. */
    $anch = (int) $one("SELECT COUNT(*) FROM repair01_screen_registry
                         WHERE parent_screen_id <> '' AND parent_rule LIKE 'ANCHOR%'");
    if ($anch === 0) { echo "  X لا أبَ مستنبَطًا في السجلِّ ألبتّة — حكمُ س٥ بلا ما يمسكه\n"; $fail++; }
    else { echo "  ◆ حكمُ س٥ له ما يمسكه: **$anch** أبًا مستنبَطًا (`ANCHOR_*`) في السجل\n"; }
    echo $fail ? "\nX الفحصُ الذاتيُّ سقط بـ$fail\n"
               : "\n🟢 الفحصُ الذاتيُّ تامٌّ — سبعُ خطواتٍ مجموعُها المقامُ نفسُه، والجسرُ ليس أعمى\n";
    exit($fail ? 1 : 0);
}

/* ═══ ③·أ **إثباتُ الحاجبِ بحركةِ عدّاد** — §٥ القاعدة ٣ ════════════════════
   ⛔ **وحاجبٌ لم تتحرَّكْ حالتُه ليس مُثبَتًا**: يُكسر أبُ سطحٍ حيٍّ واحدٍ داخلَ
   **معاملةٍ تُلغى**، فيُقاس س٥ فيرتفع بمقدارِ مواضعِ ذلك المسار، ثمَّ تُلغى
   المعاملةُ فيعود. ⛔ **ولا كتابةَ تبقى**: `ROLLBACK` لا حذفٌ يدويّ. */
if (in_array('--prove-s5', $argv, true)) {
    $base = $S[5]['bad'];
    $victim = null;
    foreach ($items as $it) {
        $k = strtolower(trim((string) $it['route'], '/'));
        if (!isset($byRoute[$k])) { $k = preg_replace('~[?#].*$~', '', $k); }
        if (isset($byRoute[$k]) && $byRoute[$k]['parent_screen_id'] !== '') { $victim = $byRoute[$k]; break; }
    }
    if (!$victim) { exit("  X لا سطحَ حيًّا بأبٍ — لا يمكن إثباتُ الحكم\n"); }
    $pos = 0;
    foreach ($items as $it) {
        $k = strtolower(trim((string) $it['route'], '/'));
        if (!isset($byRoute[$k])) { $k = preg_replace('~[?#].*$~', '', $k); }
        if (isset($byRoute[$k]) && $byRoute[$k]['screen_id'] === $victim['screen_id']) { $pos++; }
    }
    echo "\n═══ إثباتُ حكمِ س٥ بحركةِ عدّاد ═══\n";
    printf("  الأساسُ: س٥ يحتاج **%d** · والضحيّةُ `%s` (%s) بـ**%d** موضعًا حيًّا\n",
           $base, $victim['screen_id'], $victim['route'], $pos);
    $conn->query('START TRANSACTION');
    $conn->query("UPDATE repair01_screen_registry SET parent_screen_id = 'SCR-zzq99'
                   WHERE screen_id = '" . $conn->real_escape_string($victim['screen_id']) . "'");
    /* يُعاد الحكمُ وحدَه — بالقراءةِ نفسِها لا بأداةٍ ثانيةٍ تتفرّق */
    $byId2 = array();
    $rr = $conn->query("SELECT screen_id, owner_code, parent_screen_id, parent_rule, on_disk FROM repair01_screen_registry");
    while ($rr && ($x = $rr->fetch_assoc())) { $byId2[$x['screen_id']] = $x; }
    $byRoute2 = array();
    $rr = $conn->query("SELECT screen_id, route, owner_code, parent_screen_id, parent_rule, on_disk
                          FROM repair01_screen_registry WHERE route <> ''");
    while ($rr && ($x = $rr->fetch_assoc())) { $byRoute2[strtolower(trim($x['route'], '/'))] = $x; }
    $bad2 = 0;
    foreach ($items as $it) {
        $k = strtolower(trim((string) $it['route'], '/'));
        if (!isset($byRoute2[$k])) { $k = preg_replace('~[?#].*$~', '', $k); }
        if (!isset($byRoute2[$k])) { continue; }
        $s = $byRoute2[$k];
        if ($s['parent_screen_id'] === '') { continue; }
        $p = isset($byId2[$s['parent_screen_id']]) ? $byId2[$s['parent_screen_id']] : null;
        if (!$p || $s['parent_screen_id'] === $s['screen_id']
            || !isset($PARENT_DECLARED[$s['parent_rule']]) || (int) $p['on_disk'] !== 1) { $bad2++; }
    }
    $conn->query('ROLLBACK');
    $after = (int) $one("SELECT COUNT(*) FROM repair01_screen_registry
                          WHERE screen_id = '" . $conn->real_escape_string($victim['screen_id']) . "'
                            AND parent_screen_id = '" . $conn->real_escape_string($victim['parent_screen_id']) . "'");
    printf("  بكسرِ الأبِ: س٥ يحتاج **%d** (‏الفرقُ %+d · والمتوقَّعُ %+d)\n", $bad2, $bad2 - $base, $pos);
    printf("  بعدَ `ROLLBACK`: صفُّ الضحيّةِ عاد إلى أبِه الأصليِّ = %s\n", $after === 1 ? 'نعم' : '⛔ لا');
    $good = ($bad2 - $base === $pos) && $after === 1;
    echo $good ? "\n🟢 **الحكمُ مُثبَتٌ**: العدّادُ تحرَّك بكسرِ أبٍ واحدٍ ثمَّ عاد — وليس أخضرَ كاذبًا\n"
               : "\nX **الحكمُ لم يُثبَت** — لم يتحرَّكِ العدّادُ كما يجب أو لم يعُدِ الصفّ\n";
    exit($good ? 0 : 1);
}

/* ═══ ③·ب المُصيَّرُ مقابلَ **الملفِّ التصميميّ** — وهو سؤالُ §٥·٦ حرفًا ══════
   ⛔ **والخطواتُ السبعُ أعلاه تقيس المخزنَ لا المُصيَّر** — وهذا عطبٌ مقيسٌ لا
      تقدير: `nav_items.group_id ⟶ link_groups.name` **لا يُصيَّر أصلًا** حين
      يكون للمسارِ صفٌّ في `nav_canonical` أو إعلانٌ في `gov_target_nav`؛
      و`includes/unified_nav.php` يأخذ الاسمَ من `canonical_ar` متى كان
      `APPROVED` (‏سطر ٩٤٢..٩٤٥) والمجموعةَ من الإعلانِ ثمَّ المعتمَد.
      ⇒ **فقياسُ المخزنِ يُنتج فرقًا لا يراه مستخدم**.
   ⛔ **ومقارنةُ المُصيَّرِ بـ`nav_canonical` دَورٌ لا قياس**: المُصيِّرُ يقرأ منه،
      فالمطابقةُ تُصبح حتميّةً بالبناءِ لا مقيسة (‏قيست: **٢٠٩٦/٢٠٩٦**).
   ◆ **والسلطةُ في §٥·٦ هي «الملف»** — `repair01_requirements` — والجسرُ إليه
      **بالمعرِّفِ لا بالاسم**: المسارُ ⇐ `screen_id` ⇐ `repair01_target_universe`
      (`MATCHED`) ⇐ `requirement_id`. ⛔ **وما لا جسرَ له لا يُحكَم عليه**:
      يُعدُّ `NO_BRIDGE` **محجوبًا على المصالحة**، لا مطابقًا ولا مخالفًا.
   ◆ **و`$specGroup`/`$specOrder` كانتا تُحمَّلان ولا تُستعملان** — الملفُّ
      يُقرأ ثمَّ يُهمَل، والحكمُ يقع على المصفوفةِ الوسيطة. */
$scr2req = array();
$r = $q("SELECT screen_id, requirement_id FROM repair01_target_universe
          WHERE verdict = 'MATCHED' AND screen_id <> '' AND requirement_id <> ''");
while ($x = $r->fetch_row()) { $scr2req[$x[0]] = $x[1]; }
$specByReq = array();
$r = $q("SELECT requirement_id, unit, group_name, surface, seq FROM repair01_requirements");
while ($x = $r->fetch_assoc()) { $specByReq[$x['requirement_id']] = $x; }
$declByRole = array();
$r = @$conn->query("SELECT role_id, route, group_ar, group_no, item_no FROM gov_target_nav");
if ($r) {
    while ($x = $r->fetch_assoc()) {
        if (strncmp((string) $x['route'], 'GAP:', 4) === 0) { continue; }
        $k = strtolower(trim(preg_replace('~[?#].*$~', '', (string) $x['route']), '/'));
        if ($k === '') { continue; }
        $declByRole[(int) $x['role_id']][$k] = array('g' => (string) $x['group_ar'],
            'o' => ((int) $x['group_no'] * 1000) + (int) $x['item_no']);
    }
}
/* ⛔ **أمرُ SIDEBAR_RENDER_FIX §٤·٥ — «المُصيَّرُ» من الشجرةِ فعلًا لا من
   محاكاةِ أسبقيّةٍ**: المحاكاةُ السابقةُ (إعلانٌ⇐معتمَدٌ⇐إرثٌ) أثبتت التجربةُ
   ⑧ خطأَها — `nav_canonical.group_name` عمودٌ ميّتٌ تصييرًا ورأسُ غيرِ
   المُعلَنِ من `nav_route_group` (التجربة ⑦). فتُصيَّر شجرةُ كلِّ دورٍ
   بعمليّةٍ نقيّةٍ (`tools/lib/render_role_cli.php` — غلافُ
   `uxp_render_role_html`) ويُقرأ رأسُ كلِّ بندٍ منها حرفًا.
   «لا يُقاس سطحٌ بما في جدولِه — بل بما يظهر للمستخدمِ في جلستِه.» */
$roleUid = array();
$r = $q("SELECT CAST(u.role AS UNSIGNED) rid, MIN(u.id) uid FROM users u
          WHERE u.company_id = 4 GROUP BY rid");
while ($x = $r->fetch_assoc()) { $roleUid[(int) $x['rid']] = (int) $x['uid']; }
$rendered = array();   // rid => base => group
$ridsSeen = array();
foreach ($items as $it) { $ridsSeen[(int) $it['role_id']] = 1; }
foreach (array_keys($ridsSeen) as $rid0) {
    if (!isset($roleUid[$rid0])) { continue; }
    $o0 = array();
    @exec('"' . PHP_BINARY . '" ' . escapeshellarg($ROOT . '/tools/lib/render_role_cli.php')
        . ' ' . $rid0 . ' ' . $roleUid[$rid0] . ' 2>NUL', $o0);
    $j0 = json_decode(implode('', $o0), true);
    if (!is_array($j0)) { continue; }
    foreach ($j0['positions'] as $p0) {
        $b0 = strtolower(preg_replace('~[?#].*$~', '', preg_replace('~^(\.\./)+~', '', trim((string) $p0['h']))));
        if ($b0 !== '' && !isset($rendered[$rid0][$b0])) {
            $rendered[$rid0][$b0] = array('g' => (string) $p0['g'],
                                          's' => isset($p0['s']) ? (string) $p0['s'] : '');
        }
    }
}
$F = array('ok' => 0, 'bad' => 0, 'nobridge' => 0, 'nogroup' => 0, 'notrendered' => 0);
$Frt = array(); $Fex = array();
foreach ($items as $it) {
    $rid = (int) $it['role_id'];
    $b   = strtolower(trim(preg_replace('~[?#].*$~', '', (string) $it['route']), '/'));
    $scr = isset($byRoute[$b]) ? $byRoute[$b] : null;
    $rq  = ($scr && isset($scr2req[$scr['screen_id']])) ? $scr2req[$scr['screen_id']] : '';
    if ($rq === '' || !isset($specByReq[$rq])) { $F['nobridge']++; continue; }
    $sp = $specByReq[$rq];
    if (trim((string) $sp['group_name']) === '') { $F['nogroup']++; continue; }
    if (!isset($rendered[$rid][$b])) { $F['notrendered']++; continue; }
    $shownG = $rendered[$rid][$b]['g'];
    $shownS = $rendered[$rid][$b]['s'];
    /* مجموعةُ الملفِّ قد تُصيَّر عنوانًا فرعيًّا داخل رأسِ الطيِّ او رأسًا —
       والمطابقةُ تصحُّ على ايِّهما ظهرت فيه فعلًا */
    $spn = $norm($sp['group_name']);
    if ($spn === $norm($shownS) || $spn === $norm($shownG)) { $F['ok']++; }
    else {
        $F['bad']++; $Frt[$b] = 1;
        if (count($Fex) < 10) {
            $Fex[] = 'دور ' . $rid . ' · ' . $b . ' مُصيَّرٌ «' . $shownG . ($shownS !== '' ? ' ▸ ' . $shownS : '')
                   . '» · والملفُّ «' . $sp['group_name'] . '» [' . $sp['unit'] . ']';
        }
    }
}

/* ═══ ④ العرض ═══════════════════════════════════════════════════════════ */
$TITLE = array(
    1 => 'عطِّل ما ليس في الملف بعذرٍ مكتوبٍ ومرجع',
    2 => 'صحِّح الأسماء المعروضة على التسمية المعتمدة',
    3 => 'صحِّح المجموعات لتطابق مجموعات الملف',
    4 => 'رتِّب الأسطح على دورة العمل لا على تاريخ الإضافة',
    5 => 'صحِّح علاقات الأب والتبويب',
    6 => 'صحِّح الظهور بالصلاحية — ولا تُضحَّى لأجل ترتيب',
    7 => 'اربط الكل بسجل الملاحة المعياري',
);
$w4 = (int) $one("SELECT COUNT(*) FROM repair01_w4_sidebar");
echo "\n═══ `RPR-02` §٦ — السايدبارُ قبلَ الشاشات · قياسُ السبع ═══\n";
printf("  اللقطة %s · **بنودٌ حيّةٌ مُصيَّرةٌ %d** · وقاست الموجةُ الرابعةُ %d سطحًا (نطاقُها لا السايدبار)\n\n",
       $sid, $N, $w4);
printf("  %-3s %-40s %8s %8s %8s %10s\n", '#', 'الخطوة', 'مستقيم', 'يحتاج', 'لا ينطبق', 'مسارٌ فريد');
echo '  ' . str_repeat('─', 78) . "\n";
for ($i = 1; $i <= 7; $i++) {
    printf("  س%-2d %-40s %8d %8d %8d %10d
", $i, mb_substr($TITLE[$i], 0, 40),
           $S[$i]['ok'], $S[$i]['bad'], $S[$i]['na'], count($S[$i]['rt']));
}
$needTot = 0;
for ($i = 1; $i <= 7; $i++) { $needTot += $S[$i]['bad']; }
printf("\n  **مواضعُ تحتاج تصحيحًا في الخطواتِ السبع مجتمعةً: %d**\n", $needTot);
/* ── خبرٌ خارجَ الحكم: الأبُ المعماريُّ العابرُ للإدارات (بطاقةُ الكيان) ── */
printf("\n  ◆ **خبرٌ (خارجَ حكمِ س٥)**: أبٌ معماريٌّ **من إدارةٍ أخرى** — %d موضعًا على %d مسارًا.\n",
       $S[5]['cross'], count($S[5]['crossRt']));
echo "     وهي تبويباتُ **بطاقةِ الكيان** وعبورُها الإداراتِ **بالتصميم** — والأمرُ يقول\n";
echo "     «ولا دمجَ في أبٍ ليس أبَه **المعماريّ**» ⛔ **ولا يشترط إدارتَه**.\n";
$ci = 0;
foreach ($S[5]['crossRt'] as $k => $v) { echo "       · $k ($v)\n"; if (++$ci >= 6) { break; } }

if ($LIST) {
    for ($i = 1; $i <= 7; $i++) {
        if (!$S[$i]['ex']) { continue; }
        echo "\n  ── س$i · شواهدُ ──\n";
        foreach ($S[$i]['ex'] as $x) { echo "     · $x\n"; }
    }
}
/* ── المُصيَّرُ مقابلَ الملفّ — سؤالُ §٥·٦ حرفًا ── */
$Fden = $F['ok'] + $F['bad'];
echo "\n  ══ المُصيَّرُ مقابلَ **الملفِّ التصميميّ** — وهو سؤالُ §٥·٦ حرفًا ══\n";
printf("     مطابقٌ **%d** · مخالفٌ **%d** (مساراتٌ فريدةٌ %d) · والمقامُ المقارَنُ %d\n",
       $F['ok'], $F['bad'], count($Frt), $Fden);
printf("     ⛔ محجوبٌ على المصالحة `NO_BRIDGE` **%d** — لا سطحَ مطابَقًا فلا متطلبَ يُقاس عليه\n", $F['nobridge']);
printf("     ◆ والملفُّ بلا مجموعةٍ لهذا المتطلب: %d · وصفٌّ نشطٌ لا يُصيَّر: %d\n",
       $F['nogroup'], $F['notrendered']);
echo "     ◆ **المُصيَّرُ هنا من الشجرةِ فعلًا** (عمليّةٌ نقيّةٌ لكلِّ دور — أمرُ SIDEBAR_RENDER_FIX §٤·٥)\n";
/* §٤·٦ — المقاماتُ الثلاثةُ بمصدرِ كلٍّ: «عملًا» صفوفُ المخازنِ (بوسمِها) ·
   «حاكمًا» المطابقةُ اعلاه من الشجرةِ · «تغطيةً» كم من الملفِّ ظاهرٌ فعلًا */
$covSeen = array();
foreach ($rendered as $rid0 => $bs) {
    foreach ($bs as $b0 => $g0) {
        $scr0 = isset($byRoute[$b0]) ? $byRoute[$b0] : null;
        $rq0 = ($scr0 && isset($scr2req[$scr0['screen_id']])) ? $scr2req[$scr0['screen_id']] : '';
        if ($rq0 !== '') { $covSeen[$rq0] = 1; }
    }
}
$covDen = 0; $covNum = 0;
foreach ($specByReq as $rq0 => $sp0) {
    if (trim((string) $sp0['group_name']) === '') { continue; }
    $covDen++;
    if (isset($covSeen[$rq0])) { $covNum++; }
}
printf("     ◆ **المقاماتُ الثلاثة (§٤·٦)**: حاكمًا %s%% (من الشجرة) · تغطيةً %s%% (%d من %d متطلبَ ملفٍّ ظاهرٌ لدورٍ فأكثر) · وعملًا يبقى عدَّ صفوفِ مخزنٍ بوسمِه الصريحِ لا وصفًا للظهور\n",
       $Fden ? round($F['ok'] * 100 / $Fden, 1) : 0,
       $covDen ? round($covNum * 100 / $covDen, 1) : 0, $covNum, $covDen);
if ($Fex) {
    echo "\n     ── شواهدُ ──\n";
    foreach ($Fex as $x) { echo "       · $x\n"; }
}
echo "\n  ⛔ **والخطواتُ السبعُ أعلاه تقيس المخزنَ لا المُصيَّر** — و`link_groups`\n";
echo "     لا تُصيَّر أصلًا لمن له صفٌّ معتمَدٌ أو إعلان (‏إرثٌ مُصيَّر: "
   . $Fsrc['LEGACY'] . " من " . $Fden . "). ⇒ **فرقُ المخزنِ لا يراه مستخدم.**\n";
echo "  ⛔ **ومقارنةُ المُصيَّرِ بـ`nav_canonical` دَورٌ لا قياس** — المُصيِّرُ يقرأ منه.\n";
echo "\n  ⛔ **وهذا يقيس ولا يصحّح** — والتصحيحُ يمسّ ملاحةً حيّةً يراها المستخدمون،\n";
echo "     فيُبنى على هذا السجلِّ بعد قراءتِه لا قبلَها.\n";

if ($MD) {
    $o  = "# `RPR-02` §٦ — السايدبارُ قبلَ الشاشات · قياسُ السبع\n\n";
    $o .= "> ⛔ **مولَّدٌ من تشغيلٍ حيّ**: `php tools/" . basename(__FILE__) . " --md` · اللقطة `" . $sid . "`\n\n";
    $o .= "المقامُ **بنودٌ حيّةٌ مُصيَّرة " . $N . "** — لا بيانٌ مُعلَن. وقاست الموجةُ الرابعةُ **"
        . $w4 . "** سطحًا هي نطاقُها، ⛔ **ولا يُقرأ ذلك تغطيةً للسايدبار كلِّه**.\n\n";
    $o .= "| # | الخطوة | مستقيم | يحتاج تصحيحًا | لا ينطبق | مسارٌ فريد |\n|---|---|---:|---:|---:|---:|\n";
    for ($i = 1; $i <= 7; $i++) {
        $o .= '| س' . $i . ' | ' . $TITLE[$i] . ' | ' . $S[$i]['ok'] . ' | **' . $S[$i]['bad'] . '** | '
            . $S[$i]['na'] . ' | ' . count($S[$i]['rt']) . " |\n";
    }
    $rtTot = array();
    for ($i = 1; $i <= 7; $i++) { foreach (array_keys($S[$i]['rt']) as $k) { $rtTot[$k] = 1; } }
    $o .= "\n**مواضعُ تحتاج تصحيحًا مجتمعةً: " . $needTot . "** — و**مساراتٌ فريدةٌ تحتاج عملًا: "
        . count($rtTot) . "**.\n";
    $o .= "⛔ **والعمودان لا يُخلطان**: البندُ يتكرَّر بعددِ الأدوارِ التي تراه، "
        . "فـ«موضعٌ» ليس «شاشةً» — والعملُ يُقدَّر بالمساراتِ الفريدةِ لا بالمواضع.\n\n";
    $o .= "⛔ **وسبعةُ أحكامٍ لا حكمٌ واحد**: بندٌ قد يستقيم اسمًا ويختلَّ مجموعةً،\n";
    $o .= "وجمعُها في «مطابق/غير مطابق» يُخفي أيَّ الخطواتِ تحتاج عملًا.\n";
    file_put_contents($ROOT . '/docs/REPAIR01_20260823/RPR02_S6_SIDEBAR.md', $o);
    echo "\n✔ كُتب docs/REPAIR01_20260823/RPR02_S6_SIDEBAR.md\n";
}
