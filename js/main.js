/* ============================================
   NIKAHIN — Landing Page Interactions
============================================ */

$(document).ready(function () {

  /* ── Navbar scroll state ── */
  function checkScroll() {
    if ($(window).scrollTop() > 40) {
      $('#navbar').addClass('scrolled');
    } else {
      $('#navbar').removeClass('scrolled');
    }
  }
  $(window).on('scroll', checkScroll);
  checkScroll();

  /* ── Smooth-scroll anchors ── */
  $('a[href^="#"]').on('click', function (e) {
    var t = $(this).attr('href');
    if (t.length <= 1) return;
    var $tgt = $(t);
    if ($tgt.length) {
      e.preventDefault();
      $('html, body').animate({ scrollTop: $tgt.offset().top - 80 }, 700);
    }
  });

  /* ── Mobile menu ── */
  $('.hamburger').on('click', function () {
    $('.navbar-mobile').toggleClass('open');
  });
  $('.navbar-mobile a').on('click', function () {
    $('.navbar-mobile').removeClass('open');
  });

  /* ── Active section in navbar on scroll ── */
  var sectionIds = ['#hero', '#designs', '#pricing', '#register', '#about'];
  $(window).on('scroll', function () {
    var sc = $(window).scrollTop() + 120;
    sectionIds.forEach(function (id) {
      var $s = $(id);
      if (!$s.length) return;
      if (sc >= $s.offset().top && sc < $s.offset().top + $s.outerHeight()) {
        $('.navbar-menu a').removeClass('active');
        $('.navbar-menu a[href="' + id + '"]').addClass('active');
      }
    });
  });

  /* ── Live demo modal: open when clicking a design card with data-demo attribute */
  $(document).on('click', '.design-card[data-demo]', function () {
    var url = $(this).data('demo');
    if (!url) return;
    window.open(url, '_blank');
  });

  /* ── Reveal on scroll (very light) ── */
  function checkReveal() {
    $('.reveal').each(function () {
      var $el = $(this);
      var top = $el.offset().top;
      if ($(window).scrollTop() + $(window).height() - 80 > top) {
        $el.addClass('is-visible');
      }
    });
  }
  $(window).on('scroll', checkReveal);
  checkReveal();
});
