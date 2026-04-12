/**
 * Admin scripts for Custom Assessment Plugin
 */

jQuery(document).ready(function ($) {
  // Export dropdown menu toggle (delegated so it still works after search re-renders rows)
  $(document).on("click", ".ca-export-dropdown-btn", function (e) {
    e.preventDefault();
    e.stopPropagation();

    var submissionId = $(this).data("id");
    var menu = $("#export-" + submissionId);

    // Close other open menus
    $(".ca-export-dropdown").not(menu).removeClass("show");

    // Toggle current menu
    menu.toggleClass("show");
  });

  // Close menu when clicking outside
  $(document).on("click", function (e) {
    if (
      !$(e.target).closest(".ca-export-dropdown-btn").length &&
      !$(e.target).closest(".ca-export-dropdown").length
    ) {
      $(".ca-export-dropdown").removeClass("show");
    }
  });

  // Close menu when clicking an option (delegated)
  $(document).on("click", ".ca-export-option", function () {
    $(this).closest(".ca-export-dropdown").removeClass("show");
  });

  // Category inline editing functionality
  $(".ca-edit-btn").on("click", function () {
    var $row = $(this).closest("tr");
    var index = $(this).data("index");
    var categoryName = $(this).data("category");
    var $nameElement = $row.find(".ca-category-name");
    var $inputElement = $row.find(".ca-category-input");
    var $editBtn = $row.find(".ca-edit-btn");
    var $saveBtn = $row.find(".ca-save-btn");
    var $cancelBtn = $row.find(".ca-category-cancel-btn");

    // Switch to edit mode
    $nameElement.hide();
    $inputElement.show().focus();
    $editBtn.hide();
    $saveBtn.show();
    $cancelBtn.show();
  });

  $(".ca-save-btn").on("click", function () {
    var $row = $(this).closest("tr");
    var index = $(this).data("index");
    var originalCategory = $(this).data("category");
    var $nameElement = $row.find(".ca-category-name");
    var $inputElement = $row.find(".ca-category-input");
    var $editBtn = $row.find(".ca-edit-btn");
    var $saveBtn = $row.find(".ca-save-btn");
    var $cancelBtn = $row.find(".ca-category-cancel-btn");
    var newCategory = $inputElement.val().trim();

    // Validation
    if (newCategory === "") {
      alert("Category name cannot be empty.");
      $inputElement.focus();
      return;
    }

    if (newCategory === originalCategory) {
      // No changes, just revert to view mode
      cancelEditMode($row, $nameElement, $inputElement, $editBtn, $saveBtn);
      return;
    }

    // Confirm edit
    if (
      !confirm(
        'Are you sure you want to rename "' +
          originalCategory +
          '" to "' +
          newCategory +
          '"?',
      )
    ) {
      cancelEditMode($row, $nameElement, $inputElement, $editBtn, $saveBtn);
      return;
    }

    // Create form and submit
    var form = $("<form>");
    form.attr("method", "post");
    form.attr("action", "");

    var nonceField = $("<input>");
    nonceField.attr("type", "hidden");
    nonceField.attr("name", "_wpnonce");
    nonceField.attr("value", ca_admin_data.nonce); // This should be passed from PHP

    var actionField = $("<input>");
    actionField.attr("type", "hidden");
    actionField.attr("name", "ca_action");
    actionField.attr("value", "edit_category");

    var oldCategoryField = $("<input>");
    oldCategoryField.attr("type", "hidden");
    oldCategoryField.attr("name", "old_category_name");
    oldCategoryField.attr("value", originalCategory);

    var newCategoryField = $("<input>");
    newCategoryField.attr("type", "hidden");
    newCategoryField.attr("name", "new_category_name");
    newCategoryField.attr("value", newCategory);

    form.append(nonceField);
    form.append(actionField);
    form.append(oldCategoryField);
    form.append(newCategoryField);
    form.appendTo("body").submit();
  });

  // Cancel edit on Enter key (save) or Escape key (cancel)
  $(".ca-category-input").on("keydown", function (e) {
    var $row = $(this).closest("tr");
    var $nameElement = $row.find(".ca-category-name");
    var $inputElement = $row.find(".ca-category-input");
    var $editBtn = $row.find(".ca-edit-btn");
    var $saveBtn = $row.find(".ca-save-btn");

    if (e.key === "Enter") {
      e.preventDefault();
      $saveBtn.click();
    } else if (e.key === "Escape") {
      e.preventDefault();
      cancelEditMode($row, $nameElement, $inputElement, $editBtn, $saveBtn);
    }
  });

  // Cancel button in edit mode
  $(document).on("click", ".ca-category-cancel-btn", function (e) {
    e.preventDefault();
    var $row = $(this).closest("tr");
    var $nameElement = $row.find(".ca-category-name");
    var $inputElement = $row.find(".ca-category-input");
    var $editBtn = $row.find(".ca-edit-btn");
    var $saveBtn = $row.find(".ca-save-btn");
    cancelEditMode($row, $nameElement, $inputElement, $editBtn, $saveBtn);
  });

  // Cancel edit when clicking outside the input (optional)
  $(document).on("click", function (e) {
    if (
      !$(e.target).closest(".ca-category-input").length &&
      !$(e.target).closest(".ca-edit-btn").length &&
      !$(e.target).closest(".ca-save-btn").length &&
      !$(e.target).closest(".ca-category-cancel-btn").length
    ) {
      $(".ca-category-input").each(function () {
        var $input = $(this);
        if ($input.is(":visible")) {
          var $row = $input.closest("tr");
          var $nameElement = $row.find(".ca-category-name");
          var $editBtn = $row.find(".ca-edit-btn");
          var $saveBtn = $row.find(".ca-save-btn");
          cancelEditMode($row, $nameElement, $input, $editBtn, $saveBtn);
        }
      });
    }
  });

  function cancelEditMode(
    $row,
    $nameElement,
    $inputElement,
    $editBtn,
    $saveBtn,
  ) {
    var $cancelBtn = $row.find(".ca-category-cancel-btn");
    $inputElement.hide();
    $nameElement.show();
    $editBtn.show();
    $saveBtn.hide();
    $cancelBtn.hide();
    $inputElement.val($inputElement.data("original"));
  }

  // Universal search functionality for all admin listings
  function initUniversalSearch() {
    var searchTimeout;
    var originalData = [];
    var allDataLoaded = false;
    var latestSearchTerm = "";
    var $tableBody = $(".ca-admin-table tbody");
    // Cache the initial (server-rendered) paginated rows so clearing search restores pagination.
    var $defaultPageRows = $tableBody.find("tr").clone();
    var $searchCount = $(".ca-search-count");
    var $searchResultsCount = $("#ca-search-results-count");
    var $paginationControls = $(".tablenav.top, .tablenav.bottom");
    var currentPageType = getCurrentPageType();

    // Keep a reference to the default paginated rows so inline edits remain when clearing search.
    if (currentPageType === "questions") {
      window.CA_ADMIN_QUESTIONS_DEFAULT_PAGE_ROWS = $defaultPageRows;
    }

    function buildQuestionRow(item) {
      // Build a <tr> that matches the PHP-rendered layout for the Questions page.
      // Used when the data source doesn't provide a cloned DOM row.
      var questionIndex = item.question_index != null ? parseInt(item.question_index, 10) : 0;
      var editFormId = "ca-edit-question-form-" + questionIndex;
      var categoriesList = Array.isArray(window.CA_ADMIN_QUESTIONS_CATEGORIES)
        ? window.CA_ADMIN_QUESTIONS_CATEGORIES
        : [];

      var $tr = $("<tr>");
      var $idTd = $("<td>").addClass("ca-col-id");
      var $idCheckbox = $("<input>")
        .attr("type", "checkbox")
        .addClass("ca-question-select")
        .val(questionIndex);
      $idTd.append($idCheckbox);
      $idTd.append((questionIndex || 0) + 1);
      $tr.append($idTd);

      // Category cell (span + hidden dropdown)
      var $categoryTd = $("<td>").addClass("ca-col-category");
      var categoryVal = item.category != null ? item.category : "";
      var $categorySpan = $("<span>")
        .addClass("ca-question-category-text")
        .attr("data-original", categoryVal)
        .text(categoryVal);

      var $categorySelect = $("<select>")
        .addClass("ca-question-category-select")
        .css("display", "none")
        .attr("form", editFormId)
        .attr("name", "new_category")
        .attr("data-original", categoryVal);

      categoriesList.forEach(function (cat) {
        var $opt = $("<option>").attr("value", cat).text(cat);
        if (cat === categoryVal) {
          $opt.prop("selected", true);
        }
        $categorySelect.append($opt);
      });

      $categoryTd.append($categorySpan, $categorySelect);
      $tr.append($categoryTd);

      // Priority cell (static)
      var $priorityTd = $("<td>").addClass("ca-col-priority");
      var priorityVal = item.priority != null ? parseInt(item.priority, 10) : 0;

      var $prioritySpan = $("<span>")
        .addClass("ca-question-priority-text")
        .attr("data-original", priorityVal)
        .text(priorityVal || "");

      var $priorityInput = $("<input>")
        .addClass("ca-question-priority-input")
        .css("display", "none")
        .attr("type", "number")
        .attr("form", editFormId)
        .attr("name", "new_priority")
        .attr("data-original", priorityVal)
        .attr("min", 1)
        .attr("step", 1)
        .attr("autocomplete", "off")
        .val(priorityVal);

      $priorityTd.append($prioritySpan, $priorityInput);
      $tr.append($priorityTd);

      // Question cell (span + hidden input)
      var $questionTd = $("<td>").addClass("ca-col-question");
      var questionVal = item.question != null ? item.question : "";
      var $questionSpan = $("<span>")
        .addClass("ca-question-text-display")
        .attr("data-original", questionVal)
        .text(questionVal);

      var $questionInput = $("<input>")
        .addClass("ca-question-text-input")
        .css("display", "none")
        .attr("type", "text")
        .attr("form", editFormId)
        .attr("name", "new_question_text")
        .attr("maxlength", "500")
        .attr("autocomplete", "off")
        .attr("data-original", questionVal)
        .val(questionVal);

      $questionTd.append($questionSpan, $questionInput);
      $tr.append($questionTd);

      var confirmText =
        window.CA_ADMIN_QUESTIONS_DELETE_CONFIRM || "Are you sure you want to delete this question?";

      var nonceVal = window.CA_ADMIN_QUESTIONS_DELETE_NONCE || "";

      var $actionsTd = $("<td>").addClass("ca-col-actions");

      // Edit form (used by Save button submission)
      var $editForm = $("<form>")
        .attr("method", "post")
        .attr("action", "")
        .attr("id", editFormId)
        .attr("class", "ca-question-edit-form")
        .attr("style", "display: inline;");

      var editNonceVal = window.CA_ADMIN_QUESTIONS_EDIT_NONCE || "";
      var $editNonceField = $("<input>")
        .attr("type", "hidden")
        .attr("name", "_wpnonce")
        .val(editNonceVal);

      var $editActionField = $("<input>")
        .attr("type", "hidden")
        .attr("name", "ca_action")
        .val("edit_question");

      var $editIndexField = $("<input>")
        .attr("type", "hidden")
        .attr("name", "question_index")
        .val(questionIndex);

      var $editBtn = $("<button>")
        .attr("type", "button")
        .addClass("button button-small button-secondary ca-question-edit-btn")
        .attr("data-index", questionIndex)
        .text("Edit");

      var $cancelBtn = $("<button>")
        .attr("type", "button")
        .addClass("button button-small button-secondary ca-question-cancel-btn")
        .css("display", "none")
        .text("Cancel");

      var $saveBtn = $("<button>")
        .attr("type", "submit")
        .addClass("button button-small button-primary ca-question-save-btn")
        .css("display", "none")
        .text("Save");

      $editForm.append($editNonceField, $editActionField, $editIndexField, $editBtn, $cancelBtn, $saveBtn);
      $actionsTd.append($editForm);

      // Delete form
      var $deleteForm = $("<form>")
        .attr("method", "post")
        .attr("style", "display: inline;")
        .attr(
          "onsubmit",
          "return confirm(" + JSON.stringify(confirmText) + ");"
        );

      var $deleteNonceField = $("<input>")
        .attr("type", "hidden")
        .attr("name", "_wpnonce")
        .val(nonceVal);

      var $deleteActionField = $("<input>")
        .attr("type", "hidden")
        .attr("name", "ca_action")
        .val("delete_question");

      var $deleteIndexField = $("<input>")
        .attr("type", "hidden")
        .attr("name", "question_index")
        .val(questionIndex);

      var $deleteBtn = $("<button>")
        .attr("type", "submit")
        .addClass("button button-small button-secondary")
        .text("Delete");

      $deleteForm.append(
        $deleteNonceField,
        $deleteActionField,
        $deleteIndexField,
        $deleteBtn
      );
      $actionsTd.append($deleteForm);

      $tr.append($actionsTd);

      return $tr;
    }

    function appendItemRow(item) {
      if (item && item.row) {
        $tableBody.append(item.row);
        return;
      }

      if (currentPageType === "questions") {
        $tableBody.append(buildQuestionRow(item));
      }
    }

    // Determine what type of page we're on
    function getCurrentPageType() {
      var href = window.location.href;
      if (
        href.includes("custom-assessment-questions") ||
        (href.includes("custom-assessment-mindset") &&
          href.includes("ca_tab=questions")) ||
        (href.includes("custom-assessment-inner") &&
          href.includes("ca_tab=questions"))
      ) {
        return "questions";
      }
      if (
        href.includes("custom-assessment-categories") ||
        (href.includes("custom-assessment-mindset") &&
          href.includes("ca_tab=categories")) ||
        (href.includes("custom-assessment-inner") &&
          href.includes("ca_tab=categories"))
      ) {
        return "categories";
      }
      if (
        href.includes("custom-assessment-sf-submissions") ||
        href.includes("custom-assessment-submissions-all") ||
        href.includes("custom-assessment-submissions") ||
        (href.includes("custom-assessment-mindset") &&
          href.includes("ca_tab=submissions")) ||
        (href.includes("custom-assessment-social") &&
          href.includes("ca_tab=submissions")) ||
        (href.includes("custom-assessment-inner") &&
          href.includes("ca_tab=submissions"))
      ) {
        return "submissions";
      }
      return "unknown";
    }

    // Store original data from all pages based on page type
    function loadAllData() {
      if (allDataLoaded || !currentPageType) {
        return;
      }

      // Questions page: use the full list provided by PHP so search is truly global.
      if (
        currentPageType === "questions" &&
        Array.isArray(window.CA_ADMIN_QUESTIONS_ALL) &&
        window.CA_ADMIN_QUESTIONS_ALL.length > 0
      ) {
        originalData = window.CA_ADMIN_QUESTIONS_ALL;
        allDataLoaded = true;
        return;
      }

      originalData = [];

      // Get data from current page based on page type
      $tableBody.find("tr").each(function () {
        var $row = $(this);
        var rowData;

        if (currentPageType === "questions") {
          rowData = {
            row: $row.clone(),
            number: $row.find(".ca-col-id").text().trim(),
            category: $row.find("td:nth-child(2)").text().trim(),
            priority: $row.find(".ca-col-priority").text().trim(),
            question: $row.find("td:nth-child(4)").text().trim(),
          };
        } else if (currentPageType === "categories") {
          rowData = {
            row: $row.clone(),
            number: $row.find(".ca-col-id").text().trim(),
            category: $row.find("td:nth-child(2)").text().trim(),
            count: $row.find("td:nth-child(3)").text().trim(),
          };
        } else if (currentPageType === "submissions") {
          var scoreParts = [];
          $row.find(".ca-col-score").each(function () {
            scoreParts.push($(this).text().trim());
          });
          rowData = {
            row: $row.clone(),
            id: $row.find(".ca-col-id").first().text().trim(),
            name: $row.find(".ca-sub-name").text().trim(),
            email: $row.find(".ca-sub-email").text().trim(),
            phone: $row.find(".ca-sub-phone").text().trim(),
            jobTitle: $row.find(".ca-sub-job").text().trim(),
            assessment: $row.find(".ca-sub-assessment").text().trim(),
            score: scoreParts.join(" "),
            status: $row.find(".ca-col-status").text().trim(),
          };
        }

        if (rowData) {
          originalData.push(rowData);
        }
      });

      // Check if we have pagination
      var totalPages = 0;
      var currentPage = 1;

      // Extract pagination info
      var $pageNumbers = $paginationControls.find(".page-numbers");
      if ($pageNumbers.length > 0) {
        $pageNumbers.each(function () {
          var pageNum = parseInt($(this).text());
          if (!isNaN(pageNum) && pageNum > totalPages) {
            totalPages = pageNum;
          }
        });

        // Get current page
        var $currentPage = $paginationControls.find(".page-numbers.current");
        if ($currentPage.length > 0) {
          currentPage = parseInt($currentPage.text());
        }
      }

      // If we have multiple pages, fetch all data via AJAX
      if (totalPages > 1) {
        fetchAllDataPages(totalPages, currentPage);
      } else {
        allDataLoaded = true;
      }
    }

    function fetchAllDataPages(totalPages, currentPage) {
      var requests = [];
      var fetchedData = []; // Store fetched data temporarily to avoid duplicates

      // Create AJAX requests for all pages except current (already loaded)
      for (var i = 1; i <= totalPages; i++) {
        if (i !== currentPage) {
          var deferred = $.Deferred();
          requests.push(deferred.promise());

          // Build a deterministic URL so the fetched page number is always `i`.
          // (Some browsers/edge cases can keep the original `paged` query param.)
          var ajaxUrl = window.location.href;
          try {
            var urlObj = new URL(window.location.href);
            urlObj.searchParams.set("paged", i);
            ajaxUrl = urlObj.toString();
          } catch (e) {
            // Fallback for older environments.
            if (ajaxUrl.match(/([?&])paged=\d+/i)) {
              ajaxUrl = ajaxUrl.replace(
                /([?&])paged=\d+/i,
                "$1paged=" + encodeURIComponent(i)
              );
            } else {
              ajaxUrl +=
                (ajaxUrl.indexOf("?") > -1 ? "&" : "?") +
                "paged=" +
                encodeURIComponent(i);
            }
          }

          $.ajax({
            url: ajaxUrl,
            method: "GET",
            success: function (response) {
              // Parse the response to extract data from other pages
              var tempDiv = document.createElement("div");
              tempDiv.innerHTML = response;

              var $otherPageRows = $(tempDiv).find(".ca-admin-table tbody tr");
              $otherPageRows.each(function () {
                var $row = $(this);
                var rowData;

                if (currentPageType === "questions") {
                  rowData = {
                    row: $row.clone(),
                    number: $row.find(".ca-col-id").text().trim(),
                    category: $row.find("td:nth-child(2)").text().trim(),
                    priority: $row.find(".ca-col-priority").text().trim(),
                    question: $row.find("td:nth-child(4)").text().trim(),
                  };
                } else if (currentPageType === "categories") {
                  rowData = {
                    row: $row.clone(),
                    number: $row.find(".ca-col-id").text().trim(),
                    category: $row.find("td:nth-child(2)").text().trim(),
                    count: $row.find("td:nth-child(3)").text().trim(),
                  };
                } else if (currentPageType === "submissions") {
                  var scorePartsAjax = [];
                  $row.find(".ca-col-score").each(function () {
                    scorePartsAjax.push($(this).text().trim());
                  });
                  rowData = {
                    row: $row.clone(),
                    id: $row.find(".ca-col-id").first().text().trim(),
                    name: $row.find(".ca-sub-name").text().trim(),
                    email: $row.find(".ca-sub-email").text().trim(),
                    phone: $row.find(".ca-sub-phone").text().trim(),
                    jobTitle: $row.find(".ca-sub-job").text().trim(),
                    assessment: $row.find(".ca-sub-assessment").text().trim(),
                    score: scorePartsAjax.join(" "),
                    status: $row.find(".ca-col-status").text().trim(),
                  };
                }

                if (rowData) {
                  // Check for duplicates before adding to fetched data
                  var isDuplicate = false;
                  for (var j = 0; j < fetchedData.length; j++) {
                    var existingData = fetchedData[j];
                    if (currentPageType === "questions") {
                      if (
                        existingData.number === rowData.number &&
                        existingData.question === rowData.question
                      ) {
                        isDuplicate = true;
                        break;
                      }
                    } else if (currentPageType === "categories") {
                      if (
                        existingData.number === rowData.number &&
                        existingData.category === rowData.category
                      ) {
                        isDuplicate = true;
                        break;
                      }
                    } else if (currentPageType === "submissions") {
                      if (existingData.id === rowData.id) {
                        isDuplicate = true;
                        break;
                      }
                    }
                  }

                  if (!isDuplicate) {
                    fetchedData.push(rowData);
                  }
                }
              });

              deferred.resolve();
            },
            error: function () {
              deferred.resolve(); // Don't fail completely if one page fails
            },
          });
        }
      }

      // Wait for all requests to complete, then merge data
      $.when.apply($, requests).done(function () {
        // Add fetched data to originalData (only if not already present)
        fetchedData.forEach(function (newData) {
          var isDuplicate = false;
          for (var k = 0; k < originalData.length; k++) {
            var existingData = originalData[k];
            if (currentPageType === "questions") {
              if (
                existingData.number === newData.number &&
                existingData.question === newData.question
              ) {
                isDuplicate = true;
                break;
              }
            } else if (currentPageType === "categories") {
              if (
                existingData.number === newData.number &&
                existingData.category === newData.category
              ) {
                isDuplicate = true;
                break;
              }
            } else if (currentPageType === "submissions") {
              if (existingData.id === newData.id) {
                isDuplicate = true;
                break;
              }
            }
          }

          if (!isDuplicate) {
            originalData.push(newData);
          }
        });

        allDataLoaded = true;

        // If user is currently searching, refresh results now that we have the full dataset.
        if (latestSearchTerm && latestSearchTerm.length >= 3) {
          performSearch(latestSearchTerm);
        }
      });
    }

    // Initialize with current page data
    loadAllData();

    // Set up search input event handlers for all search fields
    $("#ca-search-questions, #ca-search-categories, #ca-search-submissions").on(
      "input",
      function () {
        var searchTerm = $(this).val().toLowerCase().trim();

        // Clear previous timeout
        clearTimeout(searchTimeout);

        // Show search count container
        $searchCount.show();

        // Debounce: wait 300ms after user stops typing
        searchTimeout = setTimeout(function () {
          performSearch(searchTerm);
        }, 300);
      },
    );

    function performSearch(searchTerm) {
      // Always search using the latest term if multiple keystrokes happen while data is loading.
      latestSearchTerm = searchTerm;

      var isNumericSearch = /^[0-9]+$/.test(searchTerm);

      // Keep the "min 3 chars" UX for text searches,
      // but allow numeric searches on the Questions page (Priority is 1..N, often 1-2 digits).
      if (searchTerm.length < 3 && !(currentPageType === "questions" && isNumericSearch)) {
        // Restore the original server-rendered paginated rows.
        $tableBody.empty();
        if ($defaultPageRows && $defaultPageRows.length > 0) {
          $tableBody.append($defaultPageRows.clone());
        }

        var $table = $tableBody.closest("table");
        var $tableHead = $table.find("thead");
        $tableHead.show();

        $searchCount.hide();
        return;
      }

      // If data are still loading, run the search with whatever is loaded so far.
      // It will be refreshed automatically once all pages are fetched.
      if (!allDataLoaded && originalData.length === 0) {
        setTimeout(function () {
          performSearch(latestSearchTerm);
        }, 150);
        return;
      }

      var matchingData = [];
      var totalMatches = 0;

      // Search through all data based on page type
      originalData.forEach(function (item) {
        var matches = false;

        if (currentPageType === "questions") {
          // Search in question number, category, priority, and question text
          var numberStr = (item.number || "").toString().toLowerCase();
          var categoryStr = (item.category || "").toString().toLowerCase();
          var priorityStr = (item.priority || "").toString().toLowerCase();
          var questionStr = (item.question || "").toString().toLowerCase();
          if (
            numberStr.includes(searchTerm) ||
            categoryStr.includes(searchTerm) ||
            priorityStr.includes(searchTerm) ||
            questionStr.includes(searchTerm)
          ) {
            matches = true;
          }
        } else if (currentPageType === "categories") {
          // Search in category number and category name
          if (
            (item.number || "").toString().toLowerCase().includes(searchTerm) ||
            (item.category || "").toString().toLowerCase().includes(searchTerm)
          ) {
            matches = true;
          }
        } else if (currentPageType === "submissions") {
          // Search in ID, name, email, phone, job title, assessment (all-submissions), score, status
          if (
            (item.id || "").toString().toLowerCase().includes(searchTerm) ||
            (item.name || "").toString().toLowerCase().includes(searchTerm) ||
            (item.email || "").toString().toLowerCase().includes(searchTerm) ||
            (item.phone || "").toString().toLowerCase().includes(searchTerm) ||
            (item.jobTitle || "").toString().toLowerCase().includes(searchTerm) ||
            (item.assessment || "").toString().toLowerCase().includes(searchTerm) ||
            (item.score || "").toString().toLowerCase().includes(searchTerm) ||
            (item.status || "").toString().toLowerCase().includes(searchTerm)
          ) {
            matches = true;
          }
        }

        if (matches) {
          matchingData.push(item);
          totalMatches++;
        }
      });

      // Update table with matching results
      var $table = $tableBody.closest("table");
      var $tableHead = $table.find("thead");

      $tableBody.empty();
      if (matchingData.length > 0) {
        matchingData.forEach(function (item) {
          appendItemRow(item);
        });
        // Show table headers when there are results
        $tableHead.show();
      } else {
        var noResultsMessage = "No items found matching your search.";
        if (currentPageType === "questions") {
          noResultsMessage = "No questions found matching your search.";
        } else if (currentPageType === "categories") {
          noResultsMessage = "No categories found matching your search.";
        } else if (currentPageType === "submissions") {
          noResultsMessage = "No submissions found matching your search.";
        }

        // Create a single row with a single cell that spans all columns
        // This ensures the message is displayed without showing empty table columns
        var $noResultsRow = $("<tr>");
        var $noResultsCell = $("<td>");
        $noResultsCell.attr("colspan", "99");
        $noResultsCell.css({
          "text-align": "center",
          padding: "20px",
          color: "#666",
          "font-style": "italic",
        });
        $noResultsCell.text(noResultsMessage);
        $noResultsRow.append($noResultsCell);
        $tableBody.append($noResultsRow);

        // Hide table headers when there are no results
        $tableHead.hide();
      }

      // Update search results count
      var resultText =
        totalMatches === 1 ? "1 result" : totalMatches + " results";
      $searchResultsCount.text(
        "Found " + resultText + ' for "' + searchTerm + '"',
      );
    }
  }

  // Initialize universal search if any search field exists
  if (
    $("#ca-search-questions").length ||
    $("#ca-search-categories").length ||
    $("#ca-search-submissions").length
  ) {
    initUniversalSearch();
  }

  // Inline edit for Assessment Questions (Edit -> dropdown/text -> Save)
  function enableQuestionEditMode($row) {
    var $categoryText = $row.find(".ca-question-category-text");
    var $categorySelect = $row.find(".ca-question-category-select");
    var $questionText = $row.find(".ca-question-text-display");
    var $questionInput = $row.find(".ca-question-text-input");
    var $priorityText = $row.find(".ca-question-priority-text");
    var $priorityInput = $row.find(".ca-question-priority-input");
    var $editBtn = $row.find(".ca-question-edit-btn");
    var $cancelBtn = $row.find(".ca-question-cancel-btn");
    var $saveBtn = $row.find(".ca-question-save-btn");

    $categoryText.hide();
    $categorySelect.show();
    $questionText.hide();
    $questionInput.show().focus();
    $priorityText.hide();
    $priorityInput.show();

    $editBtn.hide();
    $cancelBtn.show();
    $saveBtn.show();
  }

  function cancelQuestionEditMode($row) {
    var $categorySelect = $row.find(".ca-question-category-select");
    var $questionInput = $row.find(".ca-question-text-input");
    var $priorityInput = $row.find(".ca-question-priority-input");
    var $cancelBtn = $row.find(".ca-question-cancel-btn");

    var origCat = $categorySelect.data("original");
    var origText = $questionInput.data("original");
    var origPriority = $priorityInput.data("original");

    if (origCat != null) {
      $categorySelect.val(origCat);
    }
    if (origText != null) {
      $questionInput.val(origText);
    }
    if (origPriority != null) {
      $priorityInput.val(origPriority);
    }

    // Update visible spans back to the original values.
    var $categorySpan = $row.find(".ca-question-category-text");
    var $questionSpan = $row.find(".ca-question-text-display");
    var $prioritySpan = $row.find(".ca-question-priority-text");
    $categorySpan.text($categorySelect.val() != null ? $categorySelect.val() : "");
    $questionSpan.text($questionInput.val() != null ? $questionInput.val() : "");
    $prioritySpan.text(
      $priorityInput.val() != null ? $priorityInput.val() : ""
    );

    $categorySpan.show();
    $categorySelect.hide();
    $questionSpan.show();
    $questionInput.hide();
    $prioritySpan.show();
    $priorityInput.hide();

    $row.find(".ca-question-edit-btn").show();
    $cancelBtn.hide();
    $row.find(".ca-question-save-btn").hide();
  }

  // Use delegated handlers so search-rebuilt rows still work.
  $(document).on("click", ".ca-question-edit-btn", function (e) {
    e.preventDefault();
    var $row = $(this).closest("tr");
    var isEditing = $row.find(".ca-question-text-input:visible").length > 0;

    if (isEditing) {
      cancelQuestionEditMode($row);
    } else {
      enableQuestionEditMode($row);
    }
  });

  $(document).on("click", ".ca-question-cancel-btn", function (e) {
    e.preventDefault();
    var $row = $(this).closest("tr");
    cancelQuestionEditMode($row);
  });

  $(document).on("keydown", function (e) {
    if (e.key !== "Escape") return;
    $(".ca-question-text-input:visible").each(function () {
      cancelQuestionEditMode($(this).closest("tr"));
    });
  });

  function finishQuestionEditModeAfterSave($row) {
    // Switch back to display-only state after a successful save.
    $row.find(".ca-question-category-text").show();
    $row.find(".ca-question-category-select").hide();
    $row.find(".ca-question-text-display").show();
    $row.find(".ca-question-text-input").hide();
    $row.find(".ca-question-priority-text").show();
    $row.find(".ca-question-priority-input").hide();

    $row.find(".ca-question-edit-btn").show();
    $row.find(".ca-question-cancel-btn").hide();
    $row.find(".ca-question-save-btn").hide();
  }

  function syncQuestionRowValues($row, updated) {
    // updated: { category, text, priority }
    var cat = updated.category != null ? String(updated.category) : "";
    var text = updated.text != null ? String(updated.text) : "";
    var prio = updated.priority != null ? String(updated.priority) : "";

    var $categorySelect = $row.find(".ca-question-category-select");
    var $questionInput = $row.find(".ca-question-text-input");
    var $priorityInput = $row.find(".ca-question-priority-input");

    if ($categorySelect.length) {
      $categorySelect.val(cat);
      $categorySelect.data("original", cat);
      $categorySelect.attr("data-original", cat);
    }
    if ($questionInput.length) {
      $questionInput.val(text);
      $questionInput.data("original", text);
      $questionInput.attr("data-original", text);
    }
    if ($priorityInput.length) {
      $priorityInput.val(prio);
      $priorityInput.data("original", prio);
      $priorityInput.attr("data-original", prio);
    }

    $row.find(".ca-question-category-text").text(cat).attr("data-original", cat);
    $row
      .find(".ca-question-text-display")
      .text(text)
      .attr("data-original", text);
    $row
      .find(".ca-question-priority-text")
      .text(prio || "")
      .attr("data-original", prio);
  }

  function updateQuestionsAllData(updated) {
    if (!Array.isArray(window.CA_ADMIN_QUESTIONS_ALL)) return;

    var idx = parseInt(updated.question_index, 10);
    for (var i = 0; i < window.CA_ADMIN_QUESTIONS_ALL.length; i++) {
      var item = window.CA_ADMIN_QUESTIONS_ALL[i];
      if (item && parseInt(item.question_index, 10) === idx) {
        item.category = updated.category;
        item.priority = String(updated.priority);
        item.question = updated.text;
        break;
      }
    }
  }

  function updateDefaultQuestionRowClones(updated) {
    if (
      !window.CA_ADMIN_QUESTIONS_DEFAULT_PAGE_ROWS ||
      !window.CA_ADMIN_QUESTIONS_DEFAULT_PAGE_ROWS.length
    ) {
      return;
    }

    var idx = parseInt(updated.question_index, 10);
    var $cloneRow = window.CA_ADMIN_QUESTIONS_DEFAULT_PAGE_ROWS.filter(function () {
      var val = $(this).find(".ca-question-select").val();
      return parseInt(val, 10) === idx;
    });

    if ($cloneRow.length) {
      syncQuestionRowValues($cloneRow, updated);
    }
  }

  // AJAX save for inline edits (prevents full page reload)
  $(document).on("submit", ".ca-question-edit-form", function (e) {
    e.preventDefault();

    var $form = $(this);
    var $row = $form.closest("tr");
    if (!$row.length) return;

    var formId = $form.attr("id");
    var ajaxUrl =
      window.CA_ADMIN_AJAX_URL ||
      (typeof ajaxurl !== "undefined" ? ajaxurl : null);
    if (!ajaxUrl) {
      alert("AJAX URL not available.");
      return;
    }

    var editNonce = $form.find('input[name="_wpnonce"]').val();
    var questionIndex = $form.find('input[name="question_index"]').val();
    var $categorySelect = $row.find(
      'select[form="' + formId + '"][name="new_category"]'
    );
    var $questionInput = $row.find(
      'input[form="' + formId + '"][name="new_question_text"]'
    );
    var $priorityInput = $row.find(
      'input[form="' + formId + '"][name="new_priority"]'
    );

    var payload = {
      action: "ca_edit_question_ajax",
      _wpnonce: editNonce,
      question_index: questionIndex,
      new_category: $categorySelect.val() || "",
      new_question_text: $questionInput.val() || "",
      new_priority: $priorityInput.val() || 0,
      assessment_type:
        window.CA_ADMIN_QUESTIONS_ASSESSMENT_TYPE || "mindset",
    };

    var $saveBtn = $row.find(".ca-question-save-btn").first();
    $saveBtn.prop("disabled", true);

    $.ajax({
      url: ajaxUrl,
      method: "POST",
      data: payload,
      dataType: "json",
      success: function (resp) {
        if (!resp || !resp.success) {
          var msg =
            resp && resp.data && resp.data.message
              ? resp.data.message
              : "Unable to save this question.";
          alert(msg);
          $saveBtn.prop("disabled", false);
          return;
        }

        syncQuestionRowValues($row, resp.data);
        finishQuestionEditModeAfterSave($row);
        updateQuestionsAllData(resp.data);
        updateDefaultQuestionRowClones(resp.data);

        $saveBtn.prop("disabled", false);
      },
      error: function (xhr) {
        var msg =
          "Unable to save this question. Please check your Priority value and try again.";

        // jQuery may reject non-2xx responses, so we try to extract the message.
        try {
          if (xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
            msg = xhr.responseJSON.data.message;
          } else if (xhr && xhr.responseJSON && xhr.responseJSON.message) {
            msg = xhr.responseJSON.message;
          } else if (xhr && xhr.responseText) {
            var parsed = JSON.parse(xhr.responseText);
            if (parsed && parsed.data && parsed.data.message) {
              msg = parsed.data.message;
            }
          }
        } catch (e) {
          // Keep fallback message.
        }

        alert(msg);
        $saveBtn.prop("disabled", false);
      },
    });
  });

  // Improve Add New Question UX (live counter + enable/disable submit).
  if ($("#question_text").length) {
    (function initQuestionFormUX() {
      var $questionText = $("#question_text");
      var $category = $("#question_category");
      var $priority = $("#question_priority");
      var $submitBtn = $(".ca-question-submit").first();
      var $addForm = $submitBtn.closest("form");
      var $counter = $("#ca-question-text-counter");

      var maxLen = parseInt($questionText.attr("maxlength"), 10);
      if (isNaN(maxLen)) {
        maxLen = 500;
      }

      function updateCounter() {
        if (!$counter.length) return;
        var len = ($questionText.val() || "").length;
        $counter.text(len);
      }

      function updateSubmitState() {
        if (!$submitBtn.length) return;
        var catVal = ($category.val() || "").trim();
        var prioVal = ($priority.val() || "").toString().trim();
        var qText = ($questionText.val() || "").trim();

        var prioNum = parseInt(prioVal, 10);
        var isValid =
          catVal !== "" &&
          !isNaN(prioNum) &&
          prioNum > 0 &&
          qText.length > 0;
        $submitBtn.prop("disabled", !isValid);
        $submitBtn.attr("aria-disabled", (!isValid).toString());
      }

      function getNextPriorityForCategory(categoryVal) {
        var max = 0;
        if (Array.isArray(window.CA_ADMIN_QUESTIONS_ALL)) {
          window.CA_ADMIN_QUESTIONS_ALL.forEach(function (item) {
            if (!item) return;
            if (categoryVal && String(item.category) !== String(categoryVal)) return;
            var p = parseInt(item.priority, 10);
            if (!isNaN(p) && p > max) max = p;
          });
        }

        return String(max + 1);
      }

      function autoFillPriority() {
        // Only fill when the user hasn't entered a priority yet.
        var currentVal = ($priority.val() || "").toString().trim();
        if (currentVal !== "") return;

        var catVal = ($category.val() || "").trim();
        $priority.val(getNextPriorityForCategory(catVal));
        updateSubmitState();
      }

      updateCounter();
      updateSubmitState();

      $questionText.on("input", function () {
        updateCounter();
        updateSubmitState();
      });
      $category.on("change", updateSubmitState);
      $priority.on("change", updateSubmitState);

      // Before submit: block if the priority already exists in the selected category.
      if ($addForm && $addForm.length) {
        $addForm.on("submit", function (e) {
          var catVal = ($category.val() || "").trim();
          var prioVal = ($priority.val() || "").toString().trim();
          var qText = ($questionText.val() || "").trim();

          // Let backend handle validation if category/priority/text are missing.
          if (!catVal || !prioVal || !qText) {
            return true;
          }

          var prioNum = parseInt(prioVal, 10);
          if (isNaN(prioNum) || prioNum <= 0) {
            return true;
          }

          var conflict = false;
          if (Array.isArray(window.CA_ADMIN_QUESTIONS_ALL)) {
            window.CA_ADMIN_QUESTIONS_ALL.forEach(function (item) {
              if (conflict) return;
              if (!item) return;
              if (String(item.category) !== String(catVal)) return;
              var p = parseInt(item.priority, 10);
              if (!isNaN(p) && p === prioNum) {
                conflict = true;
              }
            });
          }

          if (conflict) {
            alert(
              "Priority already exists in this category. Please choose another number."
            );
            e.preventDefault();
            return false;
          }

          return true;
        });
      }

      // Auto-fill only when category is NOT selected yet.
      if (($category.val() || "").trim() === "") {
        autoFillPriority();
      }
    })();
  }

  // Bulk Edit UX for Assessment Questions
  if ($("#ca-bulk-edit-form").length) {
    (function initBulkEditUX() {
      var $openBtn = $(".ca-bulk-edit-open").first();
      var $overlay = $("#ca-bulk-edit-modal-overlay");
      var $cancelBtn = $(".ca-bulk-edit-cancel");
      var $selectedCount = $(".ca-bulk-selected-count");
      var $allSelect = $("#ca-bulk-select-all");
      var $indexesContainer = $("#ca-bulk-selected-indexes");
      var $indexesCount = $("#ca-bulk-question-indexes-count");
      var $bulkForm = $("#ca-bulk-edit-form");

      function getSelectedIndexes() {
        return $(".ca-question-select:checked")
          .map(function () {
            return $(this).val();
          })
          .get();
      }

      function syncSelectAll() {
        var $items = $(".ca-question-select");
        var total = $items.length;
        var selected = $items.filter(":checked").length;

        if (total === 0) {
          $allSelect.prop("checked", false);
          return;
        }

        $allSelect.prop("checked", selected === total);
      }

      function updateBulkBar() {
        var selectedArr = getSelectedIndexes();
        var count = selectedArr.length;

        if ($selectedCount.length) {
          $selectedCount.text(count + " selected");
        }

        if ($openBtn.length) {
          $openBtn.prop("disabled", count === 0);
        }

        syncSelectAll();
      }

      // Open modal
      $(document).on("click", ".ca-bulk-edit-open", function (e) {
        e.preventDefault();
        updateBulkBar();
        if ($openBtn.prop("disabled")) return;
        $overlay.show();
      });

      // Cancel modal
      $(document).on("click", ".ca-bulk-edit-cancel", function (e) {
        e.preventDefault();
        $overlay.hide();
      });

      // Toggle select-all
      $(document).on("change", "#ca-bulk-select-all", function () {
        var checked = $(this).is(":checked");
        $(".ca-question-select").prop("checked", checked);
        updateBulkBar();
      });

      // Individual selection
      $(document).on("change", ".ca-question-select", function () {
        updateBulkBar();
      });

      // Build hidden index inputs on submit
      $bulkForm.on("submit", function (e) {
        var selectedArr = getSelectedIndexes();
        if (!selectedArr.length) {
          e.preventDefault();
          return;
        }

        $indexesContainer.empty();
        selectedArr.forEach(function (idx) {
          $("<input>")
            .attr("type", "hidden")
            .attr("name", "question_indexes[]")
            .val(idx)
            .appendTo($indexesContainer);
        });
        if ($indexesCount.length) {
          $indexesCount.val(selectedArr.length);
        }
      });

      // Init state
      updateBulkBar();
    })();
  }
});
