/**
 * @file
 * Cooking mode functionality.
 */

(function ($, Drupal) {
  'use strict';

  Drupal.behaviors.cookingMode = {
    attach: function (context, settings) {
      $('.cooking-mode', context).once('cooking-mode').each(function () {
        const $cookingMode = $(this);
        const $steps = $cookingMode.find('.cooking-step');
        const totalSteps = $steps.length;
        let currentStepIndex = 0;
        let activeTimers = [];

        // Initialize
        updateProgress();

        // Exit cooking mode
        $cookingMode.find('.exit-cooking-mode').on('click', function () {
          if (confirm(Drupal.t('Are you sure you want to exit cooking mode? Active timers will be cleared.'))) {
            window.history.back();
          }
        });

        // Ingredient checkboxes
        $cookingMode.find('.ingredient-checkbox').on('change', function () {
          $(this).closest('li').toggleClass('checked', this.checked);
        });

        // Next step
        $cookingMode.find('.next-step').on('click', function () {
          if (currentStepIndex < totalSteps - 1) {
            $steps.eq(currentStepIndex).removeClass('active').addClass('completed');
            currentStepIndex++;
            $steps.eq(currentStepIndex).addClass('active');
            updateProgress();
            scrollToCurrentStep();
          }
        });

        // Previous step
        $cookingMode.find('.prev-step').on('click', function () {
          if (currentStepIndex > 0) {
            $steps.eq(currentStepIndex).removeClass('active');
            currentStepIndex--;
            $steps.eq(currentStepIndex).removeClass('completed').addClass('active');
            updateProgress();
            scrollToCurrentStep();
          }
        });

        // Mark complete
        $cookingMode.find('.mark-complete').on('click', function () {
          const $currentStep = $steps.eq(currentStepIndex);
          $currentStep.addClass('completed');

          if (currentStepIndex < totalSteps - 1) {
            $currentStep.removeClass('active');
            currentStepIndex++;
            $steps.eq(currentStepIndex).addClass('active');
            updateProgress();
            scrollToCurrentStep();
          }
        });

        // Finish cooking
        $cookingMode.find('.finish-cooking').on('click', function () {
          $steps.eq(currentStepIndex).addClass('completed');

          if (confirm(Drupal.t('Congratulations! Mark this recipe as "I made this"?'))) {
            // Trigger "I made this" functionality
            const recipeId = $cookingMode.data('recipe-id');
            $.post('/recipe/' + recipeId + '/made-it', function (response) {
              alert(Drupal.t('Great job! Recipe marked as made.'));
              window.close();
            });
          } else {
            window.close();
          }
        });

        // Start timer
        $cookingMode.find('.start-timer').on('click', function () {
          const duration = parseInt($(this).data('duration'));
          const label = $(this).data('label');

          startTimer(duration, label);
        });

        function startTimer(duration, label) {
          const timerId = 'timer-' + Date.now();
          const endTime = Date.now() + (duration * 1000);

          const timer = {
            id: timerId,
            label: label,
            duration: duration,
            endTime: endTime,
            element: null
          };

          // Create timer element
          const $timerEl = $('<div class="active-timer" id="' + timerId + '"></div>');
          $timerEl.append('<div class="timer-label">' + label + '</div>');
          $timerEl.append('<div class="timer-display">--:--</div>');
          $timerEl.append('<button class="timer-cancel button--small">Cancel</button>');

          $('#active-timers').append($timerEl);
          timer.element = $timerEl;

          $timerEl.find('.timer-cancel').on('click', function () {
            cancelTimer(timer);
          });

          activeTimers.push(timer);

          // Update timer display
          const interval = setInterval(function () {
            const remaining = Math.max(0, timer.endTime - Date.now());
            const minutes = Math.floor(remaining / 60000);
            const seconds = Math.floor((remaining % 60000) / 1000);

            timer.element.find('.timer-display').text(
              String(minutes).padStart(2, '0') + ':' + String(seconds).padStart(2, '0')
            );

            if (remaining <= 0) {
              clearInterval(interval);
              timerComplete(timer);
            }
          }, 100);

          timer.interval = interval;
        }

        function timerComplete(timer) {
          timer.element.addClass('timer-complete');
          playTimerSound();

          if (Notification.permission === 'granted') {
            new Notification('Timer Complete', {
              body: timer.label + ' is done!',
              icon: '/modules/custom/recipeboxx_recipe/images/timer-icon.png'
            });
          }

          alert(Drupal.t('@label is done!', {'@label': timer.label}));

          setTimeout(function () {
            cancelTimer(timer);
          }, 5000);
        }

        function cancelTimer(timer) {
          if (timer.interval) {
            clearInterval(timer.interval);
          }

          timer.element.remove();
          activeTimers = activeTimers.filter(t => t.id !== timer.id);
        }

        function playTimerSound() {
          // Create audio context and play a simple beep
          if (typeof AudioContext !== 'undefined' || typeof webkitAudioContext !== 'undefined') {
            const context = new (AudioContext || webkitAudioContext)();
            const oscillator = context.createOscillator();
            const gainNode = context.createGain();

            oscillator.connect(gainNode);
            gainNode.connect(context.destination);

            oscillator.frequency.value = 800;
            oscillator.type = 'sine';

            gainNode.gain.setValueAtTime(0.3, context.currentTime);
            gainNode.gain.exponentialRampToValueAtTime(0.01, context.currentTime + 0.5);

            oscillator.start(context.currentTime);
            oscillator.stop(context.currentTime + 0.5);
          }
        }

        function updateProgress() {
          const progress = ((currentStepIndex + 1) / totalSteps) * 100;
          $('#progress-fill').css('width', progress + '%');
          $('#current-step-num').text(currentStepIndex + 1);
        }

        function scrollToCurrentStep() {
          const $currentStep = $steps.eq(currentStepIndex);
          $currentStep[0].scrollIntoView({
            behavior: 'smooth',
            block: 'center'
          });
        }

        // Request notification permission
        if (Notification.permission === 'default') {
          Notification.requestPermission();
        }

        // Prevent accidental page unload
        $(window).on('beforeunload', function () {
          if (activeTimers.length > 0) {
            return Drupal.t('You have active timers. Are you sure you want to leave?');
          }
        });
      });
    }
  };

})(jQuery, Drupal);
