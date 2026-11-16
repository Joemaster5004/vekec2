"""Certificate emblem upload, resizing and snapshot HTTP regression checks."""
import base64
import json
import re


def run(admin, teacher, other, student, guest, course, suffix, expect, assert_true, id_from_redirect):
    base = '/admin/certificate-design'
    png = base64.b64decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aH3sAAAAASUVORK5CYII=')
    design = {'institution': 'สถานศึกษาทดสอบตรา ' + suffix, 'heading': 'เกียรติบัตร',
              'subtitle': 'CERTIFICATE OF COMPLETION', 'intro': 'ให้ไว้เพื่อแสดงว่า',
              'completion': 'ได้ผ่านการเรียนรู้และการประเมินผลในหลักสูตร',
              'director_name': 'ผู้อำนวยการทดสอบ ' + suffix, 'director_position': 'ผู้อำนวยการสถานศึกษาทดสอบ',
              'style': 'classic', 'accent': '#375a39', 'signature_alignment': 'center',
              'signature_width': '20', 'show_hours': '1', 'emblem_size': '8.5', 'emblem_source': 'upload'}

    def save(data=design, client=admin, content=png, token=True):
        files = {'emblem': ('emblem.png', 'image/png', content)} if content is not None else None
        return client.request(base, data, files, token=token)

    def preview_url(url, client=admin, token=True):
        return client.request(base + '/emblem-preview', {'emblem_url': url}, token=token)

    def paper(code):
        return re.search(r'<article class="certificate-sheet[\s\S]*?</article>', student.request('/certificate/' + code)[1]).group(0)

    previous_code = re.search(r'href="/certificate/([a-f0-9]{32})"', student.request('/certificates')[1]).group(1)
    previous = paper(previous_code)
    expect(admin.request(base + '/emblem'), 404, 'old template has no custom emblem')
    page = admin.request(base)[1]
    assert_true('name="emblem"' in page and 'name="emblem_size"' in page and 'name="emblem_url"' in page, 'designer offers upload URL and emblem resize')
    expect(save(client=teacher), 403, 'teacher cannot replace emblem')
    expect(save(client=student), 403, 'student cannot replace emblem')
    expect(save(token=False), 403, 'emblem changes require CSRF')
    for client in [teacher, student]:
        expect(client.request(base + '/emblem'), 403, 'template emblem is restricted to admin')
        expect(preview_url('https://example.org/logo.png', client), 403, 'external emblem fetch restricted to admin')
    expect(guest.request(base + '/emblem'), 303, 'guest cannot access template emblem')
    result = preview_url('https://example.org/logo.png', token=False)
    expect(result, 403, 'external emblem preview requires CSRF')
    assert_true('error' in json.loads(result[1]), 'emblem preview errors are JSON')
    for value in ['2.9', '12.1', '3.55', 'NaN', 'INF', 'none']:
        expect(save(dict(design, emblem_size=value)), 422, 'invalid emblem size rejected')
    expect(save(dict(design, emblem_source='forged')), 422, 'invalid emblem source rejected')
    expect(save(content=None), 422, 'upload mode requires emblem file')
    expect(save(content=b'not an image'), 422, 'fake emblem image rejected')
    expect(save(content=b'<svg xmlns="http://www.w3.org/2000/svg"></svg>'), 422, 'SVG emblem rejected')
    expect(save(content=png + b'0' * 2097152), 422, 'oversized emblem rejected')
    expect(save(), 303, 'administrator uploads external emblem image')
    first_image = admin.request(base + '/emblem')
    expect(first_image, 200, 'stored emblem streams successfully')
    first_filename = first_image[2]['Content-Disposition']
    assert_true(first_image[2]['Content-Type'] == 'image/png', 'stored emblem has validated image MIME')
    saved = admin.request(base + '/preview')[1]
    assert_true('--emblem-size:8.5cqw' in saved and 'cert-large-emblem' in saved and 'src="/admin/certificate-design/emblem"' in saved, 'preview persists custom image and chosen size')

    keep = dict(design, emblem_source='keep')
    expect(save(dict(keep, emblem_size='12'), content=None), 303, 'existing emblem can be resized to maximum')
    assert_true('--emblem-size:12cqw' in admin.request(base + '/preview')[1], 'maximum emblem size persists')
    expect(save(dict(keep, emblem_size='3'), content=None), 303, 'existing emblem can be resized to minimum')
    assert_true('--emblem-size:3cqw' in admin.request(base + '/preview')[1], 'minimum emblem size persists')
    expect(save(keep, content=None), 303, 'resize restores preferred emblem size')
    assert_true(admin.request(base + '/emblem')[2]['Content-Disposition'] == first_filename, 'resize does not replace image')
    stale = dict(keep)
    stale.pop('emblem_size'); stale.pop('emblem_source')
    expect(save(stale, content=None), 303, 'older open form can still save template')
    assert_true('--emblem-size:8.5cqw' in admin.request(base + '/preview')[1], 'older form preserves current emblem size')
    invalid = save(dict(design, heading='', emblem_size='10'))
    expect(invalid, 422, 'invalid template rejects new emblem atomically')
    assert_true(admin.request(base + '/emblem')[2]['Content-Disposition'] == first_filename, 'failed template save preserves original emblem')

    for url in ['http://example.org/logo.png', 'https://localhost/logo.png', 'https://127.0.0.1/logo.png',
                'https://169.254.169.254/latest/meta-data/', 'https://2130706433/logo.png',
                'https://[::1]/logo.png', 'https://example.org:8443/logo.png',
                'https://user:password@example.org/logo.png', 'file:///etc/passwd',
                'data:image/png;base64,AAAA', 'https://example.org/logo.png#fragment']:
        expect(preview_url(url), 422, 'unsafe emblem URL rejected before download')
    expect(save(dict(keep, emblem_source='url', emblem_url='https://127.0.0.1/image.png'), content=None), 422, 'save enforces external URL restrictions')
    assert_true(admin.request(base + '/emblem')[2]['Content-Disposition'] == first_filename, 'failed remote download preserves existing image')

    result = teacher.request('/teach/courses', dict(course, title='Emblem course ' + suffix, code='EMBLEM-' + suffix))
    expect(result, 303, 'create course for emblem snapshot')
    cid = id_from_redirect(result)
    expect(teacher.request(f'/teach/{cid}/lessons', {'kind': 'article', 'title': 'Emblem lesson', 'body': 'Complete this lesson'}), 303, 'create emblem lesson')
    lid = int(re.search(r'href="/learn/(\d+)"', teacher.request(f'/teach/{cid}')[1]).group(1))
    expect(teacher.request(f'/teach/{cid}/status', {'status': 'published'}), 303, 'publish emblem course')
    expect(student.request(f'/courses/{cid}/enroll', {}), 303, 'enroll in emblem course')
    expect(student.request(f'/learn/{lid}/complete', {}), 303, 'complete emblem lesson')
    issued = student.request(f'/courses/{cid}/certificate', {})
    expect(issued, 303, 'issue certificate with uploaded emblem')
    code = issued[2]['Location'].split('/')[-1]
    original = paper(code)
    assert_true('--emblem-size:8.5cqw' in original and f'/certificate/{code}/emblem' in original, 'issued certificate snapshots emblem and size')
    image = student.request(f'/certificate/{code}/emblem')
    expect(image, 200, 'owner can load issued emblem')
    assert_true(image[2]['Content-Disposition'] == first_filename, 'issued emblem uses immutable uploaded file')
    expect(other.request(f'/certificate/{code}/emblem'), 403, 'another teacher cannot load private emblem')
    expect(guest.request(f'/certificate/{code}/emblem'), 303, 'guest cannot load private emblem')
    expect(guest.request(f'/verify/{code}/emblem'), 200, 'public certificate verification loads bound emblem')
    expect(guest.request('/verify/' + '0' * 32 + '/emblem'), 404, 'unknown certificate cannot expose emblem')
    expect(save(dict(design, emblem_size='12')), 303, 'replace emblem with a newly stored image')
    assert_true(admin.request(base + '/emblem')[2]['Content-Disposition'] != first_filename, 'replacement creates a different immutable file')
    assert_true(paper(code) == original and paper(previous_code) == previous, 'replacing and resizing emblem preserves both old certificate formats')
    expect(save(dict(keep, emblem_source='default'), content=None), 303, 'administrator restores default emblem')
    expect(admin.request(base + '/emblem'), 404, 'default emblem has no uploaded image endpoint')
    assert_true('class="cert-default-emblem" >' in admin.request(base + '/preview')[1], 'default award icon is visible again')
    assert_true(student.request(f'/certificate/{code}/emblem')[2]['Content-Disposition'] == first_filename, 'restoring default retains image for already-issued certificates')
    assert_true(student.request(f'/courses/{cid}/certificate', {})[2]['Location'] == '/certificate/' + code, 'repeat issuance retains emblem snapshot')
    expect(save(dict(keep, emblem_path='../private.png'), content=None), 303, 'posted storage path does not replace emblem')
    expect(admin.request(base + '/emblem'), 404, 'forged stored image path is ignored')
    expect(save(dict(design, emblem_size='12')), 303, 'leave maximum-size emblem in test environment for visual QA')
    log = admin.request('/admin?logs_per_page=50')[1]
    assert_true('certificate.emblem_uploaded' in log and 'certificate.emblem_removed' in log, 'emblem changes are audited')
