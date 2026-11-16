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
# Create an editable course through the ordinary teacher flow.
r=teacher.call('/teach/courses','POST',{'title':'AI HTTP test','code':'AI-HTTP','category':'ทดสอบระบบ','description':'Test only','level':'voc1','hours':1,'color':'teal'},form=True)
expect(r,303,'create course')
cid=int(re.search(r'/teach/(\d+)',r[2]['Location']).group(1))
path=f'/api/v1/courses/{cid}/ai/drafts'
expect(teacher.call(f'/teach/{cid}'),200,'editor with AI panel')
html=teacher.call(f'/teach/{cid}')[1]
yes('ai-draft-form' in html and 'ai-draft.js' in html,'AI controls and script rendered')
expect(teacher.call(path),409,'unconfigured AI gives actionable error')
c=expect(teacher.call('/api/v1/me/ai-credentials','POST',{'provider':'mock','label':'HTTP draft test','secret':'test-valid-http-draft'}),201,'create key')['data']
expect(teacher.call('/api/v1/me/ai-credentials/'+c['credential_id']+'/verify','POST',{'allow_billable_test':False,'confirmed_provider_revision':1}),200,'verify key')
b={'capability':'text','payer':'teacher','credential_id':c['credential_id'],'model':'mock-model','monthly_budget':1,'currency':'USD'}
expect(teacher.call(f'/api/v1/courses/{cid}/ai/billing','PUT',{'expected_revision':0,'bindings':[b],'confirmed_charge_acceptance':True}),200,'configure text')
expect(teacher.call(path),200,'generation config')
a={'kind':'quiz','topic':'วงจรไฟฟ้า','objectives':'','reference':'','instructions':'','count':2,'minutes':30,'difficulty':'mixed','mode':'individual','reference_lesson_id':0}
expect(teacher.call(path,'POST',a,token=False),403,'generation requires CSRF')
expect(teacher.call(path,'POST',a,origin='https://evil.invalid'),403,'generation requires origin')
expect(other.call(path,'POST',a),403,'other teacher cannot generate')
expect(teacher.call(path,'POST',dict(a,reference_lesson_id=999999999)),422,'foreign source rejected')
idem=secrets.token_hex(16)
r=expect(teacher.call(path,'POST',a,idem=idem),200,'generate quiz')['data']
r2=expect(teacher.call(path,'POST',a,idem=idem),200,'replay quiz')['data']
yes(r==r2 and len(r['questions'])==2,'replay returns same draft')
expect(teacher.call(path,'POST',dict(a,topic='changed'),idem=idem),409,'changed request cannot reuse key')
expect(teacher.call(f'/teach/{cid}/lessons','POST',{'kind':'quiz','title':'AI test quiz','body':'Instructions','pass_score':60,'time_limit':30,'max_attempts':3},form=True),303,'create quiz lesson')
html=teacher.call(f'/teach/{cid}')[1];lid=int(re.search(r'/teach/quizzes/(\d+)',html).group(1))
expect(teacher.call(f'/teach/quizzes/{lid}'),200,'question screen with AI controls')
qpath=f'/api/v1/quizzes/{lid}/ai-questions';savekey=secrets.token_hex(16)
expect(teacher.call(qpath,'POST',{'questions':r['questions']},idem=savekey),200,'save reviewed questions')
expect(teacher.call(qpath,'POST',{'questions':r['questions']},idem=savekey),200,'save replay')
html=teacher.call(f'/teach/quizzes/{lid}')[1]
yes('2 ข้อ' in html and '4 ข้อ' not in html,'save replay does not duplicate questions')
invalid=[dict(r['questions'][0]),dict(r['questions'][1],answer=5)]
expect(teacher.call(qpath,'POST',{'questions':invalid}),422,'invalid selection rejected atomically')
expect(other.call(qpath,'POST',{'questions':r['questions']}),403,'other teacher cannot save')
for kind in ['article','assignment']:
 d=expect(teacher.call(path,'POST',dict(a,kind=kind)),200,'generate '+kind)['data']
 expect(teacher.call(f'/teach/{cid}/lessons','POST',{'kind':kind,'title':d['title'],'body':d['body'],'body_format':'text','pass_score':60},form=True),303,'save '+kind+' with ordinary form')
html=teacher.call(f'/teach/{cid}')[1]
yes('บทเรียนต้นทาง' in html,'source lesson selector rendered')
# Student role denial using a test fixture account created via SQL only in isolated test DB.
student_email='ai-student-'+secrets.token_hex(8)+'@example.invalid'
# Use teacher registration flow to enroll a student and lock the curriculum.
expect(teacher.call(f'/teach/{cid}/status','POST',{'status':'published'},form=True),303,'publish test course')
expect(teacher.call(f'/teach/{cid}/register','POST',{'name':'AI student','email':student_email,'password':PASSWORD,'level':'voc1'},form=True),303,'enroll test student')
student=Client();student.login(student_email)
expect(student.call(path,'POST',a),403,'student AI denied')
expect(teacher.call(path,'POST',a),409,'enrolled course generation locked')
expect(teacher.call(qpath,'POST',{'questions':r['questions']}),409,'enrolled course AI save locked')
print(f'AI drafts HTTP checks passed: {checks}')
