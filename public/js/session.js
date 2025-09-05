(function(){
  function postSession(path, payload){
    const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || window.csrfToken || '';
    const headers = { 'Content-Type': 'application/json' };
    if(token){
      headers['X-CSRF-Token'] = token;
    }
    return fetch(`/session/${path}`, {
      method: 'POST',
      credentials: 'same-origin',
      headers,
      body: JSON.stringify(payload || {})
    }).then(async resp => {
      if(!resp.ok){
        const text = await resp.text();
        throw new Error(text || 'Session request failed');
      }
      const ct = resp.headers.get('Content-Type') || '';
      if(ct.includes('application/json')){
        return resp.json();
      }
      return null;
    }).catch(e => {
      console.error(`session/${path} request failed`, e);
      throw e;
    });
  }
  globalThis.postSession = postSession;
})();
