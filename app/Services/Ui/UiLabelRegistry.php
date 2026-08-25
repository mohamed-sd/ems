<?php
namespace App\Services\Ui;

require_once __DIR__ . '/UiPurity.php';

/**
 * app/Services/Ui/UiLabelRegistry.php
 *   سجلُّ المسمّياتِ المركزيُّ — REPAIR01 · W06 §٤-٣
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **الغاية**: «يُمنع بعده كتابةُ اسمِ حقلٍ أو شاشةٍ يدويًّا في ملفّ — فلا
 *   يظهر المصطلحُ بثلاثِ صيغٍ في ثلاثِ شاشات». فالمفتاحُ التقنيُّ واحدٌ،
 *   والمسمّى يُقرأ منه لا يُكتب في الشاشة.
 *
 * ◆ **والردُّ هنا لا في المخطَّط** (‏_CONTEXT §قواعد القياس ٢): `CHECK` يمنع
 *   التشكيلَ يجعل حاجبَ «تشكيلٌ ظاهر» **أعمى بالبناء** — ما يمنعه المخطَّطُ
 *   لا يُختبَر. فالردُّ في `register()` **ويُقيَّد في `repair01_w6_reject_log`**،
 *   فيبقى قابلًا للكسرِ في الفحصِ السلبيِّ وقابلًا للقياسِ في الرحلة.
 *
 * ◆ **والعتبةُ من السجلِّ لا من الشيفرة** (‏W06 §٥): `thresholds()` تقرأ
 *   `repair01_w6_thresholds` — ولا رقمَ مكتوبٌ هنا.
 *
 * ◆ **وصنفُ الظهورِ يُنفَّذ لا يُعلَن** (‏W06 §٤-٧): `label()` ترفض تصييرَ
 *   مسمًّى `DEVELOPER_ONLY` في سياقِ مستخدمٍ نهائيّ، وتقيّد الرفض.
 * ═══════════════════════════════════════════════════════════════════════════
 */
class UiLabelRegistry
{
    /** صنفُ الظهورِ الممنوعُ على المستخدمِ النهائيّ (‏W06 §٤-٧). */
    public const DEVELOPER_ONLY = 'DEVELOPER_ONLY';

    /** أصنافُ الظهورِ الأربعةُ — مُعلَنةٌ لا مُخمَّنة. */
    public static function visibilityClasses()
    {
        return array('USER_VISIBLE', 'AUDITOR_VISIBLE', 'ADMIN_VISIBLE', 'DEVELOPER_ONLY');
    }

    /** حالاتُ المسمّى الأربع — آلةُ حالةٍ لا نصٌّ حرّ (§٧). */
    public static function labelStates()
    {
        return array('DRAFT', 'ACTIVE', 'DEPRECATED', 'REPLACED');
    }

    /** حدودُ الطولِ من السجلِّ — لا رقمَ في الشيفرة. */
    public static function thresholds(\mysqli $conn)
    {
        static $cache = null;
        if ($cache !== null) { return $cache; }
        $cache = array();
        $r = @$conn->query("SELECT threshold_key, value_no FROM repair01_w6_thresholds");
        while ($r && $x = $r->fetch_row()) { $cache[(string) $x[0]] = (int) $x[1]; }
        return $cache;
    }

    /** حدُّ السياق: `BUTTON` ⇐ `MAX_LEN_BUTTON`. صفرٌ = لا حدَّ مسجَّل. */
    public static function maxLen(\mysqli $conn, $context)
    {
        $t = self::thresholds($conn);
        $k = 'MAX_LEN_' . strtoupper((string) $context);
        return isset($t[$k]) ? (int) $t[$k] : 0;
    }

    /**
     * تقييدُ رفضٍ — «تُرفَض **ويُقيَّد الرفض**» (‏§٦-أ).
     * الردُّ بلا قيدٍ ادّعاءٌ لا دليل.
     */
    public static function logReject(\mysqli $conn, $key, $attempted, $code, $detail, $caller = '', $runId = '')
    {
        $st = @$conn->prepare("INSERT INTO repair01_w6_reject_log
              (technical_key, attempted, reject_code, reject_detail, caller, actor_id, run_id)
              VALUES (?,?,?,?,?,?,?)");
        if (!$st) { return false; }
        $actor = 0;
        if (isset($_SESSION['user']['id'])) { $actor = (int) $_SESSION['user']['id']; }
        $key = mb_substr((string) $key, 0, 160);
        $attempted = mb_substr((string) $attempted, 0, 600);
        $detail = mb_substr((string) $detail, 0, 400);
        $caller = mb_substr((string) $caller, 0, 190);
        $runId = mb_substr((string) $runId, 0, 48);
        $st->bind_param('sssssis', $key, $attempted, $code, $detail, $caller, $actor, $runId);
        $ok = $st->execute();
        $st->close();
        return $ok;
    }

    /**
     * تسجيلُ مسمًّى. **يُرفض المشكولُ والمصطلحُ التقنيُّ والمعادلةُ والزائدُ
     * طولًا** — ويُقيَّد الرفضُ بسببِه.
     *
     * @return array{ok:bool, code:string, detail:string}
     */
    public static function register(\mysqli $conn, $technicalKey, $label, array $opt = array())
    {
        $technicalKey = trim((string) $technicalKey);
        $label = trim((string) $label);
        $context = isset($opt['allowed_context']) ? (string) $opt['allowed_context'] : '';
        $caller = isset($opt['caller']) ? (string) $opt['caller'] : '';
        $runId = isset($opt['run_id']) ? (string) $opt['run_id'] : '';

        if ($technicalKey === '' || $label === '') {
            self::logReject($conn, $technicalKey, $label, 'EMPTY', 'مفتاح أو مسمى فارغ', $caller, $runId);
            return array('ok' => false, 'code' => 'EMPTY', 'detail' => 'مفتاح أو مسمى فارغ');
        }

        /* ══ ردٌّ صلبٌ وردٌّ ليّنٌ — والفرقُ بينهما قرارٌ لا سهو ═══════════════
           **الصلب** (تشكيلٌ · مصطلحٌ تقنيٌّ · معادلة): خرقٌ للقاعدةِ الدستوريّة،
           لا يدخل السجلَّ ويُقيَّد رفضُه.
           **الليّن** (طولٌ زائد): عتبةُ جودةٍ لا قاعدةٌ دستوريّة. **ورفضُ
           المسمّى الطويلِ عن السجلِّ يُخفيه لا يُصلحه** — يصير اسمًا حيًّا بلا
           صفٍّ، فيقرأ الفاحصُ «اسمٌ خارجَ السجلّ» ويقرأ «طولٌ زائد ٠» معًا،
           وكلاهما كاذب. فيُسجَّل **ويُقيَّد طولُه** ويُعَدُّ في سقّاطةِ الدَّين. */
        $primaryContext = trim(explode(' ', str_replace(array(',', '·'), ' ', $context))[0]);
        $max = self::maxLen($conn, $primaryContext);
        $v = UiPurity::verdict($label, $max);
        $hard = array();
        $soft = array();
        foreach ($v['defects'] as $d) {
            if ($d === 'TOO_LONG') { $soft[] = $d; } else { $hard[] = $d; }
        }
        if ($hard) {
            $first = $hard[0];
            $code = strpos($first, 'TECH_TERM') === 0 ? 'TECH_TERM' : $first;
            self::logReject($conn, $technicalKey, $label, $code, implode(' · ', $hard), $caller, $runId);
            return array('ok' => false, 'code' => $code, 'detail' => implode(' · ', $hard));
        }
        if ($soft) {
            self::logReject($conn, $technicalKey, $label, 'TOO_LONG',
                'الحد ' . $max . ' والمقيس ' . mb_strlen(trim($label), 'UTF-8')
                . ' — سجل ووسم دينا (‏' . $primaryContext . ')', $caller, $runId);
        }

        $cols = array(
            'arabic_ui_label'   => $label,
            'short_label'       => isset($opt['short_label']) ? (string) $opt['short_label'] : '',
            'allowed_context'   => $context,
            'sensitive'         => !empty($opt['sensitive']) ? '1' : '0',
            'deprecated_label'  => isset($opt['deprecated_label']) ? (string) $opt['deprecated_label'] : '',
            'replacement_label' => isset($opt['replacement_label']) ? (string) $opt['replacement_label'] : '',
            'visibility_class'  => isset($opt['visibility_class']) ? (string) $opt['visibility_class'] : 'USER_VISIBLE',
            'label_state'       => isset($opt['label_state']) ? (string) $opt['label_state'] : 'ACTIVE',
            'source_table'      => isset($opt['source_table']) ? (string) $opt['source_table'] : '',
            'source_column'     => isset($opt['source_column']) ? (string) $opt['source_column'] : '',
            'source_key'        => isset($opt['source_key']) ? (string) $opt['source_key'] : '',
            'owner_code'        => isset($opt['owner_code']) ? (string) $opt['owner_code'] : '',
            'rule_id'           => isset($opt['rule_id']) ? (string) $opt['rule_id'] : '',
            'src_ref'           => isset($opt['src_ref']) ? (string) $opt['src_ref'] : '',
            'origin'            => isset($opt['origin']) ? (string) $opt['origin'] : 'W06',
        );
        $names = array_keys($cols);
        $ph = implode(',', array_fill(0, count($names) + 1, '?'));
        $upd = array();
        foreach ($names as $n) { $upd[] = "`$n` = VALUES(`$n`)"; }
        $sql = "INSERT INTO repair01_ui_labels (`technical_key`,`" . implode('`,`', $names) . "`)
                VALUES ($ph) ON DUPLICATE KEY UPDATE " . implode(', ', $upd);
        $st = $conn->prepare($sql);
        if (!$st) { return array('ok' => false, 'code' => 'DB', 'detail' => $conn->error); }
        /* الكلُّ نصٌّ — و`sensitive` يُمرَّر '0'/'1' فتقبله القاعدةُ في TINYINT.
           (‏وحسابُ موضعِ الحرفِ بفهرسٍ مشتقٍّ من العدِّ يتفرّق عند أوّلِ عمودٍ
            يُضاف — فالنصُّ للكلِّ أسلمُ من فهرسٍ يُخمَّن.) */
        $vals = array_merge(array($technicalKey), array_values($cols));
        $types = str_repeat('s', count($vals));
        $bind = array($types);
        foreach ($vals as $i => $x) { $bind[] = &$vals[$i]; }
        call_user_func_array(array($st, 'bind_param'), $bind);
        $ok = $st->execute();
        $err = $st->error;
        $st->close();
        return $ok ? array('ok' => true, 'code' => 'OK', 'detail' => '')
                   : array('ok' => false, 'code' => 'DB', 'detail' => $err);
    }

    /**
     * قراءةُ مسمًّى معتمد. الغائبُ عن السجلِّ **يُقيَّد رفضُه** ويُعاد
     * المُنقّى من الاحتياطيِّ — فلا تنكسر شاشةٌ، ولا يمرُّ اسمٌ خارجَ السجلِّ
     * بلا أثر.
     */
    public static function label(\mysqli $conn, $technicalKey, $fallback = '', $forUser = true)
    {
        $st = @$conn->prepare("SELECT arabic_ui_label, visibility_class, label_state
                                 FROM repair01_ui_labels WHERE technical_key = ? LIMIT 1");
        if (!$st) { return UiPurity::purifyGenerated($fallback); }
        $technicalKey = (string) $technicalKey;
        $st->bind_param('s', $technicalKey);
        $st->execute();
        $row = $st->get_result()->fetch_assoc();
        $st->close();

        if (!$row) {
            self::logReject($conn, $technicalKey, $fallback, 'NOT_REGISTERED',
                'مسمى يطلب وليس في السجل المركزي', 'UiLabelRegistry::label');
            return UiPurity::purifyGenerated($fallback);
        }
        if ($forUser && $row['visibility_class'] === self::DEVELOPER_ONLY) {
            self::logReject($conn, $technicalKey, $row['arabic_ui_label'], 'DEVELOPER_ONLY',
                'مسمى تقني طلب في سياق مستخدم نهائي', 'UiLabelRegistry::label');
            return '';
        }
        return (string) $row['arabic_ui_label'];
    }

    /**
     * عرضُ رمزٍ داخليّ (‏W06 §٤-٦): `NEEDS_SOURCE` ⇐ «يحتاج مستندًا».
     * والرمزُ الذي لا عرضَ له **يُقيَّد** ولا يُصيَّر خامًّا.
     */
    public static function display(\mysqli $conn, $rawCode, $short = false)
    {
        $rawCode = trim((string) $rawCode);
        if ($rawCode === '') { return ''; }
        $st = @$conn->prepare("SELECT display_ar, display_short FROM repair01_w6_code_dict
                                WHERE raw_code = ? LIMIT 1");
        if (!$st) { return $rawCode; }
        $st->bind_param('s', $rawCode);
        $st->execute();
        $row = $st->get_result()->fetch_assoc();
        $st->close();
        if (!$row) {
            self::logReject($conn, 'code:' . $rawCode, $rawCode, 'RAW_CODE',
                'رمز داخلي بلا عرض في القاموس', 'UiLabelRegistry::display');
            return $rawCode;
        }
        if ($short && trim((string) $row['display_short']) !== '') { return (string) $row['display_short']; }
        return (string) $row['display_ar'];
    }

    /** هل الرمزُ الداخليُّ معروضٌ في القاموس؟ (‏قراءةٌ بلا تقييدِ رفض). */
    public static function hasDisplay(\mysqli $conn, $rawCode)
    {
        $st = @$conn->prepare("SELECT 1 FROM repair01_w6_code_dict WHERE raw_code = ? LIMIT 1");
        if (!$st) { return false; }
        $rawCode = (string) $rawCode;
        $st->bind_param('s', $rawCode);
        $st->execute();
        $has = (bool) $st->get_result()->fetch_row();
        $st->close();
        return $has;
    }

    /**
     * تقاعدُ مسمًّى مع بديلِه — **ولا حذف**: الصيغةُ القديمةُ دليلٌ يُقاس
     * عليه أنَّ الحيَّ لم يعد يحملها.
     */
    public static function deprecate(\mysqli $conn, $technicalKey, $oldLabel, $newLabel)
    {
        $st = @$conn->prepare("UPDATE repair01_ui_labels
                                  SET deprecated_label = ?, replacement_label = ?, label_state = 'REPLACED'
                                WHERE technical_key = ?");
        if (!$st) { return false; }
        $oldLabel = (string) $oldLabel; $newLabel = (string) $newLabel; $technicalKey = (string) $technicalKey;
        $st->bind_param('sss', $oldLabel, $newLabel, $technicalKey);
        $ok = $st->execute();
        $st->close();
        return $ok;
    }
}
