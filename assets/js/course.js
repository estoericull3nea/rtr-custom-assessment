/* global CA_Course_Config, jQuery */
(function ($) {
  "use strict";

  if (typeof CA_Course_Config === "undefined") {
    return;
  }

  var cfg    = CA_Course_Config;
  var labels = cfg.labels || {};

  var $modal        = $("#ca-course-modal");
  var $overlay      = $("#ca-course-modal-overlay");
  var $closeBtn     = $("#ca-course-close-modal");
  var $screenInfo   = $("#ca-course-screen-info");
  var $screenLoad   = $("#ca-course-screen-loading");
  var $form         = $("#ca-course-info-form");
  var $submitBtn    = $("#ca-course-submit-btn");
  var $errorBox     = $("#ca-course-info-error");
  var $loadingText  = $("#ca-course-loading-text");

  // ── Open / close ──────────────────────────────────────────────────────────

  function openModal() {
    $modal.attr("aria-hidden", "false");
    $("body").addClass("ca-modal-open");
    setTimeout(function () {
      $modal.addClass("ca-modal--open");
    }, 10);
    showScreen("info");
    $errorBox.text("").hide();
  }

  function closeModal() {
    $modal.removeClass("ca-modal--open");
    $modal.attr("aria-hidden", "true");
    $("body").removeClass("ca-modal-open");
  }

  // ── Screen switching ──────────────────────────────────────────────────────

  function showScreen(name) {
    $screenInfo.hide().removeClass("ca-screen-active");
    $screenLoad.hide().removeClass("ca-screen-active");

    if (name === "loading") {
      $screenLoad.show().addClass("ca-screen-active");
    } else {
      $screenInfo.show().addClass("ca-screen-active");
    }
  }

  function setLoading(text) {
    $loadingText.text(text || labels.loading || "Loading…");
    showScreen("loading");
  }

  // ── Validation ────────────────────────────────────────────────────────────

  function getFormData() {
    return {
      first_name: $.trim($("#ca-course-first-name").val()),
      last_name:  $.trim($("#ca-course-last-name").val()),
      email:      $.trim($("#ca-course-email").val()),
    };
  }

  function validateForm(data) {
    if (!data.first_name) return "Please enter your first name.";
    if (!data.last_name)  return "Please enter your last name.";
    if (!data.email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(data.email)) {
      return "Please enter a valid email address.";
    }
    return null;
  }

  // ── AJAX ──────────────────────────────────────────────────────────────────

  function doCheckout(data) {
    setLoading(labels.redirecting || "Redirecting to checkout…");

    $.ajax({
      url:    cfg.ajax_url,
      method: "POST",
      data: {
        action:     "ca_course_checkout",
        nonce:      cfg.nonce,
        first_name: data.first_name,
        last_name:  data.last_name,
        email:      data.email,
      },
      success: function (res) {
        if (!res.success) {
          showError(res.data && res.data.message ? res.data.message : labels.error_generic);
          return;
        }

        if (res.data.already_paid) {
          setLoading(labels.accessing || "Opening your course…");
          window.location.href = res.data.course_url;
        } else {
          window.location.href = res.data.checkout_url;
        }
      },
      error: function () {
        showError(labels.error_generic || "Something went wrong. Please try again.");
      },
    });
  }

  function showError(msg) {
    showScreen("info");
    $errorBox.text(msg).show();
    setSubmitting(false);
  }

  // ── Button state ──────────────────────────────────────────────────────────

  function setSubmitting(on) {
    $submitBtn.prop("disabled", on).toggleClass("ca-btn--loading", on);
  }

  // ── Event bindings ────────────────────────────────────────────────────────

  $(document).on("click", ".ca-course-trigger", openModal);

  $closeBtn.on("click", closeModal);
  $overlay.on("click", closeModal);

  $(document).on("keydown", function (e) {
    if (e.key === "Escape" && $modal.hasClass("ca-modal--open")) {
      closeModal();
    }
  });

  $form.on("submit", function (e) {
    e.preventDefault();
    $errorBox.text("").hide();

    var data  = getFormData();
    var error = validateForm(data);
    if (error) {
      $errorBox.text(error).show();
      return;
    }

    setSubmitting(true);
    doCheckout(data);
  });

})(jQuery);
