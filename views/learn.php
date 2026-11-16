<?php

use App\{DB, Learning, RichText, Quiz};
use function App\{e, csrfField, dateThai};

$title = $lesson['title'];
require __DIR__ . '/layout-top.php';
$uid = (int)$user['id'];
$manager = $user['role'] === 'admin' || ($user['role'] === 'teacher' && (int)$lesson['teacher_id'] === $uid);
$lessons = Learning::lessons((int)$lesson['course_id'], $uid);
$p = DB::one('SELECT * FROM progress WHERE user_id=? AND lesson_id=?', [$uid, $lesson['id']]);
$complete = !empty($p['completed_at']);
$next = null;
foreach ($lessons as $l) if ($l['position'] > $lesson['position']) {
  $next = $l;
  break;
}
?>
<div class="breadcrumb"><a href="/courses/<?= $lesson['course_id'] ?>"><?= e($lesson['course_title']) ?></a><?= icon('chevron') ?><span>ห้องเรียน</span></div>
<div class="classroom">
  <section class="lesson-content">
    <div class="panel lesson-main">
      <div class="panel-heading"><span class="pill">กิจกรรมที่ <?= $lesson['position'] ?></span><?php if ($complete): ?><span class="success-text"><?= icon('check') ?>เรียนสำเร็จแล้ว</span><?php endif; ?></div>
      <h1><?= e($lesson['title']) ?></h1><?php if ($manager): ?><div class="notice">โหมดตัวอย่างสำหรับผู้สอน การดูหน้านี้ไม่บันทึกผลการเรียน</div><?php endif; ?>
      <?php if ($lesson['kind'] === 'video'):
        $youtube = null;
        $external = $lesson['external_url'] ?? '';
        $host = parse_url($external, PHP_URL_HOST);
        if (in_array($host, ['youtube.com', 'www.youtube.com', 'youtu.be', 'm.youtube.com'], true)) {
          parse_str((string)parse_url($external, PHP_URL_QUERY), $ytQuery);
          $ytCandidate = $host === 'youtu.be' ? trim((string)parse_url($external, PHP_URL_PATH), '/') : ($ytQuery['v'] ?? basename((string)parse_url($external, PHP_URL_PATH)));
          if (is_string($ytCandidate) && preg_match('/^[a-zA-Z0-9_-]{11}$/', $ytCandidate)) $youtube = $ytCandidate;
        }
        $direct = $lesson['media_path'] || preg_match('/\.mp4$/i', (string)parse_url($external, PHP_URL_PATH)); ?>
        <div class="video-wrapper" data-lesson="<?= $lesson['id'] ?>" data-required="<?= !$manager && !$complete && $lesson['require_watch'] ? '1' : '0' ?>" data-duration="<?= (int)$lesson['duration_seconds'] ?>" data-start="<?= (float)($p['watched_seconds'] ?? 0) ?>">
          <?php if ($youtube): ?><div id="youtube-player" data-video-id="<?= e($youtube) ?>"></div><?php elseif ($direct): ?><video id="lesson-video" controls playsinline preload="metadata" controlslist="nodownload noplaybackrate" src="<?= $lesson['media_path'] ? '/media/' . $lesson['id'] . '/video' : e($external) ?>">เบราว์เซอร์นี้ไม่รองรับวิดีโอ</video><?php else: ?><div class="external-video">
              <p>วิดีโอนี้เปิดผ่านเว็บไซต์ภายนอก</p><a href="<?= e($external) ?>" target="_blank" rel="noopener noreferrer" class="button outline">เปิดวิดีโอ <?= icon('play') ?></a>
            </div><?php endif; ?></div>
        <?php if ($lesson['require_watch'] && !$manager): ?><div class="video-status" role="status"><?= icon('lock') ?><span id="video-status-text"><?= $complete ? 'ดูวิดีโอครบแล้ว' : 'ระบบจะบันทึกความคืบหน้าเมื่อเล่นวิดีโอ กรุณาดูจนจบก่อนทำกิจกรรมถัดไป' ?></span></div><?php endif; ?>
      <?php endif; ?><div class="prose rich-content"><?= RichText::render($lesson['body'], $lesson['body_format'] ?? 'text') ?></div><?php if ($lesson['attachment_path']): ?><a class="attachment" href="/media/<?= $lesson['id'] ?>/attachment"><?= icon('download') ?>ดาวน์โหลดเอกสารประกอบบทเรียน</a><?php endif; ?>
      <?php if ($lesson['kind'] === 'assignment'): $submission = DB::one('SELECT * FROM submissions WHERE user_id=? AND lesson_id=?', [$uid, $lesson['id']]); ?>
        <div class="activity-info"><span>คะแนนเต็ม 100 คะแนน</span><strong>เกณฑ์ผ่าน <?= $lesson['pass_score'] ?>%</strong></div><?php if ($submission): ?><div class="feedback">
            <h3><?= $submission['score'] === null ? 'ส่งงานแล้ว · รอตรวจ' : 'ผลการตรวจ: ' . $submission['score'] . ' / 100 คะแนน' ?></h3>
            <p><?= e($submission['feedback'] ?? 'ครูผู้สอนจะตรวจและให้ข้อเสนอแนะในหน้านี้') ?></p><?php if ($submission['file_path']): ?><a href="/submissions/<?= $submission['id'] ?>/file" class="text-link">ดาวน์โหลดไฟล์ที่ส่ง</a><?php endif; ?>
          </div><?php endif; ?>
        <?php if (!$manager && !$complete): ?><form class="form-stack" method="post" action="/learn/<?= $lesson['id'] ?>/submit" enctype="multipart/form-data"><?= csrfField() ?><label>คำตอบ / รายละเอียดงาน<textarea name="body" maxlength="30000" placeholder="เขียนคำตอบของคุณที่นี่"><?= e($submission['body'] ?? '') ?></textarea></label><label>แนบไฟล์ (ถ้ามี)<input type="file" name="file" accept=".pdf,.jpg,.jpeg,.png"><small>PDF, JPG หรือ PNG ไม่เกิน 10 MB · การส่งใหม่จะแทนที่ไฟล์เดิม</small></label><button class="button"><?= $submission ? 'ส่งงานใหม่' : 'ส่งใบงาน' ?> <?= icon('arrow') ?></button></form><?php endif; ?>
      <?php elseif ($lesson['kind'] === 'quiz'): $attempts = DB::all('SELECT * FROM attempts WHERE user_id=? AND lesson_id=? ORDER BY id DESC', [$uid, $lesson['id']]);
        $open = null;
        foreach ($attempts as $a) if (!$a['submitted_at']) {
          $open = $a;
          break;
        }
        $questionCount = DB::one('SELECT COUNT(*) AS n FROM questions WHERE lesson_id=?', [$lesson['id']])['n']; ?>
        <div class="quiz-info">
          <div><strong><?= (int)$questionCount ?></strong><span>ข้อสอบ</span></div>
          <div><strong><?= $lesson['time_limit'] ?></strong><span>นาที / ครั้ง</span></div>
          <div><strong><?= $lesson['pass_score'] ?>%</strong><span>เกณฑ์ผ่าน</span></div>
          <div><strong><?= max(0, (int)$lesson['max_attempts'] - count($attempts)) ?></strong><span>สิทธิ์ที่เหลือ</span></div>
        </div>
        <?php if ($open && !$manager): $questions = DB::all('SELECT id,prompt,options,content_format FROM questions WHERE lesson_id=? ORDER BY id', [$lesson['id']]);
          $deadline = strtotime($open['started_at']) + (int)$lesson['time_limit'] * 60; ?><div class="quiz-timer" data-deadline="<?= $deadline ?>" data-server-now="<?= time() ?>"><?= icon('clock') ?> เวลาที่เหลือ <strong id="quiz-countdown">กำลังคำนวณ...</strong></div>
          <form method="post" action="/quiz/<?= $open['id'] ?>/submit" id="quiz-form"><?= csrfField() ?><?php foreach ($questions as $i => $q): ?><fieldset class="question">
              <legend>ข้อที่ <?= $i + 1 ?></legend>
              <div class="rich-content question-prompt"><?= RichText::render($q['prompt'], $q['content_format']) ?></div><?php foreach (json_decode($q['options'], true) as $index => $option): ?><div class="answer-option"><input type="radio" name="answers[<?= $q['id'] ?>]" value="<?= $index ?>" aria-labelledby="answer-<?= $q['id'] ?>-<?= $index ?>">
                  <div id="answer-<?= $q['id'] ?>-<?= $index ?>" class="answer-content"><strong><?= ['ก', 'ข', 'ค', 'ง'][$index] ?>.</strong>
                    <div class="rich-content"><?= RichText::render($option, $q['content_format']) ?></div>
                  </div>
                </div><?php endforeach; ?>
            </fieldset><?php endforeach; ?><button class="button full">ส่งคำตอบ <?= icon('check') ?></button></form>
        <?php elseif (!$manager && !$complete && count($attempts) < (int)$lesson['max_attempts']): ?><p class="form-hint">เวลาเริ่มนับทันทีที่กดเริ่ม และยังนับต่อเมื่อปิดหน้านี้ หากส่งหลังหมดเวลาจะได้ 0 คะแนน</p>
          <form method="post" action="/learn/<?= $lesson['id'] ?>/quiz/start"><?= csrfField() ?><button class="button full">เริ่มทำแบบทดสอบ <?= icon('arrow') ?></button></form><?php endif; ?>
        <?php if ($manager || Quiz::reviewAllowed((int)$lesson['id'], $uid)): $reviewQuestions = DB::all('SELECT * FROM questions WHERE lesson_id=? ORDER BY id', [$lesson['id']]); ?>
          <section class="quiz-review">
            <h2>เฉลยและคำอธิบาย</h2><?php foreach ($reviewQuestions as $i => $q): $options = json_decode($q['options'], true); ?><div class="question-preview">
                <h3>ข้อที่ <?= $i + 1 ?></h3>
                <div class="rich-content"><?= RichText::render($q['prompt'], $q['content_format']) ?></div>
                <div class="quiz-correct"><strong>คำตอบที่ถูกต้อง: <?= ['ก', 'ข', 'ค', 'ง'][(int)$q['answer']] ?></strong>
                  <div class="rich-content"><?= RichText::render($options[(int)$q['answer']], $q['content_format']) ?></div>
                </div><?php if ($q['explanation']): ?><h4>คำอธิบาย</h4>
                  <div class="rich-content"><?= RichText::render($q['explanation'], $q['content_format']) ?></div><?php endif; ?>
              </div><?php endforeach; ?>
          </section>
        <?php elseif (!$manager): ?><p class="form-hint">เฉลยและคำอธิบายจะแสดงเมื่อสอบผ่านหรือใช้สิทธิ์สอบครบแล้ว</p><?php endif; ?>
        <?php if ($attempts): ?><div class="attempt-history">
            <h3>ประวัติการทำแบบทดสอบ</h3><?php foreach ($attempts as $a): ?><div class="info-row"><span><?= dateThai($a['started_at']) ?> <?= date('H:i', strtotime($a['started_at'])) ?></span><strong><?= $a['score'] === null ? 'กำลังทำ' : e($a['score']) . ' / 100' ?></strong></div><?php endforeach; ?>
          </div><?php endif; ?>
      <?php elseif (!$complete && !$manager && !$lesson['require_watch']): ?><form method="post" action="/learn/<?= $lesson['id'] ?>/complete"><?= csrfField() ?><button class="button full"><?= icon('check') ?>ฉันเรียนบทนี้เรียบร้อยแล้ว</button></form><?php endif; ?>
    </div>
    <div class="lesson-bottom-nav"><a class="button outline" href="/courses/<?= $lesson['course_id'] ?>"><?= icon('back') ?>หน้ารายวิชา</a><?php if ($next): $next['sequential'] = $lesson['sequential'];
                                                                                                                                              $nextUnlocked = $manager || Learning::unlocked($next, $uid); ?><a class="button <?= $nextUnlocked ? '' : 'disabled-link' ?>" <?= $nextUnlocked ? 'href="/learn/' . $next['id'] . '"' : 'aria-disabled="true"' ?> id="next-lesson" data-href="/learn/<?= $next['id'] ?>">กิจกรรมถัดไป <?= icon($nextUnlocked ? 'arrow' : 'lock') ?></a><?php else: ?><a class="button outline" href="/courses/<?= $lesson['course_id'] ?>">ดูความคืบหน้าหลักสูตร <?= icon('award') ?></a><?php endif; ?></div>
  </section>
  <aside class="classroom-aside panel">
    <h3>เนื้อหารายวิชา</h3>
    <p class="form-hint"><?= e($lesson['course_title']) ?></p>
    <nav><?php foreach ($lessons as $l): $l['sequential'] = $lesson['sequential'];
            $unlocked = $manager || Learning::unlocked($l, $uid); ?><a class="classroom-nav <?= (int)$l['id'] === (int)$lesson['id'] ? 'current' : '' ?> <?= !$unlocked ? 'disabled-link' : '' ?>" <?= $unlocked ? 'href="/learn/' . $l['id'] . '"' : 'aria-disabled="true"' ?>><span><?= icon($l['completed_at'] ? 'check' : ($unlocked ? ($l['kind'] === 'video' ? 'play' : 'file') : 'lock')) ?></span>
          <div><?= e($l['title']) ?><small><?= $l['completed_at'] ? 'สำเร็จแล้ว' : ($unlocked ? 'พร้อมเรียน' : 'เรียนกิจกรรมก่อนหน้าให้ผ่าน') ?></small></div>
        </a><?php endforeach; ?></nav>
  </aside>
</div>
<?php require __DIR__ . '/layout-bottom.php'; ?>