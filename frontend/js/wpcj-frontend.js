(function ($) {
    'use strict';

    var $wrap        = null;
    var totalEntries = 0;
    var votedCount   = 0;

    // ── Lightbox ─────────────────────────────────────────────────
    var lb = {
        $el: null, $img: null,
        scale: 1, tx: 0, ty: 0,
        dragging: false, startX: 0, startY: 0, startTx: 0, startTy: 0,

        init: function () {
            $('body').append(
                '<div id="wpcj-lightbox">' +
                '<div class="wpcj-lb-overlay"></div>' +
                '<img class="wpcj-lb-img" src="" alt="">' +
                '<button class="wpcj-lb-close" aria-label="Close">&times;</button>' +
                '<div class="wpcj-lb-controls">' +
                '<button class="wpcj-lb-zoom-out" title="Zoom out">&#8722;</button>' +
                '<span class="wpcj-lb-zoom-label">100%</span>' +
                '<button class="wpcj-lb-zoom-in" title="Zoom in">+</button>' +
                '</div></div>'
            );
            this.$el  = $('#wpcj-lightbox');
            this.$img = this.$el.find('.wpcj-lb-img');
            this._bind();
        },

        open: function (src, alt) {
            this.scale = 1; this.tx = 0; this.ty = 0;
            this.$img.attr({ src: src, alt: alt || '' });
            this._apply();
            this.$el.addClass('wpcj-lb--open');
            $('body').addClass('wpcj-lb-body-lock');
        },

        close: function () {
            this.$el.removeClass('wpcj-lb--open');
            $('body').removeClass('wpcj-lb-body-lock');
            this.$img.attr('src', '');
        },

        zoom: function (dir) {
            this.scale = Math.min(5, Math.max(0.2, this.scale * (dir > 0 ? 1.25 : 0.8)));
            if (this.scale <= 1) { this.tx = 0; this.ty = 0; }
            this._apply();
        },

        _apply: function () {
            this.$img.css('transform',
                'translate(calc(-50% + ' + this.tx + 'px), calc(-50% + ' + this.ty + 'px)) scale(' + this.scale + ')'
            );
            this.$el.find('.wpcj-lb-zoom-label').text(Math.round(this.scale * 100) + '%');
            this.$img.toggleClass('is-draggable', this.scale > 1);
        },

        _bind: function () {
            var self = this;

            this.$el.on('click', '.wpcj-lb-overlay', function () { self.close(); });
            this.$el.on('click', '.wpcj-lb-close',   function () { self.close(); });
            this.$el.on('click', '.wpcj-lb-zoom-in',  function () { self.zoom(1); });
            this.$el.on('click', '.wpcj-lb-zoom-out', function () { self.zoom(-1); });

            this.$el.on('wheel', function (e) {
                e.preventDefault();
                self.zoom(-e.originalEvent.deltaY);
            });

            this.$img.on('mousedown', function (e) {
                if (self.scale <= 1) return;
                e.preventDefault();
                self.dragging = true;
                self.startX = e.clientX; self.startY = e.clientY;
                self.startTx = self.tx;  self.startTy = self.ty;
                self.$img.addClass('is-dragging');
            });

            $(document).on('mousemove.wpcjlb', function (e) {
                if (!self.dragging) return;
                self.tx = self.startTx + (e.clientX - self.startX);
                self.ty = self.startTy + (e.clientY - self.startY);
                self._apply();
            }).on('mouseup.wpcjlb', function () {
                if (self.dragging) {
                    self.dragging = false;
                    self.$img.removeClass('is-dragging');
                }
            });

            $(document).on('keydown.wpcjlb', function (e) {
                if (e.key === 'Escape' && self.$el.hasClass('wpcj-lb--open')) { self.close(); }
            });

            $(document).on('click', '.wpcj-entry-front__thumb-link', function (e) {
                e.preventDefault();
                self.open($(this).attr('href'), $(this).find('img').attr('alt') || '');
            });
        }
    };

    $(function () {
        $wrap        = $('.wpcj-voting-wrap');
        totalEntries = parseInt( $wrap.data('total') ) || 0;
        votedCount   = parseInt( $wrap.data('voted') ) || 0;
        var readonly = $wrap.data('readonly') === 1 || $wrap.data('readonly') === '1';

        lb.init();

        if ( readonly ) return;

        // ── Star click → set score ──────────────────────────────
        $(document).on('click', '.wpcj-star-front:not([disabled])', function () {
            var $star  = $(this);
            var $stars = $star.closest('.wpcj-stars-front');
            var $card  = $star.closest('.wpcj-entry-front');
            var score  = parseInt( $star.data('value') );

            $stars.find('.wpcj-star-front').each(function () {
                $(this).toggleClass( 'active', parseInt( $(this).data('value') ) <= score );
            });
            $stars.data('score', score);

            saveVote( $card, score );
        });

        // ── Reset button → remove vote ──────────────────────────
        $(document).on('click', '.wpcj-reset-vote', function () {
            var $card  = $(this).closest('.wpcj-entry-front');
            var $stars = $card.find('.wpcj-stars-front');
            $stars.find('.wpcj-star-front').removeClass('active');
            $stars.data('score', 0);
            saveVote( $card, 0 );
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
                    $card.find('.wpcj-reset-vote').addClass('wpcj-hidden');
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
                    $card.find('.wpcj-reset-vote').removeClass('wpcj-hidden');
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
