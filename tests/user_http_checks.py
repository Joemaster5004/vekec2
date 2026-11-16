"""Member management checks using the isolated integration suite's HTTP clients."""
import csv
from html import unescape
from io import StringIO
import json
import re
from urllib.parse import urlencode


def run(admin, teacher, student, guest, suffix, expect, assert_true):
    base = '/admin/users'
    password = 'TestOnlyPassword!2026'
    Client = type(admin)

    def lookup(email):
        html = admin.request('/admin?' + urlencode({'q': email, 'users_per_page': 50}))[1]
        for key, row in re.findall(r'<tr data-member-id="(\d+)">(.*?)</tr>', html, re.S):
            if email in [unescape(value) for value in re.findall(r'<small>(.*?)</small>', row, re.S)]:
                return int(key), row
        return None, ''

    def upload(rows=None, name='members.json', content=None, client=admin, token=True):
        payload = content if content is not None else json.dumps(rows, ensure_ascii=False).encode()
        return client.request(base + '/import', {}, {'members': (name, 'application/octet-stream', payload)}, token=token)

    member = {'name': 'สมาชิก <script>alert(1)</script> ' + suffix,
              'email': 'member-' + suffix + '@example.invalid', 'password': password,
              'role': 'student', 'level': 'public', 'active': '1'}

    for path in [base + '/new', base + '/import', base + '/1/edit', base + '/1/delete']:
        expect(guest.request(path), 303, 'anonymous member management blocked')
        expect(teacher.request(path), 403, 'teacher member management blocked')
        expect(student.request(path), 403, 'student member management blocked')
    expect(admin.request(base + '/new'), 200, 'member creation form renders')
    expect(admin.request(base + '/import'), 200, 'member import form renders')
    expect(admin.request(base, member, token=False), 403, 'member creation requires CSRF')
    expect(upload([member], token=False), 403, 'member import requires CSRF')
    for client in [teacher, student]:
        expect(client.request(base, member), 403, 'non-admin cannot create a member')
        expect(upload([member], client=client), 403, 'non-admin cannot import members')

    for field, value in [('name', ''), ('name', '\u00a0'), ('name', 'ก' * 181),
                         ('email', 'bad-email'), ('password', 'short'), ('password', 'x' * 73),
                         ('password', 'ก' * 25), ('role', 'owner'), ('level', 'all'), ('active', 'yes')]:
        expect(admin.request(base, dict(member, **{field: value})), 422, 'invalid member ' + field + ' rejected')
    expect(admin.request(base, member), 303, 'administrator creates a member')
    member_id, row = lookup(member['email'])
    assert_true(member_id and '&lt;script&gt;' in row and '<script>' not in row, 'new member listed with escaped name')
    expect(admin.request(base, dict(member, email=member['email'].upper())), 422, 'email uniqueness is case insensitive')
    page = admin.request(f'{base}/{member_id}/edit')
    expect(page, 200, 'member editor renders')
    assert_true(password not in page[1] and 'password_hash' not in page[1], 'editor never reveals password or hash')
    account = Client()
    account.login(member['email'])
    expect(account.request('/admin'), 403, 'new student has learner permissions')

    updated = dict(member, name='สมาชิกที่แก้ไข ' + suffix,
                   email='updated-' + suffix + '@example.invalid', password='', level='voc2', role='teacher')
    expect(admin.request(f'{base}/{member_id}/save', updated), 303, 'administrator edits identity level and role')
    account.login(updated['email'])
    expect(account.request('/teach'), 200, 'edited teacher role is effective with unchanged password')
    profile = account.request('/profile')[1]
    assert_true(updated['name'] in profile and '<option value="voc2" selected>' in profile, 'edited name and level persist')
    expect(admin.request(f'{base}/{member_id}/save', dict(updated, email='admin@example.invalid')), 422, 'duplicate email edit rejected')
    assert_true(lookup(updated['email'])[0] == member_id, 'failed edit preserves member identity')
    expect(admin.request(f'{base}/{member_id}/save', dict(updated, active='0')), 303, 'administrator suspends member')
    expect(account.request('/dashboard'), 303, 'suspended existing session loses access')
    account.request('/login')
    expect(account.request('/login', {'email': updated['email'], 'password': password}), 422, 'suspended member cannot log in')
    expect(admin.request(f'{base}/{member_id}/save', dict(updated, password='ReplacementPassword!2026')), 303, 'administrator reactivates and resets password')
    expect(account.request('/login', {'email': updated['email'], 'password': password}), 422, 'old password fails after reset')
    expect(account.request('/login', {'email': updated['email'], 'password': 'ReplacementPassword!2026'}), 303, 'new password works after reset')
    account.request('/dashboard')

    for client in [teacher, student]:
        expect(client.request(f'{base}/{member_id}/save', updated), 403, 'non-admin cannot edit member')
        expect(client.request(f'{base}/{member_id}/delete', {'confirm_delete': '1'}), 403, 'non-admin cannot delete member')
    expect(admin.request(f'{base}/{member_id}/save', updated, token=False), 403, 'member edit requires CSRF')
    expect(admin.request(f'{base}/{member_id}/delete', {'confirm_delete': '1'}, token=False), 403, 'member deletion requires CSRF')
    expect(admin.request(f'{base}/{member_id}/delete', {}), 422, 'member deletion requires explicit confirmation')
    expect(admin.request(f'{base}/{member_id}/delete'), 200, 'member deletion confirmation renders')
    assert_true(lookup(updated['email'])[0] == member_id, 'GET deletion page never deletes member')
    expect(admin.request(f'{base}/{member_id}/delete', {'confirm_delete': '1'}), 303, 'unused member deleted')
    assert_true(lookup(updated['email'])[0] is None, 'deleted member removed from list')
    expect(account.request('/dashboard'), 303, 'deleted member session loses access')
    expect(admin.request(f'{base}/{member_id}/edit'), 404, 'deleted member editor returns not found')
    expect(admin.request(f'{base}/{member_id}/save', updated), 404, 'saving deleted member returns not found')
    expect(admin.request(f'{base}/{member_id}/delete', {'confirm_delete': '1'}), 404, 'deleting missing member returns not found')
    expect(admin.request(f'{base}/{member_id}', {'role': 'student', 'active': '1'}), 404, 'legacy access update rejects missing member')

    admin_id = lookup('admin@example.invalid')[0]
    own = dict(member, name='Test admin', email='admin@example.invalid', password='', role='admin')
    expect(admin.request(f'{base}/{admin_id}/save', dict(own, role='student')), 422, 'administrator cannot demote self')
    expect(admin.request(f'{base}/{admin_id}/save', dict(own, active='0')), 422, 'administrator cannot suspend self')
    expect(admin.request(f'{base}/{admin_id}/delete', {'confirm_delete': '1'}), 422, 'administrator cannot delete self')
    expect(admin.request(f'{base}/{admin_id}/save', own), 303, 'administrator can save own profile without losing access')
    teacher_id = lookup('teacher@example.invalid')[0]
    student_id = lookup('student-' + suffix + '@example.invalid')[0]
    expect(admin.request(f'{base}/{teacher_id}', {'role': 'student', 'active': '1'}), 409, 'course owner cannot become learner')
    for key in [teacher_id, student_id]:
        expect(admin.request(f'{base}/{key}/delete', {'confirm_delete': '1'}), 409, 'account with course or learning history cannot be deleted')
        page = admin.request(f'{base}/{key}/delete')[1]
        assert_true('แก้ไขสถานะบัญชี' in page and 'name="confirm_delete"' not in page, 'referenced member offers suspension instead of deletion')
    expect(student.request('/certificates'), 200, 'learning history remains accessible after rejected deletion')

    def imported(index, **values):
        return dict({'name': 'นำเข้า ' + suffix + ' ' + str(index),
                     'email': f'import-{suffix}-{index}@example.invalid', 'password': password}, **values)

    expect(upload([imported(1), imported(2, role='teacher', level='voc1', active=0)]), 303, 'JSON imports multiple members and optional defaults')
    one, row = lookup(imported(1)['email'])
    assert_true(one and 'ผู้เรียน' in row and 'ประชาชนทั่วไป' in row and 'เปิดใช้งาน' in row, 'JSON defaults to active public learner')
    _, row = lookup(imported(2)['email'])
    assert_true('ครูผู้สอน' in row and 'ปวช.' in row and 'ระงับบัญชี' in row, 'JSON explicit role level and inactive status saved')
    imported_account = Client()
    imported_account.login(imported(1)['email'])
    expect(imported_account.request('/admin'), 403, 'imported default learner can log in without administrator access')
    stream = StringIO(newline='')
    writer = csv.writer(stream)
    writer.writerow(['email', 'name', 'password', 'role', 'level', 'active'])
    writer.writerow([imported(3)['email'], 'ชื่อ, "นามสกุล"\nบรรทัดสอง', password, '', '', ''])
    writer.writerow([imported(4)['email'], 'ผู้ดูแลนำเข้า', password, 'admin', 'voc2', '1'])
    expect(upload(name='members.CSV', content=stream.getvalue().encode('utf-8-sig')), 303, 'CSV imports BOM Thai quotes comma multiline fields and reordered headers')
    _, row = lookup(imported(3)['email'])
    assert_true('ชื่อ, &quot;นามสกุล&quot;' in row and 'เปิดใช้งาน' in row, 'CSV preserves quoted name and fills blank optional values')
    assert_true('ผู้ดูแลระบบ' in lookup(imported(4)['email'])[1], 'administrator can import administrator role')
    expect(upload(name='minimal.csv', content=('name,email,password\r\nผู้เรียน,' + imported(5)['email'] + ',' + password + '\r\n').encode()), 303, 'CSV supports only required columns')

    for bad in [imported('bad', password='short'), imported('bad', role='owner'), imported('bad', level='all'),
                imported('bad', active='yes'), imported('bad', name=['array']), imported('bad', role=None),
                imported('bad', name='ก' * 181), imported('bad', thaid_subject='forged')]:
        result = upload([imported('rollback'), bad])
        expect(result, 422, 'invalid import row rejected')
        assert_true('รายการที่ 2' in result[1] and lookup(imported('rollback')['email'])[0] is None, 'invalid second row identifies position and saves no earlier member')
        assert_true(password not in result[1], 'import error does not expose passwords')
    expect(upload([imported('dup'), dict(imported('dup'), email=imported('dup')['email'].upper())]), 422, 'duplicate emails within import rejected')
    assert_true(lookup(imported('dup')['email'])[0] is None, 'duplicate file creates no members')
    expect(upload([imported('rollback'), imported(1)]), 422, 'existing email rejects entire import')
    assert_true(lookup(imported('rollback')['email'])[0] is None, 'existing email conflict creates no earlier member')
    for content in [b'{', b'{}', b'[]', b'[[]]', b'[1]', b'\xff']:
        expect(upload(content=content), 422, 'invalid or empty JSON rejected')
    for content in [b'', b'name,email\nX,x@example.invalid\n', b'name,email,password,email\n',
                    b'name,email,password,unknown\n', b'name,email,password\n', b'name,email,password\nX,Y\n',
                    b'name,email,password\nX,x@example.invalid,"UnclosedPassword!2026\n', b'\xff']:
        expect(upload(name='bad.csv', content=content), 422, 'invalid CSV rejected')
    expect(upload(name='members.xlsx', content=b'fake'), 422, 'unsupported file rejected')
    expect(upload(content=b' ' * (1048576 + 1)), 422, 'oversized member file rejected')
    expect(upload([imported('limit-' + str(i)) for i in range(201)]), 422, 'member row limit enforced')
    assert_true(lookup(imported('limit-0')['email'])[0] is None, 'over-limit file creates no members')
    expect(admin.request(base + '/import', {}), 422, 'missing member upload rejected')
    multiline = 'name,email,password\r\n"ชื่อ\nสองบรรทัด",' + imported('rollback')['email'] + ',' + password + '\r\nผิด,bad,password\r\n'
    result = upload(name='lines.csv', content=multiline.encode())
    expect(result, 422, 'invalid CSV row after multiline field rejected')
    assert_true('แถวที่ 4' in result[1], 'CSV reports physical line after multiline field')
    assert_true(lookup(imported('rollback')['email'])[0] is None, 'invalid CSV row rolls back earlier member')
    for template in ['/templates/members.csv', '/templates/members.json']:
        expect(guest.request(template), 200, 'member import template downloadable')
    log = admin.request('/admin?logs_per_page=50')[1]
    for action in ['user.created', 'user.updated', 'user.deleted', 'user.password_reset', 'users.imported']:
        assert_true(action in log, 'member action audited: ' + action)
