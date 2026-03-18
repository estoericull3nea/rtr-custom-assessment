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
});
