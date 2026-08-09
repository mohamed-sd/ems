<?php
/**
 * tools/u13_screens_manifest.php — بيانُ شاشاتِ update0013
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ المصبُّ الواحدُ للشاشات: يقرأه البذرُ (تسجيلُ الشاشاتِ الحاكمةِ ووسمُ حقولِها)
 *   والمولِّدُ (كتابةُ الملفاتِ وتسجيلُها في `modules` و`nav_items`) والبوابةُ
 *   (فحصُ أن كلَّ ما في البيانِ مبنيٌّ ومسجَّلٌ ويُصيَّر). فلا قائمةٌ ثانيةٌ تنجرف.
 *
 * ◆ أساسُ كلِّ شاشة (`basis`):
 *   • `registered`  — الوثيقةُ تسمّيها بملفِّها وأعمدتِها (FIN-OBL-01 §٤-٢٣ وحدَها).
 *   • `named`       — الوثيقةُ تسمّيها نصًّا بلا ملف (PROP-01 §٥-١).
 *   • `derived`     — لا سجلَّ لها، فاشتُقّت من **اختصاصٍ ذريٍّ بعينِه** يُذكر في
 *                     `doc_ref`. وهذا حسمُ المخالفاتِ V-03 · V-05 · V-06 · V-07 · V-08.
 *   ولا شاشةَ بلا أساسٍ ومرجع.
 */

function u13_screens_manifest()
{
    return array(

    /* ══ FIN-OBL-01 §٤-٢٣ — السبعُ المسجَّلةُ بأسمائها وملفاتِها ══════════ */
    array('code' => 'ob_register', 'dir' => 'Finance', 'file' => 'ob_register.php',
          'title' => 'سجل الالتزامات', 'icon' => 'fa fa-file-contract',
          'table' => 'fin_obl_register', 'nature' => 'document', 'role' => 17,
          'basis' => 'registered', 'doc' => 'FIN-OBL-01 §4-23', 'doc_ref' => 'OBL-0042',
          'intro' => 'كلُّ التزامٍ مولَّدٍ عند نفاذِ عقدِه — لا عند أولِ دفعة',
          'rule'  => 'OR-01: العقدُ النافذُ يولّد جدولَ استحقاقٍ لكلِّ مدتِه فورًا · والصمتُ عنه يُخفي التزامًا حقيقيًّا',
          'empty' => 'لا التزامَ مولَّدٌ بعدُ — يُولَّد آليًّا عند نفاذِ عقدٍ باختبارِ تجنبٍ مسجَّل',
          /* ◆ لا فعلَ «إنشاءِ التزام» هنا: OR-01 تجعل التوليدَ أثرَ نفاذِ العقدِ
               لا فعلًا يدويًّا. الفعلُ الوحيدُ المملوكُ للشاشةِ هو OR-08. */
          'actions_src' => "\n    'actions'    => array(\n"
            . "        'terminate' => array(\n"
            . "            'code'  => 'fin.obl.terminate',\n"
            . "            'label' => 'إنهاءُ التزامٍ بإنهاءِ عقدِه',\n"
            . "            'rule'  => 'OR-08: يُغلق ما لم يستحقَّ بعدُ — والمستحقُّ قبلَ الإنهاءِ يبقى دَينًا',\n"
            . "            'fields' => array('source_kind' => 'نوعُ المصدر (contract)', 'source_ref' => 'مرجعُ العقد',\n"
            . "                              'on_date' => 'تاريخُ الإنهاء (YYYY-MM-DD)', 'why' => 'سببُ الإنهاء'),\n"
            . "            'run' => function (\$conn, \$co, \$uid, \$in) {\n"
            . "                require_once __DIR__ . '/../app/Services/Finance/ObligationEngine.php';\n"
            . "                \$d = trim((string) (\$in['on_date'] ?? ''));\n"
            . "                if (\$d === '' || !preg_match('~^\\\\d{4}-\\\\d{2}-\\\\d{2}\$~', \$d)) {\n"
            . "                    return array('ok' => false, 'reason' => 'تاريخُ الإنهاءِ لازمٌ بصيغة YYYY-MM-DD');\n"
            . "                }\n"
            . "                return \\App\\Services\\Finance\\ObligationEngine::terminate(\$conn, \$co,\n"
            . "                    (string) (\$in['source_kind'] ?? 'contract'), (string) (\$in['source_ref'] ?? ''),\n"
            . "                    \$d, (string) (\$in['why'] ?? ''));\n"
            . "            }),\n"
            . "    ),"),

    array('code' => 'ob_schedule', 'dir' => 'Finance', 'file' => 'ob_schedule.php',
          'title' => 'جدول الاستحقاقات', 'icon' => 'fa fa-calendar-days',
          'table' => 'fin_obl_schedule', 'nature' => 'register', 'role' => 17,
          'basis' => 'registered', 'doc' => 'FIN-OBL-01 §4-23', 'doc_ref' => 'OBL-0043',
          'order' => 'obligation_id DESC, period_no ASC',
          'intro' => 'الطبقاتُ الثلاثُ بأعمدةٍ مستقلة — الارتباطُ والمعترَفُ به والذمة',
          'rule'  => 'OBL-0137: لا تُدمج ولا تُقفز · SY-05: والفترةُ الكسريةُ موسومةٌ صريحًا',
          'empty' => 'لا استحقاقاتٍ بعدُ — تُولَّد دفعةً واحدةً مع الالتزام'),

    array('code' => 'ob_due_soon', 'dir' => 'Finance', 'file' => 'ob_due_soon.php',
          'title' => 'المستحق قريبًا والتذكيرات', 'icon' => 'fa fa-bell',
          'table' => 'fin_obl_schedule', 'nature' => 'read', 'role' => 17,
          'basis' => 'registered', 'doc' => 'FIN-OBL-01 §4-23', 'doc_ref' => 'OBL-0044',
          'where' => "state IN ('scheduled','recognized','invoiced') AND due_date >= CURDATE() AND due_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)",
          'order' => 'due_date ASC',
          'intro' => 'ما يستحق خلالَ ثلاثين يومًا — بمهلةِ تذكيرٍ قبلَ كلٍّ',
          'rule'  => 'OR-04: صفرُ استحقاقٍ يمرُّ بلا تذكيرٍ قبلَه',
          'empty' => 'لا مستحقَّ خلالَ ثلاثين يومًا'),

    array('code' => 'ob_overdue', 'dir' => 'Finance', 'file' => 'ob_overdue.php',
          'title' => 'الالتزامات المتأخرة والذمم الدائنة', 'icon' => 'fa fa-triangle-exclamation',
          'table' => 'fin_obl_schedule', 'nature' => 'read', 'role' => 17,
          'basis' => 'registered', 'doc' => 'FIN-OBL-01 §4-23', 'doc_ref' => 'OBL-0045',
          'where' => "(state = 'moved_to_payables' OR (due_date < CURDATE() AND settled < l2_recognized AND state <> 'closed'))",
          'order' => 'due_date ASC',
          'intro' => 'ما مرَّ تاريخُه بلا سدادٍ فرُحِّل إلى الذممِ الدائنة',
          'rule'  => 'OR-05: يظهر في أعمارِ الذممِ · وتُنشر إشارةُ إنذارٍ إن تجاوز التأخيرُ الحد',
          'empty' => 'لا التزامَ متأخرًا — وهذا هو المطلوب'),

    array('code' => 'ob_horizon', 'dir' => 'Finance', 'file' => 'ob_horizon.php',
          'title' => 'آفاق الالتزامات الثلاثة', 'icon' => 'fa fa-timeline',
          'table' => 'fin_obl_schedule', 'nature' => 'read', 'role' => 17,
          'basis' => 'registered', 'doc' => 'FIN-OBL-01 §4-23', 'doc_ref' => 'OBL-0046',
          'where' => "state IN ('scheduled','recognized','invoiced')",
          'order' => 'due_date ASC', 'panel' => 'horizons',
          'intro' => 'ثلاثةُ آفاقٍ زمنية: ثلاثون يومًا · سنةٌ · وما بعدها',
          'rule'  => 'OR-12: ولكلٍّ مجموعُه بالطرفِ والنوع · ويُقاطَع بجدولِ التدفقِ النقديِّ المتوقع',
          'empty' => 'لا التزاماتٍ قائمةً في أيِّ أفق'),

    array('code' => 'ob_commitments', 'dir' => 'Finance', 'file' => 'ob_commitments.php',
          'title' => 'الملتزَم به وأثره في الموازنة', 'icon' => 'fa fa-scale-balanced',
          'table' => 'fin_obl_register', 'nature' => 'read', 'role' => 17,
          'basis' => 'registered', 'doc' => 'FIN-OBL-01 §4-23', 'doc_ref' => 'OBL-0047',
          'where' => "state = 'active'",
          'intro' => 'الالتزامُ يخفض المتاحَ في الموازنةِ عند الاعتمادِ لا عند الدفع',
          'rule'  => 'OR-06: المتاحُ الحقيقيُّ = المعتمدُ − المنفَّذُ − الملتزَمُ به · ثلاثةُ أرقامٍ في كلِّ بندٍ لا رقمان',
          'empty' => 'لا ارتباطاتٍ تخفض المتاحَ حاليًّا'),

    array('code' => 'ob_contingent', 'dir' => 'Finance', 'file' => 'ob_contingent.php',
          'title' => 'الالتزامات المحتملة والإفصاح', 'icon' => 'fa fa-file-shield',
          'table' => 'fin_obl_avoidance', 'nature' => 'document', 'role' => 17,
          'basis' => 'registered', 'doc' => 'FIN-OBL-01 §4-23', 'doc_ref' => 'OBL-0048',
          'where' => "verdict IN ('disclose_only','disclose_with_penalty','recognition_candidate','onerous')",
          'intro' => 'ما يُفصح عنه في الإيضاحاتِ ولا يُقيَّد في الميزانية',
          'rule'  => 'OR-11: الالتزامُ المحتملُ يُفصح ولا يُقيَّد · ولا يُقيَّد مخصَّصٌ إلا بقرارٍ ماليٍّ مسبَّبٍ ومعتمد',
          'empty' => 'لا التزاماتٍ محتملةً تستوجب إفصاحًا'),

    /* ══ PROP-01 §٥-١ — الجديدتانِ في صندوقِ الرئيس ═══════════════════════ */
    /* والأخريان مبنيتان حيًّا: Portal/ceo_approvals.php · Portal/ceo_risk.php */
    array('code' => 'ceo_audit_reports', 'dir' => 'Portal', 'file' => 'ceo_audit_reports.php',
          'title' => 'تقارير المراجعة الداخلية', 'icon' => 'fa fa-file-shield',
          'table' => 'exec_audit_reports', 'nature' => 'read', 'role' => 9,
          'basis' => 'named', 'doc' => 'PROP-01 §5-1', 'doc_ref' => 'CEO-Y0119',
          'order' => 'issued_at DESC',
          'intro' => 'يصل الرئيسَ مباشرةً غيرَ مفلترٍ — ولا يمرُّ بالماليةِ ولا بالحوكمةِ ولا بمن يُراجَع',
          'rule'  => 'CEO-Y0119: الوصولُ المباشرُ غيرُ المفلترِ شرطُ استقلالِ المراجعة · ولا يملك أحدٌ فلترتَه',
          'empty' => 'لا تقاريرَ مراجعةٍ واردةً بعدُ'),

    array('code' => 'ceo_assignments', 'dir' => 'Portal', 'file' => 'ceo_assignments.php',
          'title' => 'موافقات التكليف', 'icon' => 'fa fa-user-shield',
          'table' => 'exec_assignments', 'nature' => 'document', 'role' => 9,
          'basis' => 'named', 'doc' => 'PROP-01 §5-1', 'doc_ref' => 'CEO-Y0121',
          'order' => 'requested_at DESC',
          'intro' => 'كلُّ مسمًّى قياديٍّ أو رقابيٍّ — بفحصِ تعارضِ الواجباتِ والاستقلالِ آليًّا قبلَ العرض',
          'rule'  => 'CEO-Y0121: التكليفُ لا يسري ولا يمنح صلاحيةً قبلَ الموافقة · CEO-Y0122: والمتعارضُ لا يُعرض حتى يُحسم',
          'empty' => 'لا طلباتِ تكليفٍ معروضة',
          /* الفعلُ يُنفَّذ بالبوابةِ نفسِها — فحارسُ «الرئيسُ حصرًا» و«المتعارضُ لا
             يُقرَّر» يبقى في مكانٍ واحدٍ ولا تلتفُّ عليه شاشة. */
          'actions_src' => "\n    'actions'    => array(\n"
            . "        'approve' => array(\n"
            . "            'code'  => 'exec.assign.decide',\n"
            . "            'label' => 'الموافقةُ على تكليف',\n"
            . "            'rule'  => 'CEO-Y0121: للرئيسِ التنفيذيِّ حصرًا — والمتعارضُ لا يُقرَّر (CEO-Y0122)',\n"
            . "            'fields' => array('assignment_no' => 'رقمُ التكليف', 'decision_reason' => 'حيثياتُ القرار'),\n"
            . "            'optional' => array('decision_reason' => true),\n"
            . "            'run' => function (\$conn, \$co, \$uid, \$in) {\n"
            . "                require_once __DIR__ . '/../app/Services/Exec/AssignmentGate.php';\n"
            . "                return \\App\\Services\\Exec\\AssignmentGate::decide(\$conn, array(\n"
            . "                    'company_id' => \$co, 'assignment_no' => (string) (\$in['assignment_no'] ?? ''),\n"
            . "                    'decided_by' => \$uid, 'decision' => 'approved',\n"
            . "                    'decision_reason' => (string) (\$in['decision_reason'] ?? '')));\n"
            . "            }),\n"
            . "        'reject' => array(\n"
            . "            'code'  => 'exec.assign.decide',\n"
            . "            'label' => 'ردُّ تكليف',\n"
            . "            'rule'  => 'ملزمٌ بحيثياتِه — ولا يُردُّ بلا سبب',\n"
            . "            'fields' => array('assignment_no' => 'رقمُ التكليف', 'decision_reason' => 'سببُ الرد'),\n"
            . "            'run' => function (\$conn, \$co, \$uid, \$in) {\n"
            . "                require_once __DIR__ . '/../app/Services/Exec/AssignmentGate.php';\n"
            . "                return \\App\\Services\\Exec\\AssignmentGate::decide(\$conn, array(\n"
            . "                    'company_id' => \$co, 'assignment_no' => (string) (\$in['assignment_no'] ?? ''),\n"
            . "                    'decided_by' => \$uid, 'decision' => 'rejected',\n"
            . "                    'decision_reason' => (string) (\$in['decision_reason'] ?? '')));\n"
            . "            }),\n"
            . "    ),"),

    /* ══ FIN-ACC-01 — مشتقةٌ من اختصاصاتِها الذرية (حسمُ V-03) ═══════════ */
    array('code' => 'acc_my_day', 'dir' => 'Finance', 'file' => 'acc_my_day.php',
          'title' => 'مساحة عملي اليوم — محاسب التخصص', 'icon' => 'fa fa-list-check',
          'table' => 'work_items', 'nature' => 'read', 'role' => 18,
          'basis' => 'derived', 'doc' => 'FIN-ACC-01 §4-3', 'doc_ref' => 'FACC-0028',
          'where' => "status NOT IN ('closed_accepted','cancelled')", 'order' => 'due_at ASC',
          'scope_user' => 'assigned_user_id',
          'intro' => 'ثمانيةُ بنودٍ بنطاقِ المحاسبِ وتخصصِه وحدَهما',
          'rule'  => 'FACC-0028..0035: بنطاقِه وتخصصِه وحدَهما — والحدثُ خارجَ نطاقِه لا يظهر له',
          'empty' => 'لا مهامَّ في نطاقِك اليوم'),

    array('code' => 'acc_specializations', 'dir' => 'Finance', 'file' => 'acc_specializations.php',
          'title' => 'التخصصات المحاسبية العشرة', 'icon' => 'fa fa-sitemap',
          'table' => 'fin_acc_specializations', 'nature' => 'register', 'role' => 31,
          'basis' => 'derived', 'doc' => 'FIN-ACC-01 §4-1', 'doc_ref' => 'FACC-0001',
          'order' => 'code ASC', 'global_ref' => 1,
          'intro' => 'عشرةُ تخصصاتٍ لا مسمًّى عامٌّ واحد — لكلٍّ حساباتُه ونطاقُه وأبعادُه وحدُّه',
          'rule'  => 'FACC-0001: الصلاحيةُ من التخصصِ والنطاقِ لا من اسمِ المستخدم · ويجوز الجمعُ بشرطِ عدمِ تعارضِ الواجبات',
          'empty' => 'لم تُبذر التخصصاتُ بعدُ'),

    array('code' => 'acc_routing_matrix', 'dir' => 'Finance', 'file' => 'acc_routing_matrix.php',
          'title' => 'مصفوفة التوجيه لمحاسبي التخصصات', 'icon' => 'fa fa-diagram-project',
          'table' => 'fin_routing_matrix', 'nature' => 'register', 'role' => 31,
          'basis' => 'derived', 'doc' => 'FIN-OBL-01 §4-15', 'doc_ref' => 'OBL-0001',
          'order' => 'code ASC', 'global_ref' => 1,
          'intro' => 'خمسةٌ وثلاثون مسارًا من ستَّ عشرةَ إدارةً إلى عشرةِ تخصصات',
          'rule'  => 'OBL-0001: لا يختار المُطلِقُ إلى من يذهب طلبُه · ولا تصل الخزينةَ واقعةٌ قبلَ محاسبِ تخصصِها ورئيسِ الحسابات',
          'empty' => 'لم تُبذر المصفوفةُ بعدُ'),

    array('code' => 'acc_backflow', 'dir' => 'Finance', 'file' => 'acc_backflow.php',
          'title' => 'المرتجَع المالي للإدارات', 'icon' => 'fa fa-reply-all',
          /* ◆ ليست قراءةً محضة: BR-06 تُوجب على مالكِ السجلِّ **إغلاقًا بسببٍ
               مسجَّل** — فالإعلانُ سجلٌّ لا عرضٌ، وإلا فالقاعدةُ بلا يدٍ تنفّذها. */
          'table' => 'fin_backflow_log', 'nature' => 'register', 'role' => 18,
          'basis' => 'derived', 'doc' => 'FIN-OBL-01 §4-2', 'doc_ref' => 'OBL-0285',
          'order' => 'fired_at DESC',
          'intro' => 'ولكلِّ ما يُرسَل إلى الماليةِ مرتجَعٌ مقابلٌ إلى مصدرِه',
          'rule'  => 'BR-01: فالانتظارُ الصامتُ أسوأُ من الرفض · BR-03: والسببُ برمزٍ محكومٍ لا بنصٍّ حر',
          'empty' => 'لا مرتجَعاتٍ مُطلَقةً بعدُ',
          /* ◆ لا فعلَ حذفٍ ولا تعديلَ سبب: BR-06 «صفرُ إشعارٍ محذوف». */
          'actions_src' => "\n    'actions'    => array(\n"
            . "        'resolve' => array(\n"
            . "            'code'  => 'fin.route.backflow.resolve',\n"
            . "            'label' => 'إغلاقُ مرتجَعٍ عولِج',\n"
            . "            'rule'  => 'BR-06: صفرُ إشعارٍ محذوف — والإغلاقُ بسببٍ مسجَّلٍ وفاعلٍ معروف',\n"
            . "            'fields' => array('backflow_id' => 'رقمُ المرتجَع', 'close_reason' => 'سببُ الإغلاق'),\n"
            . "            'run' => function (\$conn, \$co, \$uid, \$in) {\n"
            . "                require_once __DIR__ . '/../app/Services/Finance/RoutingEngine.php';\n"
            . "                return \\App\\Services\\Finance\\RoutingEngine::resolveBackflow(\$conn, array(\n"
            . "                    'company_id' => \$co, 'backflow_id' => (int) (\$in['backflow_id'] ?? 0),\n"
            . "                    'closed_by' => \$uid, 'close_reason' => (string) (\$in['close_reason'] ?? '')));\n"
            . "            }),\n"
            . "    ),"),

    array('code' => 'acc_approval_chain', 'dir' => 'Finance', 'file' => 'acc_approval_chain.php',
          'title' => 'سلسلة الاعتماد الرباعية', 'icon' => 'fa fa-list-ol',
          'table' => 'fin_approval_chain', 'nature' => 'read', 'role' => 18,
          'basis' => 'derived', 'doc' => 'FIN-ACC-01 §4-7', 'doc_ref' => 'FACC-0040',
          'order' => 'decided_at DESC',
          'intro' => 'أنواعُ الاعتمادِ الأربعةُ على كلِّ مستند — ولا يُغني أحدُها عن الآخر',
          'rule'  => 'FACC-0044: صفرُ طلبٍ يُنفَّذ باعتمادٍ واحدٍ من الأربعة · ولا يُجمع اثنان في شخصٍ حيث يتعارضان',
          'empty' => 'لا اعتماداتٍ مسجَّلةً بعدُ'),

    /* ══ FIN-CTRL-01 — مشتقةٌ (حسمُ V-04 · V-05) ═════════════════════════ */
    /* ◆ عيبٌ كشفه الفاحصُ العكسي: كانت تقرأ `fin_obl_rules WHERE family='OR'`
         — وتلك **قواعدُ محرّكِ الالتزامات** لا مؤشراتُ جودةِ المحاسبة. وجدولٌ
         يحمل غيرَ ما تدّعي الشاشةُ أسوأُ من شاشةٍ فارغة. */
    array('code' => 'ctrl_quality_kpis', 'dir' => 'Finance', 'file' => 'ctrl_quality_kpis.php',
          'title' => 'مؤشرات جودة المحاسبة الاثنا عشر', 'icon' => 'fa fa-gauge-high',
          'table' => 'fin_quality_kpis', 'nature' => 'read', 'role' => 31,
          'basis' => 'derived', 'doc' => 'FIN-CTRL-01 §4-3', 'doc_ref' => 'FCTRL-0047',
          'order' => 'seq ASC', 'global_ref' => 1,
          'intro' => 'اثنا عشرَ مؤشرًا بحدِّه ومالكِه ودوريةِ قياسه',
          'rule'  => 'FCTRL-0047: محسوبٌ من القيودِ لا من إدخالٍ يدوي — ولكلٍّ مصدرُ حسابِه مكتوب',
          'empty' => 'لم تُبذر المؤشراتُ بعدُ'),

    array('code' => 'ctrl_authority_limits', 'dir' => 'Finance', 'file' => 'ctrl_authority_limits.php',
          'title' => 'الحدود الصريحة — ما لا يملكه كل دور', 'icon' => 'fa fa-ban',
          'table' => 'gov_authority_limits', 'nature' => 'register', 'role' => 31,
          'basis' => 'derived', 'doc' => 'FIN-ACC-01 §4-8', 'doc_ref' => 'FACC-0045',
          'order' => 'doc_code ASC, seq ASC', 'global_ref' => 1,
          'intro' => 'خمسةٌ وخمسون حدًّا من خمسِ وثائقٍ — ولكلٍّ مُنفِذُه الحي',
          'rule'  => 'الحدُّ بلا مُنفِذٍ دعوى لا قيد — والعمودُ «المُنفِذ» يُفحص في كلِّ بوابة',
          'empty' => 'لم تُبذر الحدودُ بعدُ'),

    array('code' => 'tre_cycle_stages', 'dir' => 'Finance', 'file' => 'tre_cycle_stages.php',
          'title' => 'مراحل دورتي الدفع والقبض', 'icon' => 'fa fa-diagram-successor',
          'table' => 'fin_cycle_stages', 'nature' => 'register', 'role' => 21,
          'basis' => 'derived', 'doc' => 'FIN-TRE-01 §4-4', 'doc_ref' => 'FTRE-0059',
          'where' => "cycle_kind IN ('payment','receipt')",
          'order' => 'cycle_kind ASC, seq ASC', 'global_ref' => 1,
          'intro' => 'خمسَ عشرةَ مرحلةً للدفعِ وتسعٌ للقبض — بترتيبها ولا تُقفز',
          'rule'  => 'FTRE-0059/0060: اختبارُ تسلسل — المراحلُ بترتيبها ولا تُقفز',
          'empty' => 'لم تُبذر المراحلُ بعدُ'),

    array('code' => 'tre_unit_roles', 'dir' => 'Finance', 'file' => 'tre_unit_roles.php',
          'title' => 'الأدوار الثمانية داخل وحدة الخزينة', 'icon' => 'fa fa-user-group',
          'table' => 'fin_treasury_roles', 'nature' => 'register', 'role' => 21,
          'basis' => 'derived', 'doc' => 'FIN-TRE-01 §4-2', 'doc_ref' => 'FTRE-0002',
          'order' => 'seq ASC', 'global_ref' => 1,
          'intro' => 'ثمانيةُ أدوارٍ لكلٍّ حسابُه ونطاقُه وسقفُه المعلَن',
          'rule'  => 'FMGR-0021: أمينُ الخزينةِ يُفصل عن منفِّذِ المدفوعاتِ ومُعِدِّ المطابقة',
          'empty' => 'لم تُبذر الأدوارُ بعدُ'),

    array('code' => 'ctrl_doc_registry', 'dir' => 'Finance', 'file' => 'ctrl_doc_registry.php',
          'title' => 'سجل بنود الوثائق وتغطيتها', 'icon' => 'fa fa-list-check',
          'table' => 'gov_doc_registry', 'nature' => 'register', 'role' => 31,
          'basis' => 'derived', 'doc' => 'FIN-OBL-01 OBL-0307', 'doc_ref' => 'OBL-0307',
          'order' => 'doc_code ASC, family ASC, seq ASC', 'global_ref' => 1,
          'intro' => 'كلُّ بندٍ تعلنه الوثائقُ وأثرُه الحيُّ — والفارغُ ثغرةٌ تُرى',
          'rule'  => 'OBL-0307: البندُ المعلَنُ بلا أثرٍ حيٍّ ثغرةٌ تُسجَّل لا تُهمَل',
          'empty' => 'لم يُزامَن السجلُّ بعدُ — شغّل u13_reverse_audit --sync'),

    array('code' => 'ctrl_dept_propagation', 'dir' => 'Finance', 'file' => 'ctrl_dept_propagation.php',
          'title' => 'الأحكام المنتشرة على الإدارات الست عشرة', 'icon' => 'fa fa-share-nodes',
          'table' => 'gov_dept_propagation', 'nature' => 'read', 'role' => 31,
          'basis' => 'derived', 'doc' => 'PROP-01 §6-1', 'doc_ref' => 'PROP-01 §6-1',
          'order' => 'propagated DESC', 'global_ref' => 1,
          'intro' => 'خمسُمئةٍ وثلاثةٌ وعشرون حكمًا منتشرًا في ستَّ عشرةَ إدارة',
          'rule'  => 'PROP-01 §6-1: أُدرجت في السجلاتِ الذريةِ فعلًا — فتُفحص في تدقيقِ كلِّ إدارة',
          'empty' => 'لم يُبذر الانتشارُ بعدُ'),

    array('code' => 'ctrl_supervision', 'dir' => 'Finance', 'file' => 'ctrl_supervision.php',
          'title' => 'إشراف رئيس الحسابات على محاسبي التخصصات', 'icon' => 'fa fa-users-gear',
          'table' => 'fin_accountants', 'nature' => 'register', 'role' => 31,
          'basis' => 'derived', 'doc' => 'FIN-CTRL-01 §4-1', 'doc_ref' => 'FCTRL-0042',
          'where' => "(is_deleted IS NULL OR is_deleted = 0)", 'order' => 'spec_code ASC',
          'intro' => 'توزيعُ الأعمالِ وتحديدُ نطاقِ كلِّ محاسبٍ ومنعُ التداخلِ غيرِ المحكوم',
          'rule'  => 'FCTRL-0042: رئيسُ الحساباتِ يوزّع بالقالبِ لا بمنحٍ فرديٍّ كلَّ مرة',
          'empty' => 'لا محاسبينَ مسنَدين'),

    array('code' => 'ctrl_role_migration', 'dir' => 'Finance', 'file' => 'ctrl_role_migration.php',
          'title' => 'ترحيل الأدوار المالية القديمة', 'icon' => 'fa fa-right-left',
          'table' => 'fin_role_migration', 'nature' => 'register', 'role' => 31,
          'basis' => 'derived', 'doc' => 'FIN-MGR-01 §4-3', 'doc_ref' => 'FMGR-0018',
          'order' => 'old_role_id ASC',
          'intro' => 'إعادةُ تصنيفٍ وبناءٌ فوقَ الموجودِ لا اختراعُ نظامٍ موازٍ',
          'rule'  => 'FMGR-0022: ولا يُحذف دورٌ قديمٌ قبل ترحيلِ حاملِه',
          'empty' => 'لا ترحيلاتٍ مسجَّلة'),

    array('code' => 'ctrl_doc_variance', 'dir' => 'Finance', 'file' => 'ctrl_doc_variance.php',
          'title' => 'مخالفات الوثائق وحسمُها', 'icon' => 'fa fa-scale-unbalanced',
          'table' => 'gov_doc_variance', 'nature' => 'register', 'role' => 31,
          'basis' => 'derived', 'doc' => 'FIN-OBL-01 OBL-0307', 'doc_ref' => 'OBL-0307',
          'order' => 'variance_code ASC', 'global_ref' => 1,
          'intro' => 'تعارضُ الوثيقةِ مع نفسِها ثغرةٌ تُسجَّل ولا تُهمَل — ولكلِّ حسمٍ أساسٌ مكتوب',
          'rule'  => 'OBL-0307: والحدثُ الذي لا مُطلِقَ له ثغرةٌ تُسجَّل عيبًا لا تُهمَل — والقياسُ عليه',
          'empty' => 'لا مخالفاتٍ مكشوفة'),

    /* ══ FIN-TRE-01 — مشتقةٌ (حسمُ V-07) ═════════════════════════════════ */
    array('code' => 'tre_authority_caps', 'dir' => 'Finance', 'file' => 'tre_authority_caps.php',
          'title' => 'سقوف سلطة الالتزام والدفع', 'icon' => 'fa fa-gauge',
          'table' => 'fin_authority_caps', 'nature' => 'register', 'role' => 32,
          'basis' => 'derived', 'doc' => 'FIN-ACC-01 §4-7', 'doc_ref' => 'FACC-0042',
          'order' => 'max_amount DESC',
          'intro' => 'اعتمادُ الالتزامِ أو الدفعِ لا يُمنح بلا سقفٍ معلَنٍ لصاحبِه',
          'rule'  => 'CEO-Y0120: وما تجاوز سقفَ المديرِ الماليِّ والنائبِ يصل الرئيسَ ولا يُنفَّذ قبلَ قرارِه',
          'empty' => 'لا سقوفَ معرَّفة'),

    array('code' => 'tre_sod_matrix', 'dir' => 'Finance', 'file' => 'tre_sod_matrix.php',
          'title' => 'مصفوفة فصل الواجبات الثلاثة عشر', 'icon' => 'fa fa-shield-halved',
          'table' => 'sec_sod_pairs', 'nature' => 'register', 'role' => 31,
          'basis' => 'derived', 'doc' => 'FIN-ACC-01 §4-9', 'doc_ref' => 'FACC-0058',
          'order' => 'code ASC', 'global_ref' => 1,
          'intro' => 'ثلاثةَ عشرَ زوجًا — قيدٌ بنيويٌّ يرفض التكليفَ لا سياسةٌ مكتوبة',
          'rule'  => 'PROP-01 §7-2 ⑩: صفرُ حسابٍ يجمع زوجًا من أزواجِ فصلِ الواجبات',
          'empty' => 'لم تُبذر المصفوفةُ بعدُ'),

    /* ══ IAF-01 — الستَّ عشرةَ (حسمُ V-08) ═══════════════════════════════ */
    array('code' => 'iaf_charter', 'dir' => 'Audit', 'file' => 'iaf_charter.php',
          'title' => 'ميثاق المراجعة الداخلية', 'icon' => 'fa fa-scroll',
          'table' => 'iaf_charter', 'nature' => 'document', 'role' => 33,
          'basis' => 'derived', 'doc' => 'IAF-01 §4-1', 'doc_ref' => 'IAF-0007',
          'intro' => 'الغرضُ والسلطةُ والمسؤوليةُ والاستقلال — معتمدًا من الجهةِ المشرفة',
          'rule'  => 'IAF-0004: لا تتبع الماليةَ ولا رئيسَ الحساباتِ ولا الحوكمةَ في إصدارِ أحكامها',
          'empty' => 'لا ميثاقَ معتمدٌ بعدُ — ولا كونَ رقابيٌّ بلا ميثاق'),

    array('code' => 'iaf_independence', 'dir' => 'Audit', 'file' => 'iaf_independence.php',
          'title' => 'إقرارات الاستقلال وتعارض المصالح', 'icon' => 'fa fa-user-check',
          'table' => 'iaf_independence', 'nature' => 'document', 'role' => 33,
          'basis' => 'derived', 'doc' => 'IAF-01 §4-1', 'doc_ref' => 'IAF-0009',
          'order' => 'declared_at DESC',
          'intro' => 'إقرارٌ سنويٌّ لكلِّ مراجعٍ وقبلَ كلِّ تكليف',
          'rule'  => 'IAF-0009: ولا تكليفَ بمهمةٍ بلا إقرارٍ سارٍ قبلَه',
          'empty' => 'لا إقراراتِ استقلالٍ مسجَّلة'),

    array('code' => 'iaf_universe', 'dir' => 'Audit', 'file' => 'iaf_universe.php',
          'title' => 'الكون الرقابي', 'icon' => 'fa fa-globe',
          'table' => 'iaf_universe', 'nature' => 'register', 'role' => 33,
          'basis' => 'derived', 'doc' => 'IAF-01 §4-3', 'doc_ref' => 'IAF-0013',
          'order' => 'risk_score DESC',
          'intro' => 'نطاقاتُ المراجعةِ كلُّها بدرجةِ خطرِ كلٍّ وتاريخِ آخرِ مراجعة',
          'rule'  => 'IAF-0014: التقييمُ السنويُّ للمخاطرِ أساسُ الخطةِ لا الاجتهاد',
          'empty' => 'لم يُبنَ الكونُ الرقابيُّ بعدُ — ولا خطةَ بلا كون'),

    array('code' => 'iaf_plan', 'dir' => 'Audit', 'file' => 'iaf_plan.php',
          'title' => 'خطة المراجعة السنوية', 'icon' => 'fa fa-calendar-check',
          'table' => 'iaf_plan', 'nature' => 'document', 'role' => 33,
          'basis' => 'derived', 'doc' => 'IAF-01 §4-3', 'doc_ref' => 'IAF-0015',
          'order' => 'plan_year DESC',
          'intro' => 'خطةٌ مبنيةٌ على المخاطرِ معتمدةٌ من الجهةِ المشرفة',
          'rule'  => 'IAF-0044: لا مهمةَ بلا خطةٍ ولا خطةَ بلا كونٍ رقابيٍّ ولا كونَ بلا ميثاق',
          'empty' => 'لا خطةَ معتمدةٌ بعدُ'),

    array('code' => 'iaf_engagements', 'dir' => 'Audit', 'file' => 'iaf_engagements.php',
          'title' => 'مهام المراجعة', 'icon' => 'fa fa-briefcase',
          'table' => 'iaf_engagements', 'nature' => 'document', 'role' => 33,
          'basis' => 'derived', 'doc' => 'IAF-01 §4-5', 'doc_ref' => 'IAF-0016',
          'order' => 'id DESC',
          'intro' => 'مراجعاتٌ ماليةٌ وتشغيليةٌ وتقنيةٌ والتزامية',
          'rule'  => 'IAF-0006: ولا يصبح المراجعُ جزءًا مما سيراجعه لاحقًا',
          'empty' => 'لا مهامَّ مراجعةٍ مفتوحة'),

    array('code' => 'iaf_workpapers', 'dir' => 'Audit', 'file' => 'iaf_workpapers.php',
          'title' => 'أوراق العمل والأدلة', 'icon' => 'fa fa-folder-open',
          'table' => 'iaf_workpapers', 'nature' => 'register', 'role' => 33,
          'basis' => 'derived', 'doc' => 'IAF-01 §4-4', 'doc_ref' => 'IAF-0037',
          'order' => 'captured_at DESC',
          'intro' => 'نسخُ أدلةٍ غيرُ قابلةٍ للتعديلِ ببصمةِ كلٍّ',
          'rule'  => 'IAF-0037: سحبُ الأدلةِ وحفظُ نسخِ مراجعةٍ غيرِ قابلةٍ للتعديل',
          'empty' => 'لا أوراقَ عملٍ ملتقَطة'),

    array('code' => 'iaf_findings', 'dir' => 'Audit', 'file' => 'iaf_findings.php',
          'title' => 'ملاحظات المراجعة', 'icon' => 'fa fa-flag',
          'table' => 'iaf_findings', 'nature' => 'document', 'role' => 33,
          'basis' => 'derived', 'doc' => 'IAF-01 §4-3', 'doc_ref' => 'IAF-0025',
          'order' => 'raised_at DESC',
          'intro' => 'كلُّ ملاحظةٍ بخطورتِها ومهلةِ ردِّها وخطةِ معالجتها',
          'rule'  => 'لا تُغلق ملاحظةٌ بلا دليلٍ يقبله المراجعُ — ولا تُغلق من الإدارةِ نفسِها',
          'empty' => 'لا ملاحظاتٍ مفتوحة',
          'actions_src' => "\n    'actions'    => array(\n"
            . "        'accept_evidence' => array(\n"
            . "            'code'  => 'iaf.evidence.accept',\n"
            . "            'label' => 'قبولُ دليلٍ على ملاحظة',\n"
            . "            'rule'  => 'الدليلُ يقبله المراجعُ — وقبولُه شرطُ الإغلاق',\n"
            . "            'fields' => array('finding_no' => 'رقمُ الملاحظة', 'evidence_ref' => 'مرجعُ الدليل'),\n"
            . "            'run' => function (\$conn, \$co, \$uid, \$in) {\n"
            . "                require_once __DIR__ . '/../app/Services/Audit/InternalAuditService.php';\n"
            . "                return \\App\\Services\\Audit\\InternalAuditService::acceptEvidence(\$conn, array(\n"
            . "                    'company_id' => \$co, 'finding_no' => (string) (\$in['finding_no'] ?? ''),\n"
            . "                    'evidence_ref' => (string) (\$in['evidence_ref'] ?? ''), 'accepted_by' => \$uid));\n"
            . "            }),\n"
            . "        'close' => array(\n"
            . "            'code'  => 'iaf.finding.close',\n"
            . "            'label' => 'إغلاقُ ملاحظة',\n"
            . "            'rule'  => 'CEO-Y0125: ولا يملك الرئيسُ إغلاقَها بلا دليل · ولا تُغلق من الإدارةِ نفسِها',\n"
            . "            'fields' => array('finding_no' => 'رقمُ الملاحظة'),\n"
            . "            'run' => function (\$conn, \$co, \$uid, \$in) {\n"
            . "                require_once __DIR__ . '/../app/Services/Audit/InternalAuditService.php';\n"
            . "                return \\App\\Services\\Audit\\InternalAuditService::closeFinding(\$conn, array(\n"
            . "                    'company_id' => \$co, 'finding_no' => (string) (\$in['finding_no'] ?? ''),\n"
            . "                    'closed_by' => \$uid));\n"
            . "            }),\n"
            . "    ),"),

    array('code' => 'iaf_responses', 'dir' => 'Audit', 'file' => 'iaf_responses.php',
          'title' => 'ردود الإدارات على الملاحظات', 'icon' => 'fa fa-comments',
          'table' => 'iaf_findings', 'nature' => 'read', 'role' => 33,
          'basis' => 'derived', 'doc' => 'IAF-01 §4-3', 'doc_ref' => 'IAF-0027',
          'where' => "state IN ('responded','in_remediation','evidence_submitted')",
          'order' => 'responded_at DESC',
          'intro' => 'ردُّ الإدارةِ إلزاميٌّ بمهلة — والسكوتُ يُصعَّد للجهةِ المشرفة',
          'rule'  => 'BF-15: والردُّ إلزاميٌّ بمهلةٍ · والسكوتُ يُصعَّد',
          'empty' => 'لا ردودَ واردةً بعدُ'),

    array('code' => 'iaf_action_plans', 'dir' => 'Audit', 'file' => 'iaf_action_plans.php',
          'title' => 'خطط المعالجة ومتابعتها', 'icon' => 'fa fa-list-check',
          'table' => 'iaf_findings', 'nature' => 'read', 'role' => 33,
          'basis' => 'derived', 'doc' => 'IAF-01 §4-3', 'doc_ref' => 'IAF-0028',
          'where' => "action_plan IS NOT NULL AND state <> 'closed'",
          'order' => 'action_due ASC',
          'intro' => 'الاتفاقُ على خططِ المعالجةِ ومتابعةُ تنفيذها بمهلةِ كلٍّ',
          'rule'  => 'IAF-0028: المتابعةُ للمراجعِ — والإدارةُ تنفّذ ولا تشهد على نفسها',
          'empty' => 'لا خططَ معالجةٍ قائمة'),

    array('code' => 'iaf_closure', 'dir' => 'Audit', 'file' => 'iaf_closure.php',
          'title' => 'محاضر الإغلاق', 'icon' => 'fa fa-circle-check',
          'table' => 'iaf_findings', 'nature' => 'read', 'role' => 33,
          'basis' => 'derived', 'doc' => 'IAF-01 §4-3', 'doc_ref' => 'IAF-0029',
          'where' => "state = 'closed'", 'order' => 'closed_at DESC',
          'intro' => 'ما أُغلق بدليلٍ قَبِله المراجعُ — ومن أغلقه ومتى',
          'rule'  => 'CEO-Y0125: ولا يملك الرئيسُ إغلاقَ ملاحظةٍ بلا دليلٍ يقبله المراجع',
          'empty' => 'لا ملاحظاتٍ مُغلقةٍ بعدُ'),

    array('code' => 'iaf_escalations', 'dir' => 'Audit', 'file' => 'iaf_escalations.php',
          'title' => 'المصعَّد إلى الجهة المشرفة', 'icon' => 'fa fa-arrow-up-right-dots',
          'table' => 'iaf_findings', 'nature' => 'read', 'role' => 33,
          'basis' => 'derived', 'doc' => 'IAF-01 §2-2', 'doc_ref' => 'IAF-0041',
          'where' => "state = 'escalated'", 'order' => 'escalated_at DESC',
          'intro' => 'ما تجاوز مهلتَه فصُعِّد آليًّا — ولا يملك أحدٌ منعَه',
          'rule'  => 'IAF §2-2: والتصعيدُ آليٌّ بالمهلةِ ويصل الجهةَ المشرفةَ مباشرةً',
          'empty' => 'لا ملاحظاتٍ مصعَّدة'),

    array('code' => 'iaf_reports', 'dir' => 'Audit', 'file' => 'iaf_reports.php',
          'title' => 'تقارير المراجعة للجهة المشرفة', 'icon' => 'fa fa-file-lines',
          'table' => 'exec_audit_reports', 'nature' => 'document', 'role' => 33,
          'basis' => 'derived', 'doc' => 'IAF-01 §4-3', 'doc_ref' => 'IAF-0030',
          'order' => 'issued_at DESC',
          'intro' => 'التقريرُ يصل الجهةَ المشرفةَ مباشرةً بمسارٍ مسجَّلٍ يُفحص',
          'rule'  => 'CEO-Y0119: ولا يمرُّ بالماليةِ ولا بالحوكمةِ ولا بمن يُراجَع',
          'empty' => 'لا تقاريرَ صادرة'),

    array('code' => 'iaf_access_log', 'dir' => 'Audit', 'file' => 'iaf_access_log.php',
          'title' => 'سجل اطّلاع المراجع الحساس', 'icon' => 'fa fa-eye',
          'table' => 'iaf_access_log', 'nature' => 'read', 'role' => 33,
          'basis' => 'derived', 'doc' => 'IAF-01 §4-4', 'doc_ref' => 'IAF-0036',
          'order' => 'accessed_at DESC',
          'intro' => 'كلُّ اطّلاعٍ حساسٍ مسجَّلٌ بمرجعِ المهمةِ التي تُبرِّره',
          'rule'  => 'OBL-0127: فالوظيفةُ الرقابيةُ مراقَبةٌ أيضًا',
          'empty' => 'لا اطّلاعاتٍ مسجَّلة'),

    array('code' => 'iaf_competencies', 'dir' => 'Audit', 'file' => 'iaf_competencies.php',
          'title' => 'اختصاصات المراجعة العشرون', 'icon' => 'fa fa-clipboard-list',
          'table' => 'iaf_competencies', 'nature' => 'register', 'role' => 33,
          'basis' => 'derived', 'doc' => 'IAF-01 §4-3', 'doc_ref' => 'IAF-0012',
          'order' => 'seq ASC', 'global_ref' => 1,
          'intro' => 'عشرون اختصاصًا من الميثاقِ إلى تقييمِ الجودة',
          'rule'  => 'IAF §4-3: ولكلِّ اختصاصٍ شاهدُ قبولٍ مكتوب',
          'empty' => 'لم تُبذر الاختصاصاتُ بعدُ'),

    array('code' => 'iaf_authorities', 'dir' => 'Audit', 'file' => 'iaf_authorities.php',
          'title' => 'صلاحيات المراجع داخل النظام', 'icon' => 'fa fa-key',
          'table' => 'iaf_authorities', 'nature' => 'register', 'role' => 33,
          'basis' => 'derived', 'doc' => 'IAF-01 §4-4', 'doc_ref' => 'IAF-0032',
          'order' => 'seq ASC', 'global_ref' => 1,
          'intro' => 'اثنتا عشرةَ صلاحية — قراءةٌ مستقلةٌ بلا كتابةٍ على الأصول',
          'rule'  => 'IAF-0043: ولا يملك كتابةً على السجلاتِ التشغيليةِ أو الماليةِ الأصلية',
          'empty' => 'لم تُبذر الصلاحياتُ بعدُ'),

    array('code' => 'iaf_quality', 'dir' => 'Audit', 'file' => 'iaf_quality.php',
          'title' => 'تقييم جودة المراجعة الدوري', 'icon' => 'fa fa-award',
          'table' => 'iaf_quality_reviews', 'nature' => 'document', 'role' => 33,
          'basis' => 'derived', 'doc' => 'IAF-01 §4-1', 'doc_ref' => 'IAF-0008',
          'order' => 'reviewed_at DESC',
          'intro' => 'تقييمٌ داخليٌّ دوريٌّ وخارجيٌّ عند الانطباق',
          'rule'  => 'IAF-0031: والوظيفةُ التي تقيّم غيرَها تُقيَّم',
          'empty' => 'لا تقييماتِ جودةٍ مسجَّلة'),
    );
}
