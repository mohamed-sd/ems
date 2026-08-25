<?php
/**
 * includes/related_tickets_tab.php — «البلاغاتُ المتصلة» (NAV-01 §5-④ · B-03)
 * ───────────────────────────────────────────────────────────────────────────
 * «تاريخُ البلاغات جزءٌ من تاريخ الكيان» — قسمٌ يُضمَّن في الملفات الأم:
 *   ملفُّ المعدة · ملفُّ المشروع/الموقع · ملفُّ المورد.
 *
 * الاستعمال (داخل صفحةٍ مصادَقةٍ متصلة):
 *   $rt_kind = 'equipment' | 'mnt_order' | 'site' | 'supplier';
 *   $rt_ref  = المعرّف؛
 *   include __DIR__ . '/../includes/related_tickets_tab.php';
 *
 * ◆ **ما أُصلح فيه (قرارُ المالك 2026-08-19)**
 *   ① كان يُضمَّن **بعد** إغلاقِ `</div>` الخاصِّ بـ`.main`، فيسقط خارجَ غلافِ
 *     الشاشةِ ويظهر بجانبِها — بلا هوامشِ القشرةِ ولا عرضِها.
 *   ② ولغتُه البصريةُ ثالثةٌ: `<h3 class="h5">` وجدولُ بوتستراب `table-sm`
 *     وشارةٌ بلونٍ مثبَّتٍ عارٍ في سمةِ `style`.
 *   ③ و**المرحلةُ تُطبع بحرفِها الإنجليزيِّ** (`in_progress` · `follow_up`)
 *     كما تخرج من العمود — والمستخدمُ العربيُّ يقرأ رمزَ قاعدةِ بيانات.
 *   ④ و`priority` كان **يُجلب في الاستعلامِ ولا يُعرض إطلاقًا**: عمودٌ يُقرأ
 *     من القاعدةِ ويُرمى — وهو أهمُّ ما في بلاغٍ متصل.
 *   فصار قسمًا من «بطاقةِ الكِيان» (`ems-profile__section`) بشاراتِ نغماتِها،
 *   والمرحلةُ باسمِها العربيِّ من `tkt_stages()` — مصدرِ الشاشاتِ نفسِه.
 *
 * ◆ **والوجهةُ تُقصد مباشرةً**: كان الرابطُ `tickets_list.php?open=` وهو
 *   معامَلٌ كان ميتًا ثم شُرِّف بتحويلةٍ إلى `ticket_form.php`. فالقصدُ هنا
 *   إلى البلاغِ نفسِه بلا قفزةٍ وسيطة.
 *
 * القراءةُ بنطاق الشركة من $conn القائم — والمكوّنُ عرضٌ صرفٌ بلا كتابة.
 */
if (!isset($rt_kind, $rt_ref) || !isset($conn)) { return; }

require_once __DIR__ . '/profile_kit.php';

$rt_ref = intval($rt_ref);
$rt_company = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;

// العمودُ الرابط بحسب نوع الكيان — والبلاغُ يحمل سياقَه المحمول (TKT-01 §2)
$rt_col_map = array(
    'equipment' => 't.equipment_id',
    'mnt_order' => "t.linked_ref_table = 'mnt_order' AND t.linked_ref_id",
    'site'      => 't.project_id',
    'supplier'  => 't.reporter_entity_id',
);
if (!isset($rt_col_map[$rt_kind])) { return; }
$rt_cond = strpos($rt_col_map[$rt_kind], '=') !== false
    ? '(' . $rt_col_map[$rt_kind] . ' = ' . $rt_ref . ')'
    : $rt_col_map[$rt_kind] . ' = ' . $rt_ref;

$rt_rows = array();
$rt_sql = "SELECT t.id, t.ticket_no, t.stage, t.priority, t.complaint, t.created_at,
                  (SELECT COUNT(*) FROM ticket_workstreams w
                    WHERE w.tk_id = t.id AND w.state NOT IN ('closed','admin_closed')) AS open_ws
           FROM tickets t
           WHERE $rt_cond" . ($rt_company > 0 ? " AND t.company_id = $rt_company" : '') . "
           ORDER BY t.created_at DESC LIMIT 30";
$rt_res = mysqli_query($conn, $rt_sql);
if ($rt_res) { while ($x = mysqli_fetch_assoc($rt_res)) { $rt_rows[] = $x; } }

/* أسماءُ المراحلِ العربيةُ من مصدرِ شاشاتِ البلاغاتِ نفسِه — لا نسخةٌ ثانيةٌ
   منها هنا تتفرّق عنها عند أولِ تعديل. */
$rt_stage_ar = array();
if (is_file(__DIR__ . '/../Tickets/tkt_helpers.php')) {
    require_once __DIR__ . '/../Tickets/tkt_helpers.php';
    if (function_exists('tkt_stages')) { $rt_stage_ar = tkt_stages(); }
}

/* نغمةُ المرحلةِ من معجمِ المكوّنِ الثماني — التسعُ حالاتٍ كما في العمودِ حرفًا */
$rt_stage_tone = array(
    'new'         => 'info',
    'classified'  => 'purple',
    'routed'      => 'cyan',
    'in_progress' => 'warn',
    'waiting'     => 'neutral',
    'follow_up'   => 'gold',
    'done'        => 'ok',
    'closed'      => 'neutral',
    'cancelled'   => 'danger',
);
/* والأولويةُ ثلاثٌ — وكانت تُجلب ولا تُعرض */
$rt_prio_ar   = array('normal' => 'عادية', 'high' => 'عالية', 'critical' => 'حرجة');
$rt_prio_tone = array('normal' => 'neutral', 'high' => 'warn', 'critical' => 'danger');

/* عدُّ المفتوحِ يسبق العدَّ الكليَّ في الدلالة: «كم يشغلني الآن» قبل «كم كان» */
$rt_open = 0; $rt_crit = 0;
foreach ($rt_rows as $t) {
    if (!in_array($t['stage'], array('done', 'closed', 'cancelled'), true)) { $rt_open++; }
    if ($t['priority'] === 'critical') { $rt_crit++; }
}

$rt_pre  = (strpos($_SERVER['SCRIPT_NAME'] ?? '', '/Tickets/') !== false) ? '' : '../Tickets/';
$rt_meta = array();
$rt_meta[] = ems_profile_badge(count($rt_rows) . ' بلاغا', 'neutral');
if ($rt_open > 0) { $rt_meta[] = ems_profile_badge($rt_open . ' مفتوح', 'warn'); }
if ($rt_crit > 0) { $rt_meta[] = ems_profile_badge($rt_crit . ' حرج', 'danger'); }

echo ems_profile_section_open(array(
    'title'   => 'البلاغات المتصلة',
    'icon'    => 'fas fa-bell',
    'actions' => implode(' ', $rt_meta),
    'note'    => 'آخر ثلاثين بلاغا يحملون هذا الكيان في سياقهم — الأحدث أولا.',
));

if (empty($rt_rows)) {
    echo ems_profile_note('لا بلاغات متصلة بهذا الكيان.', 'info');
} else {
    ?>
    <?php /* ◆ **ولا لغةَ جدولٍ رابعةٌ تُخترع هنا**: الغلافُ `table-container`
             والصنفُ `table table-striped` كلاهما قائمٌ في النظامِ ومستعمَلٌ في
             أقسامِ البطاقاتِ نفسِها (ملفُّ عمليةِ التمويل مثلًا). واختراعُ
             `ems-profile__table` كان سيضيف نسخةً خامسةً من تصميمِ جدولٍ —
             وهو عينُ ما تعالجه هذه الجولة. */ ?>
    <div class="table-container">
      <table class="table table-striped" data-no-dt>
        <thead>
          <tr>
            <th>الرقم</th><th>الوصف</th><th>الأولوية</th><th>المرحلة</th>
            <th>مسارات مفتوحة</th><th>التاريخ</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($rt_rows as $t):
            $rt_st = (string) $t['stage'];
            $rt_pr = (string) $t['priority'];
            $rt_txt = trim((string) $t['complaint']);
        ?>
          <tr>
            <td><a href="<?= htmlspecialchars($rt_pre . 'ticket_form.php?id=' . intval($t['id']), ENT_QUOTES, 'UTF-8') ?>">
                <?= htmlspecialchars($t['ticket_no'], ENT_QUOTES, 'UTF-8') ?></a></td>
            <td><?php
                /* الوصفُ يُقصُّ للعرضِ ويبقى كاملًا في `title` — القصُّ بلا إعلانٍ
                   يجعل نصًّا مبتورًا يبدو نصًّا تامًّا. */
                if ($rt_txt === '') {
                    echo '<span class="ems-profile__fact-value--empty">—</span>';
                } else {
                    $rt_short = mb_substr($rt_txt, 0, 70);
                    echo '<span title="' . htmlspecialchars($rt_txt, ENT_QUOTES, 'UTF-8') . '">'
                       . htmlspecialchars($rt_short, ENT_QUOTES, 'UTF-8')
                       . (mb_strlen($rt_txt) > 70 ? '…' : '') . '</span>';
                }
            ?></td>
            <td><?= ems_profile_badge(isset($rt_prio_ar[$rt_pr]) ? $rt_prio_ar[$rt_pr] : $rt_pr,
                        isset($rt_prio_tone[$rt_pr]) ? $rt_prio_tone[$rt_pr] : 'neutral') ?></td>
            <td><?= ems_profile_badge(isset($rt_stage_ar[$rt_st]) ? $rt_stage_ar[$rt_st] : $rt_st,
                        isset($rt_stage_tone[$rt_st]) ? $rt_stage_tone[$rt_st] : 'neutral') ?></td>
            <td><?php $rt_ws = intval($t['open_ws']);
                echo $rt_ws > 0 ? ems_profile_badge((string) $rt_ws, 'warn')
                                : '<span class="ems-profile__fact-value--empty">—</span>'; ?></td>
            <td><?= htmlspecialchars(substr((string) $t['created_at'], 0, 10), ENT_QUOTES, 'UTF-8') ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php
}

echo ems_profile_section_close();
