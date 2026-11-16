'use strict';
$(function(){
  $(document).on('change','.catalog-filters select[name="category"]',function(){this.form.requestSubmit();});
  $('.mobile-menu').on('click',function(){const open=!$('.sidebar').hasClass('open');$('.sidebar,.sidebar-scrim').toggleClass('open',open);$(this).attr('aria-expanded',String(open));});
  $('.sidebar-scrim').on('click',function(){$('.sidebar,.sidebar-scrim').removeClass('open');$('.mobile-menu').attr('aria-expanded','false').trigger('focus');});
  $(document).on('keydown',function(e){if(e.key==='Escape')$('.sidebar-scrim.open').trigger('click');});
  $('[data-back]').on('click',()=>history.length>1?history.back():location.assign('/'));
  $('[data-print]').on('click',()=>window.print());
  $('.answer-option').on('click',function(e){if(!$(e.target).closest('a,button,input').length&&!window.getSelection()?.toString())$(this).find('input[type="radio"]').prop('checked',true).trigger('change');});
  $('form[data-confirm]').on('submit',function(e){if(!window.confirm($(this).attr('data-confirm')))e.preventDefault();});
  $('[data-toggle-password]').on('click',function(){const input=document.getElementById($(this).attr('data-toggle-password'));const show=input.type==='password';input.type=show?'text':'password';$(this).text(show?'ซ่อน':'แสดง').attr('aria-label',show?'ซ่อนรหัสผ่าน':'แสดงรหัสผ่าน');});
  $('#lesson-kind').on('change',function(){const kind=this.value;$('[data-for-kind]').each(function(){const visible=$(this).attr('data-for-kind').split(' ').includes(kind);$(this).prop('hidden',!visible).find('input,select,textarea').prop('disabled',!visible);});}).trigger('change');
  $('#verify-form').on('submit',function(e){e.preventDefault();const code=this.elements.code.value.trim().toLowerCase();if(/^[a-f0-9]{32}$/.test(code))location.assign('/verify/'+code);});
  const timer=document.querySelector('[data-deadline]');
  if(timer){const remainingAtLoad=Number(timer.dataset.deadline)-Number(timer.dataset.serverNow);const began=performance.now();const update=()=>{const seconds=Math.max(0,Math.ceil(remainingAtLoad-(performance.now()-began)/1000));$('#quiz-countdown').text(String(Math.floor(seconds/60)).padStart(2,'0')+':'+String(seconds%60).padStart(2,'0'));if(seconds===0){clearInterval(ticking);document.getElementById('quiz-form').requestSubmit();}};const ticking=setInterval(update,1000);update();}
  const csrf=$('meta[name="csrf-token"]').attr('content');
  function notify(message){$('#toast').text(message).prop('hidden',false);setTimeout(()=>$('#toast').prop('hidden',true),5500);}
  function post(path,data){return $.ajax({url:path,method:'POST',data,headers:{'X-CSRF-Token':csrf},dataType:'json'});}
  const wrapper=document.querySelector('.video-wrapper');
  if(wrapper){
    const required=wrapper.dataset.required==='1';let ready=!required,token='',highWater=Number(wrapper.dataset.start||0),busy=false,finished=false,seek=()=>{},position=()=>0,playing=()=>false,pause=()=>{};
    const start=async()=>{if(!required)return;try{const r=await post('/api/lessons/'+wrapper.dataset.lesson+'/video/start',{});token=r.token;highWater=r.position;seek(highWater);ready=true;if(r.completed)done();}catch(e){$('#video-status-text').text(e.responseJSON?.error||'เชื่อมต่อไม่สำเร็จ กรุณาโหลดหน้าใหม่');}};
    const done=()=>{if(finished)return;finished=true;$('#video-status-text').text('ดูวิดีโอครบแล้ว บันทึกผลการเรียนสำเร็จ');const next=$('#next-lesson');next.removeClass('disabled-link').removeAttr('aria-disabled').attr('href',next.attr('data-href'));notify('เรียนวิดีโอบทนี้สำเร็จแล้ว');};
    const tick=async(force=false)=>{if(!required||!ready||busy||finished||(!force&&!playing()))return;busy=true;try{const r=await post('/api/lessons/'+wrapper.dataset.lesson+'/video/tick',{token,position:position()});highWater=r.position;if(r.reset){seek(highWater);notify('กรุณารับชมตามลำดับ ระบบได้กลับไปยังตำแหน่งล่าสุด');}if(r.completed)done();}catch(e){pause();$('#video-status-text').text(e.responseJSON?.error||'บันทึกความคืบหน้าไม่สำเร็จ กรุณาตรวจสอบการเชื่อมต่อและโหลดใหม่');}finally{busy=false;}};
    const video=document.getElementById('lesson-video');
    if(video){seek=s=>{video.currentTime=s;};position=()=>video.currentTime;playing=()=>!video.paused&&!video.ended;pause=()=>video.pause();video.addEventListener('loadedmetadata',()=>{if(!ready)start();},{once:true});if(video.readyState>=1&&!ready)start();video.addEventListener('seeking',()=>{if(required&&!finished&&ready&&video.currentTime>highWater+7)video.currentTime=highWater;});video.addEventListener('ratechange',()=>{if(required&&video.playbackRate!==1)video.playbackRate=1;});video.addEventListener('play',()=>{if(required&&!ready)video.pause();});video.addEventListener('ended',()=>tick(true));}
    const yt=document.getElementById('youtube-player');
    if(yt){window.onYouTubeIframeAPIReady=()=>{const player=new YT.Player('youtube-player',{videoId:yt.dataset.videoId,playerVars:{playsinline:1,rel:0,origin:location.origin},events:{onReady:()=>{seek=s=>player.seekTo(s,true);position=()=>player.getCurrentTime();playing=()=>player.getPlayerState()===1;pause=()=>player.pauseVideo();start();},onStateChange:event=>{if(required&&!ready&&event.data===1)player.pauseVideo();if(event.data===0)tick(true);},onPlaybackRateChange:()=>{if(required)player.setPlaybackRate(1);},onError:()=>$('#video-status-text').text('ไม่สามารถเล่น YouTube ได้ กรุณาติดต่อผู้สอนเพื่อตรวจสอบสิทธิ์วิดีโอ')}});};const script=document.createElement('script');script.src='https://www.youtube.com/iframe_api';document.head.appendChild(script);}
    if(required){setInterval(()=>tick(),5000);document.addEventListener('visibilitychange',()=>{if(document.hidden){tick(true);pause();}});}
  }
  // Optional browser agent interface; uses the catalog's real GET search and visible results.
  if(document.modelContext?.registerTool&&document.querySelector('.catalog-section')){
    const lifecycle=new AbortController();window.addEventListener('pagehide',()=>lifecycle.abort(),{once:true});
    try{Promise.resolve(document.modelContext.registerTool({name:'search_courses',title:'ค้นหารายวิชา',description:'Search published LMS courses and update the visible catalog. Does not enroll the learner.',inputSchema:{type:'object',properties:{query:{type:'string',maxLength:200},level:{type:'string',enum:['','voc1','voc2','public']}},required:['query'],additionalProperties:false},annotations:{readOnlyHint:true,untrustedContentHint:true},async execute(input){if(!input||typeof input.query!=='string'||input.query.length>200||!['','voc1','voc2','public'].includes(input.level??''))throw new Error('Invalid course search');const params=new URLSearchParams({q:input.query,level:input.level??''});const response=await fetch('/?'+params);if(!response.ok)throw new Error('Course search unavailable');const doc=new DOMParser().parseFromString(await response.text(),'text/html');const result=doc.querySelector('.catalog-section');if(!result)throw new Error('Invalid catalog response');document.querySelector('.catalog-section').replaceWith(result);history.replaceState(null,'','/?'+params+'#courses');result.scrollIntoView({behavior:'smooth'});return {courses:Array.from(result.querySelectorAll('.course-card h3 a')).map(a=>({title:a.textContent,url:a.getAttribute('href')}))};}},{signal:lifecycle.signal})).catch(()=>{});}catch(e){/* Unsupported implementations must not affect the learning experience. */}
  }
});

// Homepage carousel: pause for interaction and honor reduced-motion preferences.
document.querySelectorAll('[data-carousel]').forEach(carousel => {
  const slides = [...carousel.querySelectorAll('.banner-slide')];
  const controls = carousel.querySelector('.banner-controls');
  if (!controls || slides.length < 2) return;
  controls.hidden = false;
  const dots = [...carousel.querySelectorAll('[data-carousel-go]')];
  const imageArea = carousel.querySelector('.banner-slides');
  const motion = window.matchMedia('(prefers-reduced-motion: reduce)');
  let index = 0, hovering = imageArea.matches(':hover'), timer;
  const refresh = () => {
    clearInterval(timer);
    if (!motion.matches && !hovering && !document.hidden && !carousel.contains(document.activeElement)) timer = setInterval(() => show(index + 1), 6000);
  };
  const show = (next, announce = false) => {
    index = (next + slides.length) % slides.length;
    slides.forEach((slide, i) => { slide.hidden = i !== index; dots[i].setAttribute('aria-current', String(i === index)); });
    if (announce) carousel.querySelector('[data-carousel-status]').textContent = `Banner ${index + 1} จาก ${slides.length}`;
  };
  carousel.querySelector('[data-carousel-prev]').addEventListener('click', () => { show(index - 1, true); refresh(); });
  carousel.querySelector('[data-carousel-next]').addEventListener('click', () => { show(index + 1, true); refresh(); });
  dots.forEach((dot, i) => dot.addEventListener('click', () => { show(i, true); refresh(); }));
  imageArea.addEventListener('mouseenter', () => { hovering = true; refresh(); });
  imageArea.addEventListener('mouseleave', () => { hovering = false; refresh(); });
  carousel.addEventListener('focusin', refresh);
  carousel.addEventListener('focusout', () => setTimeout(refresh, 0));
  document.addEventListener('visibilitychange', refresh);
  motion.addEventListener('change', refresh);
  refresh();
});

document.querySelectorAll('[data-banner-form]').forEach(form => {
  const input = form.querySelector('[data-banner-image]');
  const preview = form.querySelector('[data-banner-preview]');
  const status = form.querySelector('[data-banner-dimensions]');
  const initialPreview = preview.innerHTML, initialStatus = status.textContent;
  let selection = 0;
  input.addEventListener('change', () => {
    const current = ++selection;
    input.setCustomValidity('');
    preview.innerHTML = initialPreview; preview.hidden = !initialPreview; status.textContent = initialStatus;
    const file = input.files[0];
    if (!file) return;
    const fail = message => { input.setCustomValidity(message); status.textContent = message; };
    if (!['image/jpeg','image/png','image/webp'].includes(file.type) || file.size > 5 * 1024 * 1024) {
      fail('กรุณาเลือก JPG, PNG หรือ WebP ขนาดไม่เกิน 5 MB'); return;
    }
    const reader = new FileReader();
    reader.onload = () => {
      if (current !== selection) return;
      const image = new Image();
      image.onload = () => {
        if (current !== selection) return;
        if (image.naturalWidth * image.naturalHeight > 20000000) { fail('ภาพต้องไม่เกิน 20 ล้านพิกเซล'); return; }
        preview.replaceChildren(image); preview.hidden = false;
        status.textContent = `ภาพที่เลือก: ${image.naturalWidth} × ${image.naturalHeight} พิกเซล` + (Math.abs(image.naturalWidth / image.naturalHeight - 2.5) > 0.01 ? ' — สัดส่วนต่างจาก 5:2 จะมีพื้นที่ว่างในกรอบ แนะนำ 1600 × 640 พิกเซล' : ' — สัดส่วนเหมาะสม');
      };
      image.onerror = () => { if (current === selection) fail('ไม่สามารถอ่านไฟล์ภาพนี้ได้'); };
      image.alt = 'ตัวอย่าง Banner ที่เลือก'; image.src = reader.result;
    };
    reader.onerror = () => { if (current === selection) fail('ไม่สามารถอ่านไฟล์ภาพนี้ได้'); };
    reader.readAsDataURL(file);
  });
});
