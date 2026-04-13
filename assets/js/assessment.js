/**
 * Custom Assessment — Frontend JavaScript
 * Multi-assessment modal (mindset 1–5, social fluency 1–10).
 */
(function ($) {
  "use strict";

  var state = {
    assessmentType: "mindset",
    submissionId: null,
    stepIndex: 0,
    totalQuestions: 0,
    questionOrder: [],
    answersCache: {},
    isSubmitting: false,
  };

  var $modal,
    $overlay,
    $body,
    $progressContainer,
    $progressBar,
    $progressLabel,
    $screens,
    $infoForm,
    $infoError,
    $startBtn,
    $modalTitle,
    $categoryLabel,
    $questionCounter,
    $questionText,
    $questionScaleNote,
    $answerGroup,
    $questionError,
    $backBtn,
    $nextBtn,
    $phoneInput,
    $phoneCountrySelect,
    $resultsContent,
    $resumeDialog,
    $resumeEmailText,
    $resumeContinueBtn,
    $resumeNewBtn;

  function getCurrentConfig() {
    if (
      CA_Config.assessments &&
      state.assessmentType &&
      CA_Config.assessments[state.assessmentType]
    ) {
      return CA_Config.assessments[state.assessmentType];
    }
    return {
      type: "mindset",
      modal_title: "Assessment",
      scale_max: 5,
      total_questions: CA_Config.total_questions || 30,
      scale_note: "",
      per_number_labels: {},
      questions_priority: CA_Config.questions_priority || [],
    };
  }

  function sessionStorageKey() {
    return "ca_assessment_session_" + state.assessmentType;
  }

  function withAssessment(payload) {
    var data = payload || {};
    data.assessment_type = state.assessmentType;
    return data;
  }

  function init() {
    $modal = $("#ca-modal");
    if (!$modal.length) {
      return;
    }

    $overlay = $("#ca-modal-overlay");
    $body = $("body");
    $progressContainer = $("#ca-progress-container");
    $progressBar = $("#ca-progress-bar");
    $progressLabel = $("#ca-progress-label");
    $screens = $modal.find(".ca-screen");
    $infoForm = $("#ca-info-form");
    $infoError = $("#ca-info-error");
    $startBtn = $("#ca-start-btn");
    $modalTitle = $("#ca-modal-title");
    $categoryLabel = $("#ca-category-label");
    $questionCounter = $("#ca-question-counter");
    $questionText = $("#ca-question-text");
    $questionScaleNote = $("#ca-question-scale-note");
    $answerGroup = $("#ca-answer-group");
    $questionError = $("#ca-question-error");
    $backBtn = $("#ca-back-btn");
    $nextBtn = $("#ca-next-btn");
    $phoneInput = $("#ca-phone");
    $phoneCountrySelect = $("#ca-phone-country");
    $resultsContent = $("#ca-results-content");
    $resumeDialog = $("#ca-resume-dialog");
    $resumeEmailText = $("#ca-resume-email-text");
    $resumeContinueBtn = $("#ca-resume-continue");
    $resumeNewBtn = $("#ca-resume-new");

    $(document).on("click", ".ca-assessment-trigger", openModal);

    $("#ca-close-modal").on("click", closeModal);
    $overlay.on("click", closeModal);

    $(document).on("keydown", function (e) {
      if (e.key === "Escape" && $modal.hasClass("ca-modal--open")) {
        closeModal();
      }
    });

    $infoForm.on("submit", handleInfoSubmit);
    $phoneCountrySelect.on("change", syncPhonePlaceholderWithCountry);
    syncPhonePlaceholderWithCountry();

    $modal.on("click", ".ca-answer-option", function () {
      $modal.find(".ca-answer-option").removeClass("ca-selected");
      $(this).addClass("ca-selected");
      $(this).find(".ca-answer-radio").prop("checked", true);
      hideError($questionError);
    });

    $nextBtn.on("click", handleNext);
    $backBtn.on("click", handleBack);
  }

  function openModal(e) {
    var type = $(e.currentTarget).attr("data-ca-assessment") || "mindset";
    state.assessmentType = type;

    var cfg = getCurrentConfig();
    if ($modalTitle.length) {
      $modalTitle.text(cfg.modal_title || "Assessment");
    }

    $modal.attr("aria-hidden", "false");
    $body.addClass("ca-modal-open");

    requestAnimationFrame(function () {
      $modal.addClass("ca-modal--open");
    });

    resetState();
    showScreen("info");
    hideProgress();
  }

  function closeModal() {
    $modal.removeClass("ca-modal--open");
    $modal.attr("aria-hidden", "true");
    $body.removeClass("ca-modal-open");
    hideResumeDialog();
    $("#ca-scale-endpoints").remove();
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
        localStorage.getItem(sessionStorageKey()) || "null",
      );
    } catch (err) {
      return null;
    }
  }

  function saveSession(email, submissionId) {
    localStorage.setItem(
      sessionStorageKey(),
      JSON.stringify({ email: email, submissionId: submissionId }),
    );
  }

  function clearSavedSession() {
    localStorage.removeItem(sessionStorageKey());
  }

  function resetState() {
    var cfg = getCurrentConfig();
    state.submissionId = null;
    state.stepIndex = 0;
    state.answersCache = {};
    state.isSubmitting = false;
    state.totalQuestions = cfg.total_questions || 0;
    state.questionOrder = buildQuestionOrder();
    $infoForm[0].reset();
    syncPhonePlaceholderWithCountry();
    hideError($infoError);
    setProgress(0);
    $("#ca-scale-endpoints").remove();
  }

  function syncPhonePlaceholderWithCountry() {
    if (!$phoneInput.length || !$phoneCountrySelect.length) {
      return;
    }
    var selected = $phoneCountrySelect.find("option:selected");
    var placeholder = selected.attr("data-placeholder") || "+1 (555) 000-0000";
    $phoneInput.attr("placeholder", placeholder);
  }

  function buildQuestionOrder() {
    var cfg = getCurrentConfig();
    var list = Array.isArray(cfg.questions_priority)
      ? cfg.questions_priority
      : [];

    if (list.length > 0) {
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
          return arr.indexOf(v) === i;
        });
    }

    var order = [];
    var n = state.totalQuestions || 0;
    for (var i = 0; i < n; i++) order.push(i);
    return order;
  }

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
      $modal.find(".ca-modal-body").scrollTop(0);
    }
  }

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
      $("#ca-email").val().trim() || (getSavedSession() && getSavedSession().email) || "",
      submissionId,
    );

    var answeredCount = Object.keys(state.answersCache).length;
    setProgress(total > 0 ? Math.round((answeredCount / total) * 100) : 0);
    showProgress();

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
      submitAssessment();
      return;
    }

    loadQuestion(state.stepIndex);
  }

  function findInProgressByEmail(email, next) {
    caPost(
      withAssessment({
        action: "ca_find_in_progress_by_email",
        nonce: CA_Config.nonce,
        email: email,
      }),
    )
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
    var data = withAssessment({
      action: "ca_save_user_info",
      nonce: CA_Config.nonce,
      first_name: $("#ca-first-name").val().trim(),
      last_name: $("#ca-last-name").val().trim(),
      email: $("#ca-email").val().trim(),
      phone: $("#ca-phone").val().trim(),
      job_title: $("#ca-job-title").val().trim(),
    });

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
              (response && response.data && response.data.message) ||
              CA_Config.labels.error_generic,
          );
        }
      })
      .fail(function (xhr, textStatus, errorThrown) {
        console.error("CA AJAX ca_save_user_info failed:", {
          textStatus: textStatus,
          errorThrown: errorThrown,
          status: xhr && xhr.status ? xhr.status : null,
          responseText:
            xhr && xhr.responseText ? xhr.responseText.slice(0, 500) : null,
        });
        showError($infoError, getAjaxErrorMessage(xhr));
      })
      .always(function () {
        setBtnLoading($startBtn, false);
        state.isSubmitting = false;
      });
  }

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

      saveUserInfo();
    });
  }

  function escAttr(s) {
    return String(s)
      .replace(/&/g, "&amp;")
      .replace(/"/g, "&quot;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;");
  }

  function buildAnswerMarkup(q) {
    var cfg = getCurrentConfig();
    var scaleMax =
      (q && q.scale_max) ||
      (cfg && cfg.scale_max) ||
      5;
    var style = (q && q.label_style) || "per_number";
    var html = "";
    var i;

    if (style === "yes_no") {
      var yesLbl =
        (CA_Config.labels && CA_Config.labels.yes_no_yes) || "Yes";
      var noLbl = (CA_Config.labels && CA_Config.labels.yes_no_no) || "No";
      html +=
        '<label class="ca-answer-option ca-answer-option--yesno" data-value="1">' +
        '<input type="radio" name="ca_answer" value="1" class="ca-answer-radio" aria-label="' +
        escAttr(yesLbl) +
        '">' +
        '<span class="ca-answer-btn ca-answer-btn--yesno"><span class="ca-answer-label">' +
        escHtml(yesLbl) +
        "</span></span></label>";
      html +=
        '<label class="ca-answer-option ca-answer-option--yesno" data-value="2">' +
        '<input type="radio" name="ca_answer" value="2" class="ca-answer-radio" aria-label="' +
        escAttr(noLbl) +
        '">' +
        '<span class="ca-answer-btn ca-answer-btn--yesno"><span class="ca-answer-label">' +
        escHtml(noLbl) +
        "</span></span></label>";
    } else if (style === "endpoints") {
      for (i = 1; i <= scaleMax; i++) {
        html +=
          '<label class="ca-answer-option" data-value="' +
          i +
          '">' +
          '<input type="radio" name="ca_answer" value="' +
          i +
          '" class="ca-answer-radio" aria-label="' +
          i +
          '">' +
          '<span class="ca-answer-btn"><span class="ca-answer-num">' +
          i +
          '</span><span class="ca-answer-label"></span></span></label>';
      }
    } else {
      var labels = (cfg && cfg.per_number_labels) || {};
      for (i = 1; i <= scaleMax; i++) {
        var lbl =
          (labels[i] !== undefined && labels[i] !== null && labels[i] !== ""
            ? labels[i]
            : labels[String(i)]) || "";
        html +=
          '<label class="ca-answer-option" data-value="' +
          i +
          '">' +
          '<input type="radio" name="ca_answer" value="' +
          i +
          '" class="ca-answer-radio" aria-label="' +
          escAttr(lbl ? lbl : String(i)) +
          '">' +
          '<span class="ca-answer-btn"><span class="ca-answer-num">' +
          i +
          '</span><span class="ca-answer-label">' +
          escHtml(lbl) +
          "</span></span></label>";
      }
    }

    return { html: html, scaleMax: scaleMax, style: style, endpoints: (q && q.endpoints) || {} };
  }

  function loadQuestion(stepIndex) {
    showScreen("loading");

    var questionIndex =
      state.questionOrder && state.questionOrder.length > 0
        ? state.questionOrder[stepIndex]
        : stepIndex;

    var data = withAssessment({
      action: "ca_get_question",
      nonce: CA_Config.nonce,
      question_index: questionIndex,
      submission_id: state.submissionId,
    });

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
        console.error("CA AJAX ca_get_question failed:", {
          textStatus: textStatus,
          errorThrown: errorThrown,
          status: xhr && xhr.status ? xhr.status : null,
          responseText:
            xhr && xhr.responseText ? xhr.responseText.slice(0, 500) : null,
        });
        alert(getAjaxErrorMessage(xhr));
      });
  }

  function renderQuestion(data, stepIndex, questionIndex) {
    var q = data.question;
    var total = data.total;
    var saved = data.saved_answer;
    var isLast = stepIndex >= total - 1;
    var cfg = getCurrentConfig();

    $("#ca-scale-endpoints").remove();

    var noteText = cfg.scale_note || "";
    if ($questionScaleNote.length) {
      $questionScaleNote.html(noteText || "");
    }

    var built = buildAnswerMarkup(q);
    $answerGroup.html(built.html);
    $answerGroup.removeClass(
      "ca-answer-group--cols-10 ca-answer-group--cols-5 ca-answer-group--yesno",
    );
    if (built.style === "yes_no") {
      $answerGroup.addClass("ca-answer-group--yesno");
    } else if (built.scaleMax > 5) {
      $answerGroup.addClass("ca-answer-group--cols-10");
    } else {
      $answerGroup.addClass("ca-answer-group--cols-5");
    }

    if (built.style === "endpoints") {
      var ep = built.endpoints || {};
      var midHtml = ep.mid
        ? '<span class="ca-scale-endpoints__mid">' + escHtml(ep.mid) + "</span>"
        : '<span class="ca-scale-endpoints__mid" aria-hidden="true"></span>';
      $answerGroup.after(
        '<div class="ca-scale-endpoints" id="ca-scale-endpoints">' +
          '<span class="ca-scale-endpoints__left">' +
          escHtml(ep.left || "") +
          "</span>" +
          midHtml +
          '<span class="ca-scale-endpoints__right">' +
          escHtml(ep.right || "") +
          "</span></div>",
      );
    }

    $categoryLabel.text(q.category);
    $questionCounter.text("Question " + (stepIndex + 1) + " of " + total);
    $questionText.text(q.text);

    $modal.find(".ca-answer-option").removeClass("ca-selected");
    $modal.find(".ca-answer-radio").prop("checked", false);

    var selectedVal = state.answersCache[questionIndex] || saved;
    if (selectedVal) {
      var $opt = $modal.find(
        '.ca-answer-option[data-value="' + selectedVal + '"]',
      );
      $opt.addClass("ca-selected");
      $opt.find(".ca-answer-radio").prop("checked", true);
    }

    var pct = total > 0 ? Math.round((stepIndex / total) * 100) : 0;
    setProgress(pct);

    $backBtn.prop("disabled", stepIndex === 0);
    $nextBtn.text(isLast ? CA_Config.labels.submit : CA_Config.labels.next);

    hideError($questionError);
    showScreen("questions");
  }

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

    state.answersCache[questionIndex] = answer;

    state.isSubmitting = true;
    $nextBtn.prop("disabled", true);

    var data = withAssessment({
      action: "ca_save_answer",
      nonce: CA_Config.nonce,
      submission_id: state.submissionId,
      question_index: questionIndex,
      answer: answer,
    });

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
              (response && response.data && response.data.message) ||
              CA_Config.labels.error_generic,
          );
        }
      })
      .fail(function (xhr, textStatus, errorThrown) {
        console.error("CA AJAX ca_save_answer failed:", {
          textStatus: textStatus,
          errorThrown: errorThrown,
          status: xhr && xhr.status ? xhr.status : null,
          responseText:
            xhr && xhr.responseText ? xhr.responseText.slice(0, 500) : null,
        });
        showError($questionError, getAjaxErrorMessage(xhr));
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

  function submitAssessment() {
    showScreen("loading");
    setProgress(100);

    var data = withAssessment({
      action: "ca_submit_assessment",
      nonce: CA_Config.nonce,
      submission_id: state.submissionId,
    });

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
        console.error("CA AJAX ca_submit_assessment failed:", {
          textStatus: textStatus,
          errorThrown: errorThrown,
          status: xhr && xhr.status ? xhr.status : null,
          responseText:
            xhr && xhr.responseText ? xhr.responseText.slice(0, 500) : null,
        });
        alert(getAjaxErrorMessage(xhr));
        showScreen("questions");
      });
  }

  function loadResultsPreview() {
    var data = withAssessment({
      action: "ca_get_results_preview",
      nonce: CA_Config.nonce,
      submission_id: state.submissionId,
    });

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
        console.error("CA AJAX ca_get_results_preview failed:", {
          textStatus: textStatus,
          errorThrown: errorThrown,
          status: xhr && xhr.status ? xhr.status : null,
          responseText:
            xhr && xhr.responseText ? xhr.responseText.slice(0, 500) : null,
        });
        alert(getAjaxErrorMessage(xhr));
      });
  }

  function renderResults(data) {
    var user = data.user;
    var total = data.total_score;
    var avgNum = parseFloat(data.average_score);
    var avg = avgNum.toFixed(2);
    var maxScore = data.max_score;
    var profile = data.overall_profile;
    var cats = data.category_scores;
    var scaleMax = parseInt(data.scale_max, 10) || 5;
    var isYesNo = data.assessment_type === "inner_dimensions";

    var initials = (
      user.first_name.charAt(0) + user.last_name.charAt(0)
    ).toUpperCase();

    var catHtml = "";
    cats.forEach(function (cat) {
      var pct = Math.round((cat.average / scaleMax) * 100);
      var scoreBadge = isYesNo
        ? '<span class="ca-cat-score-badge"><span class="ca-cat-score-num">' +
          pct +
          '%</span><span class="ca-cat-score-max"> Yes</span></span>'
        : '<span class="ca-cat-score-badge">' +
          '<span class="ca-cat-score-num">' +
          parseFloat(cat.average).toFixed(2) +
          "</span>" +
          '<span class="ca-cat-score-max">/ ' +
          scaleMax +
          "</span>" +
          "</span>";
      catHtml +=
        '<div class="ca-cat-card">' +
        '<div class="ca-cat-card-header">' +
        '<span class="ca-cat-name">' +
        escHtml(cat.name) +
        "</span>" +
        scoreBadge +
        "</div>" +
        '<div class="ca-cat-bar-track"><div class="ca-cat-bar-fill" style="width:0%" data-width="' +
        pct +
        '%"></div></div>' +
        '<p class="ca-cat-summary">' +
        escHtml(cat.summary) +
        "</p>" +
        "</div>";
    });

    var avgBlock = isYesNo
      ? '<span class="ca-results-score-num">' +
        Math.round(avgNum * 100) +
        "%</span>" +
        '<span class="ca-results-score-label">Yes responses (overall)</span>'
      : '<span class="ca-results-score-num">' +
        avg +
        "<sup>/" +
        scaleMax +
        "</sup></span>" +
        '<span class="ca-results-score-label">Average Score</span>';

    var IR = CA_Config.inner_results || {};
    var nacTop = "";
    if (isYesNo) {
      var emailEsc = escHtml(user.email);
      nacTop =
        '<div class="ca-results-nac-completion">' +
        '<h1 class="ca-results-nac-title">' +
        escHtml(IR.title || "Natural Attributes Cataloging") +
        "</h1>" +
        '<p class="ca-results-nac-quote">&ldquo;' +
        escHtml(
          IR.tagline ||
            "Remember Who You Were Before the World Told You Who to Be.",
        ) +
        '&rdquo;</p>' +
        '<h2 class="ca-results-nac-subtitle">' +
        escHtml(
          IR.congrats ||
            "Congratulations on Completing Your Discovery Journey!",
        ) +
        "</h2>" +
        '<p class="ca-results-nac-email">' +
        escHtml(IR.email_lead || "Your full report has been emailed to") +
        " <strong>" +
        emailEsc +
        '</strong>. <button type="button" class="ca-results-change-email-btn" id="ca-nac-change-email">' +
        escHtml(IR.change_email || "Change email address") +
        "</button></p>" +
        '<p class="ca-results-nac-intro">' +
        escHtml(
          IR.intro ||
            "You've taken an important step towards unlocking your potential. Dive into your personalized results below to uncover insights and next steps on your path to enhancing leadership skills and embracing new opportunities.",
        ) +
        "</p>" +
        "</div>";
    }

    var paywallMessage = isYesNo
      ? '<div class="ca-results-paywall-text"><button type="button" class="ca-btn ca-btn--primary ca-results-paywall-btn">please pay to get the full results</button></div>'
      : "";

    var ctaBlock = isYesNo
      ? '<div class="ca-results-cta">' +
        '<button type="button" class="ca-btn ca-btn--ghost" id="ca-close-results">Close</button>' +
        "</div>"
      : '<div class="ca-results-cta">' +
        "<p>Your results have been saved. A copy may be shared with you by email.</p>" +
        '<button type="button" class="ca-btn ca-btn--ghost" id="ca-close-results">Close</button>' +
        "</div>";

    var html =
      nacTop +
      '<div class="ca-results-hero' +
      (isYesNo ? " ca-results-preview-blocked" : "") +
      '">' +
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
      avgBlock +
      "</div>" +
      "</div>" +
      "</div>" +
      '<div class="ca-results-body' +
      (isYesNo ? " ca-results-preview-blocked" : "") +
      '">' +
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
      ctaBlock +
      "</div>" +
      paywallMessage;

    $resultsContent.html(html);
    hideProgress();
    showScreen("results");

    setTimeout(function () {
      $resultsContent.find(".ca-cat-bar-fill").each(function () {
        var $bar = $(this);
        $bar.css("width", $bar.data("width"));
      });
    }, 100);

    $resultsContent
      .off("click.caCloseResults")
      .on("click.caCloseResults", "#ca-close-results", closeModal);

    $resultsContent
      .off("click.caNacChangeEmail")
      .on("click.caNacChangeEmail", "#ca-nac-change-email", function (e) {
        e.preventDefault();
        showScreen("info");
        hideProgress();
        setTimeout(function () {
          $("#ca-email").trigger("focus");
          try {
            document.getElementById("ca-email").select();
          } catch (err) {
            /* ignore */
          }
        }, 100);
      });
  }

  function showError($el, msg) {
    $el.text(msg).addClass("ca-visible");
  }

  function hideError($el) {
    $el.text("").removeClass("ca-visible");
  }

  function decodeHtmlEntities(str) {
    var txt = document.createElement("textarea");
    txt.innerHTML = str;
    return txt.value;
  }

  function stripHtml(str) {
    return String(str || "")
      .replace(/<style[\s\S]*?<\/style>/gi, " ")
      .replace(/<script[\s\S]*?<\/script>/gi, " ")
      .replace(/<[^>]+>/g, " ")
      .replace(/\s+/g, " ")
      .trim();
  }

  function getAjaxErrorMessage(xhr) {
    var fallback = CA_Config.labels.error_generic;
    if (!xhr) return fallback;

    if (
      xhr.responseJSON &&
      xhr.responseJSON.data &&
      xhr.responseJSON.data.message
    ) {
      return String(xhr.responseJSON.data.message);
    }

    if (xhr.responseJSON && xhr.responseJSON.message) {
      return String(xhr.responseJSON.message);
    }

    var raw = xhr.responseText ? String(xhr.responseText).trim() : "";
    if (!raw) {
      return xhr.status ? "Request failed (HTTP " + xhr.status + ")." : fallback;
    }

    try {
      var parsed = JSON.parse(raw);
      if (parsed && parsed.data && parsed.data.message) {
        return String(parsed.data.message);
      }
      if (parsed && parsed.message) {
        return String(parsed.message);
      }
    } catch (e1) {
      /* ignore */
    }

    var text = stripHtml(decodeHtmlEntities(raw));
    if (text) {
      return text.slice(0, 240);
    }

    return xhr.status ? "Request failed (HTTP " + xhr.status + ")." : fallback;
  }

  function caPost(data) {
    return $.ajax({
      url: CA_Config.ajax_url,
      type: "POST",
      data: data,
      dataType: "json",
      dataFilter: function (raw) {
        if (typeof raw === "string") {
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

  function escHtml(str) {
    return String(str)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#039;");
  }

  $(document).ready(init);
})(jQuery);
