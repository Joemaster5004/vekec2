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

teacher=Client();teacher.login('teacher@example.invalid')
other=Client();other.login('otherteacher@example.invalid')
admin=Client();admin.login('admin@example.invalid')
guest=Client();expect(guest.call('/api/v1/me/ai-credentials'),401,'anonymous API denied')
expect(guest.call('/teach/ai-connections'),303,'anonymous UI redirected')
expect(teacher.call('/teach/ai-connections'),200,'teacher connection UI')
caps=expect(teacher.call('/api/v1/me/ai-capabilities'),200,'capabilities')['data']
yes(caps['encryption_ready'] and 'mock' in caps['providers'],'isolated encryption and provider ready')
key='test-valid-'+secrets.token_hex(20)
payload={'provider':'mock','label':'การเชื่อมต่อทดสอบ <script>','secret':key}
path='/api/v1/me/ai-credentials'
expect(teacher.call(path,'POST',payload,token=False),403,'POST CSRF enforced')
expect(teacher.call(path,'POST',payload,origin='https://evil.invalid'),403,'cross-origin denied')
expect(teacher.call(path,'POST',payload,origin=None),403,'missing origin denied')
expect(teacher.call(path,'POST',dict(payload,provider='http://127.0.0.1')),422,'arbitrary provider host denied')
expect(teacher.call(path,'POST',dict(payload,secret='key\r\nHeader:value')),422,'secret header injection denied')
expect(teacher.call(path,'POST',dict(payload,secret='tiny')),422,'short secret cannot be fully exposed in key hint')
idem=secrets.token_hex(16)
r=teacher.call(path,'POST',payload,idem=idem);c=expect(r,201,'create encrypted credential')['data'];cid=c['credential_id']
yes(key not in json.dumps(r[1]) and 'ciphertext' not in json.dumps(r[1]),'creation never reveals secret or ciphertext')
yes(r[2].get('Cache-Control')=='no-store','secret management responses not cached')
replay=expect(teacher.call(path,'POST',payload,idem=idem),201,'create replay')['data']
yes(replay['credential_id']==cid,'replay does not create duplicate key')
expect(teacher.call(path,'POST',dict(payload,secret=key+'different'),idem=idem),409,'secret payload conflict on duplicate key')
base=path+'/'+cid
for action,method,body in [('verify','POST',{'allow_billable_test':False,'confirmed_provider_revision':1}),('secret','PUT',{'secret':key,'expected_version':1}),('revoke','POST',{})]:
    expect(other.call(base+'/'+action,method,body),404,'other teacher cannot '+action)
expect(admin.call(base+'/verify','POST',{'allow_billable_test':False,'confirmed_provider_revision':1}),404,'administrator cannot use teacher key')
expect(teacher.call(base),404,'no reveal endpoint')
expect(teacher.call(base+'/verify','POST',{'allow_billable_test':True}),422,'billable verification disabled')
v=expect(teacher.call(base+'/verify','POST',{'allow_billable_test':False,'confirmed_provider_revision':1}),200,'verify mock key without charges')['data']
yes(v['credential']['status']=='verified' and not v['billable_test_performed'],'verification metadata only')
listed=expect(teacher.call(path),200,'list own metadata')['data']
yes(any(x['credential_id']==cid for x in listed) and key not in json.dumps(listed),'owner list hides secret')
yes(cid not in [x['credential_id'] for x in expect(other.call(path),200,'other teacher lists own keys')['data']],'credential lists isolated')
invalid=expect(teacher.call(path,'POST',{'provider':'mock','label':'Invalid key test','secret':'invalid-test-key'}),201,'save unverified invalid key')['data']['credential_id']
expect(teacher.call(path+'/'+invalid+'/verify','POST',{'allow_billable_test':False,'confirmed_provider_revision':1}),422,'provider rejects invalid key')
yes(next(x for x in teacher.call(path)[1]['data'] if x['credential_id']==invalid)['status']=='invalid','authoritative invalid status persists after failed verification')
suffix=secrets.token_hex(4)
course={'title':'BYOK '+suffix,'code':'BYOK-'+suffix,'description':'ทดสอบ BYOK','category':'ทดสอบระบบ','level':'all','hours':'1','color':'teal'}
r=teacher.call('/teach/courses','POST',course,form=True);expect(r,303,'create course for billing')
courseid=int(re.search(r'/(\d+)',r[2]['Location']).group(1));billing=f'/api/v1/courses/{courseid}/ai/billing'
b={'capability':'text','payer':'teacher','credential_id':cid,'model':'mock-model','monthly_budget':1,'currency':'USD'}
config={'expected_revision':0,'bindings':[b],'confirmed_charge_acceptance':True}
expect(other.call(billing,'PUT',config),403,'other teacher cannot assign billing')
expect(teacher.call(billing,'PUT',dict(config,confirmed_charge_acceptance=False)),422,'charge acceptance required')
expect(teacher.call(billing,'PUT',dict(config,bindings=[dict(b,model='unknown-model')])),422,'unapproved model rejected')
expect(teacher.call(billing,'PUT',dict(config,bindings=[b,b])),422,'duplicate capabilities rejected')
expect(teacher.call(billing,'PUT',dict(config,bindings=[dict(b,allow_student_usage=True,student_daily_request_limit=5)])),422,'legacy student flags rejected')
otherkey=expect(other.call(path,'POST',{'provider':'mock','label':'other','secret':'test-valid-other'}),201,'create other teacher key')['data']['credential_id']
expect(other.call(path+'/'+otherkey+'/verify','POST',{'allow_billable_test':False,'confirmed_provider_revision':1}),200,'verify other key')
expect(teacher.call(billing,'PUT',dict(config,bindings=[dict(b,credential_id=otherkey)])),404,'cannot bind other teacher key')
expect(teacher.call(billing,'PUT',dict(config,bindings=[{k:v for k,v in dict(b,payer='institution').items() if k!='credential_id'}])),403,'institution funds require policy grant')
saved=expect(teacher.call(billing,'PUT',config),200,'save own course payer')['data']
yes(saved['revision']==1,'billing revision advanced')
expect(teacher.call(billing,'PUT',config),409,'stale billing revision rejected')
assistant=dict(b,capability='assistant')
expect(teacher.call(billing,'PUT',dict(config,expected_revision=1,bindings=[b,assistant])),200,'configure teacher-only assistant')
expect(teacher.call(base+'/secret','PUT',{'expected_version':1,'secret':'test-valid-new'},token=False),403,'PUT CSRF enforced')
expect(teacher.call(base+'/secret','PUT',{'expected_version':1,'secret':'test-valid-new'}),200,'rotate credential')
expect(teacher.call(base+'/secret','PUT',{'expected_version':1,'secret':'test-valid-again'}),409,'stale credential version denied')
yes(next(x for x in teacher.call(path)[1]['data'] if x['credential_id']==cid)['status']=='unverified','rotation requires fresh verification')
expect(teacher.call(base+'/verify','POST',{'allow_billable_test':False,'confirmed_provider_revision':1}),200,'verify new key')
expect(teacher.call(base+'/revoke','POST',{}),200,'revoke in LMS')
expect(teacher.call(base+'/verify','POST',{'allow_billable_test':False,'confirmed_provider_revision':1}),409,'revoked key cannot verify')
expect(teacher.call(base+'/secret','PUT',{'expected_version':2,'secret':'test-valid-revive'}),409,'revoked key cannot revive')
expect(teacher.call(f'/api/v1/courses/{courseid}/ai/usage'),200,'usage summary available')
expect(other.call(f'/api/v1/courses/{courseid}/ai/usage'),403,'usage scope enforced')
sid=next(c.value for c in teacher.jar if c.name=='vekec_session')
subprocess.check_call(['docker','compose','-f','compose.test.yaml','exec','-T','web','php','tests/ai_expire_session.php',sid],stdout=subprocess.DEVNULL)
expect(teacher.call(path),200,'metadata remains readable after fresh-auth window')
stale=expect(teacher.call(path,'POST',payload),403,'old session cannot change credentials')
yes(stale['error']['code']=='REAUTH_REQUIRED','old session requires reauthentication')
expect(teacher.call('/api/v1/me/ai-reauth','POST',{'password':'wrong-password'}),403,'wrong password reauth rejected')
expect(teacher.call('/api/v1/me/ai-reauth','POST',{'password':PASSWORD}),200,'reauthentication succeeds')
student=Client();student.call('/register')
expect(student.call('/register','POST',{'name':'BYOK student','email':'byok-'+suffix+'@example.invalid','password':PASSWORD,'level':'public'},form=True),303,'create student')
expect(student.call(path),403,'student cannot read credential API')
expect(student.call('/teach/ai-connections'),403,'student cannot open teacher UI')

for target in ['/api/v1/me/ai-capabilities',billing,f'/api/v1/courses/{courseid}/ai/usage',f'/api/v1/courses/{courseid}/ai/conversations']:
    expect(student.call(target),403,'student denied all AI paths '+target)

print(f'BYOK HTTP checks passed: {checks}')
