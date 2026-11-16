"""Certificate designer, private signatures and issued snapshot preservation."""
import base64
from html import unescape
import re


def run(admin, teacher, other, student, guest, course, suffix, expect, assert_true, id_from_redirect):
    base = '/admin/certificate-design'
    png = base64.b64decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aH3sAAAAASUVORK5CYII=')
    design = {'institution': 'สถานศึกษาทดสอบ ' + suffix, 'heading': 'เกียรติบัตร',
              'subtitle': 'CERTIFICATE OF COMPLETION', 'intro': 'ให้ไว้เพื่อแสดงว่า',
              'completion': 'ได้ผ่านการเรียนรู้และการประเมินผลในหลักสูตร',
              'director_name': 'ผู้อำนวยการทดสอบ ' + suffix, 'director_position': 'ผู้อำนวยการสถานศึกษาทดสอบ',
              'style': 'classic', 'accent': '#375a39', 'signature_alignment': 'center',
              'signature_width': '20', 'show_hours': '1'}

    def save(data=design, client=admin, image=None, token=True):
        files = {'signature': ('signature.png', 'image/png', image)} if image is not None else None
        return client.request(base, data, files, token=token)

    def paper(html):
        return re.search(r'<article class="certificate-(?:paper|sheet)[\s\S]*?</article>', html).group(0)

    legacy_code = re.search(r'href="/certificate/([a-f0-9]{32})"', student.request('/certificates')[1]).group(1)
    legacy = paper(student.request('/certificate/' + legacy_code)[1])
    assert_true('class="certificate-paper"' in legacy, 'previously issued certificate uses original layout')
    for path in [base, base + '/preview', base + '/signature']:
        expect(guest.request(path), 303, 'anonymous certificate administration rejected')
        expect(teacher.request(path), 403, 'teacher certificate administration rejected')
        expect(student.request(path), 403, 'student certificate administration rejected')
    expect(admin.request(base), 200, 'certificate designer renders')
    expect(admin.request(base + '/preview'), 200, 'certificate preview works before first template save')
    expect(admin.request(base + '/signature'), 404, 'missing template signature is not exposed')
    expect(save(token=False, image=png), 403, 'certificate template requires CSRF')
    expect(save(client=teacher, image=png), 403, 'teacher cannot upload a director signature')
    expect(save(client=student), 403, 'student cannot edit certificate template')

    for field, value in [('institution', ''), ('heading', 'x' * 41), ('director_name', ''),
                         ('director_position', 'ก' * 121), ('intro', '\u00a0'), ('subtitle', 'x' * 81),
                         ('completion', 'x' * 121), ('style', 'injected'), ('accent', '#fff; color:red'),
                         ('signature_alignment', 'absolute'), ('signature_width', '31'), ('signature_width', '11')]:
        expect(save(dict(design, **{field: value})), 422, 'invalid certificate field rejected: ' + field)
    expect(save(image=b'not an image'), 422, 'fake signature image rejected')
    expect(save(image=b'<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'), 422, 'SVG signature rejected')
    expect(save(image=png + b'0' * 2097152), 422, 'oversized signature rejected')
    expect(save(dict(design, remove_signature='1'), image=png), 422, 'cannot upload and remove signature simultaneously')

    expect(save(image=png), 303, 'administrator saves template with signature')
    current_signature = admin.request(base + '/signature')
    expect(current_signature, 200, 'administrator can preview stored signature')
    assert_true(current_signature[2].get('Content-Type') == 'image/png', 'signature is served as an image')
    first_filename = current_signature[2]['Content-Disposition']
    saved = admin.request(base)[1]
    assert_true(design['director_name'] in saved and 'name="remove_signature"' in saved, 'designer reloads director and offers signature removal')
    expect(save(dict(design, signature_width='24')), 303, 'template edit keeps signature when no new image is selected')
    assert_true(admin.request(base + '/signature')[2]['Content-Disposition'] == first_filename, 'unmodified signature retained across saves')
    preview = admin.request(base + '/preview')
    assert_true('PREVIEW' in preview[1] and 'ไม่ใช่เกียรติบัตรที่ออกจริง' in preview[1], 'sample and print preview are clearly marked')

    def issue(index):
        result = teacher.request('/teach/courses', dict(course, title='Certificate template course ' + suffix + '-' + str(index), code='CERT-' + suffix + '-' + str(index)))
        expect(result, 303, 'create course for certificate snapshot')
        cid = id_from_redirect(result)
        expect(teacher.request(f'/teach/{cid}/lessons', {'kind': 'article', 'title': 'Certificate lesson', 'body': 'Complete this lesson'}), 303, 'create snapshot course lesson')
        lid = int(re.search(r'href="/learn/(\d+)"', teacher.request(f'/teach/{cid}')[1]).group(1))
        expect(teacher.request(f'/teach/{cid}/status', {'status': 'published'}), 303, 'publish snapshot course')
        expect(student.request(f'/courses/{cid}/enroll', {}), 303, 'enroll in snapshot course')
        expect(student.request(f'/courses/{cid}/certificate', {}), 422, 'template does not bypass certificate eligibility')
        expect(student.request(f'/learn/{lid}/complete', {}), 303, 'complete snapshot lesson')
        result = student.request(f'/courses/{cid}/certificate', {})
        expect(result, 303, 'issue certificate using saved template')
        code = result[2]['Location'].split('/')[-1]
        return cid, code

    cid, code = issue(1)
    actual = student.request('/certificate/' + code)
    expect(actual, 200, 'learner certificate with custom design renders')
    first_paper = paper(actual[1])
    assert_true('cert-style-classic' in first_paper and design['director_name'] in first_paper and design['director_position'] in first_paper and design['institution'] in first_paper, 'issued certificate stores design institution and director')
    assert_true('PREVIEW' not in first_paper and '/certificate/' + code + '/signature' in first_paper, 'issued certificate has real code and bound signature route')
    private_signature = student.request('/certificate/' + code + '/signature')
    expect(private_signature, 200, 'owner can load issued signature')
    assert_true(private_signature[2]['Content-Disposition'] == first_filename, 'issued signature references original immutable image')
    expect(guest.request('/certificate/' + code + '/signature'), 303, 'anonymous private signature access rejected')
    expect(other.request('/certificate/' + code + '/signature'), 403, 'other user private signature access rejected')
    expect(admin.request('/certificate/' + code + '/signature'), 200, 'administrator can inspect issued signature')
    public_signature = guest.request('/verify/' + code + '/signature')
    expect(public_signature, 200, 'verification page can display issued signature')
    assert_true(public_signature[2]['Content-Disposition'] == first_filename, 'verification signature matches issued certificate')
    expect(guest.request('/verify/' + '0' * 32 + '/signature'), 404, 'unknown certificate cannot retrieve a signature')

    second = dict(design, director_name='ผู้อำนวยการคนใหม่ ' + suffix, director_position='รักษาการผู้อำนวยการ',
                  style='modern', heading='ประกาศนียบัตร', accent='#24476c', signature_alignment='right', signature_width='28')
    expect(save(second, image=png), 303, 'administrator changes director layout and signature')
    assert_true(admin.request(base + '/signature')[2]['Content-Disposition'] != first_filename, 'replacement signature has a new immutable filename')
    assert_true(paper(student.request('/certificate/' + code)[1]) == first_paper, 'template edits leave issued certificate unchanged')
    assert_true(student.request('/certificate/' + code + '/signature')[2]['Content-Disposition'] == first_filename, 'old signature still loads after replacement')
    assert_true(paper(student.request('/certificate/' + legacy_code)[1]) == legacy, 'legacy certificate stays unchanged after template save')
    result = student.request(f'/courses/{cid}/certificate', {})
    assert_true(result[2]['Location'] == '/certificate/' + code, 'repeat issuance preserves existing certificate and snapshot')
    _, second_code = issue(2)
    second_paper = paper(student.request('/certificate/' + second_code)[1])
    assert_true('cert-style-modern' in second_paper and second['director_name'] in second_paper and 'cert-signature-right' in second_paper, 'future certificate uses updated template and director')

    without_signature = dict(second, style='minimal', remove_signature='1')
    without_signature.pop('show_hours')
    expect(save(without_signature), 303, 'administrator removes signature and hides hours')
    expect(admin.request(base + '/signature'), 404, 'removed signature is absent from active template')
    assert_true('cert-style-minimal' in admin.request(base + '/preview')[1], 'minimal template preview renders')
    expect(student.request('/certificate/' + code + '/signature'), 200, 'removal retains original certificate signature')
    expect(student.request('/certificate/' + second_code + '/signature'), 200, 'removal retains replacement signature on issued certificate')
    _, third_code = issue(3)
    third_paper = paper(student.request('/certificate/' + third_code)[1])
    assert_true('class="cert-sheet-hours" hidden' in third_paper, 'new certificate respects hidden hours setting')
    expect(guest.request('/verify/' + third_code + '/signature'), 404, 'unsigned certificate has no image route')
    escaped = dict(second, heading='<script>alert(1)</script>', director_name='<img src=x onerror=alert(1)>', signature_path='../private.png')
    expect(save(escaped), 303, 'template text safely accepts literal markup')
    rendered = admin.request(base + '/preview')[1]
    assert_true('&lt;script&gt;alert(1)&lt;/script&gt;' in rendered and '<script>alert(1)</script>' not in rendered and '&lt;img' in rendered, 'template preview escapes all custom text')
    expect(admin.request(base + '/signature'), 404, 'posted filename cannot attach an arbitrary stored file')
    expect(save(second, image=png), 303, 'restore readable test template for visual inspection')
    audit = admin.request('/admin?logs_per_page=50')[1]
    assert_true(all(action in audit for action in ['certificate.design_saved', 'certificate.signature_uploaded', 'certificate.signature_removed']), 'template and signature changes are audited')
