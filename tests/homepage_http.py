"""Homepage visibility and public video directory; isolated test server only."""
import re
import secrets
import department_video_http as http
from department_video_http import Client, expect

admin,teacher,guest=Client(),Client(),Client()
admin.login('admin');teacher.login('teacher');guest.request('/')
settings='/admin/homepage'
keys=['department_videos','latest_courses','popular_courses','course_catalog']
ids=['department-videos','latest-courses','popular-courses','courses']
page=expect(admin.request(settings),200)
original={key:1 for key in keys if re.search(r'name="'+key+r'"[^>]*checked',page)}
expect(guest.request(settings),303)
expect(guest.request(settings,{}),303)
expect(teacher.request(settings),403)
expect(teacher.request(settings,{}),403)
expect(admin.request(settings,{},csrf=False),403)
expect(admin.request(settings,{'department_videos':'unexpected'}),422)
video_ids=[]
suffix=secrets.token_hex(5)
page=expect(admin.request('/admin/department-videos'),200)
category=re.search(r'<option value="(\d+)"',page)[1]
def visible_sections(page):
    return [bool(re.search(r'<section[^>]+id="'+section+r'"',page)) for section in ids]
def preview_ids(page):
    section=re.search(r'<section[^>]+id="department-videos".*?</section>',page,re.S)[0]
    return list(map(int,re.findall(r'href="/department-videos/(\d+)"',section)))
try:
    expect(admin.request(settings,{key:1 for key in keys}),303)
    assert visible_sections(expect(guest.request('/'),200))==[True]*4
    assert original.keys()<=set(keys)
    for i in range(12):
        data=dict(category_id=category,title=f'Homepage {suffix} {i}',description='Directory test',source='url',external_url='https://example.com/video',duration_seconds=1,active=0 if i==11 else 1)
        expect(admin.request('/admin/department-videos',data),303)
        page=expect(admin.request('/admin/department-videos'),200)
        video_ids.append(max(map(int,re.findall(r'id="department-video-(\d+)"',page))))
    page=expect(guest.request('/'),200)
    assert preview_ids(page)==list(reversed(video_ids[8:11]))
    assert 'href="/department-videos">ดูวิดีโอทั้งหมด' in page
    directory=expect(guest.request('/department-videos?per_page=50'),200)
    assert all(f'/department-videos/{vid}"' in directory for vid in video_ids[:11])
    assert f'/department-videos/{video_ids[11]}"' not in directory
    expect(guest.request(f'/department-videos/{video_ids[11]}'),404)
    first=expect(guest.request('/department-videos'),200)
    second=expect(guest.request('/department-videos?page=2'),200)
    card_ids=lambda html:list(map(int,re.findall(r'href="/department-videos/(\d+)"',html)))
    assert len(card_ids(first))==10 and not set(card_ids(first))&set(card_ids(second))
    assert video_ids[0] in card_ids(second)
    assert 'rel="next"' in first and 'rel="prev"' in second
    # Each setting affects only its own homepage section.
    for index,key in enumerate(keys):
        expect(admin.request(settings,{k:1 for k in keys if k!=key}),303)
        expected=[True]*4;expected[index]=False
        assert visible_sections(expect(guest.request('/'),200))==expected
        persisted=expect(admin.request(settings),200)
        assert bool(re.search(r'name="'+key+r'"[^>]*checked',persisted)) is False
    # Standalone public directories remain usable with every homepage section off.
    expect(admin.request(settings,{}),303)
    assert visible_sections(expect(guest.request('/'),200))==[False]*4
    expect(guest.request('/department-videos'),200)
    course_page=expect(guest.request('/courses'),200)
    assert 'id="courses"' in course_page and 'action="/courses#courses"' in course_page
    filtered=expect(guest.request('/courses?q=impossible-'+suffix),200)
    assert 'ไม่พบรายวิชาที่ตรงกับการค้นหา' in filtered
    expect(guest.request(f'/department-videos/{video_ids[0]}'),200)
    # Featured links still work when the large course directory section is hidden.
    expect(admin.request(settings,{'latest_courses':1,'popular_courses':1}),303)
    page=expect(guest.request('/'),200)
    assert 'class="featured-more" href="/courses"' in page
    expect(admin.request(settings,{key:1 for key in keys}),303)
    assert len(preview_ids(expect(guest.request('/'),200)))==3
finally:
    for i,vid in enumerate(video_ids):
        expect(admin.request(f'/admin/department-videos/{vid}/save',dict(category_id=category,title=f'Homepage {suffix} {i}',description='',source='url',external_url='https://example.com/video',duration_seconds=1,active=0)),303)
    expect(admin.request(settings,original),303)
print(f'{http.checks} homepage HTTP checks passed.')
