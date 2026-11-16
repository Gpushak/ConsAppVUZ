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

  function cellText(row, index) {
    var cell = row.cells[index];

    return cell ? fold(cell.textContent) : '';
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

  function apply(table) {
    var select = table.querySelector('select[data-status-filter]');
    var query = select ? fold(select.value) : '';
    var search = directorySearch(table);
    var words = search ? searchWords(search.value) : [];

    var statusIndex = select ? select.parentNode.cellIndex : -1;
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
  }

  function toggleSort(table, index, header) {
    var next = header.getAttribute('aria-sort') === 'ascending' ? 'descending' : 'ascending';
    Array.prototype.forEach.call(table.tHead.rows[0].cells, function (cell) {
      cell.removeAttribute('aria-sort');
    });
    header.setAttribute('aria-sort', next);

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
      var text = clean(row.cells[index] ? row.cells[index].textContent : '');
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

    Array.prototype.forEach.call(headerRow.cells, function (header, index) {
      if (header.hasAttribute('data-skip')) {
        return;
      }

      header.classList.add('sortable');
      header.tabIndex = 0;
      header.title = 'Сортировать';
      header.addEventListener('click', function () {
        toggleSort(table, index, header);
      });
      header.addEventListener('keydown', function (event) {
        if (event.key === 'Enter' || event.key === ' ') {
          event.preventDefault();
          toggleSort(table, index, header);
        }
      });

      if (fold(header.textContent) === 'статус') {
        statusIndex = index;
      }
    });

    if (statusIndex >= 0) {
      var filterRow = document.createElement('tr');
      filterRow.className = 'filters';

      Array.prototype.forEach.call(headerRow.cells, function (header, index) {
        var cell = document.createElement('th');
        if (index === statusIndex) {
          var select = document.createElement('select');
          select.setAttribute('data-status-filter', '');
          select.setAttribute('aria-label', 'Статус');
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
          cell.appendChild(select);
        }
        filterRow.appendChild(cell);
      });

      headerRow.parentNode.appendChild(filterRow);
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
          apply(table);
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
