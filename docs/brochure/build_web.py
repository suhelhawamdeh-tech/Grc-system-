#!/usr/bin/env python3
"""Responsive web version of the 8-page executive brief (same content as build_short.py).

  python3 docs/brochure/build_web.py   -> web/brief.html (+ web/img/*.jpg, made from img/*.png)
"""
import math
import os
import sys

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
import build_short as s  # noqa: E402
from build import HERE, badge, icon, rosette, star  # noqa: E402


def veiled(src, cls=''):
    return (f'<figure class="shot veil {cls}"><div class="bar"><i></i><i></i><i></i></div>'
            f'<div class="vw"><img src="img/{src}.jpg" alt="صورة توضيحية من المنصة"><span>عرض توضيحي</span></div></figure>')


def cards(items, cls):
    return ''.join(f'<article class="{cls}">{badge(i)}<div><h3>{t}</h3><p>{d}</p></div></article>' for i, t, d in items)


def head(kicker, title, intro=''):
    return (f'<header class="sh"><span class="kicker">{kicker}</span><h2>{title}</h2>'
            f'<div class="rule"><span></span>{icon("spark","rs")}<span></span></div>'
            + (f'<p class="intro">{intro}</p>' if intro else '') + '</header>')


W = 400
c = W / 2
wheel = f'<svg viewBox="0 0 {W} {W}" class="wheel" role="img" aria-label="ست ركائز حول منصة واحدة">'
wheel += ('<defs><radialGradient id="cg" cx="50%" cy="40%" r="60%"><stop offset="0" stop-color="#1d4477"/><stop offset="1" stop-color="#0B2545"/></radialGradient>'
          '<linearGradient id="gg" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#F1D78A"/><stop offset=".5" stop-color="#C9A227"/><stop offset="1" stop-color="#9A7B1E"/></linearGradient></defs>')
wheel += f'<circle cx="{c}" cy="{c}" r="{c-4}" fill="none" stroke="#C9A227" stroke-dasharray="3 7"/>'
wheel += f'<polygon points="{star(c, c, 175, 130, 12)}" fill="none" stroke="#E6C66A"/>'
for i, (ic, t, d) in enumerate(s.pillars):
    a = math.radians(-90 + i * 60)
    x, y = c + 150 * math.cos(a), c + 150 * math.sin(a)
    wheel += f'<line x1="{c}" y1="{c}" x2="{x:.1f}" y2="{y:.1f}" stroke="#C9A227" stroke-opacity=".6"/>'
    wheel += f'<circle cx="{x:.1f}" cy="{y:.1f}" r="22" fill="#FBF7EE" stroke="#C9A227" stroke-width="1.5"/>'
    wheel += f'<text x="{x:.1f}" y="{y+6:.1f}" class="wn">{i+1}</text>'
wheel += f'<polygon points="{star(c, c, 90, 74, 8)}" fill="url(#gg)"/><circle cx="{c}" cy="{c}" r="68" fill="url(#cg)"/>'
wheel += f'<text x="{c}" y="{c-6}" class="wt1">منصة</text><text x="{c}" y="{c+24}" class="wt2">واحدة</text></svg>'
plist = ''.join(f'<li><span class="pnum">{i+1}</span>{badge(ic)}<div><b>{t}</b><small>{d}</small></div></li>'
                for i, (ic, t, d) in enumerate(s.pillars))
stats = ''.join(f'<div class="stat"><b>{a}</b><span>{b}</span></div>' for a, b in s.stats)
cbl = ''.join(f'<div class="row"><div class="k">{icon(i)}<b>{t}</b></div><p>{d}</p></div>' for i, t, d in s.cbl)
ba = ''.join(f'<div class="ba-row"><div class="b">{a}</div><div class="ar" aria-hidden="true">←</div><div class="a">{b}</div></div>' for a, b in s.ba)
steps = ''.join(f'<li class="step"><div class="sn"><span>{n}</span></div><h3>{t}</h3><p>{d}</p></li>' for n, t, d in s.steps)

BODY = f'''
<main dir="rtl" lang="ar">
<section class="hero">
  <div class="ros r1" aria-hidden="true">{rosette(520, opacity=.25, stroke=1)}</div>
  <div class="wrap hero-in">
    <div class="hero-txt">
      <span class="tag">ملخص تنفيذي لإدارة المصرف</span>
      <p class="over">منصة الحوكمة وإدارة المخاطر والامتثال</p>
      <h1>امتثالٌ أوضح، وموارد أقوى، <span>ومصرفٌ أكثر أماناً</span></h1>
      <p class="lead">منصة عربية واحدة تساعد المصرف على الوفاء بمتطلبات مصرف ليبيا المركزي، وتنمية كفاءة موظفيه، وحماية أمواله وسمعته.</p>
      <div class="pills"><a href="#cbl">الامتثال الرقابي</a><a href="#people">تطوير الموارد</a><a href="#protect">حماية المصرف</a><a href="#value">القيمة والتطبيق</a></div>
    </div>
    <div class="hero-shot">{veiled("dashboard","tilt")}</div>
  </div>
  <div class="band"><div class="wrap">ثلاث نتائج… <b>في منصة واحدة</b></div></div>
</section>

<section class="sec ivory">
  <div class="wrap">
    {head("الفكرة", "لماذا الآن؟", "تتزايد توقعات مصرف ليبيا المركزي من المصارف في الحوكمة وإدارة المخاطر والامتثال والرقابة الشرعية، بينما تُدار هذه الملفات في أغلب المصارف <b>بجداول متفرقة ومتابعة يدوية</b>. والنتيجة جهد كبير، وصورة غير مكتملة أمام الإدارة والرقيب.")}
    <div class="quote"><div class="qros" aria-hidden="true">{rosette(130, opacity=.55)}</div>
      <blockquote>السؤال الذي تجيب عنه المنصة:<br>«هل نحن ملتزمون ومحميون… وكيف نُثبت ذلك؟»</blockquote></div>
    <div class="outs">{cards(s.three, "out")}</div>
  </div>
</section>

<section class="sec cream" id="cbl">
  <div class="wrap">
    {head("المحور الأول", "الامتثال لمصرف ليبيا المركزي", "تغطي المنصة المجالات الرئيسية التي يتابعها مصرف ليبيا المركزي، وتحوّل كل متطلب إلى <b>عمل يومي موثق</b> بدل ملف يُجمع قبل التفتيش.")}
    <div class="cbl"><div class="row th"><div class="k">مجال المتطلبات الرقابية</div><p>ما تقدمه المنصة</p></div>{cbl}</div>
    <div class="callout">{icon("check")}<p><b>عند زيارة التفتيش:</b> التقارير والقرارات والأدلة جاهزة بضغطة زر، مع سجل يوضح من فعل ماذا ومتى.</p></div>
    <p class="note">تُطابَق المنصة مع تعليمات مصرف ليبيا المركزي النافذة وسياسات المصرف الداخلية بالتعاون مع إدارة الامتثال في المصرف خلال مرحلة الإعداد.</p>
  </div>
</section>

<section class="sec ivory" id="people">
  <div class="wrap">
    {head("المحور الثاني", "تطوير موارد المصرف", "أثمن موارد المصرف هم موظفوه ووقتهم ومعرفتهم. المنصة تجعل عملهم أبسط وأوضح، وأكثر متعة.")}
    <div class="grid3">{cards(s.res, "card")}</div>
    <div class="shots">
      {veiled("help","wide")}
      <figure class="phone veil"><div class="vw"><img src="img/mobile.jpg" alt="شاشة مهامي على الجوال"><span>عرض توضيحي</span></div><figcaption>«مهامي» على الجوال</figcaption></figure>
    </div>
  </div>
</section>

<section class="sec cream" id="protect">
  <div class="wrap">
    {head("المحور الثالث", "حماية المصرف", "الحماية تبدأ قبل الحادثة: أن ترى الخطر مبكراً، وتعرف من يعالجه، وتتأكد أن الضابط يعمل فعلاً.")}
    <div class="grid3">{cards(s.prot, "card navytop")}</div>
    <div class="shield"><div class="sros" aria-hidden="true">{rosette(180, opacity=.65)}</div>
      <div><h3>حماية الأموال والسمعة والترخيص</h3><p>كل خطر له مالك، وكل إجراء له موعد، وكل قرار له دليل. هكذا يُحمى المصرف من الخسارة المفاجئة، ومن الملاحظات الرقابية المتكررة.</p></div></div>
  </div>
</section>

<section class="sec ivory">
  <div class="wrap">
    {head("الحل", "المنصة باختصار", "ست ركائز على قاعدة بيانات واحدة، فما يُسجَّل في إدارة يظهر أثره فوراً في لوحة الإدارة العليا.")}
    <div class="glance"><div class="wheel-box">{wheel}</div><ol class="plist">{plist}</ol></div>
    <div class="stats">{stats}</div>
  </div>
</section>

<section class="sec cream" id="value">
  <div class="wrap">
    {head("القرار", "القيمة… والطريق إليها")}
    <div class="ba"><div class="ba-row th"><div class="b">اليوم</div><div class="ar"></div><div class="a">مع المنصة</div></div>{ba}</div>
    <h3 class="sub">خطة التطبيق</h3>
    <ol class="steps">{steps}</ol>
    <div class="prereq"><h3>{icon("check")} قبل التشغيل الفعلي</h3><ul>
      <li>استضافة إنتاجية يتحكم بها المصرف، مع نسخ احتياطي وخطة تعافٍ من الكوارث.</li>
      <li>اختبار اختراق مستقل وفق سياسة أمن المعلومات، ومطابقة المصطلحات مع تعليمات مصرف ليبيا المركزي.</li></ul></div>
  </div>
</section>

<section class="cta-sec">
  <div class="ros r3" aria-hidden="true">{rosette(600, opacity=.2, stroke=1)}</div>
  <div class="wrap cta-in">
    <div class="bros" aria-hidden="true">{rosette(140, opacity=1, stroke=1.4)}</div>
    <h2>امتثال · موارد · حماية<br><span>لنبدأ بعرض عملي</span></h2>
    <p>في جلسة واحدة نعرض كيف تصبح متطلبات الحوكمة والمخاطر والامتثال لديكم لوحة قرار واضحة.</p>
    <div class="contact">
      <div>{icon("users")}<span>جهة التواصل</span><b>…………………………</b></div>
      <div>{icon("phone")}<span>الهاتف</span><b>…………………………</b></div>
      <div>{icon("mail")}<span>البريد الإلكتروني</span><b>…………………………</b></div>
    </div>
    <p class="fine">وثيقة سرية للعرض على إدارة المصرف. الصور توضيحية من بيئة عرض ببيانات تجريبية، وتفاصيل التصميم تُعرض في جلسة العرض. 2026</p>
  </div>
</section>
</main>
'''

CSS = r'''
/* Layout: full-bleed alternating ivory/cream bands between a navy hero and navy close; content column max 1080px. Single deliberate gold-on-navy/ivory look. */
:root{
  --gold:#C9A227;--gold-hi:#E9C866;--gold-dk:#8F7119;--navy:#0B2545;--navy-2:#13315C;
  --ivory:#FBF7EE;--cream:#F4ECD8;--card:#FFFFFF;--ink:#1D2433;--muted:#566074;--on-navy:#DFE6F2;--line:#E5D6A8;--teal:#1F6F6B;
  --f-display:'Noto Kufi Arabic','Noto Sans Arabic',Tahoma,sans-serif;
  --f-body:'Noto Sans Arabic','Segoe UI',Tahoma,sans-serif;
  --f-quote:'Noto Naskh Arabic','Noto Sans Arabic',serif;
  color-scheme:light;
}
html{scroll-behavior:smooth}
@media (prefers-reduced-motion:reduce){html{scroll-behavior:auto}}
body{background:var(--ivory);color:var(--ink);font-family:var(--f-body);font-size:16px;line-height:1.9}
main{overflow-x:hidden}
.wrap{max-width:1080px;margin:0 auto;padding-inline:20px}
h1,h2,h3,.kicker,.stat b,.tag{font-family:var(--f-display);text-wrap:balance}
.ic{width:24px;height:24px}
.badge{display:inline-grid;place-items:center;width:52px;height:52px;flex:none;color:var(--navy);background:linear-gradient(135deg,#F1D78A,var(--gold) 55%,var(--gold-dk));clip-path:polygon(30% 0,70% 0,100% 30%,100% 70%,70% 100%,30% 100%,0 70%,0 30%)}
.badge .ic{width:26px;height:26px}
.rs{width:18px;height:18px;color:var(--gold);fill:var(--gold)}
/* hero */
.hero{position:relative;background:radial-gradient(120% 90% at 85% 0%,#1d4477 0%,var(--navy) 55%,#071a33 100%);color:#fff;overflow:hidden}
.ros{position:absolute;pointer-events:none}
.ros svg{max-width:none}
.r1{top:-160px;left:-180px}
.r3{top:50%;left:50%;transform:translate(-50%,-50%)}
.hero-in{position:relative;display:grid;grid-template-columns:1.05fr 1fr;gap:40px;align-items:center;padding-block:64px 90px}
.hero-in>*{min-width:0}
.tag{display:inline-block;border:1px solid var(--gold);color:var(--gold-hi);padding:4px 16px;border-radius:999px;font-size:14px}
.over{color:var(--gold-hi);font-family:var(--f-display);margin-top:18px}
.hero h1{font-size:clamp(32px,5vw,52px);line-height:1.45;margin-top:6px}
.hero h1 span{display:block;color:var(--gold-hi)}
.lead{color:var(--on-navy);font-size:18px;margin-top:16px;max-width:34em}
.pills{display:flex;flex-wrap:wrap;gap:8px;margin-top:22px}
.pills a{font-size:14px;color:#F4E2AE;text-decoration:none;border:1px solid rgba(233,200,102,.55);background:rgba(201,162,39,.08);padding:4px 14px;border-radius:999px;transition:background .2s}
.pills a:hover,.pills a:focus-visible{background:rgba(201,162,39,.25);outline:none}
.band{position:relative;background:linear-gradient(90deg,var(--gold-dk),var(--gold) 40%,#F1D78A 70%,var(--gold));color:var(--navy);font-family:var(--f-display);font-size:19px;padding-block:14px}
/* shots */
.shot{margin:0;background:var(--card);border-radius:12px;overflow:hidden;box-shadow:0 10px 28px rgba(11,37,69,.22);border:1px solid var(--line)}
.shot .bar{height:18px;background:var(--navy);display:flex;gap:5px;align-items:center;padding-inline:10px;direction:ltr}
.shot .bar i{width:7px;height:7px;border-radius:50%;background:var(--gold-hi);opacity:.8}
.vw{position:relative;overflow:hidden}
.vw img{display:block;width:100%;filter:blur(1.6px) saturate(.9)}
.vw span{position:absolute;inset:0;display:grid;place-items:center;font-family:var(--f-display);font-size:clamp(16px,3vw,26px);color:rgba(11,37,69,.55);transform:rotate(-14deg);letter-spacing:2px;text-shadow:0 0 8px #fff}
.tilt{transform:perspective(1000px) rotateY(-10deg) rotateX(4deg);border:2px solid var(--gold);box-shadow:0 26px 50px rgba(0,0,0,.45)}
/* sections */
.sec{padding-block:72px}
.ivory{background:var(--ivory)}
.cream{background:var(--cream)}
.sh{margin-bottom:32px}
.kicker{display:inline-block;color:var(--gold-dk);font-size:14px;font-weight:700;border:1px solid var(--gold);padding:2px 14px;border-radius:999px;background:#FFF8E6}
.sh h2{font-size:clamp(26px,3.6vw,36px);color:var(--navy);margin-top:10px;line-height:1.5}
.rule{display:flex;align-items:center;gap:10px;width:240px;max-width:100%;margin-top:4px}
.rule span{flex:1;height:2px;background:linear-gradient(90deg,transparent,var(--gold))}
.rule span:last-child{background:linear-gradient(90deg,var(--gold),transparent)}
.intro{font-size:18px;margin-top:16px;max-width:46em}
.intro b{color:var(--navy)}
.quote{position:relative;background:var(--navy);border-radius:16px;padding:32px 40px 32px 190px;overflow:hidden;margin-bottom:28px}
.qros{position:absolute;left:28px;top:50%;transform:translateY(-50%)}
.quote blockquote{margin:0;font-family:var(--f-quote);font-size:clamp(19px,2.5vw,24px);line-height:1.8;color:var(--gold-hi)}
.outs{display:grid;grid-template-columns:repeat(3,1fr);gap:20px}
.out{background:var(--card);border-radius:14px;padding:26px 20px;text-align:center;border-top:3px solid var(--gold);box-shadow:0 4px 14px rgba(11,37,69,.08);min-width:0}
.out h3{color:var(--navy);font-size:19px;margin:12px 0 6px;line-height:1.6}
.out p,.card p{color:var(--muted);font-size:15px;margin:0}
.grid3{display:grid;grid-template-columns:repeat(3,1fr);gap:18px}
.card{display:flex;gap:14px;background:var(--card);border-radius:14px;padding:20px;border-top:3px solid var(--gold);box-shadow:0 3px 12px rgba(11,37,69,.07);min-width:0}
.card.navytop{border-top-color:var(--navy)}
.card h3{color:var(--navy);font-size:17px;line-height:1.6;margin:0 0 4px}
.card>div{min-width:0}
/* cbl */
.cbl{display:grid;gap:10px}
.cbl .row{display:grid;grid-template-columns:minmax(0,2fr) minmax(0,3fr);background:var(--card);border-radius:12px;box-shadow:0 2px 8px rgba(11,37,69,.07);overflow:hidden}
.cbl .row .k{display:flex;align-items:center;gap:10px;padding:14px 18px;border-right:4px solid var(--gold);background:#FFFCF3}
.cbl .row .k .ic{color:var(--gold-dk);flex:none}
.cbl .row .k b{font-family:var(--f-display);color:var(--navy);font-size:16px}
.cbl .row p{margin:0;padding:14px 18px;font-size:15.5px;align-self:center}
.cbl .th{background:none;box-shadow:none}
.cbl .th .k{background:var(--navy);color:var(--gold-hi);border-right-color:var(--gold);font-family:var(--f-display);border-radius:0 12px 12px 0}
.cbl .th p{background:var(--gold);color:var(--navy);font-family:var(--f-display);border-radius:12px 0 0 12px}
.callout{display:flex;gap:14px;align-items:center;background:#FFF8E6;border-right:4px solid var(--gold);padding:16px 20px;border-radius:10px;margin-top:24px}
.callout .ic{width:32px;height:32px;color:var(--gold-dk);flex:none}
.callout p{margin:0}
.note{font-size:14px;color:var(--muted);border-top:1px dashed #D9C78F;padding-top:12px;margin-top:20px}
/* people shots */
.shots{display:flex;gap:28px;align-items:flex-start;margin-top:36px}
.shots .wide{flex:1;min-width:0}
.phone{margin:0;width:190px;flex:none;border:9px solid var(--navy);border-radius:28px;overflow:hidden;box-shadow:0 12px 30px rgba(11,37,69,.3);outline:1px solid var(--gold);background:var(--card)}
.phone figcaption{text-align:center;background:var(--navy);color:var(--gold-hi);font-size:14px;padding:6px}
/* shield */
.shield{display:flex;gap:28px;align-items:center;margin-top:32px;background:linear-gradient(135deg,var(--navy),var(--navy-2));color:#fff;border-radius:18px;padding:28px 34px;border:1px solid var(--gold)}
.shield .sros{flex:none}
.shield h3{color:var(--gold-hi);font-size:24px;margin:0 0 6px}
.shield p{color:var(--on-navy);margin:0;font-size:17px}
/* glance */
.glance{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1fr);gap:36px;align-items:center}
.wheel{width:100%;max-width:420px;height:auto;display:block;margin:0 auto}
.wt1{font-family:var(--f-display);fill:#E9C866;font-size:17px;text-anchor:middle}
.wt2{font-family:var(--f-display);fill:#FFFFFF;font-size:26px;font-weight:700;text-anchor:middle}
.wn{font-family:var(--f-display);fill:#0B2545;font-size:18px;font-weight:700;text-anchor:middle}
.plist{list-style:none;margin:0;padding:0;display:grid;gap:12px}
.plist li{display:flex;align-items:center;gap:14px;background:var(--card);border-radius:12px;padding:10px 14px;box-shadow:0 2px 8px rgba(11,37,69,.07)}
.plist .pnum{font-family:var(--f-display);color:var(--gold-dk);font-weight:700;width:18px;text-align:center}
.plist .badge{width:42px;height:42px}.plist .badge .ic{width:21px;height:21px}
.plist b{display:block;font-family:var(--f-display);color:var(--navy)}
.plist small{color:var(--muted);font-size:14px}
.stats{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-top:36px}
.stat{background:var(--navy);border-radius:14px;padding:20px 10px;text-align:center;border-bottom:3px solid var(--gold)}
.stat b{display:block;font-size:30px;color:var(--gold-hi);line-height:1.4;font-variant-numeric:tabular-nums}
.stat span{color:var(--on-navy);font-size:14px}
/* value */
.ba{display:grid;gap:10px}
.ba-row{display:grid;grid-template-columns:minmax(0,1fr) 44px minmax(0,1fr);align-items:stretch}
.ba-row>div{padding:14px 18px;border-radius:10px}
.ba .b{background:#E9EBEF;color:var(--muted)}
.ba .a{background:var(--card);color:var(--navy);font-weight:700;border-right:4px solid var(--gold);box-shadow:0 2px 8px rgba(11,37,69,.08)}
.ba .ar{display:grid;place-items:center;color:var(--gold);font-size:22px;padding:0}
.ba .th .b{background:#7A8496;color:#fff;font-family:var(--f-display)}
.ba .th .a{background:var(--navy);color:var(--gold-hi);font-family:var(--f-display);border-right-color:var(--gold)}
.ba .th .ar{background:none}
.sub{color:var(--navy);font-size:24px;margin:48px 0 24px}
.steps{list-style:none;margin:0;padding:0;display:grid;grid-template-columns:repeat(4,1fr);gap:18px;position:relative}
.steps::before{content:'';position:absolute;top:36px;right:12%;left:12%;height:2px;background:repeating-linear-gradient(90deg,var(--gold) 0 6px,transparent 6px 11px)}
.step{text-align:center;position:relative;min-width:0}
.sn{width:72px;height:72px;margin:0 auto 10px;display:grid;place-items:center;background:linear-gradient(135deg,#F1D78A,var(--gold),var(--gold-dk));clip-path:polygon(30% 0,70% 0,100% 30%,100% 70%,70% 100%,30% 100%,0 70%,0 30%)}
.sn span{width:54px;height:54px;display:grid;place-items:center;background:var(--navy);color:var(--gold-hi);font-family:var(--f-display);font-size:22px;clip-path:inherit}
.step h3{color:var(--navy);font-size:17px;margin:0}
.step p{color:var(--muted);font-size:14.5px;margin:4px 0 0}
.prereq{margin-top:36px;background:var(--card);border:1px solid var(--line);border-right:4px solid var(--teal);border-radius:12px;padding:18px 24px}
.prereq h3{display:flex;gap:8px;align-items:center;color:var(--teal);font-size:18px;margin:0 0 6px}
.prereq ul{margin:0;padding-right:20px}
/* cta */
.cta-sec{position:relative;overflow:hidden;background:radial-gradient(120% 90% at 50% 0%,#1d4477 0%,var(--navy) 60%,#071a33 100%);color:#fff;text-align:center;padding-block:72px}
.cta-in{position:relative}
.bros{display:flex;justify-content:center;margin-bottom:20px}
.cta-sec h2{font-size:clamp(28px,4.4vw,42px);line-height:1.5;margin:0}
.cta-sec h2 span{color:var(--gold-hi)}
.cta-in>p{color:var(--on-navy);font-size:18px;max-width:36em;margin:16px auto 30px}
.contact{display:grid;gap:12px;max-width:520px;margin:0 auto}
.contact div{display:flex;align-items:center;gap:14px;background:rgba(255,255,255,.06);border:1px solid rgba(233,200,102,.45);border-radius:12px;padding:12px 20px;text-align:right}
.contact .ic{color:var(--gold-hi);flex:none}
.contact span{color:var(--gold-hi);font-family:var(--f-display);min-width:8em}
.contact b{color:#C7D0DE;font-weight:400;overflow-wrap:anywhere}
.fine{color:#9FB0C8;font-size:13px;margin-top:40px}
/* responsive */
@media (max-width:900px){
  .hero-in{grid-template-columns:1fr;padding-block:48px 60px}
  .tilt{transform:none}
  .outs,.grid3{grid-template-columns:1fr 1fr}
  .glance{grid-template-columns:1fr}
  .stats,.steps{grid-template-columns:1fr 1fr}
  .steps::before{display:none}
}
@media (max-width:600px){
  .wrap{padding-inline:16px}
  .sec{padding-block:52px}
  .outs,.grid3{grid-template-columns:1fr}
  .quote{padding:26px 22px}
  .qros{display:none}
  .cbl .row{grid-template-columns:1fr}
  .cbl .row .k{border-radius:0}
  .cbl .th{display:none}
  .shots{flex-direction:column;align-items:center}
  .shots .wide{width:100%}
  .shield{flex-direction:column;text-align:center;padding:24px 20px}
  .shield .sros svg{width:120px;height:120px}
  .ba-row{grid-template-columns:1fr}
  .ba .ar{transform:rotate(-90deg);height:28px}
  .ba .th{display:none}
  .stat b{font-size:24px}
  .contact div{flex-wrap:wrap}
}
'''

PAGE = f'''<title>منصة الحوكمة والمخاطر</title>
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Noto+Kufi+Arabic:wght@400;700&family=Noto+Naskh+Arabic:wght@400;700&family=Noto+Sans+Arabic:wght@400;700&display=swap">
<style>{CSS}</style>
{BODY}'''


def main():
    out = os.path.join(HERE, 'web', 'brief.html')
    os.makedirs(os.path.dirname(out), exist_ok=True)
    with open(out, 'w', encoding='utf-8') as f:
        f.write(PAGE)
    print('wrote', out)


if __name__ == '__main__':
    main()
