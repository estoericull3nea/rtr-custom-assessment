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
    // Step index (0..totalQuestions-1) in the priority-sorted display order.
    stepIndex: 0,
    totalQuestions: CA_Config.total_questions || 30,
    // Priority-sorted order of the stable backend `question_index` values.
    questionOrder: [],
    // Cache answers by stable `question_index`: { [questionIndex]: answerValue }
    answersCache: {},
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
    $resultsContent,
    $resumeDialog,
    $resumeEmailText,
    $resumeContinueBtn,
    $resumeNewBtn;

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
    $resumeDialog = $("#ca-resume-dialog");
    $resumeEmailText = $("#ca-resume-email-text");
    $resumeContinueBtn = $("#ca-resume-continue");
    $resumeNewBtn = $("#ca-resume-new");

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

    // Don't check for saved session on page reload
    // Only check when user manually enters email and submits
    resetState();
    showScreen("info");
    hideProgress();
  }

  function closeModal() {
    $modal.removeClass("ca-modal--open");
    $modal.attr("aria-hidden", "true");
    $body.removeClass("ca-modal-open");
    hideResumeDialog();
  }

  function showResumeDialog(email, onContinue, onNew) {
    if (!$resumeDialog.length) {
      return;
    }

    $resumeEmailText.text(
      "You have an in-progress assessment for " +
        email +
        ". Click Continue to resume or Start New to begin over.",
    );

    $resumeContinueBtn.off("click").on("click", function () {
      hideResumeDialog();
      if (typeof onContinue === "function") {
        onContinue();
      }
    });

    $resumeNewBtn.off("click").on("click", function () {
      hideResumeDialog();
      if (typeof onNew === "function") {
        onNew();
      }
    });

    $screens.removeClass("ca-screen-active");
    $resumeDialog.removeAttr("hidden");
    setBtnLoading($startBtn, false);
    state.isSubmitting = false;
  }

  function hideResumeDialog() {
    if (!$resumeDialog.length) {
      return;
    }
    $resumeDialog.attr("hidden", "true");
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
    state.stepIndex = 0;
    state.answersCache = {};
    state.isSubmitting = false;
    state.questionOrder = buildQuestionOrder();
    $infoForm[0].reset();
    hideError($infoError);
    setProgress(0);
  }

  function buildQuestionOrder() {
    var list = Array.isArray(CA_Config.questions_priority)
      ? CA_Config.questions_priority
      : [];

    if (list.length > 0) {
      // Priority is managed per category in admin, so keep category groups
      // in their original order and sort only within each category.
      var categoryOrder = {};
      var categoryPos = 0;
      list.forEach(function (item) {
        var cat = item && item.category ? String(item.category) : "";
        if (!Object.prototype.hasOwnProperty.call(categoryOrder, cat)) {
          categoryOrder[cat] = categoryPos;
          categoryPos++;
        }
      });

      list = list.slice().sort(function (a, b) {
        var ac = a && a.category ? String(a.category) : "";
        var bc = b && b.category ? String(b.category) : "";
        var acPos = Object.prototype.hasOwnProperty.call(categoryOrder, ac)
          ? categoryOrder[ac]
          : Number.MAX_SAFE_INTEGER;
        var bcPos = Object.prototype.hasOwnProperty.call(categoryOrder, bc)
          ? categoryOrder[bc]
          : Number.MAX_SAFE_INTEGER;
        if (acPos !== bcPos) return acPos - bcPos;

        var ap = parseInt(a.priority, 10) || 0;
        var bp = parseInt(b.priority, 10) || 0;
        if (ap !== bp) return ap - bp;

        var ai = parseInt(a.index, 10) || 0;
        var bi = parseInt(b.index, 10) || 0;
        return ai - bi;
      });

      return list
        .map(function (item) {
          return parseInt(item.index, 10) || 0;
        })
        .filter(function (v, i, arr) {
          // De-dupe (shouldn't be needed, but prevents weirdness if data is malformed)
          return arr.indexOf(v) === i;
        });
    }

    // Fallback: natural order.
    var order = [];
    for (var i = 0; i < state.totalQuestions; i++) order.push(i);
    return order;
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

  function resumeAssessment(submissionId, answersMap, total) {
    state.submissionId = submissionId;
    state.answersCache = answersMap && typeof answersMap === "object" ? answersMap : {};

    saveSession(
      $("#ca-email").val().trim() || getSavedSession().email || "",
      submissionId,
    );

    var answeredCount = Object.keys(state.answersCache).length;
    setProgress(total > 0 ? Math.round((answeredCount / total) * 100) : 0);
    showProgress();

    // Find first unanswered question in the priority-sorted order.
    function hasAnswer(questionIndex) {
      var idx = parseInt(questionIndex, 10);
      return (
        Object.prototype.hasOwnProperty.call(state.answersCache, idx) ||
        Object.prototype.hasOwnProperty.call(state.answersCache, String(idx))
      );
    }

    var nextStep = 0;
    var allAnswered = true;
    for (var step = 0; step < total; step++) {
      var qIndex = state.questionOrder[step];
      if (!hasAnswer(qIndex)) {
        nextStep = step;
        allAnswered = false;
        break;
      }
    }

    state.stepIndex = nextStep;

    if (allAnswered) {
      // Should be rare, but if everything is answered just compute results.
      submitAssessment();
      return;
    }

    loadQuestion(state.stepIndex);
  }

  function findInProgressByEmail(email, next) {
    caPost({
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

    caPost(data)
      .done(function (response) {
        if (response.success) {
          state.submissionId = response.data.submission_id;
          state.stepIndex = 0;
          saveSession(data.email, state.submissionId);
          showScreen("questions");
          showProgress();
          loadQuestion(state.stepIndex);
        } else {
          showError(
            $infoError,
            (response &&
            response.data &&
            typeof response.data === "string" &&
            response.data) ||
              (response &&
                response.data &&
                response.data.message) ||
              CA_Config.labels.error_generic,
          );
        }
      })
      .fail(function (xhr, textStatus, errorThrown) {
        // eslint-disable-next-line no-console
        console.error("CA AJAX ca_save_user_info failed:", {
          textStatus: textStatus,
          errorThrown: errorThrown,
          status: xhr && xhr.status ? xhr.status : null,
          responseText:
            xhr && xhr.responseText ? xhr.responseText.slice(0, 500) : null,
        });
        var serverMsg =
          (xhr &&
            xhr.responseJSON &&
            xhr.responseJSON.data &&
            xhr.responseJSON.data.message) ||
          null;
        showError($infoError, serverMsg || CA_Config.labels.error_generic);
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
        (response.data.status === "in_progress" ||
          response.data.status === "started")
      ) {
        showResumeDialog(
          email,
          function () {
            resumeAssessment(
              response.data.submission_id,
              response.data.answers_map,
              response.data.total,
            );
          },
          function () {
            clearSavedSession();
            saveUserInfo();
          },
        );
        return;
      }

      // Either no in-progress entry or user chose to start new
      saveUserInfo();
    });
  }

  /* -------------------------------------------------------
	   Question loading
	------------------------------------------------------- */
  function loadQuestion(stepIndex) {
    showScreen("loading");

    var questionIndex =
      state.questionOrder && state.questionOrder.length > 0
        ? state.questionOrder[stepIndex]
        : stepIndex;

    var data = {
      action: "ca_get_question",
      nonce: CA_Config.nonce,
      question_index: questionIndex,
      submission_id: state.submissionId,
    };

    caPost(data)
      .done(function (response) {
        if (response.success) {
          renderQuestion(response.data, stepIndex, questionIndex);
        } else {
          alert(
            (response &&
              response.data &&
              typeof response.data === "string"
              ? response.data
              : response.data.message) ||
              CA_Config.labels.error_generic,
          );
        }
      })
      .fail(function (xhr, textStatus, errorThrown) {
        // eslint-disable-next-line no-console
        console.error("CA AJAX ca_get_question failed:", {
          textStatus: textStatus,
          errorThrown: errorThrown,
          status: xhr && xhr.status ? xhr.status : null,
          responseText:
            xhr && xhr.responseText ? xhr.responseText.slice(0, 500) : null,
        });
        alert(CA_Config.labels.error_generic);
      });
  }

  function renderQuestion(data, stepIndex, questionIndex) {
    var q = data.question;
    var total = data.total;
    var saved = data.saved_answer;
    var isLast = stepIndex >= total - 1;

    // Update text
    $categoryLabel.text(q.category);
    $questionCounter.text("Question " + (stepIndex + 1) + " of " + total);
    $questionText.text(q.text);

    // Clear and restore answers
    $answerOptions.removeClass("ca-selected");
    $modal.find(".ca-answer-radio").prop("checked", false);

    var selectedVal = state.answersCache[questionIndex] || saved;
    if (selectedVal) {
      var $opt = $modal.find(
        '.ca-answer-option[data-value="' + selectedVal + '"]',
      );
      $opt.addClass("ca-selected");
      $opt.find(".ca-answer-radio").prop("checked", true);
    }

    // Progress
    var pct = total > 0 ? Math.round((stepIndex / total) * 100) : 0;
    setProgress(pct);

    // Buttons
    $backBtn.prop("disabled", stepIndex === 0);
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
    var stepIndex = state.stepIndex;
    var questionIndex =
      state.questionOrder && state.questionOrder.length > 0
        ? state.questionOrder[stepIndex]
        : stepIndex;

    // Cache it
    state.answersCache[questionIndex] = answer;

    state.isSubmitting = true;
    $nextBtn.prop("disabled", true);

    var data = {
      action: "ca_save_answer",
      nonce: CA_Config.nonce,
      submission_id: state.submissionId,
      question_index: questionIndex,
      answer: answer,
    };

    caPost(data)
      .done(function (response) {
        if (response.success) {
          var nextStep = stepIndex + 1;
          var isLast = nextStep >= state.totalQuestions;

          if (isLast) {
            submitAssessment();
          } else {
            state.stepIndex = nextStep;
            loadQuestion(nextStep);
          }
        } else {
          showError(
            $questionError,
            (response &&
            response.data &&
            typeof response.data === "string" &&
            response.data) ||
              (response &&
                response.data &&
                response.data.message) ||
              CA_Config.labels.error_generic,
          );
        }
      })
      .fail(function (xhr, textStatus, errorThrown) {
        // eslint-disable-next-line no-console
        console.error("CA AJAX ca_save_answer failed:", {
          textStatus: textStatus,
          errorThrown: errorThrown,
          status: xhr && xhr.status ? xhr.status : null,
          responseText:
            xhr && xhr.responseText ? xhr.responseText.slice(0, 500) : null,
        });
        showError($questionError, CA_Config.labels.error_generic);
      })
      .always(function () {
        $nextBtn.prop("disabled", false);
        state.isSubmitting = false;
      });
  }

  function handleBack() {
    if (state.stepIndex <= 0) return;
    state.stepIndex--;
    loadQuestion(state.stepIndex);
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

    caPost(data)
      .done(function (response) {
        if (response.success) {
          clearSavedSession();
          loadResultsPreview();
        } else {
          alert(
            (response &&
              response.data &&
              typeof response.data === "string"
              ? response.data
              : response.data.message) ||
              CA_Config.labels.error_generic,
          );
          showScreen("questions");
        }
      })
      .fail(function (xhr, textStatus, errorThrown) {
        // eslint-disable-next-line no-console
        console.error("CA AJAX ca_submit_assessment failed:", {
          textStatus: textStatus,
          errorThrown: errorThrown,
          status: xhr && xhr.status ? xhr.status : null,
          responseText:
            xhr && xhr.responseText ? xhr.responseText.slice(0, 500) : null,
        });
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

    caPost(data)
      .done(function (response) {
        if (response.success) {
          renderResults(response.data);
        } else {
          alert(
            (response &&
              response.data &&
              typeof response.data === "string"
              ? response.data
              : response.data.message) ||
              CA_Config.labels.error_generic,
          );
        }
      })
      .fail(function (xhr, textStatus, errorThrown) {
        // eslint-disable-next-line no-console
        console.error("CA AJAX ca_get_results_preview failed:", {
          textStatus: textStatus,
          errorThrown: errorThrown,
          status: xhr && xhr.status ? xhr.status : null,
          responseText:
            xhr && xhr.responseText ? xhr.responseText.slice(0, 500) : null,
        });
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

  /**
   * Robust AJAX POST wrapper.
   *
   * On some mobile networks, proxies/CDNs/WAFs can inject extra content
   * (whitespace, BOM, HTML notices) before the JSON body that WordPress
   * returns from admin-ajax.php.  jQuery's default behaviour is to fail
   * with "parsererror" when the response cannot be decoded as JSON, which
   * surfaces as the generic "Something went wrong" message.
   *
   * caPost() forces dataType: 'json' and uses a dataFilter to strip any
   * leading garbage before the first '{' so JSON.parse always receives a
   * clean string.  It returns the same jQuery jqXHR/promise so callers
   * can chain .done() / .fail() / .always() as usual.
   */
  function caPost(data) {
    return $.ajax({
      url: CA_Config.ajax_url,
      type: "POST",
      data: data,
      dataType: "json",
      dataFilter: function (raw) {
        if (typeof raw === "string") {
          // Attempt to isolate the JSON payload even if proxies inject
          // extra HTML/text before/after the JSON.
          raw = raw.trim();
          var start = raw.indexOf("{");
          var startAlt = raw.indexOf("[");
          if (start < 0) start = startAlt;

          if (start >= 0) {
            var endObj = raw.lastIndexOf("}");
            var endArr = raw.lastIndexOf("]");
            var end = Math.max(endObj, endArr);
            if (end > start) {
              raw = raw.substring(start, end + 1);
            } else {
              raw = raw.substring(start);
            }
          }
        }
        return raw;
      },
    });
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
