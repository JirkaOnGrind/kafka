(() => {
  'use strict';
  document.documentElement.dataset.theme = 'black';
  document.documentElement.style.colorScheme = 'dark';
  try { localStorage.removeItem('kafka-theme'); } catch { }
  const url = new URL(location.href);
  if (url.searchParams.has('theme')) {
    url.searchParams.delete('theme');
    history.replaceState(history.state, '', url);
  }
})();
