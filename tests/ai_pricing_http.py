"""BYOK HTTP security checks. Uses only isolated compose.test.yaml and mock provider."""
from urllib.request import Request, build_opener, HTTPCookieProcessor, HTTPRedirectHandler
from urllib.error import HTTPError
from urllib.parse import urlencode
from http.cookiejar import CookieJar
import json, re, secrets, subprocess

BASE='http://localhost:8090'
PASSWORD='TestOnlyPassword!2026'
checks=0
class NoRedirect(HTTPRedirectHandler):
    def redirect_request(self, req, fp, code, msg, headers, newurl): return None
class Client:
    def __init__(self): self.jar=CookieJar();self.opener=build_opener(HTTPCookieProcessor(self.jar),NoRedirect()); self.csrf=''
    def call(self,path,method='GET',body=None,token=True,origin=BASE,idem=None,form=False):
        headers={}
        if body is not None:
            if form:
                body=dict(body);body['_csrf']=self.csrf;raw=urlencode(body).encode()
            else:
                raw=json.dumps(body,ensure_ascii=False).encode();headers['Content-Type']='application/json'
                if token: headers['X-CSRF-Token']=self.csrf
                if origin is not None: headers['Origin']=origin
                headers['Idempotency-Key']=idem or secrets.token_hex(16)
        else: raw=None
        try: response=self.opener.open(Request(BASE+path,data=raw,method=method,headers=headers),timeout=20)
        except HTTPError as e: response=e
        text=response.read().decode();match=re.search(r'name="csrf-token" content="([^"]+)"',text)
        if match:self.csrf=match.group(1)
        try: data=json.loads(text)
        except ValueError: data=text
        return response.code,data,response.headers
    def login(self,email):
        self.call('/login');expect(self.call('/login','POST',{'email':email,'password':PASSWORD},form=True),303,'login '+email)
        self.call('/dashboard')
def expect(r,status,label):
    global checks
    assert r[0]==status, f'{label}: expected {status}, got {r[0]} {r[1]}'
    checks+=1;print('PASS',label);return r[1]
def yes(value,label):
    global checks
    assert value,label
    checks+=1;print('PASS',label)


admin=Client();admin.login('admin@example.invalid')
teacher=Client();teacher.login('teacher@example.invalid')
path='/api/v1/admin/ai-providers/pricing'
expect(Client().call(path),401,'anonymous cannot read admin pricing')
expect(teacher.call(path),403,'teacher cannot read admin pricing')
expect(teacher.call(path,'PUT',{'expected_revision':0,'rows':[]}),403,'teacher cannot change pricing')
slug='http-price-'+secrets.token_hex(4)
models={cap:[] for cap in ['text','embedding','speech','analytics','assistant']};models['text']=['http-price-model']
expect(admin.call('/api/v1/admin/ai-providers','POST',{'slug':slug,'name':'Price HTTP test','adapter':'openai_compatible','base_url':'https://api.example.com/v1','models':models,'enabled':True,'expected_revision':0}),201,'create priced provider')
config=expect(admin.call(path),200,'admin reads pricing')['data']
yes(any(r['provider']==slug and r['input'] is None for r in config['rows']),'missing model price visible')
payload={'expected_revision':config['revision'],'rows':[{'provider':slug,'model':'http-price-model','input':0.3,'output':2.5}]}
expect(admin.call(path,'PUT',payload,token=False),403,'pricing requires CSRF')
expect(admin.call(path,'PUT',payload,origin='https://evil.invalid'),403,'pricing requires trusted origin')
r=expect(admin.call(path,'PUT',payload),200,'admin saves model price')['data']
yes(any(r['provider']==slug and r['configured'] and r['output']==2.5 for r in r['rows']),'configured price returned')
expect(admin.call(path,'PUT',payload),409,'stale pricing revision rejected')
yes('ai-pricing-form' in expect(admin.call('/admin/ai-providers'),200,'admin page renders price editor'),'price inputs rendered')
print(f'Pricing HTTP checks passed: {checks}')
