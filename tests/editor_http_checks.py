"""Rich text, image permissions and answer disclosure checks in isolated test app."""
import base64, json, re

def run(teacher, other, student, guest, course, suffix, expect, assert_true, id_from_redirect):
    png = base64.b64decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aH3sAAAAASUVORK5CYII=')
    def new_course(code):
        result = teacher.request('/teach/courses', dict(course, code=code+'-'+suffix, title='Rich editor '+code+' '+suffix))
        expect(result,303,'create editor test course '+code)
        return id_from_redirect(result)
    cid = new_course('RICH')
    def upload(client, course_id=cid, content=png, name='photo.png', mime='image/png', token=True):
        return client.request(f'/teach/{course_id}/images', {}, {'image':(name,mime,content)}, token=token)
    def image():
        result=upload(teacher); expect(result,200,'upload valid private PNG')
        assert_true(result[2].get('Content-Type','').startswith('application/json'),'image response is JSON')
        url=json.loads(result[1])['url']; assert_true(bool(re.fullmatch(r'/media/images/[a-f0-9]{40}\.png',url)),'image gets random canonical URL')
        return url
    result=upload(teacher,token=False); expect(result,403,'image upload requires CSRF'); assert_true('error' in json.loads(result[1]),'CSRF image error is JSON')
    expect(upload(other),403,'other teacher cannot upload to course')
    expect(upload(student),403,'student cannot upload teaching images')
    expect(upload(guest),303,'guest cannot upload teaching images')
    expect(upload(teacher,content=b'not an image'),422,'fake PNG rejected')
    expect(upload(teacher,content=b'<svg xmlns="http://www.w3.org/2000/svg"></svg>',name='bad.svg',mime='image/svg+xml'),422,'SVG image rejected')
    expect(upload(teacher,content=png+b'0'*(5*1024*1024)),422,'oversized image rejected')
    body_image, question_image, explanation_image, orphan = image(),image(),image(),image()
    expect(teacher.request(orphan),200,'owner can preview unattached image')
    expect(other.request(orphan),403,'other teacher cannot view private image')
    expect(guest.request(orphan),303,'guest cannot view image')
    expect(student.request(body_image),403,'unenrolled learner cannot view image')
    def add_lesson(kind, title, body, format='html'):
        result=teacher.request(f'/teach/{cid}/lessons',{'kind':kind,'title':title,'body':body,'body_format':format,'pass_score':'60','time_limit':'5','max_attempts':'2'})
        expect(result,303,'create '+kind+' with '+format+' content')
        return int(re.findall(r'href="/learn/(\d+)"',teacher.request(f'/teach/{cid}')[1])[-1])
    first=add_lesson('article','Legacy content','<strong>literal legacy</strong>','text')
    assignment=add_lesson('assignment','Illustrated worksheet',f'<h2>Worksheet title</h2><p><strong>instructions</strong></p><img src="{body_image}" alt="diagram" onerror="evil()"><script>leak()</script>')
    quiz=add_lesson('quiz','Illustrated questions','<p>Read each question.</p>')
    page=teacher.request(f'/learn/{assignment}')[1]
    assert_true('<h2>Worksheet title</h2>' in page and f'src="{body_image}"' in page and 'onerror=' not in page and 'leak()' not in page,'worksheet persists formatting and image and strips XSS')
    assert_true('&lt;strong&gt;literal legacy&lt;/strong&gt;' in teacher.request(f'/learn/{first}')[1],'legacy lesson HTML stays literal')
    prompt=f'<p><strong>Illustrated prompt</strong></p><img src="{question_image}">'
    question={'prompt':prompt,'option_a':f'<img src="{question_image}" alt="answer A">','option_b':'<p>Option B</p>','option_c':'<p>Option C</p>','option_d':'<p>Option D</p>','answer':'0','explanation':f'<h3>SECRET_REVIEW_{suffix}</h3><img src="{explanation_image}">','content_format':'html'}
    expect(teacher.request(f'/teach/quizzes/{quiz}/questions',dict(question,option_d='<p><br></p>')),422,'empty rich answer rejected')
    expect(teacher.request(f'/teach/quizzes/{quiz}/questions',question),303,'rich question accepts image-only answer')
    editor_page=teacher.request(f'/teach/quizzes/{quiz}')[1]
    assert_true(editor_page.count('class="rich-editor"')==6 and '/assets/rich-editor.js' in editor_page,'all six question fields use local editor')
    assert_true(f'SECRET_REVIEW_{suffix}' in editor_page and '<strong>Illustrated prompt</strong>' in editor_page,'teacher sees formatted question and explanation')
    othercid=new_course('FOREIGN')
    foreign=upload(teacher,course_id=othercid); expect(foreign,200,'upload in second course'); foreign_url=json.loads(foreign[1])['url']
    expect(teacher.request(f'/teach/quizzes/{quiz}/questions',dict(question,prompt=f'<img src="{foreign_url}">')),422,'cross-course question image rejected')
    expect(teacher.request(f'/teach/{cid}/lessons',{'kind':'article','title':'foreign','body':f'<img src="{foreign_url}">','body_format':'html'}),422,'cross-course lesson image rejected')
    csv=f'prompt,option_a,option_b,option_c,option_d,answer,explanation,content_format\n"<p>CSV rich prompt</p>","<p>A</p>","<p>B</p>","<p>C</p>","<p>D</p>",0,"<p>CSV explanation</p>",html\n'.encode()
    expect(teacher.request(f'/teach/quizzes/{quiz}/import',{}, {'questions':('rich.csv','text/csv',csv)}),303,'extended CSV imports HTML and explanation')
    expect(teacher.request(f'/teach/{cid}/status',{'status':'published'}),303,'publish illustrated course')
    expect(student.request(f'/courses/{cid}/enroll',{}),303,'enroll illustrated course')
    expect(upload(teacher),409,'curriculum image upload locks after enrollment')
    expect(student.request(body_image),403,'locked worksheet image is private')
    expect(student.request(orphan),403,'unattached image stays private after enrollment')
    expect(student.request(f'/learn/{first}/complete',{}),303,'finish preceding article')
    result=student.request(body_image); expect(result,200,'unlocked worksheet image is readable')
    assert_true(result[2].get('Content-Type')=='image/png' and result[2].get('Content-Disposition','').startswith('inline'),'images have inline image MIME')
    expect(student.request(f'/learn/{assignment}/submit',{'body':'completed worksheet'}),303,'submit illustrated worksheet')
    page=teacher.request(f'/teach/{cid}/students')[1]; submission=int(re.search(r'action="/teach/submissions/(\d+)/grade"',page).group(1))
    expect(teacher.request(f'/teach/submissions/{submission}/grade',{'score':'100'}),303,'grade illustrated worksheet')
    expect(student.request(question_image),403,'question images unavailable before attempt')
    expect(student.request(explanation_image),403,'explanation image unavailable before attempt')
    for attempt_number in [1,2]:
        expect(student.request(f'/learn/{quiz}/quiz/start',{}),303,'start rich quiz attempt '+str(attempt_number))
        page=student.request(f'/learn/{quiz}')[1]
        assert_true(f'SECRET_REVIEW_{suffix}' not in page and explanation_image not in page,'active quiz does not expose explanation')
        assert_true('<strong>Illustrated prompt</strong>' in page and f'src="{question_image}"' in page,'learner question formatting and image render')
        expect(student.request(question_image),200,'question image available in active attempt')
        expect(student.request(explanation_image),403,'explanation image hidden during attempt')
        aid=int(re.search(r'action="/quiz/(\d+)/submit"',page).group(1)); qids=list(dict.fromkeys(re.findall(r'name="answers\[(\d+)\]"',page)))
        answer='1' if attempt_number==1 else '0'
        expect(student.request(f'/quiz/{aid}/submit',{f'answers[{qid}]':answer for qid in qids}),303,'submit rich quiz attempt '+str(attempt_number))
        if attempt_number==1:
            assert_true(f'SECRET_REVIEW_{suffix}' not in student.request(f'/learn/{quiz}')[1],'failed retry keeps explanation hidden')
            expect(student.request(explanation_image),403,'failed retry cannot fetch explanation image')
    review=student.request(f'/learn/{quiz}')[1]
    assert_true(f'SECRET_REVIEW_{suffix}' in review and explanation_image in review and 'CSV explanation' in review,'pass reveals formatted explanation and imported explanation')
    expect(student.request(explanation_image),200,'review image unlocked after passing')
    expect(student.request(f'/learn/{quiz}/quiz/start',{}),409,'cannot retake after answer key disclosed')
    return cid, quiz
