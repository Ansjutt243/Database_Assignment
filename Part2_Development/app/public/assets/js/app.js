// ML IT Helpdesk - small enhancements. Every page still works without
// JavaScript because the server repeats all checks.
(function () {
  'use strict';

  // Verification (Design Doc 8.4): forms with data-confirm ask "Are you sure?"
  document.querySelectorAll('form[data-confirm]').forEach(function (form) {
    form.addEventListener('submit', function (ev) {
      if (!window.confirm(form.dataset.confirm)) { ev.preventDefault(); }
    });
  });

  // Print buttons on reports
  document.querySelectorAll('[data-print]').forEach(function (btn) {
    btn.addEventListener('click', function () { window.print(); });
  });

  function setText(id, value) {
    var el = document.getElementById(id);
    if (el) { el.value = value || ''; }
  }

  function getJson(url, done) {
    fetch(url, { credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(done)
      .catch(function () { done(null); });
  }

  // Log New Call: fill caller details from the personnel register (UR-02)
  var caller = document.getElementById('CallerID');
  if (caller && caller.dataset.lookup) {
    var fillCaller = function () {
      if (!caller.value) { ['c_job', 'c_dept', 'c_office'].forEach(function (id) { setText(id, ''); }); return; }
      getJson(caller.dataset.lookup + '?id=' + encodeURIComponent(caller.value), function (d) {
        setText('c_job', d && d.JobTitle);
        setText('c_dept', d && d.DepartmentName);
        setText('c_office', d && d.OfficeName);
      });
    };
    caller.addEventListener('change', fillCaller);
    fillCaller();
  }

  // Log New Call: fill equipment details and warranty badge (UR-03)
  var serial = document.getElementById('SerialNumber');
  if (serial && serial.dataset.lookup) {
    var badge = document.getElementById('warranty_badge');
    var fillHardware = function () {
      if (!serial.value.trim()) { return; }
      getJson(serial.dataset.lookup + '?serial=' + encodeURIComponent(serial.value.trim()), function (d) {
        if (!d || !d.found) {
          setText('h_type', ''); setText('h_model', ''); setText('h_installed', '');
          badge.textContent = 'Not in register'; badge.className = 'badge badge-bad';
          return;
        }
        setText('h_type', d.EquipmentType + ' / ' + d.ManufacturerName);
        setText('h_model', d.Make + ' ' + d.Model);
        setText('h_installed', d.Installed);
        badge.textContent = d.WarrantyStatus;
        badge.className = 'badge ' + (d.WarrantyStatus === 'Under warranty' ? 'badge-good' : 'badge-bad');
        var os = document.getElementById('OSSoftwareID');
        if (os && d.OSSoftwareID && !os.value) { os.value = d.OSSoftwareID; }
      });
    };
    serial.addEventListener('change', fillHardware);
    fillHardware();
  }
})();
