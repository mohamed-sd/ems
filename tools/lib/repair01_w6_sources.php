<?php
/**
 * tools/lib/repair01_w6_sources.php
 *   مصادرُ النصِّ المُصيَّرِ ودفترُ التحويلِ — REPAIR01 · W06
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **مصدرٌ واحدٌ لثلاثِ أدوات**: المنقّي (`repair01_w6_apply`) والفاحصُ
 *   (`repair01_ui_purity`) والبوّابةُ (`repair01_w6_gate`) يقرؤون النطاقَ من
 *   هنا. ولو أعلن كلٌّ منهم مقامَه لتفرّقت المقاماتُ بصمت — و«المقامُ كاملٌ
 *   لا مختار» (‏_CONTEXT §قواعد القياس ٤).
 *
 * ◆ **والمقامُ يشمل ما لا يُنقّى**: ثلاثةُ مصادرَ في الدفترِ **لا تُصيَّر**
 *   (`is_rendered = 0`) — تُصنَّف `ADMIN_VISIBLE` وتُعذَر ولا تُمَسّ. وحذفُها
 *   من الدفترِ يجعل المقامَ مختارًا؛ وإبقاؤها بلا تصنيفٍ يجعل التنقيةَ عمياء.
 *
 * ◆ **وترتيبُ التنقيةِ يحكم** (‏W06 §٤-٥): المولِّدُ قبل المولَّد.
 *   `nav09_action_map` (‏٣) قبل `work_items` (‏١٣) — وإلّا عاد الدَّينُ مع
 *   أوّلِ فعل.
 * ═══════════════════════════════════════════════════════════════════════════
 */

/**
 * مصادرُ النصّ. كلُّ صفٍّ: الجدولُ · العمودُ · مفتاحُ الصفِّ · هل يُصيَّر ·
 * مَن يُصيِّره بالاسم · صنفُ الظهور · هل ذيلُه نصُّ مستخدم · ترتيبُ التنقية.
 */
function repair01_w6_sources()
{
    return array(
        'gov_screen_cycle.next_state' => array(
            'table' => 'gov_screen_cycle', 'column' => 'next_state', 'key' => 'id',
            'where' => '', 'rendered' => 1, 'composite' => 0, 'order' => 1,
            'renderer' => 'includes/page_header.php · سطرُ الدورةِ في كلِّ ترويسة',
            'visibility' => 'USER_VISIBLE',
            'context' => 'CYCLE',
            'why' => 'الحالة التالية تصير في ترويسة كل صفحة تخدم هذا الملف',
            'src_ref' => 'W06 §٣ · gov_screen_cycle.next_state · includes/page_header.php:317',
        ),
        'gov_screen_cycle.output_doc' => array(
            'table' => 'gov_screen_cycle', 'column' => 'output_doc', 'key' => 'id',
            'where' => '', 'rendered' => 1, 'composite' => 0, 'order' => 2,
            'renderer' => 'includes/page_header.php · المستندُ الناتجُ في سطرِ الدورة',
            'visibility' => 'USER_VISIBLE',
            'context' => 'CYCLE',
            'why' => 'المستند الناتج يصير الى جانب الحالة التالية في الترويسة نفسها',
            'src_ref' => 'W06 §٣ · gov_screen_cycle.output_doc · includes/page_header.php:325',
        ),
        'nav09_action_map.label_ar' => array(
            'table' => 'nav09_action_map', 'column' => 'label_ar', 'key' => 'canonical_code',
            'where' => '', 'rendered' => 1, 'composite' => 0, 'order' => 3,
            'renderer' => 'WorkItemService::fromNavAction · مصدرُ عنوانِ كلِّ بندِ عمل',
            'visibility' => 'USER_VISIBLE',
            'context' => 'WORK_ITEM',
            'why' => 'المولد: كل فعل في النظام يركب منه عنوان بند عمل — ينقى قبل المولد',
            'src_ref' => 'W06 §٤-٤ · app/Services/Work/WorkItemService.php:553',
        ),
        'nav_items.label_ar' => array(
            'table' => 'nav_items', 'column' => 'label_ar', 'key' => 'id',
            'where' => '', 'rendered' => 1, 'composite' => 0, 'order' => 4,
            'renderer' => 'includes/unified_nav.php · بندُ السايدبار (الاحتياطيُّ الرابع)',
            'visibility' => 'USER_VISIBLE',
            'context' => 'SIDEBAR',
            'why' => 'اسم البند حين لا يعلوه معياري معتمد ولا اسم حالي',
            'src_ref' => 'W06 §٣ · nav_items.label_ar · includes/unified_nav.php:888',
        ),
        'nav_canonical.canonical_ar' => array(
            'table' => 'nav_canonical', 'column' => 'canonical_ar', 'key' => 'id',
            'where' => '', 'rendered' => 1, 'composite' => 0, 'order' => 5,
            'renderer' => 'includes/unified_nav.php · الاسمُ المعياريُّ المعتمد (الأسبقيّةُ الأولى)',
            'visibility' => 'USER_VISIBLE',
            'context' => 'SIDEBAR',
            'why' => 'المعتمد يغلب الحالي يغلب بند القائمة — فهو اول ما تقع عليه العين',
            'src_ref' => 'W06 §٣ · nav_canonical.canonical_ar · includes/unified_nav.php:886',
        ),
        'nav_canonical.current_label' => array(
            'table' => 'nav_canonical', 'column' => 'current_label', 'key' => 'id',
            'where' => 'current_label IS NOT NULL', 'rendered' => 1, 'composite' => 0, 'order' => 6,
            'renderer' => 'includes/unified_nav.php · الاسمُ الحاليُّ للمسار',
            'visibility' => 'USER_VISIBLE',
            'context' => 'SIDEBAR',
            'why' => 'يقرأ في المقارنة والمنظر ويظهر حين لا اعتماد',
            'src_ref' => 'W06 §٣ · nav_canonical.current_label',
        ),
        'nav_canonical.group_name' => array(
            'table' => 'nav_canonical', 'column' => 'group_name', 'key' => 'id',
            'where' => '', 'rendered' => 1, 'composite' => 0, 'order' => 7,
            'renderer' => 'includes/unified_nav.php · مجموعةُ المسارِ المعياريّة',
            'visibility' => 'USER_VISIBLE',
            'context' => 'SIDEBAR',
            'why' => 'اسم المجموعة التي يسكنها المسار في السايدبار المعياري',
            'src_ref' => 'W06 §٣ · nav_canonical.group_name',
        ),
        'nav_canonical_current.cur_label' => array(
            'table' => 'nav_canonical_current', 'column' => 'cur_label', 'key' => 'route,role_id',
            'where' => '', 'rendered' => 1, 'composite' => 0, 'order' => 8,
            'renderer' => 'includes/unified_nav.php · اسمُ البندِ الحاليُّ لكلِّ دور',
            'visibility' => 'USER_VISIBLE',
            'context' => 'SIDEBAR',
            'why' => 'الاسم الذي يراه الدور حين لا اعتماد معياري',
            'src_ref' => 'W06 §٣ · nav_canonical_current.cur_label · includes/unified_nav.php:887',
        ),
        'nav_canonical_current.cur_group' => array(
            'table' => 'nav_canonical_current', 'column' => 'cur_group', 'key' => 'route,role_id',
            'where' => '', 'rendered' => 1, 'composite' => 0, 'order' => 9,
            'renderer' => 'includes/unified_nav.php · مجموعةُ البندِ الحاليّة',
            'visibility' => 'USER_VISIBLE',
            'context' => 'SIDEBAR',
            'why' => 'اسم المجموعة الحالية لكل دور',
            'src_ref' => 'W06 §٣ · nav_canonical_current.cur_group',
        ),
        'nav_group_taxonomy.name_ar' => array(
            'table' => 'nav_group_taxonomy', 'column' => 'name_ar', 'key' => 'code',
            'where' => '', 'rendered' => 1, 'composite' => 0, 'order' => 10,
            'renderer' => 'includes/unified_nav.php · رأسُ المجموعةِ الاثنتَي عشرةَ (emsNavTaxonomy)',
            'visibility' => 'USER_VISIBLE',
            'context' => 'SIDEBAR',
            'why' => 'المصدر الفعلي لرؤوس المجموعات الاثنتي عشرة — و includes/nav_groups.php بذرة تقرا عند غيابه',
            'src_ref' => 'W06 §٤-١ ③ · nav_group_taxonomy.name_ar · includes/unified_nav.php:816',
        ),
        'link_groups.name' => array(
            'table' => 'link_groups', 'column' => 'name', 'key' => 'id',
            'where' => '', 'rendered' => 1, 'composite' => 0, 'order' => 10,
            'renderer' => 'includes/unified_nav.php · رأسُ المجموعةِ في السايدبار',
            'visibility' => 'USER_VISIBLE',
            'context' => 'SIDEBAR',
            'why' => 'اسم المجموعة يرسم من هنا — والحارس يفحص سجلا اخر (ذاكرة الحملة)',
            'src_ref' => 'W06 §٤-١ ③ · link_groups.name · includes/unified_nav.php:83',
        ),
        'link_groups.stage_title' => array(
            'table' => 'link_groups', 'column' => 'stage_title', 'key' => 'id',
            'where' => 'stage_title IS NOT NULL', 'rendered' => 1, 'composite' => 0, 'order' => 11,
            'renderer' => 'includes/unified_nav.php · عنوانُ المرحلةِ فوقَ مجموعاتِها',
            'visibility' => 'USER_VISIBLE',
            'context' => 'SIDEBAR',
            'why' => 'عنوان المرحلة يصير رأسا في السايدبار',
            'src_ref' => 'W06 §٤-١ ④ · includes/unified_nav.php:295',
        ),
        'link_groups.stage_desc' => array(
            'table' => 'link_groups', 'column' => 'stage_desc', 'key' => 'id',
            'where' => 'stage_desc IS NOT NULL', 'rendered' => 1, 'composite' => 0, 'order' => 12,
            'renderer' => 'includes/unified_nav.php · سطرُ شرحِ المرحلةِ تحتَ اسمِها',
            'visibility' => 'USER_VISIBLE',
            'context' => 'SIDEBAR',
            'why' => 'سطر الشرح يصير تحت اسم المرحلة',
            'src_ref' => 'W06 §٤-١ ④ · includes/unified_nav.php:375',
        ),
        'work_items.title' => array(
            'table' => 'work_items', 'column' => 'title', 'key' => 'id',
            'where' => '', 'rendered' => 1, 'composite' => 1, 'order' => 13,
            'renderer' => 'main/my_tasks.php · قوائمُ بنودِ العملِ وصناديقُ الاعتماد',
            'visibility' => 'USER_VISIBLE',
            'context' => 'WORK_ITEM',
            'why' => 'المولد سلفا: ينقى بعد مصدره وذيله بين قوسين نص مستخدم لا يمس',
            'src_ref' => 'W06 §٤-٥ · work_items.title',
        ),

        /* ── ما لا يُصيَّر: مُعلَنٌ ومصنَّفٌ ولا يُمَسّ (‏§٤-٧ · §٤-١٠) ───── */
        'gov_screen_cycle.screen_title' => array(
            'table' => 'gov_screen_cycle', 'column' => 'screen_title', 'key' => 'id',
            'where' => '', 'rendered' => 0, 'composite' => 0, 'order' => 90,
            'renderer' => 'لا مستهلكَ يُصيِّره — مسحُ الشيفرةِ يجد page_header وحدَه ويقرأ next_state وoutput_doc',
            'visibility' => 'ADMIN_VISIBLE',
            'context' => 'REGISTRY',
            'why' => 'يحمل اسم الملف بين قوسين لكل صف — سجل حوكمة لا واجهة اعمال',
            'src_ref' => 'W06 §٤-٧ · قياسٌ حيّ: لا مصيّر لهذا العمود',
        ),
        'gov_screen_cycle.group_name' => array(
            'table' => 'gov_screen_cycle', 'column' => 'group_name', 'key' => 'id',
            'where' => '', 'rendered' => 0, 'composite' => 0, 'order' => 91,
            'renderer' => 'مقامُ مقارنةٍ في بوّابتَي W03 وW05 — لا يُصيَّر',
            'visibility' => 'ADMIN_VISIBLE',
            'context' => 'REGISTRY',
            'why' => 'مجموعة المصفوفة الحاكمة يقاس عليها لا تعرض',
            'src_ref' => 'W06 §٤-٧ · gov_screen_cycle.group_name',
        ),
        'nav09_action_map.screen_title' => array(
            'table' => 'nav09_action_map', 'column' => 'screen_title', 'key' => 'canonical_code',
            'where' => '', 'rendered' => 0, 'composite' => 0, 'order' => 92,
            'renderer' => 'كان يُضَمُّ بشرطةِ ربطٍ إلى عنوانِ بندِ العمل — ورُفع الضمُّ في W06 §٤-٤',
            'visibility' => 'ADMIN_VISIBLE',
            'context' => 'REGISTRY',
            'why' => 'بعد رفع شرطة الربط لم يعد يصير — والشاشة تحفظ في source_screen',
            'src_ref' => 'W06 §٤-٤ · app/Services/Work/WorkItemService.php',
        ),
    );
}

/**
 * **ملفُّ الشاشةِ مصدرًا** (‏W06 §٤-٥ · الجولةُ الثانيةُ مصحَّحةُ النطاق).
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **وهو أكبرُ ما فات الجولةَ الأولى**: تلك فحصت الجداولَ السبعةَ فخرجت
 *   خضراءَ ١٨/١٨، و**٨٧٢ ملفَّ شاشةٍ** خارجَ مقامِها فيها عشراتُ الآلافِ من
 *   علاماتِ التشكيلِ في نصٍّ يراه المستخدم. و«لا اكتمالَ ببوّابةٍ تفحص ما
 *   اخترتَ فحصَه».
 *
 * ◆ **ولماذا شكلُه غيرُ شكلِ إخوتِه**: الجدولُ مفتاحُه صفٌّ وقيمتُه عمود؛
 *   والملفُّ مفتاحُه مسارٌ وقيمتُه **مدًى مصنَّفٌ داخلَه** — فلا يُقاس بدالّةِ
 *   `repair01_w6_read`. ويبقى في الدفترِ نفسِه (`repair01_w6_scope`) صفًّا
 *   واحدًا بمقامِه ومُصيِّرِه وصنفِ ظهورِه، وإلّا كان المقامُ مختارًا.
 *
 * ◆ **والأثقلُ أثرًا أوّلًا** (‏§٤-٥): `insidebar.php` و`inheader.php`
 *   يُصيَّرانِ في **كلِّ صفحة** — فتشكيلُهما أكثرُ ما يقع عليه بصرُ المستخدم.
 * ═══════════════════════════════════════════════════════════════════════════
 * @return array وصفُ المصدرِ لدفترِ النطاق
 */
function repair01_w6_file_source($rows = 0, $before = 0, $after = 0)
{
    return array(
        'key'        => 'screen_files.php_text',
        'table'      => 'screen_files',
        'column'     => 'php_text',
        'filter'     => 'ملف php في نطاق الشاشات — بلا vendor و tools و tests و database و docs',
        'rows'       => (int) $rows,
        'before'     => (int) $before,
        'after'      => (int) $after,
        'order'      => 14,
        'renderer'   => 'الملف نفسه — insidebar.php و inheader.php يصيران في كل صفحة',
        'visibility' => 'USER_VISIBLE',
        'why'        => 'نص الواجهة المكتوب في الشيفرة — والتعليق يعزل بالرمزنة والمقارن يعذر بمعجم الاقتران',
        'src_ref'    => 'W06 §٤-٥ · tools/lib/repair01_w6_files.php',
    );
}

/** المصادرُ التي تُصيَّر وحدَها — نطاقُ التنقيةِ والفحص. */
function repair01_w6_rendered_sources()
{
    $out = array();
    foreach (repair01_w6_sources() as $k => $s) {
        if ((int) $s['rendered'] === 1) { $out[$k] = $s; }
    }
    uasort($out, function ($a, $b) { return $a['order'] - $b['order']; });
    return $out;
}

/**
 * تعبيرُ مفتاحِ الصفِّ في SQL ومفتاحُه نصًّا — يدعم المفتاحَ المركَّب
 * (`nav_canonical_current` مفتاحُه `route` و`role_id` معًا).
 */
function repair01_w6_key_parts($src)
{
    return array_map('trim', explode(',', (string) $src['key']));
}

/** شرطُ `WHERE` يطابق صفًّا بمفتاحِه النصّيِّ المخزَّن (‏أجزاءٌ مفصولةٌ بـ`|`). */
function repair01_w6_key_where(mysqli $conn, $src, $storedKey)
{
    $parts = repair01_w6_key_parts($src);
    $vals  = explode('|', (string) $storedKey);
    $w = array();
    foreach ($parts as $i => $p) {
        $v = isset($vals[$i]) ? $vals[$i] : '';
        $w[] = '`' . $p . "` = '" . $conn->real_escape_string($v) . "'";
    }
    return implode(' AND ', $w);
}

/** قراءةُ صفوفِ مصدرٍ: [مفتاحٌ نصّيّ ⇐ القيمة]. */
function repair01_w6_read(mysqli $conn, $src)
{
    $parts = repair01_w6_key_parts($src);
    $cols  = '`' . implode('`,`', $parts) . '`';
    $sql = "SELECT $cols, `" . $src['column'] . "` AS __v FROM `" . $src['table'] . "`"
         . ($src['where'] !== '' ? ' WHERE ' . $src['where'] : '');
    $out = array();
    $r = $conn->query($sql);
    while ($r && $x = $r->fetch_assoc()) {
        $k = array();
        foreach ($parts as $p) { $k[] = (string) $x[$p]; }
        $out[implode('|', $k)] = (string) $x['__v'];
    }
    return $out;
}
