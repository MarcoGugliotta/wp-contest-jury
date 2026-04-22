/* global jQuery, wpcjData */
(function ($) {
    'use strict';

    // Star rating interaction
    $(document).on('click', '.wpcj-star', function () {
        var $stars  = $(this).closest('.wpcj-stars');
        var score   = parseInt($(this).data('value'), 10);
        $stars.data('score', score);
        $stars.find('.wpcj-star').each(function (i) {
            $(this).toggleClass('active', i < score);
        });
    });

    // Save / update vote via AJAX
    $(document).on('click', '.wpcj-save-vote', function () {
        var $btn     = $(this);
        var $card    = $btn.closest('.wpcj-entry-card');
        var round    = $btn.data('round');
        var entry    = $btn.data('entry');
        var score    = $card.find('.wpcj-stars').data('score') || 0;
        var notes    = $card.find('.wpcj-notes').val();

        if (score === 0) {
            alert(wpcjData.i18n ? wpcjData.i18n.selectScore : 'Please select a score before saving.');
            return;
        }

        $btn.prop('disabled', true);

        $.post(wpcjData.ajaxUrl, {
            action:   'wpcj_save_vote',
            nonce:    wpcjData.nonce,
            round_id: round,
            entry_id: entry,
            score:    score,
            notes:    notes
        }, function (response) {
            if (response.success) {
                $btn.text('Update');
                var $indicator = $card.find('.wpcj-saved-indicator');
                if (!$indicator.length) {
                    $btn.after('<span class="wpcj-saved-indicator">Saved ✓</span>');
                }
            } else {
                alert(response.data || 'Error saving vote.');
            }
        }).always(function () {
            $btn.prop('disabled', false);
        });
    });

}(jQuery));
