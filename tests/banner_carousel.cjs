const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
function element() {
  return {hidden:false, matches:()=>false, events:{}, attrs:{}, textContent:'', addEventListener(k,f){this.events[k]=f}, setAttribute(k,v){this.attrs[k]=v}};
}
for (const reduced of [false,true]) {
  const slides=[element(),element(),element()]; slides[1].hidden=slides[2].hidden=true;
  const dots=slides.map(element);
  const controls=element(), imageArea=element(), prev=element(), next=element(), status=element();
  controls.hidden=true;
  const carousel=element();
  carousel.querySelectorAll=s=>s==='.banner-slide'?slides:dots;
  carousel.querySelector=s=>({'.banner-controls':controls,'.banner-slides':imageArea,'[data-carousel-prev]':prev,'[data-carousel-next]':next,'[data-carousel-status]':status}[s]);
  carousel.contains=e=>e===next;
  const document=element(); document.hidden=false; document.activeElement=null;
  document.querySelectorAll=s=>s==='[data-carousel]'?[carousel]:[];
  const motion=element(); motion.matches=reduced;
  let tick=null;
  const context={document,window:{matchMedia:()=>motion},setInterval:f=>{tick=f;return 1},clearInterval:()=>{tick=null},setTimeout:f=>f()};
  const source=fs.readFileSync('public/assets/app.js','utf8');
  new vm.Script(source); // Parse the whole application, then run the isolated carousel block.
  vm.runInNewContext(source.slice(source.indexOf('// Homepage carousel:')),context);
  assert.equal(controls.hidden,false);
  assert.equal(Boolean(tick),!reduced);
  next.events.click(); assert.equal(slides[1].hidden,false); assert.equal(dots[1].attrs['aria-current'],'true');
  prev.events.click(); prev.events.click(); assert.equal(slides[2].hidden,false);
  dots[0].events.click(); assert.equal(slides[0].hidden,false);
  if(reduced) { motion.matches=false; motion.events.change(); }
  tick(); assert.equal(slides[1].hidden,false);
  imageArea.events.mouseenter(); assert.equal(tick,null);
  imageArea.events.mouseleave(); assert.equal(typeof tick,'function');
  document.activeElement=next; carousel.events.focusin(); assert.equal(tick,null);
  document.activeElement=null; carousel.events.focusout(); assert.equal(typeof tick,'function');
  document.hidden=true; document.events.visibilitychange(); assert.equal(tick,null);
  document.hidden=false; document.events.visibilitychange();
  motion.matches=true; motion.events.change(); assert.equal(tick,null);
}
console.log('PASS carousel navigation, wrapping, auto-play, pause, hover/focus, visibility and reduced motion');
