<?php
$year = date('Y');
$scriptName = isset($_SERVER['SCRIPT_NAME']) ? str_replace('\\', '/', $_SERVER['SCRIPT_NAME']) : '/index.php';
$baseUrl = rtrim(dirname($scriptName), '/');
if ($baseUrl === '/' || $baseUrl === '\\') $baseUrl = '';
function landing_url($path = '') {
    global $baseUrl;
    if ($path === '' || $path === '/') return $baseUrl === '' ? '/' : $baseUrl;
    return ($baseUrl === '' ? '' : $baseUrl) . '/' . ltrim($path, '/');
}
function e($s) { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: SAMEORIGIN");
header("X-XSS-Protection: 1; mode=block");
header("Referrer-Policy: strict-origin-when-cross-origin");
header("Permissions-Policy: camera=(), microphone=(), geolocation=()");
?><!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>منصة إنجاز | إدارة التعدين في السودان</title>
<meta name="description" content="منصة إنجاز — نظام SaaS متخصص لإدارة شركات التعدين والمناجم والمعدات الثقيلة والعقود في السودان.">
<meta name="robots" content="index, follow">
<link rel="preconnect" href="/ems/assets/css/local-fonts.css">
<link rel="preconnect" href="/ems/assets/css/local-fonts.css" crossorigin>
<link href="/ems/assets/css/local-fonts.css" rel="stylesheet">
<style>
/* ══════════════════════════════════════════════════════
   TOKENS — Heavy Industry / Sudan Mining Palette
══════════════════════════════════════════════════════ */
:root {
  /* Deep earth base — anthracite not navy */
  --bg:    #f5f4f0;
  --bg2:   #eceae4;
  --bg3:   #ffffff;
  --bg4:   #f0ede6;

  /* Borders */
  --ln:    rgba(0,0,0,.09);
  --lnh:   rgba(0,0,0,.16);
  --ln2:   rgba(0,0,0,.04);

  /* ── MOLTEN GOLD (primary brand) — Sudan gold mining */
  --au5:   #e8900a;  /* deep amber */
  --au4:   #f5a623;  /* gold */
  --au3:   #fdc85a;  /* bright gold */
  --au2:   #fde9b0;  /* pale gold */
  --bg-au: rgba(232,144,10,.09);
  --br-au: rgba(180,110,0,.30);
  --glow-au: rgba(232,144,10,.24);

  /* ── IRON ORANGE (machinery / heavy equip) */
  --fe5:   #c84a0c;  /* deep rust */
  --fe4:   #e8621a;  /* machinery orange */
  --fe3:   #f08050;  /* warm orange */
  --bg-fe: rgba(200,74,12,.10);
  --br-fe: rgba(200,74,12,.30);
  --glow-fe: rgba(232,98,26,.22);

  /* ── NILE BLUE (Sudan Nile / operations) */
  --ni5:   #1255a8;
  --ni4:   #2872d4;
  --ni3:   #68a8f0;
  --bg-ni: rgba(18,85,168,.10);
  --br-ni: rgba(18,85,168,.30);
  --glow-ni: rgba(40,114,212,.22);

  /* ── EARTH RED (Sahara desert) */
  --sa5:   #8b2800;
  --sa4:   #c03820;
  --sa3:   #e05838;
  --bg-sa: rgba(139,40,0,.10);
  --br-sa: rgba(192,56,32,.28);

  /* ── STEEL GRAY (machinery) */
  --st5:   #4a5568;
  --st4:   #718096;
  --st3:   #a0aec0;
  --bg-st: rgba(74,85,104,.12);
  --br-st: rgba(74,85,104,.30);

  /* ── EMERALD (success/active) */
  --em5:   #10b97a;
  --em3:   #50d4a0;
  --bg-em: rgba(16,185,122,.09);
  --br-em: rgba(16,185,122,.26);

  /* Text */
  --tx1: #1a1410;  /* near black */ 
  --tx2: #5a4e40;  /* earth mid dark */
  --tx3: #8a7d6e;  /* lighter label */

  --r-sm: 6px;
  --r-md: 12px;
  --r-lg: 18px;
  --r-xl: 24px;
  --ease: cubic-bezier(0.16,1,0.3,1);
  --T: .24s;
}

/* ══ RESET ══════════════════════════════════════════ */
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html{scroll-behavior:smooth}
body{
  font-family:'Barlow Condensed','Tajawal',sans-serif;
  background:var(--bg);color:var(--tx1);
  min-height:100vh;overflow-x:hidden;
  -webkit-font-smoothing:antialiased;
}
a{color:inherit;text-decoration:none}
button{font-family:inherit;cursor:pointer;border:none;background:none}
ul{list-style:none}

/* ══ ATMOSPHERE — Industrial Earth ════════════════════ */
.atm{position:fixed;inset:0;z-index:0;pointer-events:none;overflow:hidden}

/* Mesh: warm earth / desert tones */
.atm-mesh{
  position:absolute;inset:0;
  background:
    radial-gradient(ellipse 100% 70% at 0% -5%,   rgba(232,144,10,.09) 0%,transparent 48%),
    radial-gradient(ellipse 60%  50% at 100% 0%,   rgba(200,74,12,.06)  0%,transparent 42%),
    radial-gradient(ellipse 80%  55% at 50% 110%,  rgba(18,85,168,.05)  0%,transparent 44%),
    radial-gradient(ellipse 50%  40% at 100% 80%,  rgba(232,144,10,.04) 0%,transparent 38%),
    radial-gradient(ellipse 70%  35% at 10% 65%,   rgba(200,74,12,.03)  0%,transparent 38%);
}

/* Industrial grid — heavier lines */
.atm-grid{
  position:absolute;inset:0;
  background-image:
    linear-gradient(rgba(255,200,80,.025) 1px,transparent 1px),
    linear-gradient(90deg,rgba(255,200,80,.025) 1px,transparent 1px);
  background-size:64px 64px;
  mask-image:radial-gradient(ellipse 85% 55% at 50% 0%,black 15%,transparent 72%);
}

/* Diagonal industrial stripes accent */
.atm-stripes{
  position:absolute;inset:0;
  background-image:repeating-linear-gradient(
    -55deg,
    transparent, transparent 60px,
    rgba(180,120,0,.04) 60px, rgba(180,120,0,.04) 61px
  );
}

/* Floating orbs — earth/fire tones */
.orb{position:absolute;border-radius:50%;filter:blur(120px)}
.o1{width:700px;height:700px;background:rgba(232,144,10,.07);top:-250px;right:-180px;animation:oa 30s ease-in-out infinite}
.o2{width:550px;height:550px;background:rgba(200,74,12,.05);top:35%;left:-200px;animation:ob 26s ease-in-out infinite}
.o3{width:480px;height:480px;background:rgba(18,85,168,.04);bottom:-100px;right:20%;animation:oa 22s ease-in-out infinite reverse}
.o4{width:400px;height:400px;background:rgba(232,144,10,.04);top:60%;right:-100px;animation:ob 34s ease-in-out infinite}
@keyframes oa{0%,100%{transform:translateY(0) scale(1)}50%{transform:translateY(-60px) scale(1.07)}}
@keyframes ob{0%,100%{transform:translateY(0) scale(1)}50%{transform:translateY(44px) scale(.93)}}

/* Heavy grain texture — industrial feel */
.atm-grain{
  position:absolute;inset:0;opacity:.18;
  background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='300' height='300'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='.85' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='300' height='300' filter='url(%23n)' opacity='.055'/%3E%3C/svg%3E");
}

/* ══ LAYOUT ══════════════════════════════════════════ */
.wrap{position:relative;z-index:1}
.ctr{width:min(1220px,calc(100% - 40px));margin-inline:auto}

/* ══ HEADER ══════════════════════════════════════════ */
.site-header{position:sticky;top:0;z-index:200}
.hbar{
  height:68px;display:flex;align-items:center;justify-content:space-between;gap:16px;
  padding-inline:max(20px,calc((100% - 1220px)/2 + 20px));
  background:rgba(245,244,240,.88);border-bottom:1px solid rgba(180,130,20,.18);
  backdrop-filter:blur(28px) saturate(160%);-webkit-backdrop-filter:blur(28px);
  transition:box-shadow var(--T);
}
.hbar.sc{box-shadow:0 4px 28px rgba(0,0,0,.10),0 1px 0 rgba(180,130,20,.14)}

/* Logo */
.logo{display:flex;align-items:center;gap:12px;flex-shrink:0}
.lgem{
  width:42px;height:42px;border-radius:10px;flex-shrink:0;
  background:linear-gradient(145deg,#fffaf0,var(--au4));
  border:1px solid var(--br-au);
  display:flex;align-items:center;justify-content:center;
  box-shadow:0 0 24px var(--glow-au),inset 0 1px 0 rgba(255,220,100,.15);
  transition:box-shadow var(--T);
}
.logo:hover .lgem{box-shadow:0 0 38px rgba(232,144,10,.4),inset 0 1px 0 rgba(255,220,100,.2)}
.lgem svg{width:20px;height:20px}
.lname strong{display:block;font-family:'Tajawal',sans-serif;font-size:1rem;font-weight:800;color:var(--tx1);letter-spacing:.01em}
.lname small{display:block;font-size:.63rem;font-weight:600;color:var(--tx3);letter-spacing:.11em;text-transform:uppercase}

/* Nav */
.nav-mid{display:flex;align-items:center;gap:2px}
.nav-mid a{font-size:.84rem;font-weight:600;color:var(--tx2);padding:8px 13px;border-radius:var(--r-sm);transition:color var(--T),background var(--T)}
.nav-mid a:hover{color:var(--tx1);background:rgba(232,144,10,.10)}

.nav-end{display:flex;align-items:center;gap:8px}
.btn-ng{
  font-size:.82rem;font-weight:600;color:var(--tx2);
  padding:9px 15px;border-radius:var(--r-sm);
  border:1px solid rgba(0,0,0,.12);transition:all var(--T);
}
.btn-ng:hover{color:var(--tx1);border-color:rgba(0,0,0,.2);background:rgba(0,0,0,.04)}
.btn-np{
  display:inline-flex;align-items:center;gap:8px;
  font-size:.83rem;font-weight:800;color:#0f0800;letter-spacing:.02em;
  padding:10px 20px;border-radius:9px;
  background:linear-gradient(135deg,var(--au3),var(--au4),var(--au5));
  box-shadow:0 4px 20px var(--glow-au),inset 0 1px 0 rgba(255,240,160,.3);
  transition:all var(--T);
}
.btn-np svg{width:15px;height:15px}
.btn-np:hover{transform:translateY(-1px);box-shadow:0 8px 28px rgba(232,144,10,.5)}

.ham{display:none;flex-direction:column;gap:5px;padding:9px;border-radius:var(--r-sm);border:1px solid rgba(0,0,0,.12);transition:all var(--T)}
.ham:hover{border-color:var(--br-au);background:rgba(232,144,10,.08)}
.ham span{display:block;width:20px;height:1.5px;background:var(--tx2);border-radius:2px}

/* ══ DRAWER ══════════════════════════════════════════ */
.drawer{
  position:fixed;inset:0;z-index:500;display:flex;flex-direction:column;
  background:rgba(248,246,242,.98);backdrop-filter:blur(32px);padding:20px 24px 32px;
  opacity:0;transform:translateX(100%);
  transition:opacity .32s var(--ease),transform .32s var(--ease);pointer-events:none;
}
.drawer.open{opacity:1;transform:translateX(0);pointer-events:all}
.drw-top{display:flex;align-items:center;justify-content:space-between;margin-bottom:28px}
.drw-x{width:38px;height:38px;border-radius:var(--r-sm);border:1px solid rgba(0,0,0,.1);display:flex;align-items:center;justify-content:center;color:var(--tx2);transition:all var(--T)}
.drw-x:hover{color:var(--tx1);border-color:var(--br-au)}
.drw-x svg{width:16px;height:16px}
.drawer nav{display:flex;flex-direction:column;gap:5px;flex:1}
.drawer nav a{padding:13px 16px;border-radius:var(--r-md);border:1px solid rgba(0,0,0,.09);font-size:1rem;font-weight:600;color:var(--tx2);transition:all var(--T)}
.drawer nav a:hover{color:var(--tx1);border-color:var(--br-au);background:rgba(232,144,10,.08)}
.drw-foot{margin-top:16px}
.drw-foot a{display:flex;align-items:center;justify-content:center;gap:10px;padding:14px;border-radius:var(--r-md);background:linear-gradient(135deg,var(--au3),var(--au5));font-weight:800;font-size:1rem;color:#0f0800}

/* ══ HERO ════════════════════════════════════════════ */
.hero{padding:88px 0 72px;position:relative}

/* Industrial crosshair decoration */
.hero::before{
  content:'';position:absolute;top:60px;left:50%;transform:translateX(-50%);
  width:1px;height:60px;
  background:linear-gradient(180deg,transparent,rgba(232,144,10,.3),transparent);
  pointer-events:none;
}

.hero-pill{
  display:inline-flex;align-items:center;gap:10px;
  background:rgba(232,144,10,.09);border:1px solid rgba(180,120,0,.28);
  border-radius:4px;padding:7px 16px 7px 10px;margin-bottom:28px;
  animation:up .7s var(--ease) both;
}
.pill-dot{
  width:26px;height:26px;border-radius:50%;
  background:var(--bg-em);border:1px solid var(--br-em);
  display:flex;align-items:center;justify-content:center;flex-shrink:0;
}
.pill-dot svg{width:13px;height:13px;color:var(--em5)}
.hero-pill span{font-size:.77rem;font-weight:700;color:var(--au3);letter-spacing:.04em}

/* Two-column hero */
.hero-grid{display:grid;grid-template-columns:1.12fr .88fr;gap:56px;align-items:center}

.hero-h1{
  font-family:'Tajawal',sans-serif;
  font-size:clamp(2.3rem,5.5vw,4.2rem);
  font-weight:900;line-height:1.13;letter-spacing:-.02em;
  animation:up .7s .06s var(--ease) both;
}
.c-au{background:linear-gradient(125deg,var(--au2) 0%,var(--au3) 35%,var(--au4) 65%,var(--au5) 100%);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text}
.c-fe{background:linear-gradient(125deg,var(--fe3),var(--fe4),var(--fe5));-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text}
.c-ni{background:linear-gradient(125deg,var(--ni3),var(--ni4));-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text}

/* Sudan flag accent bar */
.sudan-bar{
  display:flex;gap:0;width:48px;height:4px;border-radius:2px;
  overflow:hidden;margin-bottom:16px;
  animation:up .7s .03s var(--ease) both;
}
.sudan-bar span:nth-child(1){flex:1;background:#da121a}/* red */
.sudan-bar span:nth-child(2){flex:1;background:#fff}/* white */
.sudan-bar span:nth-child(3){flex:1;background:#000}/* black */
.sudan-bar span:nth-child(4){flex:.6;background:#007229}/* green */

.hero-sub{margin-top:20px;max-width:520px;color:var(--tx2);font-size:1rem;line-height:1.9;font-family:'Tajawal',sans-serif;animation:up .7s .13s var(--ease) both}

.hero-cta{margin-top:34px;display:flex;gap:12px;flex-wrap:wrap;animation:up .7s .2s var(--ease) both}

.btn-gold{
  display:inline-flex;align-items:center;gap:10px;
  font-weight:800;font-size:.95rem;color:#0f0800;letter-spacing:.02em;
  padding:13px 26px;border-radius:8px;
  background:linear-gradient(135deg,var(--au2),var(--au3),var(--au4),var(--au5));
  box-shadow:0 4px 26px var(--glow-au),inset 0 1px 0 rgba(255,240,160,.35);
  transition:all var(--T);
}
.btn-gold svg{width:17px;height:17px}
.btn-gold:hover{transform:translateY(-2px);box-shadow:0 10px 34px rgba(232,144,10,.55)}

.btn-wire{
  display:inline-flex;align-items:center;gap:10px;
  font-weight:700;font-size:.95rem;color:var(--tx1);
  padding:13px 24px;border-radius:8px;
  background:rgba(0,0,0,.05);border:1px solid rgba(0,0,0,.15);transition:all var(--T);
}
.btn-wire svg{width:14px;height:14px;color:var(--tx2)}
.btn-wire:hover{background:rgba(255,255,255,.08);border-color:rgba(180,130,20,.35);transform:translateY(-1px)}

/* Trust row */
.hero-trust{margin-top:40px;display:flex;align-items:center;gap:18px;flex-wrap:wrap;animation:up .7s .27s var(--ease) both}
.tsep{width:1px;height:22px;background:rgba(0,0,0,.12)}
.ti{display:flex;align-items:center;gap:8px}
.ti svg{width:14px;height:14px;color:var(--em5);flex-shrink:0}
.ti span{font-size:.76rem;color:var(--tx3);font-weight:600;font-family:'Tajawal',sans-serif}

/* ── DASHBOARD VISUAL */
.hero-vis{position:relative;animation:up .7s .1s var(--ease) both}

.dash{
  background:#fff;
  border:1px solid rgba(180,130,20,.20);
  border-radius:16px;padding:22px;
  box-shadow:0 12px 48px rgba(0,0,0,.10),
             0 0 0 1px rgba(255,220,100,.08) inset,
             0 0 40px rgba(232,144,10,.06);
  position:relative;overflow:hidden;
}
.dash::before{
  content:'';position:absolute;top:0;left:0;right:0;height:2px;
  background:linear-gradient(90deg,transparent,var(--au5) 30%,var(--fe4) 70%,transparent);
}
.dash::after{
  content:'';position:absolute;top:-80px;left:-80px;width:240px;height:240px;border-radius:50%;
  background:radial-gradient(circle,rgba(232,144,10,.10),transparent 70%);pointer-events:none;
}

.dc-head{display:flex;align-items:center;justify-content:space-between;margin-bottom:18px;position:relative;z-index:1}
.dc-dots{display:flex;gap:5px}
.dc-dots span{width:9px;height:9px;border-radius:50%}
.dc-dots span:nth-child(1){background:#ff5f57}
.dc-dots span:nth-child(2){background:#febc2e}
.dc-dots span:nth-child(3){background:#28c840}
.dc-label{font-size:.74rem;font-weight:700;color:var(--tx3);letter-spacing:.06em;text-transform:uppercase}

.dc-kpis{display:grid;grid-template-columns:repeat(3,1fr);gap:9px;margin-bottom:18px}
.kpi{background:rgba(245,242,234,1);border:1px solid rgba(0,0,0,.08);border-radius:10px;padding:13px 11px}
.kpi-v{font-family:'Tajawal',sans-serif;font-size:1.5rem;font-weight:900;line-height:1}
.kpi-l{margin-top:4px;font-size:.68rem;color:var(--tx3);font-family:'Tajawal',sans-serif}

.dc-bars{display:flex;align-items:flex-end;gap:5px;height:72px;margin-bottom:18px}
.bar{flex:1;border-radius:4px 4px 0 0;animation:barUp .9s var(--ease) both}
@keyframes barUp{from{transform:scaleY(0);transform-origin:bottom}to{transform:scaleY(1)}}

.dc-acts{display:flex;flex-direction:column;gap:7px}
.dc-act{display:flex;align-items:center;gap:10px}
.act-dot{width:7px;height:7px;border-radius:50%;flex-shrink:0}
.act-bar{flex:1;height:5px;border-radius:3px;background:rgba(0,0,0,.06)}
.act-tag{font-size:.66rem;font-weight:700;padding:2px 8px;border-radius:3px;flex-shrink:0;font-family:'Tajawal',sans-serif}

/* Float cards */
.fl1,.fl2{position:absolute;border-radius:10px;padding:12px 15px}
.fl1{bottom:-20px;right:-20px;min-width:150px;background:#fff;border:1px solid rgba(180,120,0,.25);box-shadow:0 8px 28px rgba(0,0,0,.10),0 0 16px rgba(232,144,10,.08)}
.fl2{top:50px;left:-26px;min-width:136px;background:#fff;border:1px solid rgba(180,70,10,.22);box-shadow:0 6px 24px rgba(0,0,0,.09),0 0 14px rgba(200,74,12,.06)}
.fl-lbl{font-size:.67rem;color:var(--tx3);margin-bottom:4px;font-family:'Tajawal',sans-serif}
.fl-val{font-family:'Tajawal',sans-serif;font-size:1.15rem;font-weight:800}
.fl-chg{display:flex;align-items:center;gap:4px;margin-top:3px;font-size:.68rem;font-weight:700;font-family:'Tajawal',sans-serif}

/* ══ PROOF STRIP ══════════════════════════════════════ */
.proof{
  border-top:1px solid rgba(180,130,20,.18);
  border-bottom:1px solid rgba(180,130,20,.18);
  background:linear-gradient(180deg,rgba(248,244,236,1),rgba(242,238,228,1));
  position:relative;
}
.proof::before{
  content:'';position:absolute;top:0;left:0;right:0;height:1px;
  background:linear-gradient(90deg,transparent,rgba(180,120,0,.25),rgba(180,70,10,.18),transparent);
}
.proof-row{display:grid;grid-template-columns:repeat(4,1fr)}
.proof-cell{padding:26px 16px;text-align:center;position:relative}
.proof-cell+.proof-cell::before{content:'';position:absolute;right:0;top:20%;height:60%;width:1px;background:rgba(0,0,0,.1)}
.proof-n{font-family:'Tajawal',sans-serif;font-size:2.2rem;font-weight:900;line-height:1}
.n-au{background:linear-gradient(135deg,var(--au3),var(--au4));-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text}
.n-fe{background:linear-gradient(135deg,var(--fe3),var(--fe4));-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text}
.n-ni{background:linear-gradient(135deg,var(--ni3),var(--ni4));-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text}
.n-em{background:linear-gradient(135deg,var(--em3),var(--em5));-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text}
.proof-d{margin-top:6px;font-size:.75rem;color:var(--tx3);font-weight:600;font-family:'Tajawal',sans-serif;letter-spacing:.04em}

/* ══ SECTIONS COMMON ════════════════════════════════ */
.sec{padding:92px 0}

/* Industrial eyebrow */
.ey{
  display:inline-flex;align-items:center;gap:10px;
  font-size:.68rem;font-weight:700;letter-spacing:.18em;text-transform:uppercase;
  margin-bottom:12px;
}
.ey-au{color:var(--au3)}.ey-au::before,.ey-au::after{background:var(--au3)}
.ey-fe{color:var(--fe3)}.ey-fe::before,.ey-fe::after{background:var(--fe3)}
.ey-ni{color:var(--ni3)}.ey-ni::before,.ey-ni::after{background:var(--ni3)}
.ey-em{color:var(--em3)}.ey-em::before,.ey-em::after{background:var(--em3)}
.ey::before,.ey::after{content:'';display:inline-block;width:18px;height:1.5px;flex-shrink:0}

.sec-h{
  font-family:'Tajawal',sans-serif;
  font-size:clamp(1.8rem,3.8vw,2.7rem);
  font-weight:900;letter-spacing:-.022em;line-height:1.18;
}
.sec-sub{margin-top:12px;color:var(--tx2);font-size:.97rem;line-height:1.88;max-width:550px;font-family:'Tajawal',sans-serif}

/* Divider line */
.sec-divider{
  width:100%;height:1px;
  background:linear-gradient(90deg,transparent,rgba(232,144,10,.2),rgba(200,74,12,.15),transparent);
  margin:0;
}

/* ══ FEATURES BENTO ════════════════════════════════ */
.feat-bg{
  background:linear-gradient(180deg,rgba(240,236,228,.5),rgba(245,241,234,.8) 50%,rgba(240,236,228,.5));
  border-top:1px solid rgba(180,130,20,.12);border-bottom:1px solid rgba(180,130,20,.12);
}

.bento{display:grid;grid-template-columns:repeat(12,1fr);gap:13px;margin-top:50px}

.bc{
  background:#fff;
  border:1px solid rgba(0,0,0,.09);
  border-radius:var(--r-lg);padding:24px;
  position:relative;overflow:hidden;
  transition:transform var(--T),border-color var(--T),box-shadow var(--T);
}
.bc::before{
  content:'';position:absolute;top:0;left:0;right:0;height:1.5px;
  background:var(--bc-line,none);opacity:0;transition:opacity var(--T);
}
.bc:hover{transform:translateY(-4px);border-color:rgba(180,120,0,.35);box-shadow:0 12px 36px rgba(0,0,0,.12)}
.bc:hover::before{opacity:1}

.s12{grid-column:span 12}.s8{grid-column:span 8}.s6{grid-column:span 6}
.s5{grid-column:span 5}.s4{grid-column:span 4}.s7{grid-column:span 7}
.s3{grid-column:span 3}

/* Feature icon */
.fic{width:46px;height:46px;border-radius:10px;display:flex;align-items:center;justify-content:center;margin-bottom:18px;flex-shrink:0}
.fic svg{width:22px;height:22px}
.fic-au{background:var(--bg-au);border:1px solid var(--br-au);color:var(--au3)}
.fic-fe{background:var(--bg-fe);border:1px solid var(--br-fe);color:var(--fe3)}
.fic-ni{background:var(--bg-ni);border:1px solid var(--br-ni);color:var(--ni3)}
.fic-em{background:var(--bg-em);border:1px solid var(--br-em);color:var(--em3)}
.fic-st{background:var(--bg-st);border:1px solid var(--br-st);color:var(--st3)}
.fic-sa{background:var(--bg-sa);border:1px solid var(--br-sa);color:var(--sa3)}

.bc h3{font-size:.98rem;font-weight:700;margin-bottom:7px;font-family:'Tajawal',sans-serif}
.bc p{font-size:.83rem;color:var(--tx2);line-height:1.78;font-family:'Tajawal',sans-serif}

/* Big feature card */
.bc-hero{
  grid-column:span 8;
  background:linear-gradient(145deg,#fff,#fdf8ef);
  display:flex;gap:24px;align-items:center;
}
.bc-hero .f-vis{
  width:180px;height:120px;flex-shrink:0;border-radius:12px;
  background:linear-gradient(145deg,rgba(255,240,200,.5),rgba(255,248,225,.8));
  border:1px solid rgba(180,120,0,.22);
  display:flex;align-items:center;justify-content:center;
  position:relative;overflow:hidden;
}
.bc-hero .f-vis::before{
  content:'';position:absolute;inset:0;
  background:conic-gradient(from 0deg at 50% 50%,rgba(232,144,10,.07) 0%,transparent 50%,rgba(232,144,10,.07) 100%);
  animation:spin 16s linear infinite;
}
@keyframes spin{to{transform:rotate(360deg)}}
.bc-hero .f-vis svg{width:50px;height:50px;color:var(--au4);opacity:.55;position:relative;z-index:1}

.fbul{margin-top:13px;display:flex;flex-direction:column;gap:6px}
.fbul li{display:flex;align-items:center;gap:8px;font-size:.81rem;color:var(--tx2);font-family:'Tajawal',sans-serif}
.fbul li svg{width:13px;height:13px;color:var(--em5);flex-shrink:0}

.big-metric{
  font-family:'Tajawal',sans-serif;font-size:2.5rem;font-weight:900;line-height:1;margin-bottom:5px;
}

/* ══════════════════════════════════════════════════
   PROCESS — HOW IT WORKS (Major Redesign)
══════════════════════════════════════════════════ */
.proc-section{
  padding:96px 0;
  background:
    linear-gradient(180deg,rgba(242,238,230,1),rgba(248,245,237,1) 50%,rgba(242,238,230,1));
  border-top:1px solid rgba(180,130,20,.14);
  border-bottom:1px solid rgba(180,130,20,.14);
  position:relative;overflow:hidden;
}

/* Industrial gear watermark */
.proc-section::before{
  content:'';position:absolute;left:-120px;top:50%;transform:translateY(-50%);
  width:500px;height:500px;border-radius:50%;
  border:60px solid rgba(180,120,0,.04);
  pointer-events:none;
}
.proc-section::after{
  content:'';position:absolute;right:-80px;top:30%;
  width:300px;height:300px;border-radius:50%;
  border:40px solid rgba(180,70,10,.035);
  pointer-events:none;
}

.proc-head{
  display:flex;align-items:flex-end;justify-content:space-between;
  gap:24px;flex-wrap:wrap;margin-bottom:64px;
}

/* HORIZONTAL TIMELINE — fully redesigned */
.timeline{position:relative;padding-top:24px}

/* Connecting rail — industrial pipe look */
.timeline-rail{
  position:absolute;top:60px;right:calc(100%/6);left:calc(100%/6);
  height:3px;
  background:linear-gradient(90deg,
    transparent 0%,
    rgba(180,110,0,.5) 8%,
    var(--au5) 30%,
    var(--fe4) 50%,
    var(--au5) 70%,
    rgba(180,110,0,.5) 92%,
    transparent 100%
  );
  border-radius:2px;
}

/* Rail glow */
.timeline-rail::after{
  content:'';position:absolute;top:-2px;left:0;right:0;height:7px;
  background:linear-gradient(90deg,transparent,rgba(232,144,10,.2),rgba(200,74,12,.14),rgba(232,144,10,.2),transparent);
  filter:blur(4px);
}

/* Rail bolts */
.timeline-rail::before{
  content:'';position:absolute;top:-6px;left:50%;transform:translateX(-50%);
  width:15px;height:15px;border-radius:50%;
  background:var(--au4);
  box-shadow:0 0 12px var(--glow-au);
}

.steps-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:20px;position:relative}

/* Individual step card — industrial panel style */
.step-card{
  background:#fff;
  border:1px solid rgba(180,130,20,.16);
  border-radius:16px;
  padding:0;overflow:hidden;
  position:relative;
  transition:all var(--T);
}
.step-card:hover{
  transform:translateY(-6px);
  border-color:rgba(180,120,0,.4);
  box-shadow:0 16px 40px rgba(0,0,0,.12),0 0 24px var(--step-glow,rgba(232,144,10,.08));
}

/* Colored header band */
.step-band{
  height:5px;
  background:var(--step-grad);
  position:relative;
}
.step-band::after{
  content:'';position:absolute;top:0;left:0;right:0;bottom:0;
  background:linear-gradient(90deg,rgba(255,255,255,.15),transparent);
}

/* Step number bubble — positioned on the rail */
.step-bubble{
  width:48px;height:48px;border-radius:50%;
  border:3px solid var(--step-color);
  background:#f0ece4;
  display:flex;align-items:center;justify-content:center;
  font-family:'Tajawal',sans-serif;font-size:.95rem;font-weight:900;
  color:var(--step-color);
  margin:0 auto;
  position:relative;top:-24px;
  box-shadow:0 0 20px var(--step-glow,rgba(232,144,10,.2)),0 4px 12px rgba(0,0,0,.4);
  flex-shrink:0;
}

.step-body{
  padding:4px 24px 28px;
  text-align:center;
}

/* Icon in step */
.step-icon{
  width:52px;height:52px;border-radius:14px;
  display:flex;align-items:center;justify-content:center;
  margin:0 auto 16px;
  background:var(--step-bg);
  border:1px solid var(--step-border);
}
.step-icon svg{width:25px;height:25px;color:var(--step-color)}

.step-card h4{
  font-family:'Tajawal',sans-serif;
  font-size:1.08rem;font-weight:800;margin-bottom:10px;
  color:var(--tx1);
}
.step-card p{
  font-size:.84rem;color:var(--tx2);line-height:1.82;
  font-family:'Tajawal',sans-serif;
}

/* Step variants */
.step-1{
  --step-color:var(--au4);
  --step-grad:linear-gradient(90deg,var(--au5),var(--au3));
  --step-glow:rgba(232,144,10,.18);
  --step-bg:var(--bg-au);
  --step-border:var(--br-au);
}
.step-2{
  --step-color:var(--fe3);
  --step-grad:linear-gradient(90deg,var(--fe5),var(--fe3));
  --step-glow:rgba(200,74,12,.16);
  --step-bg:var(--bg-fe);
  --step-border:var(--br-fe);
}
.step-3{
  --step-color:var(--em3);
  --step-grad:linear-gradient(90deg,var(--em5),var(--em3));
  --step-glow:rgba(16,185,122,.15);
  --step-bg:var(--bg-em);
  --step-border:var(--br-em);
}

/* ── Sudan Context Strip ── */
.sudan-strip{
  margin-top:60px;
  background:#fff;
  border:1px solid rgba(180,130,20,.18);
  border-radius:var(--r-lg);
  padding:32px 36px;
  display:grid;grid-template-columns:auto 1fr auto;
  align-items:center;gap:32px;
  position:relative;overflow:hidden;
}
.sudan-strip::before{
  content:'';position:absolute;top:0;left:0;right:0;height:2px;
  background:linear-gradient(90deg,#da121a 25%,#fff 25%,#fff 50%,#000 50%,#000 75%,#007229 75%);
}
.sudan-flag-vert{
  display:flex;flex-direction:column;gap:0;
  width:6px;height:64px;border-radius:3px;overflow:hidden;flex-shrink:0;
}
.sudan-flag-vert span:nth-child(1){flex:1;background:#da121a}
.sudan-flag-vert span:nth-child(2){flex:1;background:#fff}
.sudan-flag-vert span:nth-child(3){flex:1;background:#000}

.sudan-text h4{font-family:'Tajawal',sans-serif;font-size:1rem;font-weight:800;margin-bottom:5px;color:var(--tx1)}
.sudan-text p{font-family:'Tajawal',sans-serif;font-size:.85rem;color:var(--tx2);line-height:1.75}
.sudan-stat{text-align:center;flex-shrink:0}
.sudan-stat-n{font-family:'Tajawal',sans-serif;font-size:1.8rem;font-weight:900;color:var(--au3);line-height:1}
.sudan-stat-l{font-size:.72rem;color:var(--tx3);font-family:'Tajawal',sans-serif;margin-top:4px}

/* Trust chips */
.trust-row{display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin-top:40px}
.trc{
  background:#fff;border:1px solid rgba(0,0,0,.09);
  border-radius:var(--r-lg);padding:22px;
  display:flex;align-items:flex-start;gap:13px;
}
.tric{width:40px;height:40px;border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0}
.tric svg{width:19px;height:19px}
.trb strong{display:block;font-size:.9rem;font-weight:700;margin-bottom:4px;font-family:'Tajawal',sans-serif}
.trb p{font-size:.79rem;color:var(--tx2);line-height:1.7;font-family:'Tajawal',sans-serif}

/* ══ COMPARISON ═════════════════════════════════════ */
.cmp-shell{
  border-radius:var(--r-xl);
  border:1px solid rgba(180,130,20,.18);
  background:#fff;overflow:hidden;
  box-shadow:0 24px 64px rgba(0,0,0,.4);
}
.cmp-shell .sx{overflow-x:auto}
.cmp-shell table{width:100%;border-collapse:collapse;min-width:540px}
.cmp-shell th{
  padding:17px 22px;text-align:right;
  font-size:.7rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;
  border-bottom:1px solid rgba(180,130,20,.14);
  background:rgba(248,244,234,1);
  font-family:'Tajawal',sans-serif;
}
.cmp-shell th:nth-child(1){color:var(--tx3)}
.cmp-shell th:nth-child(2){color:var(--au3)}
.cmp-shell th:nth-child(3){color:var(--tx3)}
.cmp-shell td{padding:14px 22px;font-size:.86rem;border-bottom:1px solid rgba(0,0,0,.05);font-family:'Tajawal',sans-serif}
.cmp-shell tbody tr:last-child td{border-bottom:none}
.cmp-shell tbody tr:hover{background:rgba(232,144,10,.05)}
.cmp-shell td:first-child{color:var(--tx2);font-weight:500}

.ty{display:inline-flex;align-items:center;gap:6px;font-size:.8rem;font-weight:700;color:var(--em5);background:var(--bg-em);border:1px solid var(--br-em);padding:4px 11px;border-radius:4px}
.ty svg{width:12px;height:12px}
.tn{display:inline-flex;align-items:center;gap:6px;font-size:.8rem;font-weight:500;color:var(--tx3);background:rgba(255,255,255,.03);border:1px solid var(--ln);padding:4px 11px;border-radius:4px}
.tn svg{width:11px;height:11px}

/* ══ PORTALS ════════════════════════════════════════ */
.portals{display:grid;grid-template-columns:repeat(2,1fr);gap:15px}
.pc{
  background:#fff;border:1px solid rgba(0,0,0,.09);
  border-radius:var(--r-lg);padding:28px 26px;
  display:flex;flex-direction:column;
  position:relative;overflow:hidden;transition:all var(--T);
}
.pc::before{
  content:'';position:absolute;top:0;left:0;right:0;height:1.5px;
  background:var(--pc-ln);opacity:.7;transition:opacity var(--T);
}
.pc:hover{transform:translateY(-4px);border-color:var(--pc-br,rgba(0,0,0,.2));box-shadow:0 20px 52px rgba(0,0,0,.45),0 0 28px var(--pc-glow,rgba(232,144,10,.06))}
.pc:hover::before{opacity:1}
.pc-au{--pc-ln:linear-gradient(90deg,transparent,var(--au4),transparent);--pc-br:var(--br-au);--pc-glow:var(--glow-au)}
.pc-fe{--pc-ln:linear-gradient(90deg,transparent,var(--fe3),transparent);--pc-br:var(--br-fe);--pc-glow:var(--glow-fe)}
.pc-ni{--pc-ln:linear-gradient(90deg,transparent,var(--ni3),transparent);--pc-br:var(--br-ni);--pc-glow:var(--glow-ni)}
.pc-em{--pc-ln:linear-gradient(90deg,transparent,var(--em3),transparent);--pc-br:var(--br-em);--pc-glow:rgba(16,185,122,.12)}
.pc-icon{width:48px;height:48px;border-radius:12px;display:flex;align-items:center;justify-content:center;margin-bottom:18px;flex-shrink:0}
.pc-icon svg{width:22px;height:22px}
.pc h3{font-family:'Tajawal',sans-serif;font-size:1.06rem;font-weight:800;margin-bottom:7px}
.pc p{font-size:.83rem;color:var(--tx2);line-height:1.8;flex:1;margin-bottom:20px;font-family:'Tajawal',sans-serif}
.btn-pc{
  display:inline-flex;align-items:center;gap:8px;align-self:flex-start;
  font-size:.81rem;font-weight:600;color:var(--tx2);
  padding:9px 14px;border-radius:6px;border:1px solid rgba(0,0,0,.12);background:rgba(0,0,0,.03);
  font-family:'Tajawal',sans-serif;transition:all var(--T);
}
.btn-pc svg{width:12px;height:12px}
.btn-pc:hover{color:var(--tx1);border-color:rgba(180,120,0,.35);background:rgba(232,144,10,.08);transform:translateX(-3px)}

/* ══ CTA BANNER ══════════════════════════════════════ */
.cta-wrap{
  margin:0 0 92px;border-radius:var(--r-xl);padding:60px 52px;
  background:linear-gradient(140deg,rgba(255,236,180,.5) 0%,rgba(255,248,235,1) 40%,rgba(255,220,160,.4) 100%);
  border:1px solid rgba(180,130,20,.25);
  display:grid;grid-template-columns:1fr auto;gap:40px;align-items:center;
  position:relative;overflow:hidden;
  box-shadow:0 12px 40px rgba(0,0,0,.08),0 0 0 1px rgba(255,220,100,.12) inset;
}
.cta-wrap::before{content:'';position:absolute;top:-120px;right:-120px;width:400px;height:400px;border-radius:50%;background:radial-gradient(circle,rgba(255,200,80,.18),transparent 70%)}
.cta-wrap::after{content:'';position:absolute;bottom:-100px;left:-80px;width:320px;height:320px;border-radius:50%;background:radial-gradient(circle,rgba(255,180,100,.14),transparent 70%)}
.cta-deco{position:absolute;bottom:0;left:0;right:0;height:2px;background:linear-gradient(90deg,transparent,var(--au5) 30%,var(--fe4) 70%,transparent)}
.cta-txt{position:relative;z-index:1}
.cta-txt h2{font-family:'Tajawal',sans-serif;font-size:clamp(1.5rem,3vw,2.2rem);font-weight:900;letter-spacing:-.02em;margin-bottom:11px}
.cta-txt p{color:var(--tx2);font-size:.96rem;line-height:1.82;max-width:440px;font-family:'Tajawal',sans-serif}
.cta-btns{display:flex;flex-direction:column;gap:10px;position:relative;z-index:1;flex-shrink:0}

/* ══ FOOTER ══════════════════════════════════════════ */
.site-footer{border-top:1px solid rgba(180,130,20,.15);background:rgba(242,238,230,1)}
.foot-inner{padding:24px 0;display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap}
.foot-left{display:flex;align-items:center;gap:14px}
.foot-copy{font-size:.77rem;color:var(--tx3);font-family:'Tajawal',sans-serif}
.foot-links{display:flex;gap:4px;flex-wrap:wrap}
.foot-links a{font-size:.77rem;color:var(--tx3);padding:7px 12px;border-radius:6px;border:1px solid rgba(0,0,0,.1);transition:all var(--T);font-family:'Tajawal',sans-serif}
.foot-links a:hover{color:var(--tx1);border-color:var(--br-au);background:rgba(232,144,10,.08)}

/* ══ REVEAL ══════════════════════════════════════════ */
.rv{opacity:0;transform:translateY(24px);transition:opacity .6s var(--ease),transform .6s var(--ease)}
.rv.in{opacity:1;transform:none}
.d1{transition-delay:.08s}.d2{transition-delay:.16s}.d3{transition-delay:.24s}

@keyframes up{from{opacity:0;transform:translateY(22px)}to{opacity:1;transform:none}}

/* ══ RESPONSIVE ══════════════════════════════════════ */
@media(max-width:1024px){
  .hero-grid{grid-template-columns:1fr;gap:46px}.hero-vis{order:-1}.fl1,.fl2{display:none}
  .bc-hero{grid-column:span 12;flex-direction:column}.f-vis{width:100%;height:88px}
  .s8,.s7{grid-column:span 12}.s6,.s5{grid-column:span 6}.s4{grid-column:span 6}.s3{grid-column:span 6}
  .sudan-strip{grid-template-columns:1fr;text-align:center}
  .sudan-flag-vert{display:none}
}
@media(max-width:860px){
  .nav-mid,.btn-ng{display:none}.ham{display:flex}
  .proof-row{grid-template-columns:repeat(2,1fr)}.proof-cell:nth-child(3)::before{display:none}
  .steps-grid{grid-template-columns:1fr}.timeline-rail{display:none}
  .portals{grid-template-columns:1fr}
  .trust-row{grid-template-columns:1fr}
  .cta-wrap{grid-template-columns:1fr;padding:34px 22px}
  .cta-btns{flex-direction:row;flex-wrap:wrap}
  .s4,.s5,.s6{grid-column:span 12}.s3{grid-column:span 6}
}
@media(max-width:580px){
  .hero{padding:58px 0 48px}.hero-h1{font-size:2.1rem}
  .sec{padding:62px 0}.s3{grid-column:span 12}
  .proof-row{grid-template-columns:1fr 1fr}
  .cta-wrap{border-radius:var(--r-lg)}
  .proc-section::before,.proc-section::after{display:none}
}
@media(prefers-reduced-motion:reduce){*,*::before,*::after{animation-duration:.01ms!important;transition-duration:.01ms!important}}
</style>
</head>
<body>

<!-- ATMOSPHERE -->
<div class="atm" aria-hidden="true">
  <div class="atm-mesh"></div>
  <div class="atm-grid"></div>
  <div class="atm-stripes"></div>
  <div class="orb o1"></div><div class="orb o2"></div><div class="orb o3"></div><div class="orb o4"></div>
  <div class="atm-grain"></div>
</div>

<!-- DRAWER -->
<div class="drawer" id="drawer" role="dialog" aria-modal="true" aria-label="القائمة">
  <div class="drw-top">
    <div class="logo">
      <div class="lgem">
        <!-- Pickaxe icon -->
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" style="color:var(--au3)"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/></svg>
      </div>
      <div class="lname"><strong>منصة إنجاز</strong></div>
    </div>
    <button class="drw-x" id="drwX" aria-label="إغلاق">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
    </button>
  </div>
  <nav>
    <a href="#features">المزايا</a>
    <a href="#process">كيف تعمل</a>
    <a href="#comparison">المقارنة</a>
    <a href="#portals">بوابات الدخول</a>
    <a href="<?php echo e(landing_url('company/login.php')); ?>">بوابة الشركات</a>
    <a href="<?php echo e(landing_url('admin/login.php')); ?>">لوحة الإدارة</a>
  </nav>
  <div class="drw-foot">
    <a href="<?php echo e(landing_url('login.php')); ?>">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:16px;height:16px"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>
      دخول النظام
    </a>
  </div>
</div>

<div class="wrap">

<!-- ══════════════════ HEADER ══════════════════ -->
<header class="site-header">
  <div class="hbar" id="hbar">
    <a class="logo" href="<?php echo e(landing_url('index.php')); ?>" aria-label="منصة إنجاز">
      <div class="lgem">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" style="color:var(--au3)"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/></svg>
      </div>
      <div class="lname">
        <strong>منصة إنجاز</strong>
        <small>Sudan Mining SaaS</small>
      </div>
    </a>

    <nav class="nav-mid" aria-label="التنقل الرئيسي">
      <a href="#features">المزايا</a>
      <a href="#process">كيف تعمل</a>
      <a href="#comparison">المقارنة</a>
      <a href="#portals">بوابات الدخول</a>
    </nav>

    <div class="nav-end">
      <a class="btn-ng" href="<?php echo e(landing_url('company/login.php')); ?>">بوابة الشركات</a>
      <a class="btn-np" href="<?php echo e(landing_url('login.php')); ?>">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>
        دخول النظام
      </a>
      <button class="ham" id="ham" aria-label="القائمة" aria-expanded="false" aria-controls="drawer">
        <span></span><span></span><span></span>
      </button>
    </div>
  </div>
</header>

<main>

<!-- ══════════════════ HERO ══════════════════ -->
<section class="hero">
  <div class="ctr">
    <!-- Sudan identity pill -->
    <div class="hero-pill">
      <div class="pill-dot">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><polyline points="20 6 9 17 4 12"/></svg>
      </div>
      <span>منصة SaaS متخصصة لقطاع التعدين في السودان</span>
    </div>

    <div class="hero-grid">
      <!-- TEXT COLUMN -->
      <div>
        <!-- Sudan flag bar -->
        <div class="sudan-bar"><span></span><span></span><span></span><span></span></div>

        <h1 class="hero-h1">
          إدارة <span class="c-au">المناجم</span><br>
          والمعدات <span class="c-fe">الثقيلة</span><br>
          بدقة <span class="c-ni">عالمية</span>
        </h1>

        <p class="hero-sub">
          إنجاز مصممة خصيصاً لشركات التعدين في السودان — تربط المشاريع والمعدات الثقيلة والعقود وساعات التشغيل في منظومة واحدة تواكب ضخامة العمليات الميدانية.
        </p>

        <div class="hero-cta">
          <a class="btn-gold" href="<?php echo e(landing_url('company/register.php')); ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/></svg>
            تسجيل شركتك الآن
          </a>
          <a class="btn-wire" href="#features">
            اكتشف النظام
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
          </a>
        </div>

        <div class="hero-trust">
          <div class="ti">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
            <span>بيانات مشفرة وآمنة</span>
          </div>
          <div class="tsep"></div>
          <div class="ti">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
            <span>تشغيل 24/7 سحابي</span>
          </div>
          <div class="tsep"></div>
          <div class="ti">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="3" width="15" height="13" rx="2"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
            <span>دعم كامل للمعدات الثقيلة</span>
          </div>
        </div>
      </div>

      <!-- DASHBOARD VISUAL -->
      <div class="hero-vis">
        <div class="dash">
          <div class="dc-head">
            <div class="dc-dots"><span></span><span></span><span></span></div>
            <div class="dc-label">لوحة العمليات الميدانية</div>
          </div>
          <!-- KPIs -->
          <div class="dc-kpis">
            <div class="kpi">
              <div class="kpi-v" style="background:linear-gradient(135deg,var(--au3),var(--au4));-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text">٤٨</div>
              <div class="kpi-l">مشروع تعدين نشط</div>
            </div>
            <div class="kpi">
              <div class="kpi-v" style="background:linear-gradient(135deg,var(--fe3),var(--fe4));-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text">١٢٣</div>
              <div class="kpi-l">معدة ثقيلة مسجلة</div>
            </div>
            <div class="kpi">
              <div class="kpi-v" style="background:linear-gradient(135deg,var(--em3),var(--em5));-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text">٩٢٪</div>
              <div class="kpi-l">كفاءة التشغيل</div>
            </div>
          </div>
          <!-- Bars -->
          <div class="dc-bars">
            <div class="bar" style="height:40%;background:rgba(232,144,10,.18);border:1px solid rgba(180,120,0,.25);animation-delay:.10s"></div>
            <div class="bar" style="height:62%;background:rgba(232,144,10,.24);border:1px solid rgba(180,120,0,.28);animation-delay:.14s"></div>
            <div class="bar" style="height:48%;background:rgba(200,74,12,.18);border:1px solid rgba(180,70,10,.25);animation-delay:.18s"></div>
            <div class="bar" style="height:78%;background:rgba(232,144,10,.26);border:1px solid rgba(180,120,0,.30);animation-delay:.22s"></div>
            <div class="bar" style="height:55%;background:rgba(200,74,12,.20);border:1px solid rgba(180,70,10,.28);animation-delay:.26s"></div>
            <div class="bar" style="height:90%;background:rgba(232,144,10,.30);border:1px solid rgba(180,120,0,.35);animation-delay:.30s"></div>
            <div class="bar" style="height:70%;background:rgba(200,74,12,.22);border:1px solid rgba(180,70,10,.30);animation-delay:.34s"></div>
            <div class="bar" style="height:95%;background:rgba(16,185,122,.18);border:1px solid rgba(10,160,100,.25);animation-delay:.38s"></div>
          </div>
          <!-- Activity -->
          <div class="dc-acts">
            <div class="dc-act"><div class="act-dot" style="background:var(--em5)"></div><div class="act-bar" style="flex:.75"></div><div class="act-tag" style="background:var(--bg-em);color:var(--em3);border:1px solid var(--br-em)">تشغيل</div></div>
            <div class="dc-act"><div class="act-dot" style="background:var(--au3)"></div><div class="act-bar" style="flex:.9"></div><div class="act-tag" style="background:var(--bg-au);color:var(--au3);border:1px solid var(--br-au)">حفر</div></div>
            <div class="dc-act"><div class="act-dot" style="background:var(--fe3)"></div><div class="act-bar" style="flex:.5"></div><div class="act-tag" style="background:var(--bg-fe);color:var(--fe3);border:1px solid var(--br-fe)">صيانة</div></div>
          </div>
        </div>
        <!-- Float cards -->
        <div class="fl1">
          <div class="fl-lbl">ساعات تشغيل اليوم</div>
          <div class="fl-val" style="color:var(--au3)">٣٢٠ س</div>
          <div class="fl-chg" style="color:var(--em5)">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" style="width:11px;height:11px"><polyline points="18 15 12 9 6 15"/></svg>
            +١٨٪ هذا الأسبوع
          </div>
        </div>
        <div class="fl2">
          <div class="fl-lbl">عقود سارية</div>
          <div class="fl-val" style="color:var(--fe3)">٢٧ عقد</div>
          <div class="fl-chg" style="color:var(--au3)">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" style="width:11px;height:11px"><polyline points="18 15 12 9 6 15"/></svg>
            هذا الشهر
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ══ PROOF STRIP ════════════════════════════════ -->
<div class="proof">
  <div class="ctr">
    <div class="proof-row">
      <div class="proof-cell"><div class="proof-n n-au">+12</div><div class="proof-d">وحدة تشغيل متكاملة</div></div>
      <div class="proof-cell"><div class="proof-n n-fe">3</div><div class="proof-d">بوابات دخول حسب الدور</div></div>
      <div class="proof-cell"><div class="proof-n n-ni">RTL</div><div class="proof-d">تجربة عربية أصيلة</div></div>
      <div class="proof-cell"><div class="proof-n n-em">24/7</div><div class="proof-d">وصول سحابي مستمر</div></div>
    </div>
  </div>
</div>

<!-- ══ FEATURES BENTO ══════════════════════════════ -->
<section class="sec feat-bg" id="features">
  <div class="ctr">
    <div class="rv">
      <div class="ey ey-au">المزايا</div>
      <h2 class="sec-h">مبنية لضخامة عمليات التعدين</h2>
      <p class="sec-sub">كل ميزة صُممت لتواكب تحديات التعدين الفعلية — من المنجم حتى العقد حتى التقرير.</p>
    </div>
    <div class="bento">

      <!-- BIG -->
      <div class="bc bc-hero rv d1" style="--bc-line:linear-gradient(90deg,transparent,var(--au4),transparent)">
        <div>
          <div class="fic fic-au">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M8 3H5a2 2 0 0 0-2 2v3m18 0V5a2 2 0 0 0-2-2h-3m0 18h3a2 2 0 0 0 2-2v-3M3 16v3a2 2 0 0 0 2 2h3"/></svg>
          </div>
          <h3>إدارة المناجم والمشاريع بشكل شامل</h3>
          <p>ربط فوري بين المناجم والمشاريع والعملاء مع تتبع الحالة ونوع التشغيل. رؤية 360° لكل العمليات الميدانية من لحظة بدء الحفر حتى الإغلاق الكامل.</p>
          <ul class="fbul">
            <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><polyline points="20 6 9 17 4 12"/></svg> دعم متعدد المناجم والمواقع المتزامنة</li>
            <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><polyline points="20 6 9 17 4 12"/></svg> تتبع إنتاج المنجم وحالته التشغيلية لحظة بلحظة</li>
            <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><polyline points="20 6 9 17 4 12"/></svg> ربط العملاء والموردين بالمشاريع تلقائياً</li>
          </ul>
        </div>
        <div class="f-vis">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width=".7" stroke-linecap="round" stroke-linejoin="round"><path d="M8 3H5a2 2 0 0 0-2 2v3m18 0V5a2 2 0 0 0-2-2h-3m0 18h3a2 2 0 0 0 2-2v-3M3 16v3a2 2 0 0 0 2 2h3"/></svg>
        </div>
      </div>

      <!-- Equipment heavy -->
      <div class="bc s4 rv d2" style="--bc-line:linear-gradient(90deg,transparent,var(--fe3),transparent);background:linear-gradient(145deg,rgba(200,74,12,.04),#fff)">
        <div class="fic fic-fe">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="3" width="15" height="13" rx="2"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
        </div>
        <div class="big-metric" style="background:linear-gradient(135deg,var(--fe3),var(--fe4));-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text">٣٦٠°</div>
        <h3>إدارة المعدات الثقيلة</h3>
        <p>حفارات، جرافات، شاحنات نقل، مولدات — كل معدة تعرف أين هي وماذا تفعل في أي لحظة.</p>
      </div>

      <!-- Contracts -->
      <div class="bc s6 rv" style="--bc-line:linear-gradient(90deg,transparent,var(--au3),transparent)">
        <div class="fic fic-au">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
        </div>
        <h3>دورة حياة العقود الكاملة</h3>
        <p>تجديد، إيقاف، إنهاء، تسوية ودمج عقود التشغيل مع سجل تدقيق واضح لكل إجراء. لا فجوات في المسار المالي.</p>
      </div>

      <!-- Hours -->
      <div class="bc s3 rv d1" style="--bc-line:linear-gradient(90deg,transparent,var(--fe3),transparent)">
        <div class="fic fic-fe">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
        </div>
        <div class="big-metric" style="background:linear-gradient(135deg,var(--fe3),var(--fe4));-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text">24/7</div>
        <h3>ساعات الوردية والإنتاج</h3>
        <p>تسجيل ورديات العمل وساعات الحفر والأعطال لكل معدة ومشغل.</p>
      </div>

      <!-- Reports -->
      <div class="bc s3 rv d2" style="--bc-line:linear-gradient(90deg,transparent,var(--em3),transparent)">
        <div class="fic fic-em">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
        </div>
        <div class="big-metric" style="background:linear-gradient(135deg,var(--em3),var(--em5));-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text">∞</div>
        <h3>تقارير الإنتاج والأداء</h3>
        <p>تحليلات فورية لإنتاجية المنجم والمعدات وكفاءة الفرق الميدانية.</p>
      </div>

      <!-- RBAC -->
      <div class="bc s6 rv d3" style="--bc-line:linear-gradient(90deg,transparent,var(--ni3),transparent)">
        <div class="fic fic-ni">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
        </div>
        <h3>صلاحيات متعددة الأدوار</h3>
        <p>إدارة عليا، مشرفو مواقع، سائقو معدات، مشغلو تشغيل — كل دور يرى ويفعل ما يحتاجه فقط مع سجل نشاط كامل ومدقق.</p>
      </div>

    </div>
  </div>
</section>

<!-- ══ HOW IT WORKS — Redesigned ════════════════════ -->
<section class="proc-section" id="process">
  <div class="ctr">
    <div class="proc-head rv">
      <div>
        <div class="ey ey-fe">كيف تعمل</div>
        <h2 class="sec-h">ثلاث خطوات للانطلاق</h2>
        <p class="sec-sub" style="max-width:480px">من لحظة تسجيل شركتك حتى إدارة أول منجم كاملاً — في وقت قياسي بدون تعقيد أو أيام انتظار.</p>
      </div>
      <a class="btn-gold" href="<?php echo e(landing_url('company/register.php')); ?>" style="align-self:center">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:16px;height:16px"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/></svg>
        ابدأ الآن
      </a>
    </div>

    <!-- TIMELINE -->
    <div class="timeline rv">
      <div class="timeline-rail" aria-hidden="true"></div>
      <div class="steps-grid">

        <!-- STEP 1 -->
        <article class="step-card step-1">
          <div class="step-band"></div>
          <div style="display:flex;justify-content:center">
            <div class="step-bubble">١</div>
          </div>
          <div class="step-body">
            <div class="step-icon">
              <!-- Building/Company icon -->
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
            </div>
            <h4>تسجيل الشركة وفريق العمل</h4>
            <p>أنشئ حساب شركتك وأضف المستخدمين وحدد الأدوار — مدير موقع، مشرف معدات، سائق، محاسب. كل شخص يحصل على صلاحياته الدقيقة.</p>
          </div>
        </article>

        <!-- STEP 2 -->
        <article class="step-card step-2">
          <div class="step-band"></div>
          <div style="display:flex;justify-content:center">
            <div class="step-bubble">٢</div>
          </div>
          <div class="step-body">
            <div class="step-icon">
              <!-- Truck/Equipment icon -->
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="3" width="15" height="13" rx="2"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
            </div>
            <h4>رفع المناجم والمعدات والعقود</h4>
            <p>أضف مناجمك وأرقام معداتك الثقيلة ومشغليها، وادخل عقود التشغيل مع مواعيدها وشروطها — كل شيء مرتبط ببعض تلقائياً.</p>
          </div>
        </article>

        <!-- STEP 3 -->
        <article class="step-card step-3">
          <div class="step-band"></div>
          <div style="display:flex;justify-content:center">
            <div class="step-bubble">٣</div>
          </div>
          <div class="step-body">
            <div class="step-icon">
              <!-- Chart/operations icon -->
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
            </div>
            <h4>تشغيل يومي وتقارير فورية</h4>
            <p>فرقك الميدانية تسجل ساعات العمل والأعطال، وأنت تتابع كل شيء من لوحة التحكم في الوقت الفعلي وتستخرج تقارير الإدارة بنقرة واحدة.</p>
          </div>
        </article>

      </div>
    </div>

    <!-- Sudan context strip -->
    <div class="sudan-strip rv" style="margin-top:52px">
      <div class="sudan-flag-vert"><span></span><span></span><span></span></div>
      <div class="sudan-text">
        <h4>مصممة لبيئة التعدين السوداني</h4>
        <p>تأخذ في الاعتبار تحديات التعدين في السودان — من مواقع الولايات النائية وتقلبات ساعات العمل الميداني، إلى تعقيدات عقود الموردين ومتطلبات الجهات التنظيمية المحلية.</p>
      </div>
      <div class="sudan-stat">
        <div class="sudan-stat-n">السودان</div>
        <div class="sudan-stat-l">مركز التعدين الأفريقي</div>
      </div>
    </div>

    <!-- Trust row -->
    <div class="trust-row">
      <div class="trc rv">
        <div class="tric" style="background:var(--bg-em);border:1px solid var(--br-em)">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" style="color:var(--em5)"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
        </div>
        <div class="trb">
          <strong>بيانات آمنة ومشفرة</strong>
          <p>جميع بيانات المناجم والعقود مشفرة أثناء النقل والتخزين مع سياسات صارمة للخصوصية.</p>
        </div>
      </div>
      <div class="trc rv d1">
        <div class="tric" style="background:var(--bg-au);border:1px solid var(--br-au)">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" style="color:var(--au3)"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>
        </div>
        <div class="trb">
          <strong>يعمل على كل الأجهزة</strong>
          <p>جهاز المدير في الخرطوم أو تابلت المشرف في الموقع — نفس التجربة بدون فرق.</p>
        </div>
      </div>
      <div class="trc rv d2">
        <div class="tric" style="background:var(--bg-fe);border:1px solid var(--br-fe)">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" style="color:var(--fe3)"><polyline points="17 1 21 5 17 9"/><path d="M3 11V9a4 4 0 0 1 4-4h14"/><polyline points="7 23 3 19 7 15"/><path d="M21 13v2a4 4 0 0 1-4 4H3"/></svg>
        </div>
        <div class="trb">
          <strong>تحديثات مستمرة بدون توقف</strong>
          <p>تطوير دوري بمزايا تعكس احتياجات قطاع التعدين المتطورة تصل تلقائياً.</p>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ══ COMPARISON ══════════════════════════════════ -->
<section class="sec" id="comparison">
  <div class="ctr">
    <div class="rv">
      <div class="ey ey-au">المقارنة</div>
      <h2 class="sec-h">لماذا إنجاز وليس حل عام؟</h2>
      <p class="sec-sub">الفرق بين أداة عامة وحل مبني لضخامة التعدين السوداني.</p>
    </div>
    <div class="cmp-shell rv" style="margin-top:40px">
      <div class="sx">
        <table>
          <thead>
            <tr>
              <th scope="col">المعيار</th>
              <th scope="col">منصة إنجاز</th>
              <th scope="col">حلول SaaS عامة</th>
            </tr>
          </thead>
          <tbody>
            <tr><td>إدارة المناجم ومشاريع التعدين</td><td><span class="ty"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><polyline points="20 6 9 17 4 12"/></svg>مبنية داخل النظام</span></td><td><span class="tn"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>تخصيص مكلف</span></td></tr>
            <tr><td>تشغيل المعدات الثقيلة ومشغليها</td><td><span class="ty"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><polyline points="20 6 9 17 4 12"/></svg>تدفق واحد مترابط</span></td><td><span class="tn"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>أدوات منفصلة</span></td></tr>
            <tr><td>دورة حياة العقود (تجديد/إيقاف/دمج)</td><td><span class="ty"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><polyline points="20 6 9 17 4 12"/></svg>مدعومة بالكامل</span></td><td><span class="tn"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>جزئية</span></td></tr>
            <tr><td>واجهة عربية RTL أصيلة</td><td><span class="ty"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><polyline points="20 6 9 17 4 12"/></svg>مدمجة افتراضياً</span></td><td><span class="tn"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>ناقصة</span></td></tr>
            <tr><td>ملاءمة بيئة التعدين السوداني</td><td><span class="ty"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><polyline points="20 6 9 17 4 12"/></svg>مصممة لها</span></td><td><span class="tn"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>غير متاح</span></td></tr>
            <tr><td>تكلفة الإعداد والتخصيص</td><td><span class="ty"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><polyline points="20 6 9 17 4 12"/></svg>صفر — جاهز فوراً</span></td><td><span class="tn"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>عالية جداً</span></td></tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</section>

<!-- ══ PORTALS ════════════════════════════════════ -->
<section class="sec" id="portals" style="padding-top:0">
  <div class="ctr">
    <div class="rv">
      <div class="ey ey-ni">بوابات الدخول</div>
      <h2 class="sec-h">المدخل الصحيح لكل دور</h2>
      <p class="sec-sub">من المدير التنفيذي في الخرطوم حتى سائق الحفارة في الموقع — كل دور له مدخله الخاص.</p>
    </div>
    <div class="portals" style="margin-top:40px">

      <article class="pc pc-au rv">
        <div class="pc-icon" style="background:var(--bg-au);border:1px solid var(--br-au)">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" style="color:var(--au3)"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
        </div>
        <h3>تسجيل شركة تعدين جديدة</h3>
        <p>ابدأ رحلتك مع إنجاز بإنشاء حساب شركتك. اضبط المواقع والمناجم والفرق خلال دقائق — جاهز للتشغيل الفعلي من اليوم الأول.</p>
        <a class="btn-pc" href="<?php echo e(landing_url('company/register.php')); ?>">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/></svg>
          إنشاء حساب
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
        </a>
      </article>

      <article class="pc pc-fe rv d1">
        <div class="pc-icon" style="background:var(--bg-fe);border:1px solid var(--br-fe)">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" style="color:var(--fe3)"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
        </div>
        <h3>بوابة مستخدمي الشركات</h3>
        <p>للموظفين والفرق التشغيلية — مشرفو المواقع، المهندسون، فرق المتابعة — للوصول لمشاريعهم وعقودهم ومهامهم اليومية.</p>
        <a class="btn-pc" href="<?php echo e(landing_url('company/login.php')); ?>">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>
          دخول بوابة الشركات
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
        </a>
      </article>

      <article class="pc pc-ni rv d1">
        <div class="pc-icon" style="background:var(--bg-ni);border:1px solid var(--br-ni)">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" style="color:var(--ni3)"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
        </div>
        <h3>دخول نظام التشغيل الميداني</h3>
        <p>للمشغلين الميدانيين وسائقي المعدات لإدخال ساعات العمل والأعطال ومتابعة المهام اليومية بواجهة مبسطة تعمل في الموقع.</p>
        <a class="btn-pc" href="<?php echo e(landing_url('login.php')); ?>">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>
          دخول نظام التشغيل
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
        </a>
      </article>

      <article class="pc pc-em rv d2">
        <div class="pc-icon" style="background:var(--bg-em);border:1px solid var(--br-em)">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" style="color:var(--em5)"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
        </div>
        <h3>لوحة الإدارة العليا</h3>
        <p>للمديرين التنفيذيين ومسؤولي النظام — تحكم كامل في الشركات والصلاحيات والتقارير التنفيذية وإعدادات النظام.</p>
        <a class="btn-pc" href="<?php echo e(landing_url('admin/login.php')); ?>">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M12 1v4M12 19v4M4.22 4.22l2.83 2.83M16.95 16.95l2.83 2.83M1 12h4M19 12h4M4.22 19.78l2.83-2.83M16.95 7.05l2.83-2.83"/></svg>
          دخول الإدارة
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
        </a>
      </article>

    </div>
  </div>
</section>

<!-- ══ CTA BLOCK ══════════════════════════════════ -->
<div class="ctr">
  <div class="cta-wrap rv">
    <div class="cta-deco"></div>
    <div class="cta-txt">
      <h2>ابدأ إدارة مناجمك الآن</h2>
      <p>منصة واحدة لجميع عمليات شركتك — من الحفارة حتى التقرير التنفيذي. لا تعقيد، لا إعداد طويل، جاهز من اليوم الأول.</p>
    </div>
    <div class="cta-btns">
      <a class="btn-gold" href="<?php echo e(landing_url('company/register.php')); ?>">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:16px;height:16px"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/></svg>
        تسجيل شركة تعدين
      </a>
      <a class="btn-wire" href="<?php echo e(landing_url('admin/login.php')); ?>">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:15px;height:15px"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
        لوحة الإدارة
      </a>
    </div>
  </div>
</div>

</main>

<!-- ══ FOOTER ════════════════════════════════════ -->
<footer class="site-footer">
  <div class="ctr foot-inner">
    <div class="foot-left">
      <div class="lgem" style="width:34px;height:34px;border-radius:9px">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" style="width:16px;height:16px;color:var(--au3)"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/></svg>
      </div>
      <span class="foot-copy">&copy; <?php echo (int)$year; ?> منصة إنجاز — نظام إدارة التعدين في السودان</span>
    </div>
    <div class="foot-links">
      <a href="<?php echo e(landing_url('login.php')); ?>">التشغيل الميداني</a>
      <a href="<?php echo e(landing_url('company/login.php')); ?>">بوابة الشركات</a>
      <a href="<?php echo e(landing_url('admin/login.php')); ?>">الإدارة العليا</a>
    </div>
  </div>
</footer>

</div><!-- /wrap -->

<script>
(function(){
  'use strict';
  var hbar=document.getElementById('hbar');
  window.addEventListener('scroll',function(){if(hbar)hbar.classList.toggle('sc',window.scrollY>20);},{passive:true});

  var ham=document.getElementById('ham'),drawer=document.getElementById('drawer'),drwX=document.getElementById('drwX');
  function openD(){drawer.classList.add('open');ham.setAttribute('aria-expanded','true');document.body.style.overflow='hidden';}
  function closeD(){drawer.classList.remove('open');ham.setAttribute('aria-expanded','false');document.body.style.overflow='';}
  if(ham)ham.addEventListener('click',openD);
  if(drwX)drwX.addEventListener('click',closeD);
  if(drawer){
    drawer.addEventListener('click',function(e){if(e.target===drawer)closeD();});
    drawer.querySelectorAll('nav a,.drw-foot a').forEach(function(a){a.addEventListener('click',closeD);});
  }
  document.addEventListener('keydown',function(e){if(e.key==='Escape')closeD();});

  /* Scroll reveal */
  var rvs=document.querySelectorAll('.rv');
  if('IntersectionObserver' in window){
    var io=new IntersectionObserver(function(entries){
      entries.forEach(function(e){if(e.isIntersecting){e.target.classList.add('in');io.unobserve(e.target);}});
    },{threshold:.09,rootMargin:'0px 0px -44px 0px'});
    rvs.forEach(function(el){io.observe(el);});
  } else {
    rvs.forEach(function(el){el.classList.add('in');});
  }

  /* Smooth hash scroll */
  document.querySelectorAll('a[href^="#"]').forEach(function(a){
    a.addEventListener('click',function(e){
      var id=this.getAttribute('href').slice(1);
      var el=id?document.getElementById(id):null;
      if(el){e.preventDefault();el.scrollIntoView({behavior:'smooth',block:'start'});}
    });
  });
})();
</script>
</body>
</html>

