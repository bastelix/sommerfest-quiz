(function(){
  function createConfettiPiece(container, colors){
    const div = document.createElement('div');
    const size = Math.random() * 8 + 4;
    div.style.position = 'absolute';
    div.style.width = size + 'px';
    div.style.height = size + 'px';
    div.style.backgroundColor = colors[Math.floor(Math.random()*colors.length)];
    div.style.top = '-10px';
    div.style.left = Math.random() * 100 + '%';
    div.style.opacity = Math.random() * 0.5 + 0.5;
    div.style.transform = 'rotate(' + Math.random()*360 + 'deg)';
    div.style.borderRadius = '50%';
    container.appendChild(div);
    const fall = div.animate([
      { transform: div.style.transform, top: '-10px' },
      { transform: 'rotate(' + Math.random()*360 + 'deg)', top: window.innerHeight + 20 + 'px' }
    ], {
      duration: Math.random()*3000 + 3000,
      easing: 'ease-out',
      delay: Math.random()*2000
    });
    fall.onfinish = () => div.remove();
  }

  window.startConfetti = function(){
    const colors = ['#fce18a', '#ff726d', '#b48def', '#f4306d', '#43d9ad'];
    const num = 150;
    const container = document.createElement('div');
    container.style.position = 'fixed';
    container.style.pointerEvents = 'none';
    container.style.overflow = 'hidden';
    container.style.top = 0;
    container.style.left = 0;
    container.style.width = '100%';
    container.style.height = '100%';
    document.body.appendChild(container);
    for(let i=0;i<num;i++){
      createConfettiPiece(container, colors);
    }
    setTimeout(() => container.remove(), 8000);
  };
})();
