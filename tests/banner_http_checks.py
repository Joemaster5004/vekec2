"""Banner regression checks; only the isolated test server on port 8090."""
import base64
import http.cookiejar
import re
import secrets
import urllib.error
import urllib.parse
import urllib.request

BASE = 'http://localhost:8090'
class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, *args): return None
class Client:
    def __init__(self):
        self.opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()), NoRedirect())
        self.token = ''
    def request(self, path, data=None, image=None, csrf=True):
        headers = {}
        payload = None
        if data is not None:
            data = dict(data)
            if csrf: data['_csrf'] = self.token
            if image is not None:
                boundary = secrets.token_hex(16)
                payload = b''.join(f'--{boundary}\r\nContent-Disposition: form-data; name="{key}"\r\n\r\n{value}\r\n'.encode() for key,value in data.items())
                payload += f'--{boundary}\r\nContent-Disposition: form-data; name="image"; filename="banner.png"\r\nContent-Type: image/png\r\n\r\n'.encode() + image + f'\r\n--{boundary}--\r\n'.encode()
                headers['Content-Type'] = 'multipart/form-data; boundary=' + boundary
            else: payload = urllib.parse.urlencode(data).encode()
        try: response = self.opener.open(urllib.request.Request(BASE+path, data=payload, headers=headers), timeout=15)
        except urllib.error.HTTPError as error: response = error
        body = response.read().decode(errors='replace')
        token = re.search(r'name="csrf-token" content="([^"]+)"', body)
        if token: self.token = token[1]
        return response.status, body
    def login(self, role):
        self.request('/login')
        check(self.request('/login', {'email': role+'@example.invalid', 'password':'TestOnlyPassword!2026'}),303)
        self.request('/dashboard')
def check(result, status):
    assert result[0] == status, (status, result[0], result[1][-400:])
    return result[1]

admin, teacher, guest = Client(), Client(), Client()
admin.login('admin'); teacher.login('teacher'); guest.request('/')
check(guest.request('/admin/banners'),303)
check(teacher.request('/admin/banners'),403)
check(teacher.request('/admin/banners',{}),403)
check(admin.request('/admin/banners',{},csrf=False),403)
check(admin.request('/admin/banners',{'title':'Missing image'}),422)
check(admin.request('/admin/banners',{'title':'Invalid image'},b'<svg onload="alert(1)"/>'),422)
image = base64.b64decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII=')
ids=[]
try:
    for order in [20,10]:
        check(admin.request('/admin/banners', {'title':f'Banner test {order} <script>', 'active':1, 'sort_order':order, 'link_url':'/courses/1'},image),303)
        page=check(admin.request('/admin/banners'),200)
        ids.append(max(map(int,re.findall(r'id="banner-(\d+)"',page))))
    page=check(guest.request('/'),200)
    assert page.index(f'/media/banners/{ids[1]}') < page.index(f'/media/banners/{ids[0]}')
    assert '&lt;script&gt;' in page and 'data-carousel-next' in page
    for link in ['javascript:alert(1)','//example.com','/\\example.com','https://example.com\nfoo']:
        check(admin.request(f'/admin/banners/{ids[0]}/save',{'title':'Bad link','link_url':link}),422)
    check(teacher.request(f'/admin/banners/{ids[0]}/save',{'title':'No access'}),403)
    check(teacher.request(f'/admin/banners/{ids[0]}/delete',{}),403)
    check(admin.request(f'/admin/banners/{ids[0]}/save',{'title':'Hidden'}),303)
    check(guest.request(f'/media/banners/{ids[0]}'),404)
    check(admin.request(f'/media/banners/{ids[0]}'),200)
    page=check(guest.request('/'),200)
    assert f'/media/banners/{ids[0]}' not in page and 'data-carousel-next' not in page
    check(admin.request(f'/admin/banners/{ids[1]}/save',{'title':'Replaced','active':1,'sort_order':1},image),303)
    check(guest.request(f'/media/banners/{ids[1]}'),200)
finally:
    for banner_id in ids: check(admin.request(f'/admin/banners/{banner_id}/delete',{}),303)
page=check(guest.request('/'),200)
assert 'class="hero"' in page and 'data-carousel-next' not in page
check(admin.request(f'/admin/banners/{ids[0]}/save',{'title':'Deleted'}),404)
check(guest.request(f'/media/banners/{ids[0]}'),404)
print('PASS banner CRUD, ordering, replacement, visibility, fallback, permissions, CSRF, upload and URL validation')
