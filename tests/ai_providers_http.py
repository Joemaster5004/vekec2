"""Run baseline BYOK checks plus admin catalog/teacher-only HTTP checks; no external API calls."""
import runpy, secrets
context=runpy.run_path('tests/ai_http_checks.py')
teacher,other,admin,student,guest=(context[k] for k in ['teacher','other','admin','student','guest'])
checks=0
def expect(r,status,label):
    global checks
    assert r[0]==status,(label,r)
    checks+=1;print('PASS',label);return r[1]
def yes(value,label):
    global checks
    assert value,label
    checks+=1;print('PASS',label)
base='/api/v1/admin/ai-providers'
expect(guest.call('/admin/ai-providers'),303,'anonymous admin provider UI denied')
expect(teacher.call('/admin/ai-providers'),403,'teacher cannot manage provider UI')
expect(student.call('/admin/ai-providers'),403,'student cannot manage provider UI')
expect(admin.call('/admin/ai-providers'),200,'admin provider UI renders')
for user in [teacher,other,student]: expect(user.call(base),403,'only administrator can list full catalog')
expect(admin.call(base),200,'admin reads full catalog')
slug='http-'+secrets.token_hex(4)
body={'slug':slug,'name':'Custom <img onerror=alert(1)>','adapter':'openai_compatible','base_url':'https://api.example.com/v1','models':{'text':['custom-text'],'embedding':[],'speech':[],'analytics':[],'assistant':['teacher-helper']},'enabled':True,'expected_revision':0}
expect(teacher.call(base,'POST',body),403,'teacher cannot register provider')
expect(admin.call(base,'POST',body,token=False),403,'admin create enforces CSRF')
expect(admin.call(base,'POST',body,origin='https://evil.invalid'),403,'admin create enforces Origin')
expect(admin.call(base,'POST',dict(body,adapter='arbitrary-header')),422,'unimplemented adapter denied')
expect(admin.call(base,'POST',dict(body,models={'unknown':['model']})),422,'unknown model capability denied')
expect(admin.call(base,'POST',dict(body,models={'text':[]})),422,'enabled provider needs models')
for url in ['http://api.example.com/v1','https://127.0.0.1/v1','https://api.example.com:8443/v1','https://api.example.com/v1?key=hidden']:
    expect(admin.call(base,'POST',dict(body,base_url=url)),422,'unsafe provider URL rejected')
p=expect(admin.call(base,'POST',body),201,'admin adds custom provider')['data']
yes(p['slug']==slug and p['revision']==1,'provider starts at revision one')
expect(admin.call(base,'POST',body),409,'duplicate provider code rejected')
catalog=expect(teacher.call('/api/v1/me/ai-capabilities'),200,'teacher refreshes choices')['data']['provider_catalog']
yes(any(p['slug']==slug and p['models']['text']==['custom-text'] for p in catalog),'admin-defined models visible to teacher')
new=expect(teacher.call('/api/v1/me/ai-credentials','POST',{'provider':slug,'label':'Custom credential','secret':'test-valid-private-custom'}),201,'teacher selects admin-defined provider')['data']
yes(new['provider_base_url']=='https://api.example.com/v1' and new['provider_current_revision']==1,'teacher sees destination before verification')
credbase='/api/v1/me/ai-credentials/'+new['credential_id']
expect(teacher.call(credbase+'/verify','POST',{'allow_billable_test':False}),422,'provider revision approval required before key transmission')
updated=dict(body,expected_revision=1,base_url='https://another.example.com/v1')
expect(admin.call(base+'/'+slug,'PUT',updated,token=False),403,'admin edit enforces PUT CSRF')
expect(admin.call(base+'/'+slug,'PUT',dict(updated,slug='renamed-provider')),422,'provider code immutable')
expect(admin.call(base+'/'+slug,'PUT',updated),200,'admin edits provider endpoint')
expect(admin.call(base+'/'+slug,'PUT',updated),409,'stale provider revision denied')
metadata=expect(teacher.call('/api/v1/me/ai-credentials'),200,'read affected credential metadata')['data']
yes(next(c for c in metadata if c['credential_id']==new['credential_id'])['status']=='provider_changed','teacher prompted to reapprove changed provider')
expect(teacher.call(credbase+'/verify','POST',{'allow_billable_test':False,'confirmed_provider_revision':1}),409,'old endpoint approval cannot send key to new endpoint')
expect(admin.call(base+'/'+slug,'PUT',dict(updated,expected_revision=2,enabled=False)),200,'admin disables provider')
catalog=teacher.call('/api/v1/me/ai-capabilities')[1]['data']['provider_catalog']
yes(not any(p['slug']==slug for p in catalog),'disabled provider hidden from teacher choices')
expect(teacher.call('/api/v1/me/ai-credentials','POST',{'provider':slug,'label':'Blocked','secret':'test-valid-key-not-sent'}),422,'disabled provider cannot accept new keys')
expect(teacher.call(credbase+'/verify','POST',{'allow_billable_test':False,'confirmed_provider_revision':3}),409,'disabled provider cannot receive saved key')
expect(student.call(base,'POST',body),403,'student cannot add provider by direct API')
print(f'Provider HTTP checks passed: {checks}')
