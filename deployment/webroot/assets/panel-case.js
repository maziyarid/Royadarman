(() => {
  const root=document.querySelector('[data-panel-case]'); if(!root) return;
  const csrf=document.querySelector('meta[name="csrf-token"]')?.content||'', locale=root.dataset.locale, caseId=root.dataset.caseId, sourceLanguage=root.dataset.sourceLanguage, errorFallback=root.dataset.error;
  let caseVersion=Number(root.dataset.caseVersion||1); const notice=document.getElementById('notice');
  const show=(text,error=false)=>{if(!notice)return;notice.textContent=text;notice.className='notice'+(error?' error':'');notice.classList.remove('is-hidden');window.scrollTo({top:0,behavior:'smooth'})};
  const askPolicy=async (content) => {
    if (typeof window.royadarmanDialog === 'function') {
      return window.royadarmanDialog({
        title: root.dataset.accept || '',
        body: content,
        confirmLabel: root.dataset.accept || 'OK',
        cancelLabel: root.dataset.cancel || 'Cancel',
      });
    }
    return false;
  };
  const request=async(url,{method='GET',body=null,language=locale,multipart=false}={})=>{const headers={'Accept':'application/json','X-CSRF-TOKEN':csrf,'X-Locale':language};if(!multipart&&body!==null)headers['Content-Type']='application/json';const response=await fetch(url,{method,credentials:'same-origin',headers,body:body===null?null:(multipart?body:JSON.stringify(body))});const data=await response.json().catch(()=>({}));if(!response.ok)throw new Error(data?.error?.message||data?.error?.code||data?.message||errorFallback);return data};
  document.querySelector('[data-submit-case]')?.addEventListener('click',async()=>{try{const policy=await request('/api/v1/policies/case_coordination',{language:sourceLanguage});if(!await askPolicy(policy.data.content))return;await request(`/api/v1/cases/${caseId}/submit`,{method:'POST',language:sourceLanguage,body:{version:caseVersion,policy_version:policy.data.version,content_hash:policy.data.content_hash}});location.reload()}catch(e){show(e.message,true)}});
  const upload=document.getElementById('upload-form');upload?.addEventListener('submit',async e=>{e.preventDefault();try{const policy=await request('/api/v1/policies/opg_document_sharing',{language:sourceLanguage});if(!await askPolicy(policy.data.content))return;await request(`/api/v1/cases/${caseId}/consent/opg_document_sharing`,{method:'POST',language:sourceLanguage,body:{policy_version:policy.data.version,content_hash:policy.data.content_hash,locale:sourceLanguage}});await request(`/api/v1/cases/${caseId}/documents`,{method:'POST',language:sourceLanguage,body:new FormData(upload),multipart:true});location.reload()}catch(err){show(err.message,true)}});
  document.querySelectorAll('[data-referral-decision]').forEach(btn=>btn.addEventListener('click',async()=>{const id=btn.dataset.referralId,language=btn.dataset.language,decision=btn.dataset.referralDecision;try{let body={decision};if(decision==='accepted'){const policy=await request('/api/v1/policies/referral_sharing',{language});if(!await askPolicy(policy.data.content))return;body={...body,policy_version:policy.data.version,content_hash:policy.data.content_hash}}await request(`/api/v1/cases/${caseId}/referrals/${id}/decision`,{method:'POST',language,body});location.reload()}catch(e){show(e.message,true)}}));
  const statusForm=document.getElementById('status-form');statusForm?.addEventListener('submit',async e=>{e.preventDefault();const f=new FormData(statusForm);try{await request(`/api/v1/staff/cases/${caseId}/status`,{method:'PATCH',body:{status:f.get('status'),reason:f.get('reason')||null,version:caseVersion}});location.reload()}catch(err){show(err.message,true)}});
  const assignForm=document.getElementById('assign-form');assignForm?.addEventListener('submit',async e=>{e.preventDefault();const f=new FormData(assignForm);try{await request(`/api/v1/staff/cases/${caseId}/assignments`,{method:'POST',body:{assignee_user_id:f.get('assignee_user_id'),purpose:'clinical_review',version:caseVersion}});location.reload()}catch(err){show(err.message,true)}});
  const referralForm=document.getElementById('referral-form');referralForm?.addEventListener('submit',async e=>{e.preventDefault();const f=new FormData(referralForm);try{await request(`/api/v1/staff/cases/${caseId}/referral-proposals`,{method:'POST',body:{clinic_id:f.get('clinic_id'),reasoning:f.get('reasoning'),source_language:sourceLanguage}});location.reload()}catch(err){show(err.message,true)}});
  const reviewForm=document.getElementById('review-form');reviewForm?.addEventListener('submit',async e=>{e.preventDefault();try{await request(`/api/v1/staff/cases/${caseId}/reviews`,{method:'POST',body:Object.fromEntries(new FormData(reviewForm)),language:sourceLanguage});location.reload()}catch(err){show(err.message,true)}});
  document.querySelectorAll('[data-publish-review]').forEach(btn=>btn.addEventListener('click',async()=>{try{await request(`/api/v1/staff/cases/${caseId}/reviews/${btn.dataset.publishReview}/publish`,{method:'POST',language:sourceLanguage,body:{}});location.reload()}catch(e){show(e.message,true)}}));
})();
