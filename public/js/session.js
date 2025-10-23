(function(){
  function resolveHeaders(asJson){
    const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || window.csrfToken || '';
    const headers = {};
    if(asJson){
      headers['Content-Type'] = 'application/json';
    }
    if(token){
      headers['X-CSRF-Token'] = token;
    }
    return headers;
  }

  function postSession(path, payload){
    return fetch(`/session/${path}`, {
      method: 'POST',
      credentials: 'same-origin',
      headers: resolveHeaders(true),
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

  function deleteSession(path){
    return fetch(`/session/${path}`, {
      method: 'DELETE',
      credentials: 'same-origin',
      headers: resolveHeaders(false)
    }).then(async resp => {
      if(!resp.ok){
        const text = await resp.text();
        throw new Error(text || 'Session delete failed');
      }
      return null;
    }).catch(e => {
      console.error(`session/${path} delete failed`, e);
      throw e;
    });
  }
  globalThis.postSession = postSession;
  globalThis.deleteSession = deleteSession;
})();
