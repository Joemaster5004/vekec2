"""Category CRUD checks run with the isolated HTTP integration clients."""
from html import unescape
from urllib.parse import urlencode
import re


def run(admin, teacher, student, guest, course, suffix, expect, assert_true, id_from_redirect):
    base = '/admin/categories'
    expect(guest.request(base), 303, 'anonymous category management rejected')
    expect(teacher.request(base), 403, 'teacher cannot manage categories')
    expect(student.request(base), 403, 'student cannot manage categories')
    expect(admin.request(base), 200, 'administrator category page renders')
    expect(admin.request(base, {'name': 'missing csrf'}, token=False), 403, 'category mutation requires CSRF')
    expect(teacher.request(base, {'name': 'teacher category'}), 403, 'teacher cannot create categories')
    expect(student.request(base, {'name': 'student category'}), 403, 'student cannot create categories')
    for name in ['', '   ', '\u00a0', 'ก' * 101]:
        expect(admin.request(base, {'name': name}), 422, 'invalid category name rejected')

    name = 'หมวดทดสอบ ' + suffix + ' <script>alert(1)</script>'
    target_name = 'หมวดปลายทาง ' + suffix

    def categories():
        html = category_pages()
        return {unescape(name): int(key) for key, name in re.findall(r'id="category-name-(\d+)"[^>]*value="([^"]*)"', html)}

    def category_pages():
        first = admin.request(base+'?per_page=50')[1]
        pages = max([1] + list(map(int, re.findall(r'aria-label="หน้า (\d+)"', first))))
        return first + ''.join(admin.request(base+f'?per_page=50&page={page}')[1] for page in range(2,pages+1))

    expect(admin.request(base, {'name': name}), 303, 'administrator creates category')
    expect(admin.request(base, {'name': target_name}), 303, 'administrator creates destination category')
    source_id, target_id = categories()[name], categories()[target_name]
    expect(admin.request(base, {'name': '  ' + target_name + '  '}), 422, 'duplicate trimmed category rejected')
    expect(admin.request(f'{base}/{source_id}/save', {'name': target_name}), 422, 'rename to existing category rejected')
    assert_true(name in categories(), 'duplicate rename preserves original category')
    page = category_pages()
    assert_true('&lt;script&gt;alert(1)&lt;/script&gt;' in page and '<script>alert(1)</script>' not in page, 'category names escaped in management')
    catalog = guest.request('/')[1]
    assert_true('&lt;script&gt;alert(1)&lt;/script&gt;' in catalog, 'new category appears in public filters safely')
    editor = teacher.request('/teach/new')[1]
    assert_true('<select name="category" required>' in editor and '&lt;script&gt;alert(1)&lt;/script&gt;' in editor, 'course form uses managed category dropdown')
    expect(teacher.request('/teach/courses', dict(course, category='missing-'+suffix)), 422, 'unmanaged course category rejected')

    course_ids = []
    for i in range(3):
        result = teacher.request('/teach/courses', dict(course, category=name, title='Category course '+suffix+' '+str(i), code='CAT-'+suffix+'-'+str(i)))
        expect(result, 303, 'course saved with managed category')
        course_ids.append(id_from_redirect(result))
    cid = course_ids[0]
    expect(teacher.request(f'/teach/{cid}/lessons', {'kind':'article','title':'Category test lesson','body':'Category test content'}), 303, 'category course lesson created')
    html = teacher.request(f'/teach/{cid}')[1]
    lid = int(re.search(r'href="/learn/(\d+)"', html).group(1))
    expect(teacher.request(f'/teach/{cid}/status', {'status':'published'}), 303, 'category course published')
    expect(teacher.request(f'/teach/{course_ids[2]}/status', {'status':'archived'}), 303, 'category course archived')
    expect(student.request(f'/courses/{cid}/enroll', {}), 303, 'learner enrolled before category rename')
    expect(student.request(f'/learn/{lid}/complete', {}), 303, 'learner progress recorded before category rename')

    renamed = 'หมวดที่แก้ไข ' + suffix
    expect(teacher.request(f'{base}/{source_id}/save', {'name':renamed}), 403, 'teacher cannot rename categories')
    expect(student.request(f'{base}/{source_id}/delete', {}), 403, 'student cannot delete categories')
    expect(admin.request(f'{base}/{source_id}/save', {'name':renamed}), 303, 'administrator renames used category')
    assert_true(categories()[renamed] == source_id, 'category id stable after rename')
    for course_id in course_ids:
        html = teacher.request(f'/teach/{course_id}')[1]
        assert_true(f'<option value="{renamed}" selected>' in html, 'rename updates draft published and archived courses')
    filtered = guest.request('/?' + urlencode({'category':renamed}))[1]
    assert_true(f'href="/courses/{cid}"' in filtered, 'public filter finds course after rename')
    expect(student.request(f'/courses/{cid}/certificate', {}), 303, 'rename preserves enrollment and completed progress')

    expect(admin.request(f'{base}/{source_id}/delete', {}), 409, 'used category requires destination')
    expect(admin.request(f'{base}/{source_id}/delete', {'replacement_id':source_id}), 422, 'cannot move category into itself')
    expect(admin.request(f'{base}/{source_id}/delete', {'replacement_id':999999999}), 422, 'invalid destination rejected')
    assert_true(renamed in categories(), 'failed category deletion preserves source')
    expect(admin.request(f'{base}/{source_id}/delete', {'replacement_id':target_id}), 303, 'delete used category moves courses atomically')
    assert_true(renamed not in categories(), 'deleted category removed from management')
    for course_id in course_ids:
        html = teacher.request(f'/teach/{course_id}')[1]
        assert_true(f'<option value="{target_name}" selected>' in html, 'course survives category deletion in destination')
    expect(student.request(f'/learn/{lid}'), 200, 'learning remains accessible after category move')
    expect(student.request(f'/courses/{cid}/certificate', {}), 303, 'existing certificate remains available after category move')
    expect(teacher.request('/teach/courses', dict(course, category=renamed)), 422, 'stale category selection rejected')
    empty = 'หมวดว่าง '+suffix
    expect(admin.request(base, {'name':empty}), 303, 'empty category created')
    empty_id = categories()[empty]
    expect(admin.request(f'{base}/{empty_id}/delete', {}), 303, 'empty category deleted without destination')
    assert_true(empty not in categories(), 'empty category removed')
    expect(admin.request(f'{base}/{empty_id}/save', {'name':'missing'}), 404, 'editing deleted category rejected')
    expect(admin.request(f'{base}/{empty_id}/delete', {}), 404, 'deleting missing category rejected')
