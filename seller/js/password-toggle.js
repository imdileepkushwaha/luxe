(function () {
  function syncUi(input, btn) {
    var visible = input.type === 'text';
    btn.classList.toggle('seller-password-toggle--on', visible);
    btn.setAttribute('aria-pressed', visible ? 'true' : 'false');
    btn.setAttribute('aria-label', visible ? 'Hide password' : 'Show password');
  }

  function initWrap(wrap) {
    if (wrap.dataset.luxePwToggleInit === '1') return;
    wrap.dataset.luxePwToggleInit = '1';

    var input = wrap.querySelector('input');
    var btn = wrap.querySelector('.seller-password-toggle');
    if (!input || !btn) return;

    btn.addEventListener('click', function (e) {
      e.preventDefault();
      e.stopPropagation();
      input.type = input.type === 'password' ? 'text' : 'password';
      syncUi(input, btn);
    });
    syncUi(input, btn);
  }

  document.querySelectorAll('.seller-password-wrap').forEach(initWrap);
})();
