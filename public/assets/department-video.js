(() => {
  const root = document.querySelector('[data-guidance-video]');
  if (!root || root.dataset.active !== '1') return;
  const status = root.querySelector('[data-guidance-status]');
  const registration = document.getElementById('guidance-registration');
  const csrf = document.querySelector('meta[name="csrf-token"]').content;
  let token, player, timer, busy = false, completed = false;
  const api = async (action, values = {}) => {
    const response = await fetch(`/department-videos/${root.dataset.guidanceVideo}/${action}`, {
      method: 'POST', headers: {'Content-Type': 'application/x-www-form-urlencoded'},
      body: new URLSearchParams({_csrf: csrf, token: token || '', ...values})
    });
    const text = await response.text();
    let data;
    try { data = JSON.parse(text); } catch { throw new Error(response.status === 409 ? 'ข้อมูลวิดีโอเปลี่ยนแล้ว กรุณาโหลดหน้าใหม่' : 'บันทึกการรับชมไม่สำเร็จ กรุณาลองใหม่หรือโหลดหน้าใหม่'); }
    if (!response.ok) throw new Error(data.error || 'บันทึกการรับชมไม่สำเร็จ');
    return data;
  };
  const unlock = () => {
    completed = true; clearInterval(timer); registration.hidden = false;
    status.textContent = 'รับชมครบแล้ว กรอกข้อมูลด้านล่างเพื่อรับเกียรติบัตร';
  };
  const tick = async () => {
    if (busy || completed || !token || !player) return;
    busy = true;
    try {
      const result = await api('tick', {position: player.position()});
      if (result.reset) {
        player.seek(result.position);
        status.textContent = 'กรุณารับชมตามลำดับ ระบบกลับไปยังตำแหน่งที่รับชมแล้ว';
      } else status.textContent = 'กำลังบันทึกการรับชม ดูจนจบเพื่อรับเกียรติบัตร';
      if (result.completed) unlock();
    } catch (error) { status.textContent = error.message; }
    finally { busy = false; }
  };
  const start = async () => {
    const result = await api('start'); token = result.token;
    if (result.completed) unlock();
    return result.position;
  };
  const initialize = async () => {
    if (root.dataset.mode === 'external') {
      const open = root.querySelector('[data-guidance-external]');
      const confirm = root.querySelector('[data-guidance-confirm]');
      open.addEventListener('click', async () => {
        try { await start(); confirm.disabled = false; status.textContent = 'เมื่อดูจบแล้ว กลับมากดยืนยันการรับชมด้านล่าง'; }
        catch (error) { status.textContent = error.message; }
      });
      confirm.addEventListener('click', async () => {
        confirm.disabled = true;
        try { const result = await api('tick', {confirmed: '1'}); if (result.completed) unlock(); }
        catch (error) { status.textContent = error.message; confirm.disabled = false; }
      });
      return;
    }
    if (root.dataset.mode === 'mp4') {
      const video = root.querySelector('video');
      // Start the server clock when playback starts, including after pausing.
      video.addEventListener('play', async () => {
        try {
          const position = await start();
          if (!completed && (video.currentTime > position + 1 || video.currentTime === 0)) video.currentTime = position;
        } catch (error) { video.pause(); status.textContent = error.message; }
      });
      video.addEventListener('ratechange', () => { if (video.playbackRate > 1) video.playbackRate = 1; });
      video.addEventListener('ended', tick);
      video.addEventListener('error', () => { status.textContent = 'ไม่สามารถเล่นวิดีโอนี้ได้ กรุณาลองใหม่หรือติดต่อผู้ดูแลระบบ'; });
      player = {position: () => video.currentTime, seek: value => {video.currentTime = value;}};
    } else {
      const element = document.getElementById('guidance-youtube');
      window.onYouTubeIframeAPIReady = () => {
        const yt = new YT.Player(element, {
          videoId: element.dataset.videoId, playerVars: {playsinline: 1, rel: 0},
          events: {
            onReady: () => { player = {position: () => yt.getCurrentTime(), seek: value => yt.seekTo(value, true)}; },
            onStateChange: async event => {
              if (event.data === 1) {
                try { const position = await start(); if (!completed && yt.getCurrentTime() > position + 1) yt.seekTo(position, true); }
                catch (error) { yt.pauseVideo(); status.textContent = error.message; }
              }
              if (event.data === 0) tick();
            },
            onPlaybackRateChange: event => { if (event.data > 1) yt.setPlaybackRate(1); },
            onError: () => { status.textContent = 'ไม่สามารถเล่น YouTube ได้ กรุณาตรวจสอบการอนุญาตฝังวิดีโอหรือติดต่อผู้ดูแลระบบ'; }
          }
        });
      };
      const script = document.createElement('script'); script.src = 'https://www.youtube.com/iframe_api'; document.head.append(script);
    }
    timer = setInterval(tick, 2000);
    // Restore an already completed session when reloading the page.
    await start();
  };
  initialize().catch(error => { status.textContent = error.message; });
})();
