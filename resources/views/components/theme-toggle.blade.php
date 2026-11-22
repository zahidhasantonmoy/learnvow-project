{{-- Dark-mode toggle: flips data-theme on <html> and remembers it in the lv_theme cookie (1 year). --}}
<button type="button" class="theme-btn" onclick="(function(){var h=document.documentElement;var next=h.getAttribute('data-theme')==='dark'?'light':'dark';h.setAttribute('data-theme',next);document.cookie='lv_theme='+next+';path=/;max-age='+(60*60*24*365);})()">◐</button>
