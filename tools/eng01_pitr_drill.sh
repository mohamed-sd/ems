#!/usr/bin/env bash
# ═══════════════════════════════════════════════════════════════════════════
# ENG-01 ⑦ · تجربةُ استعادةٍ لنقطةِ زمن — PR-01..PR-06
# ───────────────────────────────────────────────────────────────────────────
# «◆ وتجربةُ استعادةٍ إلى لحظةٍ بعينِها لا إلى نسخة: تُختار لحظةٌ قبلَ خطإٍ
#    مصطنعٍ ويُستعاد إليها ويُثبت أن ما بعدَها لم يعد»
# «◆ فاستعادةُ نسخةٍ ليلةَ أمسٍ تُفقد عملَ اليومِ كلِّه»
#
# الخطواتُ مرقَّمةٌ وأوامرُها كاملةٌ — «◆ ومحضرُ استعادةٍ يقرؤه من لم يبنِ النظام».
#
# التشغيل:  bash tools/eng01_pitr_drill.sh <scratch_dir>
# ═══════════════════════════════════════════════════════════════════════════
set -u

SP="${1:?يلزم مسارُ مساحةِ العمل}"
BIN="/c/wamp64/bin/mariadb/mariadb11.4.9/bin"

# ── INJ-FIX-01 · الموجة ب · الحاجز ④ — معاملاتٌ ببيئةٍ لا بأرقامٍ محفورة ──────
# ◆ **لماذا**: كُتب هذا المسبارُ لنسخةِ خادمٍ مؤقتةٍ على المنفذ 3399، ولا وجودَ
#   لها اليوم. والحاجزُ الرابعُ يطلب إثباتَ الاستعادةِ لنقطةِ زمنٍ **على البنيةِ
#   التي تعمل فعلًا** — فبنيةُ السجلِّ الثنائيِّ هي ما يُختبر، لا وجودُ منفذٍ
#   بعينِه. فصارت المنفذُ والقاعدةُ والحسابُ ومجلدُ البياناتِ معاملاتِ بيئةٍ
#   **بقيمٍ افتراضيةٍ تحفظ سلوكَ ENG-01 كما كان**.
# ◆ **والقاعدةُ في مساحةِ `test_%`** حين تُشغَّل على خادمٍ حيّ — فلا تُنشأ قاعدةٌ
#   باسمٍ عامٍّ بجوارِ الإنتاج.
PORT="${PITR_PORT:-3399}"
DBUSER="${PITR_USER:-root}"
DBPASS="${PITR_PASS:-}"
DB="${PITR_DB:-drill_ems}"
DATADIR="${PITR_DATADIR:-}"

PWFLAG=""
[ -n "$DBPASS" ] && PWFLAG="-p$DBPASS"
MY="$BIN/mysql.exe --protocol=TCP -h127.0.0.1 -P$PORT -u$DBUSER $PWFLAG"
DUMP="$BIN/mariadb-dump.exe --protocol=TCP -h127.0.0.1 -P$PORT -u$DBUSER $PWFLAG"
BINLOG="$BIN/mariadb-binlog.exe"

say() { printf '%s\n' "$*"; }
hr()  { printf '%s\n' "───────────────────────────────────────────────────────────────"; }

say ""
say "═══════════════════════════════════════════════════════════════"
say " تجربةُ الاستعادةِ لنقطةِ زمن — ENG-01 ⑦"
say "═══════════════════════════════════════════════════════════════"

DRILL_START=$($MY -N -B -e "SELECT NOW(3)")
say " بدءُ التجربة : $DRILL_START"
hr

# ── ① قاعدةُ التجربةِ وبياناتُ «ما قبلَ اللحظة» ──────────────────────────
say "▐ ① إنشاءُ القاعدةِ وكتابةُ ما قبلَ اللحظة"
$MY -e "DROP DATABASE IF EXISTS $DB; CREATE DATABASE $DB CHARACTER SET utf8mb4;"
$MY $DB -e "
CREATE TABLE ledger (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  note VARCHAR(80) NOT NULL,
  written_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3)
) ENGINE=InnoDB;"
for i in 1 2 3 4 5 6 7 8 9 10; do
  $MY $DB -e "INSERT INTO ledger (note) VALUES ('قبلَ اللحظة #$i');"
done
BEFORE=$($MY -N -B $DB -e "SELECT COUNT(*) FROM ledger")
say "   صفوفُ ما قبلَ اللحظة: $BEFORE"

# ── ② النسخةُ الكاملةُ المتحقَّقُ منها (PR-02) ────────────────────────────
say ""
say "▐ ② النسخةُ الكاملةُ وموضعُها في السجل"
$MY -e "FLUSH BINARY LOGS;"
$DUMP --single-transaction --master-data=2 --databases $DB > "$SP/drill_full.sql" 2>"$SP/drill_dump.err"
DUMPSZ=$(wc -c < "$SP/drill_full.sql")
if [ "$DUMPSZ" -lt 100 ]; then
  say "   ✗ فشلت النسخة: $(head -2 "$SP/drill_dump.err")"; exit 1
fi
POSLINE=$(grep -m1 "CHANGE MASTER TO" "$SP/drill_full.sql" | tr -d '\r')
# موضعُ النسخةِ في السجل — منه تبدأ إعادةُ التشغيلِ لا من أولِ الملف،
# وإلا أُعيد ما تحمله النسخةُ أصلًا فتضاعفت الصفوف.
DUMP_FILE=$(printf '%s' "$POSLINE" | sed -n "s/.*MASTER_LOG_FILE='\([^']*\)'.*/\1/p")
DUMP_POS=$(printf  '%s' "$POSLINE" | sed -n "s/.*MASTER_LOG_POS=\([0-9]*\).*/\1/p")
say "   النسخة: drill_full.sql ($DUMPSZ بايت)"
say "   الموضع: ${DUMP_FILE:-?} @ ${DUMP_POS:-?}"

# ── ③ كتاباتٌ بينَ النسخةِ واللحظةِ ثم تثبيتُ اللحظة ────────────────────
# ◆ **INJ-FIX-01 — بلا هذه الخطوةِ لا تستطيع التجربةُ أن ترسب**: كانت اللحظةُ
#   T0 تقع بعدَ النسخةِ بثوانٍ **بلا كتابةٍ بينهما**، فنافذةُ الإعادةِ فارغةٌ
#   أصلًا. فلو كان إعادةُ تشغيلِ السجلِّ معطوبًا تمامًا لخرجت النتيجةُ نفسُها
#   ومرَّت التجربةُ خضراء — **نجاحٌ يستحيل تحوُّلُه إلى رسوب**.
# ◆ فتُكتب صفوفٌ **بعدَ النسخةِ وقبلَ T0**: النسخةُ وحدَها لا تحملها، ولا تعود
#   إلا بإعادةِ تشغيلٍ صحيحة. فصار للتجربةِ اتجاهان: تكشف ما عاد ولا ينبغي،
#   وتكشف ما لم يعد وينبغي.
say ""
say "▐ ③ كتاباتٌ بينَ النسخةِ واللحظةِ — لا تعود إلا بإعادةِ تشغيلٍ صحيحة"
for i in 1 2 3; do
  $MY $DB -e "INSERT INTO ledger (note) VALUES ('بينَ النسخةِ وT0 #$i');"
done
MIDWRITES=3
say "   كُتب $MIDWRITES صفًّا بعدَ النسخةِ وقبلَ اللحظة"
sleep 2
T0=$($MY -N -B -e "SELECT NOW()")
say "   نقطةُ الاستعادة T0 = $T0"
BEFORE=$($MY -N -B $DB -e "SELECT COUNT(*) FROM ledger")
say "   الحالةُ المرجوُّ استعادتُها: $BEFORE صفًّا"
sleep 2

# ── ④ الخطأُ المصطنعُ بعدَ اللحظة ───────────────────────────────────────
say ""
say "▐ ④ الخطأُ المصطنعُ — كتاباتٌ بعدَ T0 يجب ألا تعود"
for i in 1 2 3 4 5 6 7; do
  $MY $DB -e "INSERT INTO ledger (note) VALUES ('بعدَ اللحظة — خطأٌ مصطنع #$i');"
done
$MY $DB -e "DELETE FROM ledger WHERE id = 1;"
$MY $DB -e "UPDATE ledger SET note='مُتلَفٌ بعدَ اللحظة' WHERE id = 2;"
AFTER_TOTAL=$($MY -N -B $DB -e "SELECT COUNT(*) FROM ledger")
AFTER_ADDED=$($MY -N -B $DB -e "SELECT COUNT(*) FROM ledger WHERE note LIKE 'بعدَ اللحظة%'")
say "   إجماليُّ الصفوفِ بعدَ الخطأ: $AFTER_TOTAL (منها $AFTER_ADDED مُضافةٌ بعدَ T0)"
say "   وحُذف الصفُّ 1 وأُتلف الصفُّ 2 — والحذفُ أخطرُ ما نستعيد منه"

# ── ⑤ الاستعادةُ إلى T0 ────────────────────────────────────────────────
say ""
say "▐ ⑤ الاستعادة: النسخةُ الكاملةُ ثم إعادةُ تشغيلِ السجلِّ حتى T0"
REST_START=$(date +%s)

# (أ) الملفاتُ الثنائيةُ بترتيبِها — من موضعِ النسخةِ إلى الآن
FILES=$($MY -N -B -e "SHOW BINARY LOGS" | awk '{print $1}' | tr -d '\r')
say "   ملفاتُ السجل: $(printf '%s' "$FILES" | tr '\n' ' ')"

# ── INJ-FIX-01 — نسخُ ملفاتِ السجلِّ إلى مساحةِ العمل ────────────────────────
# ◆ **عيبٌ كامنٌ في هذا المسبارِ أُصلح**: الخطوةُ (ج) أدناه تقرأ من `$SP/drlog/`
#   **ولم يكن في المسبارِ ما يملأ هذا المجلد**. فكانت إعادةُ التشغيلِ تقرأ
#   مسارًا غيرَ موجود، فيخرج `drill_replay.sql` فارغًا وتُقرأ النتيجةُ «لم يعد
#   شيءٌ بعدَ T0» — وهو **نجاحٌ كاذب**: لم يُعَد شيءٌ لأن السجلَّ لم يُقرأ أصلًا.
# ◆ **ولا يُقرأ السجلُّ في موضعِه**: يُنسخ ثم يُقرأ، فالخادمُ يكتب فيه لحظتَها
#   والقراءةُ من ملفٍّ حيٍّ تُخرج نصفَ واقعة.
mkdir -p "$SP/drlog"
if [ -n "$DATADIR" ] && [ -d "$DATADIR" ]; then
  for f in $FILES; do
    if [ -f "$DATADIR/$f" ]; then cp -f "$DATADIR/$f" "$SP/drlog/$f"; fi
  done
  COPIED=$(ls -1 "$SP/drlog" 2>/dev/null | wc -l)
  say "   نُسخ إلى مساحةِ العمل: $COPIED ملفًّا من $DATADIR"
  if [ "$COPIED" = "0" ]; then
    say "   ✗ لم يُنسخ ملفُّ سجلٍّ واحد — لا تُقرأ نتيجةُ الإعادةِ نجاحًا"; exit 1
  fi
else
  say "   ✗ PITR_DATADIR غيرُ محدَّدٍ أو غيرُ موجود: '${DATADIR}'"
  say "     ولا يُكمَل بلا سجلٍّ — فالفراغُ هنا يُنتج نجاحًا كاذبًا"; exit 1
fi
FIRST_BL=$(printf '%s\n' "$FILES" | head -1)
LAST_BL=$(printf '%s\n' "$FILES" | tail -1)

# (ب) إسقاطُ القاعدةِ ثم إعادةُ النسخةِ الكاملة
$MY -e "DROP DATABASE IF EXISTS $DB;"
$MY < "$SP/drill_full.sql"
MID=$($MY -N -B $DB -e "SELECT COUNT(*) FROM ledger")
say "   (أ) بعدَ النسخةِ الكاملةِ وحدَها: $MID صفًّا"

# (ج) إعادةُ تشغيلِ السجلِّ من موضعِ النسخةِ حتى T0 — ولا ثانيةَ بعدَها
#     الملفاتُ من ملفِّ النسخةِ فصاعدًا، و--start-position تسري على أولِها.
BLPATHS=""
SEEN=0
for f in $FILES; do
  if [ "$SEEN" = "0" ] && [ "$f" != "$DUMP_FILE" ]; then continue; fi
  SEEN=1
  BLPATHS="$BLPATHS $SP/drlog/$f"
done
[ -z "$BLPATHS" ] && for f in $FILES; do BLPATHS="$BLPATHS $SP/drlog/$f"; done
say "   ملفاتُ الإعادة: $(printf '%s' "$BLPATHS" | wc -w) ملفًّا من $DUMP_FILE @ $DUMP_POS"
# shellcheck disable=SC2086
"$BINLOG" --disable-log-bin --database=$DB \
          --start-position="${DUMP_POS:-4}" --stop-datetime="$T0" \
          $BLPATHS > "$SP/drill_replay.sql" 2>"$SP/drill_binlog.err"
REPLAYSZ=$(wc -c < "$SP/drill_replay.sql")
$MY $DB < "$SP/drill_replay.sql"
REST_END=$(date +%s)
RTO=$(( REST_END - REST_START ))
say "   (ب) أُعيد تشغيلُ $REPLAYSZ بايت من السجلِّ حتى $T0"
say "   (ج) زمنُ الاستعادة: ${RTO} ثانية"

# ── ⑥ الإثبات ─────────────────────────────────────────────────────────
say ""
say "▐ ⑥ الإثبات — ما قبلَ اللحظةِ عاد وما بعدَها لم يعد"
RESTORED=$($MY -N -B $DB -e "SELECT COUNT(*) FROM ledger")
LEAKED=$($MY -N -B $DB -e "SELECT COUNT(*) FROM ledger WHERE note LIKE 'بعدَ اللحظة%'")
DAMAGED=$($MY -N -B $DB -e "SELECT COUNT(*) FROM ledger WHERE note = 'مُتلَفٌ بعدَ اللحظة'")
ROW1=$($MY -N -B $DB -e "SELECT COUNT(*) FROM ledger WHERE id = 1")

MIDBACK=$($MY -N -B $DB -e "SELECT COUNT(*) FROM ledger WHERE note LIKE 'بينَ النسخةِ وT0%'")

say "   صفوفٌ بعدَ الاستعادة        : $RESTORED   [المتوقَّع $BEFORE]"
say "   صفوفٌ كُتبت بعدَ T0 وعادت   : $LEAKED   [المتوقَّع 0]"
say "   صفوفٌ أُتلفت بعدَ T0 وعادت  : $DAMAGED   [المتوقَّع 0]"
say "   الصفُّ 1 المحذوفُ بعدَ T0    : $ROW1   [المتوقَّع 1 — عاد]"
say "   صفوفُ ما بينَ النسخةِ وT0   : $MIDBACK   [المتوقَّع ${MIDWRITES:-0} — **دليلُ أن السجلَّ أُعيد فعلًا**]"

VERDICT=fail
if [ "$RESTORED" = "$BEFORE" ] && [ "$LEAKED" = "0" ] && [ "$DAMAGED" = "0" ] \
   && [ "$ROW1" = "1" ] && [ "$MIDBACK" = "${MIDWRITES:-0}" ] && [ "${MIDWRITES:-0}" != "0" ]; then
  VERDICT=pass
fi

DRILL_END=$($MY -N -B -e "SELECT NOW(3)")
hr
say " الحكم        : $VERDICT"
say " بدءُ التجربة : $DRILL_START"
say " نهايتُها     : $DRILL_END"
say " نقطةُ الاستعادة T0 : $T0"
say " زمنُ الاستعادة RTO : ${RTO} ثانية"
say " أولُ ملفِّ سجل : $FIRST_BL   آخرُه: $LAST_BL"
say "═══════════════════════════════════════════════════════════════"

# مخرَجٌ آليٌّ يقرؤه مُسجِّلُ المحضر
cat > "$SP/drill_result.env" <<EOF
VERDICT=$VERDICT
DRILL_START=$DRILL_START
DRILL_END=$DRILL_END
T0=$T0
RTO=$RTO
ROWS_BEFORE=$BEFORE
ROWS_AFTER_EXPECTED_GONE=$AFTER_ADDED
ROWS_AFTER_ACTUAL=$LEAKED
RESTORED=$RESTORED
FIRST_BL=$FIRST_BL
LAST_BL=$LAST_BL
EOF
say " المخرَج: $SP/drill_result.env"
say ""
[ "$VERDICT" = "pass" ] && exit 0 || exit 1
