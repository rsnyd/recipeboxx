/**
 * @file
 * RecipeBoxx theme JavaScript.
 */

(function (Drupal, once) {
  'use strict';

  /**
   * Mobile navigation toggle.
   */
  Drupal.behaviors.recipeboxxMobileNav = {
    attach: function (context) {
      once('mobile-nav', '.header__mobile-toggle', context).forEach(function (toggle) {
        const mobileNav = document.querySelector('.mobile-nav');
        const body = document.body;

        toggle.addEventListener('click', function () {
          const isOpen = toggle.classList.contains('hamburger--active');

          toggle.classList.toggle('hamburger--active');
          toggle.setAttribute('aria-expanded', !isOpen);

          if (mobileNav) {
            mobileNav.classList.toggle('mobile-nav--open');
            mobileNav.setAttribute('aria-hidden', isOpen);
          }

          body.classList.toggle('mobile-nav-open');
        });

        // Close mobile nav when clicking a link
        if (mobileNav) {
          mobileNav.querySelectorAll('a').forEach(function (link) {
            link.addEventListener('click', function () {
              toggle.classList.remove('hamburger--active');
              toggle.setAttribute('aria-expanded', 'false');
              mobileNav.classList.remove('mobile-nav--open');
              mobileNav.setAttribute('aria-hidden', 'true');
              body.classList.remove('mobile-nav-open');
            });
          });
        }
      });
    }
  };

  /**
   * Sticky header with hide on scroll down.
   */
  Drupal.behaviors.recipeboxxStickyHeader = {
    attach: function (context) {
      once('sticky-header', '.header', context).forEach(function (header) {
        let lastScrollY = window.scrollY;
        let ticking = false;

        function updateHeader() {
          const scrollY = window.scrollY;

          // Add scrolled class when scrolled past threshold
          if (scrollY > 50) {
            header.classList.add('header--scrolled');
          } else {
            header.classList.remove('header--scrolled');
          }

          // Hide header when scrolling down, show when scrolling up
          if (scrollY > lastScrollY && scrollY > 200) {
            header.classList.add('header--hidden');
          } else {
            header.classList.remove('header--hidden');
          }

          lastScrollY = scrollY;
          ticking = false;
        }

        window.addEventListener('scroll', function () {
          if (!ticking) {
            window.requestAnimationFrame(updateHeader);
            ticking = true;
          }
        });
      });
    }
  };

  /**
   * Back to top button.
   */
  Drupal.behaviors.recipeboxxBackToTop = {
    attach: function (context) {
      once('back-to-top', '.back-to-top', context).forEach(function (button) {
        // Show/hide button based on scroll position
        function toggleButton() {
          if (window.scrollY > 500) {
            button.classList.add('back-to-top--visible');
          } else {
            button.classList.remove('back-to-top--visible');
          }
        }

        window.addEventListener('scroll', toggleButton);

        // Scroll to top on click
        button.addEventListener('click', function () {
          window.scrollTo({
            top: 0,
            behavior: 'smooth'
          });
        });
      });
    }
  };

  /**
   * Ingredient checkbox interaction.
   */
  Drupal.behaviors.recipeboxxIngredients = {
    attach: function (context) {
      once('ingredients', '.ingredients__checkbox', context).forEach(function (checkbox) {
        checkbox.addEventListener('change', function () {
          const item = checkbox.closest('.ingredients__item');
          if (item) {
            item.classList.toggle('ingredients__item--checked', checkbox.checked);
          }
        });
      });
    }
  };

  /**
   * Smooth scroll for anchor links.
   */
  Drupal.behaviors.recipeboxxSmoothScroll = {
    attach: function (context) {
      once('smooth-scroll', 'a[href^="#"]', context).forEach(function (link) {
        link.addEventListener('click', function (e) {
          const targetId = link.getAttribute('href');
          if (targetId === '#') return;

          const target = document.querySelector(targetId);
          if (target) {
            e.preventDefault();
            const headerHeight = document.querySelector('.header')?.offsetHeight || 0;
            const targetPosition = target.getBoundingClientRect().top + window.scrollY - headerHeight - 20;

            window.scrollTo({
              top: targetPosition,
              behavior: 'smooth'
            });
          }
        });
      });
    }
  };

  /**
   * Card hover effects enhancement.
   */
  Drupal.behaviors.recipeboxxCards = {
    attach: function (context) {
      once('card-effects', '.recipe-card, .card', context).forEach(function (card) {
        // Add subtle parallax effect on mouse move
        card.addEventListener('mousemove', function (e) {
          const rect = card.getBoundingClientRect();
          const x = e.clientX - rect.left;
          const y = e.clientY - rect.top;

          const centerX = rect.width / 2;
          const centerY = rect.height / 2;

          const rotateX = (y - centerY) / 20;
          const rotateY = (centerX - x) / 20;

          card.style.transform = `perspective(1000px) rotateX(${rotateX}deg) rotateY(${rotateY}deg) translateY(-4px)`;
        });

        card.addEventListener('mouseleave', function () {
          card.style.transform = '';
        });
      });
    }
  };

  /**
   * Search input focus enhancement.
   */
  Drupal.behaviors.recipeboxxSearch = {
    attach: function (context) {
      once('search-focus', '.search', context).forEach(function (search) {
        const input = search.querySelector('.search__input');
        if (input) {
          input.addEventListener('focus', function () {
            search.classList.add('search--focused');
          });

          input.addEventListener('blur', function () {
            search.classList.remove('search--focused');
          });
        }
      });
    }
  };

  /**
   * Lazy load images.
   */
  Drupal.behaviors.recipeboxxLazyLoad = {
    attach: function (context) {
      if ('IntersectionObserver' in window) {
        const imageObserver = new IntersectionObserver(function (entries, observer) {
          entries.forEach(function (entry) {
            if (entry.isIntersecting) {
              const img = entry.target;
              if (img.dataset.src) {
                img.src = img.dataset.src;
                img.removeAttribute('data-src');
              }
              if (img.dataset.srcset) {
                img.srcset = img.dataset.srcset;
                img.removeAttribute('data-srcset');
              }
              img.classList.add('loaded');
              observer.unobserve(img);
            }
          });
        }, {
          rootMargin: '50px 0px',
          threshold: 0.01
        });

        once('lazy-load', 'img[data-src]', context).forEach(function (img) {
          imageObserver.observe(img);
        });
      }
    }
  };

  /**
   * Print recipe functionality.
   */
  Drupal.behaviors.recipeboxxPrint = {
    attach: function (context) {
      once('print-recipe', '.btn--print', context).forEach(function (button) {
        button.addEventListener('click', function (e) {
          e.preventDefault();
          window.print();
        });
      });
    }
  };

})(Drupal, once);
