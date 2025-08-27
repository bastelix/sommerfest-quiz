(function(){
  function safeEventData(ev){
    const title = String(ev.title || '');
    const start = new Date(ev.start);
    const end = new Date(ev.end);
    const tOpts = { hour: '2-digit', minute: '2-digit' };
    const startStr = start.toLocaleTimeString('de-DE', tOpts);
    const endStr = end.toLocaleTimeString('de-DE', tOpts);
    const shortTitle = title.length > 14 ? title.slice(0, 12) + '…' : title;
    return {
      id: ev.id,
      title,
      shortTitle,
      rangeLabel: `${startStr}–${endStr} ${shortTitle}`,
      when: start.toLocaleString('de-DE', {
        weekday: 'short',
        day: '2-digit',
        month: '2-digit',
        hour: '2-digit',
        minute: '2-digit'
      })
    };
  }
  window.safeEventData = safeEventData;
})();
