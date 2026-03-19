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
    var $tableBody = $(".ca-admin-table tbody");
    var $searchCount = $(".ca-search-count");
    var $searchResultsCount = $("#ca-search-results-count");
    var $paginationControls = $(".tablenav.top, .tablenav.bottom");
    var currentPageType = getCurrentPageType();

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

      // Create AJAX requests for all pages except current (already loaded)
      for (var i = 1; i <= totalPages; i++) {
        if (i !== currentPage) {
          var deferred = $.Deferred();
          requests.push(deferred.promise());

          $.ajax({
            url: window.location.href,
            method: "GET",
            data: { paged: i },
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
                  originalData.push(rowData);
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

      // Wait for all requests to complete
      $.when.apply($, requests).done(function () {
        allDataLoaded = true;
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
      if (searchTerm.length < 3) {
        // Show all data if search term is too short
        if (originalData.length > 0) {
          $tableBody.empty();
          originalData.forEach(function (item) {
            $tableBody.append(item.row);
          });
        }
        $searchCount.hide();
        return;
      }

      // If data are still loading, wait a bit and try again
      if (!allDataLoaded && originalData.length === 0) {
        setTimeout(function () {
          performSearch(searchTerm);
        }, 100);
        return;
      }

      var matchingData = [];
      var totalMatches = 0;

      // Search through all data based on page type
      originalData.forEach(function (item) {
        var matches = false;

        if (currentPageType === "questions") {
          // Search in question number, category, priority, and question text
          if (
            item.number.toLowerCase().includes(searchTerm) ||
            item.category.toLowerCase().includes(searchTerm) ||
            item.priority.toLowerCase().includes(searchTerm) ||
            item.question.toLowerCase().includes(searchTerm)
          ) {
            matches = true;
          }
        } else if (currentPageType === "categories") {
          // Search in category number and category name
          if (
            item.number.toLowerCase().includes(searchTerm) ||
            item.category.toLowerCase().includes(searchTerm)
          ) {
            matches = true;
          }
        } else if (currentPageType === "submissions") {
          // Search in ID, name, email, phone, job title, score, and status
          if (
            item.id.toLowerCase().includes(searchTerm) ||
            item.name.toLowerCase().includes(searchTerm) ||
            item.email.toLowerCase().includes(searchTerm) ||
            item.phone.toLowerCase().includes(searchTerm) ||
            item.jobTitle.toLowerCase().includes(searchTerm) ||
            item.score.toLowerCase().includes(searchTerm) ||
            item.status.toLowerCase().includes(searchTerm)
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
      $tableBody.empty();
      if (matchingData.length > 0) {
        matchingData.forEach(function (item) {
          $tableBody.append(item.row);
        });
      } else {
        var noResultsMessage = "No items found matching your search.";
        if (currentPageType === "questions") {
          noResultsMessage = "No questions found matching your search.";
        } else if (currentPageType === "categories") {
          noResultsMessage = "No categories found matching your search.";
        } else if (currentPageType === "submissions") {
          noResultsMessage = "No submissions found matching your search.";
        }

        $tableBody.append(
          '<tr><td colspan="99" style="text-align: center; padding: 20px; color: #666;">' +
            noResultsMessage +
            "</td></tr>",
        );
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
