<?php
/**
 * includes/fix_closure_source.php — مصدرُ حالةِ الإغلاقِ **الواحد**
 * ═══════════════════════════════════════════════════════════════════════════
 * ⇐ البندُ الحاجبُ في تكليفِ حملةِ الأدلة (2026-08-13)
 *
 * ── المشكلةُ المقيسة ────────────────────────────────────────────────────────
 * كان في النظامِ **مصدران** لسؤالٍ واحدٍ («هل أُغلق هذا البند؟»):
 *   ① مصفوفةٌ **مثبَّتةٌ بيدٍ** في `tools/fix_status_report.php` من السطر 58.
 *   ② حالةُ «مُغلقٌ بشاهد» في `docs/fix_progress/INJ_findings_state.tsv`.
 * فتفرّقا — كما يتفرّق كلُّ عدّادَينِ في ملفَّين (وهو نمطٌ متكرِّرٌ في هذا
 * المستودع). والمالكُ طلب توحيدَهما.
 *
 * ── ولماذا **لا** يُجعل التقريرُ يقرأ الـTSV ────────────────────────────────
 * لأنَّ اتجاهَ الاشتقاقِ **معكوسٌ**: `fix_progress_report.php` هو مَن **يكتب**
 * الـTSV، وهو يقرأ حالتَه من مصفوفةِ `fix_status_report.php` (السطر 239).
 * فلو قرأ التقريرُ الـTSV لأُغلقت حلقة: التقريرُ ⇐ TSV ⇐ التقرير — و**كلُّ
 * بندٍ يشهد لنفسِه**، وهو عينُ ما تمنعه القاعدةُ GT-01. فالتوحيدُ يكون
 * بمصدرٍ **ثالثٍ خارجَ الحلقة**: القرصُ نفسُه.
 *
 * ── المصدرُ الواحد: **مِسبارٌ يذكر المعرِّفَ** ───────────────────────────────
 *   · **ساكنًا**: معرِّفٌ يذكره ملفُّ فاحصٍ أو مِسبارٍ في `tests/` أو `tools/`.
 *     وهذه حالةُ «**مذكورٌ**» لا «مُغلق» — تُعلَن كما هي ولا تُحسَب إنجازًا.
 *   · **حيًّا** (`--live`): يُشغَّل كلُّ مِسبارٍ يذكره ويُشترط **رمزُ خروجٍ صفر**.
 *     وهذه وحدَها «**مُغلقٌ بشاهد**» — وهي قاعدةُ CL-01 حرفيًّا:
 *     «الإصلاحُ وحدَه لا يُغلق بندًا · والإغلاقُ بتشغيلِ الاختبارِ لا بالتوقيعِ عليه».
 *
 * ◆ والمسحُ **يقصي** `.claude/worktrees/` و`storage/backups/` و`vendor/` —
 *   فمِسبارٌ في نسخةٍ احتياطيةٍ يُنتج خضرةً كاذبةً لبندٍ مفتوح.
 * ◆ ولا يُقبل ذكرٌ داخلَ **تعليقٍ عابرٍ في أداةِ توليدٍ**: يُشترط أن يكون الملفُّ
 *   **قابلًا للتشغيل** بمفرده (فاحصٌ أو مِسبار) — فأداةُ بناءٍ تذكر معرِّفًا في
 *   ترويستِها لا تشهد له.
 * ═══════════════════════════════════════════════════════════════════════════
 */

if (!function_exists('ems_fix_probe_files')) {
    /**
     * كلُّ ملفٍّ **قابلٍ للتشغيلِ** قد يحمل شاهدًا — مع إقصاءِ النسخِ والفروع.
     * @return array<string> مساراتٌ مطلقة
     */
    function ems_fix_probe_files($root)
    {
        $out = array();
        foreach (array('/tests', '/tools') as $sub) {
            $dir = $root . $sub;
            if (!is_dir($dir)) { continue; }
            foreach (glob($dir . '/*.php') as $f) {
                $n = basename($f);
                /* ملفاتُ العُدَّةِ المسبوقةُ بشرطةٍ سفليةٍ ليست مسابيرَ تُشغَّل */
                if ($n[0] === '_') { continue; }
                $out[] = str_replace('\\', '/', $f);
            }
        }
        sort($out);
        return $out;
    }
}

if (!function_exists('ems_fix_mentions')) {
    /**
     * خريطةُ معرِّفٍ ⇒ قائمةِ المسابيرِ التي **تشهد له** — لا التي تذكره.
     *
     * ── ولماذا الوسمُ لا الذكر ─────────────────────────────────────────────
     * قِيس الفرقُ فكان حاسمًا: بالذكرِ المجرَّد يُحسَب **INJ-0416 مُغلقًا** وهو
     * **موقوفٌ عمدًا** بوسمِ «مصدرٌ مفقود» — لأنَّ ثلاثةَ ملفاتٍ تذكره **مثالًا
     * على الموقوف** في نثرِ ترويساتِها. وكذلك INJ-0331. فالذكرُ يقلب المعنى
     * إلى ضدِّه.
     *
     * فالمعيارُ هو الوسمُ الصريحُ المعتمَدُ في هذا المستودع:
     *     ⇐ شواهدُ أحكامٍ: INJ-#### · INJ-####
     * وهو **إعلانٌ من الفاحصِ عن نفسِه**: «أنا أشهد لهذه الأحكام». ويُثبَّت
     * بـ`tools/fix_apply_bindings.php` بدليلٍ منقولٍ ومراجعةٍ خصمٍ، لا بالحدس.
     *
     * ◆ والوسمُ يُقرأ من **أوّلِ 6000 بايتٍ** (ترويسةُ الملف) — فذكرٌ في وسطِ
     *   الشيفرةِ ليس إعلانَ شهادة.
     */
    function ems_fix_mentions($root)
    {
        $map = array();
        foreach (ems_fix_probe_files($root) as $f) {
            $src = (string) @file_get_contents($f);
            if ($src === '') { continue; }
            $head = mb_substr($src, 0, 6000);
            if (!preg_match_all('~شواهد أحكام\s*:(.{0,1200})~su', $head, $blocks)) { continue; }
            foreach ($blocks[1] as $blk) {
                /* ── الوسمُ يمتدُّ ما امتدَّت **أسطرُ المعرِّفات** ────────────────
                     يقف عند أوّلِ سطرٍ لا يحمل معرِّفًا — فلا يبتلع نثرَ الترويسةِ
                     بعده (وفيه قد يُذكر معرِّفٌ **موقوفٌ** مثالًا، كـINJ-0416،
                     فيُحسَب شهادةً وهو نقيضُها). */
                $keep = array();
                foreach (preg_split('~\r?\n~', $blk) as $i => $ln) {
                    if ($i > 0 && !preg_match('~\bINJ-\d{4}\b~', $ln)) { break; }
                    $keep[] = $ln;
                }
                $blk = implode("\n", $keep);
                if (!preg_match_all('~\bINJ-\d{4}\b~', $blk, $m)) { continue; }
                foreach (array_unique($m[0]) as $id) {
                    if (!isset($map[$id])) { $map[$id] = array(); }
                    if (!in_array($f, $map[$id], true)) { $map[$id][] = $f; }
                }
            }
        }
        ksort($map);
        return $map;
    }
}

if (!function_exists('ems_fix_closed_ids')) {
    /**
     * المعرِّفاتُ المُغلقةُ — **بالقياسِ لا بقائمةٍ مكتوبة**.
     *
     * @param string   $root   جذرُ المستودع
     * @param bool     $live   true ⇒ يُشغَّل كلُّ مِسبارٍ ويُشترط خروجٌ صفر
     * @param callable $log    اختياريّ: يُنادى بسطرِ تقدُّمٍ لكلِّ مِسبارٍ مُشغَّل
     * @return array{closed: array<string>, mentioned: array<string>,
     *               red: array<string,string>, probes: array<string,array>}
     */
    function ems_fix_closed_ids($root, $live = false, $log = null)
    {
        $mentions = ems_fix_mentions($root);
        $mentioned = array_keys($mentions);
        if (!$live) {
            return array('closed' => array(), 'mentioned' => $mentioned,
                         'red' => array(), 'probes' => $mentions);
        }

        /* يُشغَّل كلُّ مِسبارٍ **مرّةً واحدةً** مهما كثُرت معرِّفاتُه */
        $need = array();
        foreach ($mentions as $id => $files) {
            foreach ($files as $f) { $need[$f] = true; }
        }
        $php = defined('PHP_BINARY') ? PHP_BINARY : 'php';
        $verdict = array();
        foreach (array_keys($need) as $f) {
            $cmd = escapeshellarg($php) . ' ' . escapeshellarg($f) . ' 2>&1';
            $out = array(); $code = 1;
            @exec($cmd, $out, $code);
            $verdict[$f] = ((int) $code === 0);
            if (is_callable($log)) {
                $log(($verdict[$f] ? '  ✔ ' : '  ✘ ') . str_replace($root . '/', '', $f)
                     . ' (exit=' . (int) $code . ')');
            }
        }

        $closed = array(); $red = array();
        foreach ($mentions as $id => $files) {
            $green = array();
            foreach ($files as $f) { if (!empty($verdict[$f])) { $green[] = $f; } }
            if ($green) { $closed[] = $id; }
            else { $red[$id] = implode(' · ', array_map(function ($p) use ($root) {
                return str_replace($root . '/', '', $p);
            }, $files)); }
        }
        sort($closed);
        return array('closed' => $closed, 'mentioned' => $mentioned,
                     'red' => $red, 'probes' => $mentions);
    }
}
