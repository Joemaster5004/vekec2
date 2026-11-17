"""Public department guidance regression; isolated test server only."""
import base64
import http.cookiejar
import json
import re
import secrets
import time
import urllib.error
import urllib.parse
import urllib.request
from pathlib import Path

BASE = 'http://localhost:8090'
checks = 0
class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, *args): return None
class Client:
    def __init__(self):
        self.opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()), NoRedirect())
        self.token = ''
        self.location = ''
    def request(self, path, data=None, files=(), csrf=True, headers=None):
        headers = dict(headers or {})
        payload = None
        if data is not None:
            data = dict(data)
            if csrf: data['_csrf'] = self.token
            if files:
                boundary = secrets.token_hex(16)
                payload = b''.join(f'--{boundary}\r\nContent-Disposition: form-data; name="{k}"\r\n\r\n{v}\r\n'.encode() for k,v in data.items())
                for field, name, mime, content in files:
                    payload += f'--{boundary}\r\nContent-Disposition: form-data; name="{field}"; filename="{name}"\r\nContent-Type: {mime}\r\n\r\n'.encode() + content + b'\r\n'
                payload += f'--{boundary}--\r\n'.encode()
                headers['Content-Type'] = 'multipart/form-data; boundary=' + boundary
            else: payload = urllib.parse.urlencode(data).encode()
        try: r = self.opener.open(urllib.request.Request(BASE+path, data=payload, headers=headers), timeout=30)
        except urllib.error.HTTPError as error: r = error
        self.location = r.headers.get('Location', '')
        raw = r.read()
        body = raw.decode(errors='replace')
        match = re.search(r'name="csrf-token" content="([^"]+)"', body)
        if match: self.token = match[1]
        return r.status, body
    def login(self, role):
        self.request('/login')
        expect(self.request('/login', {'email': role+'@example.invalid', 'password':'TestOnlyPassword!2026'}),303)
        self.request('/dashboard')
def expect(result, status):
    global checks
    assert result[0] == status, (status, result[0], result[1][-700:])
    checks += 1
    return result[1]

def video_stats(client, vid, views, certificates, directory='/'):
    page = expect(client.request(directory),200)
    cards = re.findall(r'<article class="panel guidance-card">.*?</article>',page,re.S)
    card = next(card for card in cards if f'href="/department-videos/{vid}"' in card)
    assert f'เข้าชม {views:,} ครั้ง' in card, card
    assert f'รับเกียรติบัตร {certificates:,} คน' in card, card

if __name__ == '__main__':
    a, t, g = Client(), Client(), Client()
    a.login('admin'); t.login('teacher'); g.request('/')
    expect(g.request('/admin/department-videos'),303)
    expect(t.request('/admin/department-videos'),403)
    expect(t.request('/admin/department-videos',{}),403)
    expect(a.request('/admin/department-videos',{},csrf=False),403)
    page = expect(a.request('/admin/department-videos'),200)
    category = re.search(r'<option value="(\d+)"', page)[1]
    data = dict(category_id=category,title='Guidance '+secrets.token_hex(5)+' <script>',description='Guidance description',source='url',external_url='https://example.com/video',application_url='https://example.com/apply',duration_seconds=2,active=1)
    expect(a.request('/admin/department-videos',{**data,'external_url':'javascript:alert(1)'}),422)
    expect(a.request('/admin/department-videos',{**data,'application_url':'javascript:alert(1)'}),422)
    expect(a.request('/admin/department-videos',{**data,'category_id':9999999}),422)
    image = base64.b64decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII=')
    expect(a.request('/admin/department-videos',data,[('attachments[]','bad.html','text/html',b'<script>evil</script>')]),422)
    expect(a.request('/admin/department-videos',data,[('attachments[]','guide.png','image/png',image)]),303)
    page = expect(a.request('/admin/department-videos'),200)
    vid = max(map(int,re.findall(r'id="department-video-(\d+)"',page)))
    path=f'/department-videos/{vid}'
    page=expect(g.request('/'),200)
    assert page.index('id="department-videos"') < page.index('LATEST COURSES')
    assert '&lt;script&gt;' in page
    video_stats(g,vid,0,0)
    page=expect(g.request(path),200)
    video_stats(g,vid,1,0)
    expect(g.request(path),200)
    video_stats(g,vid,1,0)  # Refreshes and catalog visits do not inflate views.
    another_guest=Client()
    expect(another_guest.request(path),200)
    video_stats(g,vid,2,0,'/department-videos')
    assert 'https://example.com/apply' in page and 'id="guidance-registration" hidden' in page
    fileid=re.search(fr'{path}/media/(\d+)',page)[1]
    expect(g.request(f'{path}/media/{fileid}'),200)
    video_stats(g,vid,2,0)
    expect(t.request(f'/admin/department-videos/{vid}/save',data),403)
    expect(t.request(f'/admin/department-videos/{vid}/files/{fileid}/delete',{}),403)
    expect(g.request(path+'/tick',dict(token='bad',confirmed=1)),409)
    profile=dict(name='Guidance Student',school='Private School',grade='M3',phone='0812345678',email='guidance@example.invalid',consent=1)
    expect(g.request(path+'/certificate',profile),403)
    state=json.loads(expect(g.request(path+'/start',{}),200))
    expect(g.request(path+'/tick',dict(token=state['token'],confirmed=1)),422)
    time.sleep(2.1)
    state=json.loads(expect(g.request(path+'/tick',dict(token=state['token'],confirmed=1)),200))
    assert state['completed']
    expect(g.request(path+'/certificate',{**profile,'email':'bad'}),422)
    expect(g.request(path+'/certificate',{**profile,'consent':0}),422)
    expect(g.request(path+'/certificate',profile),303)
    certificate=g.location
    expect(g.request(path+'/certificate',profile),303)
    assert certificate==g.location
    video_stats(g,vid,2,1)  # Issuance retries reuse the existing certificate.
    video_stats(g,vid,2,1,'/department-videos')
    cert=expect(Client().request(certificate),200)
    assert 'Guidance Student' in cert and 'guidance@example.invalid' not in cert and 'Private School' not in cert
    expect(a.request(f'/admin/department-videos/{vid}/save',{**data,'title':'Changed title','active':0}),303)
    expect(g.request(path),404)
    expect(a.request(path),200)  # Hidden admin previews must not add views.
    expect(g.request(f'{path}/media/{fileid}'),404)
    cert=expect(g.request(certificate),200)
    assert 'Changed title' not in cert
    expect(a.request(f'/admin/department-videos/{vid}/save',data),303)
    video_stats(g,vid,2,1)  # Edits and republishing preserve the counters.
    expect(g.request(path+'/certificate',profile),403)
    expect(a.request(f'/admin/categories/{category}/delete',{}),409)
    expect(a.request(f'/admin/department-videos/{vid}/files/{fileid}/delete',{}),303)
    expect(g.request(f'{path}/media/{fileid}'),404)
    # Real MP4 upload, byte ranges and elapsed-time watch checks.
    clip=Path('tests/.artifacts/department-clip.mp4').read_bytes()
    expect(a.request('/admin/department-videos',{**data,'source':'upload','title':'Uploaded clip'},[('video','clip.mp4','video/mp4',clip)]),303)
    page=expect(a.request('/admin/department-videos'),200)
    vid=max(map(int,re.findall(r'id="department-video-(\d+)"',page)));path=f'/department-videos/{vid}'
    video_stats(g,vid,0,0)  # Counters belong to individual videos.
    expect(g.request(path),200)
    expect(g.request(path+'/media',headers={'Range':'bytes=0-31'}),206)
    state=json.loads(expect(g.request(path+'/start',{}),200));token=state['token']
    for position in [8, 'NaN', -1]:
        result=g.request(path+'/tick',dict(token=token,position=position))
        if position==8: assert json.loads(expect(result,200))['reset']
        else: expect(result,422)
    for position in range(1,9):
        time.sleep(1)
        state=json.loads(expect(g.request(path+'/tick',dict(token=token,position=position)),200))
    assert state['completed'],state
    expect(g.request(path+'/certificate',profile),303)
    expect(g.request(g.location),200)
    expect(a.request(f'/admin/department-videos/{vid}/save',{**data,'source':'upload','active':0}),303)
    # Participant contact details stay restricted to admin.
    assert 'guidance@example.invalid' in expect(a.request('/admin/department-videos'),200)
    print(f'{checks} department guidance HTTP checks passed.')
