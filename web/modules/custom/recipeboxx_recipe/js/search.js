/**
 * @file
 * JavaScript for recipe search functionality.
 */

(function (Drupal, $) {
  'use strict';

  /**
   * Enhance recipe search form with AJAX filtering.
   */
  Drupal.behaviors.recipeSearch = {
    attach: function (context, settings) {
      // Add active class to current filter facets
      $('.recipe-search-facets a', context).once('recipe-search-facet').each(function() {
        var $link = $(this);
        var linkUrl = new URL($link.attr('href'), window.location.origin);
        var currentUrl = new URL(window.location.href);

        // Check if this facet is active in current URL
        var linkParams = linkUrl.searchParams;
        var currentParams = currentUrl.searchParams;

        var isActive = false;
        linkParams.forEach(function(value, key) {
          if (currentParams.get(key) === value) {
            isActive = true;
          }
        });

        if (isActive) {
          $link.addClass('active');
        }
      });

      // Enhance search form submission
      $('#recipeboxx-recipe-search-form', context).once('recipe-search-form').on('submit', function(e) {
        var $form = $(this);
        var keyword = $form.find('input[name="keyword"]').val();

        // Show loading indicator
        $form.find('input[type="submit"]').prop('disabled', true).val(Drupal.t('Searching...'));
      });

      // Add clear filter functionality
      $('.recipe-search-clear-filters', context).once('recipe-search-clear').on('click', function(e) {
        e.preventDefault();
        window.location.href = $(this).attr('href');
      });
    }
  };

  /**
   * Add favorite/bookmark functionality for recipe cards.
   */
  Drupal.behaviors.recipeCardActions = {
    attach: function (context, settings) {
      $('.recipe-card-bookmark', context).once('recipe-bookmark').on('click', function(e) {
        e.preventDefault();
        var $button = $(this);
        var recipeId = $button.data('recipe-id');

        // TODO: Implement bookmark AJAX call
        $button.toggleClass('bookmarked');

        // Update button text
        if ($button.hasClass('bookmarked')) {
          $button.find('.bookmark-text').text(Drupal.t('Bookmarked'));
        } else {
          $button.find('.bookmark-text').text(Drupal.t('Bookmark'));
        }
      });
    }
  };

})(Drupal, jQuery);
