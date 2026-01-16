/**
 * Opportunity → ZQPM Creation Multi-Step Modal
 */
(function ($) {
    "use strict";

    console.log("opportunity-zqpm.js: Script loaded ✓✓✓ VERSION v11.3 - 2026-01-16 11:23 ✓✓✓");

    let modal,
        currentStep = 1;
    let formationsData = [],
        sessionsData = [];
    let selectedFormationId, selectedSessionId;
    let opportunityId, companyId, personId;

    $(document).ready(function () {
        console.log("opportunity-zqpm.js: DOM ready");
        modal = $("#zqpm-creation-modal");
        console.log("opportunity-zqpm.js: Modal element found:", modal.length);

        // Open modal on button click
        $(".open-zqpm-modal").on("click", function () {
            opportunityId = $(this).data("opportunity-id");
            companyId = $(this).data("company-id");
            personId = $(this).data("person-id");
            selectedFormationId = $(this).data("formation-id");

            openModal();
        });

        // Close modal
        $(".zqpm-modal-close, .cancel-zqpm-creation").on("click", closeModal);
        $(".zqpm-modal-overlay").on("click", closeModal);

        // Navigation buttons
        $(".zqpm-btn-next").on("click", nextStep);
        $(".zqpm-btn-back").on("click", prevStep);

        // Submit ZQPM creation
        $(".confirm-zqpm-creation").on("click", createZQPM);

        // Formation change triggers session reload
        $("#zqpm-formation-select").on("change", function () {
            selectedFormationId = $(this).val();
            if (currentStep === 2) {
                loadSessions();
            }
        });

        // Open nested session creation modal
        $(document).on("click", ".zqpm-create-new-session", function (e) {
            e.preventDefault();
            openSessionModal();
        });

        // Close nested session modal
        $(".zqpm-nested-modal .zqpm-modal-close").on("click", closeSessionModal);
        $(".zqpm-nested-modal .zqpm-modal-overlay").on("click", closeSessionModal);

        // ESC key to close
        $(document).on("keyup", function (e) {
            if (e.key === "Escape" && modal.is(":visible")) {
                if ($("#zqpm-create-session-modal").is(":visible")) {
                    closeSessionModal();
                } else {
                    closeModal();
                }
            }
        });
    });

    function openModal() {
        currentStep = 1;
        modal.fadeIn(200);
        $("body").addClass("zqpm-modal-open");
        showStep(1);
        loadFormations();
    }

    function closeModal() {
        modal.fadeOut(200);
        $("body").removeClass("zqpm-modal-open");
        $(".zqpm-error-message").hide();
        currentStep = 1;
    }

    function showStep(step) {
        currentStep = step;

        // Hide all steps
        $(".zqpm-step").hide();

        // Show current step
        $('.zqpm-step[data-step="' + step + '"]').show();

        // Update buttons
        $(".zqpm-btn-back").toggle(step > 1);
        $(".zqpm-btn-next").toggle(step < 3);
        $(".confirm-zqpm-creation").toggle(step === 3);

        $(".zqpm-error-message").hide();
    }

    function nextStep() {
        // Validate current step
        if (currentStep === 1) {
            if (!$("#zqpm-formation-select").val()) {
                showError("Veuillez sélectionner une formation.");
                return;
            }
            selectedFormationId = $("#zqpm-formation-select").val();
            loadSessions();
        } else if (currentStep === 2) {
            if (!$("#zqpm-session-select").val()) {
                showError("Veuillez sélectionner une session.");
                return;
            }
            selectedSessionId = $("#zqpm-session-select").val();
            updateSummary();
        }

        showStep(currentStep + 1);
    }

    function prevStep() {
        if (currentStep > 1) {
            showStep(currentStep - 1);
        }
    }

    function showError(message, type = "error") {
        const $msg = $(".zqpm-error-message");
        $msg.removeClass("success error").addClass(type).html(message).fadeIn();
    }

    function loadFormations() {
        const $select = $("#zqpm-formation-select");
        $select.html('<option value="">Chargement...</option>').prop("disabled", true);

        console.log("loadFormations: Starting AJAX call");

        $.ajax({
            url: formapressOpportunityZQPM.ajax_url,
            type: "POST",
            data: {
                action: "formapress_get_formations",
                nonce: formapressOpportunityZQPM.nonce,
            },
            success: function (response) {
                console.log("loadFormations: Response received", response);
                if (response.success && response.data) {
                    formationsData = response.data;
                    console.log("loadFormations: Formations data", formationsData);
                    renderFormations();
                } else {
                    console.error("loadFormations: No data or unsuccessful response");
                    $select.html('<option value="">Aucune formation disponible</option>');
                }
            },
            error: function (xhr, status, error) {
                console.error("loadFormations: AJAX error", status, error);
                $select.html('<option value="">Erreur de chargement</option>');
            },
            complete: function () {
                $select.prop("disabled", false);
            },
        });
    }

    function renderFormations() {
        const $select = $("#zqpm-formation-select");
        let html = '<option value="">— Sélectionner une formation —</option>';

        console.log("renderFormations: formationsData length", formationsData.length);
        console.log("renderFormations: selectedFormationId", selectedFormationId);

        formationsData.forEach(function (formation) {
            const selected = formation.id == selectedFormationId ? " selected" : "";
            html += '<option value="' + formation.id + '"' + selected + ">" + formation.title + "</option>";
        });

        console.log("renderFormations: Final HTML length", html.length);
        $select.html(html);
    }

    function loadSessions() {
        const $select = $("#zqpm-session-select");
        $select.html('<option value="">Chargement...</option>').prop("disabled", true);

        // Update "create new session" link
        $(".zqpm-create-new-session").attr(
            "href",
            formapressOpportunityZQPM.new_session_url + "&formation_id=" + selectedFormationId
        );

        $.ajax({
            url: formapressOpportunityZQPM.ajax_url,
            type: "POST",
            data: {
                action: "formapress_get_available_sessions",
                nonce: formapressOpportunityZQPM.nonce,
                formation_id: selectedFormationId,
            },
            success: function (response) {
                if (response.success && response.data && response.data.length > 0) {
                    sessionsData = response.data;
                    renderSessions();
                } else {
                    $select.html('<option value="">Aucune session disponible</option>');
                    $(".zqpm-create-session-option").show();
                }
            },
            error: function () {
                $select.html('<option value="">Erreur de chargement</option>');
            },
            complete: function () {
                $select.prop("disabled", false);
            },
        });
    }

    function renderSessions() {
        const $select = $("#zqpm-session-select");
        let html = '<option value="">— Sélectionner une session —</option>';

        sessionsData.forEach(function (session) {
            const label = session.formation_title
                ? session.formation_title + " - " + session.date
                : session.title + " - " + session.date;
            html += '<option value="' + session.id + '">' + label + "</option>";
        });

        $select.html(html);
        $(".zqpm-create-session-option").toggle(sessionsData.length === 0);
    }

    function updateSummary() {
        // Update formation summary
        const formationText = $("#zqpm-formation-select option:selected").text();
        $(".zqpm-summary-formation").text(formationText);

        // Update session summary
        const sessionText = $("#zqpm-session-select option:selected").text();
        $(".zqpm-summary-session").text(sessionText);
    }

    function createZQPM() {
        const $btn = $(".confirm-zqpm-creation");
        const $error = $(".zqpm-error-message");

        $error.hide();
        $btn.prop("disabled", true).html(
            '<span class="spinner is-active" style="float:none;margin:0;"></span> Création...'
        );

        $.ajax({
            url: formapressOpportunityZQPM.ajax_url,
            type: "POST",
            data: {
                action: "formapress_create_zqpm_from_opportunity",
                nonce: formapressOpportunityZQPM.nonce,
                opportunity_id: opportunityId,
                session_id: selectedSessionId,
                company_id: companyId,
                person_id: personId,
                formation_id: selectedFormationId,
            },
            success: function (response) {
                if (response.success && response.data.redirect_url) {
                    window.location.href = response.data.redirect_url;
                } else {
                    const message =
                        response.data && response.data.message ? response.data.message : "Erreur lors de la création.";
                    $error.html(message).fadeIn();
                    $btn.prop("disabled", false).text("Créer le Suivi de session");
                }
            },
            error: function () {
                $error.html("Erreur réseau. Veuillez réessayer.").fadeIn();
                $btn.prop("disabled", false).text("Créer le Suivi de session");
            },
        });
    }

    function openSessionModal() {
        const formationId = selectedFormationId;
        const formationName = $("#zqpm-formation-select option:selected").text();

        if (!formationId) {
            showError("Veuillez sélectionner une formation d'abord.");
            return;
        }

        // Show loading
        $("#zqpm-create-session-modal .zqpm-modal-body").html(
            '<div class="zqpm-loading"><span class="spinner is-active"></span> Chargement du formulaire...</div>'
        );
        $("#zqpm-create-session-modal").fadeIn(200);
        modal.addClass("dimmed");

        // Load existing zFormations session form
        $.ajax({
            url: formapressOpportunityZQPM.ajax_url,
            type: "POST",
            data: {
                action: "zform_get_session_form",
                formid: formationId,
            },
            success: function (html) {
                // Inject form into modal
                $("#zqpm-create-session-modal .zqpm-modal-body").html(html);

                // Add modal context flag to form
                $("#add_session_form").append('<input type="hidden" name="modal_context" value="1">');

                // Define helper functions for session form
                function addDateRow() {
                    console.log("[ZQPM Modal] addDateRow v11.0 - 2026-01-16 11:15");
                    var table = document.getElementById("zdates_meta_table");
                    if (!table) return;

                    var rows = table.rows;
                    var rowIndex = rows.length + 1;
                    var duration = 3.5;

                    if (typeof duration_default !== "undefined") {
                        duration = duration_default;
                    }

                    var newRow = table.insertRow(rows.length);
                    newRow.innerHTML = `
                        <td>
                            <fieldset class="grabbable_date_fieldset grab">
                                <span class="dashicons dashicons-move"></span>
                                <span class="dashicons dashicons-no"></span>
                                <h3>Jour ${rowIndex}</h3>
                                <input type="hidden" id="new_dates[${rowIndex}][order]" name="new_dates[${rowIndex}][order]" value="${rowIndex}"/>
                                <p>
                                    <input type="text" id="new_dates[${rowIndex}][date]" name="new_dates[${rowIndex}][date]" class="date-picker session_date_picker" required autocomplete="off"/>
                                </p>
                                <p class="box_demi_journees">
                                    <input type="checkbox" id="new_dates[${rowIndex}][morning]" name="new_dates[${rowIndex}][morning]"> Matin<br>
                                    <span>Durée (heures) : <input type="number" name="new_dates[${rowIndex}][morning_duration]" value="${duration}" min="0" step="0.5"></span>
                                </p>
                                <p class="box_demi_journees">
                                    <input type="checkbox" id="new_dates[${rowIndex}][afternoon]" name="new_dates[${rowIndex}][afternoon]"> Après-midi<br>
                                    <span>Durée (heures) : <input type="number" name="new_dates[${rowIndex}][afternoon_duration]" value="${duration}" min="0" step="0.5"></span>
                                </p>
                            </fieldset>
                        </td>
                    `;

                    var days_fieldset = document.getElementById("days");
                    if (days_fieldset) {
                        days_fieldset.style.maxHeight = days_fieldset.scrollHeight + "px";
                    }

                    var newDateInput = newRow.querySelector(".session_date_picker");
                    var $newDateInput = $(newDateInput);

                    // Initialize datepicker with simpler config
                    $newDateInput.attr("autocomplete", "off").datepicker({
                        beforeShowDay: $.datepicker.noWeekends,
                        minDate: "-12M -0D",
                        dateFormat: "dd/mm/yy",
                    });

                    // Force z-index on the datepicker div after initialization
                    $("#ui-datepicker-div").css("z-index", 100005);

                    console.log(
                        "[ZQPM Modal] Initialized datepicker for new row, hasDatepicker:",
                        $newDateInput.hasClass("hasDatepicker")
                    );

                    $(newRow)
                        .find(".dashicons-no")
                        .on("click", function (e) {
                            e.preventDefault();
                            deleteDateRow(this);
                        });

                    $(newRow)
                        .find(".dashicons-move")
                        .on("mousedown", function (e) {
                            initDragDateRow(e);
                        });

                    console.log("[ZQPM Modal] ✓✓✓ CODE VERSION v11.2 - 2026-01-16 11:21 ✓✓✓");
                    console.log("[ZQPM Modal] Added new date row:", rowIndex);
                }

                function deleteDateRow(deleteButton) {
                    var row = $(deleteButton).closest("tr")[0];
                    if (!row) return;

                    var table = row.parentNode.parentNode;
                    table.deleteRow(row.rowIndex);

                    var rows = table.rows;
                    for (var i = 0; i < rows.length; i++) {
                        var h3 = rows[i].querySelector("h3");
                        if (h3) {
                            h3.textContent = "Jour " + (i + 1);
                        }
                        var orderInput = rows[i].querySelector('input[type="hidden"]');
                        if (orderInput) {
                            orderInput.value = i + 1;
                        }
                    }

                    console.log("[ZQPM Modal] Deleted date row");
                }

                function initDragDateRow(e) {
                    var drag = false;
                    var $tr = $(e.target).closest("tr");
                    var startIndex = $tr.index();
                    var startY = e.pageY;
                    var $body = $(document.body);

                    $body.addClass("grabCursor").css("userSelect", "none");
                    $tr.addClass("grabbed");

                    function moveRow(e) {
                        if (!drag && Math.abs(e.pageY - startY) < 10) {
                            return;
                        }

                        drag = true;

                        $tr.siblings().each(function () {
                            var $sibling = $(this);
                            var siblingIndex = $sibling.index();
                            var siblingTop = $sibling.offset().top;

                            if (
                                siblingIndex >= 0 &&
                                e.pageY >= siblingTop &&
                                e.pageY < siblingTop + $sibling.outerHeight()
                            ) {
                                if (siblingIndex < $tr.index()) {
                                    $tr.insertAfter($sibling);
                                } else {
                                    $tr.insertBefore($sibling);
                                }
                                return false;
                            }
                        });
                    }

                    function stopDrag(e) {
                        if (drag && startIndex !== $tr.index()) {
                            var table = $tr.closest("table")[0];
                            var rows = table.rows;
                            for (var i = 0; i < rows.length; i++) {
                                var h3 = rows[i].querySelector("h3");
                                if (h3) {
                                    h3.textContent = "Jour " + (i + 1);
                                }
                                var orderInput = rows[i].querySelector('input[name*="[order]"]');
                                if (orderInput) {
                                    orderInput.value = i + 1;
                                }
                            }
                        }

                        $(document).off("mousemove", moveRow).off("mouseup", stopDrag);
                        $body.removeClass("grabCursor").css("userSelect", "");
                        $tr.removeClass("grabbed");
                    }

                    $(document).on("mousemove", moveRow).on("mouseup", stopDrag);
                }

                // Initialize session form
                console.log("[ZQPM Modal] Initializing session form in nested modal...");

                setTimeout(function () {
                    try {
                        // 1. Initialize date mode switching
                        var dates_mode_radio_btns = document.getElementsByName("dates_mode");
                        var dates_fieldsets = document.getElementsByClassName("zsessions_dates_modes");

                        function switch_dates_modes(radio_btn) {
                            for (var i = 0; i < dates_fieldsets.length; i++) {
                                var inputs = dates_fieldsets[i].getElementsByTagName("input");
                                if (dates_fieldsets[i].id == radio_btn.value) {
                                    dates_fieldsets[i].style.maxHeight = dates_fieldsets[i].scrollHeight + "px";
                                    Array.from(inputs).forEach(function (input) {
                                        if (input.type == "text" && input.name.substring(0, 9) != "new_dates") {
                                            input.required = true;
                                        }
                                    });
                                } else {
                                    dates_fieldsets[i].style.maxHeight = null;
                                    Array.from(inputs).forEach(function (input) {
                                        if (input.type == "text") {
                                            input.required = false;
                                        }
                                    });
                                }
                            }
                        }

                        Array.from(dates_mode_radio_btns).forEach(function (radio_btn) {
                            if (radio_btn.checked) {
                                switch_dates_modes(radio_btn);
                            }
                            $(radio_btn).click(function (e) {
                                switch_dates_modes(radio_btn);
                            });
                        });

                        // 2. Initialize ALL date pickers (both modes)
                        $(".session_date_picker, #start_date, #end_date").each(function () {
                            var $input = $(this);

                            var config = {
                                beforeShowDay: $.datepicker.noWeekends,
                                minDate: "-12M -0D",
                                dateFormat: "dd/mm/yy",
                                beforeShow: function (input, inst) {
                                    setTimeout(function () {
                                        inst.dpDiv.css({ zIndex: 100005 });
                                    }, 1);
                                },
                            };

                            if ($input.attr("id") === "start_date") {
                                config.onSelect = function (selected) {
                                    $("#end_date").datepicker("option", "minDate", selected);
                                };
                            } else if ($input.attr("id") === "end_date") {
                                config.onSelect = function (selected) {
                                    $("#start_date").datepicker("option", "maxDate", selected);
                                };
                            }

                            $input.attr("autocomplete", "off").datepicker(config);
                        });

                        console.log(
                            "[ZQPM Modal] Found",
                            $(".session_date_picker, #start_date, #end_date").length,
                            "date inputs initialized"
                        );

                        // Add delegated click handlers that work for all inputs (even future ones)
                        $("#zqpm-create-session-modal").on(
                            "click focus",
                            ".session_date_picker, #start_date, #end_date",
                            function (e) {
                                var $input = $(this);
                                if ($input.hasClass("hasDatepicker")) {
                                    e.preventDefault();
                                    e.stopPropagation();
                                    console.log(
                                        "[ZQPM Modal] Showing datepicker for",
                                        $input.attr("name") || $input.attr("id")
                                    );

                                    // Force z-index before showing
                                    $("#ui-datepicker-div").css("z-index", 100005);

                                    // Show datepicker
                                    $input.datepicker("show");

                                    // Verify it's visible
                                    setTimeout(function () {
                                        var $picker = $("#ui-datepicker-div");
                                        console.log(
                                            "[ZQPM Modal] Datepicker visible:",
                                            $picker.is(":visible"),
                                            "display:",
                                            $picker.css("display"),
                                            "z-index:",
                                            $picker.css("z-index")
                                        );
                                    }, 50);
                                } else {
                                    console.log(
                                        "[ZQPM Modal] Input not initialized:",
                                        $input.attr("name") || $input.attr("id")
                                    );
                                }
                            }
                        );

                        // 3. Initialize "Add day" button
                        $(".d_add_row")
                            .off("click")
                            .on("click", function (e) {
                                e.preventDefault();
                                addDateRow();
                            });

                        // 4. Initialize delete buttons for existing date rows
                        $(".grabbable_date_fieldset .dashicons-no")
                            .off("click")
                            .on("click", function (e) {
                                e.preventDefault();
                                deleteDateRow(this);
                            });

                        // 5. Initialize drag handles for date row reordering
                        $(".grabbable_date_fieldset .dashicons-move")
                            .off("mousedown")
                            .on("mousedown", function (e) {
                                initDragDateRow(e);
                            });

                        console.log("[ZQPM Modal] Session form initialized successfully");
                    } catch (error) {
                        console.error("[ZQPM Modal] Error initializing session form:", error);
                    }
                }, 300);

                // Hijack the form submit
                hijackSessionFormSubmit();

                // Replace cancel button behavior
                $("#cancelbtn")
                    .off("click")
                    .on("click", function (e) {
                        e.preventDefault();
                        closeSessionModal();
                    });
            },
            error: function () {
                $("#zqpm-create-session-modal .zqpm-modal-body").html(
                    '<div class="zqpm-error">Erreur lors du chargement du formulaire.</div>'
                );
            },
        });
    }

    function closeSessionModal() {
        $("#zqpm-create-session-modal").fadeOut(200);
        modal.removeClass("dimmed");

        // Clear modal content after animation
        setTimeout(function () {
            $("#zqpm-create-session-modal .zqpm-modal-body").empty();
        }, 300);
    }

    function hijackSessionFormSubmit() {
        // Remove default submit handler and add our own
        $("#add_session_form")
            .off("submit")
            .on("submit", function (e) {
                e.preventDefault();

                const $form = $(this);
                const $submitBtn = $("#submitbtn");
                const originalBtnText = $submitBtn.text();

                // Disable submit button
                $submitBtn.prop("disabled", true).html('<span class="spinner is-active"></span> Création...');

                // Submit via AJAX
                $.ajax({
                    url: formapressOpportunityZQPM.ajax_url,
                    type: "POST",
                    data: $form.serialize(),
                    dataType: "json",
                    success: function (response) {
                        if (response.type === "success" && response.session_id) {
                            // Add new session to parent modal
                            addSessionToParentModal(response);

                            // Close nested modal
                            closeSessionModal();

                            // Show success in parent modal
                            showError("✓ Session créée avec succès !", "success");
                        } else {
                            alert("Erreur lors de la création de la session.");
                            $submitBtn.prop("disabled", false).text(originalBtnText);
                        }
                    },
                    error: function (xhr, status, error) {
                        console.error("Session creation error:", error);
                        alert("Erreur réseau. Veuillez réessayer.");
                        $submitBtn.prop("disabled", false).text(originalBtnText);
                    },
                });

                return false;
            });
    }

    function addSessionToParentModal(sessionData) {
        // Build session display label
        const sessionLabel =
            sessionData.formation_title + " - " + (sessionData.session_date || sessionData.session_title);

        // Add to select dropdown
        const $parentSelect = $("#zqpm-session-select");
        const newOption = new Option(sessionLabel, sessionData.session_id, true, true);
        $parentSelect.append(newOption);

        // Update selected session ID
        selectedSessionId = sessionData.session_id;

        // Update sessions data array
        sessionsData.push({
            id: sessionData.session_id,
            title: sessionData.session_title,
            date: sessionData.session_date,
            formation_title: sessionData.formation_title,
        });

        // Hide "no sessions" message if it was showing
        $(".zqpm-create-session-option").hide();
    }
})(jQuery);
