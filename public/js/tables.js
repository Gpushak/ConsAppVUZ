(function () {
  var showFinished = false;

  function fold(value) {
    return String(value || '').replace(/\s+/g, ' ').trim().toLocaleLowerCase('ru');
  }

  // Same folding as App\Support\SearchText: ё/е, punctuation, then a word prefix.
  function searchFold(value) {
    return String(value || '')
      .toLocaleLowerCase('ru')
      .replace(/ё/g, 'е')
      .replace(/[^\p{L}\p{N}]+/gu, ' ')
      .replace(/\s+/g, ' ')
      .trim();
  }

  function searchWords(value) {
    var folded = searchFold(value);

    return folded ? folded.split(' ').filter(Boolean) : [];
  }

  function searchMatches(haystack, tokens) {
    var words = searchWords(haystack);

    return tokens.every(function (token) {
      return words.some(function (word) {
        return word.indexOf(token) === 0;
      });
    });
  }

  function clean(value) {
    return String(value || '').replace(/\s+/g, ' ').trim();
  }

  function visibleText(node) {
    if (!node) {
      return '';
    }

    var copy = node.cloneNode(true);
    Array.prototype.forEach.call(copy.querySelectorAll('.cell-label'), function (label) {
      label.remove();
    });

    return copy.textContent;
  }

  function cellText(row, index) {
    var cell = row.cells[index];

    return cell ? fold(visibleText(cell)) : '';
  }

  function sortButton(label) {
    var button = document.createElement('button');
    button.type = 'button';
    button.appendChild(document.createTextNode(label));
    var mark = document.createElement('span');
    mark.className = 'sort-mark';
    mark.setAttribute('aria-hidden', 'true');
    button.appendChild(mark);

    return button;
  }

  function markSort(button, state) {
    if (!button) {
      return;
    }

    if (state) {
      button.setAttribute('data-sort', state);
    } else {
      button.removeAttribute('data-sort');
    }
  }

  function compareValues(left, right) {
    var date = /^(\d{2})\.(\d{2})\.(\d{4})$/;
    var leftDate = left.match(date);
    var rightDate = right.match(date);

    if (leftDate && rightDate) {
      var leftKey = leftDate[3] + leftDate[2] + leftDate[1];
      var rightKey = rightDate[3] + rightDate[2] + rightDate[1];

      return leftKey < rightKey ? -1 : leftKey > rightKey ? 1 : 0;
    }

    return left.localeCompare(right, 'ru', { numeric: true, sensitivity: 'base' });
  }

  function isPersonDirectory(table) {
    return table.hasAttribute('data-teacher-directory') || table.hasAttribute('data-user-directory');
  }

  function directorySearch(table) {
    if (table.hasAttribute('data-teacher-directory')) {
      return document.querySelector('[data-teacher-search]');
    }
    if (table.hasAttribute('data-user-directory')) {
      return document.querySelector('[data-user-search]');
    }

    return null;
  }

  function dataRows(table) {
    return Array.prototype.filter.call(table.tBodies[0].rows, function (row) {
      return !row.hasAttribute('data-static');
    });
  }

  function apply(table, announce) {
    var select = table.parentNode ? table.parentNode.querySelector('select[data-status-filter]') : null;
    var query = select ? fold(select.value) : '';
    var search = directorySearch(table);
    var words = search ? searchWords(search.value) : [];

    var statusIndex = select ? Number(select.getAttribute('data-status-index')) : -1;
    var filtering = query !== '' || words.length > 0;
    var shown = 0;

    dataRows(table).forEach(function (row) {
      var visible = true;

      if (row.hasAttribute('data-finished') && !showFinished && query === '') {
        visible = false;
      }

      if (visible && query && statusIndex >= 0 && cellText(row, statusIndex) !== query) {
        visible = false;
      }

      if (visible && words.length && row.hasAttribute('data-search')) {
        visible = searchMatches(row.getAttribute('data-search'), words);
      }

      row.hidden = !visible;
      if (visible) {
        shown += 1;
      }
    });

    var empty = table.querySelector('[data-nomatch]');
    if (empty) {
      empty.hidden = !(filtering && shown === 0);
    }

    if (announce && isPersonDirectory(table)) {
      var liveId = table.hasAttribute('data-user-directory') ? 'user-search-status' : 'teacher-search-status';
      var live = document.getElementById(liveId);
      if (live) {
        live.textContent = words.length > 0 && shown === 0 ? 'Нет совпадений' : 'Показано ' + shown;
      }
    }
  }

  var sorters = new WeakMap();

  function toggleSort(table, index, header) {
    var next = header.getAttribute('aria-sort') === 'ascending' ? 'descending' : 'ascending';
    Array.prototype.forEach.call(table.tHead.rows[0].cells, function (cell) {
      cell.removeAttribute('aria-sort');
    });
    header.setAttribute('aria-sort', next);
    (sorters.get(table) || []).forEach(function (item) {
      var state = item.header === header ? next : '';
      markSort(item.headerButton, state);
      markSort(item.stripButton, state);
    });

    var direction = next === 'ascending' ? 1 : -1;
    var body = table.tBodies[0];
    dataRows(table)
      .sort(function (left, right) {
        return direction * compareValues(cellText(left, index), cellText(right, index));
      })
      .forEach(function (row) {
        body.appendChild(row);
      });

    Array.prototype.forEach.call(body.rows, function (row) {
      if (row.hasAttribute('data-static')) {
        body.appendChild(row);
      }
    });
  }

  function statusOptions(table, index) {
    var seen = {};
    var values = [];

    dataRows(table).forEach(function (row) {
      var text = clean(visibleText(row.cells[index]));
      var key = fold(text);
      if (!key || seen[key]) {
        return;
      }
      seen[key] = true;
      values.push(text);
    });

    values.sort(function (left, right) {
      return compareValues(fold(left), fold(right));
    });

    return values;
  }

  function enhance(table) {
    if (!table.tHead || !table.tBodies[0] || table.dataset.enhanced === '1') {
      return;
    }
    table.dataset.enhanced = '1';

    var headerRow = table.tHead.rows[0];
    var statusIndex = -1;
    var controls = [];

    Array.prototype.forEach.call(headerRow.cells, function (header, index) {
      if (header.hasAttribute('data-skip')) {
        return;
      }

      var label = clean(header.textContent);
      header.classList.add('sortable');
      header.textContent = '';
      var button = sortButton(label);
      header.appendChild(button);
      button.addEventListener('click', function () {
        toggleSort(table, index, header);
      });
      controls.push({ header: header, headerButton: button, stripButton: null, index: index, label: label });

      if (fold(label) === 'статус') {
        statusIndex = index;
      }
    });

    var tools = document.createElement('div');
    tools.className = 'table-tools';
    var sortStrip = document.createElement('div');
    sortStrip.className = 'table-sort';
    controls.forEach(function (item) {
      var button = sortButton(item.label);
      button.addEventListener('click', function () {
        toggleSort(table, item.index, item.header);
      });
      item.stripButton = button;
      sortStrip.appendChild(button);
    });
    tools.appendChild(sortStrip);
    sorters.set(table, controls);

    if (statusIndex >= 0) {
      var statusLabel = document.createElement('label');
      statusLabel.className = 'field table-status';
      var caption = document.createElement('span');
      caption.textContent = 'Статус';
      var select = document.createElement('select');
      select.setAttribute('data-status-filter', '');
      select.setAttribute('data-status-index', String(statusIndex));
      var all = document.createElement('option');
      all.value = '';
      all.textContent = 'Все';
      select.appendChild(all);
      statusOptions(table, statusIndex).forEach(function (status) {
        var option = document.createElement('option');
        option.value = status;
        option.textContent = status;
        select.appendChild(option);
      });
      select.addEventListener('change', function () {
        apply(table);
      });
      statusLabel.appendChild(caption);
      statusLabel.appendChild(select);
      tools.appendChild(statusLabel);
    }

    if (controls.length || statusIndex >= 0) {
      table.parentNode.insertBefore(tools, table);
    }

    if ((statusIndex >= 0 || isPersonDirectory(table)) && dataRows(table).length && !table.querySelector('[data-nomatch]')) {
      var empty = document.createElement('tr');
      empty.setAttribute('data-static', '');
      empty.setAttribute('data-nomatch', '');
      empty.hidden = true;
      var message = document.createElement('td');
      message.colSpan = headerRow.cells.length;
      message.className = 'muted';
      message.textContent = 'Нет совпадений';
      empty.appendChild(message);
      table.tBodies[0].appendChild(empty);
    }

    apply(table);
  }

  function applyFinishedCards() {
    Array.prototype.forEach.call(document.querySelectorAll('article[data-finished]'), function (card) {
      card.hidden = !showFinished;
    });
  }

  function updateToggle() {
    Array.prototype.forEach.call(document.querySelectorAll('[data-finished-toggle]'), function (button) {
      button.textContent = showFinished ? 'Скрыть завершённые' : 'Показать завершённые';
      button.setAttribute('aria-pressed', showFinished ? 'true' : 'false');
    });
  }

  function syncFinishedOnly() {
    Array.prototype.forEach.call(document.querySelectorAll('[data-only-finished]'), function (section) {
      section.hidden = !showFinished;
    });
  }

  function toggleSlotCard(card) {
    var button = card.querySelector('.slot-toggle');
    var details = card.querySelector('.slot-details');

    if (!button || !details) {
      return;
    }

    var open = button.getAttribute('aria-expanded') !== 'true';
    button.setAttribute('aria-expanded', open ? 'true' : 'false');
    details.hidden = !open;
  }

  function bootSlotCards() {
    Array.prototype.forEach.call(document.querySelectorAll('[data-slot-card]'), function (card) {
      if (card.dataset.foldReady === '1') {
        return;
      }
      card.dataset.foldReady = '1';

      card.addEventListener('click', function (event) {
        var node = event.target;
        if (node && node.nodeType !== 1) {
          node = node.parentElement;
        }
        if (!node) {
          return;
        }

        var toggle = card.querySelector('.slot-toggle');
        var interactive = node.closest('a, button, input, select, textarea, label');
        if (interactive && card.contains(interactive) && interactive !== toggle) {
          return;
        }

        toggleSlotCard(card);
      });
    });
  }

  function bootNewTeacher() {
    var button = document.querySelector('[data-new-teacher]');
    var panel = document.getElementById('new-teacher');

    if (!button || !panel || button.dataset.ready === '1') {
      return;
    }
    button.dataset.ready = '1';

    function setOpen(open) {
      panel.hidden = !open;
      button.setAttribute('aria-expanded', open ? 'true' : 'false');
    }

    button.addEventListener('click', function () {
      setOpen(panel.hidden);
    });

    var cancel = panel.querySelector('[data-new-teacher-cancel]');
    if (cancel) {
      cancel.addEventListener('click', function () {
        setOpen(false);
      });
    }
  }

  function boot() {
    Array.prototype.forEach.call(document.querySelectorAll('table.data-table'), enhance);
    Array.prototype.forEach.call(document.querySelectorAll('[data-teacher-search], [data-user-search]'), function (search) {
      search.addEventListener('input', function () {
        var selector = search.hasAttribute('data-user-search')
          ? 'table[data-user-directory]'
          : 'table[data-teacher-directory]';
        var table = document.querySelector(selector);
        if (table) {
          apply(table, true);
        }
      });
    });

    Array.prototype.forEach.call(document.querySelectorAll('[data-finished-toggle]'), function (button) {
      button.addEventListener('click', function () {
        showFinished = !showFinished;
        updateToggle();
        syncFinishedOnly();
        applyFinishedCards();
        Array.prototype.forEach.call(document.querySelectorAll('table.data-table'), apply);
      });
    });
    updateToggle();
    syncFinishedOnly();
    applyFinishedCards();
    bootSlotCards();
    bootNewTeacher();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})();
