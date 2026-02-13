(function ($) {
  "use strict";

  /******************************************************************
   * CONFIG
   ******************************************************************/
  var HOLD_MINUTES = (typeof twwtfa !== 'undefined' && twwtfa.hold_minutes) ? parseInt(twwtfa.hold_minutes, 10) : 10;
  var HOLD_MS = HOLD_MINUTES * 60 * 1000;

  /******************************************************************
   * SESSION STORAGE HELPERS (No Cookies)
   ******************************************************************/
  function ssGet(key) {
    try { return JSON.parse(sessionStorage.getItem(key)); }
    catch (e) { return null; }
  }
  function ssSet(key, val) {
    try { sessionStorage.setItem(key, JSON.stringify(val)); }
    catch (e) {}
  }
  function ssRemove(key) { sessionStorage.removeItem(key); }

  // Keys used per variation id
  function keyHold(vid) { return 'seat_hold_' + vid; }          // {vid, seat, title, expiry}
  function keyExpiry(vid) { return 'seat_expiry_' + vid; }      // {expiry:number}

  /******************************************************************
   * COUNTDOWN (works on PDP or Cart where .twwt_woo_cc_notice exists)
   ******************************************************************/
  function renderCountdown($ele, expiryTs, title, onExpire) {
    if (!$ele || !$ele.length) return;

    function tick() {
      var now = Date.now();
      var remain = (expiryTs * 1000) - now;
      if (remain > 0) {
        var minutes = Math.floor(remain / 60000);
        var seconds = Math.floor((remain % 60000) / 1000);
        $ele.html(
          'We will hold your webinar (' + (title || $ele.data('title') || 'Webinar') + ') seats for ' +
          '<ins>' + minutes + ' minutes ' + (seconds < 10 ? '0' : '') + seconds + ' seconds</ins> only.'
        );
      } else {
        clearInterval(timer);
        $ele.text('Your seat reservation expired.');
        if (typeof onExpire === 'function') onExpire();
        // Clear WooCommerce cart via AJAX, then reload
            jQuery.post(
                wc_add_to_cart_params.ajax_url, // WC AJAX endpoint
                { action: 'woocommerce_clear_cart' },
                function () {
                    setTimeout(function () { location.reload(); }, 1500); // reload after 1.5s
                }
            );
        }
    }

    tick();
    var timer = setInterval(tick, 1000);
    return timer;
  }

  /******************************************************************
   * SEAT UI RESET (original helper)
   ******************************************************************/
  function as_reset($ele) {
    var _ckdln = $ele.closest('ul.my-tickets').find('input:checkbox:checked').length;
    setTimeout(function () {
      $('ul.my-tickets.de-active .ticket-box').prop('checked', false);
    }, 10);
    var _atr = $ele.data("value");
    var vid = $ele.data("vid");

    // Set the variation attribute dropdown (try taxonomy, then custom, then any)
    var $select = $("#pa_seat");
    if (!$select.length) $select = $("#seat");
    if (!$select.length) $select = $("form.variations_form select[name^='attribute_']").first();
    $select.val(_atr).change();

    // Directly set variation_id so WooCommerce knows which variation to add
    $("input[name='variation_id'], input.variation_id").val(vid);

    $("input[name=quantity]").val(_ckdln).change();
  }

  /******************************************************************
   * PARTICIPANT REFRESH (original)
   ******************************************************************/
  function getParticipant(_id) {
    $.get('?participant=true&productid=' + _id, function (data) {
      $(".tab-participant").html(data);
      setTimeout(function () { getParticipant(_id); }, 60000);
    });
  }

  /******************************************************************
   * DOCUMENT READY
   ******************************************************************/
  $(function () {

    /********************
     * Cart Page Notice
     ********************/
    var $notices = $('.twwt_woo_cc_notice');
    if ($notices.length) {
      $notices.each(function () {
        var $this = $(this);
        var vid = $this.data('id');            // Variation ID expected
        var title = $this.data('title') || ''; // Optional in your markup
        var hold = ssGet(keyHold(vid));        // {vid, seat, title, expiry}
        var expiryObj = ssGet(keyExpiry(vid)); // {expiry}
        var expiryTs = 0;

        // Prefer explicit expiry store, fallback to hold.expiry if present
        if (expiryObj && typeof expiryObj.expiry === 'number') {
          expiryTs = expiryObj.expiry;
        } else if (hold && typeof hold.expiry === 'number') {
          expiryTs = hold.expiry;
        }

        if (expiryTs && expiryTs > (Date.now() / 1000)) {
          // Countdown until expiry; on expire, clear storage
          renderCountdown($this, expiryTs, (hold && hold.title) || title, function () {
            ssRemove(keyHold(vid));
            ssRemove(keyExpiry(vid));
          });
        } else {
          $this.text('Your seat hold has expired or is not set.');
        }
      });
    }

    /********************
     * Random Generator (original)
     ********************/
    function shuffleArray(arr) {
      for (let i = arr.length - 1; i > 0; i--) {
        const j = Math.floor(Math.random() * (i + 1));
        [arr[i], arr[j]] = [arr[j], arr[i]];
      }
      return arr;
    }

    $("#generate-random").on("click", function (e) {
      e.preventDefault();

      const qtyRaw = $("#random-generator").val();
      const _seat = parseInt(qtyRaw, 10);

      if (Number.isNaN(_seat) || _seat <= 0) {
        alert("Please enter a valid random quantity (number > 0).");
        return;
      }

      // Select only available seats
      const $available = $(".my-tickets input[type=checkbox]")
        .not(".rbtn-tt-perma")
        .not(".rbtn-tt-temp")
        .not(".wait");

      const totalAvailable = $available.length;
      if (totalAvailable === 0) {
        alert("No available tickets found.");
        return;
      }

      const pickCount = Math.min(_seat, totalAvailable);

      if (_seat > totalAvailable) {
        alert(
          `Requested ${_seat} seats but only ${totalAvailable} available. Selecting all available seats.`
        );
      }

      // 🔒 IMPORTANT: silently clear previous random selections
      $available.prop("checked", false);

      // Shuffle & pick
      const arr = shuffleArray($available.toArray());
      const picked = arr.slice(0, pickCount);

      // ✅ Trigger change only for selected seats
      picked.forEach(function (el) {
        $(el).prop("checked", true).trigger("change");
      });

      console.log(`Randomly selected ${pickCount}/${totalAvailable} seats.`);
    });

    /********************
     * Seat Selection (modified: uses sessionStorage)
     ********************/
    $(".ticket-box").change(function () {
      var $ele = $(this);
      var checkedCount = $ele.closest('ul.my-tickets').find('input:checkbox:checked').length;
      if (checkedCount > 0) {
        var vid = $ele.data("vid");
        var seat = $ele.val();

        console.log(`DEBUG: Seat clicked: ${seat} (Variation ID: ${vid}), Checked count = ${checkedCount}`);

        if (!vid) {
          console.warn('DEBUG: data-vid missing on .ticket-box element');
          return;
        }

        $ele.addClass('wait');
        $.get(`?availabilitycheck=true&variationid=${vid}&seat=${seat}`, function (cdata) {
          $ele.removeClass('wait');
          if (cdata == 1 || cdata === "1") { // Available
            $('ul.my-tickets').addClass('de-active');
            $ele.closest('ul.my-tickets').removeClass('de-active');
            $(".proceed-cart").show();
            $(".proceed-cart-info").hide();

            var seat_txt = (checkedCount === 1) ? 'seat' : 'seats';
            $(".prc-qty").html(`You have selected ${checkedCount} ${seat_txt}.`);

            // Store a hold in sessionStorage for 10 minutes
            var expiryTs = Math.floor(Date.now() / 1000) + (HOLD_MS / 1000);
            var title = $('.product_title, h1.product_title, .entry-title').first().text().trim() || 'Webinar';

            ssSet(keyHold(vid), { vid: vid, seat: seat, title: title, expiry: expiryTs });
            ssSet(keyExpiry(vid), { expiry: expiryTs }); // also stored in a separate key for simpler read

            console.log(`DEBUG: Hold stored: vid=${vid}, seat=${seat}, expiry=${expiryTs}`);

            // If a notice exists on this page, show live countdown
            var $notice = $('.twwt_woo_cc_notice[data-id="' + vid + '"]');
            if ($notice.length) {
              renderCountdown($notice, expiryTs, title, function () {
                ssRemove(keyHold(vid));
                ssRemove(keyExpiry(vid));
              });
            }

            // Also auto-clear after 5 minutes (for PDP)
            setTimeout(function () {
              // Clear only if the same hold still exists (no new seat was selected)
              var current = ssGet(keyHold(vid));
              if (current && current.expiry === expiryTs) {
                ssRemove(keyHold(vid));
                ssRemove(keyExpiry(vid));
                // Reset UI on the PDP
                $('ul.my-tickets').removeClass('de-active');
                $(".proceed-cart").hide();
                $(".proceed-cart-info").show();
                // uncheck this seat visually
                $ele.prop('checked', false);
                $(".twwt_woo_cc_notice[data-id='" + vid + "']").text('Your seat reservation expired.');
                   jQuery.post(
                        wc_add_to_cart_params.ajax_url,
                        { action: 'woocommerce_clear_cart' },
                        function () {
                            setTimeout(function () {
                                location.reload();
                            }, 1500);
                        }
                    );

              }
            }, HOLD_MS);

            as_reset($ele);

          } else {
            $ele.prop('checked', false).prop('disabled', true).addClass('rbtn-tt-temp');
            $(".availabilitycheck-msg").html(cdata).show();
            setTimeout(function () { $(".availabilitycheck-msg").html('').hide(); }, 3000);
            as_reset($ele);
          }

          // Trigger immediate poll so other users' changes show up right away
          if (typeof window.twwtPollSeats === 'function') {
            setTimeout(window.twwtPollSeats, 500);
          }
        });

      } else {
        // None checked
        $('ul.my-tickets').removeClass('de-active');
        $(".proceed-cart").hide();
        $(".proceed-cart-info").show();
        as_reset($ele);
      }
    });

    /********************
     * Proceed Cart (original behavior + SS validation)
     ********************/
    $(".prc-btn").click(function (e) {
      e.preventDefault();
      console.log('DEBUG: Proceed button clicked');

      // Verify we have a hold for the current product (use first ticket-box's vid as context)
      var $any = $('.ticket-box').first();
      var vid = $any.length ? $any.data('vid') : null;
      if (vid) {
        var hold = ssGet(keyHold(vid));
        if (!hold || hold.expiry <= (Date.now() / 1000)) {
          alert('Your seat hold has expired. Please reselect a seat.');
          return;
        }
      }

      var $form = $('form.cart');
      if ($form.length) {
        // Get variation info from the checked ticket boxes
        var $checked = $('input.ticket-box:checked').first();
        var checkedVid = $checked.length ? $checked.data('vid') : null;
        var checkedAttr = $checked.length ? $checked.data('value') : null;

        // Ensure variation_id hidden input exists and is set
        // (WC may not render this input if product loaded as simple)
        if (checkedVid) {
          var $vidInput = $form.find('input[name="variation_id"]');
          if (!$vidInput.length) {
            $form.append('<input type="hidden" name="variation_id" value="' + checkedVid + '">');
          } else {
            $vidInput.val(checkedVid);
          }
        }

        // Ensure attribute input exists (try common attribute names)
        if (checkedAttr) {
          var hasAttr = $form.find('select[name^="attribute_"], input[name^="attribute_"]').length;
          if (!hasAttr) {
            // Try pa_seat first, then seat
            var attrName = 'attribute_pa_seat';
            if ($('#seat').length || !$('#pa_seat').length) {
              attrName = 'attribute_seat';
            }
            $form.append('<input type="hidden" name="' + attrName + '" value="' + checkedAttr + '">');
          }
        }

        // Ensure add-to-cart hidden input exists (needed for native form submit)
        var productId = $form.data('product_id')
          || $form.find('input[name="product_id"]').val()
          || $(".single_add_to_cart_button").val();
        if (productId && !$form.find('input[type="hidden"][name="add-to-cart"]').length) {
          $form.append('<input type="hidden" name="add-to-cart" value="' + productId + '">');
        }
        // Ensure product_id hidden input exists
        if (productId && !$form.find('input[name="product_id"]').length) {
          $form.append('<input type="hidden" name="product_id" value="' + productId + '">');
        }

        // Native submit bypasses WooCommerce's add-to-cart-variation.js validation
        $form[0].submit();
        console.log('DEBUG: Native form.cart submit, vid=' + checkedVid);
      } else {
        console.warn('DEBUG: No form.cart found.');
      }
    });

    /********************
     * "sss" Button - AJAX OTP (original)
     ********************/
    $(".sss").click(function (e) {
      $.ajax({
        dataType: "json",
        method: "POST",
        url: (typeof twwtfa !== 'undefined' ? twwtfa.ajaxurl : '/wp-admin/admin-ajax.php'),
        data: {
          dataType: "json",
          'action': 'twwt_woo_ajax_function',
          'type': 'otp-send',
          'nonce': (typeof twwtfa !== 'undefined' ? twwtfa.nonce : '')
        },
        success: function (data) { },
        error: function (err) {
          console.log(err);
        },
        cache: false,
      });
    });

    /********************
     * Participant auto refresh (original)
     ********************/
    if ($(".tab-participant").length > 0) {
      var _id = $(".tab-participant").data("id");
      getParticipant(_id);
    }

    /********************
     * Winner Selection (original)
     ********************/
    $("input[name=rbtnseats]").click(function (e) {
      var _id = $(this).data('id');
      $("#tw_orderid").val(_id);
    });

    $("#tw_mywinnerform").submit(function (e) {
      e.preventDefault();
      $("#btn_select_winnerf").attr('disabled', 'disabled');
      $("#btn_select_winnerf span").html('Please wait...');

      var _pid = $("#tw_pid").val();
      var _oid = $("#tw_orderid").val();
      var _seat = $("input[name=rbtnseats]:checked").val();
      $("#wsnm").html($("#wsnm_" + _seat).html());
      $("#wsnmst").html(_seat);
      var _nonce = $("#tw_winner_nonce").val();
      $.get('?myaction=selectwinner&pid=' + _pid + '&oid=' + _oid + '&seat=' + _seat + '&_wpnonce=' + _nonce)
      .done(function (data) {
        $("#btn_select_winnerf").remove();
        $("input[name=rbtnseats]").attr('disabled', 'disabled');
        $('#sbWinner').show();
      })
      .fail(function () {
        alert('Failed to select winner. Please reload the page and try again.');
        $("#btn_select_winnerf").removeAttr('disabled');
        $("#btn_select_winnerf span").html('Select a Winner');
      });
    });

    $(".btn-close").click(function (e) {
      e.preventDefault();
      $('#sbWinner').hide();
      location.reload();
    });

    /********************
     * Live Seat Polling – update availability every 5s without full page reload
     ********************/
    var $dashboard = $('.ticket-dashboard[data-product-id]');
    if ($dashboard.length && typeof twwtfa !== 'undefined' && twwtfa.ajaxurl) {
      var pollProductId = $dashboard.data('product-id');
      var POLL_INTERVAL = 5000; // 5 seconds
      var pollInFlight = false;

      function twwtPollSeats() {
        if (pollInFlight) return; // skip if previous request still pending
        pollInFlight = true;
        $.getJSON(twwtfa.ajaxurl, { action: 'twwt_seat_status', product_id: pollProductId })
          .always(function () { pollInFlight = false; })
          .done(function (resp) {
            if (!resp || !resp.success || !resp.data) return;

            var data = resp.data; // { vid: { seats: {num: type}, total: N, available: N } }

            $.each(data, function (vid, info) {
              var seats = info.seats;

              // Update each seat checkbox
              $.each(seats, function (seatNum, type) {
                var $cb = $('input.ticket-box[data-vid="' + vid + '"][value="' + seatNum + '"]');
                if (!$cb.length) return;

                // Never touch seats the current user has checked
                if ($cb.is(':checked')) return;

                if (type === 'perma') {
                  $cb.prop('disabled', true)
                     .removeClass('rbtn-tt-temp')
                     .addClass('rbtn-tt-perma');
                } else if (type === 'temp') {
                  $cb.prop('disabled', true)
                     .removeClass('rbtn-tt-perma')
                     .addClass('rbtn-tt-temp');
                } else {
                  // Seat is now available – re-enable unless it's in the de-active group
                  var inDeactive = $cb.closest('ul.my-tickets').hasClass('de-active');
                  if (!inDeactive) {
                    $cb.prop('disabled', false)
                       .removeClass('rbtn-tt-perma rbtn-tt-temp');
                  }
                }
              });

              // Update "Available Seats: X/Y" text
              var $seatList = $('ul.ticket-id-' + vid);
              if ($seatList.length) {
                var $availDiv = $seatList.closest('.rseat-container').find('.seat-available');
                if ($availDiv.length) {
                  $availDiv.html('<strong>Available Seats:</strong> ' + info.available + '/' + info.total);
                }
              }
            });
          });
      }

      // Expose globally so seat-selection handler can trigger an immediate poll
      window.twwtPollSeats = twwtPollSeats;

      // First poll shortly after page load, then every POLL_INTERVAL
      setTimeout(twwtPollSeats, 2000);
      setInterval(twwtPollSeats, POLL_INTERVAL);

      // Immediate poll when page is restored from bfcache (back button)
      $(window).on('pageshow', function (e) {
        if (e.originalEvent && e.originalEvent.persisted) {
          twwtPollSeats();
        }
      });

      // Immediate poll when tab becomes visible again
      $(document).on('visibilitychange', function () {
        if (!document.hidden) {
          twwtPollSeats();
        }
      });
    }

  }); // ready

/*************************************************************************
 * FIX: Re-initialize countdown after WooCommerce AJAX refresh (coupon etc.)
 *************************************************************************/
function twwt_reinit_timer() {
    var $notices = $('.twwt_woo_cc_notice');
    if ($notices.length) {
      $notices.each(function () {
        var $this = $(this);
        var vid = $this.data('id');
        var title = $this.data('title') || '';
        var hold = ssGet(keyHold(vid));
        var expiryObj = ssGet(keyExpiry(vid));
        var expiryTs = 0;

        if (expiryObj && typeof expiryObj.expiry === 'number') {
          expiryTs = expiryObj.expiry;
        } else if (hold && typeof hold.expiry === 'number') {
          expiryTs = hold.expiry;
        }

        if (expiryTs && expiryTs > (Date.now() / 1000)) {
          renderCountdown($this, expiryTs, (hold && hold.title) || title, function () {
            ssRemove(keyHold(vid));
            ssRemove(keyExpiry(vid));
          });
        }
      });
    }
}

// WooCommerce fragment updated → restart countdown
jQuery(document.body).on(
  'updated_cart_totals updated_checkout wc_fragments_loaded wc_fragments_refreshed',
  function () {
      setTimeout(twwt_reinit_timer, 150);
  }
);

// also catch coupon AJAX specifically
jQuery(document).ajaxComplete(function (event, xhr, settings) {
  if (settings.url && settings.url.indexOf('apply_coupon') !== -1) {
      setTimeout(twwt_reinit_timer, 150);
  }
});  

})(jQuery);
