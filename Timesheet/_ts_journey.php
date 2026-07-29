<?php
/**
 * Timesheet/_ts_journey.php — بانيةُ شريط رحلة الوحدة التشغيلية
 * ═══════════════════════════════════════════════════════════════════════════
 * الدستور §5/§9 · UX-01 §6 · UX-03 §8.2: «شريطُ مراحلَ أعلى شاشتها: المرحلةُ
 * الحالية مضاءةٌ، والتاليةُ باسم صاحبها — فيرى المبتدئ أين هو وماذا بعد».
 *
 * **صفرُ جدولٍ جديد**: الرحلةُ إسقاطٌ لا تخزين. الأزمنةُ من السجل الإلحاقي
 * `unit_approvals`، والمرحلةُ الحالية من `unit_entries.state` الفعلية، والأصحابُ
 * من سجل الأدوار — وما لا مصدرَ له يُترك فارغًا (قاعدةُ عدم التلفيق).
 *
 * ── خمسُ مراحلَ من حالات الآلة الثلاث عشرة ─────────────────────────────────
 *   ① الإدخال (draft · submitted)      ② اعتماد الموقع (site_approved)
 *   ③ بطاقاتُ الأطراف (parties_review · parties_approved)
 *   ④ اعتماد المبيعات (sales_approved)  ⑤ التحويل المالي (converted)
 * والباقياتُ ليست تقدّمًا: `returned` **انكسارٌ** يُعرض لافتةً، و`on_hold` وقفة،
 * و`rejected`/`cancelled`/`reversed`/`superseded` توقفٌ.
 *
 * ── ⚠️ الفخُّ الأول: `returned` ليست مرحلة ───────────────────────────────────
 * الإعادةُ للاستكمال ليست خطوةً إلى الأمام بل **رجوعٌ بالرقم نفسه وجولةٍ
 * جديدة** (UX-03 §8.2). فتُعرض `banner` بـ`kind=return` ومعها **رقمُ الجولة**
 * وسببُ الإعادة من `unit_approvals.note` — وعرضُها مرحلةً عاديةً يجعل الشريط
 * يكذب على المستخدم فيظنّ أنه تقدّم وهو رجع.
 *
 * ── ⚠️ الفخُّ الثاني: اعتماداتُ الأطراف **متوازيةٌ لا متسلسلة** ──────────────
 * المورد والمشغّل والمشرف يحكمون **معًا** لا بالدور (`approve()` تفتح لأيٍّ
 * منهم من `site_approved` وتنتظر اكتمالَهم)، فعرضُهم ثلاثَ مراحلَ متتاليةٍ
 * **كذبٌ بصريّ**: يوحي بترتيبٍ لا وجودَ له، ويجعل واقعةً اعتمدها المشغّلُ وحده
 * تبدو «متأخرةً عن المورد». فمرحلةٌ واحدةٌ بعدّادٍ («2 من 3»).
 *
 * والمطلوبون هم **مَن حضر مرجعُه على الواقعة** (`requiredPartiesOf`) لا الثلاثةُ
 * دائمًا — فواقعةٌ بلا مشرفٍ لا ينتظرها أحد. والمصدرُ دالةُ الخدمة نفسُها التي
 * تحكم بها آلةُ الحالات، لا نسخةٌ ثانيةٌ منها (نسخةٌ ثانية = انفصامٌ مؤجَّل).
 */

if (!function_exists('unit_journey_role_name')) {
    /** اسمُ الدور من سجلّه — زينةُ عرضٍ لا تعطّل الشريط إن تعذّرت. */
    function unit_journey_role_name($gate, $roleId)
    {
        static $cache = array();
        $rid = intval($roleId);
        if ($rid <= 0) { return ''; }
        if (array_key_exists($rid, $cache)) { return $cache[$rid]; }
        $name = '';
        try {
            if ($gate !== null) {
                $row = $gate->selectOne('roles', array('columns' => array('name'),
                                                       'where' => array('id' => $rid)));
                if ($row && isset($row['name'])) { $name = strval($row['name']); }
            }
        } catch (\Throwable $t) { /* الاسمُ زينةٌ */ }
        return $cache[$rid] = $name;
    }
}

if (!function_exists('unit_journey_party_label')) {
    /** تسميةُ الطرف بلغة المهمة — لا `supplier` ولا `stage` في وجه المستخدم. */
    function unit_journey_party_label($stage)
    {
        $map = array('supplier' => 'المورد', 'operator' => 'المشغّل',
                     'supervisor' => 'المشرف', 'fleet' => 'الأسطول');
        return isset($map[$stage]) ? $map[$stage] : $stage;
    }
}

if (!function_exists('unit_journey')) {
    /**
     * @param  object|null $gate      بوابةُ المستأجر (لأسماء الأدوار) — تقبل null
     * @param  array       $entry     صفُّ `unit_entries`
     * @param  array       $approvals صفوفُ `unit_approvals` لهذه الواقعة مرتّبةً تصاعديًّا
     * @return array عقد ems_journey_bar()
     */
    function unit_journey($gate, array $entry, array $approvals = array())
    {
        require_once __DIR__ . '/../includes/journey_bar.php';       // ems_journey_ago
        require_once __DIR__ . '/../app/Services/Unit/TimesheetEntryService.php';

        $state = strval($entry['state']);
        $round = isset($entry['current_round']) ? intval($entry['current_round']) : 1;

        // ── ① المطلوبون: مَن حضر مرجعُه — من دالة الخدمة لا من نسخةٍ ثانية ──
        $required = \App\Services\Unit\TimesheetEntryService::requiredPartiesOf($entry);
        $reqCount = count($required);

        // ── ② الأزمنةُ من السجل الإلحاقي · وآخرُ إعادةٍ بسببها ──────────────
        // أزمنةُ **الجولة الجارية** وحدَها: جولةٌ ماتت زمنُها تاريخٌ لا حالة،
        // وعرضُه يوحي بإنجازٍ أُلغي بالإعادة.
        $at = array();
        $decided = array();     // الأطرافُ المطلوبةُ التي حكمت في الجولة الجارية
        $lastReturn = null;
        foreach ($approvals as $a) {
            $stage = strval($a['stage']);
            $when  = strval($a['decided_at']);
            $rno   = intval($a['round_no']);
            if (strval($a['decision']) === 'returned') {
                if ($lastReturn === null || $rno >= intval($lastReturn['round_no'])) { $lastReturn = $a; }
                continue;
            }
            if (strval($a['decision']) !== 'approved' || $rno !== $round) { continue; }
            if     ($stage === 'site')  { $at[2] = $when; }
            elseif ($stage === 'sales') { $at[4] = $when; }
            elseif (in_array($stage, $required, true)) {
                $decided[$stage] = true;
                // زمنُ المرحلة الثالثة هو **آخرُ** طرفٍ حكم — لا أوّلُه:
                // المرحلةُ تنتهي باكتمال الجميع لا ببداية أوّلهم.
                if (!isset($at[3]) || $when > $at[3]) { $at[3] = $when; }
            }
        }
        $doneCount = count($decided);
        // زمنُ الإدخال من الواقعة نفسِها حين لا حركةَ مسجَّلةً له
        if (!isset($at[1]) && !empty($entry['created_at'])) { $at[1] = strval($entry['created_at']); }
        if (!empty($entry['converted_at'])) { $at[5] = strval($entry['converted_at']); }

        // ── ③ المرحلةُ الحالية من الحالة الفعلية ────────────────────────────
        $stopped   = array('rejected', 'cancelled', 'reversed', 'superseded');
        $isStopped = in_array($state, $stopped, true);
        $isPaused  = ($state === 'on_hold');
        $isReturn  = ($state === 'returned');

        $current = 1;
        if     ($state === 'draft' || $state === 'submitted' || $isReturn) { $current = 1; }
        elseif ($state === 'site_approved' || $state === 'parties_review') { $current = 3; }
        elseif ($state === 'parties_approved')                            { $current = 4; }
        elseif ($state === 'sales_approved')                              { $current = 5; }
        elseif ($state === 'converted')                                   { $current = 6; }  // كلُّها منجَزة
        elseif ($isPaused) {
            // الوقفةُ لا تُرجع الواقعةَ: تُعرض عند آخر مرحلةٍ بلغتها فعلًا
            for ($k = 5; $k >= 1; $k--) { if (isset($at[$k])) { $current = $k + 1; break; } }
        } elseif ($isStopped) {
            for ($k = 5; $k >= 1; $k--) { if (isset($at[$k])) { $current = $k + 1; break; } }
        }
        // `submitted` بعد اعتماد الموقع مستحيلٌ في الآلة، لكن الشريطَ يعرض
        // الواقعَ لا المفترض: إن وُجد زمنُ اعتمادِ موقعٍ فالمرحلةُ الثانيةُ تمّت.
        if ($current === 1 && isset($at[2])) { $current = 3; }

        // ── ④ الأصحابُ من سجل الأدوار ───────────────────────────────────────
        $siteOwner  = unit_journey_role_name($gate, 5);    // إدارة الموقع
        $salesOwner = unit_journey_role_name($gate, 12);   // ادارة المبيعات
        $finOwner   = unit_journey_role_name($gate, 17);   // إدارة المالية

        $partyOwners = array();
        foreach ($required as $p) { $partyOwners[] = unit_journey_party_label($p); }

        $defs = array(
            1 => array('label' => 'إدخالُ اليوم',      'owner' => 'مُدخِلُ الدوام'),
            2 => array('label' => 'اعتمادُ الموقع',    'owner' => $siteOwner),
            3 => array('label' => 'بطاقاتُ الأطراف',   'owner' => implode(' · ', $partyOwners)),
            4 => array('label' => 'اعتمادُ المبيعات',  'owner' => $salesOwner),
            5 => array('label' => 'التحويلُ المالي',   'owner' => $finOwner),
        );

        $stages = array();
        foreach ($defs as $k => $d) {
            if     ($k <  $current)  { $status = 'done'; }
            elseif ($k === $current) { $status = ($isStopped || $isReturn) ? 'off' : 'current'; }
            else                     { $status = ($isStopped || $isReturn) ? 'off' : 'todo'; }

            $row = array('label' => $d['label'], 'status' => $status);
            if (isset($at[$k]))     { $row['at'] = $at[$k]; }
            if ($d['owner'] !== '') { $row['owner'] = $d['owner']; }

            // ── عدّادُ المرحلة المتوازية: «2 من 3» ──────────────────────────
            // يُعرض ما دامت المرحلةُ لم تُغلق (حاليةً أو موقوفةً عندها)، ويختفي
            // بعد اكتمالها — فالعدّادُ سؤالٌ لا سجل.
            if ($k === 3 && $reqCount > 0 && $status !== 'todo' && $status !== 'done') {
                $row['note'] = $doneCount . ' من ' . $reqCount;
            }
            if ($k === 3 && $status === 'done' && $reqCount > 1) {
                $row['note'] = 'اكتملت ' . $reqCount . ' بطاقات';
            }
            if ($status === 'current' && $isPaused) { $row['note'] = 'موقوفةٌ مؤقتًا'; }
            $stages[] = $row;
        }

        $j = array('stages' => $stages);

        // ── ⑤ الخطوةُ التالية باسم صاحبها ───────────────────────────────────
        if (!$isStopped && !$isReturn && !$isPaused && $current >= 1 && $current <= 5) {
            $since = !empty($entry['created_at']) ? strval($entry['created_at']) : '';
            for ($k = $current - 1; $k >= 1; $k--) { if (isset($at[$k])) { $since = $at[$k]; break; } }
            $next = array('label' => $defs[$current]['label'], 'owner' => $defs[$current]['owner']);
            if ($since !== '') { $next['since'] = $since; }
            // مَن بقي من الأطراف بالاسم — «ماذا بعد» لا «أين نحن» فقط
            if ($current === 3 && $doneCount < $reqCount) {
                $pending = array();
                foreach ($required as $p) {
                    if (empty($decided[$p])) { $pending[] = unit_journey_party_label($p); }
                }
                if ($pending) { $next['owner'] = implode(' · ', $pending); }
            }
            $j['next'] = $next;
        }

        // ── ⑥ اللافتة: انكسارُ الإعادة · وقفةٌ · توقفٌ · اكتمال ──────────────
        if ($isReturn) {
            $note = ($lastReturn !== null) ? trim(strval($lastReturn['note'])) : '';
            $j['banner'] = array(
                'kind'  => 'return',
                'title' => 'أُعيدت إليك لاستكمال:',
                'text'  => ($note !== '') ? $note : 'راجع بيانات اليوم وأعد الإرسال.',
                'meta'  => (($lastReturn !== null && !empty($lastReturn['decided_at']))
                            ? (ems_journey_ago($lastReturn['decided_at']) . ' · ') : '')
                           . 'بالرقم نفسه ' . strval($entry['entry_no'])
                           . ' · الجولة ' . $round,
            );
        } elseif ($isStopped) {
            $labels = array('rejected' => 'مرفوضة', 'cancelled' => 'ملغاة',
                            'reversed' => 'معكوسة', 'superseded' => 'محلٌّ محلَّها غيرُها');
            $j['banner'] = array(
                'kind'  => 'stop',
                'title' => 'توقفت الرحلة: ' . (isset($labels[$state]) ? $labels[$state] : $state),
                'text'  => '',
            );
        } elseif ($isPaused) {
            $j['banner'] = array('kind' => 'stop', 'title' => 'الوحدةُ موقوفةٌ مؤقتًا',
                'text' => 'الرحلةُ متوقفةٌ حتى يُرفع سببُ الوقف — ولم تُلغَ.');
        } elseif ($state === 'converted') {
            $j['banner'] = array(
                'kind'  => 'done',
                'title' => 'اكتملت الرحلة — وصارت الوحدةُ مالًا في الدفتر.',
                'meta'  => isset($at[5]) ? ems_journey_ago($at[5]) : '',
            );
        }

        return $j;
    }
}
