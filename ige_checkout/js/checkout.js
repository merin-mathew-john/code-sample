(function ($, Drupal) {
  'use strict';

  $(window).on('load', function () {
    if ($('#edit-trademark-request').length > 0) {
      $('.wizard-selected-class').hide();
      $('.selected-class-items').hide();
      trademarkSummary();
      $(".trademark-class-select.form-checkbox:checked").each(function () {
        var val = this.value;
        $('.wizard-selected-class').show();
        $('.selected-class-' + val).show();
      });

    }
  });

  // Hide the selected class details on page load.
  $(document).ready(function(event) {
    $('.class-popup-more-text').hide();
    $('.wizard-class-popup-search-keyword').keypress(function (e) {
      var key = e.which;
      if (key == 13) {
        $('.wizard-class-popup-search.button').trigger('click');
      }
    });

     $('#edit-class-select-later').on('click', function (e) {
      e.preventDefault();
      $(".remove-class:visible").each(function () {
        $(this).trigger("click");
      });
      $(this).addClass('active');
      $(this).removeClass('white-bg');
      $("#edit-class-select").removeClass('active');
      $("#edit-class-select").addClass('white-bg');
    });
    $('.checkout-pending-services input.form-checkbox').on('click', function (e) {
      if (this.checked) {
        var add_trademark_url = "/trademark/add/" + $('.checkout-pending-services').data("orderid") + '/' + this.value;
        if (lang != "en") {
          add_trademark_url = "/" + lang + "/trademark/add/" + $('.checkout-pending-services').data("orderid") + '/' + this.value;
        }
        $.ajax({
          // Define the type of HTTP verb we want to use (POST for our form).
          type: 'GET',
          // The url where we want to POST.
          url: add_trademark_url,
        })
          .done(function (data) {
            location.reload(true);
          });
      }
    });

    $('.checkout-current-services.form-checkbox').one('click', function (e) {
      if (!(this.checked)) {
        var remove_trademark_url = "/trademark/remove/" + $('.checkout-current-services').data("orderid") + '/' + this.value;
        if (lang != "en") {
          remove_trademark_url = "/" + lang + "/trademark/remove/" + $('.checkout-current-services').data("orderid") + '/' + this.value;
        }
        $.ajax({
          // Define the type of HTTP verb we want to use (POST for our form).
          type: 'GET',
          // The url where we want to POST.
          url: remove_trademark_url,
        })
          .done(function (data) {
            location.reload(true);
          });
      }
    });

    $(document).on('click', '.readmore-class', function (e) {
      e.preventDefault();
      var tid = $(this).data('tid');
      var dots = document.getElementById("dots-" + tid);
      var moreText = document.getElementById("more-" + tid);
      var btnText = document.getElementById("class-popup-more-button-" + tid);

      if (dots.style.display === "none") {
        dots.style.display = "inline";
        btnText.innerHTML = Drupal.t("Read more");
        moreText.style.display = "none";
      } else {
        dots.style.display = "none";
        btnText.innerHTML = Drupal.t("Read less");
        moreText.style.display = "inline";
      }
    });

    $(document).on('click', '.remove-class', function (e) {
      e.preventDefault();
      var key = $(this).data("id");
      var element = '#edit-class-selection-popup-class-' + key;
      var element2 = '#edit-class-' + key;
      $(element).trigger('click');
      $(element2).trigger('click');
      $('.selected-class-' + key).removeClass('visible');
      $('.form-text.' + key).val('');
    });

    $(document).on('change', '.wizard-class-selection-popup .form-checkbox', function () {
      var id = this.id;
      var val = this.value;
      if (this.checked) {
        var target_id = val;
        var target_class = 'dynamic-class-desc dynamic-' + val;
        if ($('#' + target_id).length === 0) {
          $('#' + id).next().append(
            $('<input>', {
              type: 'text',
              id: val,
              class: target_class,
              placeholder: 'Enter the list of products or services for this class, or leave blank if you wish to provide the list at a later time',
            })
          );
          $('.wizard-selected-class').show();
          $('.selected-class-' + val).show();
        }
      }
      else {
        $('.selected-class-' + val).hide();
        $('#' + this.value).remove();
        $('.selected-class-' + val).val("");
      }
    });

    var lang = $('#page').data('lang');
    $('.wizard-selected-class').hide();
    $('.selected-class-items').hide();
    $('.existing-owner-checkout').hide();
    $('.same-as-owner').hide();
    $('#checkout-new-billing').hide();
    $('.billing-same-as-owner').hide()

    // For showing summary in the bottom of checkout form
    $('.trademark-type').on('change', function (e) {
      trademarkSummary();
    });
    $('.trademark-countries').on('change', function (e) {
      trademarkSummary();
    });
    $(document).on('change', '.trademark-class-select', function (e) {
      $('.selected-class-' + this.value).removeClass('visible');
      $('.form-text.' + this.value).val('');
      trademarkSummary();
    });
    $(document).on('change', '.trademark-form .form-managed-file', function (e) {
      trademarkSummary();
    });

    $(document).on('click', '.wizard-class-popup-cancel', function (e) {
      e.preventDefault();
      $('.add-class-close .fa-times').trigger('click');
    });

    var base_url = $('#base_url').val();
    $('.wizard-class-popup-search').on('click', function (e) {
      e.preventDefault();
      var search_class_url = base_url + "/ajax/class/search";
      if (lang != "en") {
        search_class_url = base_url + "/" + lang + "/ajax/class/search";
      }
      var keyword = $('.wizard-class-popup-search-keyword').val();
      $.ajax({
        // Define the type of HTTP verb we want to use (POST for our form).
        type: 'GET',
        data: {
          "keyword": keyword,
        },
        // The url where we want to POST.
        url: search_class_url,
      })
        .done(function (data) {
          $('.wizard-class-selection-popup .form-checkboxes').html(data.data);
          $(".selected-class-items .form-text:visible").each(function () {
            var tid = $(this).data("class");
            var el = '#edit-class-' + tid;
            $(el).trigger("click");
          });
        });
    });

    $('.wizard-class-popup-search-clear').on('click', function (e) {
      e.preventDefault();
      var search_class_url = "/ajax/class/search";
      if (lang != "en") {
        search_class_url = "/" + lang + "/ajax/class/search";
      }
      var keyword = '';
      $('.wizard-class-popup-search-keyword').val('');
      $.ajax({
        // Define the type of HTTP verb we want to use (POST for our form).
        type: 'GET',
        data: {
          "keyword": keyword,
        },
        // The url where we want to POST.
        url: search_class_url,
      })
        .done(function (data) {
          $('.wizard-class-selection-popup .form-checkboxes').html(data.data);
          $(".selected-class-items .form-text:visible").each(function () {
            var tid = $(this).data("class");
            var el = '#edit-class-' + tid;
            $(el).trigger("click");
          });
        });
    });

  });

  Drupal.behaviors.ige_checkout = {
    attach: function(context, settings) {
      var lang = $('#page').data('lang');

      $('.wizard-class-popup-choose').on('click', function (e) {
        e.preventDefault();
        $('.add-class-close .fa-times').trigger('click');
      });

      $(document).on('keyup', '.dynamic-class-desc', function() {
        var id = this.id
        $('.' + id).val(this.value);
      });


      // Checkout billing info
      $('#billing-same-as-owner').on('click', function(e) {
        $('#edit-wrapper-options-owner').trigger("click");
        if ($('#billing-same-as-owner').data("profile") != null) {
          var billing_trademark_url = "/order/" + $('#billing-same-as-owner').data("order") + '/billing/' + $('#billing-same-as-owner').data("profile");
          if (lang != "en") {
            billing_trademark_url = "/" + lang + "/order/" + $('#billing-same-as-owner').data("order") + '/billing/' + $('#billing-same-as-owner').data("profile");
          }
          e.preventDefault();
          $('.billing-same-as-owner').show();
          $('.existing-owner-checkout').hide();
          $('#checkout-new-billing').hide();
          $('.billing-info a').removeClass('active');
          $('#billing-same-as-owner').addClass('active');

          $.ajax({
            // Define the type of HTTP verb we want to use (POST for our form).
            type: 'GET',
            // The url where we want to POST.
            url: billing_trademark_url,
          })
          .done(function (data) {
          });
        }

      });

      $('.existing-owner-checkout .form-radio').on('change', function (e) {
        e.preventDefault();
        if (this.value != null) {
          var billing_same_owner_url = "/order/" + $('#billing-same-as-owner').data("order") + '/billing/' + this.value;
          if (lang != "en") {
            billing_same_owner_url = "/" + lang + "/order/" + $('#billing-same-as-owner').data("order") + '/billing/' + this.value;
          }
          $.ajax({
            // Define the type of HTTP verb we want to use (POST for our form).
            type: 'GET',
            // The url where we want to POST.
            url: billing_same_owner_url,
          })
          .done(function (data) {
          });
        }
      });


      $('#billing-select-prof').on('click', function(e) {
        $("#edit-wrapper-options-existing-profile").trigger("click");
        e.preventDefault();
        $('.billing-same-as-owner').hide();
        $('.existing-owner-checkout').show();
        $('#checkout-new-billing').hide();

        $('.billing-info a').removeClass('active');
        $('#billing-select-prof').addClass('active');
      });

      $('#billing-new-prof').on('click', function(e) {
        $("#edit-wrapper-options-new-profile").trigger("click");
        e.preventDefault();
        $('.billing-same-as-owner').hide();
        $('.existing-owner-checkout').hide();
        $('#checkout-new-billing').show();

        $('.billing-info a').removeClass('active');
        $('#billing-new-prof').addClass('active');
      });
    }
  };

  function trademarkSummary() {
    if ($('.trademark-countries').val() != '') {

      var search = false;
      var application = false;
      var lang = $('#page').data('lang');
      var url = "/ajax/trademark/summary";
      if (lang != "en") {
        url = "/" + lang + "/ajax/trademark/summary";
      }
      $('.trademark-type').each(function () {
        if (this.checked) {
          if (this.value == 'search') {
            search = true;
          }
          if (this.value == 'application') {
            application = true;
          }
        }
      });
      var classes = $('.wizard-class-selection-popup input:checkbox:checked').map(function () {
        return $(this).val();
      }).get();

      if ($(".selected-class-items .form-text:visible").length != 0) {
        var classes = $(".selected-class-items .form-text:visible").map(function () {
          return $(this).data("class");
        }).get();
      }

      $('#selected_class_data').val(classes);
      var image = null;
      if ($('.trademark-form .form-managed-file img').length > 0) {
        image = true;
      }

      // Store the form data to pass to the ajax url.
      var formData = {
        'search': search,
        'application': application,
        'countries': $('.trademark-countries').val(),
        'class': classes,
        'image': image,
      };

      // Process the form.
      $.ajax({
        // Define the type of HTTP verb we want to use (POST for our form).
        type: 'GET',
        // The url where we want to POST.
        url: url,
        dataType: 'json',
        // Our data object.
        data: formData,
        encode: true
      })
      .done(function (data) {
        $(".trademark-wizard-summary").html(data.data);
      });
    }

  }

})(jQuery, Drupal);


