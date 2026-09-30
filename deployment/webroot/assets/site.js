(()=>{document.addEventListener('click',e=>{document.querySelectorAll('details[open]').forEach(d=>{if(!d.contains(e.target))d.removeAttribute('open')})});document.addEventListener('keydown',e=>{if(e.key==='Escape')document.querySelectorAll('details[open]').forEach(d=>d.removeAttribute('open'))});})();
document.addEventListener('input',e=>{
  const q=e.target;
  if(!(q instanceof HTMLInputElement)||q.id!=='need-q')return;
  const n=q.value.trim().toLowerCase();
  const filter=(nodes)=>{
    const list=Array.from(nodes);
    if(n===''){list.forEach(el=>{el.hidden=false});return;}
    const scored=list.map(el=>{
      const hay=((el.getAttribute('data-search')||'')+' '+(el.getAttribute('data-area')||'')+' '+(el.textContent||'')).toLowerCase();
      return [el, hay.includes(n)];
    });
    const any=scored.some(([,ok])=>ok);
    scored.forEach(([el,ok])=>{el.hidden=any?!ok:false;});
  };
  filter(document.querySelectorAll('[data-area]'));
  filter(document.querySelectorAll('[data-service-card]'));
});
