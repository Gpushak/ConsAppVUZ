(function () {
  function fold(value) {
    return String(value || '').replace(/\s+/g, ' ').trim().toLocaleLowerCase('ru');
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

  function dataRows(table) {
    return Array.prototype.filter.call(table.tBodies[0].rows, function (row) {
      return !row.hasAttribute('data-static');
    });
  }

  function apply(table) {
    var inputs = table.querySelectorAll('tr.filters input');
    var search = document.querySelector('[data-teacher-search]');
    var words = [];

    if (search && table.hasAttribute('data-teacher-directory')) {
      words = fold(search.value).split(' ').filter(Boolean);
    }

    var shown = 0;
    dataRows(table).forEach(function (row) {
      var visible = true;

      Array.prototype.forEach.call(inputs, function (input) {
        var query = fold(input.value);
        if (!query || !visible) {
          return;
        }
        var index = input.parentNode.cellIndex;
        if (cellText(row, index).indexOf(query) === -1) {
          visible = false;
        }
      });

      if (visible && words.length && row.hasAttribute('data-search')) {
        var haystack = fold(row.getAttribute('data-search'));
        visible = words.every(function (word) {
          return haystack.indexOf(word) !== -1;
        });
      }

      row.hidden = !visible;
      if (visible) {
        shown += 1;
      }
    });

    var empty = table.querySelector('[data-nomatch]');
    if (empty) {
      empty.hidden = shown !== 0;
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

  function enhance(table) {
    if (!table.tHead || !table.tBodies[0] || table.dataset.enhanced === '1') {
      return;
    }
    table.dataset.enhanced = '1';

    var headerRow = table.tHead.rows[0];
    var filterRow = document.createElement('tr');
    filterRow.className = 'filters';

    Array.prototype.forEach.call(headerRow.cells, function (header, index) {
      var cell = document.createElement('th');
      if (!header.hasAttribute('data-skip')) {
        var input = document.createElement('input');
        input.type = 'search';
        input.placeholder = 'фильтр';
        input.autocomplete = 'off';
        input.setAttribute('aria-label', 'Фильтр: ' + fold(header.textContent));
        input.addEventListener('input', function () {
          apply(table);
        });
        cell.appendChild(input);

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
      }
      filterRow.appendChild(cell);
    });

    headerRow.parentNode.appendChild(filterRow);

    if (dataRows(table).length) {
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
  }

  function boot() {
    Array.prototype.forEach.call(document.querySelectorAll('table.data-table'), enhance);
    var search = document.querySelector('[data-teacher-search]');
    if (!search) {
      return;
    }
    search.addEventListener('input', function () {
      var table = document.querySelector('table[data-teacher-directory]');
      if (table) {
        apply(table);
      }
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})();
