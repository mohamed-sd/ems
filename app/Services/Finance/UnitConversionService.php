<?php
/**
 * خدمة التحويل المالي على سكّة السلسلة — UnitConversionService (E-01×E-02)
 * ───────────────────────────────────────────────────────────────────────────
 * قرار المالك 2026-08-05: «مسار الاعتماد الواحد = السلسلة الخماسية وtimesheet
 * مرآة». هذه الخدمة هي **الحلقة الخامسة** فعليًّا: تحويل الوحدة المعتمدة
 * (sales_approved) إلى أثرها المالي، وختمُ السلسلة (converted + converted_at)
 * **ذرّيًّا مع المروحة** في معاملة واحدة — فلا مالَ بلا ختمٍ ولا ختمَ بلا مال.
 *
 * نقطة تنفيذٍ واحدة (E-01 §6-1): شاشة Finance/unit_records_fin وأداة الكنس
 * tools/e01_convert_sweep تناديان هذه الخدمةَ ولا تعيدان بناء منطقها.
 *
 * حدود الحكم:
 *  - الأهلية تُقرأ من السلسلة (state='sales_approved') لا من timesheet_approvals —
 *    فالسلسلة هي مصدر الحقيقة والمرآة للتسعير التعاقدي (العقود لا تسكن السجل).
 *  - المروحة كلُّها متعذّرة (صفر أثر وصفر تبنٍّ) ⇒ لا ختم: يبقى الصف في
 *    الطابور بأسبابه المعلنة («لا تلفيق» — الفجوة تُرى لا تُدفن).
 *  - أثرٌ ماليٌّ قائمٌ سلفًا (تبنٍّ) ⇒ يُختم الصف: يشفي انحرافَ سكّتين لا يكرّر مالًا.
 */

namespace App\Services\Finance;

require_once dirname(__DIR__) . '/EffectFanout.php';
require_once dirname(__DIR__, 2) . '/Core/TenantGateException.php';
require_once dirname(__DIR__, 2) . '/Core/TenantRegistry.php';
require_once dirname(__DIR__, 2) . '/Core/TenantContext.php';
require_once dirname(__DIR__, 2) . '/Core/TenantDb.php';

use App\Services\EffectFanout;

class UnitConversionService
{
    /**
     * تحويل يومٍ واحد: مروحةُ الأثر ثم ختمُ السلسلة في المعاملة نفسها.
     *
     * @param \mysqli            $conn  الاتصال
     * @param \App\Core\TenantDb $gate  بوابةُ عزلٍ لشركة الصف (المعاملة تُفتح هنا)
     * @param int                $tsId  معرّف صف الدوام (مفتاح التسعير والعطالة)
     * @param int                $actor المستخدم الفاعل
     * @return array{ok:bool,converted:bool,effects:int,adopted:int,reason:string,skipped:array}
     */
    public static function convertOne(\mysqli $conn, $gate, $tsId, $actor)
    {
        $tsId = intval($tsId);
        $out = array('ok' => false, 'converted' => false, 'effects' => 0, 'adopted' => 0,
                     'reason' => '', 'skipped' => array());
        try {
            $gate->runInTransaction(function ($g) use (&$out, $conn, $tsId, $actor) {
                // ① قفلُ صف السلسلة عبر الجسر — الأهلية داخل المعاملة (لا سباق)
                $uuid = 'ts:' . $tsId;
                $st = $conn->prepare("SELECT id, company_id, state FROM unit_entries
                                       WHERE sync_uuid = ? LIMIT 1 FOR UPDATE");
                $st->bind_param('s', $uuid);
                $st->execute();
                $ue = $st->get_result()->fetch_assoc();
                $st->close();
                if (!$ue) {
                    throw new \RuntimeException('لا صفَّ سلسلةٍ بجسر ' . $uuid . ' — شغّل أداة الجسر أولًا');
                }
                if ((string) $ue['state'] !== 'sales_approved') {
                    throw new \RuntimeException('حالة السلسلة ' . $ue['state'] . ' — لا يُحوَّل إلا sales_approved');
                }

                // ② المروحة (عطالتها fin_event_links — إعادة النداء لا تكرر)
                $res = EffectFanout::forTimesheetId($conn, $g, $tsId, intval($actor));
                $out['effects'] = count($res['effects']);
                $out['adopted'] = isset($res['adopted']) ? count($res['adopted']) : 0;
                // الأثرُ المولَّد **قبل** هذه المعاملة عطالةٌ صامتة (continue في
                // المروحة) لا يظهر في effects/adopted — يُحسب تبنّيًا هنا وإلا
                // بقي انحرافُ «مالٌ بلا ختم» بلا شفاء (فحص e02 ⑥).
                $done = EffectFanout::existingEffects($g, $tsId, EffectFanout::SOURCE_TIMESHEET);
                foreach (array('revenue_event', 'supplier_due', 'cost_record') as $money) {
                    if (isset($done[$money])) { $out['adopted']++; }
                }
                $out['skipped'] = $res['skipped'];
                if (!empty($res['revision_pending'])) {
                    throw new \RuntimeException('مراجعة كمية معلّقة — التصحيح لمحرّك العكسيات');
                }
                if ($out['effects'] + $out['adopted'] === 0) {
                    // كلُّ الآثار متعذّرة بأسبابها — لا ختم ولا فشل: يبقى في الطابور
                    $reasons = array();
                    foreach (array_slice($res['skipped'], 0, 2) as $s) { $reasons[] = $s['reason']; }
                    $out['ok'] = true;
                    $out['reason'] = 'صفر أثرٍ قابلٍ للتوليد: ' . implode(' · ', $reasons);
                    return;
                }

                // ③ ختمُ السلسلة — WHERE بالحالة يمنع الختم المزدوج
                $ueId = intval($ue['id']);
                $st = $conn->prepare("UPDATE unit_entries
                                         SET state = 'converted', converted_at = NOW()
                                       WHERE id = ? AND state = 'sales_approved'");
                $st->bind_param('i', $ueId);
                $st->execute();
                $n = $st->affected_rows;
                $st->close();
                if ($n !== 1) { throw new \RuntimeException('تعذّر ختمُ السلسلة #' . $ueId . ' — سباقُ حالة'); }

                $out['ok'] = true;
                $out['converted'] = true;
                if (function_exists('log_security_event')) {
                    log_security_event('UNIT_CONVERTED',
                        'ue=' . $ueId . ' ts=' . $tsId . ' effects=' . $out['effects']
                        . ' adopted=' . $out['adopted'] . ' actor=' . intval($actor));
                }
            }, 'unit chain conversion ts ' . $tsId);
        } catch (\Throwable $e) {
            $out['ok'] = false;
            $out['reason'] = $e->getMessage();
        }
        return $out;
    }

    /**
     * تحويل دفعة — تبني بوابةَ كلِّ صفٍّ بشركته (جلسةً كان السياقُ أم CLI).
     * كلُّ صفٍّ معاملتُه المستقلة: فشلُ واحدٍ لا يُسقط البقية.
     *
     * @return array{converted:int,effects:int,failed:array<string>,rows:array}
     */
    public static function convertBatch(\mysqli $conn, array $tsIds, $actor)
    {
        $sum = array('converted' => 0, 'effects' => 0, 'failed' => array(), 'rows' => array());
        foreach ($tsIds as $tsId) {
            $tsId = intval($tsId);
            if ($tsId <= 0) { continue; }
            // شركةُ الصف من السلسلة (الجسر) — البوابة تُبنى لها
            $uuid = 'ts:' . $tsId;
            $st = $conn->prepare("SELECT company_id FROM unit_entries WHERE sync_uuid = ? LIMIT 1");
            $st->bind_param('s', $uuid);
            $st->execute();
            $row = $st->get_result()->fetch_assoc();
            $st->close();
            if (!$row) {
                $sum['failed'][] = 'TS-' . $tsId . ': لا صفَّ سلسلةٍ بجسره';
                continue;
            }
            if (isset($_SESSION['user']['id']) && function_exists('ems_tenant_db')) {
                $gate = ems_tenant_db();
            } else {
                $sysOk = (PHP_SAPI === 'cli')
                    || (function_exists('ems_env') && (string) ems_env('EVENTS_CRON_KEY', '') !== '');
                $gate = new \App\Core\TenantDb($conn,
                    \App\Core\TenantContext::forSystem(intval($row['company_id']), 0, '', $sysOk));
            }
            $r = self::convertOne($conn, $gate, $tsId, $actor);
            $sum['rows'][$tsId] = $r;
            if ($r['converted']) {
                $sum['converted']++;
                $sum['effects'] += $r['effects'];
            } elseif (!$r['ok']) {
                $sum['failed'][] = 'TS-' . $tsId . ': ' . $r['reason'];
            }
        }
        return $sum;
    }
}
