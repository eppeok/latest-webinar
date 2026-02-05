(function ($) {
  "use strict";

  /******************************************************************
   * CONFIG
   ******************************************************************/
  var HOLD_MINUTES = 10;            // Seat hold duration
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
    $("#pa_seat").val(_atr).change();
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

      var $btn = $(".single_add_to_cart_button");
      if ($btn.length) {
        $btn.trigger('click');  // should be non-AJAX for webinars per your PHP filter
        console.log('DEBUG: Triggered .single_add_to_cart_button click');
      } else if ($('form.cart').length) {
        $('form.cart').trigger('submit');
        console.log('DEBUG: Submitted form.cart');
      } else {
        console.warn('DEBUG: No add-to-cart button or form found.');
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
      $.get('?myaction=selectwinner&pid=' + _pid + '&oid=' + _oid + '&seat=' + _seat, function (data) {
        $("#btn_select_winnerf").remove();
        $("input[name=rbtnseats]").attr('disabled', 'disabled');
        $('#sbWinner').show();
      });
    });

    $(".btn-close").click(function (e) {
      e.preventDefault();
      $('#sbWinner').hide();
      location.reload();
    });

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
