/* ===========================================================================
   GIHONAWIT ONLINE BOOKSTORE — js/main.js
   ---------------------------------------------------------------------------
   Vanilla JavaScript. No framework, no build step, no dependency.

   The Fetch API drives every asynchronous interaction:
     · live search suggestions in the masthead
     · catalogue filtering, sorting and pagination on books.php
     · add-to-cart and wishlist toggles from any page

   Progressive enhancement: with JavaScript disabled the same pages work
   through ordinary form posts and full page loads.

   Public namespace: window.GB
   =========================================================================== */
(function (window, document) {
  'use strict';

  /* ---------------------------------------------------------------------- */
  /* Configuration injected by includes/header.php                          */
  /* ---------------------------------------------------------------------- */
  var CFG = window.GB_CONFIG || {};

  CFG.baseUrl    = CFG.baseUrl || '';
  CFG.csrf       = CFG.csrf || '';
  CFG.currency   = CFG.currency || '$';
  CFG.cartCount  = Number(CFG.cartCount) || 0;
  CFG.wishCount  = Number(CFG.wishCount) || 0;
  CFG.endpoints  = CFG.endpoints || {};
  CFG.strings    = CFG.strings || {};

  var GB = {};

  /* ---------------------------------------------------------------------- */
  /* Utilities                                                              */
  /* ---------------------------------------------------------------------- */

  /** Escape text before inserting it into innerHTML. */
  GB.escape = function (value) {
    return String(value === null || value === undefined ? '' : value)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  };

  /** Trailing-edge debounce for the live search field. */
  GB.debounce = function (fn, wait) {
    var timer = null;
    return function () {
      var context = this;
      var args = arguments;
      window.clearTimeout(timer);
      timer = window.setTimeout(function () { fn.apply(context, args); }, wait || 260);
    };
  };

  /** sprintf-style fill for translated strings containing %d / %s. */
  GB.fill = function (template, values) {
    var list = Array.isArray(values) ? values : [values];
    var index = 0;

    return String(template || '').replace(/%[ds]/g, function () {
      return index < list.length ? list[index++] : '';
    });
  };

  /* ---------------------------------------------------------------------- */
  /* Toasts                                                                 */
  /* ---------------------------------------------------------------------- */
  GB.toast = function (message, type) {
    var host = document.querySelector('.toasts');

    if (!host) {
      host = document.createElement('div');
      host.className = 'toasts';
      host.setAttribute('role', 'status');
      document.body.appendChild(host);
    }

    var node = document.createElement('div');
    node.className = 'toast toast--' + (type || 'info');
    node.textContent = message;
    host.appendChild(node);

    var remove = function () {
      node.classList.add('is-leaving');
      window.setTimeout(function () {
        if (node.parentNode) { node.parentNode.removeChild(node); }
      }, 220);
    };

    window.setTimeout(remove, 4200);
    node.addEventListener('click', remove);
  };

  /* ---------------------------------------------------------------------- */
  /* fetch() wrapper — always same-origin, always carries the CSRF token    */
  /* ---------------------------------------------------------------------- */
  GB.request = function (url, options) {
    options = options || {};

    var headers = { 'X-Requested-With': 'XMLHttpRequest' };
    var body = options.body || null;

    if (options.data) {
      headers['Content-Type'] = 'application/json';
      headers['X-CSRF-Token'] = CFG.csrf;
      body = JSON.stringify(options.data);
    } else if (options.method && options.method.toUpperCase() !== 'GET') {
      headers['X-CSRF-Token'] = CFG.csrf;
    }

    return window.fetch(url, {
      method: options.method || (options.data ? 'POST' : 'GET'),
      credentials: 'same-origin',
      headers: headers,
      body: body
    }).then(function (response) {
      return response.text().then(function (text) {
        var payload;

        try {
          payload = JSON.parse(text);
        } catch (error) {
          throw new Error(GB.fill(CFG.strings.error || 'Unexpected server response (%s).', [response.status]));
        }

        if (!response.ok && payload && payload.message) {
          throw new Error(payload.message);
        }

        return payload;
      });
    });
  };

  /* ---------------------------------------------------------------------- */
  /* Counters                                                               */
  /* ---------------------------------------------------------------------- */
  function paintCount(selector, value) {
    document.querySelectorAll(selector).forEach(function (node) {
      node.textContent = String(value);
      node.hidden = Number(value) === 0;
    });
  }

  GB.setCartCount = function (count) {
    CFG.cartCount = Number(count) || 0;
    paintCount('[data-cart-count]', CFG.cartCount);
  };

  GB.setWishCount = function (count) {
    CFG.wishCount = Number(count) || 0;
    paintCount('[data-wish-count]', CFG.wishCount);
  };

  /* ---------------------------------------------------------------------- */
  /* Book markup — mirrors includes/book-card.php so server-rendered and     */
  /* AJAX-rendered cards are visually identical                              */
  /* ---------------------------------------------------------------------- */

  /** Cover block: a real image when configured, otherwise the CSS template. */
  GB.cover = function (book) {
    if (book.cover_image) {
      return '<img class="book-card__photo" src="' + GB.escape(book.cover_image) +
        '" alt="Cover of ' + GB.escape(book.title) + '" loading="lazy" width="400" height="600">';
    }

    return '' +
      '<div class="cover cover--t' + (Number(book.cover_tone) || 1) + '">' +
        '<span class="cover__top">' +
          '<span class="cover__genre">' + GB.escape(book.genre) + '</span>' +
          '<span class="cover__title">' + GB.escape(book.title) + '</span>' +
        '</span>' +
        '<span class="cover__bottom">' +
          '<span class="cover__rule"></span>' +
          '<span class="cover__author">' + GB.escape(book.author) + '</span>' +
        '</span>' +
      '</div>';
  };

  /** A complete catalogue card. */
  GB.bookCard = function (book) {
    var out = Number(book.stock_quantity) <= 0;
    var low = !out && Number(book.stock_quantity) <= 5;
    var flags = '';

    if (Number(book.is_featured) === 1) {
      flags += '<span class="tag tag--gold">' + GB.escape(CFG.strings.featured || 'Featured') + '</span>';
    }
    if (out) {
      flags += '<span class="tag tag--out">' + GB.escape(CFG.strings.out || 'Out of Stock') + '</span>';
    } else if (low) {
      flags += '<span class="tag tag--low">' + GB.fill(CFG.strings.low || '%d left', [book.stock_quantity]) + '</span>';
    }

    return '' +
      '<article class="book-card" data-book-id="' + book.id + '">' +
        '<div class="book-card__media">' +
          GB.cover(book) +
          '<div class="book-card__flags">' + flags + '</div>' +
          '<button type="button" class="book-card__wish' + (book.wishlisted ? ' is-saved' : '') + '"' +
            ' data-wish="' + book.id + '"' +
            ' aria-label="' + GB.escape(CFG.strings.wish || 'Save to wishlist') + '"' +
            ' aria-pressed="' + (book.wishlisted ? 'true' : 'false') + '">' +
            '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' +
              '<path d="M12 20.3S4.5 15.6 4.5 10.3A4.2 4.2 0 0 1 12 7.6a4.2 4.2 0 0 1 7.5 2.7c0 5.3-7.5 10-7.5 10z"/>' +
            '</svg>' +
          '</button>' +
        '</div>' +
        '<div class="book-card__body">' +
          '<h3 class="book-card__title"><a href="' + GB.escape(book.url) + '">' + GB.escape(book.title) + '</a></h3>' +
          '<p class="book-card__author">' + GB.escape(book.author) + '</p>' +
          '<p class="book-card__tags"><span class="tag">' + GB.escape(book.genre) + '</span></p>' +
          '<div class="book-card__foot">' +
            '<span class="book-card__price">' + GB.escape(book.price_fmt) + '</span>' +
            '<span class="book-card__cta">' +
              '<button type="button" class="btn btn--primary btn--sm" data-add-to-cart="' + book.id + '"' +
                (out ? ' disabled' : '') + '>' +
                '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' +
                  '<path d="M3 4h2.2l2.3 10.6A2 2 0 0 0 9.5 16h7.9a2 2 0 0 0 2-1.6L21 8H6.2"/>' +
                  '<circle cx="10" cy="20" r="1.4"/><circle cx="18" cy="20" r="1.4"/>' +
                '</svg>' +
                GB.escape(out ? (CFG.strings.out || 'Out of Stock') : (CFG.strings.add || 'Add to Cart')) +
              '</button>' +
            '</span>' +
          '</div>' +
        '</div>' +
      '</article>';
  };

  /* ---------------------------------------------------------------------- */
  /* Behaviour: mobile navigation                                           */
  /* ---------------------------------------------------------------------- */
  function initNav() {
    var toggle = document.querySelector('[data-nav-toggle]');
    var menu = document.querySelector('[data-nav]');

    if (!toggle || !menu) { return; }

    toggle.addEventListener('click', function () {
      var open = menu.classList.toggle('is-open');
      toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
  }

  /* ---------------------------------------------------------------------- */
  /* Behaviour: dismissible notices                                        */
  /* ---------------------------------------------------------------------- */
  function initNotices() {
    document.querySelectorAll('.notice').forEach(function (notice) {
      var close = notice.querySelector('[data-dismiss]');

      var hide = function () {
        notice.style.transition = 'opacity .2s ease';
        notice.style.opacity = '0';
        window.setTimeout(function () {
          if (notice.parentNode) { notice.parentNode.removeChild(notice); }
        }, 200);
      };

      if (close) { close.addEventListener('click', hide); }
      window.setTimeout(hide, 9000);
    });
  }

  /* ---------------------------------------------------------------------- */
  /* Behaviour: tabs                                                        */
  /* ---------------------------------------------------------------------- */
  function initTabs() {
    document.querySelectorAll('[data-tabs]').forEach(function (group) {
      var tabs = group.querySelectorAll('[data-tab]');

      tabs.forEach(function (tab) {
        tab.addEventListener('click', function () {
          var target = tab.getAttribute('data-tab');

          tabs.forEach(function (other) {
            var active = other === tab;
            other.classList.toggle('is-active', active);
            other.setAttribute('aria-selected', active ? 'true' : 'false');
          });

          document.querySelectorAll('[data-tab-panel]').forEach(function (panel) {
            panel.hidden = panel.getAttribute('data-tab-panel') !== target;
          });
        });
      });
    });
  }

  /* ---------------------------------------------------------------------- */
  /* Behaviour: quantity steppers                                           */
  /* ---------------------------------------------------------------------- */
  function initQuantity() {
    document.querySelectorAll('[data-qty]').forEach(function (widget) {
      if (widget.dataset.ready === '1') { return; }
      widget.dataset.ready = '1';

      var input = widget.querySelector('input');
      if (!input) { return; }

      var min = parseInt(input.getAttribute('min') || '1', 10);
      var max = parseInt(input.getAttribute('max') || '99', 10);

      var sync = function () {
        var value = parseInt(input.value, 10);
        if (isNaN(value)) { value = min; }
        value = Math.min(max, Math.max(min, value));
        input.value = String(value);

        widget.querySelectorAll('[data-step]').forEach(function (button) {
          var step = parseInt(button.getAttribute('data-step'), 10);
          button.disabled = (step < 0 && value <= min) || (step > 0 && value >= max);
        });
      };

      widget.querySelectorAll('[data-step]').forEach(function (button) {
        button.addEventListener('click', function () {
          input.value = String((parseInt(input.value, 10) || min) + parseInt(button.getAttribute('data-step'), 10));
          sync();
        });
      });

      input.addEventListener('change', sync);
      input.addEventListener('blur', sync);
      sync();
    });
  }

  /* ---------------------------------------------------------------------- */
  /* Behaviour: hero message rotation (the three dots under the hero)        */
  /* ---------------------------------------------------------------------- */
  function initHero() {
    var hero = document.querySelector('[data-hero]');
    if (!hero) { return; }

    var dots = hero.querySelectorAll('[data-slide]');
    var tag = hero.querySelector('[data-hero-tag]');
    var body = hero.querySelector('[data-hero-body]');

    if (!dots.length || !tag || !body) { return; }

    var timer = null;

    var show = function (index) {
      var dot = dots[index];
      if (!dot) { return; }

      dots.forEach(function (other, i) {
        other.classList.toggle('is-active', i === index);
        other.setAttribute('aria-current', i === index ? 'true' : 'false');
      });

      tag.textContent = dot.getAttribute('data-tag') || '';
      body.textContent = dot.getAttribute('data-body') || '';
    };

    var start = function () {
      window.clearInterval(timer);
      timer = window.setInterval(function () {
        var current = 0;
        dots.forEach(function (dot, i) { if (dot.classList.contains('is-active')) { current = i; } });
        show((current + 1) % dots.length);
      }, 7000);
    };

    dots.forEach(function (dot, index) {
      dot.addEventListener('click', function () {
        show(index);
        start();
      });
    });

    hero.addEventListener('mouseenter', function () { window.clearInterval(timer); });
    hero.addEventListener('mouseleave', start);
    start();
  }

  /* ---------------------------------------------------------------------- */
  /* Behaviour: live search suggestions in the masthead                     */
  /* ---------------------------------------------------------------------- */
  function initSuggest() {
    var input = document.querySelector('[data-search-input]');

    if (!input || !CFG.endpoints.search) { return; }

    var box = document.querySelector('[data-suggest]');
    var form = input.closest('form');

    if (!box) {
      box = document.createElement('div');
      box.className = 'suggest';
      box.setAttribute('data-suggest', '');
      box.hidden = true;

      if (form && form.parentNode) {
        form.parentNode.style.position = 'relative';
        form.parentNode.appendChild(box);
      }
    }

    var hide = function () { box.hidden = true; box.innerHTML = ''; };

    var render = function (payload, query) {
      var items = payload.items || [];

      if (!items.length) {
        hide();
        return;
      }

      var html = items.slice(0, 6).map(function (book) {
        return '<a class="suggest__item" href="' + GB.escape(book.url) + '">' +
            (book.cover_image
              ? '<img class="suggest__swatch" src="' + GB.escape(book.cover_image) + '" alt="" width="26" height="36">'
              : '<span class="suggest__swatch cover cover--t' + (Number(book.cover_tone) || 1) + '"></span>') +
            '<span>' +
              '<strong>' + GB.escape(book.title) + '</strong>' +
              '<span class="suggest__meta">' + GB.escape(book.author) + ' · ' + GB.escape(book.price_fmt) + '</span>' +
            '</span>' +
          '</a>';
      }).join('');

      html += '<a class="suggest__all" href="' + GB.escape(CFG.baseUrl + '/books.php?q=' + encodeURIComponent(query)) + '">' +
        GB.fill(CFG.strings.results || '%d books found', [payload.total]) + ' →</a>';

      box.innerHTML = html;
      box.hidden = false;
    };

    var run = GB.debounce(function () {
      var query = input.value.trim();

      if (query.length < 2) {
        hide();
        return;
      }

      GB.request(CFG.endpoints.search + '?q=' + encodeURIComponent(query) + '&per_page=6')
        .then(function (payload) { render(payload, query); })
        .catch(hide);
    }, 220);

    input.addEventListener('input', run);
    input.addEventListener('focus', function () { if (input.value.trim().length >= 2) { run(); } });

    document.addEventListener('click', function (event) {
      if (!box.contains(event.target) && event.target !== input) { hide(); }
    });

    input.addEventListener('keydown', function (event) {
      if (event.key === 'Escape') { hide(); }
    });
  }

  /* ---------------------------------------------------------------------- */
  /* Behaviour: catalogue filtering, sorting and pagination                 */
  /* ---------------------------------------------------------------------- */
  function initCatalogue() {
    var root = document.querySelector('[data-catalogue]');

    if (!root || !CFG.endpoints.search) { return; }

    var form = root.querySelector('[data-filters]');
    var results = root.querySelector('[data-results]');
    var count = root.querySelector('[data-count]');
    var chips = root.querySelector('[data-chips]');
    var pager = root.querySelector('[data-pager]');
    var sort = root.querySelector('[data-sort]');

    var state = { page: 1, pages: 1 };

    var collect = function (page) {
      var params = new URLSearchParams();
      var data = form ? new FormData(form) : new FormData();

      data.forEach(function (value, key) {
        var text = String(value).trim();
        if (text !== '') { params.set(key, text); }
      });

      if (sort && sort.value) { params.set('sort', sort.value); }
      params.set('page', String(page || 1));

      return params;
    };

    var renderCount = function (payload) {
      if (!count) { return; }

      count.innerHTML = payload.total > 0
        ? GB.fill('<strong>%d</strong> ' + (CFG.strings.foundIn || 'books found'), [payload.total])
        : GB.escape(CFG.strings.none || 'No books matched your search.');
    };

    var renderChips = function (params) {
      if (!chips) { return; }

      var labels = { q: CFG.strings.searchLabel || 'Search', category: CFG.strings.categoryLabel || 'Category', author: CFG.strings.authorLabel || 'Author', min_price: 'Min', max_price: 'Max', in_stock: CFG.strings.inStockLabel || 'In stock' };
      var html = '';

      ['q', 'category', 'author', 'min_price', 'max_price', 'in_stock'].forEach(function (key) {
        var value = params.get(key);
        if (!value) { return; }

        html += '<span class="chip">' + GB.escape(labels[key] || key) + ': ' + GB.escape(value) +
          '<button type="button" data-drop="' + key + '" aria-label="Remove filter">&times;</button></span>';
      });

      chips.innerHTML = html;
    };

    var renderPager = function (payload) {
      if (!pager) { return; }

      if (payload.pages <= 1) {
        pager.innerHTML = '';
        return;
      }

      var html = '';
      var from = Math.max(1, payload.page - 2);
      var to = Math.min(payload.pages, from + 4);
      from = Math.max(1, to - 4);

      html += '<a href="#" data-page="' + (payload.page - 1) + '" class="' + (payload.page <= 1 ? 'is-disabled' : '') + '" rel="prev">&larr; Prev</a>';

      for (var i = from; i <= to; i++) {
        html += i === payload.page
          ? '<span class="is-current" aria-current="page">' + i + '</span>'
          : '<a href="#" data-page="' + i + '">' + i + '</a>';
      }

      html += '<a href="#" data-page="' + (payload.page + 1) + '" class="' + (payload.page >= payload.pages ? 'is-disabled' : '') + '" rel="next">Next &rarr;</a>';

      pager.innerHTML = html;
    };

    var load = function (page, push) {
      var params = collect(page);

      results.setAttribute('aria-busy', 'true');
      results.classList.add('is-loading');

      GB.request(CFG.endpoints.search + '?' + params.toString())
        .then(function (payload) {
          if (!payload || payload.ok !== true) {
            throw new Error((payload && payload.message) || CFG.strings.error);
          }

          state.page = payload.page;
          state.pages = payload.pages;

          results.innerHTML = (payload.items || []).length
            ? payload.items.map(GB.bookCard).join('')
            : '<div class="empty" style="grid-column:1/-1"><h3>' + GB.escape(CFG.strings.none || 'No books found') + '</h3>' +
              '<p>' + GB.escape(CFG.strings.noneHint || 'Try a different search term or clear the filters.') + '</p>' +
              '<button type="button" class="btn btn--outline" data-clear-filters>' + GB.escape(CFG.strings.clear || 'Clear all') + '</button></div>';

          renderCount(payload);
          renderChips(params);
          renderPager(payload);
          initQuantity();

          if (push !== false) {
            window.history.replaceState({}, '', (form ? form.getAttribute('action') : 'books.php') + '?' + params.toString());
          }
        })
        .catch(function (error) {
          results.innerHTML = '<div class="empty" style="grid-column:1/-1"><h3>' +
            GB.escape(CFG.strings.error || 'Could not load the catalogue.') + '</h3><p>' + GB.escape(error.message) + '</p></div>';
          GB.toast(error.message, 'error');
        })
        .then(function () {
          results.removeAttribute('aria-busy');
          results.classList.remove('is-loading');
        });
    };

    if (form) {
      form.addEventListener('submit', function (event) {
        event.preventDefault();
        load(1);
      });
      form.addEventListener('change', function (event) {
        if (event.target.matches('input[type="radio"], input[type="checkbox"], select')) { load(1); }
      });
    }

    if (sort) {
      sort.addEventListener('change', function () { load(1); });
    }

    var typeField = form ? form.querySelector('[data-cat-input]') : null;
    if (typeField) {
      typeField.addEventListener('input', GB.debounce(function () { load(1); }, 300));
    }

    document.addEventListener('click', function (event) {
      var pageLink = event.target.closest('[data-page]');
      if (pageLink && root.contains(pageLink)) {
        event.preventDefault();
        if (pageLink.classList.contains('is-disabled')) { return; }
        load(parseInt(pageLink.getAttribute('data-page'), 10) || 1);
        window.scrollTo({ top: root.getBoundingClientRect().top + window.scrollY - 90, behavior: 'smooth' });
        return;
      }

      var drop = event.target.closest('[data-drop]');
      if (drop) {
        event.preventDefault();
        var key = drop.getAttribute('data-drop');

        if (form) {
          form.querySelectorAll('[name="' + key + '"]').forEach(function (field) {
            if (field.type === 'radio' || field.type === 'checkbox') { field.checked = false; }
            field.value = '';
          });
        }
        load(1);
        return;
      }

      var clear = event.target.closest('[data-clear-filters]');
      if (clear && root.contains(clear)) {
        event.preventDefault();
        if (form) { form.reset(); }
        load(1);
      }
    });

    window.addEventListener('popstate', function () {
      var params = new URLSearchParams(window.location.search);
      load(parseInt(params.get('page') || '1', 10) || 1, false);
    });
  }

  /* ---------------------------------------------------------------------- */
  /* Behaviour: add to cart (delegated — works for AJAX-rendered cards)      */
  /* ---------------------------------------------------------------------- */
  function initCartButtons() {
    document.addEventListener('click', function (event) {
      var button = event.target.closest('[data-add-to-cart]');
      if (!button) { return; }

      event.preventDefault();
      if (button.disabled) { return; }

      var bookId = parseInt(button.getAttribute('data-add-to-cart'), 10);
      var scope = button.closest('[data-purchase]');
      var qty = scope ? scope.querySelector('input[name="quantity"]') : null;
      var quantity = qty ? (parseInt(qty.value, 10) || 1) : 1;

      var original = button.innerHTML;
      button.disabled = true;
      button.textContent = CFG.strings.adding || 'Adding…';

      GB.request(CFG.endpoints.cart, { data: { action: 'add', book_id: bookId, quantity: quantity } })
        .then(function (payload) {
          if (payload.summary) { GB.setCartCount(payload.summary.count); }

          if (payload.ok) {
            GB.toast(payload.message, 'success');
            button.textContent = CFG.strings.added || 'Added';
            window.setTimeout(function () { button.innerHTML = original; button.disabled = false; }, 1500);
          } else {
            GB.toast(payload.message, 'error');
            button.innerHTML = original;
            button.disabled = false;
          }

          document.dispatchEvent(new CustomEvent('gb:cart-changed', { detail: payload }));
        })
        .catch(function (error) {
          GB.toast(error.message, 'error');
          button.innerHTML = original;
          button.disabled = false;
        });
    });
  }

  /* ---------------------------------------------------------------------- */
  /* Behaviour: wishlist toggle                                             */
  /* ---------------------------------------------------------------------- */
  function initWishButtons() {
    document.addEventListener('click', function (event) {
      var button = event.target.closest('[data-wish]');
      if (!button) { return; }

      event.preventDefault();

      var bookId = parseInt(button.getAttribute('data-wish'), 10);

      GB.request(CFG.endpoints.wishlist, { data: { book_id: bookId } })
        .then(function (payload) {
          button.classList.toggle('is-saved', !!payload.saved);
          button.setAttribute('aria-pressed', payload.saved ? 'true' : 'false');
          GB.setWishCount(payload.count);
          GB.toast(payload.message, payload.ok ? 'success' : 'error');
        })
        .catch(function (error) { GB.toast(error.message, 'error'); });
    });
  }

  /* ---------------------------------------------------------------------- */
  /* Behaviour: the cart page                                               */
  /* ---------------------------------------------------------------------- */
  /* Quantity edits and removals are written to the session by api/cart.php,
     then the visible totals are repainted from the response — the browser
     never computes a price of its own. */
  function initCartPage() {
    var page = document.querySelector('[data-cart-page]');

    if (!page || !CFG.endpoints.cart) { return; }

    function paint(summary) {
      if (!summary) { return; }

      var subtotal = page.querySelector('[data-sum-subtotal]');
      var shipping = page.querySelector('[data-sum-shipping]');
      var total = page.querySelector('[data-sum-total]');
      var count = page.querySelector('[data-sum-count]');
      var gap = page.querySelector('[data-sum-gap]');

      if (subtotal) { subtotal.textContent = summary.subtotal_fmt; }
      if (shipping) { shipping.textContent = summary.shipping_fmt; }
      if (total) { total.textContent = summary.total_fmt; }
      if (count) { count.textContent = summary.count; }

      if (gap) {
        gap.textContent = summary.gap > 0
          ? GB.fill(CFG.strings.freeGap || 'Add %s more for free delivery.', [formatMoney(summary.gap)])
          : (CFG.strings.freeOk || 'Free delivery unlocked.');
      }

      (summary.items || []).forEach(function (item) {
        var cell = page.querySelector('[data-row-total="' + item.book_id + '"]');
        if (cell) { cell.textContent = item.line_total_fmt; }

        var field = page.querySelector('[data-cart-qty="' + item.book_id + '"]');
        if (field && parseInt(field.value, 10) !== item.quantity) {
          field.value = String(item.quantity);
        }
      });

      GB.setCartCount(summary.count);

      /* Nothing left in the cart — swap in the empty state. */
      if ((summary.items || []).length === 0) {
        window.location.reload();
      }
    }

    function formatMoney(amount) {
      return CFG.currency + (Number(amount) || 0).toFixed(2);
    }

    function mutate(data) {
      return GB.request(CFG.endpoints.cart, { data: data })
        .then(function (payload) {
          if (!payload.ok) { throw new Error(payload.message); }
          paint(payload.summary);
          if (payload.message) { GB.toast(payload.message, 'success'); }
        })
        .catch(function (error) { GB.toast(error.message, 'error'); });
    }

    page.addEventListener('change', function (event) {
      var field = event.target.closest('[data-cart-qty]');
      if (!field) { return; }

      mutate({
        action: 'update',
        book_id: parseInt(field.getAttribute('data-cart-qty'), 10),
        quantity: parseInt(field.value, 10) || 1
      });
    });

    page.addEventListener('click', function (event) {
      var step = event.target.closest('[data-step]');

      if (step) {
        event.preventDefault();
        var widget = step.closest('[data-qty]');
        var input = widget ? widget.querySelector('[data-cart-qty]') : null;

        if (input) {
          var next = (parseInt(input.value, 10) || 1) + parseInt(step.getAttribute('data-step'), 10);

          if (next < 0) { next = 0; }
          if (next > 10) { next = 10; }

          mutate({
            action: 'update',
            book_id: parseInt(input.getAttribute('data-cart-qty'), 10),
            quantity: next
          });
        }
        return;
      }

      var remove = event.target.closest('[data-cart-remove]');

      if (remove) {
        event.preventDefault();
        mutate({
          action: 'remove',
          book_id: parseInt(remove.getAttribute('data-cart-remove'), 10)
        });
      }
    });
  }

  /* ---------------------------------------------------------------------- */
  /* Boot                                                                   */
  /* ---------------------------------------------------------------------- */
  function boot() {
    initNav();
    initNotices();
    initTabs();
    initQuantity();
    initHero();
    initSuggest();
    initCatalogue();
    initCartButtons();
    initWishButtons();
    initCartPage();

    GB.setCartCount(CFG.cartCount);
    GB.setWishCount(CFG.wishCount);

    GB.refreshQuantity = initQuantity;
  }

  window.GB = GB;

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
}(window, document));
