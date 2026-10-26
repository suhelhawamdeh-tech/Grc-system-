#!/usr/bin/env python3
"""Builds the Arabic marketing brochure.

  python3 docs/brochure/build.py          -> brochure.html + GRC-Platform-Brochure-AR.pdf

Screenshots in img/ come from the demo environment (synthetic data only).
Rendering needs Playwright for Python and a local Chromium.
"""
import math
import os
import sys

HERE = os.path.dirname(os.path.abspath(__file__))
CHROME = os.environ.get('CHROME', '/opt/pw-browsers/chromium-1194/chrome-linux/chrome')


# ---------------------------------------------------------------- geometry
def star(cx, cy, r_out, r_in, n, rot=-90):
    pts = []
    for i in range(2 * n):
        r = r_out if i % 2 == 0 else r_in
        a = math.radians(rot + i * 180 / n)
        pts.append(f'{cx + r * math.cos(a):.2f},{cy + r * math.sin(a):.2f}')
    return ' '.join(pts)


def rosette(size, color='#C9A227', opacity=1.0, stroke=1.2):
    """Layered Islamic-style rosette (8- and 16-point stars with rings)."""
    c = size / 2
    s = f'<svg class="rosette" viewBox="0 0 {size} {size}" width="{size}" height="{size}" style="opacity:{opacity}">'
    s += f'<g fill="none" stroke="{color}" stroke-width="{stroke}">'
    s += f'<circle cx="{c}" cy="{c}" r="{c*0.98:.1f}"/>'
    s += f'<circle cx="{c}" cy="{c}" r="{c*0.9:.1f}" stroke-dasharray="2 6"/>'
    s += f'<polygon points="{star(c, c, c*0.86, c*0.62, 16)}"/>'
    s += f'<polygon points="{star(c, c, c*0.7, c*0.4, 8)}"/>'
    s += f'<polygon points="{star(c, c, c*0.7, c*0.4, 8, rot=-67.5)}"/>'
    s += f'<circle cx="{c}" cy="{c}" r="{c*0.3:.1f}"/>'
    s += f'<polygon points="{star(c, c, c*0.28, c*0.16, 8)}" fill="{color}" fill-opacity=".25"/>'
    for i in range(16):
        a = math.radians(i * 22.5)
        s += (f'<line x1="{c + c*0.3*math.cos(a):.1f}" y1="{c + c*0.3*math.sin(a):.1f}" '
              f'x2="{c + c*0.62*math.cos(a):.1f}" y2="{c + c*0.62*math.sin(a):.1f}" stroke-opacity=".5"/>')
    s += '</g></svg>'
    return s


TILE = ("data:image/svg+xml;utf8,"
        "<svg xmlns='http://www.w3.org/2000/svg' width='72' height='72' viewBox='0 0 72 72'>"
        "<g fill='none' stroke='%23C9A227' stroke-width='.8'>"
        "<rect x='20' y='20' width='32' height='32'/>"
        "<rect x='20' y='20' width='32' height='32' transform='rotate(45 36 36)'/>"
        "<circle cx='36' cy='36' r='7'/>"
        "<path d='M0 0L13 13M72 0L59 13M0 72L13 59M72 72L59 59M36 0V9M36 72V63M0 36H9M72 36H63'/>"
        "</g></svg>")

# ---------------------------------------------------------------- icons
ICONS = {
    'shield': '<path d="M12 3l8 3v6c0 5-3.5 8-8 9-4.5-1-8-4-8-9V6z"/><path d="M8.5 12l2.5 2.5 4.5-5"/>',
    'alert': '<path d="M12 3L2 20h20z"/><path d="M12 9v5M12 17v.5"/>',
    'check': '<rect x="3" y="3" width="18" height="18" rx="3"/><path d="M7.5 12l3 3 6-6"/>',
    'chart': '<path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/>',
    'book': '<path d="M4 4h7a2 2 0 012 2v14a2 2 0 00-2-2H4zM20 4h-5a2 2 0 00-2 2v14a2 2 0 012-2h5z"/>',
    'moon': '<path d="M20 14.5A8 8 0 019.5 4a8 8 0 1010.5 10.5z"/>',
    'users': '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20c.5-4 3-6 6.5-6s6 2 6.5 6"/><circle cx="17" cy="9" r="2.5"/><path d="M17 14c2.5 0 4 1.5 4.5 4.5"/>',
    'file': '<path d="M6 2h8l5 5v15H6z"/><path d="M14 2v5h5M9 13h7M9 17h7"/>',
    'layers': '<path d="M12 3l9 5-9 5-9-5z"/><path d="M3 13l9 5 9-5"/>',
    'lock': '<rect x="4" y="10" width="16" height="11" rx="2"/><path d="M8 10V7a4 4 0 018 0v3"/>',
    'target': '<circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="5"/><circle cx="12" cy="12" r="1.5"/>',
    'pulse': '<path d="M2 12h4l3-7 4 14 3-7h6"/>',
    'list': '<path d="M9 6h12M9 12h12M9 18h12"/><circle cx="4" cy="6" r="1"/><circle cx="4" cy="12" r="1"/><circle cx="4" cy="18" r="1"/>',
    'bell': '<path d="M6 16V11a6 6 0 0112 0v5l2 2H4z"/><path d="M10 21h4"/>',
    'help': '<circle cx="12" cy="12" r="9"/><path d="M9.5 9.5a2.5 2.5 0 114 2c-1 .7-1.5 1.2-1.5 2.5M12 17v.5"/>',
    'pen': '<path d="M4 20l1-5L16 4l4 4L9 19z"/><path d="M14 6l4 4"/>',
    'phone': '<rect x="7" y="2" width="10" height="20" rx="2"/><path d="M11 18h2"/>',
    'globe': '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3c3 3 3 15 0 18M12 3c-3 3-3 15 0 18"/>',
    'mail': '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 7l9 6 9-6"/>',
    'key': '<circle cx="8" cy="15" r="4"/><path d="M11 12l9-9M17 6l3 3M15 8l2 2"/>',
    'eye': '<path d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/>',
    'branch': '<circle cx="6" cy="5" r="2"/><circle cx="6" cy="19" r="2"/><circle cx="18" cy="9" r="2"/><path d="M6 7v10M18 11c0 4-6 3-12 6"/>',
    'upload': '<path d="M12 16V4M7 9l5-5 5 5M4 20h16"/>',
    'scale': '<path d="M12 3v18M6 21h12M4 8h16M4 8l-2 6h4zM20 8l-2 6h4z"/>',
    'compass': '<circle cx="12" cy="12" r="9"/><path d="M15.5 8.5l-2 5-5 2 2-5z"/>',
    'clock': '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
    'server': '<rect x="3" y="4" width="18" height="7" rx="1.5"/><rect x="3" y="13" width="18" height="7" rx="1.5"/><path d="M7 7.5h.5M7 16.5h.5"/>',
    'spark': '<path d="M12 2l2.5 7.5L22 12l-7.5 2.5L12 22l-2.5-7.5L2 12l7.5-2.5z"/>',
    'xls': '<rect x="3" y="3" width="18" height="18" rx="2"/><path d="M8 8l8 8M16 8l-8 8"/>',
}


def icon(name, cls='ic'):
    return (f'<svg class="{cls}" viewBox="0 0 24 24" fill="none" stroke="currentColor" '
            f'stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">{ICONS[name]}</svg>')


def badge(name):
    """Icon inside a gold octagon."""
    return f'<span class="badge">{icon(name)}</span>'


def shot(src, cls='shot', cap=None):
    h = (f'<figure class="{cls}"><div class="bar"><i></i><i></i><i></i></div>'
         f'<img src="img/{src}.png" alt=""></figure>')
    if cap:
        h = h.replace('</figure>', f'<figcaption>{cap}</figcaption></figure>')
    return h


# ---------------------------------------------------------------- page chrome
TOTAL = 12


def page(n, body, cls='', title=None, kicker=None):
    head = ''
    if title:
        head = (f'<header class="ph"><div class="kicker">{kicker or ""}</div>'
                f'<h2>{title}</h2><div class="rule"><span></span>{icon("spark","rs")}<span></span></div></header>')
    foot = (f'<footer class="pf"><span>منصة الحوكمة وإدارة المخاطر والامتثال</span>'
            f'<span class="pn">{n:02d}</span></footer>') if 1 < n < TOTAL else ''
    return f'<section class="page {cls}">{head}{body}{foot}</section>'


# ---------------------------------------------------------------- content
pages = []

# 1. Cover
pages.append(page(1, f'''
<div class="cover-pattern"></div>
<div class="cover-ros r1">{rosette(520, opacity=.28, stroke=1)}</div>
<div class="cover-ros r2">{rosette(260, opacity=.35)}</div>
<div class="cover-top">
  <div class="tag">ملف تعريفي · الإصدار 1.3</div>
</div>
<div class="cover-title">
  <div class="over">منصة متكاملة للمصارف</div>
  <h1>الحوكمة<br>وإدارة المخاطر<br><span>والامتثال</span></h1>
  <p class="lead">صورة واحدة واضحة لمخاطر المصرف والتزاماته،<br>بالعربية أولاً، ومصممة لطبيعة العمل المصرفي الإسلامي.</p>
  <div class="pills"><span>المخاطر</span><span>الامتثال</span><span>الامتثال الشرعي</span><span>الضوابط</span><span>استمرارية الأعمال</span><span>تقارير المجلس</span></div>
</div>
<div class="cover-shot">{shot("dashboard","shot tilt")}</div>
<div class="cover-band"><div>من ملفات متفرقة… <b>إلى قرار واثق</b></div></div>
''', cls='cover'))

# 2. The challenge
pains = [
    ('xls', 'بيانات متفرقة', 'سجلات المخاطر والحوادث والالتزامات موزعة على ملفات Excel والبريد الإلكتروني، ولا توجد «نسخة واحدة من الحقيقة».'),
    ('clock', 'متابعة يدوية', 'المهام والمواعيد تُتابع بالاتصالات والتذكير الشخصي، فتتأخر الإجراءات ولا يعرف أحد من المسؤول.'),
    ('chart', 'تقارير مُرهقة', 'إعداد تقرير لمجلس الإدارة أو لجنة المخاطر يستغرق أياماً من التجميع والتنسيق اليدوي.'),
    ('scale', 'متطلبات متزايدة', 'توقعات الجهات الرقابية في الحوكمة وإدارة المخاطر، ومتطلبات الرقابة الشرعية، تحتاج إلى دليل موثق لا إلى اجتهاد.'),
]
cards = ''.join(f'<div class="pain">{badge(i)}<h3>{t}</h3><p>{d}</p></div>' for i, t, d in pains)
pages.append(page(2, f'''
<p class="intro">يواجه مدير المصرف سؤالاً بسيطاً في ظاهره: <b>«ما أكبر المخاطر علينا اليوم، ومن يعالجها، ومتى؟»</b>
والإجابة عنه في أغلب المصارف تمر عبر عشرات الملفات والرسائل والاجتماعات.</p>
<div class="pains">{cards}</div>
<div class="quote">
  <div class="qros">{rosette(150, opacity=.5)}</div>
  <blockquote>ما لا يُقاس لا يُدار،<br>وما لا يُوثَّق لا يُثبَت أمام المراجع والرقيب.</blockquote>
</div>
<div class="callout">{icon("compass")}<p>هذه المنصة صُممت لتجيب عن ذلك السؤال في <b>شاشة واحدة</b>، وتحفظ الدليل الموثق خلف كل رقم.</p></div>
''', title='لماذا تحتاج المصارف إلى منصة موحدة؟', kicker='التحدي'))

# 3. At a glance - wheel
pillars = [
    ('alert', 'إدارة المخاطر', 'سجل، تقييم، معالجة'),
    ('scale', 'الامتثال', 'التزامات وتعاميم'),
    ('moon', 'الامتثال الشرعي', 'فتاوى ومراجعات وتطهير'),
    ('shield', 'الضوابط الرقابية', 'مكتبة واختبار'),
    ('layers', 'استمرارية الأعمال', 'تحليل الأثر'),
    ('users', 'الحوكمة والتقارير', 'مجالس، لجان، أهداف'),
]
W = 560
c = W / 2
wheel = f'<svg viewBox="0 0 {W} {W}" class="wheel">'
wheel += f'<defs><radialGradient id="cg" cx="50%" cy="40%" r="60%"><stop offset="0" stop-color="#1d4477"/><stop offset="1" stop-color="#0B2545"/></radialGradient>'
wheel += '<linearGradient id="gg" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#F1D78A"/><stop offset=".5" stop-color="#C9A227"/><stop offset="1" stop-color="#9A7B1E"/></linearGradient></defs>'
wheel += f'<circle cx="{c}" cy="{c}" r="{c-6}" fill="none" stroke="#C9A227" stroke-width="1" stroke-dasharray="3 7"/>'
wheel += f'<polygon points="{star(c, c, 205, 150, 12)}" fill="none" stroke="#E6C66A" stroke-width="1"/>'
for i in range(6):
    a = math.radians(-90 + i * 60)
    x, y = c + 190 * math.cos(a), c + 190 * math.sin(a)
    wheel += f'<line x1="{c}" y1="{c}" x2="{x:.1f}" y2="{y:.1f}" stroke="#C9A227" stroke-width="1.2" stroke-opacity=".6"/>'
wheel += f'<polygon points="{star(c, c, 112, 92, 8)}" fill="url(#gg)"/>'
wheel += f'<circle cx="{c}" cy="{c}" r="86" fill="url(#cg)"/>'
wheel += f'<text x="{c}" y="{c-8}" class="wt1">منصة</text><text x="{c}" y="{c+26}" class="wt2">واحدة</text>'
wheel += '</svg>'
nodes = ''
for i, (ic, t, d) in enumerate(pillars):
    a = math.radians(-90 + i * 60)
    x, y = 50 + 34 * math.cos(a), 50 + 34 * math.sin(a)
    nodes += f'<div class="node" style="left:{x:.2f}%;top:{y:.2f}%">{badge(ic)}<b>{t}</b><small>{d}</small></div>'
stats = [('+18', 'وحدة عمل متكاملة'), ('2', 'لغتان: العربية والإنجليزية'), ('1,234', 'فحصاً آلياً للجودة'), ('100%', 'واجهات عربية من اليمين لليسار')]
st = ''.join(f'<div class="stat"><b>{a}</b><span>{b}</span></div>' for a, b in stats)
pages.append(page(3, f'''
<p class="intro center">ست ركائز مترابطة تعمل على قاعدة بيانات واحدة، فما يُسجَّل في وحدة يظهر أثره فوراً في الوحدات الأخرى وفي لوحة الإدارة.</p>
<div class="wheel-wrap">{wheel}{nodes}</div>
<div class="stats">{st}</div>
''', title='المنصة في صفحة واحدة', kicker='الحل'))

# 4. Modules grid
mods = [
    ('alert', 'سجل المخاطر', 'تقييم الاحتمال والأثر، المخاطر المتبقية، والمالك.'),
    ('check', 'إجراءات المعالجة', 'خطط بمسؤول وموعد ونسبة إنجاز.'),
    ('pulse', 'الحوادث والخسائر', 'تصنيف بازل، تصعيد تلقائي، وكشف التكرار.'),
    ('target', 'مؤشرات المخاطر KRI', 'حدود أخضر/أصفر/أحمر، مؤشرات مركبة، وتنبيه بالتجاوز.'),
    ('compass', 'قابلية تحمل المخاطر', 'حدود يعتمدها المجلس وقياس التجاوز.'),
    ('list', 'التقييم الذاتي RCSA', 'جداول دورية ومحفزات تلقائية.'),
    ('shield', 'مكتبة الضوابط', 'ضوابط مربوطة بالمخاطر واختبار فعاليتها.'),
    ('scale', 'الالتزامات الرقابية', 'سجل المتطلبات ومواعيدها ومسؤوليها.'),
    ('file', 'التعاميم الرقابية', 'توزيع التعميم وتتبع إقرار كل إدارة.'),
    ('moon', 'الامتثال الشرعي', 'الفتاوى، المراجعات، الأحداث، التطهير، والتقرير السنوي.'),
    ('layers', 'تحليل الأثر BIA', 'العمليات الحرجة وأهداف الاستعادة.'),
    ('book', 'السياسات', 'إصدارات ومراجعة واعتماد موثق.'),
    ('users', 'المجالس واللجان', 'الاجتماعات والقرارات ومتابعة تنفيذها.'),
    ('spark', 'الأهداف الاستراتيجية', 'بطاقة أداء تربط الأهداف بالمخاطر.'),
    ('pen', 'الموافقات', 'مبدأ «المُدخِل والمُراجِع» في القرارات الحساسة.'),
    ('chart', 'التقارير وحزمة المجلس', 'تقارير جاهزة وتصدير إلى Excel.'),
    ('upload', 'استيراد البيانات', 'نقل سجلاتكم الحالية من ملفات CSV.'),
    ('eye', 'سجل التدقيق', 'كل تعديل محفوظ: من، ماذا، ومتى.'),
]
grid = ''.join(f'<div class="mod">{badge(i)}<div><h4>{t}</h4><p>{d}</p></div></div>' for i, t, d in mods)
pages.append(page(4, f'''
<p class="intro">كل ما تحتاجه إدارات المخاطر والامتثال والرقابة الشرعية والتدقيق في مكان واحد، بصلاحيات تحدد ما يراه كل مستخدم.</p>
<div class="mods">{grid}</div>
''', title='ثماني عشرة وحدة… منظومة واحدة', kicker='الوحدات'))

# 5. Islamic banking
pages.append(page(5, f'''
<div class="split">
  <div class="txt">
    <p class="intro">ليست وحدة مضافة لاحقاً، بل جزء أصيل من المنصة يخدم هيئة الرقابة الشرعية وإدارة التدقيق الشرعي.</p>
    <ul class="ticks">
      <li><b>سجل الفتاوى والقرارات الشرعية</b> مرجع موحد يُربط بالمنتجات والعقود.</li>
      <li><b>المراجعات الشرعية</b> خطة مراجعة، نتائج، وتوصيات بمتابعة تنفيذها.</li>
      <li><b>أحداث عدم الالتزام الشرعي</b> تسجيل الحدث وأثره وتصنيفه وإجراءاته.</li>
      <li><b>التطهير</b> احتساب المبالغ الواجب تطهيرها ومتابعة صرفها حتى الإقفال.</li>
      <li><b>التقرير الشرعي السنوي</b> يُجمَّع آلياً من البيانات المسجلة.</li>
    </ul>
  </div>
  <div class="ill">{rosette(300, opacity=.9)}</div>
</div>
<div class="duo">{shot("sharia", cap="لوحة الامتثال الشرعي")}{shot("event", cap="تفاصيل حدث عدم التزام شرعي ومبلغ التطهير")}</div>
''', title='مصممة للعمل المصرفي الإسلامي', kicker='ميزة فارقة'))

# 6. Ease of use
ease = [
    ('help', 'دليل مبسط داخل كل شاشة', 'زر «؟» يفتح شرحاً قصيراً يناسب دور المستخدم والشاشة التي يعمل عليها.'),
    ('pen', 'ملاحظات وتذكيرات شخصية', 'يدوّن المستخدم ملاحظته ويحدد موعد التذكير، فلا يضيع شيء.'),
    ('list', '«مهامي» في مكان واحد', 'كل ما ينتظر المستخدم: موافقات، إجراءات متأخرة، تعاميم للإقرار.'),
    ('mail', 'تنبيهات بالبريد', 'ملخص دوري وتصعيد تلقائي للمتأخرات، مع خيار إيقافه.'),
    ('globe', 'عربي / إنجليزي', 'تبديل اللغة بضغطة واحدة، والواجهة العربية كاملة من اليمين لليسار.'),
    ('phone', 'يعمل على الجوال', 'واجهة متجاوبة للمتابعة السريعة من أي مكان.'),
]
eg = ''.join(f'<div class="ease">{badge(i)}<h4>{t}</h4><p>{d}</p></div>' for i, t, d in ease)
pages.append(page(6, f'''
<p class="intro">صُممت المنصة لموظف المصرف لا لخبير التقنية: لغة واضحة، ألوان تدل على الأولوية، وكل شاشة تشرح نفسها.</p>
<div class="ease-grid">{eg}</div>
<div class="ease-shots">
  <div class="col2">{shot("help", cap="مركز المساعدة: شرح مبسط حسب دور المستخدم")}</div>
  <figure class="phone"><img src="img/mobile.png" alt=""><figcaption>«مهامي» على الجوال</figcaption></figure>
</div>
''', title='سهلة… وممتعة في الاستخدام', kicker='تجربة المستخدم'))

# 7. Board visibility
pages.append(page(7, f'''
<p class="intro">لوحة المعلومات تُجيب في ثوانٍ: أين المخاطر المرتفعة؟ ما المتأخر؟ ما حجم الخسائر؟ أين تقف أهدافنا الاستراتيجية؟</p>
{shot("dashboard", "shot wide", "لوحة المعلومات: أرقام محسوبة من البيانات المسجلة، كل رقم يقود إلى تفاصيله")}
<div class="duo">{shot("strategy", cap="بطاقة الأهداف الاستراتيجية")}{shot("governance", cap="تقرير المجالس واللجان وقراراتها")}</div>
<div class="chips">
  <span>{icon("chart")} حزمة تقارير مجلس الإدارة</span>
  <span>{icon("xls")} تصدير Excel بضغطة</span>
  <span>{icon("users")} متابعة قرارات اللجان</span>
  <span>{icon("target")} ربط الأهداف بالمخاطر</span>
</div>
''', title='رؤية الإدارة العليا ومجلس الإدارة', kicker='وضوح القرار'))

# 8. Controls / BIA / circulars / risk
pages.append(page(8, f'''
<div class="quad">
  <div class="qi">{shot("risk")}<h4>{icon("alert")} سجل المخاطر</h4><p>خريطة حرارية، تقييم متبقٍ، ومالك واضح لكل خطر.</p></div>
  <div class="qi">{shot("controls")}<h4>{icon("shield")} مكتبة الضوابط واختبارها</h4><p>تكشف المنصة المخاطر التي تعتمد على ضوابط غير مُثبتة الفعالية.</p></div>
  <div class="qi">{shot("bia")}<h4>{icon("layers")} تحليل الأثر على الأعمال</h4><p>العمليات الحرجة، أهداف زمن الاستعادة، وفجوات التعافي.</p></div>
  <div class="qi">{shot("circulars")}<h4>{icon("file")} التعاميم الرقابية</h4><p>كل تعميم يصل إلى الإدارة المعنية، ويُسجَّل إقرارها وتاريخه.</p></div>
</div>
''', title='من التسجيل إلى الدليل الموثق', kicker='داخل المنصة'))

# 9. Security
sec = [
    ('key', 'تحقق بخطوتين', 'رمز متغير من تطبيق المصادقة على الجوال (TOTP) إضافة إلى كلمة المرور.'),
    ('pen', 'المُدخِل والمُراجِع', 'القرارات الحساسة لا تُعتمد إلا بموافقة شخص ثانٍ.'),
    ('eye', 'سجل تدقيق كامل', 'كل إضافة أو تعديل محفوظة باسم صاحبها ووقتها.'),
    ('branch', 'صلاحيات حسب الدور والفرع', 'كل مستخدم يرى بيانات إدارته أو فرعه فقط.'),
    ('lock', 'تشفير البيانات الحساسة', 'الأسرار المخزنة مشفرة بمعيار AES-256.'),
    ('server', 'بياناتكم تحت سيطرتكم', 'يمكن تشغيلها على خوادم المصرف أو استضافة يختارها المصرف.'),
]
sg = ''.join(f'<div class="sec">{badge(i)}<div><h4>{t}</h4><p>{d}</p></div></div>' for i, t, d in sec)
pages.append(page(9, f'''
<p class="intro">منصة تحمل بيانات المخاطر يجب أن تكون نفسها نموذجاً للانضباط الرقابي.</p>
<div class="secs">{sg}</div>
<div class="shieldbox">
  <div class="sros">{rosette(220, opacity=.6)}</div>
  <div><h3>جودة مُختبَرة</h3><p>كل إصدار يمر على <b>1,234 فحصاً آلياً</b> تغطي الصلاحيات والحسابات والواجهات العربية والإنجليزية، قبل أن يصل إلى المستخدم.</p></div>
</div>
''', title='الأمان والانضباط الرقابي', kicker='الثقة'))

# 10. Value
rows = [
    ('ملفات Excel متفرقة ونسخ متعارضة', 'قاعدة بيانات واحدة ونسخة واحدة من الحقيقة'),
    ('تقرير المجلس يستغرق أياماً', 'حزمة تقارير جاهزة وتصدير Excel فوري'),
    ('متابعة المهام بالهاتف والذاكرة', '«مهامي» وتذكيرات وتصعيد تلقائي بالبريد'),
    ('صعوبة إثبات ما تم أمام الرقيب', 'سجل تدقيق وموافقات موثقة لكل خطوة'),
    ('الامتثال الشرعي في ملفات منفصلة', 'وحدة شرعية متكاملة وتقرير سنوي آلي'),
    ('اعتماد على أشخاص بعينهم', 'معرفة مؤسسية محفوظة في النظام'),
]
tr = ''.join(f'<tr><td class="b">{a}</td><td class="ar">←</td><td class="a">{b}</td></tr>' for a, b in rows)
gains = [
    ('compass', 'قرار أسرع وأوضح', 'صورة لحظية عن المخاطر بدل انتظار التقارير.'),
    ('scale', 'جاهزية رقابية', 'دليل موثق جاهز عند التفتيش أو المراجعة.'),
    ('clock', 'وقت أقل في التجميع', 'وقت أكثر للتحليل والمعالجة.'),
    ('moon', 'ثقة المساهمين والعملاء', 'التزام شرعي ظاهر وموثق.'),
]
gg = ''.join(f'<div class="gain">{badge(i)}<h4>{t}</h4><p>{d}</p></div>' for i, t, d in gains)
pages.append(page(10, f'''
<table class="ba"><thead><tr><th>قبل المنصة</th><th></th><th>بعد المنصة</th></tr></thead><tbody>{tr}</tbody></table>
<h3 class="sub">ماذا يكسب المصرف؟</h3>
<div class="gains">{gg}</div>
''', title='القيمة المضافة لمصرفكم', kicker='العائد'))

# 11. Roadmap
steps = [
    ('1', 'عرض وتجربة', 'عرض حي على بيانات تجريبية، ثم تجربة محدودة مع إدارة المخاطر.'),
    ('2', 'إعداد ونقل البيانات', 'ضبط الإدارات والفروع والصلاحيات، واستيراد السجلات الحالية من ملفات CSV.'),
    ('3', 'تدريب المستخدمين', 'جلسات قصيرة حسب الدور، يدعمها الدليل المبسط داخل المنصة.'),
    ('4', 'التشغيل والتوسع', 'التشغيل الفعلي ثم تفعيل الوحدات تدريجياً حسب أولويات المصرف.'),
]
sp = ''.join(f'<div class="step"><div class="sn"><span>{n}</span></div><h4>{t}</h4><p>{d}</p></div>' for n, t, d in steps)
pages.append(page(11, f'''
<p class="intro">نبدأ صغيراً ونتوسع بثقة: تجربة عملية أولاً، ثم انتقال منظم دون تعطيل العمل اليومي.</p>
<div class="steps">{sp}</div>
<div class="prereq">
  <h3>{icon("check")} متطلبات التشغيل الفعلي (بشفافية)</h3>
  <ul>
    <li>بيئة استضافة إنتاجية يتحكم بها المصرف، مع خطة نسخ احتياطي واستعادة من الكوارث.</li>
    <li>اختبار اختراق مستقل قبل التشغيل الفعلي، وفق سياسة أمن المعلومات في المصرف.</li>
    <li>مراجعة المصطلحات العربية ومواءمتها مع تعليمات مصرف ليبيا المركزي وسياسات المصرف الداخلية.</li>
    <li>ربط خادم البريد الإلكتروني الداخلي لتفعيل التنبيهات والملخصات.</li>
  </ul>
</div>
<div class="tech">
  <div>{icon("server")}<b>تقنية قياسية</b><span>PHP و MySQL، تعمل على استضافة شائعة دون تراخيص إضافية لقواعد البيانات</span></div>
  <div>{icon("globe")}<b>متصفح فقط</b><span>لا برامج تُثبَّت على أجهزة الموظفين</span></div>
  <div>{icon("upload")}<b>بداية سريعة</b><span>استيراد السجلات الحالية بدل إدخالها يدوياً</span></div>
</div>
''', title='خطة التطبيق', kicker='الطريق إلى التشغيل'))

# 12. Back cover
pages.append(page(12, f'''
<div class="cover-pattern"></div>
<div class="cover-ros r3">{rosette(600, opacity=.22, stroke=1)}</div>
<div class="back">
  <div class="bros">{rosette(170, opacity=1, stroke=1.4)}</div>
  <h2>لنبدأ بعرض عملي<br><span>على واقع مصرفكم</span></h2>
  <p>خلال جلسة واحدة نعرض كيف تتحول بيانات المخاطر والامتثال لديكم إلى لوحة قرار واضحة.</p>
  <div class="contact">
    <div>{icon("users")}<span>جهة التواصل</span><b>………………………………</b></div>
    <div>{icon("phone")}<span>الهاتف</span><b>………………………………</b></div>
    <div>{icon("mail")}<span>البريد الإلكتروني</span><b>………………………………</b></div>
  </div>
  <div class="cta">احجز عرضاً تجريبياً</div>
</div>
<div class="fine">جميع الصور في هذا الملف مأخوذة من بيئة عرض تحتوي بيانات تجريبية فقط. الإصدار 1.3 · 2026</div>
''', cls='cover backc'))


CSS = r'''
@page { size: A4; margin: 0; }
:root{--gold:#C9A227;--gold2:#E6C66A;--goldd:#9A7B1E;--navy:#0B2545;--navy2:#13315C;--ivory:#FBF7EE;--cream:#F3EAD3;--teal:#1F6F6B;--ink:#1d2433;--muted:#5d6577}
*{box-sizing:border-box;margin:0;padding:0}
html,body{background:#fff}
body{font-family:'Noto Sans Arabic',sans-serif;color:var(--ink);-webkit-print-color-adjust:exact;print-color-adjust:exact}
.page{width:210mm;height:297mm;position:relative;overflow:hidden;page-break-after:always;background:var(--ivory);padding:20mm 16mm 18mm}
.page:last-child{page-break-after:auto}
.page::before{content:'';position:absolute;inset:0;background:url("TILE") repeat;opacity:.07;pointer-events:none}
.page::after{content:'';position:absolute;top:0;right:0;width:100%;height:5mm;background:linear-gradient(90deg,var(--goldd),var(--gold2),var(--gold),var(--goldd))}
h1,h2,h3,h4,.kicker,.stat b,.pn{font-family:'Noto Kufi Arabic',sans-serif}
.ph{position:relative;margin-bottom:7mm}
.kicker{display:inline-block;color:var(--goldd);font-size:10pt;font-weight:700;letter-spacing:.5px;border:1px solid var(--gold);padding:1mm 4mm;border-radius:20px;background:#fff8e6}
.ph h2{font-size:23pt;color:var(--navy);margin-top:3mm;line-height:1.5}
.rule{display:flex;align-items:center;gap:3mm;margin-top:1mm;width:70mm}
.rule span{flex:1;height:1.5px;background:linear-gradient(90deg,transparent,var(--gold))}
.rule span:last-child{background:linear-gradient(90deg,var(--gold),transparent)}
.rs{width:5mm;height:5mm;color:var(--gold);fill:var(--gold)}
.pf{position:absolute;bottom:8mm;left:16mm;right:16mm;display:flex;justify-content:space-between;align-items:center;font-size:8pt;color:var(--muted);border-top:1px solid #e3d6b0;padding-top:2mm}
.pn{background:var(--navy);color:var(--gold2);width:9mm;height:9mm;display:grid;place-items:center;font-size:9pt;clip-path:polygon(30% 0,70% 0,100% 30%,100% 70%,70% 100%,30% 100%,0 70%,0 30%)}
.intro{font-size:12pt;line-height:2;color:var(--ink);margin-bottom:6mm}
.intro.center{text-align:center}
.intro b{color:var(--navy)}
.ic{width:6mm;height:6mm}
.badge{display:inline-grid;place-items:center;width:13mm;height:13mm;flex:none;color:var(--navy);background:linear-gradient(135deg,#F1D78A,var(--gold) 55%,var(--goldd));clip-path:polygon(30% 0,70% 0,100% 30%,100% 70%,70% 100%,30% 100%,0 70%,0 30%)}
.badge .ic{width:6.5mm;height:6.5mm}
/* screenshots */
figure.shot{background:#fff;border-radius:3mm;overflow:hidden;box-shadow:0 2mm 6mm rgba(11,37,69,.18);border:1px solid #e5d9b6}
figure.shot .bar{height:4.5mm;background:var(--navy);display:flex;gap:1.3mm;align-items:center;padding:0 2.5mm;direction:ltr}
figure.shot .bar i{width:1.6mm;height:1.6mm;border-radius:50%;background:var(--gold2);opacity:.8}
figure.shot img{display:block;width:100%}
figure figcaption{font-size:8.5pt;color:var(--muted);padding:2mm 3mm;background:#fff;border-top:1px solid #efe6cc}
/* cover */
.cover{background:radial-gradient(120% 80% at 80% 10%,#1d4477 0%,var(--navy) 55%,#071a33 100%);color:#fff;padding:0}
.cover::before{opacity:.12}
.cover-ros{position:absolute}
.cover-ros.r1{top:-150px;left:-170px}
.cover-ros.r2{bottom:250px;right:-90px}
.cover-ros.r3{top:50%;left:50%;transform:translate(-50%,-50%)}
.cover-top{position:absolute;top:18mm;right:16mm}
.tag{border:1px solid var(--gold);color:var(--gold2);padding:1.5mm 5mm;border-radius:20px;font-size:10pt;font-family:'Noto Kufi Arabic'}
.cover-title{position:absolute;top:40mm;right:16mm;left:16mm}
.cover-title .over{color:var(--gold2);font-family:'Noto Kufi Arabic';font-size:13pt;margin-bottom:2mm}
.cover-title h1{font-size:42pt;line-height:1.35;font-weight:700}
.cover-title h1 span{color:#E9C866}
.cover .lead{font-size:13pt;line-height:1.9;color:#dfe6f2;margin-top:5mm}
.pills{display:flex;flex-wrap:wrap;gap:2mm;margin-top:6mm;max-width:150mm}
.pills span{font-size:9pt;border:1px solid rgba(230,198,106,.6);color:#f4e2ae;padding:1mm 3.5mm;border-radius:20px;background:rgba(201,162,39,.08)}
.cover-shot{position:absolute;bottom:36mm;left:14mm;width:150mm}
.shot.tilt{transform:perspective(900px) rotateY(10deg) rotateX(4deg);border:2px solid var(--gold);box-shadow:0 8mm 18mm rgba(0,0,0,.45)}
.cover-band{position:absolute;bottom:0;left:0;right:0;height:22mm;background:linear-gradient(90deg,var(--goldd),var(--gold) 40%,#F1D78A 70%,var(--gold));display:flex;align-items:center;padding:0 16mm;clip-path:polygon(0 35%,100% 0,100% 100%,0 100%)}
.cover-band div{font-family:'Noto Kufi Arabic';color:var(--navy);font-size:14pt;margin-top:4mm}
/* challenge */
.pains{display:grid;grid-template-columns:1fr 1fr;gap:5mm}
.pain{background:#fff;border-radius:3mm;padding:6mm;border-top:3px solid var(--gold);box-shadow:0 1mm 4mm rgba(11,37,69,.08)}
.pain h3{color:var(--navy);font-size:14pt;margin:3mm 0 1.5mm}
.pain p{font-size:10.5pt;line-height:1.9;color:var(--muted)}
.quote{margin-top:9mm;position:relative;background:var(--navy);color:#fff;border-radius:4mm;padding:9mm 12mm 9mm 50mm;overflow:hidden}
.qros{position:absolute;left:5mm;top:50%;transform:translateY(-50%)}
.quote blockquote{font-family:'Noto Naskh Arabic',serif;font-size:17pt;line-height:1.8;color:var(--gold2)}
.callout{margin-top:7mm;display:flex;gap:4mm;align-items:center;background:#fff8e6;border-right:4px solid var(--gold);padding:5mm 6mm;border-radius:2mm}
.callout .ic{width:9mm;height:9mm;color:var(--goldd);flex:none}
.callout p{font-size:11.5pt;line-height:1.9}
/* wheel */
.wheel-wrap{position:relative;width:150mm;height:150mm;margin:0 auto}
.wheel{width:100%;height:100%}
.wt1{font-family:'Noto Kufi Arabic';fill:var(--gold2);font-size:20px;text-anchor:middle}
.wt2{font-family:'Noto Kufi Arabic';fill:#fff;font-size:30px;font-weight:700;text-anchor:middle}
.node{position:absolute;transform:translate(-50%,-50%);width:38mm;text-align:center;background:#fff;border:1px solid #ead9a6;border-radius:3mm;padding:3mm 2mm;box-shadow:0 1.5mm 4mm rgba(11,37,69,.12)}
.node .badge{width:11mm;height:11mm;margin-bottom:1mm}
.node b{display:block;font-family:'Noto Kufi Arabic';color:var(--navy);font-size:10.5pt}
.node small{color:var(--muted);font-size:8.5pt}
.stats{display:grid;grid-template-columns:repeat(4,1fr);gap:4mm;margin-top:6mm}
.stat{background:var(--navy);border-radius:3mm;padding:5mm 3mm;text-align:center;border-bottom:3px solid var(--gold)}
.stat b{display:block;font-size:22pt;color:var(--gold2);line-height:1.4}
.stat span{font-size:9pt;color:#dfe6f2}
/* modules */
.mods{display:grid;grid-template-columns:1fr 1fr;gap:3.5mm}
.mod{display:flex;gap:3.5mm;align-items:center;background:#fff;border-radius:2.5mm;padding:3.2mm 4mm;border-right:3px solid var(--gold);box-shadow:0 .7mm 2.5mm rgba(11,37,69,.07)}
.mod .badge{width:11mm;height:11mm}
.mod h4{color:var(--navy);font-size:11pt;line-height:1.6}
.mod p{font-size:9pt;color:var(--muted);line-height:1.6}
/* split */
.split{display:flex;gap:6mm;align-items:center}
.split .txt{flex:1}
.split .ill{flex:none}
.ticks{list-style:none}
.ticks li{position:relative;padding-right:8mm;margin-bottom:3mm;font-size:10.5pt;line-height:1.8;color:var(--muted)}
.ticks li b{display:block;color:var(--navy);font-family:'Noto Kufi Arabic';font-size:11pt}
.ticks li::before{content:'';position:absolute;right:0;top:2mm;width:4mm;height:4mm;background:var(--gold);clip-path:polygon(50% 0,100% 50%,50% 100%,0 50%)}
.duo{display:grid;grid-template-columns:1fr 1fr;gap:5mm;margin-top:5mm}
.chips{margin-top:5mm}
/* ease */
.ease-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:4mm}
.ease{background:#fff;border-radius:3mm;padding:4.5mm;text-align:center;border-bottom:3px solid var(--gold);box-shadow:0 .7mm 2.5mm rgba(11,37,69,.07)}
.ease .badge{margin-bottom:2mm}
.ease h4{color:var(--navy);font-size:11pt;line-height:1.6}
.ease p{font-size:9pt;line-height:1.7;color:var(--muted);margin-top:1mm}
.ease-shots{display:flex;gap:5mm;margin-top:6mm;align-items:flex-start}
.ease-shots .col2{flex:1;display:grid;gap:4mm}
.phone{width:40mm;flex:none;border:2.5mm solid var(--navy);border-radius:7mm;overflow:hidden;box-shadow:0 3mm 8mm rgba(11,37,69,.3);outline:1px solid var(--gold);background:#fff}
.phone img{display:block;width:100%}
.phone figcaption{text-align:center;background:var(--navy);color:var(--gold2);border:0}
/* board */
.shot.wide{width:76%;margin:0 auto}
.chips{display:flex;flex-wrap:nowrap;gap:2mm;margin-top:6mm;justify-content:center}
.chips span{display:inline-flex;align-items:center;gap:1.5mm;background:var(--navy);color:#fff;padding:1.5mm 3.5mm;border-radius:20px;font-size:8.8pt;white-space:nowrap}
.chips .ic{width:4.5mm;height:4.5mm;color:var(--gold2)}
/* quad */
.quad{display:grid;grid-template-columns:1fr 1fr;gap:7mm 6mm}
.qi h4{display:flex;align-items:center;gap:2mm;color:var(--navy);font-size:12pt;margin-top:3.5mm}
.qi h4 .ic{color:var(--goldd)}
.qi p{font-size:10pt;line-height:1.8;color:var(--muted)}
/* security */
.secs{display:grid;grid-template-columns:1fr 1fr;gap:5mm}
.sec{display:flex;gap:4mm;background:#fff;border-radius:3mm;padding:5.5mm;box-shadow:0 .7mm 2.5mm rgba(11,37,69,.07);border-top:3px solid var(--navy)}
.sec h4{color:var(--navy);font-size:12pt;line-height:1.6}
.sec p{font-size:9.5pt;line-height:1.8;color:var(--muted)}
.shieldbox{margin-top:9mm;display:flex;gap:7mm;align-items:center;background:linear-gradient(135deg,var(--navy),var(--navy2));color:#fff;border-radius:4mm;padding:7mm 9mm;border:1px solid var(--gold);overflow:hidden;position:relative}
.shieldbox .sros{flex:none;width:52mm;height:52mm;display:grid;place-items:center}
.shieldbox .sros svg{width:52mm;height:52mm}
.shieldbox h3{color:var(--gold2);font-size:17pt}
.shieldbox p{font-size:11.5pt;line-height:2;color:#e5ebf5}
.shieldbox b{color:var(--gold2)}
/* value */
table.ba{width:100%;border-collapse:separate;border-spacing:0 2.2mm}
table.ba th{font-family:'Noto Kufi Arabic';font-size:12pt;padding:3mm;color:#fff}
table.ba th:first-child{background:#7a8496;border-radius:0 2mm 2mm 0}
table.ba th:nth-child(2){background:transparent}
table.ba th:last-child{background:var(--navy);color:var(--gold2);border-radius:2mm 0 0 2mm}
table.ba td{padding:3.4mm 4mm;font-size:10.5pt;line-height:1.6}
table.ba td.b{background:#eceef2;color:#5d6577;border-radius:0 2mm 2mm 0;width:44%}
table.ba td.a{background:#fff;color:var(--navy);font-weight:700;border-right:3px solid var(--gold);border-radius:2mm 0 0 2mm;width:48%;box-shadow:0 .5mm 2mm rgba(11,37,69,.08)}
table.ba td.ar{text-align:center;color:var(--gold);font-size:16pt;width:8%}
h3.sub{color:var(--navy);font-size:15pt;margin:8mm 0 4mm}
.gains{display:grid;grid-template-columns:repeat(4,1fr);gap:4mm}
.gain{background:#fff;border-radius:3mm;padding:5mm 3mm;text-align:center;border-bottom:3px solid var(--gold);box-shadow:0 .7mm 2.5mm rgba(11,37,69,.07)}
.gain h4{color:var(--navy);font-size:10.5pt;margin-top:2mm;line-height:1.6}
.gain p{font-size:8.8pt;color:var(--muted);line-height:1.7}
/* roadmap */
.steps{display:grid;grid-template-columns:repeat(4,1fr);gap:4mm;position:relative;margin-top:3mm}
.steps::before{content:'';position:absolute;top:10mm;right:12%;left:12%;height:2px;background:repeating-linear-gradient(90deg,var(--gold) 0 6px,transparent 6px 11px)}
.step{text-align:center;position:relative}
.sn{width:20mm;height:20mm;margin:0 auto 3mm;display:grid;place-items:center;background:linear-gradient(135deg,#F1D78A,var(--gold),var(--goldd));clip-path:polygon(30% 0,70% 0,100% 30%,100% 70%,70% 100%,30% 100%,0 70%,0 30%)}
.sn span{width:15mm;height:15mm;display:grid;place-items:center;background:var(--navy);color:var(--gold2);font-family:'Noto Kufi Arabic';font-size:16pt;clip-path:inherit}
.step h4{color:var(--navy);font-size:11.5pt}
.step p{font-size:9.3pt;color:var(--muted);line-height:1.8}
.prereq{margin-top:8mm;background:#fff;border:1px solid #e5d6a8;border-right:4px solid var(--teal);border-radius:3mm;padding:5mm 7mm}
.prereq h3{display:flex;gap:2mm;align-items:center;color:var(--teal);font-size:12.5pt;margin-bottom:2mm}
.prereq ul{padding-right:5mm}
.prereq li{font-size:10pt;line-height:1.9;color:var(--ink)}
.tech{display:grid;grid-template-columns:repeat(3,1fr);gap:4mm;margin-top:6mm}
.tech div{background:var(--navy);color:#fff;border-radius:3mm;padding:5mm;text-align:center}
.tech .ic{width:9mm;height:9mm;color:var(--gold2)}
.tech b{display:block;font-family:'Noto Kufi Arabic';color:var(--gold2);font-size:11pt;margin:1.5mm 0}
.tech span{font-size:9pt;line-height:1.7;color:#dfe6f2}
/* back */
.back{position:absolute;top:34mm;left:18mm;right:18mm;text-align:center}
.bros{display:flex;justify-content:center;margin-bottom:7mm}
.back h2{font-size:30pt;line-height:1.5;color:#fff}
.back h2 span{color:var(--gold2)}
.back>p{font-size:13pt;line-height:2;color:#dfe6f2;margin:5mm auto 10mm;max-width:140mm}
.contact{display:grid;gap:4mm;max-width:130mm;margin:0 auto}
.contact div{display:flex;align-items:center;gap:4mm;background:rgba(255,255,255,.06);border:1px solid rgba(230,198,106,.45);border-radius:3mm;padding:4mm 6mm;text-align:right}
.contact .ic{color:var(--gold2);width:7mm;height:7mm}
.contact span{color:var(--gold2);font-family:'Noto Kufi Arabic';font-size:10.5pt;width:38mm}
.contact b{color:#c7d0de;font-weight:400;letter-spacing:1px}
.cta{display:inline-block;margin-top:11mm;background:linear-gradient(90deg,var(--goldd),var(--gold),#F1D78A);color:var(--navy);font-family:'Noto Kufi Arabic';font-weight:700;font-size:15pt;padding:4mm 14mm;border-radius:30px;box-shadow:0 2mm 6mm rgba(0,0,0,.35)}
.fine{position:absolute;bottom:10mm;left:16mm;right:16mm;text-align:center;font-size:8.5pt;color:#9fb0c8}
.backc::after{top:auto;bottom:0}
'''.replace('TILE', TILE)

HTML = f'''<!doctype html>
<html lang="ar" dir="rtl"><head><meta charset="utf-8">
<title>ملف تعريفي — منصة الحوكمة وإدارة المخاطر والامتثال</title>
<style>{CSS}</style></head><body>
{''.join(pages)}
</body></html>'''


def main():
    out_html = os.path.join(HERE, 'brochure.html')
    with open(out_html, 'w', encoding='utf-8') as f:
        f.write(HTML)
    print('wrote', out_html)
    if '--html-only' in sys.argv:
        return
    from playwright.sync_api import sync_playwright
    out_pdf = os.path.join(HERE, 'GRC-Platform-Brochure-AR.pdf')
    with sync_playwright() as p:
        b = p.chromium.launch(executable_path=CHROME)
        pg = b.new_page()
        pg.goto('file://' + out_html)
        pg.wait_for_load_state('networkidle')
        pg.evaluate('document.fonts.ready')
        pg.pdf(path=out_pdf, format='A4', print_background=True, prefer_css_page_size=True)
        b.close()
    print('wrote', out_pdf)


if __name__ == '__main__':
    main()
