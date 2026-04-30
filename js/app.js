/* ============================================
   NIKAHIN — App JS
   Multi-step form, photo uploads, RSVP, copy-to-clipboard
============================================ */

$(function () {

  /* =========================================
     Multi-step form
  ========================================= */
  if ($('.steps-bar').length) {
    var totalSteps = $('.step-pane').length;
    var current = parseInt($('.step-pane.is-active').data('step'), 10) || 1;

    function goToStep(n) {
      n = Math.max(1, Math.min(totalSteps, n));
      $('.step-pane').removeClass('is-active').filter('[data-step="' + n + '"]').addClass('is-active');
      $('.steps-bar .step').each(function () {
        var sn = parseInt($(this).data('step'), 10);
        $(this).removeClass('is-active is-done');
        if (sn === n) $(this).addClass('is-active');
        else if (sn < n) $(this).addClass('is-done');
      });
      current = n;
      $('html, body').animate({ scrollTop: $('.steps-bar').offset().top - 100 }, 350);
      saveDraft();
    }

    $(document).on('click', '.btn-next', function (e) {
      e.preventDefault();
      // basic required validation in current pane
      var ok = true;
      $('.step-pane.is-active [required]').each(function () {
        if (!$(this).val()) {
          $(this).css('border-color', '#b94a4a');
          ok = false;
        } else {
          $(this).css('border-color', '');
        }
      });
      if (!ok) return;
      goToStep(current + 1);
    });

    $(document).on('click', '.btn-prev', function (e) {
      e.preventDefault();
      goToStep(current - 1);
    });

    $(document).on('click', '.steps-bar .step', function () {
      var n = parseInt($(this).data('step'), 10);
      if (n < current) goToStep(n);
    });
  }

  /* =========================================
     Auto-save draft (debounced)
  ========================================= */
  var saveTimer;
  function saveDraft() {
    var $form = $('#invitation-form');
    if (!$form.length) return;
    clearTimeout(saveTimer);
    $('#save-status').text('Menyimpan…').css('color', 'var(--muted)');
    saveTimer = setTimeout(function () {
      var data = $form.serializeArray();
      data.push({ name: 'invitation_id', value: $form.data('invitation-id') });
      $.ajax({
        url: $form.data('save-url'),
        method: 'POST',
        data: data,
        dataType: 'json'
      }).done(function (r) {
        if (r.ok) {
          $('#save-status').text('Tersimpan ' + new Date().toLocaleTimeString())
            .css('color', '#4a9460');
        } else {
          $('#save-status').text('Gagal menyimpan').css('color', '#b94a4a');
        }
      }).fail(function () {
        $('#save-status').text('Tidak dapat menyimpan (offline?)').css('color', '#b94a4a');
      });
    }, 800);
  }
  $(document).on('input change', '#invitation-form input, #invitation-form select, #invitation-form textarea', saveDraft);

  /* =========================================
     Add gift account row
  ========================================= */
  $(document).on('click', '.btn-add-gift', function (e) {
    e.preventDefault();
    var idx = $('.gift-row').length;
    var html = '' +
      '<div class="gift-row">' +
        '<div class="field"><label>Bank / E-wallet</label><input type="text" name="gift[' + idx + '][provider]" placeholder="BCA, GoPay, OVO…"></div>' +
        '<div class="field"><label>Nomor</label><input type="text" name="gift[' + idx + '][number]" placeholder="123 456 7890"></div>' +
        '<div class="field"><label>Atas Nama</label><input type="text" name="gift[' + idx + '][holder]" placeholder="Nama pemilik"></div>' +
        '<button class="btn-remove-gift" title="Hapus">✕</button>' +
      '</div>';
    $('.gift-rows').append(html);
    saveDraft();
  });
  $(document).on('click', '.btn-remove-gift', function (e) {
    e.preventDefault();
    $(this).closest('.gift-row').remove();
    saveDraft();
  });

  /* =========================================
     Photo uploads (groom, bride, prewedding)
  ========================================= */
  $(document).on('change', '.photo-input', function () {
    var $wrap   = $(this).closest('.photo-uploader');
    var slot    = $wrap.data('slot');     // groom_photo | bride_photo | prewedding | reference
    var invId   = $wrap.data('invitation-id');
    var url     = $wrap.data('upload-url');
    var $grid   = $wrap.find('.photo-grid');
    var files   = this.files;
    if (!files || !files.length || !invId) return;
    var fd = new FormData();
    fd.append('invitation_id', invId);
    fd.append('type', slot);
    for (var i = 0; i < files.length; i++) {
      fd.append('files[]', files[i]);
    }
    $wrap.find('.photo-add').css('opacity', .5);
    $.ajax({
      url: url, method: 'POST', data: fd,
      processData: false, contentType: false, dataType: 'json'
    }).done(function (r) {
      if (r.ok && r.assets) {
        r.assets.forEach(function (a) {
          $grid.append(
            '<div class="photo-thumb" data-id="' + a.id + '">' +
              '<img src="' + a.url + '" alt="">' +
              '<button class="photo-remove" data-id="' + a.id + '">✕</button>' +
            '</div>'
          );
        });
      } else {
        alert(r.error || 'Gagal mengunggah');
      }
    }).fail(function () { alert('Gagal mengunggah file'); })
      .always(function () { $wrap.find('.photo-add').css('opacity', 1); });
  });

  $(document).on('click', '.photo-remove', function (e) {
    e.preventDefault();
    var id = $(this).data('id');
    var $thumb = $(this).closest('.photo-thumb');
    if (!confirm('Hapus foto ini?')) return;
    $.post(window.NIKAHIN_REMOVE_ASSET_URL || '/api/upload.php?action=delete',
           { asset_id: id, _csrf: window.NIKAHIN_CSRF }, function () { $thumb.remove(); }, 'json');
  });

  /* =========================================
     Copy-to-clipboard
  ========================================= */
  $(document).on('click', '[data-copy]', function (e) {
    e.preventDefault();
    var v = $(this).data('copy');
    var $btn = $(this);
    if (navigator.clipboard) {
      navigator.clipboard.writeText(v).then(function () {
        var orig = $btn.text();
        $btn.text('Tersalin ✓');
        setTimeout(function () { $btn.text(orig); }, 1500);
      });
    } else {
      // legacy fallback
      var ta = document.createElement('textarea');
      ta.value = v; document.body.appendChild(ta); ta.select();
      try { document.execCommand('copy'); } catch (e) {}
      document.body.removeChild(ta);
    }
  });

  /* =========================================
     OTP: resend countdown
  ========================================= */
  if ($('#otp-resend').length) {
    var t = 60;
    var $btn = $('#otp-resend');
    $btn.prop('disabled', true);
    var iv = setInterval(function () {
      t--;
      $btn.text('Kirim ulang dalam ' + t + 'd');
      if (t <= 0) {
        clearInterval(iv);
        $btn.prop('disabled', false).text('Kirim ulang kode');
      }
    }, 1000);
  }

  /* =========================================
     RSVP submit (public viewer)
  ========================================= */
  $(document).on('submit', '#rsvp-form', function (e) {
    e.preventDefault();
    var $f = $(this);
    $.ajax({
      url: $f.attr('action'), method: 'POST', data: $f.serialize(), dataType: 'json'
    }).done(function (r) {
      if (r.ok) {
        $f.replaceWith(
          '<div style="text-align:center;padding:24px;">' +
            '<h3 style="font-family:var(--inv-head);font-style:italic;">Terima kasih!</h3>' +
            '<p>Kehadiran Anda telah kami catat.</p>' +
          '</div>'
        );
      } else {
        alert(r.error || 'Gagal mengirim RSVP');
      }
    }).fail(function () { alert('Gagal mengirim RSVP'); });
  });

  /* =========================================
     Guestbook submit
  ========================================= */
  $(document).on('submit', '#guestbook-form', function (e) {
    e.preventDefault();
    var $f = $(this);
    $.ajax({
      url: $f.attr('action'), method: 'POST', data: $f.serialize(), dataType: 'json'
    }).done(function (r) {
      if (r.ok && r.message) {
        var html = '<div class="inv-gb-msg">' +
          '<div class="inv-gb-msg-head"><span class="inv-gb-msg-name">' + r.message.guest_name + '</span><span>' + r.message.created_at + '</span></div>' +
          '<div class="inv-gb-msg-text">' + r.message.message + '</div>' +
          '</div>';
        $('.inv-gb-list').prepend(html);
        $f[0].reset();
      } else {
        alert(r.error || 'Gagal mengirim ucapan');
      }
    });
  });

  /* =========================================
     Generation: poll status
  ========================================= */
  if ($('#generation-status').length) {
    var pollUrl = $('#generation-status').data('poll-url');
    var redirectUrl = $('#generation-status').data('redirect-url');
    var poll = setInterval(function () {
      $.getJSON(pollUrl, function (r) {
        if (r.status === 'ready_for_preview' || r.status === 'published') {
          clearInterval(poll);
          window.location.href = redirectUrl;
        } else if (r.status === 'flagged' || r.status === 'failed') {
          clearInterval(poll);
          $('#generation-status').html('<p style="color:#b94a4a;">' + (r.error || 'Gagal membuat undangan') + '</p>' +
            '<a href="' + redirectUrl.replace('/preview.php', '/form.php') + '" class="btn btn-outline">Kembali ke form</a>');
        }
      });
    }, 3000);
  }
});
