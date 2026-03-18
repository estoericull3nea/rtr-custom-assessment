/**
 * Custom Assessment — Frontend JavaScript
 * Full AJAX-powered multi-step modal assessment.
 * Depends on: jQuery, CA_Config (wp_localize_script)
 */
(function ($) {
  "use strict";

  /* -------------------------------------------------------
	   State
	------------------------------------------------------- */
  var state = {
    submissionId: null,
    currentIndex: 0,
    totalQuestions: CA_Config.total_questions || 30,
    answersCache: {}, // { questionIndex: answerValue }
    isSubmitting: false,
  };

  /* -------------------------------------------------------
	   DOM refs (populated on doc ready)
	------------------------------------------------------- */
  var $modal,
    $overlay,
    $panel,
    $body,
    $progressContainer,
    $progressBar,
    $progressLabel,
    $screens,
    $infoForm,
    $infoError,
    $startBtn,
    $categoryLabel,
    $questionCounter,
    $questionText,
    $answerOptions,
    $questionError,
    $backBtn,
    $nextBtn,
    $resultsContent;

  /* -------------------------------------------------------
	   Init
	------------------------------------------------------- */
  function init() {
    $modal = $("#ca-modal");
    $overlay = $("#ca-modal-overlay");
    $panel = $modal.find(".ca-modal-panel");
    $body = $("body");
    $progressContainer = $("#ca-progress-container");
    $progressBar = $("#ca-progress-bar");
    $progressLabel = $("#ca-progress-label");
    $screens = $modal.find(".ca-screen");
    $infoForm = $("#ca-info-form");
    $infoError = $("#ca-info-error");
    $startBtn = $("#ca-start-btn");
    $categoryLabel = $("#ca-category-label");
    $questionCounter = $("#ca-question-counter");
    $questionText = $("#ca-question-text");
    $answerOptions = $modal.find(".ca-answer-option");
    $questionError = $("#ca-question-error");
    $backBtn = $("#ca-back-btn");
    $nextBtn = $("#ca-next-btn");
    $resultsContent = $("#ca-results-content");

    bindEvents();
  }

  /* -------------------------------------------------------
	   Events
	------------------------------------------------------- */
  function bindEvents() {
    // Open modal
    $("#ca-open-modal").on("click", openModal);

    // Close modal
    $("#ca-close-modal").on("click", closeModal);
    $overlay.on("click", closeModal);

    // Keyboard close
    $(document).on("keydown", function (e) {
      if (e.key === "Escape" && $modal.hasClass("ca-modal--open")) {
        closeModal();
      }
    });

    // Info form submit
    $infoForm.on("submit", handleInfoSubmit);

    // Answer option selection
    $modal.on("click", ".ca-answer-option", function () {
      $answerOptions.removeClass("ca-selected");
      $(this).addClass("ca-selected");
      $(this).find(".ca-answer-radio").prop("checked", true);
      hideError($questionError);
    });

    // Navigation
    $nextBtn.on("click", handleNext);
    $backBtn.on("click", handleBack);
  }

  /* -------------------------------------------------------
	   Modal open / close
	------------------------------------------------------- */
  function openModal() {
    $modal.attr("aria-hidden", "false");
    $body.addClass("ca-modal-open");

    // Small delay to allow display: flex to apply before transition
    requestAnimationFrame(function () {
      $modal.addClass("ca-modal--open");
    });

    var stored = getSavedSession();
    if (stored && stored.submissionId) {
      $.post(CA_Config.ajax_url, {
        action: "ca_get_progress",
        nonce: CA_Config.nonce,
        submission_id: stored.submissionId,
      })
        .done(function (response) {
          if (
            response.success &&
            response.data.status === "in_progress" &&
            response.data.email === stored.email
          ) {
            var resume = window.confirm(
              "You have an in-progress assessment for " +
                stored.email +
                ". Click OK to continue or Cancel to start a new assessment.",
            );
            if (resume) {
              resumeAssessment(
                stored.submissionId,
                response.data.answered,
                response.data.total,
              );
              return;
            }
            clearSavedSession();
          }
          resetState();
          showScreen("info");
          hideProgress();
        })
        .fail(function () {
          resetState();
          showScreen("info");
          hideProgress();
        });
    } else {
      resetState();
      showScreen("info");
      hideProgress();
    }
  }

  function closeModal() {
    $modal.removeClass("ca-modal--open");
    $modal.attr("aria-hidden", "true");
    $body.removeClass("ca-modal-open");
  }

  function getSavedSession() {
    try {
      return JSON.parse(
        localStorage.getItem("ca_assessment_session") || "null",
      );
    } catch (e) {
      return null;
    }
  }

  function saveSession(email, submissionId) {
    localStorage.setItem(
      "ca_assessment_session",
      JSON.stringify({ email: email, submissionId: submissionId }),
    );
  }

  function clearSavedSession() {
    localStorage.removeItem("ca_assessment_session");
  }

  function resetState() {
    state.submissionId = null;
    state.currentIndex = 0;
    state.answersCache = {};
    state.isSubmitting = false;
    $infoForm[0].reset();
    hideError($infoError);
    setProgress(0);
  }

  /* -------------------------------------------------------
	   Screen management
	------------------------------------------------------- */
  function showScreen(name) {
    $screens.removeClass("ca-screen-active");

    var $target;
    switch (name) {
      case "info":
        $target = $("#ca-screen-info");
        break;
      case "questions":
        $target = $("#ca-screen-questions");
        break;
      case "results":
        $target = $("#ca-screen-results");
        break;
      case "loading":
        $target = $("#ca-screen-loading");
        break;
    }

    if ($target) {
      $target.addClass("ca-screen-active");
      // Scroll body to top
      $modal.find(".ca-modal-body").scrollTop(0);
    }
  }

  /* -------------------------------------------------------
	   Progress bar
	------------------------------------------------------- */
  function showProgress() {
    $progressContainer.addClass("ca-visible");
    $progressContainer.attr("aria-hidden", "false");
  }

  function hideProgress() {
    $progressContainer.removeClass("ca-visible");
    $progressContainer.attr("aria-hidden", "true");
  }

  function setProgress(pct) {
    pct = Math.min(100, Math.max(0, Math.round(pct)));
    $progressBar.css("width", pct + "%");
    $progressBar.attr("aria-valuenow", pct);
    $progressLabel.text(pct + "% Complete");
  }

  function resumeAssessment(submissionId, answered, total) {
    state.submissionId = submissionId;
    state.currentIndex = answered;
    saveSession(
      $("#ca-email").val().trim() || getSavedSession().email || "",
      submissionId,
    );
    setProgress(total > 0 ? Math.round((answered / total) * 100) : 0);
    showProgress();
    loadQuestion(answered);
  }

  function findInProgressByEmail(email, next) {
    $.post(CA_Config.ajax_url, {
      action: "ca_find_in_progress_by_email",
      nonce: CA_Config.nonce,
      email: email,
    })
      .done(function (response) {
        if (typeof next === "function") {
          next(response);
        }
      })
      .fail(function () {
        if (typeof next === "function") {
          next(null);
        }
      });
  }

  function saveUserInfo() {
    var data = {
      action: "ca_save_user_info",
      nonce: CA_Config.nonce,
      first_name: $("#ca-first-name").val().trim(),
      last_name: $("#ca-last-name").val().trim(),
      email: $("#ca-email").val().trim(),
      phone: $("#ca-phone").val().trim(),
      job_title: $("#ca-job-title").val().trim(),
    };

    $.post(CA_Config.ajax_url, data)
      .done(function (response) {
        if (response.success) {
          state.submissionId = response.data.submission_id;
          state.currentIndex = 0;
          saveSession(data.email, state.submissionId);
          showScreen("questions");
          showProgress();
          loadQuestion(0);
        } else {
          showError(
            $infoError,
            response.data.message || CA_Config.labels.error_generic,
          );
        }
      })
      .fail(function () {
        showError($infoError, CA_Config.labels.error_generic);
      })
      .always(function () {
        setBtnLoading($startBtn, false);
        state.isSubmitting = false;
      });
  }

  /* -------------------------------------------------------
	   Step 1: Info form
	------------------------------------------------------- */
  function handleInfoSubmit(e) {
    e.preventDefault();

    if (state.isSubmitting) return;

    var email = $("#ca-email").val().trim();
    if (!email) {
      showError($infoError, "Email is required.");
      return;
    }

    state.isSubmitting = true;
    hideError($infoError);
    setBtnLoading($startBtn, true);

    findInProgressByEmail(email, function (response) {
      if (
        response &&
        response.success &&
        response.data &&
        response.data.found &&
        response.data.status === "in_progress"
      ) {
        var confirmResume = window.confirm(
          "You already have a saved, in-progress assessment for this email. Click OK to continue where you left off, or Cancel to start a new assessment.",
        );
        if (confirmResume) {
          resumeAssessment(
            response.data.submission_id,
            response.data.answered,
            response.data.total,
          );
          state.isSubmitting = false;
          setBtnLoading($startBtn, false);
          return;
        }
      }

      // Either no in-progress entry or user chose to start new
      saveUserInfo();
    });
  }

  /* -------------------------------------------------------
	   Question loading
	------------------------------------------------------- */
  function loadQuestion(index) {
    showScreen("loading");

    var data = {
      action: "ca_get_question",
      nonce: CA_Config.nonce,
      question_index: index,
      submission_id: state.submissionId,
    };

    $.post(CA_Config.ajax_url, data)
      .done(function (response) {
        if (response.success) {
          renderQuestion(response.data, index);
        } else {
          alert(response.data.message || CA_Config.labels.error_generic);
        }
      })
      .fail(function () {
        alert(CA_Config.labels.error_generic);
      });
  }

  function renderQuestion(data, index) {
    var q = data.question;
    var total = data.total;
    var isLast = data.is_last;
    var saved = data.saved_answer;

    // Update text
    $categoryLabel.text(q.category);
    $questionCounter.text("Question " + (index + 1) + " of " + total);
    $questionText.text(q.text);

    // Clear and restore answers
    $answerOptions.removeClass("ca-selected");
    $modal.find(".ca-answer-radio").prop("checked", false);

    var selectedVal = state.answersCache[index] || saved;
    if (selectedVal) {
      var $opt = $modal.find(
        '.ca-answer-option[data-value="' + selectedVal + '"]',
      );
      $opt.addClass("ca-selected");
      $opt.find(".ca-answer-radio").prop("checked", true);
    }

    // Progress
    var pct = total > 0 ? Math.round((index / total) * 100) : 0;
    setProgress(pct);

    // Buttons
    $backBtn.prop("disabled", index === 0);
    $nextBtn.text(isLast ? CA_Config.labels.submit : CA_Config.labels.next);
    if (isLast) {
      $nextBtn.append(""); // clear icon for submit
    } else {
      // ensure icon is present
    }

    hideError($questionError);
    showScreen("questions");
  }

  /* -------------------------------------------------------
	   Next / Back
	------------------------------------------------------- */
  function handleNext() {
    if (state.isSubmitting) return;

    var $selected = $modal.find(".ca-answer-option.ca-selected");
    if (!$selected.length) {
      showError($questionError, CA_Config.labels.error_answer);
      return;
    }

    var answer = parseInt($selected.data("value"), 10);
    var index = state.currentIndex;

    // Cache it
    state.answersCache[index] = answer;

    state.isSubmitting = true;
    $nextBtn.prop("disabled", true);

    var data = {
      action: "ca_save_answer",
      nonce: CA_Config.nonce,
      submission_id: state.submissionId,
      question_index: index,
      answer: answer,
    };

    $.post(CA_Config.ajax_url, data)
      .done(function (response) {
        if (response.success) {
          var next = response.data.next_index;
          var isLast = response.data.is_last;
          setProgress(response.data.progress);

          if (isLast) {
            // Final submit
            submitAssessment();
          } else {
            state.currentIndex = next;
            loadQuestion(next);
          }
        } else {
          showError(
            $questionError,
            response.data.message || CA_Config.labels.error_generic,
          );
        }
      })
      .fail(function () {
        showError($questionError, CA_Config.labels.error_generic);
      })
      .always(function () {
        $nextBtn.prop("disabled", false);
        state.isSubmitting = false;
      });
  }

  function handleBack() {
    if (state.currentIndex <= 0) return;
    state.currentIndex--;
    loadQuestion(state.currentIndex);
  }

  /* -------------------------------------------------------
	   Submit assessment
	------------------------------------------------------- */
  function submitAssessment() {
    showScreen("loading");
    setProgress(100);

    var data = {
      action: "ca_submit_assessment",
      nonce: CA_Config.nonce,
      submission_id: state.submissionId,
    };

    $.post(CA_Config.ajax_url, data)
      .done(function (response) {
        if (response.success) {
          clearSavedSession();
          loadResultsPreview();
        } else {
          alert(response.data.message || CA_Config.labels.error_generic);
          showScreen("questions");
        }
      })
      .fail(function () {
        alert(CA_Config.labels.error_generic);
        showScreen("questions");
      });
  }

  /* -------------------------------------------------------
	   Results preview
	------------------------------------------------------- */
  function loadResultsPreview() {
    var data = {
      action: "ca_get_results_preview",
      nonce: CA_Config.nonce,
      submission_id: state.submissionId,
    };

    $.post(CA_Config.ajax_url, data)
      .done(function (response) {
        if (response.success) {
          renderResults(response.data);
        } else {
          alert(response.data.message || CA_Config.labels.error_generic);
        }
      })
      .fail(function () {
        alert(CA_Config.labels.error_generic);
      });
  }

  function renderResults(data) {
    var user = data.user;
    var total = data.total_score;
    var avg = parseFloat(data.average_score).toFixed(2);
    var maxScore = data.max_score;
    var profile = data.overall_profile;
    var cats = data.category_scores;

    var initials = (
      user.first_name.charAt(0) + user.last_name.charAt(0)
    ).toUpperCase();

    // Category cards
    var catHtml = "";
    cats.forEach(function (cat) {
      var pct = Math.round((cat.average / 5) * 100);
      catHtml +=
        '<div class="ca-cat-card">' +
        '<div class="ca-cat-card-header">' +
        '<span class="ca-cat-name">' +
        escHtml(cat.name) +
        "</span>" +
        '<span class="ca-cat-score-badge">' +
        '<span class="ca-cat-score-num">' +
        parseFloat(cat.average).toFixed(2) +
        "</span>" +
        '<span class="ca-cat-score-max">/ 5</span>' +
        "</span>" +
        "</div>" +
        '<div class="ca-cat-bar-track"><div class="ca-cat-bar-fill" style="width:0%" data-width="' +
        pct +
        '%"></div></div>' +
        '<p class="ca-cat-summary">' +
        escHtml(cat.summary) +
        "</p>" +
        "</div>";
    });

    var html =
      '<div class="ca-results-hero">' +
      '<p class="ca-results-hero-name">' +
      escHtml(user.first_name + " " + user.last_name) +
      " — " +
      escHtml(user.job_title) +
      "</p>" +
      '<h2 class="ca-results-profile">' +
      escHtml(profile) +
      "</h2>" +
      '<div class="ca-results-scores-row">' +
      '<div class="ca-results-score-item">' +
      '<span class="ca-results-score-num">' +
      total +
      "<sup>/" +
      maxScore +
      "</sup></span>" +
      '<span class="ca-results-score-label">Total Score</span>' +
      "</div>" +
      '<div class="ca-results-score-item">' +
      '<span class="ca-results-score-num">' +
      avg +
      "<sup>/5</sup></span>" +
      '<span class="ca-results-score-label">Average Score</span>' +
      "</div>" +
      "</div>" +
      "</div>" +
      '<div class="ca-results-body">' +
      '<div class="ca-results-user-card">' +
      '<div class="ca-results-user-avatar">' +
      escHtml(initials) +
      "</div>" +
      '<div class="ca-results-user-info">' +
      '<p class="ca-results-user-name">' +
      escHtml(user.first_name + " " + user.last_name) +
      "</p>" +
      '<p class="ca-results-user-detail">' +
      escHtml(user.email) +
      " &nbsp;·&nbsp; " +
      escHtml(user.phone) +
      "</p>" +
      "</div>" +
      "</div>" +
      '<p class="ca-results-section-title">Category Breakdown</p>' +
      catHtml +
      '<div class="ca-results-cta">' +
      "<p>Your results have been saved. A copy may be shared with you by email.</p>" +
      '<button type="button" class="ca-btn ca-btn--ghost" id="ca-close-results">Close</button>' +
      "</div>" +
      "</div>";

    $resultsContent.html(html);
    hideProgress();
    showScreen("results");

    // Animate bars after short delay
    setTimeout(function () {
      $resultsContent.find(".ca-cat-bar-fill").each(function () {
        var $bar = $(this);
        $bar.css("width", $bar.data("width"));
      });
    }, 100);

    // Bind close results button
    $("#ca-close-results").on("click", closeModal);
  }

  /* -------------------------------------------------------
	   Utility helpers
	------------------------------------------------------- */
  function showError($el, msg) {
    $el.text(msg).addClass("ca-visible");
  }

  function hideError($el) {
    $el.text("").removeClass("ca-visible");
  }

  function setBtnLoading($btn, loading) {
    if (loading) {
      $btn.addClass("ca-btn--loading").prop("disabled", true);
    } else {
      $btn.removeClass("ca-btn--loading").prop("disabled", false);
    }
  }

  // Simple HTML escape to prevent XSS in dynamically inserted strings
  function escHtml(str) {
    return String(str)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#039;");
  }

  /* -------------------------------------------------------
	   Boot
	------------------------------------------------------- */
  $(document).ready(init);
})(jQuery);
