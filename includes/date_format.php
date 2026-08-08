<?php
/**
 * includes/date_format.php — تنسيقُ التاريخِ الموحَّدُ للعرض
 * ═══════════════════════════════════════════════════════════════════════════
 * المرجع: UI-DEF-11 «الخطوطُ والأرقامُ والتواريخُ غيرُ متسقة — تاريخٌ إنجليزيٌّ
 * في واجهةٍ عربيةٍ وأرقامٌ مختلطة» · وحكمُ الوثيقة: «الغربيةُ للتخزينِ
 * والميلاديُّ للحساب».
 *
 * القاعدةُ التي ينفّذها هذا الملف:
 *   · التخزينُ والحسابُ بالأرقامِ الغربيةِ والتقويمِ الميلاديِّ — لا يُمسّان.
 *   · العرضُ وحدَه يمرُّ على دالةٍ واحدةٍ، فيتغيّر الشكلُ من موضعٍ واحدٍ لا من
 *     ثلاثمئةِ استدعاءٍ متفرق.
 *
 * سجلُّ الدَّينِ VT-07 يقيس ما لم يمرَّ بعدُ على هذه الدالة، وسقّاطتُه تمنع
 * ولادةَ استدعاءٍ متفرقٍ جديد (tools/u12_debt_ratchet.php).
 */

if (!function_exists('ems_fmt_date')) {
    /**
     * يعرض تاريخًا بصيغةٍ واحدةٍ عبر النظام.
     *
     * @param mixed  $value  تاريخٌ نصًّا أو DateTimeInterface أو طابعًا زمنيًّا،
     *                       أو null/'' فيعود بالبديلِ المعلَن.
     * @param string $style  'date' يومًا · 'datetime' يومًا ووقتًا · 'time' وقتًا
     *                       · 'month' شهرًا · 'iso' كما يُخزَّن.
     * @param string $empty  ما يُعرض حين لا تاريخَ — ولا يُترك فارغًا صامتًا.
     * @return string نصٌّ جاهزٌ للعرض (لم يُهرَّب — التهريبُ على المستدعي).
     */
    function ems_fmt_date($value, $style = 'date', $empty = '—')
    {
        if ($value === null || $value === '' || $value === '0000-00-00'
            || $value === '0000-00-00 00:00:00') {
            return $empty;
        }
        if ($value instanceof DateTimeInterface) {
            $ts = $value->getTimestamp();
        } elseif (is_int($value) || (is_string($value) && ctype_digit($value) && strlen($value) >= 9)) {
            $ts = (int) $value;
        } else {
            $ts = strtotime((string) $value);
            if ($ts === false) { return $empty; }
        }
        switch ($style) {
            case 'datetime': return date('Y-m-d H:i', $ts);
            case 'time':     return date('H:i', $ts);
            case 'month':    return date('Y-m', $ts);
            case 'iso':      return date('Y-m-d\TH:i:s', $ts);
            case 'date':
            default:         return date('Y-m-d', $ts);
        }
    }
}

if (!function_exists('ems_fmt_now')) {
    /** لحظةُ القراءةِ بالصيغةِ الموحَّدةِ نفسِها — للسطرِ السياقيِّ وبطاقاتِ المؤشر */
    function ems_fmt_now($style = 'date')
    {
        return ems_fmt_date(time(), $style);
    }
}
