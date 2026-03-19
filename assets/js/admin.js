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
});
