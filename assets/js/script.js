/**
 * SMS-SHULE — assets/js/script.js
 * JavaScript ndogo ya interactivity (hakuna frameworks):
 *  1. Sidebar ya simu (fungua/funga na overlay)
 *  2. Tabs bila reload (kwenye child_view.php)
 *  3. Alerts kujificha baada ya sekunde 5
 *  4. Uthibitisho kabla ya kufuta (.js-confirm)
 *  5. Preview ya picha kabla ya ku-upload (input[data-preview])
 */
document.addEventListener('DOMContentLoaded', function () {

  /* ---------- 1. Sidebar (mobile) ---------- */
  var hamburger = document.getElementById('hamburger');
  var sidebar   = document.getElementById('sidebar');
  var overlay   = document.getElementById('overlay');

  function closeSidebar() {
    if (sidebar) sidebar.classList.remove('open');
    if (overlay) overlay.classList.remove('show');
  }
  if (hamburger && sidebar) {
    hamburger.addEventListener('click', function () {
      sidebar.classList.toggle('open');
      overlay.classList.toggle('show');
    });
    if (overlay) overlay.addEventListener('click', closeSidebar);
  }

  /* ---------- 2. Tabs (bila reload) ---------- */
  document.querySelectorAll('.tab-btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
      document.querySelectorAll('.tab-btn').forEach(function (b) { b.classList.remove('active'); });
      document.querySelectorAll('.tab-panel').forEach(function (p) { p.classList.remove('active'); });
      btn.classList.add('active');
      var panel = document.getElementById(btn.dataset.tab);
      if (panel) panel.classList.add('active');
    });
  });

  /* ---------- 3. Alerts kujificha ---------- */
  document.querySelectorAll('.alert').forEach(function (el) {
    setTimeout(function () {
      el.style.opacity = '0';
      setTimeout(function () { el.remove(); }, 400);
    }, 5000);
  });

  /* ---------- 4. Confirm kabla ya kufuta ---------- */
  document.querySelectorAll('.js-confirm').forEach(function (el) {
    el.addEventListener('click', function (e) {
      if (!confirm(el.dataset.confirm || 'Una uhakika?')) {
        e.preventDefault();
      }
    });
  });

  /* ---------- 5. Preview ya picha ---------- */
  var imgInput = document.querySelector('input[data-preview]');
  if (imgInput) {
    imgInput.addEventListener('change', function () {
      var box = document.getElementById(imgInput.dataset.preview);
      if (box && imgInput.files && imgInput.files[0]) {
        box.src = URL.createObjectURL(imgInput.files[0]);
        box.style.display = 'inline-block';
      }
    });
  }
});
