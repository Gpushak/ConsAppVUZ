(function () {
  var shell = window.desktopShell;
  if (!shell) {
    return;
  }

  var dialogPatterns = [
    /\/slots\/\d+$/,
    /\/bookings\/\d+\/cancel$/,
    /\/teacher\/bookings\/\d+\/cancel$/,
    /\/teacher\/slots\/create$/,
    /\/teacher\/slots\/\d+\/unpublish$/,
    /\/teacher\/slots\/\d+\/conduct$/,
    /\/teacher\/slots\/\d+\/series\/stop$/,
    /\/admin\/users\/\d+\/block$/,
    /\/admin\/users\/\d+\/unblock$/,
    /\/admin\/users\/\d+\/password$/
  ];
  var rosterPattern = /\/slots\/\d+\/roster$/;

  function pathOf(url) {
    try {
      var path = new URL(url, window.location.href).pathname;
      if (path.length > 1 && path.charAt(path.length - 1) === '/') {
        path = path.slice(0, -1);
      }

      return path;
    } catch (error) {
      return '';
    }
  }

  function isDialog(url) {
    var path = pathOf(url);

    return dialogPatterns.some(function (pattern) {
      return pattern.test(path);
    });
  }

  function isRoster(url) {
    return rosterPattern.test(pathOf(url));
  }

  function dialogEl() {
    return document.getElementById('desk-dialog');
  }

  function dialogBody() {
    var dialog = dialogEl();

    return dialog ? dialog.querySelector('.desk-modal-body') : null;
  }

  function runScripts(root) {
    Array.prototype.forEach.call(root.querySelectorAll('script'), function (old) {
      var script = document.createElement('script');
      if (old.src) {
        script.src = old.src;
      } else {
        script.text = old.text;
      }
      script.async = false;
      old.replaceWith(script);
    });
  }

  var teacherPanel = null;
  var teacherPlaceholder = null;
  var teacherButton = null;

  function restoreTeacher() {
    if (!teacherPanel) {
      return;
    }
    if (teacherPlaceholder && teacherPanel.parentElement !== teacherPlaceholder.parentElement) {
      teacherPlaceholder.before(teacherPanel);
    }
    teacherPanel.hidden = true;
    if (teacherButton) {
      teacherButton.setAttribute('aria-expanded', 'false');
    }
  }

  function showDialog(kind) {
    var dialog = dialogEl();
    if (!dialog) {
      return;
    }
    dialog.setAttribute('data-desktop-dialog', kind || 'page');
    if (!dialog.open) {
      dialog.showModal();
    }
  }

  var closingDialog = false;

  function closeDialog() {
    var dialog = dialogEl();
    if (closingDialog || !dialog || !dialog.open) {
      return;
    }
    closingDialog = true;
    var inline = dialog.hasAttribute('data-desktop-inline');
    restoreTeacher();
    if (!inline) {
      var body = dialogBody();
      if (body) {
        body.replaceChildren();
      }
      dialog.removeAttribute('data-desktop-dialog');
    }
    dialog.close();
    closingDialog = false;
    if (inline && window.history.length > 1) {
      window.history.back();
    }
  }

  function openDialog(url) {
    var body = dialogBody();
    if (!body) {
      window.location.href = url;

      return;
    }
    restoreTeacher();
    var dialog = dialogEl();
    if (dialog) {
      dialog.removeAttribute('data-desktop-inline');
    }
    fetch(url, {
      credentials: 'same-origin',
      headers: {
        Accept: 'text/html',
        'X-Desktop-Fragment': '1'
      }
    }).then(function (response) {
      if (!response.ok) {
        window.location.href = url;

        return '';
      }

      return response.text();
    }).then(function (html) {
      if (!html) {
        return;
      }
      var doc = new DOMParser().parseFromString(html, 'text/html');
      var fragment = doc.querySelector('[data-desktop-fragment]');
      if (!fragment) {
        window.location.href = url;

        return;
      }
      body.replaceChildren();
      body.appendChild(document.importNode(fragment, true));
      runScripts(body);
      showDialog('page');
    }).catch(function () {
      window.location.href = url;
    });
  }

  function presentTeacher() {
    if (!teacherPanel || teacherPanel.hidden) {
      var dialog = dialogEl();
      if (dialog && dialog.open && dialog.getAttribute('data-desktop-dialog') === 'teacher') {
        closeDialog();
      }

      return;
    }
    var body = dialogBody();
    if (!body) {
      return;
    }
    if (dialogEl()) {
      dialogEl().removeAttribute('data-desktop-inline');
    }
    body.replaceChildren();
    body.appendChild(teacherPanel);
    teacherPanel.hidden = false;
    showDialog('teacher');
  }

  function bootTeacher() {
    teacherButton = document.querySelector('[data-new-user]');
    teacherPanel = document.getElementById('new-user');
    if (!teacherButton || !teacherPanel) {
      if (window.sessionStorage.getItem('desk-open-teacher') === '1') {
        window.sessionStorage.removeItem('desk-open-teacher');
      }

      return;
    }
    teacherPlaceholder = document.createElement('span');
    teacherPlaceholder.hidden = true;
    teacherPanel.after(teacherPlaceholder);
    teacherButton.addEventListener('click', function () {
      window.setTimeout(presentTeacher, 0);
    });
    var cancel = teacherPanel.querySelector('[data-new-user-cancel]');
    if (cancel) {
      cancel.addEventListener('click', function () {
        window.setTimeout(presentTeacher, 0);
      });
    }
    if (window.sessionStorage.getItem('desk-open-teacher') === '1') {
      window.sessionStorage.removeItem('desk-open-teacher');
      if (teacherPanel.hidden) {
        teacherButton.click();
      } else {
        presentTeacher();
      }

      return;
    }
    if (!teacherPanel.hidden) {
      presentTeacher();
    }
  }

  function linkByText(label) {
    var links = document.querySelectorAll('a');
    var index;
    for (index = 0; index < links.length; index += 1) {
      if (links[index].textContent.replace(/\s+/g, ' ').trim() === label) {
        return links[index];
      }
    }

    return null;
  }

  function shiftWeek(direction) {
    var link = document.querySelector('.week-range a[rel="' + (direction === 'prev' ? 'prev' : 'next') + '"]');
    if (link) {
      window.location.href = link.href;
    }
  }

  function roleAction() {
    var role = document.body.getAttribute('data-role');
    if (role === 'teacher') {
      var create = document.querySelector('a[href*="/teacher/slots/create"]');
      if (create) {
        openDialog(create.href);
      } else {
        window.location.href = '/teacher/slots/create';
      }

      return;
    }
    if (role === 'admin') {
      if (teacherButton && teacherPanel) {
        if (teacherPanel.hidden) {
          teacherButton.click();
        } else {
          presentTeacher();
        }

        return;
      }
      window.sessionStorage.setItem('desk-open-teacher', '1');
      var users = document.querySelector('a[href$="/admin/users"]');
      window.location.href = users ? users.href : '/admin/users';

      return;
    }
    var book = linkByText('Записаться');
    if (book && isDialog(book.href)) {
      openDialog(book.href);
    }
  }

  function rosterFromPage() {
    var link = document.querySelector('.slot-details:not([hidden]) a[href*="/roster"]')
      || document.querySelector('a[href*="/roster"]');
    if (link) {
      shell.openRoster(link.href);
    }
  }

  function logout() {
    var form = document.querySelector('form[action$="/logout"]');
    if (form) {
      form.submit();
    }
  }

  document.addEventListener('click', function (event) {
    if (event.defaultPrevented || event.button !== 0) {
      return;
    }
    if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
      return;
    }
    var node = event.target;
    if (node && node.nodeType !== 1) {
      node = node.parentElement;
    }
    if (!node || !node.closest) {
      return;
    }
    if (node.closest('[data-desk-close]')) {
      event.preventDefault();
      event.stopPropagation();
      closeDialog();

      return;
    }
    var link = node.closest('a[href]');
    if (!link || (link.target && link.target !== '_self')) {
      return;
    }
    var absolute;
    try {
      absolute = new URL(link.href, window.location.href);
    } catch (error) {
      return;
    }
    if (absolute.origin !== window.location.origin) {
      return;
    }
    if (isRoster(absolute.href)) {
      event.preventDefault();
      event.stopPropagation();
      shell.openRoster(absolute.href);

      return;
    }
    if (isDialog(absolute.href)) {
      event.preventDefault();
      event.stopPropagation();
      openDialog(absolute.href);

      return;
    }
    var dialog = dialogEl();
    if (dialog && dialog.open && dialog.contains(link)) {
      event.preventDefault();
      event.stopPropagation();
      var same = absolute.pathname === window.location.pathname && absolute.search === window.location.search;
      closeDialog();
      if (!same) {
        window.location.href = absolute.href;
      }
    }
  }, true);

  var shortcutAt = 0;

  function onceShortcut(run) {
    var now = Date.now();
    if (now - shortcutAt < 250) {
      return;
    }
    shortcutAt = now;
    run();
  }

  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') {
      var dialog = dialogEl();
      if (!dialog || !dialog.open) {
        return;
      }
      event.preventDefault();
      closeDialog();

      return;
    }
    if (!(event.ctrlKey || event.metaKey) || event.altKey) {
      return;
    }
    if (event.key === 'ArrowLeft') {
      event.preventDefault();
      onceShortcut(function () {
        shiftWeek('prev');
      });
    } else if (event.key === 'ArrowRight') {
      event.preventDefault();
      onceShortcut(function () {
        shiftWeek('next');
      });
    } else if (event.key === 'n' || event.key === 'N') {
      event.preventDefault();
      onceShortcut(roleAction);
    }
  });

  var inline = dialogEl();
  if (inline) {
    inline.addEventListener('cancel', function (event) {
      event.preventDefault();
      closeDialog();
    });
    if (inline.hasAttribute('data-desktop-inline')) {
      showDialog('page');
    }
  }

  shell.onWeek(function (direction) {
    onceShortcut(function () {
      shiftWeek(direction);
    });
  });
  shell.onRoleAction(function () {
    onceShortcut(roleAction);
  });
  shell.onLogout(logout);
  shell.onRoster(rosterFromPage);
  shell.setRole(document.body.getAttribute('data-role') || '');

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bootTeacher);
  } else {
    bootTeacher();
  }
})();
