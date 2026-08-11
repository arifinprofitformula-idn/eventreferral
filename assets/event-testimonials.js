(function () {
  function initEventTestimonials() {
    var section = document.querySelector('[data-event-testimonials-section]');
    var viewport = document.querySelector('[data-event-testimonials]');
    var dotsWrap = document.querySelector('[data-event-testimonial-dots]');
    if (!section || !viewport || !dotsWrap || !window.fetch) return;

    function escapeHtml(value) {
      return String(value || '').replace(/[&<>"']/g, function (char) {
        return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[char];
      });
    }

    fetch('/api/event-testimonials.php', { headers: { 'Accept': 'application/json' } })
      .then(function (response) { return response.ok ? response.json() : null; })
      .then(function (payload) {
        var testimonials = payload && payload.success && Array.isArray(payload.testimonials) ? payload.testimonials : [];
        if (!testimonials.length) return;

        viewport.innerHTML = testimonials.map(function (item, index) {
          return '<article class="testimonial-slide' + (index === 0 ? ' is-active' : '') + '">' +
            '<h3 class="testimonial-name">' + escapeHtml(item.name) + ' <span>- ' + escapeHtml(item.city) + '</span></h3>' +
            '<p class="testimonial-content">' + escapeHtml(item.content) + '</p>' +
            '</article>';
        }).join('');

        dotsWrap.innerHTML = testimonials.map(function (_item, index) {
          return '<button class="testimonial-dot' + (index === 0 ? ' is-active' : '') + '" type="button" tabindex="-1"></button>';
        }).join('');

        section.hidden = false;
        section.classList.add('is-visible');

        var slides = viewport.querySelectorAll('.testimonial-slide');
        var dots = dotsWrap.querySelectorAll('.testimonial-dot');
        var activeIndex = 0;
        if (slides.length <= 1) return;

        window.setInterval(function () {
          slides[activeIndex].classList.remove('is-active');
          if (dots[activeIndex]) dots[activeIndex].classList.remove('is-active');
          activeIndex = (activeIndex + 1) % slides.length;
          slides[activeIndex].classList.add('is-active');
          if (dots[activeIndex]) dots[activeIndex].classList.add('is-active');
        }, 5000);
      })
      .catch(function () {});
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initEventTestimonials);
  } else {
    initEventTestimonials();
  }
})();

