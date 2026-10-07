#!/usr/bin/env python3
"""Short (8-page) executive brochure, built on the helpers and styles of build.py.

  python3 docs/brochure/build_short.py   -> brochure-short.html + GRC-Platform-Brief-AR.pdf

Screenshots are deliberately veiled (blurred + watermark): the brief conveys the idea
without exposing screen designs in detail.
"""
import math
import os
import sys

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from build import CHROME, CSS, HERE, badge, icon, rosette, star  # noqa: E402

TOTAL = 8


def page(n, body, cls='', title=None, kicker=None):
    head = ''
    if title:
        head = (f'<header class="ph"><div class="kicker">{kicker or ""}</div>'
                f'<h2>{title}</h2><div class="rule"><span></span>{icon("spark","rs")}<span></span></div></header>')
    foot = (f'<footer class="pf"><span>منصة الحوكمة وإدارة المخاطر والامتثال · سري — للعرض على إدارة المصرف</span>'
            f'<span class="pn">{n:02d}</span></footer>') if 1 < n < TOTAL else ''
    return f'<section class="page {cls}">{head}{body}{foot}</section>'


def veiled(src, cls='shot', cap=None):
    c = f'<figcaption>{cap}</figcaption>' if cap else ''
    return (f'<figure class="{cls} veil"><div class="bar"><i></i><i></i><i></i></div>'
            f'<div class="vw"><img src="img/{src}.png" alt=""><span>عرض توضيحي</span></div>{c}</figure>')


pages = []

# 1. Cover
pages.append(page(1, f'''
<div class="cover-pattern"></div>
<div class="cover-ros r1">{rosette(520, opacity=.28, stroke=1)}</div>
<div class="cover-ros r2">{rosette(260, opacity=.35)}</div>
<div class="cover-top"><div class="tag">ملخص تنفيذي لإدارة المصرف</div></div>
<div class="cover-title">
  <div class="over">منصة الحوكمة وإدارة المخاطر والامتثال</div>
  <h1>امتثالٌ أوضح<br>وموارد أقوى<br><span>ومصرفٌ أكثر أماناً</span></h1>
  <p class="lead">منصة عربية واحدة تساعد المصرف على الوفاء بمتطلبات<br>مصرف ليبيا المركزي، وتنمية كفاءة موظفيه، وحماية أمواله وسمعته.</p>
  <div class="pills"><span>الامتثال الرقابي</span><span>تطوير الموارد</span><span>حماية المصرف</span><span>الامتثال الشرعي</span></div>
</div>
<div class="cover-shot">{veiled("dashboard","shot tilt")}</div>
<div class="cover-band"><div>ثلاث نتائج… <b>في منصة واحدة</b></div></div>
''', cls='cover'))

# 2. Why + three outcomes
three = [
    ('scale', 'الامتثال لمصرف ليبيا المركزي', 'دليل موثق وجاهز على أن الحوكمة وإدارة المخاطر والرقابة الشرعية تعمل فعلاً، لا على الورق فقط.'),
    ('users', 'تطوير موارد المصرف', 'موظفون يعملون بوضوح وسرعة، ومعرفة تبقى في المصرف ولا تغادر مع الأشخاص.'),
    ('shield', 'حماية المصرف', 'إنذار مبكر قبل وقوع الخسارة، وضوابط مُختبَرة، وقرارات حساسة لا تمر دون مراجعة.'),
]
tc = ''.join(f'<div class="out">{badge(i)}<h3>{t}</h3><p>{d}</p></div>' for i, t, d in three)
pages.append(page(2, f'''
<p class="intro">تتزايد توقعات مصرف ليبيا المركزي من المصارف في الحوكمة وإدارة المخاطر والامتثال والرقابة الشرعية،
بينما تُدار هذه الملفات في أغلب المصارف <b>بجداول متفرقة ومتابعة يدوية</b>. والنتيجة: جهد كبير، وصورة غير مكتملة أمام الإدارة والرقيب.</p>
<div class="quote">
  <div class="qros">{rosette(150, opacity=.5)}</div>
  <blockquote>السؤال الذي تجيب عنه المنصة:<br>«هل نحن ملتزمون ومحميون… وكيف نُثبت ذلك؟»</blockquote>
</div>
<h3 class="sub">ثلاث نتائج مباشرة للمصرف</h3>
<div class="outs">{tc}</div>
''', title='لماذا الآن؟', kicker='الفكرة'))

# 3. CBL compliance
cbl = [
    ('users', 'حوكمة مجلس الإدارة واللجان', 'الاجتماعات والحضور والقرارات، ومتابعة تنفيذها حتى الإغلاق.'),
    ('compass', 'إطار إدارة المخاطر وحدود التحمل', 'سجل مخاطر موحد، حدود يعتمدها المجلس، ومؤشرات إنذار مبكر.'),
    ('file', 'وظيفة الامتثال والتعاميم', 'كل تعميم يصل إلى الإدارة المعنية ويُوثَّق إقرارها ومواعيد التنفيذ.'),
    ('moon', 'الرقابة الشرعية', 'القرارات الشرعية والمراجعات وأحداث عدم الالتزام والتطهير والتقرير السنوي.'),
    ('layers', 'استمرارية الأعمال', 'العمليات الحرجة وأهداف الاستعادة وفجوات التعافي.'),
    ('eye', 'الرقابة الداخلية والتدقيق', 'ضوابط مُختبَرة، وسجل كامل لكل تعديل، وموافقات مزدوجة.'),
]
rows = ''.join(f'<tr><td class="k">{icon(i)}<b>{t}</b></td><td>{d}</td></tr>' for i, t, d in cbl)
pages.append(page(3, f'''
<p class="intro">تغطي المنصة المجالات الرئيسية التي يتابعها مصرف ليبيا المركزي، وتحوّل كل متطلب إلى <b>عمل يومي موثق</b> بدل ملف يُجمع قبل التفتيش.</p>
<table class="cbl"><thead><tr><th>مجال المتطلبات الرقابية</th><th>ما تقدمه المنصة</th></tr></thead><tbody>{rows}</tbody></table>
<div class="callout">{icon("check")}<p><b>عند زيارة التفتيش:</b> التقارير والقرارات والأدلة جاهزة بضغطة زر، مع سجل يوضح من فعل ماذا ومتى.</p></div>
<p class="note">تُطابَق المنصة مع تعليمات مصرف ليبيا المركزي النافذة وسياسات المصرف الداخلية بالتعاون مع إدارة الامتثال في المصرف خلال مرحلة الإعداد.</p>
''', title='الامتثال لمصرف ليبيا المركزي', kicker='المحور الأول'))

# 4. Resources
res = [
    ('clock', 'وقت أقل في التجميع', 'البيانات تُسجَّل مرة واحدة وتُستخدم في كل التقارير، فيتفرغ الموظف للتحليل.'),
    ('help', 'تعلّم أثناء العمل', 'دليل مبسط داخل كل شاشة يناسب دور المستخدم، يختصر التدريب ويقلل الأخطاء.'),
    ('bell', 'لا شيء يُنسى', 'مهامي، وملاحظات وتذكيرات شخصية، وتنبيهات بالبريد للمتأخرات.'),
    ('book', 'معرفة مؤسسية محفوظة', 'القرارات والأسباب والأدلة تبقى في المصرف ولا تغادر مع الأشخاص.'),
    ('branch', 'كل إدارة تملك مخاطرها', 'ثقافة مخاطر مشتركة بين الإدارات والفروع، بصلاحيات واضحة.'),
    ('target', 'الإدارة تركز على القرار', 'لوحة واحدة تربط الأهداف الاستراتيجية بالمخاطر والأداء.'),
]
rg = ''.join(f'<div class="sec">{badge(i)}<div><h4>{t}</h4><p>{d}</p></div></div>' for i, t, d in res)
pages.append(page(4, f'''
<p class="intro">أثمن موارد المصرف هم موظفوه ووقتهم ومعرفتهم. المنصة تجعل عملهم أبسط وأوضح، وأكثر متعة.</p>
<div class="secs">{rg}</div>
<div class="ease-shots">
  <div class="col2">{veiled("help", cap="دليل مبسط حسب دور المستخدم")}</div>
  <figure class="phone veil"><div class="vw"><img src="img/mobile.png" alt=""><span>عرض توضيحي</span></div><figcaption>«مهامي» على الجوال</figcaption></figure>
</div>
''', title='تطوير موارد المصرف', kicker='المحور الثاني'))

# 5. Protection
prot = [
    ('pulse', 'إنذار مبكر', 'مؤشرات تنبّه عند الاقتراب من الحدود قبل أن يتحول الخطر إلى خسارة.'),
    ('alert', 'الحد من الخسائر', 'تسجيل الحوادث وتصعيدها تلقائياً وكشف تكرارها لمعالجة أسبابها.'),
    ('shield', 'ضوابط مُختبَرة', 'تكشف المنصة المخاطر التي تعتمد على ضوابط لم تثبت فعاليتها.'),
    ('moon', 'حماية السمعة الشرعية', 'متابعة الالتزام الشرعي والتطهير بشفافية أمام الهيئة والمساهمين.'),
    ('pen', 'لا قرار حساس بلا مراجعة', 'قبول المخاطر وإغلاق الحوادث واعتماد السياسات بموافقة شخص ثانٍ.'),
    ('lock', 'أمن المعلومات', 'تحقق بخطوتين، وتشفير، وصلاحيات حسب الإدارة والفرع، وبيانات تحت سيطرة المصرف.'),
]
pg = ''.join(f'<div class="sec">{badge(i)}<div><h4>{t}</h4><p>{d}</p></div></div>' for i, t, d in prot)
pages.append(page(5, f'''
<p class="intro">الحماية تبدأ قبل الحادثة: أن ترى الخطر مبكراً، وتعرف من يعالجه، وتتأكد أن الضابط يعمل فعلاً.</p>
<div class="secs">{pg}</div>
<div class="shieldbox">
  <div class="sros">{rosette(220, opacity=.6)}</div>
  <div><h3>حماية الأموال والسمعة والترخيص</h3><p>كل خطر له مالك، وكل إجراء له موعد، وكل قرار له دليل. هكذا يُحمى المصرف من الخسارة المفاجئة، ومن الملاحظات الرقابية المتكررة.</p></div>
</div>
''', title='حماية المصرف', kicker='المحور الثالث'))

# 6. At a glance
pillars = [
    ('alert', 'إدارة المخاطر', 'تقييم ومعالجة ومؤشرات'),
    ('scale', 'الامتثال', 'التزامات وتعاميم'),
    ('moon', 'الامتثال الشرعي', 'متابعة وتطهير وتقارير'),
    ('shield', 'الضوابط الرقابية', 'مكتبة واختبار'),
    ('layers', 'استمرارية الأعمال', 'تحليل الأثر'),
    ('users', 'الحوكمة والتقارير', 'مجالس، لجان، أهداف'),
]
W = 560
c = W / 2
wheel = f'<svg viewBox="0 0 {W} {W}" class="wheel">'
wheel += '<defs><radialGradient id="cg" cx="50%" cy="40%" r="60%"><stop offset="0" stop-color="#1d4477"/><stop offset="1" stop-color="#0B2545"/></radialGradient>'
wheel += '<linearGradient id="gg" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#F1D78A"/><stop offset=".5" stop-color="#C9A227"/><stop offset="1" stop-color="#9A7B1E"/></linearGradient></defs>'
wheel += f'<circle cx="{c}" cy="{c}" r="{c-6}" fill="none" stroke="#C9A227" stroke-width="1" stroke-dasharray="3 7"/>'
wheel += f'<polygon points="{star(c, c, 205, 150, 12)}" fill="none" stroke="#E6C66A" stroke-width="1"/>'
for i in range(6):
    a = math.radians(-90 + i * 60)
    wheel += f'<line x1="{c}" y1="{c}" x2="{c + 190*math.cos(a):.1f}" y2="{c + 190*math.sin(a):.1f}" stroke="#C9A227" stroke-width="1.2" stroke-opacity=".6"/>'
wheel += f'<polygon points="{star(c, c, 112, 92, 8)}" fill="url(#gg)"/><circle cx="{c}" cy="{c}" r="86" fill="url(#cg)"/>'
wheel += f'<text x="{c}" y="{c-8}" class="wt1">منصة</text><text x="{c}" y="{c+26}" class="wt2">واحدة</text></svg>'
nodes = ''
for i, (ic, t, d) in enumerate(pillars):
    a = math.radians(-90 + i * 60)
    nodes += (f'<div class="node" style="left:{50 + 34*math.cos(a):.2f}%;top:{50 + 34*math.sin(a):.2f}%">'
              f'{badge(ic)}<b>{t}</b><small>{d}</small></div>')
stats = [('+18', 'وحدة عمل متكاملة'), ('عربي', 'وإنجليزي بضغطة واحدة'), ('1,234', 'فحصاً آلياً للجودة'), ('متصفح', 'لا برامج على أجهزة الموظفين')]
st = ''.join(f'<div class="stat"><b>{a}</b><span>{b}</span></div>' for a, b in stats)
pages.append(page(6, f'''
<p class="intro center">ست ركائز على قاعدة بيانات واحدة، فما يُسجَّل في إدارة يظهر أثره فوراً في لوحة الإدارة العليا.</p>
<div class="wheel-wrap">{wheel}{nodes}</div>
<div class="stats">{st}</div>
''', title='المنصة باختصار', kicker='الحل'))

# 7. Value + roadmap
ba = [
    ('جداول متفرقة ونسخ متعارضة', 'نسخة واحدة موثوقة من الحقيقة'),
    ('أيام لإعداد تقرير المجلس', 'تقارير جاهزة وتصدير فوري'),
    ('صعوبة إثبات الالتزام أمام الرقيب', 'دليل موثق لكل خطوة'),
    ('معرفة مرتبطة بأشخاص بعينهم', 'معرفة مؤسسية محفوظة'),
]
tr = ''.join(f'<tr><td class="b">{a}</td><td class="ar">←</td><td class="a">{b}</td></tr>' for a, b in ba)
steps = [
    ('1', 'عرض وتجربة', 'عرض حي ثم تجربة محدودة مع إدارة المخاطر.'),
    ('2', 'إعداد ونقل البيانات', 'ضبط الإدارات والصلاحيات واستيراد السجلات الحالية.'),
    ('3', 'تدريب قصير', 'حسب الدور، يدعمه الدليل داخل المنصة.'),
    ('4', 'تشغيل وتوسع', 'تفعيل الوحدات تدريجياً حسب أولويات المصرف.'),
]
sp = ''.join(f'<div class="step"><div class="sn"><span>{n}</span></div><h4>{t}</h4><p>{d}</p></div>' for n, t, d in steps)
pages.append(page(7, f'''
<table class="ba"><thead><tr><th>اليوم</th><th></th><th>مع المنصة</th></tr></thead><tbody>{tr}</tbody></table>
<h3 class="sub">خطة التطبيق</h3>
<div class="steps">{sp}</div>
<div class="prereq">
  <h3>{icon("check")} قبل التشغيل الفعلي</h3>
  <ul>
    <li>استضافة إنتاجية يتحكم بها المصرف، مع نسخ احتياطي وخطة تعافٍ من الكوارث.</li>
    <li>اختبار اختراق مستقل وفق سياسة أمن المعلومات، ومطابقة المصطلحات مع تعليمات مصرف ليبيا المركزي.</li>
  </ul>
</div>
''', title='القيمة… والطريق إليها', kicker='القرار'))

# 8. Back cover
pages.append(page(8, f'''
<div class="cover-pattern"></div>
<div class="cover-ros r3">{rosette(600, opacity=.22, stroke=1)}</div>
<div class="back">
  <div class="bros">{rosette(170, opacity=1, stroke=1.4)}</div>
  <h2>امتثال · موارد · حماية<br><span>لنبدأ بعرض عملي</span></h2>
  <p>في جلسة واحدة نعرض كيف تصبح متطلبات الحوكمة والمخاطر والامتثال لديكم لوحة قرار واضحة.</p>
  <div class="contact">
    <div>{icon("users")}<span>جهة التواصل</span><b>………………………………</b></div>
    <div>{icon("phone")}<span>الهاتف</span><b>………………………………</b></div>
    <div>{icon("mail")}<span>البريد الإلكتروني</span><b>………………………………</b></div>
  </div>
  <div class="cta">احجز عرضاً تجريبياً</div>
</div>
<div class="fine">وثيقة سرية للعرض على إدارة المصرف. الصور توضيحية من بيئة عرض ببيانات تجريبية، وتفاصيل التصميم تُعرض في الجلسة. 2026</div>
''', cls='cover backc'))

EXTRA = r'''
.veil .vw{position:relative;overflow:hidden}
.veil .vw img{display:block;width:100%;filter:blur(1.6px) saturate(.9)}
.veil .vw span{position:absolute;inset:0;display:grid;place-items:center;font-family:'Noto Kufi Arabic';font-size:15pt;color:rgba(11,37,69,.55);transform:rotate(-14deg);letter-spacing:2px;text-shadow:0 0 6px #fff}
.cover .veil .vw span{font-size:22pt}
.outs{display:grid;grid-template-columns:repeat(3,1fr);gap:4mm}
.out{background:#fff;border-radius:3mm;padding:6mm 4.5mm;text-align:center;border-top:3px solid var(--gold);box-shadow:0 1mm 4mm rgba(11,37,69,.08)}
.out h3{color:var(--navy);font-size:12.5pt;margin:3mm 0 2mm;line-height:1.6}
.out p{font-size:10pt;line-height:1.9;color:var(--muted)}
.quote{margin:2mm 0 0}
table.cbl{width:100%;border-collapse:separate;border-spacing:0 2.4mm}
table.cbl th{font-family:'Noto Kufi Arabic';font-size:11.5pt;padding:3mm 4mm;text-align:right}
table.cbl th:first-child{background:var(--navy);color:var(--gold2);border-radius:0 2mm 2mm 0}
table.cbl th:last-child{background:var(--gold);color:var(--navy);border-radius:2mm 0 0 2mm}
table.cbl td{background:#fff;padding:3.6mm 4mm;font-size:10.3pt;line-height:1.7;color:var(--ink);box-shadow:0 .5mm 2mm rgba(11,37,69,.07)}
table.cbl td.k{width:40%;border-right:3px solid var(--gold);border-radius:0 2mm 2mm 0}
table.cbl td:last-child{border-radius:2mm 0 0 2mm}
table.cbl td.k .ic{width:5.5mm;height:5.5mm;color:var(--goldd);vertical-align:middle;margin-left:2mm}
table.cbl td.k b{font-family:'Noto Kufi Arabic';color:var(--navy);font-size:10.5pt}
.note{margin-top:5mm;font-size:9pt;line-height:1.8;color:var(--muted);border-top:1px dashed #d9c78f;padding-top:3mm}
.ease-shots{margin-top:7mm}
'''

HTML = f'''<!doctype html>
<html lang="ar" dir="rtl"><head><meta charset="utf-8">
<title>ملخص تنفيذي — منصة الحوكمة وإدارة المخاطر والامتثال</title>
<style>{CSS}{EXTRA}</style></head><body>
{''.join(pages)}
</body></html>'''


def main():
    out_html = os.path.join(HERE, 'brochure-short.html')
    with open(out_html, 'w', encoding='utf-8') as f:
        f.write(HTML)
    print('wrote', out_html)
    from playwright.sync_api import sync_playwright
    out_pdf = os.path.join(HERE, 'GRC-Platform-Brief-AR.pdf')
    with sync_playwright() as p:
        b = p.chromium.launch(executable_path=CHROME)
        pg_ = b.new_page()
        pg_.goto('file://' + out_html)
        pg_.wait_for_load_state('networkidle')
        pg_.evaluate('document.fonts.ready')
        pg_.pdf(path=out_pdf, format='A4', print_background=True, prefer_css_page_size=True)
        b.close()
    print('wrote', out_pdf)


if __name__ == '__main__':
    main()
