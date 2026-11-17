"""Guidance design, signatures, registration and immutability; test server only."""
import base64
import json
import re
import secrets
import time
import department_video_http as http
from department_video_http import Client, expect

admin, teacher, guest = Client(), Client(), Client()
admin.login('admin'); teacher.login('teacher'); guest.request('/')
design_path='/admin/guidance-certificate-design'
number_path='/admin/guidance-certificate-numbers'
for path in [design_path,number_path,design_path+'/preview',design_path+'/signature',number_path+'/export']:
    expect(guest.request(path),303)
    expect(teacher.request(path),403)
for path in [design_path,number_path,number_path+'/activate',design_path+'/emblem-preview']:
    expect(teacher.request(path,{}),403)
    expect(admin.request(path,{},csrf=False),403)
main_before=expect(admin.request('/admin/certificate-design'),200)
main_numbers=expect(admin.request('/admin/certificate-numbers'),200)
page=expect(admin.request(design_path),200)
assert 'action="'+design_path+'"' in page and 'data-emblem-preview="'+design_path+'/emblem-preview"' in page
expect(admin.request(design_path,{'style':'bad'}),422)
suffix=secrets.token_hex(5)
design=dict(institution='Guidance college '+suffix,heading='Guidance Award '+suffix,subtitle='CERTIFICATE OF PARTICIPATION',intro='Awarded to',completion='Participated in guidance',director_name='Director Guidance '+suffix,director_position='College Director',style='modern',accent='#24476c',signature_alignment='right',signature_width=24,emblem_source='upload',emblem_size=5)
png=base64.b64decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII=')
files=[('signature','sign.png','image/png',png),('emblem','seal.png','image/png',png)]
expect(admin.request(design_path,design,files),303)
page=expect(admin.request(design_path),200)
assert design['heading'] in page and design_path+'/signature' in page and design_path+'/emblem' in page
main_after=expect(admin.request('/admin/certificate-design'),200)
assert design['heading'] not in main_after
expect(admin.request(design_path+'/signature'),200)
expect(admin.request(design_path+'/emblem'),200)
preview=expect(admin.request(design_path+'/preview'),200)
assert 'cert-style-modern' in preview and 'DEPARTMENT GUIDANCE' in preview
assert design_path+'/signature' in preview and 'แนะแนว 0101' in preview
# Isolated range with exactly two registrations.
page=expect(admin.request(number_path),200)
previous_rows=re.findall(r'<tr[^>]*data-range-id="(\d+)"[^>]*>(.*?)</tr>',page,re.S)
previous_range=next((i for i,row in previous_rows if 'ชุดปัจจุบัน' in row and 'เลขหมด' not in row),0)
prefix='GUIDE-'+suffix
range_data=dict(prefix=prefix,year=2569,start_number=701,end_number=702,digits=4)
expect(admin.request(number_path,range_data),303)
expect(admin.request(number_path,range_data),422)
page=expect(admin.request(number_path),200)
rows=re.findall(r'<tr(?:\s[^>]*)?>(.*?)</tr>',page,re.S)
row=next(row for row in rows if prefix in row)
range_id=re.search(r'name="range_id" value="(\d+)"',row)[1]
expect(admin.request(number_path+'/activate',dict(range_id=range_id)),303)
# One public video and separate anonymous watch sessions.
page=expect(admin.request('/admin/department-videos'),200)
category=re.search(r'<option value="(\d+)"',page)[1]
video=dict(category_id=category,title='Guidance activity '+suffix,description='Public guidance',source='url',external_url='https://example.com/guidance',duration_seconds=1,active=1)
expect(admin.request('/admin/department-videos',video),303)
page=expect(admin.request('/admin/department-videos'),200)
vid=max(map(int,re.findall(r'id="department-video-(\d+)"',page)))
path=f'/department-videos/{vid}'
profile=dict(name='Guidance Recipient '+suffix,school='Private school',grade='M3',phone='0812345678',email='private@example.invalid',consent=1)
def watched_client():
    c=Client(); expect(c.request(path),200)
    state=json.loads(expect(c.request(path+'/start',{}),200))
    return c,state['token']
first,token=watched_client()
second,token2=watched_client()
third,token3=watched_client()
time.sleep(1.1)
for c,t in [(first,token),(second,token2),(third,token3)]:
    assert json.loads(expect(c.request(path+'/tick',dict(token=t,confirmed=1)),200))['completed']
expect(first.request(path+'/certificate',profile),303)
cert_url=first.location
cert=expect(Client().request(cert_url),200)
assert prefix+' 0701/2569' in cert and design['heading'] in cert and design['director_name'] in cert
assert 'private@example.invalid' not in cert and 'Private school' not in cert
assert cert_url+'/signature' in cert and cert_url+'/emblem' in cert
expect(Client().request(cert_url+'/signature'),200)
expect(Client().request(cert_url+'/emblem'),200)
expect(first.request(path+'/certificate',profile),303)
assert first.location==cert_url
# Pausing blocks new claims but existing claims retain their number and signature.
expect(admin.request(number_path+'/activate',dict(range_id=0)),303)
expect(second.request(path+'/certificate',profile),409)
expect(first.request(path+'/certificate',profile),303)
expect(admin.request(number_path+'/activate',dict(range_id=range_id)),303)
expect(second.request(path+'/certificate',{**profile,'name':'Second recipient'}),303)
second_url=second.location
assert prefix+' 0702/2569' in expect(second.request(second_url),200)
expect(third.request(path+'/certificate',profile),409)
# Template changes preserve issued artwork, signature, name and registration.
new_design={**design,'heading':'Changed heading '+suffix,'director_name':'Changed director','emblem_source':'default','remove_signature':1}
expect(admin.request(design_path,new_design),303)
expect(admin.request(design_path+'/signature'),404)
expect(admin.request(design_path+'/emblem'),404)
cert=expect(first.request(cert_url),200)
assert design['heading'] in cert and new_design['heading'] not in cert and prefix+' 0701/2569' in cert
expect(first.request(cert_url+'/signature'),200)
expect(first.request(cert_url+'/emblem'),200)
expect(guest.request('/guidance-certificate/'+'0'*32+'/signature'),404)
register=expect(admin.request(number_path+'/export'),200)
assert prefix+' 0701/2569' in register and prefix+' 0702/2569' in register and 'private@example.invalid' not in register
assert prefix not in expect(admin.request('/admin/certificate-numbers/export'),200)
assert prefix not in expect(admin.request('/admin/certificate-numbers'),200)
expect(admin.request(f'/admin/department-videos/{vid}/save',{**video,'active':0}),303)
expect(admin.request(number_path+'/activate',dict(range_id=previous_range)),303)
print(f'{http.checks} guidance certificate HTTP checks passed.')
