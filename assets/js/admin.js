/**
 * Admin scripts for Custom Assessment Plugin
 */

jQuery(document).ready(function ($) {
  // Export dropdown menu toggle
  $(".ca-export-dropdown-btn").on("click", function (e) {
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

  // Close menu when clicking an option
  $(".ca-export-option").on("click", function () {
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

    // Switch to edit mode
    $nameElement.hide();
    $inputElement.show().focus();
    $editBtn.hide();
    $saveBtn.show();
  });

  $(".ca-save-btn").on("click", function () {
    var $row = $(this).closest("tr");
    var index = $(this).data("index");
    var originalCategory = $(this).data("category");
    var $nameElement = $row.find(".ca-category-name");
    var $inputElement = $row.find(".ca-category-input");
    var $editBtn = $row.find(".ca-edit-btn");
    var $saveBtn = $row.find(".ca-save-btn");
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

  // Cancel edit when clicking outside the input (optional)
  $(document).on("click", function (e) {
    if (
      !$(e.target).closest(".ca-category-input").length &&
      !$(e.target).closest(".ca-edit-btn").length &&
      !$(e.target).closest(".ca-save-btn").length
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
    $inputElement.hide();
    $nameElement.show();
    $editBtn.show();
    $saveBtn.hide();
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

    function buildQuestionRow(item) {
      // Build a <tr> that matches the PHP-rendered layout for the Questions page.
      // Used when the data source doesn't provide a cloned DOM row.
      var questionIndex = item.question_index != null ? parseInt(item.question_index, 10) : 0;

      var $tr = $("<tr>");
      $tr.append($("<td>").addClass("ca-col-id").text((questionIndex || 0) + 1));
      $tr.append($("<td>").text(item.category != null ? item.category : ""));
      $tr.append($("<td>").addClass("ca-col-priority").text(item.priority != null ? item.priority : ""));
      $tr.append($("<td>").text(item.question != null ? item.question : ""));

      var confirmText =
        window.CA_ADMIN_QUESTIONS_DELETE_CONFIRM || "Are you sure you want to delete this question?";

      var nonceVal = window.CA_ADMIN_QUESTIONS_DELETE_NONCE || "";

      var $actionsTd = $("<td>");
      var $form = $("<form>")
        .attr("method", "post")
        .attr("style", "display: inline;")
        .attr(
          "onsubmit",
          "return confirm(" + JSON.stringify(confirmText) + ");"
        );

      var $nonceField = $("<input>")
        .attr("type", "hidden")
        .attr("name", "_wpnonce")
        .val(nonceVal);

      var $actionField = $("<input>")
        .attr("type", "hidden")
        .attr("name", "ca_action")
        .val("delete_question");

      var $indexField = $("<input>")
        .attr("type", "hidden")
        .attr("name", "question_index")
        .val(questionIndex);

      var $btn = $("<button>")
        .attr("type", "submit")
        .addClass("button button-small button-secondary")
        .text("Delete");

      $form.append($nonceField, $actionField, $indexField, $btn);
      $actionsTd.append($form);
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
      if (window.location.href.includes("custom-assessment-questions")) {
        return "questions";
      } else if (
        window.location.href.includes("custom-assessment-categories")
      ) {
        return "categories";
      } else if (
        window.location.href.includes("custom-assessment-submissions")
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
          rowData = {
            row: $row.clone(),
            id: $row.find(".ca-col-id").text().trim(),
            name: $row.find("td:nth-child(2)").text().trim(),
            email: $row.find("td:nth-child(3)").text().trim(),
            phone: $row.find("td:nth-child(4)").text().trim(),
            jobTitle: $row.find("td:nth-child(5)").text().trim(),
            score: $row.find(".ca-col-score").first().text().trim(),
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
                  rowData = {
                    row: $row.clone(),
                    id: $row.find(".ca-col-id").text().trim(),
                    name: $row.find("td:nth-child(2)").text().trim(),
                    email: $row.find("td:nth-child(3)").text().trim(),
                    phone: $row.find("td:nth-child(4)").text().trim(),
                    jobTitle: $row.find("td:nth-child(5)").text().trim(),
                    score: $row.find(".ca-col-score").first().text().trim(),
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

      if (searchTerm.length < 3) {
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
          // Search in ID, name, email, phone, job title, score, and status
          if (
            (item.id || "").toString().toLowerCase().includes(searchTerm) ||
            (item.name || "").toString().toLowerCase().includes(searchTerm) ||
            (item.email || "").toString().toLowerCase().includes(searchTerm) ||
            (item.phone || "").toString().toLowerCase().includes(searchTerm) ||
            (item.jobTitle || "").toString().toLowerCase().includes(searchTerm) ||
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
});
