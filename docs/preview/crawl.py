"""See docs/preview/index.html for the landing page. Run from the project root with the dev server on :8080 and demo data installed.
"""
"""Crawl the running dev app as several demo users and write a static, read-only preview.

usage: python3 crawl.py OUT_DIR
"""
import collections
import html as htmlmod
import os
import re
import shutil
import sys

from playwright.sync_api import sync_playwright

BASE = 'http://127.0.0.1:8080'
PW = 'Demo-Pass-2024x'
OUT = sys.argv[1]
USERS = [('r', 'demo.risk'), ('c', 'demo.compliance'), ('e', 'demo.exec'), ('d', 'demo.dept.retail'), ('a', 'demo.auditor')]
MAX = 48
PER_SECTION = 3
SKIP = re.compile(r'logout|/export|\.xlsx|\.csv|/download|/edit$|/print|/panel|/mfa|/password|/account|/setup|/admin|/import|/notes/\d')

INJECT = '''
<div id="pv-bar" dir="rtl" style="position:fixed;bottom:0;left:0;right:0;z-index:9999;background:#0B2545;color:#F4E2AE;border-top:3px solid #C9A227;font:14px/1.6 'Noto Sans Arabic',Tahoma,sans-serif;padding:8px 16px;display:flex;gap:12px;flex-wrap:wrap;align-items:center;justify-content:space-between">
<span><b style="color:#E9C866">نسخة تصفح تجريبية</b> · بيانات وهمية · الحفظ والإرسال معطلان في هذه النسخة · المستخدم: __ROLE__</span>
<a href="../index.html" style="color:#0B2545;background:#E9C866;padding:2px 14px;border-radius:999px;text-decoration:none;font-weight:700">تغيير الدور</a></div>
<div id="pv-toast" hidden style="position:fixed;bottom:64px;left:50%;transform:translateX(-50%);z-index:10000;background:#C9A227;color:#0B2545;padding:10px 18px;border-radius:10px;font:14px 'Noto Sans Arabic',Tahoma,sans-serif;box-shadow:0 6px 20px rgba(0,0,0,.3)">هذه نسخة للتصفح فقط. في النسخة الفعلية يُحفظ هذا الإجراء.</div>
<script>
(function(){
  document.body.style.paddingBottom='56px';
  var t=document.getElementById('pv-toast'),h;
  function toast(){t.hidden=false;clearTimeout(h);h=setTimeout(function(){t.hidden=true;},2600);}
  document.addEventListener('submit',function(e){e.preventDefault();e.stopPropagation();toast();},true);
  document.addEventListener('click',function(e){var a=e.target.closest&&e.target.closest('a[data-pv-off]');if(a){e.preventDefault();e.stopImmediatePropagation();toast();}},true);
})();
</script>
'''

ROLES = {'r': 'مدير المخاطر', 'c': 'مسؤول الامتثال', 'e': 'الإدارة التنفيذية', 'd': 'مدير إدارة (التجزئة)', 'a': 'المدقق الداخلي'}


def fname(path):
    p = path.strip('/')
    return (p.replace('/', '_') or 'home') + '.html'


def norm(href):
    href = htmlmod.unescape(href)
    if not href.startswith('/') or href.startswith('//'):
        return None
    if '?' in href or href.startswith('/assets'):
        return None
    href = href.split('#')[0].rstrip('/') or '/'
    if SKIP.search(href):
        return None
    return href


def crawl(ctx, prefix):
    page = ctx.new_page()
    page.goto(BASE + '/login')
    page.fill('#username', dict(USERS)[prefix])
    page.fill('#password', PW)
    page.click('button[type=submit]')
    page.wait_for_load_state('networkidle')
    assert '/login' not in page.url, 'login failed for ' + prefix
    seen, pages = set(), {}
    detail = collections.Counter()
    q = collections.deque(['/', '/my', '/help', '/ia', '/ia/findings', '/aml', '/aml/customers'])
    while q and len(pages) < MAX:
        u = q.popleft()
        if u in seen:
            continue
        seen.add(u)
        r = ctx.request.get(BASE + u, max_redirects=0)
        if r.status != 200 or 'text/html' not in r.headers.get('content-type', ''):
            continue
        body = r.text()
        pages[u] = body
        for h in re.findall(r'href="([^"]+)"', body):
            n = norm(h)
            if not n or n in seen or n in q:
                continue
            parts = n.strip('/').split('/')
            sec = '/'.join(parts[:2]) if parts[0] in ('ia', 'aml') else parts[0]
            if re.search(r'/\d+$', n):
                if detail[sec] >= PER_SECTION:
                    continue
                detail[sec] += 1
                q.appendleft(n) if n.startswith(('/ia', '/aml')) else q.append(n)
            elif n.startswith(('/ia', '/aml')) or n.count('/') == 1:
                q.appendleft(n)
            else:
                q.append(n)
    page.close()
    return pages


def rewrite(body, pages, prefix):
    def link(m):
        raw = m.group(1)
        if raw.startswith('/assets/'):
            return 'href="../assets/' + raw[len('/assets/'):].split('?')[0] + '"'
        n = norm(raw) if raw.startswith('/') else None
        if n and n in pages:
            return 'href="' + fname(n) + '"'
        if raw.startswith('/') or raw.startswith('?'):
            return 'href="#" data-pv-off="1"'
        return m.group(0)
    body = re.sub(r'href="([^"]*)"', link, body)
    body = re.sub(r'src="/assets/([^"?]+)[^"]*"', r'src="../assets/\1"', body)
    body = body.replace('</body>', INJECT.replace('__ROLE__', ROLES[prefix]) + '</body>')
    return body


def main():
    if os.path.exists(OUT):
        shutil.rmtree(OUT)
    os.makedirs(os.path.join(OUT, 'assets'))
    shutil.copytree('public/assets/css', os.path.join(OUT, 'assets', 'css'))
    shutil.copytree('public/assets/js', os.path.join(OUT, 'assets', 'js'))
    counts = {}
    with sync_playwright() as p:
        b = p.chromium.launch(executable_path='/opt/pw-browsers/chromium-1194/chrome-linux/chrome')
        for prefix, _ in USERS:
            ctx = b.new_context(locale='ar')
            pages = crawl(ctx, prefix)
            ctx.close()
            os.makedirs(os.path.join(OUT, prefix))
            for u, body in pages.items():
                with open(os.path.join(OUT, prefix, fname(u)), 'w', encoding='utf-8') as f:
                    f.write(rewrite(body, pages, prefix))
            counts[prefix] = len(pages)
        b.close()
    print(counts)


if __name__ == '__main__':
    main()
