"""Launch separate test PHP processes to verify transactional budget reservation."""
import concurrent.futures, json, subprocess, secrets
CMD=['docker','compose','-f','compose.test.yaml','exec','-T','web','php','tests/ai_concurrency.php']
def run(*args): return subprocess.check_output(CMD+list(map(str,args)),text=True).strip()
data=json.loads(run('setup'))
with concurrent.futures.ThreadPoolExecutor(max_workers=8) as pool:
    results=list(pool.map(lambda _:run('reserve',data['course_id'],data['user_id'],secrets.token_hex(10)),range(8)))
assert results.count('reserved')==1,results
assert results.count('BUDGET_EXCEEDED')==7,results
assert run('total',data['course_id'])=='6000'
print('PASS 8 concurrent requests: one reservation, seven budget rejections, total remains under limit')
