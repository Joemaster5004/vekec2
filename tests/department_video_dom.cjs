/* Minimal player integration checks without a browser or external requests. */
const vm = require('node:vm');
const fs = require('node:fs');
const assert = require('node:assert/strict');
const source = fs.readFileSync(__dirname + '/../public/assets/department-video.js', 'utf8');
const flush = async () => { for (let i=0;i<20;i++) await Promise.resolve(); };
async function run(mode) {
  const events = {}, requests = [], handlers = {};
  const status = {textContent:''}, registration={hidden:true}, button={disabled:true, addEventListener:(e,fn)=>handlers['confirm']=fn};
  const video={currentTime:0,playbackRate:1,addEventListener:(e,fn)=>events[e]=fn,pause:()=>{}};
  const external={addEventListener:(e,fn)=>handlers.open=fn};
  const element={dataset:{videoId:'abcdefghijk'}};
  const root={dataset:{guidanceVideo:'7',mode,active:'1'},querySelector:q=>q==='video'?video:q==='[data-guidance-status]'?status:q==='[data-guidance-confirm]'?button:external};
  let timer, ytConfig, yt;
  const context={console,URLSearchParams, setInterval:fn=>(timer=fn,1), clearInterval:()=>{}, document:{
    querySelector:q=>q==='[data-guidance-video]'?root:{content:'csrf'},
    getElementById:id=>id==='guidance-registration'?registration:element,
    createElement:()=>({}), head:{append:()=>{}}
  },fetch:async(url,options)=>{
    const params=new URLSearchParams(options.body);requests.push({url,params});
    const data=url.endsWith('/start')?{token:'token',position:0,completed:false}:{position:8,completed:true};
    return {ok:true,text:async()=>JSON.stringify(data)};
  },YT:{Player:function(el,config){ytConfig=config;yt={getCurrentTime:()=>8,seekTo:()=>{},pauseVideo:()=>{},setPlaybackRate:()=>{}};return yt;}}};
  context.window=context;
  vm.runInNewContext(source,context);await flush();
  assert.equal(registration.hidden,true);
  if(mode==='external'){
    assert.equal(requests.length,0);await handlers.open();assert.equal(button.disabled,false);await handlers.confirm();
    assert.equal(requests.at(-1).params.get('confirmed'),'1');
  }else if(mode==='mp4'){
    await events.play();video.currentTime=8;await timer();
    assert.equal(requests.at(-1).params.get('position'),'8');
    video.playbackRate=2;events.ratechange();assert.equal(video.playbackRate,1);
  }else{
    context.onYouTubeIframeAPIReady();ytConfig.events.onReady();await ytConfig.events.onStateChange({data:1});await timer();
    assert.equal(requests.at(-1).params.get('position'),'8');
  }
  assert.equal(registration.hidden,false);
  assert.equal(requests.at(-1).params.get('_csrf'),'csrf');
  assert.equal(requests.at(-1).params.get('token'),'token');
}
(async()=>{for(const mode of ['mp4','youtube','external'])await run(mode);console.log('Player integration checks passed for MP4, YouTube and external URL.');})().catch(e=>{console.error(e);process.exitCode=1;});
