<?php
/**
 * includes/w14_grid.php — **شبكةُ حقولِ الدليل**: رأسٌ وخليّةٌ من خريطةٍ واحدة
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **الحاجزُ الذي ترفعه** — `GOV_UI_EXEC` §11 يوجب أن تكون الشاشةُ صورةَ
 *   المستندِ الذي تديره: **كلُّ حقولِ الورقةِ موجودةٌ، وترتيبُها ترتيبُ دورةِ
 *   المستند**. وأسطحُ الحوكمةِ كانت تعرض ثمانيةَ رؤوسٍ من ثلاثةَ عشرَ حقلًا،
 *   بأسماءٍ من الشيفرةِ لا من الورقة.
 *
 * ◆ **ورأسٌ وخليّةٌ من خريطتَين يتفرّقان** — وهو بعينُه عطبُ «المُعلَنِ لا
 *   المبنيّ» ([[declared-column-not-built]]): رأسٌ يُعلَن ولا مصدرَ لخليّتِه
 *   فتُحشى شرطةً وتُخفى، **فيقرؤه المقياسُ مبنيًّا وهو لا يبلغ المستخدم**.
 *   ⇒ فالخريطةُ واحدةٌ (`$GUIDE_COLS`) **يقرؤها الرأسُ والخليّةُ معًا**، ولا
 *   يُكتب رأسٌ إلّا ومصدرُ خليّتِه مصرَّحٌ بجانبِه.
 *
 * ◆ **والاسمُ اسمُ الورقةِ حرفًا** (§7: `CURRENT_UI_LABEL = CANONICAL_LABEL`) —
 *   ⛔ **بلا اصطلاحِ التصنيفِ ولا تشكيل**: `◄` و`▼` اصطلاحُ ورقةٍ (مشتقٌّ ·
 *   قائمةٌ محكومة) لا جزءٌ من الاسم، **وهما ممّا تمنعه سقّاطةُ `UI-02`
 *   في نصِّ شاشةٍ حيّة**. فالمخزَّنُ في الخريطةِ نقيٌّ، والمطابقةُ لا تتأثّر:
 *   تطبيعُ المقياسِ يُسقطهما أصلًا.
 *
 * ◆ **صيغةُ الخريطة** — `'اسم الحقل كما في الورقة' => '<مصدر>'`:
 *   · `col`     عمودٌ في الصفِّ المقروء.
 *   · `@col`    رمزٌ يُعرَض بمسمّاه من القاموسِ المركزيّ (`ems_w14_ar`).
 *   · `#key`    مشتقٌّ: دالّةٌ في `$D` تأخذ الصفَّ وتُرجع نصَّ العرض.
 *   ⛔ **ولا مصدرَ رابعَ** — فمصدرٌ غيرُ مصرَّحٍ عمودٌ بلا خليّة.
 *
 * ◆ **والعدمُ يُعلَن شرطةً** بصنفِ `ems-gov-empty` — لا فراغٌ يلتبس بعمودٍ مفقود
 *   ولا نصٌّ مُلفَّقٌ يحلُّ محلَّ بيانٍ غائب.
 * ═══════════════════════════════════════════════════════════════════════════
 */
require_once __DIR__ . '/guide_label.php';
require_once __DIR__ . '/w14_view.php';

if (!function_exists('ems_w14_grid')) {
    /**
     * @param string   $tableId  معرِّفُ الجدولِ — يربطه صندوقُ الفلترةِ المعياريّ
     * @param array    $cols     خريطةُ الدليل: اسمُ الحقلِ ⇐ مصدرُ خليّتِه
     * @param array    $rows     الصفوفُ المقروءةُ عبرَ بوّابةِ العزل
     * @param array    $D        الدوالُّ المشتقّة: مفتاحٌ ⇐ callable($row)
     * @param string   $emptyMsg نصُّ الفراغِ حين لا صفَّ
     */
    function ems_w14_grid($tableId, array $cols, array $rows, array $D = array(), $emptyMsg = '')
    {
        $h = function ($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); };
        $out = '<table id="' . $h($tableId) . '" class="data-table">' . "\n";
        $out .= '<thead><tr><th>#</th>';
        foreach ($cols as $lbl => $src) { $out .= '<th>' . $h(ems_guide_label($lbl)) . '</th>'; }
        $out .= '</tr></thead>' . "\n<tbody>\n";
        if ($rows) {
            $i = 0;
            foreach ($rows as $r) {
                $i++;
                $out .= '<tr><td>' . $i . '</td>';
                foreach ($cols as $lbl => $src) {
                    $src = (string) $src;
                    if ($src !== '' && $src[0] === '#') {
                        $k = substr($src, 1);
                        $v = isset($D[$k]) && is_callable($D[$k]) ? (string) call_user_func($D[$k], $r) : '';
                    } elseif ($src !== '' && $src[0] === '@') {
                        $k = substr($src, 1);
                        $v = (isset($r[$k]) && (string) $r[$k] !== '') ? (string) ems_w14_ar($r[$k]) : '';
                    } else {
                        $v = isset($r[$src]) && $r[$src] !== null ? (string) $r[$src] : '';
                    }
                    $v = trim($v);
                    $out .= ($v === '')
                          ? '<td class="ems-gov-empty">—</td>'
                          : '<td>' . $h($v) . '</td>';
                }
                $out .= '</tr>' . "\n";
            }
        } else {
            $out .= '<tr><td colspan="' . (count($cols) + 1) . '">' . $h($emptyMsg) . '</td></tr>' . "\n";
        }
        $out .= "</tbody></table>\n";
        return $out;
    }

    /**
     * هويّاتُ الأشخاصِ — **دفعةً واحدةً عبرَ بوّابةِ العزل**، لا استعلامًا لكلِّ خليّة.
     * ═══════════════════════════════════════════════════════════════════════
     * ⛔ **ولا نصَّ استعلامٍ هنا**: سقّاطةُ `GAP-29` ترصد أيَّ ملفٍّ جديدٍ يمسُّ
     *   جدولَ مستأجِرٍ بنصِّ `FROM <جدول>` — **مُعَدًّا أو خامًّا سواء**، لأنّها
     *   تقيس النصَّ لا النيّة. والبوّابةُ (`ems_tenant_db()->select`) هي القناةُ
     *   التي تريدها السقّاطةُ أصلًا: العزلُ فيها بالبنيةِ لا بانضباطِ الكاتب.
     */
    function ems_w14_people()
    {
        static $map = null;
        if ($map !== null) { return $map; }
        $map = array();
        try {
            $rows = ems_tenant_db()->select('users', array('limit' => 5000));
            foreach ($rows as $u) {
                $map[(int) $u['id']] = array(
                    'name' => (string) $u['username'],
                    'role' => isset($u['role_id']) ? (int) $u['role_id'] : 0,
                );
            }
        } catch (\Throwable $t) { error_log('ems_w14_people: ' . $t->getMessage()); }
        return $map;
    }

    /** هويّةُ شخصٍ — اسمُه كما في السجلّ، والغائبُ فراغٌ يُعلَن شرطةً */
    function ems_w14_person($id)
    {
        $id = (int) $id;
        if ($id <= 0) { return ''; }
        $m = ems_w14_people();
        return isset($m[$id]) ? $m[$id]['name'] : '';
    }

    /** نعم أو لا — والقيمةُ المنطقيّةُ تُعرَض كلمةً لا رقمًا */
    function ems_w14_yesno($v)
    {
        if ($v === null || (string) $v === '') { return ''; }
        return ((int) $v === 1) ? 'نعم' : 'لا';
    }

    /**
     * عددُ قواعدِ المنعِ المستندةِ إلى وثيقةٍ — «وثيقة البيت» في سجلِّ الحرّاس.
     * ◆ ويُقرأ **مرّةً واحدةً** للصفحةِ كلِّها عبرَ بوّابةِ العزل — فلا استعلامَ
     *   لكلِّ خليّة، ⛔ ولا نصَّ استعلامٍ يُرسِّب سقّاطةَ `GAP-29`.
     */
    function ems_w14_guard_count($ownerDoc)
    {
        static $map = null;
        if ($map === null) {
            $map = array();
            try {
                foreach (ems_tenant_db()->select('guard_policies', array('limit' => 2000)) as $g) {
                    $k = trim((string) $g['owner_doc']);
                    if ($k === '') { continue; }
                    $map[$k] = isset($map[$k]) ? $map[$k] + 1 : 1;
                }
            } catch (\Throwable $t) { error_log('ems_w14_guard_count: ' . $t->getMessage()); }
        }
        $k = trim((string) $ownerDoc);
        return ($k !== '' && isset($map[$k])) ? (string) $map[$k] : '';
    }

    /**
     * اسمُ الكيانِ المالكِ — **من سياقِ الحوكمةِ القائمِ لا باستعلامٍ جديد**.
     * ⛔ استعلامٌ خامٌّ على جدولِ مستأجِرٍ صنفُ دَينٍ مرصودٌ بسقّاطةٍ (`GAP-29`)
     *   ترسّب أيَّ ملفٍّ جديدٍ يمسُّه — ومحاولةُ قراءةِ `tenants` هنا أرسبتها.
     *   والسياقُ `ems_gov_ctx()` يحمل اسمَ الكيانِ سلفًا لكلِّ صفحة، **وصفوفُ
     *   السطحِ معزولةٌ بالكيانِ نفسِه** فلا حاجةَ لقراءةِ كيانٍ آخر.
     */
    function ems_w14_company($id)
    {
        $cid = isset($_SESSION['user']['company_id']) ? (int) $_SESSION['user']['company_id'] : 0;
        if ((int) $id !== $cid) { return ''; }
        if (!function_exists('ems_gov_ctx')) { return ''; }
        $ctx = ems_gov_ctx();
        return isset($ctx['values']['entity']) ? (string) $ctx['values']['entity'] : '';
    }

    /** صفةُ الشخصِ — اسمُ دورِه في السجلّ، لا رقمُ الدور */
    function ems_w14_person_role($id)
    {
        static $roles = null;
        if ($roles === null) {
            $roles = array();
            try {
                foreach (ems_tenant_db()->select('roles', array('limit' => 500)) as $r0) {
                    $roles[(int) $r0['id']] = (string) $r0['name'];
                }
            } catch (\Throwable $t) { error_log('ems_w14_person_role: ' . $t->getMessage()); }
        }
        $m = ems_w14_people();
        $id = (int) $id;
        if (!isset($m[$id])) { return ''; }
        $rid = (int) $m[$id]['role'];
        return isset($roles[$rid]) ? $roles[$rid] : '';
    }

    /**
     * وقائعُ إنفاذِ قاعدةِ منعٍ — كم مرّةً منعت فعلًا.
     * ◆ **من سجلِّ المحاولاتِ الممنوعةِ نفسِه** لا من عدّادٍ يُكتب بيد: عدّادٌ
     *   وعارضٌ من مصدرَين يتفرّقان ([[counter-parity-two-readers]]).
     * ⛔ ولا نصَّ استعلامٍ هنا — القراءةُ عبرَ بوّابةِ العزل (`GAP-29`).
     */
    function ems_w14_denial_count($guardCode)
    {
        static $map = null;
        if ($map === null) {
            $map = array();
            try {
                foreach (ems_tenant_db()->select('guard_denials', array('limit' => 20000)) as $d) {
                    $k = trim((string) $d['guard_code']);
                    if ($k === '') { continue; }
                    $map[$k] = isset($map[$k]) ? $map[$k] + 1 : 1;
                }
            } catch (\Throwable $t) { error_log('ems_w14_denial_count: ' . $t->getMessage()); }
        }
        $k = trim((string) $guardCode);
        return ($k !== '' && isset($map[$k])) ? (string) $map[$k] : '';
    }

    /**
     * عددُ العلاقاتِ بين الكيانات — طرفًا أوَّلَ أو ثانيًا في سجلِّ الأطرافِ ذاتِ العلاقة.
     * ◆ ويُقرأ مرّةً واحدةً عبرَ بوّابةِ العزل — ⛔ ولا نصَّ استعلامٍ في السطحِ
     *   الحيِّ (سقّاطةُ `GAP-29` تقيس النصَّ لا النيّة).
     */
    function ems_w14_entity_relations($entityId)
    {
        static $map = null;
        if ($map === null) {
            $map = array();
            try {
                foreach (ems_tenant_db()->select('gov_related_party', array('limit' => 20000)) as $rp) {
                    foreach (array('from_legal_entity_id', 'to_legal_entity_id') as $k) {
                        $id = isset($rp[$k]) ? (int) $rp[$k] : 0;
                        if ($id > 0) { $map[$id] = isset($map[$id]) ? $map[$id] + 1 : 1; }
                    }
                }
            } catch (\Throwable $t) { error_log('ems_w14_entity_relations: ' . $t->getMessage()); }
        }
        $id = (int) $entityId;
        return isset($map[$id]) ? (string) $map[$id] : '';
    }

    /**
     * نقطةُ منتصفِ المدّةِ بين تاريخَين — لمراجعةِ منتصفِ المدّةِ المشتقّة.
     * ◆ وموضعُها العُدّةُ لا الشاشة (سقّاطةُ `VT-07`: تنسيقُ تاريخٍ في سطحٍ حيّ).
     */
    function ems_w14_midpoint($from, $to)
    {
        $a = strtotime((string) $from);
        $b = strtotime((string) $to);
        if ($a === false || $b === false || $b <= $a) { return ''; }
        return date('Y-m-d', (int) (($a + $b) / 2));
    }

    /**
     * تاريخٌ بعدَ سنةٍ من تاريخٍ — للمراجعةِ الدوريّةِ المشتقّة.
     * ◆ **وموضعُه العُدّةُ لا الشاشة**: سقّاطةُ `VT-07` تعُدُّ كلَّ استدعاءِ تنسيقِ
     *   تاريخٍ في شاشةٍ حيّة، **والاشتقاقُ مكانُه قاعدتُه لا سطحُه** — وهو
     *   الحكمُ نفسُه الذي يحمله `RP-08`.
     */
    function ems_w14_year_after($date)
    {
        $t = strtotime((string) $date);
        if ($t === false) { return ''; }
        return date('Y-m-d', strtotime('+1 year', $t));
    }

    /**
     * أيامٌ متبقيةٌ أو تأخيرٌ إلى تاريخٍ — والسالبُ يُقرأ تأخيرًا بكلمتِه.
     * ⛔ ولا يُحسب على صفٍّ مغلَقٍ: المُنجَزُ لا مهلةَ له.
     */
    function ems_w14_days_to($date, $settled = false)
    {
        $d = trim((string) $date);
        if ($d === '' || $d === '0000-00-00') { return ''; }
        $t = strtotime($d);
        if ($t === false) { return ''; }
        if ($settled) { return 'منجز'; }
        $n = (int) floor(($t - strtotime(date('Y-m-d'))) / 86400);
        if ($n > 0) { return 'متبق ' . $n . ' يوما'; }
        if ($n < 0) { return 'متأخر ' . abs($n) . ' يوما'; }
        return 'اليوم';
    }
}
