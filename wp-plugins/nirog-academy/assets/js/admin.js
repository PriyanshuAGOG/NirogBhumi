/*
 * Nirog Bhumi Academy — admin screens.
 */
(function ($) {
  'use strict';
  var cfg = window.NBAAdmin || { i18n: {} };

  /* ---------------------------------------------------------------- curriculum builder */

  var $cb = $('.nba-cb');
  if ($cb.length) {
    var programme = $cb.data('programme');
    var $status = $cb.find('.nba-cb-status');

    var post = function (action, data) {
      return $.post(cfg.ajax, $.extend({ action: action, nonce: cfg.nonce, programme: programme }, data));
    };

    var makeSortable = function () {
      $cb.find('.nba-cb-modules').sortable({ handle: '.nba-cb-module-head .nba-cb-handle', axis: 'y', placeholder: 'nba-cb-placeholder' });
      $cb.find('.nba-cb-lessons').sortable({ connectWith: '.nba-cb-lessons', handle: '.nba-cb-handle', placeholder: 'nba-cb-placeholder' });
    };
    makeSortable();

    $cb.on('click', '.nba-cb-save', function () {
      var modules = [];
      $cb.find('.nba-cb-module').each(function () {
        modules.push({
          id: $(this).data('id'),
          lessons: $(this).find('.nba-cb-lesson').map(function () { return $(this).data('id'); }).get()
        });
      });
      var orphans = $cb.find('.nba-cb-orphans .nba-cb-lesson').map(function () { return $(this).data('id'); }).get();
      if (orphans.length) { modules.push({ id: 0, lessons: orphans }); }
      $status.text(cfg.i18n.saving);
      post('nba_cb_order', { modules: JSON.stringify(modules) })
        .done(function () { $status.text(cfg.i18n.saved); })
        .fail(function () { $status.text(cfg.i18n.error); });
    });

    $cb.on('click', '.nba-cb-add-module', function () {
      var title = window.prompt(cfg.i18n.moduleName);
      if (!title) { return; }
      post('nba_cb_add_module', { title: title }).done(function (res) {
        if (!res.success) { $status.text(cfg.i18n.error); return; }
        var $li = $('<li class="nba-cb-module"><div class="nba-cb-module-head"><span class="nba-cb-handle" aria-hidden="true">⋮⋮</span><strong class="nba-cb-module-title"></strong> <a class="button button-small"></a></div><ol class="nba-cb-lessons"></ol></li>');
        $li.attr('data-id', res.data.id).data('id', res.data.id);
        $li.find('.nba-cb-module-title').text(res.data.title);
        $li.find('a').attr('href', res.data.add_lesson).text('+ Lesson');
        $cb.find('.nba-cb-modules').append($li);
        makeSortable();
      }).fail(function () { $status.text(cfg.i18n.error); });
    });

    $cb.on('click', '.nba-cb-rename', function () {
      var $m = $(this).closest('.nba-cb-module');
      var $title = $m.find('.nba-cb-module-title');
      var title = window.prompt(cfg.i18n.moduleName, $title.text());
      if (!title) { return; }
      post('nba_cb_rename_module', { module: $m.data('id'), title: title }).done(function () { $title.text(title); });
    });

    $cb.on('click', '.nba-cb-delete-module', function () {
      if (!window.confirm(cfg.i18n.confirmDelete)) { return; }
      var $m = $(this).closest('.nba-cb-module');
      post('nba_cb_delete_module', { module: $m.data('id') }).done(function () { window.location.reload(); });
    });
  }

  /* ---------------------------------------------------------------- lesson screen */

  // Show only the modules of the chosen programme, and hide fields that do not apply.
  var $programme = $('#nba_programme_id');
  var $module = $('#nba_module_id');
  var $placement = $('#nba_placement');
  var refresh = function () {
    var pid = String($programme.val());
    $module.find('option').each(function () {
      var p = String($(this).data('programme'));
      var show = $(this).val() === '0' || p === pid;
      $(this).prop('hidden', !show).prop('disabled', !show);
    });
    if ($module.find('option:selected').prop('disabled')) { $module.val('0'); }
    var bonus = $placement.val() === 'bonus';
    $module.closest('.nba-field').toggle(!bonus);
    $('#nba_category').closest('.nba-field').toggle(bonus);
  };
  if ($programme.length && $module.length) {
    $programme.on('change', refresh);
    $placement.on('change', refresh);
    refresh();
  }

  // Media source fields.
  var $provider = $('#nba_provider');
  if ($provider.length) {
    var map = { hls: '#nba_path', file: '#nba_file', youtube: '#nba_youtube', external: '#nba_external_url' };
    var showProvider = function () {
      $.each(map, function (key, sel) { $(sel).closest('.nba-field').toggle($provider.val() === key); });
    };
    $provider.on('change', showProvider);
    showProvider();
  }

  // Lesson downloads (WordPress media modal).
  var $res = $('.nba-resources');
  if ($res.length && window.wp && wp.media) {
    var frame;
    var $input = $res.find('.nba-resources-input');
    var $list = $res.find('.nba-resources-list');
    var sync = function () {
      $input.val($list.find('li').map(function () { return $(this).data('id'); }).get().join(','));
    };
    $res.on('click', '.nba-resources-add', function (e) {
      e.preventDefault();
      if (!frame) {
        frame = wp.media({ title: cfg.i18n.chooseFiles, button: { text: cfg.i18n.useFiles }, multiple: true });
        frame.on('select', function () {
          frame.state().get('selection').each(function (att) {
            var a = att.toJSON();
            if ($list.find('li[data-id="' + a.id + '"]').length) { return; }
            var $li = $('<li><button type="button" class="button-link nba-resource-remove" aria-label="Remove">×</button></li>');
            $li.attr('data-id', a.id).data('id', a.id).prepend(document.createTextNode((a.title || a.filename) + ' '));
            $list.append($li);
          });
          sync();
        });
      }
      frame.open();
    });
    $res.on('click', '.nba-resource-remove', function () { $(this).closest('li').remove(); sync(); });
  }
})(jQuery);
