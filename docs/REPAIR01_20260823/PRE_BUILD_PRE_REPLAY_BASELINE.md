# `PRE_BUILD_PRE_REPLAY_BASELINE` — النقطةُ الثانية (أمرُ الضبطِ §١٣)

> تُثبَّت **بعد اكتمالِ جولةِ الضبطِ وقبل أوّلِ Build Batch أو Replay أو
> Bulk Data Mutation** — ⛔ ولا يُنفَّذ Replay واحدٌ قبلها.

| المفردة | القيمة |
|---|---|
| `Commit Hash` | يُقرأ من وسم `pre-build-20260830` |
| `Checkpoint Tag` | `pre-build-20260830` |
| `DB Backup` | `storage/backups/pre_build_20260830/equipation_manage_pre_build.sql.gz` (مضغوط 2026-09-02 · يُستردُّ بـ`gunzip -k`) |
| `Backup Checksum` | في `CHECKSUM.sha256` بجانبِ النسخة (sha256) |
| حالُ الجولة | شروطُ الخروجِ الأربعةَ عشرَ متحقِّقة — `CTL_ROUND_CLOSURE.md` |
| `Replay المنفَّذ` | **صفر** — `SUM(replayed)=0` في `repair01_backlog_disposition` |
| أولُ حمولةِ البناء | ثمانيةُ أهدافٍ `BUILD_READY=YES` بترتيبِ `target_uid` |
