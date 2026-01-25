/**
 * @file
 * Meal planner drag and drop functionality.
 */

(function ($, Drupal) {
  'use strict';

  Drupal.behaviors.mealPlannerDragDrop = {
    attach: function (context, settings) {
      $('.meal-plan-week-grid', context).once('meal-planner').each(function () {
        const $grid = $(this);
        const planId = $grid.data('plan-id');

        // Make recipe cards draggable
        $('.meal-recipe-card', $grid).draggable({
          helper: 'clone',
          cursor: 'move',
          revert: 'invalid',
          zIndex: 1000,
          opacity: 0.7,
          start: function (event, ui) {
            $(this).addClass('dragging');
            ui.helper.addClass('drag-helper');
          },
          stop: function (event, ui) {
            $(this).removeClass('dragging');
          }
        });

        // Make meal slots droppable
        $('.meal-slot', $grid).droppable({
          accept: '.meal-recipe-card',
          hoverClass: 'drop-hover',
          drop: function (event, ui) {
            const $slot = $(this);
            const $dragged = ui.draggable;
            const entryId = $dragged.data('entry-id');
            const newDay = $slot.data('day');
            const newMealType = $slot.data('meal-type');

            // Move the recipe card
            $dragged.detach().appendTo($slot.find('.meal-recipes'));

            // Update via AJAX
            updateMealPlanEntry(planId, entryId, newDay, newMealType);

            // Hide empty message
            $slot.find('.meal-empty').hide();
          }
        });

        // Delete recipe from meal plan
        $grid.on('click', '.remove-from-plan', function (e) {
          e.preventDefault();

          if (!confirm(Drupal.t('Remove this recipe from the meal plan?'))) {
            return;
          }

          const $card = $(this).closest('.meal-recipe-card');
          const entryId = $card.data('entry-id');

          $.ajax({
            url: '/meal-plan/' + planId + '/entry/' + entryId + '/delete',
            method: 'POST',
            dataType: 'json',
            success: function (response) {
              if (response.status === 'success') {
                $card.fadeOut(function () {
                  $card.remove();

                  // Show empty message if no recipes left
                  const $slot = $card.closest('.meal-slot');
                  if ($slot.find('.meal-recipe-card').length === 0) {
                    $slot.find('.meal-empty').show();
                  }
                });
              }
            }
          });
        });

        function updateMealPlanEntry(planId, entryId, day, mealType) {
          $.ajax({
            url: '/meal-plan/' + planId + '/entry/' + entryId + '/move',
            method: 'POST',
            data: {
              day: day,
              meal_type: mealType
            },
            dataType: 'json',
            success: function (response) {
              if (response.status === 'success') {
                // Show success message
                Drupal.behaviors.mealPlannerDragDrop.showMessage('Recipe moved successfully', 'status');
              } else {
                Drupal.behaviors.mealPlannerDragDrop.showMessage('Error moving recipe', 'error');
              }
            },
            error: function () {
              Drupal.behaviors.mealPlannerDragDrop.showMessage('Error moving recipe', 'error');
            }
          });
        }
      });

      // Add recipe to meal plan (from search/browse)
      $('.add-to-meal-plan', context).once('add-to-plan').on('click', function (e) {
        e.preventDefault();

        const $button = $(this);
        const recipeId = $button.data('recipe-id');

        // Show modal to select day and meal type
        showMealPlanModal(recipeId);
      });

      function showMealPlanModal(recipeId) {
        const days = [
          'Monday', 'Tuesday', 'Wednesday', 'Thursday',
          'Friday', 'Saturday', 'Sunday'
        ];
        const mealTypes = ['breakfast', 'lunch', 'dinner', 'snack'];

        let modalHtml = '<div class="meal-plan-modal">';
        modalHtml += '<h3>' + Drupal.t('Add to Meal Plan') + '</h3>';

        modalHtml += '<div class="form-item">';
        modalHtml += '<label>' + Drupal.t('Day') + '</label>';
        modalHtml += '<select id="meal-plan-day" class="form-select">';
        days.forEach((day, index) => {
          modalHtml += '<option value="' + index + '">' + day + '</option>';
        });
        modalHtml += '</select>';
        modalHtml += '</div>';

        modalHtml += '<div class="form-item">';
        modalHtml += '<label>' + Drupal.t('Meal Type') + '</label>';
        modalHtml += '<select id="meal-plan-meal-type" class="form-select">';
        mealTypes.forEach(type => {
          modalHtml += '<option value="' + type + '">' +
                       type.charAt(0).toUpperCase() + type.slice(1) +
                       '</option>';
        });
        modalHtml += '</select>';
        modalHtml += '</div>';

        modalHtml += '<div class="modal-actions">';
        modalHtml += '<button class="button button--primary" id="confirm-add-to-plan">' +
                     Drupal.t('Add to Plan') + '</button>';
        modalHtml += '<button class="button" id="cancel-add-to-plan">' +
                     Drupal.t('Cancel') + '</button>';
        modalHtml += '</div>';

        modalHtml += '</div>';

        const $modal = $(modalHtml);
        $('body').append($modal);

        $modal.find('#confirm-add-to-plan').on('click', function () {
          const day = $('#meal-plan-day').val();
          const mealType = $('#meal-plan-meal-type').val();

          addRecipeToMealPlan(recipeId, day, mealType);
          $modal.remove();
        });

        $modal.find('#cancel-add-to-plan').on('click', function () {
          $modal.remove();
        });
      }

      function addRecipeToMealPlan(recipeId, day, mealType) {
        $.ajax({
          url: '/meal-plan/current/add-recipe',
          method: 'POST',
          data: {
            recipe_id: recipeId,
            day: day,
            meal_type: mealType
          },
          dataType: 'json',
          success: function (response) {
            if (response.status === 'success') {
              Drupal.behaviors.mealPlannerDragDrop.showMessage(
                Drupal.t('Recipe added to meal plan'),
                'status'
              );
            } else {
              Drupal.behaviors.mealPlannerDragDrop.showMessage(
                response.message || Drupal.t('Error adding recipe'),
                'error'
              );
            }
          },
          error: function () {
            Drupal.behaviors.mealPlannerDragDrop.showMessage(
              Drupal.t('Error adding recipe to meal plan'),
              'error'
            );
          }
        });
      }
    },

    showMessage: function (message, type) {
      const $message = $('<div class="messages messages--' + type + '"></div>');
      $message.text(message);

      $('body').prepend($message);

      setTimeout(function () {
        $message.fadeOut(function () {
          $message.remove();
        });
      }, 3000);
    }
  };

})(jQuery, Drupal);
