"""Pagination checks using only the isolated integration suite's HTTP clients."""
from html import unescape
from urllib.parse import parse_qs, urlencode, urlsplit
import re


def run(admin, teacher, student, guest, course, suffix, vidcid, expect, assert_true, id_from_redirect):
    def post(client, path, data):
        result = client.request(path, data)
        if result[0] != 303:
            expect(result, 303, 'pagination fixture creation at ' + path)
        return result

    def get(client, path, query=None):
        result = client.request(path + ('?' + urlencode(query) if query else ''))
        if result[0] != 200:
            expect(result, 200, 'pagination page renders at ' + path)
        return result[1]

    def table_rows(html, index=0):
        bodies = re.findall(r'<tbody\b[^>]*>(.*?)</tbody>', html, re.S)
        if len(bodies) <= index:
            raise AssertionError('Missing expected paginated table ' + str(index))
        return [row for row in re.findall(r'<tr\b[^>]*>(.*?)</tr>', bodies[index], re.S)
                if 'table-empty' not in row]

    def category_rows(html):
        return re.findall(r'<article class="category-item"[^>]*>(.*?)</article>', html, re.S)

    def pager(html, page_key):
        for navigation in re.findall(r'<nav class="pagination"[^>]*>.*?</nav>', html, re.S):
            if 'aria-label="แบ่งหน้ารายการ ' + page_key + '"' in navigation:
                return navigation
        raise AssertionError('Missing pagination navigation for ' + page_key)

    course_ids = []
    for i in range(12):
        course_ids.append(id_from_redirect(post(teacher, '/teach/courses', dict(
            course, category='ทดสอบระบบ', title=f'Paging course {suffix} {i}', code=f'PG-{suffix}-{i}'))))
        post(teacher, f'/teach/{vidcid}/register', {
            'name': f'Paging {suffix} {i}', 'email': f'paging-{suffix}-{i}@example.invalid',
            'password': 'TestOnlyPassword!2026', 'level': 'public'})
        post(admin, '/admin/categories', {'name': f'Paging category {suffix} {i:02d}'})
    assert_true(len(set(course_ids)) == 12, 'pagination HTTP fixtures create twelve courses, students and categories')

    routes = [
        (teacher, '/teach', 'page', 'per_page', table_rows, 'teacher courses'),
        (teacher, f'/teach/{vidcid}/students', 'students_page', 'students_per_page', table_rows, 'course students'),
        (admin, '/admin/categories', 'page', 'per_page', category_rows, 'category management'),
    ]
    for client, path, page_key, size_key, records, label in routes:
        first_html = get(client, path)
        first = records(first_html)
        second = records(get(client, path, {page_key: 2}))
        assert_true(len(first) == 10 and second and not set(first).intersection(second), label + ' has ten first-page records and distinct second-page records')
        larger = records(get(client, path, {size_key: 25}))
        assert_true(10 < len(larger) <= 25 and larger[:10] == first, label + ' supports twenty-five records per page')
        last_html = get(client, path, {page_key: 999999})
        last = records(last_html)
        last_number = max(map(int, re.findall(r'aria-label="หน้า (\d+)"', pager(first_html, page_key))))
        assert_true(0 < len(last) <= 10 and
                    f'aria-current="page" aria-label="หน้า {last_number}"' in pager(last_html, page_key),
                    label + ' clamps an oversized page to a nonempty last page')
        invalid = get(client, path, {page_key + '[]': 2, size_key + '[]': 25})
        assert_true(records(invalid) == first, label + ' safely defaults malformed array page and size parameters')

    search = 'Paging ' + suffix
    first_html = get(admin, '/admin', {'q': search, 'logs_page': 2})
    users_first, logs_second = table_rows(first_html), table_rows(first_html, 1)
    users_second_html = get(admin, '/admin', {'q': search, 'users_page': 2, 'logs_page': 2})
    users_second = table_rows(users_second_html)
    assert_true(len(users_first) == 10 and len(users_second) == 2 and
                not set(users_first).intersection(users_second) and
                all(search in unescape(row) for row in users_first + users_second),
                'administrator search paginates exactly twelve matching users')
    assert_true(len(logs_second) == 10 and table_rows(users_second_html, 1) == logs_second,
                'changing the users page preserves the audit-log page')
    logs_third_html = get(admin, '/admin', {'q': search, 'logs_page': 3})
    assert_true(table_rows(logs_third_html) == users_first and len(table_rows(logs_third_html, 1)) == 10 and
                not set(logs_second).intersection(table_rows(logs_third_html, 1)),
                'changing the audit-log page preserves the users page')
    user_links = [parse_qs(urlsplit(unescape(href)).query)
                  for href in re.findall(r'href="([^"]+)"', pager(first_html, 'users_page'))]
    assert_true(user_links and all(query.get('q') == [search] and query.get('logs_page') == ['2']
                                  for query in user_links),
                'administrator pagination links retain the search and other table page')
    larger_html = get(admin, '/admin', {'q': search, 'users_per_page': 25, 'logs_per_page': 25})
    assert_true(len(table_rows(larger_html)) == 12 and len(table_rows(larger_html, 1)) == 25,
                'administrator users and logs independently support twenty-five records per page')
    last_html = get(admin, '/admin', {'q': search, 'users_page': 999999, 'logs_page': 999999})
    assert_true(table_rows(last_html) == users_second and 0 < len(table_rows(last_html, 1)) <= 10,
                'administrator users and logs clamp oversized pages to their final records')
    default_html = get(admin, '/admin', {'q': search})
    invalid_html = get(admin, '/admin', {'q': search, 'users_page': -1, 'users_per_page': 100000,
                                       'logs_page[]': 2, 'logs_per_page[]': 50})
    assert_true(table_rows(invalid_html) == table_rows(default_html) and
                table_rows(invalid_html, 1) == table_rows(default_html, 1),
                'administrator tables safely default invalid scalar and array pagination parameters')
    empty_html = get(admin, '/admin', {'q': 'no-pagination-match-' + suffix, 'users_page': 999999})
    assert_true(not table_rows(empty_html) and 'แสดง 0–0 จาก 0 รายการ' in pager(empty_html, 'users_page'),
                'empty user search displays a correct zero-record pagination summary')

    # A used category must offer destinations outside its current management page.
    category_html = get(admin, '/admin/categories', {'per_page': 50})
    all_categories = category_rows(category_html)
    category_pages = max(map(int, re.findall(r'aria-label="หน้า (\d+)"', pager(category_html, 'page'))))
    for category_page in range(2, category_pages + 1):
        all_categories.extend(category_rows(get(admin, '/admin/categories', {'per_page': 50, 'page': category_page})))
    used_index = next((i for i, row in enumerate(all_categories) if 'name="replacement_id"' in row), None)
    assert_true(used_index is not None, 'pagination fixtures include a used category for destination checks')
    used_page = used_index // 10 + 1
    current_categories = category_rows(get(admin, '/admin/categories', {'page': used_page}))
    visible_ids = set(re.findall(r'id="category-name-(\d+)"', ''.join(current_categories)))
    off_page_ids = set(re.findall(r'id="category-name-(\d+)"', ''.join(all_categories))) - visible_ids
    used_row = next(row for row in current_categories if 'name="replacement_id"' in row)
    destination_select = re.search(r'<select[^>]*name="replacement_id"[^>]*>(.*?)</select>', used_row, re.S).group(1)
    destinations = set(re.findall(r'<option value="(\d+)"', destination_select))
    assert_true(off_page_ids and off_page_ids.issubset(destinations),
                'category deletion retains destinations from other pagination pages')
    expect(student.request(f'/teach/{vidcid}/students?students_page=2'), 403,
           'pagination does not grant students access to teacher student records')
    expect(guest.request('/admin?users_page=2'), 303,
           'pagination does not grant anonymous access to administrator records')
