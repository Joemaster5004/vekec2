<?php
declare(strict_types=1);
namespace App;
final class Router {
    public function dispatch(): void {
        $path=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);$method=$_SERVER['REQUEST_METHOD'];
        if(str_starts_with($path,'/api/v1/'))AiApi::dispatch($path,$method);
        if($path==='/health'){DB::one('SELECT 1');header('Content-Type: application/json');echo '{"status":"ok"}';return;}
        if($method==='POST'){(new Actions())->dispatch($path);return;}
        if($method!=='GET')abort(405,'ไม่รองรับวิธีเรียกใช้งานนี้');
        if(in_array($path,['/','/courses'],true)){
            $homeSections=Homepage::sections();
            $q=is_string($_GET['q']??null)?trim($_GET['q']):'';$category=is_string($_GET['category']??null)?$_GET['category']:'';$level=is_string($_GET['level']??null)?$_GET['level']:'';
            $where="c.status='published'";$params=[];
            if($q!==''){$where.=' AND (c.title LIKE ? OR c.code LIKE ? OR c.description LIKE ?)';$params=array_merge($params,array_fill(0,3,'%'.$q.'%'));}
            if($category!==''){$where.=' AND c.category=?';$params[]=$category;}
            if(in_array($level,['voc1','voc2','public'],true)){$where.=" AND c.level IN ('all',?)";$params[]=$level;}
            $courses=DB::all("SELECT c.*,u.name AS teacher_name,(SELECT COUNT(*) FROM lessons l WHERE l.course_id=c.id) AS lesson_count FROM courses c JOIN users u ON u.id=c.teacher_id WHERE $where ORDER BY c.id DESC LIMIT 100",$params);
            $categories=DB::all('SELECT name AS category FROM categories ORDER BY name,id');
            view($path==='/'?'catalog':'course-catalog',array_merge(compact('courses','categories','q','category','level','homeSections'),$path==='/'?Catalog::featured():[]));return;
        }
        if(in_array($path,['/login','/register'],true)){if(Auth::user())redirect('/dashboard');view('auth',['register'=>$path==='/register']);return;}
        if($path==='/auth/thaid'){(new ThaID())->start();return;}
        if($path==='/auth/thaid/callback'){(new ThaID())->callback();return;}
        if(preg_match('#^/courses/(\d+)$#',$path,$m)){
            $course=Learning::course((int)$m[1]);$user=Auth::user();
            if($course['status']!=='published' && !($user && ($user['role']==='admin'||(int)$course['teacher_id']===(int)$user['id']||Learning::enrolled((int)$user['id'],(int)$course['id']))))abort(404,'ไม่พบรายวิชา');
            $lessons=Learning::lessons((int)$course['id'],(int)($user['id']??0));view('course',compact('course','lessons'));return;
        }
        if($path==='/admin/homepage'){Auth::require(['admin']);view('homepage-settings',['sections'=>Homepage::sections()]);return;}
        if($path==='/department-videos'){
            $pagination=new Pagination((int)DB::one('SELECT COUNT(*) AS total FROM department_videos WHERE active=1')['total']);
            view('department-videos',['departmentVideos'=>DepartmentVideo::all(true,null,$pagination),'pagination'=>$pagination]);return;
        }
        if($path==='/admin/department-videos'){Auth::require(['admin']);view('department-videos-admin',['videos'=>DepartmentVideo::all(),'categories'=>Category::options()]);return;}
        if(preg_match('#^/department-videos/(\d+)$#',$path,$m)){view('department-video',['video'=>DepartmentVideo::find((int)$m[1])]);return;}
        if(preg_match('#^/department-videos/(\d+)/media(?:/(\d+))?$#',$path,$m)){
            $v=DepartmentVideo::find((int)$m[1]);
            $name=isset($m[2])?(DB::one('SELECT path FROM department_video_files WHERE video_id=? AND id=?',[$v['id'],$m[2]])['path']??null):$v['media_path'];
            if(!$name)abort(404,'ไม่พบไฟล์');Upload::stream($name,!isset($m[2]));
        }
        if(preg_match('#^/guidance-certificate/([a-f0-9]{32})/(signature|emblem)$#',$path,$m))CertificateDesign::streamImage($m[2],$m[1],true,true);
        if(preg_match('#^/guidance-certificate/([a-f0-9]{32})$#',$path,$m)){
            $certificate=DepartmentVideo::certificate($m[1]);
            view('guidance-certificate',compact('certificate'));return;
        }
        if($path==='/dashboard'){Auth::require();view('dashboard');return;}
        if($path==='/certificates'){Auth::require();view('certificates');return;}
        if(preg_match('#^/(certificate|verify)/([a-f0-9]{32})/(signature|emblem)$#',$path,$m))CertificateDesign::streamImage($m[3],$m[2],$m[1]==='verify');
        if(preg_match('#^/(certificate|verify)/([a-f0-9]{32})$#',$path,$m)){
            $certificate=DB::one('SELECT * FROM certificates WHERE code=?',[$m[2]])??abort(404,'ไม่พบใบประกาศนียบัตร');
            if($m[1]==='certificate'){$u=Auth::require();if((int)$u['id']!==(int)$certificate['user_id']&&$u['role']!=='admin')abort(403,'ไม่มีสิทธิ์ดาวน์โหลดใบประกาศนี้');}
            view('certificate',['certificate'=>$certificate,'verify'=>$m[1]==='verify']);return;
        }
        if(preg_match('#^/learn/(\d+)$#',$path,$m)){$lesson=Learning::lesson((int)$m[1]);view('learn',compact('lesson'));return;}
        if($path==='/teach'){Auth::require(['teacher','admin']);view('teaching');return;}
        if($path==='/teach/ai-connections'){Auth::require(['teacher','admin']);view('ai-connections');return;}
        if($path==='/teach/new'){Auth::require(['teacher','admin']);view('editor',['course'=>null]);return;}
        if(preg_match('#^/teach/(\d+)$#',$path,$m)){$course=Learning::manage((int)$m[1]);view('editor',compact('course'));return;}
        if(preg_match('#^/teach/(\d+)/students$#',$path,$m)){$course=Learning::manage((int)$m[1]);view('students',compact('course'));return;}
        if(preg_match('#^/teach/quizzes/(\d+)$#',$path,$m)){$lesson=Learning::lesson((int)$m[1],false);Learning::manage((int)$lesson['course_id']);if($lesson['kind']!=='quiz')abort(404,'ไม่พบแบบทดสอบ');view('questions',compact('lesson'));return;}
        if($path==='/admin/banners'){Auth::require(['admin']);view('banners',['banners'=>Banner::all()]);return;}
        if($path==='/admin/ai-providers'){Auth::require(['admin']);view('ai-providers');return;}
        if(preg_match('#^/media/banners/(\d+)$#',$path,$m))Banner::stream((int)$m[1]);
        if(in_array($path,['/admin/certificate-numbers','/admin/guidance-certificate-numbers'],true)){Auth::require(['admin']);view('certificate-numbers',['guidance'=>$path==='/admin/guidance-certificate-numbers']);return;}
        if(in_array($path,['/admin/certificate-numbers/export','/admin/guidance-certificate-numbers/export'],true))CertificateNumbers::export($path==='/admin/guidance-certificate-numbers/export');
        if($path==='/admin'){Auth::require(['admin']);view('admin');return;}
        if(preg_match('#^/admin/(guidance-certificate-design|certificate-design)(?:/(signature|emblem|preview))?$#',$path,$m)){
            Auth::require(['admin']);$guidance=$m[1]==='guidance-certificate-design';
            if(in_array($m[2]??'',['signature','emblem'],true))CertificateDesign::streamImage($m[2],null,false,$guidance);
            $design=CertificateDesign::current($guidance);
            if(($m[2]??'')==='preview')view('certificate-preview',['guidance'=>$guidance,'design'=>$design,'certificate'=>CertificateDesign::preview($design,$guidance)]);
            else view('certificate-designer',compact('design','guidance'));
            return;
        }
        if($path==='/admin/users/new'){Auth::require(['admin']);view('user-form',['member'=>null]);return;}
        if($path==='/admin/users/import'){Auth::require(['admin']);view('user-import');return;}
        if(preg_match('#^/admin/users/(\d+)/(edit|delete)$#',$path,$m)){
            $member=Users::find((int)$m[1]);
            if($m[2]==='edit')view('user-form',compact('member'));
            else view('user-delete',['member'=>$member,'references'=>Users::references((int)$m[1])]);
            return;
        }
        if($path==='/admin/categories'){
            Auth::require(['admin']);
            $pagination=new Pagination((int)DB::one('SELECT COUNT(*) AS total FROM categories')['total']);
            view('categories',['categories'=>Category::all($pagination),'categoryOptions'=>Category::options(),'pagination'=>$pagination]);return;
        }
        if($path==='/profile'){Auth::require();view('profile');return;}
        if(preg_match('#^/media/images/([a-f0-9]{40}\.(?:jpg|png|webp))$#',$path,$m))EditorImage::stream($m[1]);
        if(preg_match('#^/media/(\d+)/(video|attachment)$#',$path,$m)){$lesson=Learning::lesson((int)$m[1]);$name=$m[2]==='video'?$lesson['media_path']:$lesson['attachment_path'];if(!$name)abort(404,'ไม่พบไฟล์');Upload::stream($name,$m[2]==='video');}
        if(preg_match('#^/submissions/(\d+)/file$#',$path,$m)){
            $u=Auth::require();$s=DB::one('SELECT s.*,l.course_id FROM submissions s JOIN lessons l ON l.id=s.lesson_id WHERE s.id=?',[$m[1]])??abort(404,'ไม่พบไฟล์');
            if((int)$s['user_id']!==(int)$u['id'])Learning::manage((int)$s['course_id']);if(!$s['file_path'])abort(404,'ไม่พบไฟล์');Upload::stream($s['file_path']);
        }
        abort(404,'ไม่พบหน้าที่คุณต้องการ');
    }
}
