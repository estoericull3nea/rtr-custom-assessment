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
    checkoutUrl: "",
    isSubmitting: false,
    /** True while final assessment submit pipeline is running. */
    isAssessmentSubmitting: false,
    /** Natural Attributes: answers not yet written to server (or draft differs). */
    unsyncedChanges: false,
    /** True while fetching next/previous question without full-screen loading. */
    awaitingQuestionFetch: false,
    /** Bundle flow mode (Natural Attributes + Social Fluency sequential). */
    bundleMode: false,
    /** 0 = first assessment running; 1 = second assessment running. */
    bundleStage: 0,
    bundleFirstType: null,
    bundleSecondType: null,
    bundleSubmissionIds: {},
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
    $closeBtn,
    $nextBtn,
    $phoneInput,
    $phoneCountrySelect,
    $resultsContent,
    $resumeDialog,
    $resumeEmailText,
    $resumeContinueBtn,
    $resumeNewBtn,
    $saveProgressDialog,
    $saveProgressTitle,
    $saveProgressMessage,
    $saveProgressSaveBtn,
    $saveProgressDiscardBtn,
    $saveProgressCancelBtn,
    $nextBtnLabel,
    $bundleOrderScreen,
    $bundleOrderError,
    $bundleInnerFirstBtn,
    $bundleSocialFirstBtn,
    $bundleOrderNextBtn;

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

  function defersServerAnswerSave() {
    var cfg = getCurrentConfig();
    return !!(cfg && cfg.defer_answer_save);
  }

  function draftAnswersStorageKey() {
    return "ca_assessment_draft_" + state.assessmentType;
  }

  function hasAnswerInCache(questionIndex) {
    var idx = parseInt(questionIndex, 10);
    return (
      Object.prototype.hasOwnProperty.call(state.answersCache, idx) ||
      Object.prototype.hasOwnProperty.call(state.answersCache, String(idx))
    );
  }

  /**
   * @returns {{ nextStep: number, allAnswered: boolean }}
   */
  function computeStepProgress() {
    var total = state.totalQuestions;
    var nextStep = 0;
    var allAnswered = true;
    for (var step = 0; step < total; step++) {
      var qIndex = state.questionOrder[step];
      if (!hasAnswerInCache(qIndex)) {
        nextStep = step;
        allAnswered = false;
        break;
      }
    }
    return { nextStep: nextStep, allAnswered: allAnswered };
  }

  function persistDraftAnswers() {
    if (!defersServerAnswerSave() || !state.submissionId) {
      return;
    }
    try {
      localStorage.setItem(
        draftAnswersStorageKey(),
        JSON.stringify({
          submissionId: state.submissionId,
          stepIndex: state.stepIndex,
          answers: state.answersCache,
        }),
      );
    } catch (err) {
      /* ignore quota / private mode */
    }
  }

  function restoreDraftAnswers() {
    if (!defersServerAnswerSave() || !state.submissionId) {
      return;
    }
    try {
      var raw = localStorage.getItem(draftAnswersStorageKey());
      if (!raw) {
        return;
      }
      var d = JSON.parse(raw);
      if (
        !d ||
        parseInt(d.submissionId, 10) !== parseInt(state.submissionId, 10)
      ) {
        return;
      }
      if (d.answers && typeof d.answers === "object") {
        var k;
        var merged = false;
        for (k in d.answers) {
          if (Object.prototype.hasOwnProperty.call(d.answers, k)) {
            var ki = parseInt(k, 10);
            state.answersCache[ki] = parseInt(d.answers[k], 10);
            merged = true;
          }
        }
        if (merged) {
          state.unsyncedChanges = true;
        }
      }
      if (
        typeof d.stepIndex === "number" &&
        d.stepIndex >= 0 &&
        d.stepIndex < state.totalQuestions
      ) {
        state.stepIndex = d.stepIndex;
      }
    } catch (err) {
      /* ignore */
    }
  }

  function clearDraftAnswers() {
    try {
      localStorage.removeItem(draftAnswersStorageKey());
    } catch (err) {
      /* ignore */
    }
  }

  function saveAllAnswersFromCache(onDone, onFail, onProgress) {
    var list = [];
    var step;
    for (step = 0; step < state.totalQuestions; step++) {
      var qix = state.questionOrder[step];
      var ans = state.answersCache[qix];
      if (ans == null || ans === "") {
        continue;
      }
      list.push({
        question_index: qix,
        answer: parseInt(ans, 10),
      });
    }

    function fail(err) {
      if (typeof onFail === "function") {
        onFail(err);
      }
    }

    function run(i) {
      if (i >= list.length) {
        if (typeof onProgress === "function" && list.length > 0) {
          onProgress(list.length, list.length);
        }
        if (typeof onDone === "function") {
          onDone();
        }
        return;
      }
      var item = list[i];
      caPost(
        withAssessment({
          action: "ca_save_answer",
          nonce: CA_Config.nonce,
          submission_id: state.submissionId,
          question_index: item.question_index,
          answer: item.answer,
        }),
      )
        .done(function (response) {
          if (response && response.success) {
            if (typeof onProgress === "function" && list.length > 0) {
              onProgress(i + 1, list.length);
            }
            run(i + 1);
          } else {
            fail(response);
          }
        })
        .fail(function (xhr) {
          fail(xhr);
        });
    }

    if (typeof onProgress === "function" && list.length > 0) {
      onProgress(0, list.length);
    }
    run(0);
  }

  function shouldPromptSaveProgress() {
    if ($("#ca-screen-loading").hasClass("ca-screen-active")) {
      return false;
    }
    if (
      state.bundleMode &&
      state.bundleStage === 0 &&
      $("#ca-screen-results").hasClass("ca-screen-active") &&
      !!state.bundleSubmissionIds[state.bundleFirstType || ""]
    ) {
      return true;
    }
    return (
      defersServerAnswerSave() &&
      $("#ca-screen-questions").hasClass("ca-screen-active") &&
      state.submissionId &&
      state.unsyncedChanges
    );
  }

  function hideSaveProgressDialog() {
    if (!$saveProgressDialog.length) {
      return;
    }
    var wasOpen = !$saveProgressDialog[0].hasAttribute("hidden");
    $saveProgressDialog.attr("hidden", "true");
    if (wasOpen) {
      $screens.removeClass("ca-screen-active");
      if ($("#ca-screen-results").length && state.bundleMode && state.bundleStage === 0) {
        $("#ca-screen-results").addClass("ca-screen-active");
      } else {
        $("#ca-screen-questions").addClass("ca-screen-active");
      }
    }
  }

  function showSaveProgressDialog() {
    if (!$saveProgressDialog.length) {
      return;
    }
    var L = CA_Config.labels || {};
    if ($saveProgressTitle.length && L.save_progress_title) {
      $saveProgressTitle.text(L.save_progress_title);
    }
    if ($saveProgressMessage.length && L.save_progress_message) {
      $saveProgressMessage.text(L.save_progress_message);
    }
    if ($saveProgressSaveBtn.length && L.save_progress_save) {
      $saveProgressSaveBtn.text(L.save_progress_save);
    }
    if ($saveProgressDiscardBtn.length && L.save_progress_discard) {
      $saveProgressDiscardBtn.text(L.save_progress_discard);
    }
    if ($saveProgressCancelBtn.length && L.save_progress_cancel) {
      $saveProgressCancelBtn.text(L.save_progress_cancel);
    }
    $screens.removeClass("ca-screen-active");
    $saveProgressDialog.removeAttr("hidden");
  }

  function attemptCloseModal() {
    if (state.isAssessmentSubmitting) {
      return;
    }
    if (
      $saveProgressDialog.length &&
      !$saveProgressDialog[0].hasAttribute("hidden")
    ) {
      hideSaveProgressDialog();
      return;
    }
    if (shouldPromptSaveProgress()) {
      showSaveProgressDialog();
      return;
    }
    closeModal();
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
    $closeBtn = $("#ca-close-modal");
    $nextBtn = $("#ca-next-btn");
    $nextBtnLabel = $("#ca-next-btn-label");
    $phoneInput = $("#ca-phone");
    $phoneCountrySelect = $("#ca-phone-country");
    $resultsContent = $("#ca-results-content");
    $resumeDialog = $("#ca-resume-dialog");
    $resumeEmailText = $("#ca-resume-email-text");
    $resumeContinueBtn = $("#ca-resume-continue");
    $resumeNewBtn = $("#ca-resume-new");
    $saveProgressDialog = $("#ca-save-progress-dialog");
    $saveProgressTitle = $("#ca-save-progress-title");
    $saveProgressMessage = $("#ca-save-progress-message");
    $saveProgressSaveBtn = $("#ca-save-progress-save");
    $saveProgressDiscardBtn = $("#ca-save-progress-discard");
    $saveProgressCancelBtn = $("#ca-save-progress-cancel");
    $bundleOrderScreen = $("#ca-screen-bundle-order");
    $bundleOrderError = $("#ca-bundle-order-error");
    $bundleInnerFirstBtn = $("#ca-bundle-inner-first");
    $bundleSocialFirstBtn = $("#ca-bundle-social-first");
    $bundleOrderNextBtn = $("#ca-bundle-order-next");
    $(document).on("click", ".ca-assessment-trigger", openModal);

    $("#ca-close-modal").on("click", attemptCloseModal);
    $overlay.on("click", attemptCloseModal);

    $saveProgressSaveBtn.on("click", function () {
      if (state.isSubmitting) {
        return;
      }
      if (
        state.bundleMode &&
        state.bundleStage === 0 &&
        $("#ca-screen-results").hasClass("ca-screen-active")
      ) {
        saveBundleProgress();
        closeModal();
        return;
      }
      state.isSubmitting = true;
      setBtnLoading($saveProgressSaveBtn, true);
      saveAllAnswersFromCache(
        function () {
          state.unsyncedChanges = false;
          clearDraftAnswers();
          setBtnLoading($saveProgressSaveBtn, false);
          state.isSubmitting = false;
          hideSaveProgressDialog();
          closeModal();
        },
        function (err) {
          setBtnLoading($saveProgressSaveBtn, false);
          state.isSubmitting = false;
          var msg = CA_Config.labels.error_generic;
          if (err && err.responseText !== undefined) {
            msg = getAjaxErrorMessage(err);
          } else if (err && err.data) {
            msg =
              typeof err.data === "string"
                ? err.data
                : err.data.message || msg;
          } else if (err && err.responseJSON && err.responseJSON.data) {
            msg =
              err.responseJSON.data.message ||
              (typeof err.responseJSON.data === "string"
                ? err.responseJSON.data
                : msg);
          }
          alert(msg);
        },
      );
    });

    $saveProgressDiscardBtn.on("click", function () {
      state.unsyncedChanges = false;
      clearDraftAnswers();
      if (state.bundleMode) {
        clearBundleProgress();
      }
      closeModal();
    });

    $saveProgressCancelBtn.on("click", function () {
      hideSaveProgressDialog();
    });

    $(document).on("keydown", function (e) {
      if (e.key !== "Escape" || !$modal.hasClass("ca-modal--open")) {
        return;
      }
      if (state.isAssessmentSubmitting) {
        e.preventDefault();
        return;
      }
      if (
        $saveProgressDialog.length &&
        !$saveProgressDialog[0].hasAttribute("hidden")
      ) {
        e.preventDefault();
        hideSaveProgressDialog();
        return;
      }
      attemptCloseModal();
    });

    $infoForm.on("submit", handleInfoSubmit);
    if ($bundleInnerFirstBtn && $bundleSocialFirstBtn) {
      $bundleInnerFirstBtn.on("click", function () {
        state.bundleFirstType = "inner_dimensions";
        state.bundleSecondType = "social_fluency";
        $bundleInnerFirstBtn.addClass("ca-selected");
        $bundleSocialFirstBtn.removeClass("ca-selected");
        if ($bundleOrderError && $bundleOrderError.length) {
          hideError($bundleOrderError);
        }
      });
      $bundleSocialFirstBtn.on("click", function () {
        state.bundleFirstType = "social_fluency";
        state.bundleSecondType = "inner_dimensions";
        $bundleSocialFirstBtn.addClass("ca-selected");
        $bundleInnerFirstBtn.removeClass("ca-selected");
        if ($bundleOrderError && $bundleOrderError.length) {
          hideError($bundleOrderError);
        }
      });
    }
    if ($bundleOrderNextBtn && $bundleOrderNextBtn.length) {
      $bundleOrderNextBtn.on("click", function () {
        if (state.isSubmitting) {
          return;
        }
        if (!state.bundleFirstType || !state.bundleSecondType) {
          if ($bundleOrderError && $bundleOrderError.length) {
            $bundleOrderError.text("Please choose an assessment order.").addClass("ca-visible");
          }
          return;
        }

        state.isSubmitting = true;
        setBtnLoading($startBtn, true);
        hideError($bundleOrderError);

        state.assessmentType = state.bundleFirstType;
        state.bundleStage = 0;

        // Switch to the chosen assessment without clearing the info fields.
        resetState({ keepInfoFields: true });

        var cfg = getCurrentConfig();
        if ($modalTitle.length) {
          $modalTitle.text(cfg.modal_title || "Assessment");
        }

        // Persist bundle flags before starting the assessment.
        var email = $("#ca-email").val().trim();
        if (!email) {
          showError($infoError, "Email is required.");
          state.isSubmitting = false;
          setBtnLoading($startBtn, false);
          return;
        }

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
                state.isSubmitting = false;
                setBtnLoading($startBtn, false);
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
      });
    }
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
    $resultsContent.on("click", ".ca-results-paywall-btn", handlePaywallCheckout);
  }

  function openModal(e) {
    var type = $(e.currentTarget).attr("data-ca-assessment") || "mindset";
    state.assessmentType = type;
    state.bundleMode = type === "bundle";
    state.bundleStage = 0;
    state.bundleFirstType = null;
    state.bundleSecondType = null;
    state.bundleSubmissionIds = {};

    var cfg = getCurrentConfig();
    if ($modalTitle.length) {
      $modalTitle.text(cfg.modal_title || "Assessment");
    }

    // Adjust the Step badge for bundle flow.
    var $infoBadge = $("#ca-screen-info .ca-intro-badge");
    if (state.bundleMode) {
      $infoBadge.text("Step 1 of 3 — Your Information");
    } else {
      $infoBadge.text("Step 1 of 2 — Your Information");
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
    setAssessmentSubmitting(false);
    hideSaveProgressDialog();
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
      return JSON.parse(localStorage.getItem(sessionStorageKey()) || "null");
    } catch (err) {
      return null;
    }
  }

  function bundleProgressStorageKey() {
    return "ca_bundle_progress";
  }

  function getSavedBundleProgress() {
    try {
      return JSON.parse(localStorage.getItem(bundleProgressStorageKey()) || "null");
    } catch (err) {
      return null;
    }
  }

  function saveBundleProgress() {
    if (!state.bundleMode) {
      return;
    }
    var email = ($("#ca-email").val() || "").trim();
    if (!email || !state.bundleFirstType || !state.bundleSecondType) {
      return;
    }
    localStorage.setItem(
      bundleProgressStorageKey(),
      JSON.stringify({
        email: email,
        bundleStage: state.bundleStage,
        bundleFirstType: state.bundleFirstType,
        bundleSecondType: state.bundleSecondType,
        bundleSubmissionIds: state.bundleSubmissionIds || {},
      }),
    );
  }

  function clearBundleProgress() {
    localStorage.removeItem(bundleProgressStorageKey());
  }

  function saveSession(email, submissionId) {
    localStorage.setItem(
      sessionStorageKey(),
      JSON.stringify({ email: email, submissionId: submissionId }),
    );
  }

  function clearSavedSession() {
    localStorage.removeItem(sessionStorageKey());
    clearDraftAnswers();
    if (state.bundleMode) {
      clearBundleProgress();
    }
  }

  function resetState(options) {
    options = options || {};
    var keepInfoFields = !!options.keepInfoFields;
    var cfg = getCurrentConfig();
    state.submissionId = null;
    state.stepIndex = 0;
    state.answersCache = {};
    state.checkoutUrl = "";
    state.isSubmitting = false;
    state.isAssessmentSubmitting = false;
    state.unsyncedChanges = false;
    state.awaitingQuestionFetch = false;
    state.totalQuestions = cfg.total_questions || 0;
    state.questionOrder = buildQuestionOrder();
    if (!keepInfoFields && $infoForm && $infoForm[0]) {
      $infoForm[0].reset();
    }
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
      case "bundle-order":
        $target = $("#ca-screen-bundle-order");
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

      if (name === "bundle-order") {
        if ($bundleInnerFirstBtn && $bundleInnerFirstBtn.length) {
          $bundleInnerFirstBtn.removeClass("ca-selected");
        }
        if ($bundleSocialFirstBtn && $bundleSocialFirstBtn.length) {
          $bundleSocialFirstBtn.removeClass("ca-selected");
        }
        if ($bundleOrderError && $bundleOrderError.length) {
          hideError($bundleOrderError);
        }
        state.bundleFirstType = null;
        state.bundleSecondType = null;
      }
    }
  }

  function showProgress() {
    $progressContainer.addClass("ca-visible");
    $progressContainer.attr("aria-hidden", "false");
  }

  function hideProgress() {
    $progressContainer.removeClass("ca-visible");
    $progressContainer.attr("aria-hidden", "true");
    setAssessmentSubmitting(false);
    resetNextButtonSubmitProgress();
  }

  function setAssessmentSubmitting(isSubmitting) {
    state.isAssessmentSubmitting = !!isSubmitting;
    if ($closeBtn && $closeBtn.length) {
      $closeBtn
        .prop("disabled", state.isAssessmentSubmitting)
        .attr("aria-disabled", state.isAssessmentSubmitting ? "true" : "false");
    }
    if ($backBtn && $backBtn.length) {
      $backBtn.prop(
        "disabled",
        state.isAssessmentSubmitting || state.stepIndex === 0,
      );
    }
  }

  function getNextButtonDefaultLabel() {
    var total = state.totalQuestions;
    if (!total || total < 1) {
      return CA_Config.labels.next;
    }
    var isLast = state.stepIndex >= total - 1;
    return isLast ? CA_Config.labels.submit : CA_Config.labels.next;
  }

  function resetNextButtonSubmitProgress() {
    var $label =
      $nextBtnLabel && $nextBtnLabel.length
        ? $nextBtnLabel
        : $nextBtn.find(".ca-next-btn-label");
    if ($label.length) {
      $label.text(getNextButtonDefaultLabel());
    }
    $nextBtn.find(".ca-next-btn-chevron").removeAttr("hidden");
  }

  function updateSubmitLoadingProgress(pct) {
    pct = Math.min(100, Math.max(0, Math.round(pct)));
    setProgress(pct);
    var $label =
      $nextBtnLabel && $nextBtnLabel.length
        ? $nextBtnLabel
        : $nextBtn.find(".ca-next-btn-label");
    if (!$label.length) {
      return;
    }
    var L = CA_Config.labels || {};
    var lead =
      L.submitting_assessment || L.loading || "Submitting assessment";
    $label.text(lead + " — " + pct + "%");
    $nextBtn.find(".ca-next-btn-chevron").attr("hidden", "hidden");
  }

  function setProgress(pct) {
    pct = Math.min(100, Math.max(0, Math.round(pct)));
    $progressBar.css("width", pct + "%");
    $progressBar.attr("aria-valuenow", pct);
    $progressLabel.text(pct + "% Complete");
  }

  function resumeAssessment(submissionId, answersMap, total) {
    state.submissionId = submissionId;
    state.answersCache =
      answersMap && typeof answersMap === "object" ? answersMap : {};

    saveSession(
      $("#ca-email").val().trim() ||
        (getSavedSession() && getSavedSession().email) ||
        "",
      submissionId,
    );

    restoreDraftAnswers();

    var answeredCount = Object.keys(state.answersCache).length;
    setProgress(total > 0 ? Math.round((answeredCount / total) * 100) : 0);
    showProgress();

    var prog = computeStepProgress();
    state.stepIndex = prog.nextStep;

    if (prog.allAnswered) {
      if (defersServerAnswerSave()) {
        setAssessmentSubmitting(true);
        showScreen("questions");
        showProgress();
        updateSubmitLoadingProgress(0);
        saveAllAnswersFromCache(
          function () {
            state.unsyncedChanges = false;
            clearDraftAnswers();
            submitAssessment({
              skipShowLoading: true,
              progressAtStart: 65,
              progressAfterSubmit: 82,
              progressPreviewFloor: 90,
            });
          },
          function (err) {
            var msg = CA_Config.labels.error_generic;
            if (err && err.data) {
              msg =
                typeof err.data === "string"
                  ? err.data
                  : err.data.message || msg;
            }
            alert(msg);
            hideProgress();
            loadQuestion(state.stepIndex);
          },
          function (done, total) {
            updateSubmitLoadingProgress(
              total <= 0 ? 2 : Math.round((done / total) * 62),
            );
          },
        );
        return;
      }
      showScreen("questions");
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
          if (state.bundleMode) {
            state.bundleSubmissionIds[state.assessmentType] =
              state.submissionId;
          }
          state.stepIndex = 0;
          saveSession(data.email, state.submissionId);
          restoreDraftAnswers();
          showScreen("questions");
          showProgress();
          var prog = computeStepProgress();
          state.stepIndex = prog.nextStep;
          var totalQ = state.totalQuestions;
          var ac = Object.keys(state.answersCache).length;
          setProgress(totalQ > 0 ? Math.round((ac / totalQ) * 100) : 0);

          if (prog.allAnswered && totalQ > 0) {
            if (defersServerAnswerSave()) {
              state.isSubmitting = true;
              setAssessmentSubmitting(true);
              showProgress();
              updateSubmitLoadingProgress(0);
              saveAllAnswersFromCache(
                function () {
                  state.unsyncedChanges = false;
                  clearDraftAnswers();
                  state.isSubmitting = false;
                  submitAssessment({
                    skipShowLoading: true,
                    progressAtStart: 65,
                    progressAfterSubmit: 82,
                    progressPreviewFloor: 90,
                  });
                },
                function (err) {
                  state.isSubmitting = false;
                  var msg = CA_Config.labels.error_generic;
                  if (err && err.data) {
                    msg =
                      typeof err.data === "string"
                        ? err.data
                        : err.data.message || msg;
                  }
                  alert(msg);
                  hideProgress();
                  loadQuestion(state.stepIndex);
                },
                function (done, total) {
                  updateSubmitLoadingProgress(
                    total <= 0 ? 2 : Math.round((done / total) * 62),
                  );
                },
              );
              return;
            }
          }

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

    if (state.bundleMode) {
      var savedBundle = getSavedBundleProgress();
      if (
        savedBundle &&
        savedBundle.email &&
        savedBundle.email.toLowerCase() === email.toLowerCase() &&
        savedBundle.bundleFirstType &&
        savedBundle.bundleSecondType &&
        savedBundle.bundleSubmissionIds
      ) {
        state.bundleMode = true;
        state.bundleStage = parseInt(savedBundle.bundleStage, 10) || 0;
        state.bundleFirstType = savedBundle.bundleFirstType;
        state.bundleSecondType = savedBundle.bundleSecondType;
        state.bundleSubmissionIds = savedBundle.bundleSubmissionIds || {};
        state.assessmentType =
          state.bundleStage >= 1 ? state.bundleSecondType : state.bundleFirstType;

        if (state.bundleSubmissionIds[state.bundleFirstType]) {
          alert(
            "Your first bundle assessment is already done. You will proceed to the next assessment.",
          );
          startBundleSecondAssessment();
          return;
        }

        showResumeDialog(
          email,
          function () {
            startBundleSecondAssessment();
          },
          function () {
            clearBundleProgress();
            state.isSubmitting = false;
            setBtnLoading($startBtn, false);
            hideProgress();
            showScreen("bundle-order");
          },
        );
        return;
      }

      state.isSubmitting = false;
      setBtnLoading($startBtn, false);
      hideProgress();
      showScreen("bundle-order");
      return;
    }

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
    var scaleMax = (q && q.scale_max) || (cfg && cfg.scale_max) || 5;
    var style = (q && q.label_style) || "per_number";
    var html = "";
    var i;

    if (style === "yes_no") {
      var yesLbl = (CA_Config.labels && CA_Config.labels.yes_no_yes) || "Yes";
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

    return {
      html: html,
      scaleMax: scaleMax,
      style: style,
      endpoints: (q && q.endpoints) || {},
    };
  }

  function hasClientQuestionBank() {
    var cfg = getCurrentConfig();
    var b = cfg && cfg.question_bank;
    return Array.isArray(b) && b.length > 0;
  }

  /**
   * Same shape as ca_get_question success data; saved_answer from cache only.
   */
  function buildClientQuestionResponseData(stepIndex, questionIndex) {
    var cfg = getCurrentConfig();
    var bank = (cfg && cfg.question_bank) || [];
    var total =
      (cfg && cfg.total_questions) || bank.length || state.totalQuestions || 0;
    if (
      !bank.length ||
      questionIndex < 0 ||
      questionIndex >= bank.length ||
      !total
    ) {
      return null;
    }
    var q = bank[questionIndex];
    if (!q || typeof q !== "object") {
      return null;
    }
    return {
      question: q,
      saved_answer: null,
      total: total,
      progress: total > 0 ? Math.round((questionIndex / total) * 100) : 0,
      is_last: questionIndex >= total - 1,
      scale_max: q.scale_max,
      assessment_type: state.assessmentType,
    };
  }

  function loadQuestion(stepIndex, options) {
    options = options || {};
    var skipLoading = !!options.skipLoadingScreen;

    var questionIndex =
      state.questionOrder && state.questionOrder.length > 0
        ? state.questionOrder[stepIndex]
        : stepIndex;

    var clientData = buildClientQuestionResponseData(stepIndex, questionIndex);
    if (clientData) {
      renderQuestion(clientData, stepIndex, questionIndex);
      if (skipLoading) {
        state.awaitingQuestionFetch = false;
        state.isSubmitting = false;
        $nextBtn.prop("disabled", false);
        $backBtn.prop("disabled", state.stepIndex === 0);
      }
      return;
    }

    if (!skipLoading) {
      showScreen("loading");
    } else {
      state.awaitingQuestionFetch = true;
      $nextBtn.prop("disabled", true);
      $backBtn.prop("disabled", true);
    }

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
            (response && response.data && typeof response.data === "string"
              ? response.data
              : response.data.message) || CA_Config.labels.error_generic,
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
      })
      .always(function () {
        if (!skipLoading) {
          return;
        }
        state.awaitingQuestionFetch = false;
        state.isSubmitting = false;
        $nextBtn.prop("disabled", false);
        $backBtn.prop("disabled", state.stepIndex === 0);
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

    $backBtn.prop("disabled", state.isAssessmentSubmitting || stepIndex === 0);
    var nextLabel = isLast ? CA_Config.labels.submit : CA_Config.labels.next;
    var $lbl =
      $nextBtnLabel && $nextBtnLabel.length
        ? $nextBtnLabel
        : $nextBtn.find(".ca-next-btn-label");
    if ($lbl.length) {
      $lbl.text(nextLabel);
      $nextBtn.find(".ca-next-btn-chevron").removeAttr("hidden");
    } else {
      $nextBtn.text(nextLabel);
    }

    hideError($questionError);
    showScreen("questions");

    if (defersServerAnswerSave()) {
      persistDraftAnswers();
    }
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

    if (defersServerAnswerSave()) {
      state.unsyncedChanges = true;
      persistDraftAnswers();

      var nextStep = stepIndex + 1;
      var isLast = nextStep >= state.totalQuestions;

      if (isLast) {
        state.isSubmitting = true;
        setAssessmentSubmitting(true);
        $nextBtn.prop("disabled", true);
        showScreen("questions");
        showProgress();
        updateSubmitLoadingProgress(0);
        saveAllAnswersFromCache(
          function () {
            state.unsyncedChanges = false;
            clearDraftAnswers();
            submitAssessment({
              skipShowLoading: true,
              progressAtStart: 65,
              progressAfterSubmit: 82,
              progressPreviewFloor: 90,
            });
          },
          function (err) {
            var msg = CA_Config.labels.error_generic;
            if (err && err.responseText !== undefined) {
              msg = getAjaxErrorMessage(err);
            } else if (err && err.data) {
              msg =
                typeof err.data === "string"
                  ? err.data
                  : err.data.message || msg;
            }
            showError($questionError, msg);
            $nextBtn.prop("disabled", false);
            state.isSubmitting = false;
            hideProgress();
            showScreen("questions");
          },
          function (done, total) {
            updateSubmitLoadingProgress(
              total <= 0 ? 2 : Math.round((done / total) * 62),
            );
          },
        );
        return;
      }

      state.stepIndex = nextStep;
      loadQuestion(nextStep, { skipLoadingScreen: true });
      return;
    }

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
          var nextStepDe = stepIndex + 1;
          var isLastDe = nextStepDe >= state.totalQuestions;

          if (isLastDe) {
            submitAssessment();
          } else {
            state.stepIndex = nextStepDe;
            loadQuestion(nextStepDe, { skipLoadingScreen: true });
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
        if (state.awaitingQuestionFetch) {
          return;
        }
        $nextBtn.prop("disabled", false);
        state.isSubmitting = false;
      });
  }

  function handleBack() {
    if (state.isAssessmentSubmitting) return;
    if (state.stepIndex <= 0) return;
    state.stepIndex--;
    loadQuestion(state.stepIndex, { skipLoadingScreen: true });
  }

  function submitAssessment(options) {
    options = options || {};
    setAssessmentSubmitting(true);
    if (!options.skipShowLoading) {
      showProgress();
    }
    var p0 =
      typeof options.progressAtStart === "number"
        ? options.progressAtStart
        : 12;
    updateSubmitLoadingProgress(p0);

    var data = withAssessment({
      action: "ca_submit_assessment",
      nonce: CA_Config.nonce,
      submission_id: state.submissionId,
    });
    if (state.bundleMode) {
      data.bundle_mode = 1;
      data.bundle_stage = state.bundleStage;
      data.bundle_inner_submission_id =
        state.bundleSubmissionIds["inner_dimensions"] || 0;
      data.bundle_social_submission_id =
        state.bundleSubmissionIds["social_fluency"] || 0;
    }

    var pAfter =
      typeof options.progressAfterSubmit === "number"
        ? options.progressAfterSubmit
        : 52;
    var previewFloor =
      typeof options.progressPreviewFloor === "number"
        ? options.progressPreviewFloor
        : Math.min(92, pAfter + 10);

    caPost(data)
      .done(function (response) {
        if (response.success) {
          updateSubmitLoadingProgress(pAfter);
          clearSavedSession();
          loadResultsPreview(null, previewFloor);
        } else {
          alert(
            (response && response.data && typeof response.data === "string"
              ? response.data
              : response.data.message) || CA_Config.labels.error_generic,
          );
          hideProgress();
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
        hideProgress();
        showScreen("questions");
      })
      .always(function () {
        state.isSubmitting = false;
        $nextBtn.prop("disabled", false);
      });
  }

  function loadResultsPreview(onComplete, startPct) {
    var sp = typeof startPct === "number" ? startPct : 78;
    updateSubmitLoadingProgress(sp);

    var data = withAssessment({
      action: "ca_get_results_preview",
      nonce: CA_Config.nonce,
      submission_id: state.submissionId,
    });

    caPost(data)
      .done(function (response) {
        if (response.success) {
          updateSubmitLoadingProgress(100);
          renderResults(response.data);
          if (typeof onComplete === "function") {
            onComplete();
          }
        } else {
          alert(
            (response && response.data && typeof response.data === "string"
              ? response.data
              : response.data.message) || CA_Config.labels.error_generic,
          );
          hideProgress();
          showScreen("questions");
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
        hideProgress();
        showScreen("questions");
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
    var requiresPaidDownload =
      data.assessment_type === "inner_dimensions" ||
      data.assessment_type === "social_fluency" ||
      data.assessment_type === "mindset";

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

    var Pack = {};
    if (data.assessment_type === "inner_dimensions") {
      Pack = CA_Config.inner_results || {};
    } else if (data.assessment_type === "social_fluency") {
      Pack = CA_Config.social_results || {};
    } else {
      Pack = CA_Config.mindset_results || {};
    }
    var isBundle = !!state.bundleMode;
    var paidTop = "";
    var bundleOfferBlock = "";
    if (requiresPaidDownload) {
      var emailEsc = escHtml(user.email);
      var quoteHtml = "";
      var previewHtml = "";
      if (data.assessment_type === "inner_dimensions") {
        var topCat = null;
        if (Array.isArray(cats) && cats.length) {
          topCat = cats
            .slice()
            .sort(function (a, b) {
              return (parseFloat(b.average) || 0) - (parseFloat(a.average) || 0);
            })[0];
        }
        var topTraitText = topCat
          ? 'Top attribute surfaced: <strong>' +
            escHtml(topCat.name) +
            "</strong>."
          : "Top attribute surfaced: <strong>Your strongest natural attribute</strong>.";
        var patternText = topCat
          ? "Pattern revealed: " + escHtml(topCat.summary || "")
          : "Pattern revealed: You respond most strongly where your natural strengths are already active.";
        previewHtml =
          '<div class="ca-results-free-preview">' +
          '<p class="ca-results-free-preview-copy">' +
          escHtml(
            Pack.preview_intro ||
              "See your preview. A snapshot of what the assessment surfaced.",
          ) +
          "</p>" +
          '<ul class="ca-results-free-preview-list">' +
          "<li>" +
          topTraitText +
          "</li>" +
          "<li>" +
          patternText +
          "</li>" +
          "</ul>" +
          '<p class="ca-results-free-preview-note">' +
          escHtml(
            Pack.preview_note ||
              "This is meaningful but incomplete. Unlock the full report for your complete breakdown and all responses.",
          ) +
          "</p>" +
          "</div>";
        quoteHtml =
          '<p class="ca-results-nac-quote">&ldquo;' +
          escHtml(
            Pack.tagline ||
              "Remember Who You Were Before the World Told You Who to Be.",
          ) +
          "&rdquo;</p>";
      } else if (data.assessment_type === "social_fluency") {
        var topDomain = null;
        if (Array.isArray(cats) && cats.length) {
          topDomain = cats
            .slice()
            .sort(function (a, b) {
              return (parseFloat(b.average) || 0) - (parseFloat(a.average) || 0);
            })[0];
        }

        var tierText = profile ? String(profile) : "Your tier";
        var domainName = topDomain ? topDomain.name : "your strongest domain";
        var domainSummary = topDomain
          ? (topDomain.summary || "")
          : "This is where your social strengths show up most clearly.";

        var socialIntro = escHtml(
          Pack.preview_intro ||
            "Your overall Social Fluency tier and one domain to notice. Free.",
        );

        previewHtml =
          '<div class="ca-results-free-preview">' +
          '<p class="ca-results-free-preview-copy">' +
          socialIntro +
          "</p>" +
          '<ul class="ca-results-free-preview-list">' +
          "<li>" +
          "Overall tier: <strong>" +
          escHtml(tierText) +
          "</strong></li>" +
          "<li>" +
          "Domain to notice: <strong>" +
          escHtml(domainName) +
          "</strong> — " +
          escHtml(domainSummary) +
          "</li>" +
          "</ul>" +
          '<p class="ca-results-free-preview-note">' +
          escHtml(
            Pack.preview_note ||
              "This is meaningful but incomplete. Unlock the full report to explore your complete breakdown and every response.",
          ) +
          "</p>" +
          "</div>";

        quoteHtml = Pack.tagline
          ? '<p class="ca-results-nac-quote">' +
            escHtml(Pack.tagline) +
            "</p>"
          : "";
      } else if (Pack.tagline) {
        quoteHtml =
          '<p class="ca-results-nac-quote">' +
          escHtml(Pack.tagline) +
          "</p>";
      }
      paidTop =
        '<div class="ca-results-nac-completion">' +
        '<h1 class="ca-results-nac-title">' +
        escHtml(
          Pack.title ||
            (data.assessment_type === "social_fluency"
              ? "Social Fluency Assessment"
              : data.assessment_type === "mindset"
                ? "Entrepreneurial Mindset Assessment"
                : "Natural Attributes Cataloging"),
        ) +
        "</h1>" +
        quoteHtml +
        '<h2 class="ca-results-nac-subtitle">' +
        escHtml(
          Pack.congrats ||
            "Congratulations on completing your assessment!",
        ) +
        "</h2>" +
        '<p class="ca-results-nac-email">' +
        escHtml(
          Pack.email_lead || "Your full report will be sent after payment to",
        ) +
        " <strong>" +
        emailEsc +
        "</strong>.</p>" +
        '<p class="ca-results-nac-intro">' +
        escHtml(
          Pack.intro ||
            "Complete checkout to unlock and download your full PDF report.",
        ) +
        "</p>" +
        previewHtml +
        "</div>";

      if (
        !isBundle &&
        (data.assessment_type === "inner_dimensions" ||
          data.assessment_type === "social_fluency")
      ) {
        var bundlePack = CA_Config.bundle_results || {};
        var bundleTitle =
          bundlePack.title || "Bundle Option — Both Assessments Unlocked";
        var bundleHeadline =
          bundlePack.headline || "Your constants and your roots — the full picture.";
        var bundlePriceLine =
          bundlePack.price_line ||
          "Price: $29 USD (saves $9)";
        var bundleBody =
          bundlePack.body ||
          "Take both assessments free. Unlock both personalized reports together for $29 — less than each one separately.";
        var bundleStartCta =
          data.assessment_type === "social_fluency"
            ? "Take Natural Attributes Cataloging assessment"
            : "Take Social Fluency Assessment";

        bundleOfferBlock =
          '<div class="ca-results-bundle-offer">' +
          '<p class="ca-results-bundle-offer-title">' +
          escHtml(bundleTitle) +
          "</p>" +
          '<button type="button" class="ca-btn ca-btn--primary ca-results-bundle-start-btn" id="ca-results-bundle-start">' +
          escHtml(bundleStartCta) +
          "</button>" +
          '<p class="ca-results-bundle-offer-headline">' +
          escHtml(bundleHeadline) +
          "</p>" +
          '<p class="ca-results-bundle-offer-price">' +
          escHtml(bundlePriceLine) +
          "</p>" +
          '<p class="ca-results-bundle-offer-copy">' +
          escHtml(bundleBody) +
          "</p>" +
          "</div>";
      }
    }

    var initialCheckoutUrl =
      state.checkoutUrl || CA_Config.checkout_url || "/checkout/";
    var showPaywallOverlay = requiresPaidDownload;
    if (isBundle) {
      // In the bundle flow, first assessment is free-preview only.
      showPaywallOverlay = state.bundleStage === 1;
    }

    var paywallCardHtml = "";
    if (showPaywallOverlay) {
      if (isBundle && state.bundleStage === 1) {
        var bundleCta =
          CA_Config.bundle_results && CA_Config.bundle_results.cta
            ? CA_Config.bundle_results.cta
            : "Unlock both reports — $29 →";
        paywallCardHtml =
          '<div class="ca-results-paywall-card">' +
          '<a href="' +
          escHtml(initialCheckoutUrl) +
          '" class="ca-btn ca-btn--primary ca-results-paywall-btn">&#128722; ' +
          escHtml(bundleCta) +
          "</a></div>";
      } else {
        paywallCardHtml =
          '<div class="ca-results-paywall-card">' +
          '<a href="' +
          escHtml(initialCheckoutUrl) +
          '" class="ca-btn ca-btn--primary ca-results-paywall-btn">&#128722; Get the Full Result</a></div>';
      }
    }

    var ctaBlock = "";
    if (isBundle && state.bundleStage === 0) {
      var nextLabel =
        state.bundleSecondType === "social_fluency"
          ? "your Social Fluency assessment"
          : "your Natural Attributes Cataloging assessment";
      ctaBlock =
        '<div class="ca-results-cta">' +
        '<button type="button" class="ca-btn ca-btn--primary" id="ca-bundle-continue">Continue to ' +
        escHtml(nextLabel) +
        "</button>" +
        '<button type="button" class="ca-btn ca-btn--ghost" id="ca-close-results" style="margin-top:10px;">Close</button>' +
        "</div>";
    } else if (showPaywallOverlay) {
      ctaBlock =
        '<div class="ca-results-cta">' +
        '<button type="button" class="ca-btn ca-btn--ghost" id="ca-close-results">Close</button>' +
        "</div>";
    } else {
      ctaBlock =
        '<div class="ca-results-cta">' +
        "<p>Your results have been saved. A copy may be shared with you by email.</p>" +
        '<button type="button" class="ca-btn ca-btn--ghost" id="ca-close-results">Close</button>' +
        "</div>";
    }

    var heroHtml =
      '<div class="ca-results-hero' +
      (showPaywallOverlay ? " ca-results-preview-blocked" : "") +
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
      "</div>";

    var bodyHtml =
      '<div class="ca-results-body' +
      (showPaywallOverlay ? " ca-results-preview-blocked" : "") +
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
      "</div>";

    var html = "";
    if (isBundle && state.bundleStage === 0) {
      // Bundle first-assessment screen: show teaser only, not the full detailed card stack.
      html =
        paidTop +
        '<div class="ca-results-body">' +
        '<div class="ca-results-cta">' +
        ctaBlock.replace(/^<div class="ca-results-cta">/, "").replace(/<\/div>$/, "") +
        "</div>" +
        "</div>";
    } else {
      html = requiresPaidDownload
        ? paidTop +
          '<div class="ca-results-preview-wrap">' +
          '<div class="ca-results-preview-obscured">' +
          heroHtml +
          bodyHtml +
          (paywallCardHtml
            ? '<div class="ca-results-paywall-overlay" role="presentation">' +
              '<div class="ca-results-paywall-stack">' +
              paywallCardHtml +
              (bundleOfferBlock ? bundleOfferBlock : "") +
              "</div></div>"
            : "") +
          "</div>" +
          (paywallCardHtml && bundleOfferBlock ? "" : bundleOfferBlock) +
          "</div>"
        : paidTop + bundleOfferBlock + heroHtml + bodyHtml;
    }

    $resultsContent.html(html);
    hideProgress();
    showScreen("results");

    if (showPaywallOverlay) {
      if (isBundle) {
        prepareBundleCheckout(false);
      } else {
        preparePaidFullResultsCheckout(false);
      }
    }

    setTimeout(function () {
      $resultsContent.find(".ca-cat-bar-fill").each(function () {
        var $bar = $(this);
        $bar.css("width", $bar.data("width"));
      });
    }, 100);

    $resultsContent
      .off("click.caCloseResults")
      .on("click.caCloseResults", "#ca-close-results", attemptCloseModal);

    if (isBundle) {
      $resultsContent
        .off("click.caBundleContinue")
        .on("click.caBundleContinue", "#ca-bundle-continue", function () {
          startBundleSecondAssessment();
        });
    }

    $resultsContent
      .off("click.caBundleStart")
      .on("click.caBundleStart", "#ca-results-bundle-start", function () {
        if (state.isSubmitting || state.bundleMode) {
          return;
        }
        state.bundleMode = true;
        state.bundleStage = 0;
        state.bundleFirstType = data.assessment_type;
        state.bundleSecondType =
          data.assessment_type === "inner_dimensions"
            ? "social_fluency"
            : "inner_dimensions";
        state.bundleSubmissionIds = {};
        state.bundleSubmissionIds[state.bundleFirstType] = state.submissionId;
        saveBundleProgress();
        startBundleSecondAssessment();
      });
  }

  function handlePaywallCheckout(e) {
    e.preventDefault();

    if (!state.submissionId && !(state.bundleMode && state.bundleStage === 1)) {
      return;
    }
    if (state.isSubmitting) {
      return;
    }

    if (state.checkoutUrl) {
      window.location.href = state.checkoutUrl;
      return;
    }

    if (state.bundleMode && state.bundleStage === 1) {
      prepareBundleCheckout(true, e.currentTarget);
    } else {
      preparePaidFullResultsCheckout(true, e.currentTarget);
    }
  }

  function prepareBundleCheckout(redirectAfterPrepare, buttonEl) {
    if (state.isSubmitting) {
      return;
    }

    var $btn = buttonEl ? $(buttonEl) : $();
    state.isSubmitting = true;
    if ($btn.length) {
      setBtnLoading($btn, true);
    }

    var innerId = state.bundleSubmissionIds["inner_dimensions"];
    var socialId = state.bundleSubmissionIds["social_fluency"];
    if (!innerId || !socialId) {
      state.isSubmitting = false;
      if ($btn.length) {
        setBtnLoading($btn, false);
      }
      alert(CA_Config.labels.error_generic);
      return;
    }

    caPost(
      withAssessment({
        action: "ca_prepare_bundle_checkout",
        nonce: CA_Config.nonce,
        inner_submission_id: innerId,
        social_submission_id: socialId,
      }),
    )
      .done(function (response) {
        if (response && response.success && response.data) {
          state.checkoutUrl =
            response.data.checkout_url ||
            CA_Config.checkout_url ||
            "/checkout/";
          $resultsContent
            .find(".ca-results-paywall-btn")
            .attr("href", state.checkoutUrl);
          if (redirectAfterPrepare) {
            window.location.href = state.checkoutUrl;
          }
          return;
        }

        if (redirectAfterPrepare) {
          alert(
            (response &&
              response.data &&
              (response.data.message || response.data)) ||
              CA_Config.labels.error_generic,
          );
        }
      })
      .fail(function (xhr, textStatus, errorThrown) {
        console.error("CA AJAX ca_prepare_bundle_checkout failed:", {
          textStatus: textStatus,
          errorThrown: errorThrown,
          status: xhr && xhr.status ? xhr.status : null,
          responseText:
            xhr && xhr.responseText ? xhr.responseText.slice(0, 500) : null,
        });
        if (redirectAfterPrepare) {
          alert(getAjaxErrorMessage(xhr));
        }
      })
      .always(function () {
        if ($btn.length) {
          setBtnLoading($btn, false);
        }
        state.isSubmitting = false;
      });
  }

  function startBundleSecondAssessment() {
    if (!state.bundleMode || state.bundleStage !== 0) {
      // Only allow continue from the first assessment.
      return;
    }
    if (!state.bundleSecondType) {
      alert(CA_Config.labels.error_generic);
      return;
    }

    state.bundleStage = 1;
    state.assessmentType = state.bundleSecondType;

    var cfg = getCurrentConfig();
    if ($modalTitle && $modalTitle.length) {
      $modalTitle.text(cfg.modal_title || "Assessment");
    }

    resetState({ keepInfoFields: true });
    hideError($infoError);
    showProgress();

    state.isSubmitting = true;
    setBtnLoading($startBtn, true);
    saveUserInfo();
  }

  function preparePaidFullResultsCheckout(redirectAfterPrepare, buttonEl) {
    if (!state.submissionId || state.isSubmitting) {
      return;
    }

    var $btn = buttonEl ? $(buttonEl) : $();
    state.isSubmitting = true;
    if ($btn.length) {
      setBtnLoading($btn, true);
    }

    caPost(
      withAssessment({
        action: "ca_prepare_paid_full_results_checkout",
        nonce: CA_Config.nonce,
        submission_id: state.submissionId,
      }),
    )
      .done(function (response) {
        if (response && response.success && response.data) {
          state.checkoutUrl =
            response.data.checkout_url ||
            CA_Config.checkout_url ||
            "/checkout/";
          $resultsContent
            .find(".ca-results-paywall-btn")
            .attr("href", state.checkoutUrl);
          if (redirectAfterPrepare) {
            window.location.href = state.checkoutUrl;
          }
          return;
        }

        if (redirectAfterPrepare) {
          alert(
            (response &&
              response.data &&
              (response.data.message || response.data)) ||
              CA_Config.labels.error_generic,
          );
        }
      })
      .fail(function (xhr, textStatus, errorThrown) {
        console.error("CA AJAX ca_prepare_paid_full_results_checkout failed:", {
          textStatus: textStatus,
          errorThrown: errorThrown,
          status: xhr && xhr.status ? xhr.status : null,
          responseText:
            xhr && xhr.responseText ? xhr.responseText.slice(0, 500) : null,
        });
        if (redirectAfterPrepare) {
          alert(getAjaxErrorMessage(xhr));
        }
      })
      .always(function () {
        if ($btn.length) {
          setBtnLoading($btn, false);
        }
        state.isSubmitting = false;
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
      return xhr.status
        ? "Request failed (HTTP " + xhr.status + ")."
        : fallback;
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
