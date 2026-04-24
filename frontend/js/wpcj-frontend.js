(function ($) {
    'use strict';

    var $wrap        = null;
    var totalEntries = 0;
    var votedCount   = 0;

    $(function () {
        $wrap        = $('.wpcj-voting-wrap');
        totalEntries = parseInt( $wrap.data('total') ) || 0;
        votedCount   = parseInt( $wrap.data('voted') ) || 0;
        var readonly = $wrap.data('readonly') === 1 || $wrap.data('readonly') === '1';
        if ( readonly ) return;

        // ── Star click → auto-save (same star = remove vote) ───
        $(document).on('click', '.wpcj-star-front:not([disabled])', function () {
            var $star        = $(this);
            var $stars       = $star.closest('.wpcj-stars-front');
            var $card        = $star.closest('.wpcj-entry-front');
            var clickedScore = parseInt( $star.data('value') );
            var currentScore = parseInt( $stars.data('score') ) || 0;
            var newScore     = ( clickedScore === currentScore ) ? 0 : clickedScore;

            $stars.find('.wpcj-star-front').each(function () {
                $(this).toggleClass( 'active', parseInt( $(this).data('value') ) <= newScore );
            });
            $stars.data('score', newScore);

            saveVote( $card, newScore );
        });

        // ── Notes blur → save (only if already voted) ──────────
        $(document).on('blur', '.wpcj-notes-front', function () {
            var $card = $(this).closest('.wpcj-entry-front');
            var score = parseInt( $card.find('.wpcj-stars-front').data('score') ) || 0;
            if ( score > 0 ) saveVote( $card, score );
        });

        // ── Submit button ───────────────────────────────────────
        $(document).on('click', '#wpcj-submit-btn:not([disabled])', function () {
            if ( ! confirm( wpcjFrontend.i18n.submitConfirm ) ) return;
            var roundId = $(this).data('round');
            var $btn    = $(this);
            $btn.prop('disabled', true).text('…');

            $.post( wpcjFrontend.ajaxUrl, {
                action:   'wpcj_submit_round',
                nonce:    wpcjFrontend.nonce,
                round_id: roundId,
            })
            .done(function (res) {
                if ( res.success ) {
                    window.location.href = wpcjFrontend.panelUrl;
                } else {
                    alert( res.data && res.data.message ? res.data.message : wpcjFrontend.i18n.submitError );
                    $btn.prop('disabled', false).text( wpcjFrontend.i18n.submitBtn || 'Submit Votes' );
                }
            })
            .fail(function () {
                alert( wpcjFrontend.i18n.submitError );
                $btn.prop('disabled', false);
            });
        });
    });

    function saveVote( $card, score ) {
        var entryId    = $card.data('entry-id');
        var roundId    = $card.data('round-id');
        var notes      = $card.find('.wpcj-notes-front').val() || '';
        var $indicator = $card.find('.wpcj-save-indicator');
        var wasVoted   = $card.hasClass('is-voted');

        $indicator.removeClass('saved').text( wpcjFrontend.i18n.saving );

        $.post( wpcjFrontend.ajaxUrl, {
            action:   'wpcj_save_vote',
            nonce:    wpcjFrontend.nonce,
            round_id: roundId,
            entry_id: entryId,
            score:    score,
            notes:    notes,
        })
        .done(function (res) {
            if ( res.success ) {
                $indicator.addClass('saved').text( wpcjFrontend.i18n.saved );

                if ( score === 0 && wasVoted ) {
                    $card.removeClass('is-voted').addClass('is-unvoted');
                    $card.find('.wpcj-voted-badge').remove();
                    updateScoreBadge( $card, 0 );
                    votedCount--;
                    updateProgress();
                    updateSubmitButton();
                    updateFilterTabs();
                } else if ( score > 0 && ! wasVoted ) {
                    $card.removeClass('is-unvoted').addClass('is-voted');
                    if ( ! $card.find('.wpcj-voted-badge').length ) {
                        $card.find('.wpcj-entry-front__thumb-link').prepend(
                            '<span class="wpcj-voted-badge" title="Voted">&#10003;</span>'
                        );
                    }
                    updateScoreBadge( $card, score );
                    votedCount++;
                    updateProgress();
                    updateSubmitButton();
                    updateFilterTabs();
                } else if ( score > 0 ) {
                    updateScoreBadge( $card, score );
                }
            } else {
                $indicator.text('⚠');
            }
        })
        .fail(function () {
            $indicator.text('⚠');
        });
    }

    function updateScoreBadge( $card, score ) {
        var $badge = $card.find('.wpcj-score-badge');
        if ( ! $badge.length ) return;
        if ( score > 0 ) {
            $badge.text( score + '/5' ).removeClass('wpcj-score-badge--hidden');
        } else {
            $badge.text('').addClass('wpcj-score-badge--hidden');
        }
    }

    function updateFilterTabs() {
        var unvotedCount = totalEntries - votedCount;
        $('.wpcj-filter-tab[data-filter="voted"] .wpcj-tab-count').text( '(' + votedCount + ')' );
        $('.wpcj-filter-tab[data-filter="unvoted"] .wpcj-tab-count').text( '(' + unvotedCount + ')' );
    }

    function updateProgress() {
        var pct = totalEntries > 0 ? Math.round( votedCount / totalEntries * 100 ) : 0;
        $('#wpcj-progress-fill').css('width', pct + '%');
        $('#wpcj-progress-label').text( 'Voted: ' + votedCount + ' / ' + totalEntries );
        $wrap.data('voted', votedCount);
    }

    function updateSubmitButton() {
        var $btn = $('#wpcj-submit-btn');
        if ( ! $btn.length ) return;
        var requireAll = $wrap.data('require-all') === 1 || $wrap.data('require-all') === '1';
        if ( requireAll && votedCount < totalEntries ) {
            $btn.prop('disabled', true).addClass('wpcj-btn--disabled');
            return;
        }
        $btn.prop('disabled', false).removeClass('wpcj-btn--disabled');
    }

}(jQuery));
