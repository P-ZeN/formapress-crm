/**
 * FormaPress CRM Admin JavaScript
 *
 * Handles:
 * - Quick-add modal for contacts/opportunities
 * - Kanban drag & drop for pipeline
 * - Person card interactions
 * - Attribute management (add/edit/delete/sort)
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

        // Attribute management pages
        initAttributeManagement();
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
            $(this).find(".column-count").text(count);
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

    /**
     * Attribute Management - For CRM attributes pages
     * Handles add/edit/delete/reorder for dynamic attributes
     */
    function initAttributeManagement() {
        // Initialize swap_input_elements for existing selectors
        $(".selector_types").each(function () {
            if (typeof swap_input_elements === "function") {
                swap_input_elements(this);
            }
        });

        // Enable drag-drop on existing rows
        $(".grab").mousedown(function (e) {
            if (typeof grab_row === "function") {
                grab_row(e);
            }
        });

        // Handle add attribute button with data attributes
        $(".add-attr-btn").on("click", function () {
            const table = $(this).data("table");
            const option = $(this).data("option");
            // Use i18n from localized script
            if (typeof formapressCrmAdmin !== "undefined" && formapressCrmAdmin.i18n) {
                zformAddRow(table, option, formapressCrmAdmin.i18n);
            }
        });
    }
})(jQuery);

/**
 * Global helper functions for attribute management
 * These need to be global to work with inline onclick handlers from zformations
 */

/**
 * Enhanced zformAddRow - adds a new attribute row
 * Compatible with both zformations and FormaPress CRM attributes pages
 *
 * @param {string} table - Table ID (e.g., 'referent-attrib-table')
 * @param {string} name - Field name prefix (e.g., 'crm_person_referent_attributes')
 * @param {object} i18n - Optional translations object
 */
function zformAddRow(table, name, i18n) {
    var translations = i18n || (typeof i18n_attrs !== "undefined" ? i18n_attrs : {});

    var tableRef = document.getElementById(table).getElementsByTagName("tbody")[0];
    var id = tableRef.rows.length;
    var newRowHTML = "";

    // Add drag handle column
    newRowHTML =
        '<td scope="row" class="grab">' +
        '<input type="hidden" name="' +
        name +
        "[" +
        id +
        '][order]" value="' +
        id +
        '"/>' +
        '<span class="dashicons dashicons-menu zform-grab"></span>' +
        "</td>";

    // Add name field
    newRowHTML += '<td scope="row">' + '<input type="text" name="' + name + "[" + id + '][name]" value=""/>' + "</td>";

    // Add type selector
    newRowHTML += '<td><select name="' + name + "[" + id + '][type]" class="selector_types">';

    // Build options based on available translations
    if (translations.text) newRowHTML += '<option value="text" selected>' + translations.text + "</option>";
    if (translations.number) newRowHTML += '<option value="number">' + translations.number + "</option>";
    if (translations.wyswyg) newRowHTML += '<option value="wyswyg">' + translations.wyswyg + "</option>";
    if (translations.list) newRowHTML += '<option value="list">' + translations.list + "</option>";
    if (translations.download) newRowHTML += '<option value="download">' + translations.download + "</option>";
    if (translations.image) newRowHTML += '<option value="image">' + translations.image + "</option>";
    if (translations.video) newRowHTML += '<option value="video">' + translations.video + "</option>";
    if (translations.date) newRowHTML += '<option value="date">' + translations.date + "</option>";
    if (translations.checkbox) newRowHTML += '<option value="checkbox">' + translations.checkbox + "</option>";
    if (translations.radio) newRowHTML += '<option value="radio">' + translations.radio + "</option>";
    if (translations.select) newRowHTML += '<option value="select">' + translations.select + "</option>";
    if (translations.tel) newRowHTML += '<option value="tel">' + translations.tel + "</option>";
    if (translations.mail) newRowHTML += '<option value="mail">' + translations.mail + "</option>";
    if (translations.textarea) newRowHTML += '<option value="textarea">' + translations.textarea + "</option>";
    if (translations.helptext) newRowHTML += '<option value="helptext">' + translations.helptext + "</option>";

    newRowHTML += "</select></td>";

    // Add options field (hidden by default)
    newRowHTML += '<td><input type="hidden" name="' + name + "[" + id + '][options]" value=""/></td>';

    // Add delete button
    newRowHTML +=
        '<td><span class="dashicons dashicons-trash zform-trash" style="cursor: pointer" onclick="zformDelRow(this)"></span></td>';

    // Insert new row
    var newRow = tableRef.insertRow(tableRef.rows.length);
    newRow.innerHTML = newRowHTML;
    newRow.className = tableRef.rows.length % 2 ? "" : "alternate";
    newRow.className += " form-attrib-row";

    // Initialize selector behavior
    var selector = document.getElementsByName(name + "[" + id + "][type]")[0];
    if (selector && typeof swap_input_elements === "function") {
        swap_input_elements(selector);
    }

    // Enable drag-drop on new row
    if (typeof jQuery !== "undefined" && typeof grab_row === "function") {
        jQuery(newRow)
            .find(".grab")
            .mousedown(function (e) {
                grab_row(e);
            });
    }
}

/**
 * Delete an attribute row
 * Called from inline onclick handlers
 *
 * @param {HTMLElement} r - The trash icon element clicked
 */
function zformDelRow(r) {
    var i = r.parentNode.parentNode.rowIndex - 1;
    r.parentNode.parentNode.parentNode.deleteRow(i);
}

/**
 * swap_input_elements - Changes options field based on field type
 * Called from zformations, needs to be globally available
 *
 * @param {HTMLElement} selector - The type select element
 */
function swap_input_elements(selector) {
    selector.addEventListener("change", function (e) {
        var type = this.value;
        var name = this.parentNode.nextElementSibling.firstElementChild.name;
        var input = this.parentNode.nextElementSibling.firstElementChild;
        var value = this.parentNode.nextElementSibling.firstElementChild.value;

        switch (type) {
            case "text":
            case "number":
            case "tel":
            case "date":
            case "wyswyg":
            case "image":
            case "video":
            case "download":
                input = document.createElement("input");
                input.type = "hidden";
                break;
            case "radio":
            case "checkbox":
            case "select":
            case "list":
                input = document.createElement("textarea");
                input.placeholder = "Valeur|Label (une option par ligne)";
                break;
        }
        input.name = name;
        input.value = value;
        this.parentNode.nextElementSibling.innerHTML = "";
        this.parentNode.nextElementSibling.appendChild(input);
    });
}

/**
 * grab_row - Enables drag-to-reorder for attribute rows
 * Called from zformations, needs to be globally available
 *
 * @param {Event} e - Mouse down event
 */
function grab_row(e) {
    var table_id = jQuery(e.target).closest("table");
    var table = document.getElementById(table_id[0].id);
    var tr = jQuery(e.target).closest("TR"),
        si = tr.index(),
        sy = e.pageY,
        b = jQuery(document.body),
        drag;

    b.addClass("grabCursor").css("userSelect", "none");
    tr.addClass("grabbed");

    function move(e) {
        if (!drag && Math.abs(e.pageY - sy) < 10) return;
        drag = true;
        tr.siblings().each(function () {
            var s = jQuery(this),
                i = s.index(),
                y = s.offset().top;
            if (i > 0 && e.pageY >= y && e.pageY < y + s.outerHeight()) {
                if (i < tr.index()) tr.insertAfter(s);
                else tr.insertBefore(s);
                return false;
            }
        });
    }

    function up(e) {
        if (drag && si != tr.index()) {
            drag = false;
            var rows = table.rows;
            for (var i = 1, row; (row = table.rows[i]); i++) {
                var input = row.cells[0].getElementsByTagName("input")[0];
                if (input) {
                    input.value = i;
                }
            }
        }
        jQuery(document).unbind("mousemove", move).unbind("mouseup", up);
        b.removeClass("grabCursor").css("userSelect", "none");
        tr.removeClass("grabbed");
    }

    jQuery(document).mousemove(move).mouseup(up);
}
