(() => {
  'use strict';
  const sheets = [...document.querySelectorAll('.certificate-sheet')];
  if (!sheets.length) return;
  const fit = sheet => {
    const width = sheet.clientWidth;
    if (!width) return;
    sheet.querySelectorAll('[data-cert-fit]').forEach(element => {
      element.style.removeProperty('font-size');
      let size = parseFloat(getComputedStyle(element).fontSize) / width * 100;
      for (let attempt = 0; attempt < 24 && size > .8 && (element.scrollHeight > element.clientHeight + 1 || element.scrollWidth > element.clientWidth + 1); attempt++) {
        size *= .93;
        element.style.fontSize = size + 'cqw';
      }
    });
  };
  let pending;
  const update = () => { cancelAnimationFrame(pending); pending = requestAnimationFrame(() => sheets.forEach(fit)); };
  if ('ResizeObserver' in window) { const observer = new ResizeObserver(update); sheets.forEach(sheet => observer.observe(sheet)); }
  document.addEventListener('certificate:changed', update);
  window.addEventListener('beforeprint', () => sheets.forEach(fit));
  window.addEventListener('afterprint', update);
  document.fonts?.ready.then(update);
  update();
})();
