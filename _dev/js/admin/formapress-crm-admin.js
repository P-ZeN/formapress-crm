/**
 * FormaPress CRM Admin JavaScript
 *
 * Handles:
 * - Quick-add modal for contacts/opportunities
 * - Kanban drag & drop for pipeline
 * - Person card interactions
 *
 * Global object: formapressCrmAdmin (ajax_url, nonce)
 */

(function ($) {
    "use strict";

    // Initialize on DOM ready
    $(document).ready(function () {
        // Initialize Select2 for better dropdowns
        if ($.fn.select2) {
            $(".crm-select2").select2({
                width: "100%",
                placeholder: "Select an option...",
            });
        }

        // Quick Add Person Modal
        initQuickAddModal();

        // Kanban drag and drop
        initKanbanBoard();

        // Person card interactions
        initPersonCards();
    });

    /**
     * Quick Add Modal
     */
    function initQuickAddModal() {
        // Open modal
        $(document).on("click", ".crm-quick-add-trigger", function (e) {
            e.preventDefault();
            $(".crm-quick-add-modal").addClass("active");
        });

        // Close modal
        $(document).on("click", ".modal-close, .btn-cancel", function (e) {
            e.preventDefault();
            $(".crm-quick-add-modal").removeClass("active");
        });

        // Close on overlay click
        $(document).on("click", ".crm-quick-add-modal", function (e) {
            if ($(e.target).is(".crm-quick-add-modal")) {
                $(this).removeClass("active");
            }
        });

        // Form submission
        $(document).on("submit", ".crm-quick-add-form", function (e) {
            e.preventDefault();

            const form = $(this);
            const submitBtn = form.find(".btn-save");

            // Disable submit button
            submitBtn.prop("disabled", true).text("Saving...");

            // Get form data
            const formData = new FormData(this);
            formData.append("action", "crm_quick_add");
            formData.append("nonce", formapressCrmAdmin.nonce);

            // AJAX save
            $.ajax({
                url: formapressCrmAdmin.ajax_url,
                type: "POST",
                data: formData,
                processData: false,
                contentType: false,
                success: function (response) {
                    if (response.success) {
                        // Close modal
                        $(".crm-quick-add-modal").removeClass("active");

                        // Show success message
                        showNotice("Contact added successfully!", "success");

                        // Reload page or update list
                        setTimeout(function () {
                            window.location.reload();
                        }, 1000);
                    } else {
                        showNotice(response.data.message || "Error saving contact", "error");
                    }
                },
                error: function () {
                    showNotice("Server error. Please try again.", "error");
                },
                complete: function () {
                    submitBtn.prop("disabled", false).text("Save Contact");
                },
            });
        });
    }

    /**
     * Kanban Board - Drag & Drop
     */
    function initKanbanBoard() {
        if (!$(".crm-kanban-board").length) return;

        let draggedCard = null;

        // Make cards draggable
        $(document).on("dragstart", ".kanban-card", function (e) {
            draggedCard = $(this);
            $(this).addClass("dragging");
            e.originalEvent.dataTransfer.effectAllowed = "move";
            e.originalEvent.dataTransfer.setData("text/html", $(this).html());
        });

        $(document).on("dragend", ".kanban-card", function () {
            $(this).removeClass("dragging");
        });

        // Handle drop zones
        $(document).on("dragover", ".kanban-cards", function (e) {
            e.preventDefault();
            $(this).addClass("drag-over");
        });

        $(document).on("dragleave", ".kanban-cards", function () {
            $(this).removeClass("drag-over");
        });

        $(document).on("drop", ".kanban-cards", function (e) {
            e.preventDefault();
            $(this).removeClass("drag-over");

            if (draggedCard) {
                // Append card to new column
                $(this).append(draggedCard);

                // Update stage via AJAX
                const opportunityId = draggedCard.data("opportunity-id");
                const newStage = $(this).closest(".kanban-column").data("stage");

                updateOpportunityStage(opportunityId, newStage);
            }
        });
    }

    /**
     * Update opportunity stage via AJAX
     */
    function updateOpportunityStage(opportunityId, newStage) {
        $.ajax({
            url: formapressCrmAdmin.ajax_url,
            type: "POST",
            data: {
                action: "formapress_crm_update_opportunity_stage",
                nonce: formapressCrmAdmin.nonce,
                opportunity_id: opportunityId,
                stage: newStage,
            },
            success: function (response) {
                if (response.success) {
                    showNotice("Opportunity moved to " + newStage, "success");
                    // Update column counts
                    updateColumnCounts();
                } else {
                    showNotice("Error updating opportunity", "error");
                }
            },
        });
    }

    /**
     * Update Kanban column card counts
     */
    function updateColumnCounts() {
        $(".kanban-column").each(function () {
            const count = $(this).find(".kanban-card").length;
            $(this).find(".kanban-count").text(count);
        });
    }

    /**
     * Person Cards
     */
    function initPersonCards() {
        // Click to view person details
        $(document).on("click", ".crm-person-card", function () {
            const personId = $(this).data("person-id");
            if (personId) {
                window.location.href = "post.php?post=" + personId + "&action=edit";
            }
        });
    }

    /**
     * Show admin notice
     */
    function showNotice(message, type) {
        const noticeClass = type === "success" ? "notice-success" : "notice-error";
        const notice = $('<div class="notice ' + noticeClass + ' is-dismissible"><p>' + message + "</p></div>");

        $(".wrap > h1").after(notice);

        // Auto-dismiss after 3 seconds
        setTimeout(function () {
            notice.fadeOut(function () {
                $(this).remove();
            });
        }, 3000);
    }
})(jQuery);
