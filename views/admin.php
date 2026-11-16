<?php
use App\{DB,Pagination,ThaID,Users};
use function App\{e,csrfField,setting,dateThai,levelLabel};
$title='จัดการระบบ';
require __DIR__.'/layout-top.php';
$search=is_string($_GET['q']??null)?trim($_GET['q']):'';
$userWhere=$search!==''?' WHERE name LIKE ? OR email LIKE ?':'';
$userParams=$search!==''?['%'.$search.'%','%'.$search.'%']:[];
$userPagination=new Pagination((int)DB::one('SELECT COUNT(*) AS total FROM users'.$userWhere,$userParams)['total'],'users_page','users_per_page');
$users=DB::all('SELECT id,name,email,role,level,active,created_at FROM users'.$userWhere.' ORDER BY id DESC LIMIT ? OFFSET ?',[...$userParams,$userPagination->perPage,$userPagination->offset]);
$logPagination=new Pagination((int)DB::one('SELECT COUNT(*) AS total FROM audit_logs')['total'],'logs_page','logs_per_page');
$logs=DB::all('SELECT a.*,u.name FROM audit_logs a LEFT JOIN users u ON u.id=a.user_id ORDER BY a.id DESC LIMIT ? OFFSET ?',[$logPagination->perPage,$logPagination->offset]);
?>
<div class="page-heading"><div><div class="eyebrow dark">ADMINISTRATION</div><h1>จัดการระบบ</h1><p>ตั้งค่าศูนย์การเรียนรู้ บัญชีผู้ใช้ และตรวจสอบประวัติการจัดการ</p></div><a href="/teach" class="button outline">รายวิชาทั้งหมด <?= icon('arrow') ?></a></div>
<section class="panel"><div class="panel-heading"><div><h2>เลขทะเบียนเกียรติบัตร</h2><p class="form-hint">กำหนดช่วงเลขจากงานทะเบียน และส่งออกรายการเพื่อตรวจเทียบ</p></div><a href="/admin/certificate-numbers" class="button outline">จัดการชุดเลขเกียรติบัตร</a></div></section>
<section class="panel"><div class="panel-heading"><div><h2>Banner หน้าแรก</h2><p class="form-hint">จัดการภาพ Carousel ลิงก์ ลำดับ และการแสดงผล</p></div><a href="/admin/banners" class="button outline">จัดการ Banner</a></div></section>
<section class="panel"><div class="panel-heading"><div><h2>หมวดหมู่รายวิชา</h2><p class="form-hint">เพิ่ม แก้ไข และลบหมวดหมู่ที่ใช้ร่วมกันในรายวิชาและหน้าค้นหา</p></div><a href="/admin/categories" class="button outline"><?= icon('layers') ?>จัดการหมวดหมู่</a></div></section>
<section class="panel"><div class="panel-heading member-list-heading"><div><h2>ออกแบบเกียรติบัตร</h2><p class="form-hint">แก้ไขรูปแบบ ข้อความ ลายเซ็น และชื่อผู้อำนวยการ พร้อมดูตัวอย่างก่อนใช้งาน</p></div><a href="/admin/certificate-design" class="button outline"><?= icon('award') ?>ออกแบบเกียรติบัตร</a></div></section>
<div class="two-panels"><section class="panel"><h2>ข้อมูลศูนย์การเรียนรู้</h2><form method="post" action="/admin/settings" class="form-stack"><?= csrfField() ?><label>อีเมลติดต่อ<input type="email" name="contact_email" required value="<?= e(setting('contact_email')) ?>"></label><label class="check-label"><input type="checkbox" name="registration_open" <?= setting('registration_open')==='1'?'checked':'' ?>>เปิดรับสมัครสมาชิกด้วยอีเมลและ ThaID</label><p class="form-hint">การปิดรับสมัครไม่ปิดกั้นบัญชีเดิม และครูยังสร้างบัญชีให้ผู้เรียนได้</p><button class="button"><?= icon('check') ?>บันทึกการตั้งค่า</button></form></section><section class="panel"><h2>การยืนยันตัวตน ThaID</h2><span class="pill <?= ThaID::enabled()?'status-published':'' ?>"><?= ThaID::enabled()?'เปิดใช้งาน':'ยังไม่เปิดใช้งาน' ?></span><p class="prose">การตั้งค่าการเชื่อมต่อและข้อมูลลับจัดเก็บบนเซิร์ฟเวอร์ ผู้ดูแลโครงสร้างพื้นฐานสามารถกำหนด Client ID, Client Secret, API key และ Callback URL ตามที่กรมการปกครองอนุมัติ</p><p class="form-hint">ใบประกาศที่ออกแล้วเก็บชื่อผู้เรียน ชื่อรายวิชา และข้อมูลสถานศึกษา ณ วันออกเอกสาร การเปลี่ยนการตั้งค่าจะมีผลเฉพาะใบประกาศใหม่</p><a class="button outline full" href="/certificates">ไปที่ใบประกาศนียบัตร <?= icon('award') ?></a></section></div>
<section class="panel" id="users">
    <div class="panel-heading member-list-heading">
        <div><h2>บัญชีผู้ใช้งาน <span class="pill"><?= number_format($userPagination->total) ?> รายการ</span></h2><p class="form-hint">เพิ่ม แก้ไข ลบ และนำเข้าสมาชิกในระบบ</p></div>
        <div class="button-row"><a href="/admin/users/import" class="button outline"><?= icon('users') ?>นำเข้าสมาชิก</a><a href="/admin/users/new" class="button"><?= icon('plus') ?>เพิ่มสมาชิก</a></div>
    </div>
    <form method="get" action="/admin#users" class="filter-bar">
        <input type="hidden" name="users_per_page" value="<?= $userPagination->perPage ?>"><input type="hidden" name="logs_page" value="<?= $logPagination->page ?>"><input type="hidden" name="logs_per_page" value="<?= $logPagination->perPage ?>">
        <div class="search-input"><?= icon('search') ?><input name="q" value="<?= e($search) ?>" aria-label="ค้นหาบัญชี" placeholder="ค้นหาชื่อหรืออีเมล"></div><button class="button outline">ค้นหา</button>
    </form>
    <div class="table-scroll"><table class="member-table"><thead><tr><th>ชื่อ / อีเมล</th><th>ระดับ / วันที่สมัคร</th><th>สิทธิ์และสถานะ</th><th>จัดการ</th></tr></thead><tbody>
        <?php foreach ($users as $u): $self=(int)$u['id']===(int)$user['id']; ?>
        <tr data-member-id="<?= (int)$u['id'] ?>">
            <td><strong><?= e($u['name']) ?></strong><small><?= e($u['email']??'ThaID') ?></small><?php if ($self): ?><small>บัญชีของคุณ</small><?php endif; ?></td>
            <td><?= e(levelLabel($u['level'])) ?><small><?= dateThai($u['created_at']) ?></small></td>
            <td><span class="pill"><?= Users::ROLES[$u['role']] ?></span><small class="<?= $u['active']?'success-text':'member-inactive' ?>"><?= $u['active']?'เปิดใช้งาน':'ระงับบัญชี' ?></small></td>
            <td><div class="button-row"><a class="button small outline" href="/admin/users/<?= (int)$u['id'] ?>/edit" aria-label="แก้ไขสมาชิก <?= e($u['name']) ?>">แก้ไข</a><?php if (!$self): ?><a class="button small danger" href="/admin/users/<?= (int)$u['id'] ?>/delete" aria-label="ลบสมาชิก <?= e($u['name']) ?>">ลบ</a><?php endif; ?></div></td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$users): ?><tr><td colspan="4" class="table-empty">ไม่พบบัญชีที่ค้นหา</td></tr><?php endif; ?>
    </tbody></table></div>
    <?php $pagination=$userPagination;require __DIR__.'/pagination.php'; ?>
</section>
<section class="panel"><div class="panel-heading"><h2>ประวัติการจัดการล่าสุด</h2><span class="pill"><?= number_format($logPagination->total) ?> รายการ</span></div><div class="table-scroll"><table><thead><tr><th>เวลา</th><th>ผู้ดำเนินการ</th><th>เหตุการณ์</th><th>อ้างอิง</th></tr></thead><tbody><?php foreach($logs as $log): ?><tr><td><?= e($log['created_at']) ?></td><td><?= e($log['name']??'ระบบ') ?></td><td><?= e($log['action']) ?></td><td class="mono"><?= e($log['resource']) ?></td></tr><?php endforeach; ?><?php if(!$logs): ?><tr><td colspan="4" class="table-empty">ยังไม่มีประวัติ</td></tr><?php endif; ?></tbody></table></div><?php $pagination=$logPagination;require __DIR__.'/pagination.php'; ?></section><?php require __DIR__.'/layout-bottom.php'; ?>
