"""HTTP integration tests. Only ever targets the isolated compose.test.yaml app."""
from urllib.request import Request, build_opener, HTTPCookieProcessor, HTTPRedirectHandler
from urllib.error import HTTPError
from urllib.parse import urlencode
from http.cookiejar import CookieJar
import json, re, secrets, time, sys

BASE='http://localhost:8090'
PASSWORD='TestOnlyPassword!2026'
checks=0
class NoRedirect(HTTPRedirectHandler):
    def redirect_request(self, req, fp, code, msg, headers, newurl): return None
class Client:
    def __init__(self): self.opener=build_opener(HTTPCookieProcessor(CookieJar()),NoRedirect()); self.csrf=''
    def request(self,path,data=None,files=None,token=True):
        headers={}
        if data is not None:
            data=dict(data)
            if token: data['_csrf']=self.csrf
            if files:
                boundary='vekec-'+secrets.token_hex(12); chunks=[]
                for k,v in data.items(): chunks.append(f'--{boundary}\r\nContent-Disposition: form-data; name="{k}"\r\n\r\n{v}\r\n'.encode())
                for field,(name,mime,content) in files.items(): chunks.append(f'--{boundary}\r\nContent-Disposition: form-data; name="{field}"; filename="{name}"\r\nContent-Type: {mime}\r\n\r\n'.encode()+content+b'\r\n')
                chunks.append(f'--{boundary}--\r\n'.encode()); payload=b''.join(chunks);headers['Content-Type']='multipart/form-data; boundary='+boundary
            else: payload=urlencode(data).encode()
        else: payload=None
        try: response=self.opener.open(Request(BASE+path,data=payload,headers=headers),timeout=15)
        except HTTPError as e: response=e
        body=response.read().decode('utf-8',errors='replace')
        match=re.search(r'name="csrf-token" content="([^"]+)"',body)
        if match:self.csrf=match.group(1)
        return response.code,body,response.headers
    def login(self,email):
        self.request('/login');r=self.request('/login',{'email':email,'password':PASSWORD});expect(r,303,'login '+email.split('@')[0]);self.request('/dashboard')
def expect(result,status,label):
    global checks
    if result[0]!=status: raise AssertionError(f'{label}: expected {status}, got {result[0]}: '+re.sub('<[^>]+>',' ',result[1])[-600:])
    checks+=1;print('PASS',label)
def assert_true(value,label):
    global checks
    if not value:raise AssertionError(label)
    checks+=1;print('PASS',label)
def id_from_redirect(result):return int(re.search(r'/(\d+)',result[2]['Location']).group(1))

guest=Client();expect(guest.request('/health'),200,'database health');expect(guest.request('/'),200,'public catalog');expect(guest.request('/admin'),303,'anonymous admin rejected')
expect(guest.request('/register',{'name':'No CSRF'},token=False),403,'CSRF required')
suffix=secrets.token_hex(4);email='student-'+suffix+'@example.invalid'
student=Client();student.request('/register')
expect(student.request('/register',{'name':'ผู้เรียนทดสอบ','email':email,'password':PASSWORD,'level':'public','role':'admin'}),303,'student self registration')
expect(student.request('/dashboard'),200,'student dashboard')
expect(student.request('/admin'),403,'self registration cannot escalate role')
expect(student.request('/teach'),403,'student cannot teach')
teacher=Client();teacher.login('teacher@example.invalid');other=Client();other.login('otherteacher@example.invalid');admin=Client();admin.login('admin@example.invalid')
course={'title':'หลักสูตรทดสอบ '+suffix,'code':'TEST-'+suffix,'description':'หลักสูตรใช้ทดสอบเส้นทางการเรียนครบวงจร','category':'ทดสอบระบบ','level':'all','hours':'2','sequential':'1','color':'teal'}
r=teacher.request('/teach/courses',course);expect(r,303,'teacher creates course');cid=id_from_redirect(r)
expect(teacher.request(f'/teach/{cid}'),200,'course editor')
expect(other.request(f'/teach/{cid}'),403,'other teacher cannot manage course')
expect(other.request(f'/teach/{cid}/students'),403,'other teacher cannot read students')
expect(teacher.request(f'/teach/{cid}/status',{'status':'published'}),422,'empty course cannot publish')
def lesson(kind,title,extra=None,files=None):
    d={'kind':kind,'title':title,'body':'เนื้อหาทดสอบ','pass_score':'60','time_limit':'1','max_attempts':'2'};d.update(extra or {})
    r=teacher.request(f'/teach/{cid}/lessons',d,files);expect(r,303,'create '+kind)
    html=teacher.request(f'/teach/{cid}')[1];ids=re.findall(r'href="/learn/(\d+)"',html);return int(ids[-1])
article=lesson('article','บทเรียนแรก <script>alert(1)</script>')
quiz=lesson('quiz','แบบทดสอบ')
assignment=lesson('assignment','ใบงาน')
expect(teacher.request(f'/teach/{cid}/status',{'status':'published'}),422,'empty quiz prevents publishing')
q={'prompt':'1 + 1 = ?','options':['1','2','3','4'],'answer':1}
expect(teacher.request(f'/teach/quizzes/{quiz}/import',{}, {'questions':('quiz.json','application/json',json.dumps([q]).encode())}),303,'JSON exam import')
csv='prompt,option_a,option_b,option_c,option_d,answer\n2 + 2 = ?,1,2,3,4,3\n'.encode()
expect(teacher.request(f'/teach/quizzes/{quiz}/import',{}, {'questions':('quiz.csv','text/csv',csv)}),303,'CSV exam import')
bad=[q,dict(q,answer=9)]
expect(teacher.request(f'/teach/quizzes/{quiz}/import',{}, {'questions':('bad.json','application/json',json.dumps(bad).encode())}),422,'invalid import rejected atomically')
assert_true(teacher.request(f'/teach/quizzes/{quiz}')[1].count('class="question-preview"')==2,'failed import leaves question count unchanged')
expect(teacher.request(f'/teach/{cid}/status',{'status':'published'}),303,'publish ready course')
preview=guest.request(f'/courses/{cid}');expect(preview,200,'preview syllabus before enrolling');assert_true('&lt;script&gt;' in preview[1] and '<script>alert(1)</script>' not in preview[1],'course titles escaped')
expect(student.request(f'/courses/{cid}/enroll',{}),303,'enroll student')
expect(student.request(f'/courses/{cid}/enroll',{}),303,'duplicate enrollment idempotent')
expect(student.request(f'/learn/{quiz}'),403,'sequential quiz locked')
expect(student.request(f'/courses/{cid}/certificate',{}),422,'premature certificate rejected')
expect(teacher.request(f'/teach/{cid}/lessons',{'kind':'article','title':'late','body':'late'}),409,'curriculum frozen once enrolled')
expect(student.request(f'/learn/{article}'),200,'article accessible')
expect(student.request(f'/learn/{article}/complete',{}),303,'article completion')
expect(student.request(f'/learn/{quiz}'),200,'quiz unlocks after article')
expect(student.request(f'/learn/{quiz}/quiz/start',{}),303,'start timed quiz')
page=student.request(f'/learn/{quiz}');expect(page,200,'quiz questions render')
aid=int(re.search(r'action="/quiz/(\d+)/submit"',page[1]).group(1));qids=list(dict.fromkeys(re.findall(r'name="answers\[(\d+)\]"',page[1])))
assert_true('correct-option' not in page[1] and 'data-answer' not in page[1],'quiz does not expose answer key')
expect(student.request(f'/quiz/{aid}/submit',{f'answers[{qids[0]}]':'1',f'answers[{qids[1]}]':'3'}),303,'server grades correct answers')
expect(student.request(f'/quiz/{aid}/submit',{}),409,'duplicate quiz submission rejected')
expect(student.request(f'/learn/{assignment}'),200,'assignment unlocks after pass')
expect(student.request(f'/learn/{assignment}/submit',{'body':'งานทดสอบ'}),303,'student submits assignment')
page=teacher.request(f'/teach/{cid}/students');expect(page,200,'teacher progress dashboard')
sid=int(re.search(r'action="/teach/submissions/(\d+)/grade"',page[1]).group(1))
expect(other.request(f'/teach/submissions/{sid}/grade',{'score':'100'}),403,'other teacher cannot grade')
expect(teacher.request(f'/teach/submissions/{sid}/grade',{'score':'101'}),422,'out of range grade rejected')
expect(teacher.request(f'/teach/submissions/{sid}/grade',{'score':'85','feedback':'ผ่านกิจกรรม'}),303,'teacher grades assignment')
r=student.request(f'/courses/{cid}/certificate',{});expect(r,303,'issue certificate after passing');cert=r[2]['Location']
expect(student.request(cert),200,'printable certificate renders')
r2=student.request(f'/courses/{cid}/certificate',{});assert_true(r2[2]['Location']==cert,'certificate issuance idempotent')
expect(guest.request(cert.replace('/certificate/','/verify/')),200,'public certificate verification')
expect(other.request(cert),403,'other user cannot download private certificate')
expect(teacher.request(f'/teach/submissions/{sid}/grade',{'score':'10'}),409,'cannot change grade after certification')
expect(teacher.request(f'/teach/{cid}/register',{'name':'ผู้เรียนที่ครูลงทะเบียน','email':'created-'+suffix+'@example.invalid','password':PASSWORD,'level':'public'}),303,'teacher creates and enrolls learner')
expect(teacher.request(f'/teach/{cid}/enroll',{'email':email}),303,'teacher enrolls existing account')
expect(teacher.request(f'/teach/{cid}/status',{'status':'archived'}),303,'archive course')
expect(student.request(f'/learn/{article}'),200,'existing learner retains archived course access')
expect(guest.request(f'/courses/{cid}'),404,'archived course hidden publicly')
expect(admin.request('/admin'),200,'administrator dashboard')
expect(student.request('/profile'),200,'profile renders')
expect(student.request('/certificates'),200,'certificate collection renders')
expect(guest.request('/auth/thaid'),503,'disabled ThaID fails closed')
expect(guest.request('/.env'),404,'environment file not exposed')
expect(guest.request('/storage/test.php'),404,'private storage not exposed')

# Required MP4 progress: metadata is server-derived; forged completion cannot unlock activities.
r=teacher.request('/teach/courses',dict(course,code='VID-'+suffix,title='Video test '+suffix));vidcid=id_from_redirect(r)
from pathlib import Path
mp4=Path('tests/.artifacts/clip.mp4').read_bytes()
expect(teacher.request(f'/teach/{vidcid}/lessons',{'kind':'video','title':'MP4 test','body':'','require_watch':'1','duration_seconds':'1'}, {'video':('clip.mp4','video/mp4',mp4)}),303,'MP4 upload and server duration')
html=teacher.request(f'/teach/{vidcid}')[1];vid=int(re.findall(r'href="/learn/(\d+)"',html)[0])
expect(teacher.request(f'/teach/{vidcid}/status',{'status':'published'}),303,'publish video course')
expect(student.request(f'/courses/{vidcid}/enroll',{}),303,'enroll video course')
expect(student.request(f'/learn/{vid}/complete',{}),422,'manual video completion rejected')
expect(guest.request(f'/media/{vid}/video'),303,'anonymous media access rejected')
start=student.request(f'/api/lessons/{vid}/video/start',{});expect(start,200,'video tracking starts');video_token=json.loads(start[1])['token']
r=student.request(f'/api/lessons/{vid}/video/tick',{'token':video_token,'position':'8'});assert_true(json.loads(r[1])['reset'] and not json.loads(r[1])['completed'],'forged instant watch rejected')
expect(student.request(f'/courses/{vidcid}/certificate',{}),422,'video certificate remains locked')
time.sleep(4);r=student.request(f'/api/lessons/{vid}/video/tick',{'token':video_token,'position':'4'});assert_true(not json.loads(r[1])['completed'],'partial video remains incomplete')
time.sleep(4);r=student.request(f'/api/lessons/{vid}/video/tick',{'token':video_token,'position':'8'});assert_true(json.loads(r[1])['completed'],'video completes after elapsed watching')
expect(student.request(f'/courses/{vidcid}/certificate',{}),303,'video certificate eligible after completion')
from category_checks import run as category_checks
category_checks(admin,teacher,student,guest,course,suffix,expect,assert_true,id_from_redirect)
from pagination_http_checks import run as pagination_checks
pagination_checks(admin,teacher,student,guest,course,suffix,vidcid,expect,assert_true,id_from_redirect)
from editor_http_checks import run as editor_checks
editor_checks(teacher,other,student,guest,course,suffix,expect,assert_true,id_from_redirect)
from user_http_checks import run as user_checks
user_checks(admin,teacher,student,guest,suffix,expect,assert_true)
from certificate_http_checks import run as certificate_checks
certificate_checks(admin,teacher,other,student,guest,course,suffix,expect,assert_true,id_from_redirect)
from certificate_emblem_checks import run as emblem_checks
emblem_checks(admin,teacher,other,student,guest,course,suffix,expect,assert_true,id_from_redirect)
print(f'\nAll {checks} HTTP integration checks passed.')
