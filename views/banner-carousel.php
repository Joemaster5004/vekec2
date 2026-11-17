<?php $banners = \App\Banner::all(true); ?>
<?php if (!$banners): ?>
<section class="hero"><div class="hero-copy"><div class="eyebrow"><span></span> YOUR NEXT CHAPTER STARTS HERE</div><h1>เรียนรู้ทักษะใหม่<br><em>สร้างโอกาสให้อนาคต</em></h1><p>พื้นที่การเรียนรู้สำหรับทุกคน จากวิทยาลัยเทคนิคกำแพงเพชร<br class="desktop-only">เติมทักษะอาชีพที่ใช่ ก้าวต่อไปในเส้นทางที่คุณเลือก</p><a href="<?= !empty($homeSections['course_catalog'])?'#courses':'/courses' ?>" class="button light">ค้นหารายวิชาที่ใช่ <?= icon('arrow') ?></a><div class="hero-tags"><span><?= icon('check') ?>เรียนรู้ได้ทุกที่</span><span><?= icon('check') ?>มีใบประกาศนียบัตร</span></div></div><div class="hero-steps"><span class="pill pale">LEARN. GROW. GO FURTHER.</span><h2>โอกาสใหม่<br>เริ่มจากการเรียนรู้</h2><div class="step"><span>01</span><div><strong>เลือกสิ่งที่อยากเรียน</strong><small>สำรวจเนื้อหาก่อนลงทะเบียน</small></div></div><div class="step"><span>02</span><div><strong>เรียนรู้ ลงมือทำ</strong><small>พัฒนาทักษะผ่านบทเรียนและกิจกรรม</small></div></div><div class="step"><span>03</span><div><strong>ต่อยอดสู่อนาคต</strong><small>รับใบประกาศเมื่อเรียนครบและผ่านเกณฑ์</small></div></div></div></section>
<?php else: ?>
<section class="banner-carousel" data-carousel role="region" aria-roledescription="คารูเซล" aria-label="ข่าวสารและประชาสัมพันธ์">
    <div class="banner-slides" aria-live="off">
    <?php foreach ($banners as $index => $banner): ?>
        <div class="banner-slide" role="group" aria-roledescription="สไลด์" aria-label="<?= $index + 1 ?> จาก <?= count($banners) ?>" <?= $index ? 'hidden' : '' ?>>
            <?php if ($banner['link_url']): ?><a href="<?= App\e($banner['link_url']) ?>"><?php endif; ?>
            <img src="/media/banners/<?= (int)$banner['id'] ?>" alt="<?= App\e($banner['title']) ?>" width="1600" height="640" <?= $index ? 'loading="lazy"' : 'fetchpriority="high"' ?>>
            <?php if ($banner['link_url']): ?></a><?php endif; ?>
        </div>
    <?php endforeach; ?>
    </div>
    <?php if (count($banners) > 1): ?>
    <div class="banner-controls" hidden>
        <button type="button" data-carousel-prev aria-label="Banner ก่อนหน้า">‹</button>
        <div class="banner-dots"><?php foreach ($banners as $index => $banner): ?><button type="button" data-carousel-go="<?= $index ?>" aria-label="ไป Banner <?= $index + 1 ?>" aria-current="<?= $index ? 'false' : 'true' ?>"></button><?php endforeach; ?></div>
        <button type="button" data-carousel-next aria-label="Banner ถัดไป">›</button>
        <span class="sr-only" data-carousel-status aria-live="polite"></span>
    </div>
    <?php endif; ?>
</section>
<?php endif; ?>
