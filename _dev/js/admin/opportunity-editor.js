/**
 * FormaPress CRM - Opportunity Editor JavaScript
 *
 * Handles:
 * - Select2 initialization for entity pickers
 * - AJAX search for persons and companies
 * - Form validation and submission
 */

(function ($) {
    "use strict";

    $(document).ready(function () {
        initEntitySelectors();
        initFormValidation();
        initStageChange();
        initQuickActions();
        initActivityTimeline();
        initProbabilitySlider();
    });

    /**
     * Initialize Select2 for entity selectors (person, company)
     */
    function initEntitySelectors() {
        const $personSelect = $("#associated-person");
        const $companySelect = $("#associated-company");
        const $formationSelect = $("#associated-formation");

        // Initialize Formation Select2
        $formationSelect.select2({
            width: "100%",
            placeholder: "Rechercher une formation...",
            allowClear: true,
            ajax: {
                url: formapressOpportunityEditor.ajax_url,
                dataType: "json",
                delay: formapressOpportunityEditor.ajax_delay || 300,
                data: function (params) {
                    return {
                        action: "formapress_search_entities",
                        nonce: formapressOpportunityEditor.nonce,
                        entity_type: "formation",
                        search: params.term,
                    };
                },
                processResults: function (data) {
                    return {
                        results: data.results || [],
                    };
                },
                cache: true,
            },
            minimumInputLength: 2,
            language: {
                inputTooShort: function () {
                    return "Entrez au moins 2 caractères...";
                },
                searching: function () {
                    return "Recherche...";
                },
                noResults: function () {
                    return "Aucune formation trouvée";
                },
                errorLoading: function () {
                    return "Erreur lors du chargement des résultats";
                },
            },
        });

        // Initialize Person Select2 (company_contact only, filtered by company if selected)
        $personSelect.select2({
            width: "100%",
            placeholder: "Rechercher un contact...",
            allowClear: true,
            ajax: {
                url: formapressOpportunityEditor.ajax_url,
                dataType: "json",
                delay: formapressOpportunityEditor.ajax_delay || 300,
                data: function (params) {
                    const companyId = $companySelect.val();
                    return {
                        action: "formapress_search_entities",
                        nonce: formapressOpportunityEditor.nonce,
                        entity_type: "person",
                        search: params.term,
                        company_id: companyId || "",
                    };
                },
                processResults: function (data) {
                    return {
                        results: data.results || [],
                    };
                },
                cache: true,
            },
            minimumInputLength: 2,
            language: {
                inputTooShort: function () {
                    return "Entrez au moins 2 caractères...";
                },
                searching: function () {
                    return "Recherche...";
                },
                noResults: function () {
                    const companyId = $companySelect.val();
                    return companyId ? "Aucun contact trouvé pour cette entreprise" : "Aucun contact trouvé";
                },
                errorLoading: function () {
                    return "Erreur lors du chargement des résultats";
                },
            },
        });

        // Initialize Company Select2
        $companySelect.select2({
            width: "100%",
            placeholder: "Rechercher une entreprise...",
            allowClear: true,
            ajax: {
                url: formapressOpportunityEditor.ajax_url,
                dataType: "json",
                delay: formapressOpportunityEditor.ajax_delay || 300,
                data: function (params) {
                    return {
                        action: "formapress_search_entities",
                        nonce: formapressOpportunityEditor.nonce,
                        entity_type: "company",
                        search: params.term,
                    };
                },
                processResults: function (data) {
                    return {
                        results: data.results || [],
                    };
                },
                cache: true,
            },
            minimumInputLength: 2,
            language: {
                inputTooShort: function () {
                    return "Entrez au moins 2 caractères...";
                },
                searching: function () {
                    return "Recherche...";
                },
                noResults: function () {
                    return "Aucune entreprise trouvée";
                },
                errorLoading: function () {
                    return "Erreur lors du chargement des résultats";
                },
            },
        });

        // Cascading logic: When person is selected, auto-populate company
        $personSelect.on("select2:select", function (e) {
            const personId = e.params.data.id;

            console.log("Person selected:", personId);

            // Fetch person's companies
            $.ajax({
                url: formapressOpportunityEditor.ajax_url,
                type: "GET",
                data: {
                    action: "formapress_crm_get_person_companies",
                    nonce: formapressOpportunityEditor.nonce,
                    person_id: personId,
                },
                success: function (response) {
                    console.log("AJAX response:", response);

                    if (response.success && response.data.companies && response.data.companies.length > 0) {
                        const companies = response.data.companies;
                        console.log("Companies found:", companies);

                        if (companies.length === 1) {
                            // Auto-select single company
                            const company = companies[0];
                            $companySelect.empty();
                            const newOption = new Option(company.text, company.id, true, true);
                            $companySelect.append(newOption).trigger("change");
                            console.log("Auto-selected single company:", company);
                        } else {
                            // Multiple companies: populate dropdown with all options
                            $companySelect.empty();

                            // Add empty placeholder option
                            const placeholderOption = new Option(
                                "Sélectionnez une entreprise (" + companies.length + " disponibles)",
                                "",
                                true,
                                true
                            );
                            $companySelect.append(placeholderOption);

                            // Add all company options
                            companies.forEach(function (company) {
                                const option = new Option(company.text, company.id, false, false);
                                $companySelect.append(option);
                            });

                            // Trigger change and open dropdown to show user the options
                            $companySelect.val("").trigger("change");

                            // Small delay before opening to ensure Select2 is ready
                            setTimeout(function () {
                                $companySelect.select2("open");
                            }, 100);

                            console.log("Populated dropdown with", companies.length, "companies");
                        }
                    } else {
                        // No companies found for this person
                        console.warn("Aucune entreprise trouvée pour ce contact #" + personId);
                        $companySelect.empty().trigger("change");
                    }
                },
                error: function (xhr, status, error) {
                    console.error("Error fetching person companies:", error);
                    console.error("Response:", xhr.responseText);
                },
            });
        });

        // Cascading logic: When company is selected, clear person and force re-search
        $companySelect.on("select2:select", function () {
            // If person is already selected, verify it belongs to this company
            const personId = $personSelect.val();
            if (personId) {
                // Could validate, but for now just clear to force re-selection
                $personSelect.val(null).trigger("change");
            }
        });

        // When company is cleared, allow all company_contact persons again
        $companySelect.on("select2:clear", function () {
            // Person select will automatically adjust on next search
        });
    }

    /**
     * Initialize form validation
     */
    function initFormValidation() {
        $("#opportunity-editor-form").on("submit", function (e) {
            // Basic HTML5 validation will handle required fields
            const $form = $(this);
            const $submitBtn = $("#save-opportunity");

            // Check if title is filled
            const title = $("#opportunity-title").val().trim();
            if (!title) {
                e.preventDefault();
                alert("Veuillez saisir un titre pour l'opportunité.");
                $("#opportunity-title").focus();
                return false;
            }

            // Disable submit button to prevent double submission
            $submitBtn.prop("disabled", true).text("Enregistrement en cours...");

            // Form will submit normally to admin-post.php
            return true;
        });
    }

    /**
     * Handle stage change to show/hide ZQPM section
     */
    function initStageChange() {
        $("#opportunity-stage").on("change", function () {
            const stage = $(this).val();
            const $zqpmPanel = $(".editor-panel:has(#zqpm-id)");

            // Only show ZQPM link panel when stage is "won"
            if (stage === "won") {
                $zqpmPanel.show();
            } else {
                $zqpmPanel.hide();
            }
        });
    }

    /**
     * Initialize Quick Actions functionality
     */
    function initQuickActions() {
        // Email template buttons trigger communication modal
        $(".email-template-btn").on("click", function () {
            const templateId = $(this).data("template-id");

            // Trigger modal with email template (communication-modal.js handles this)
            $(document).trigger("open-communication-modal", {
                templateId: templateId,
                templateType: "email_template",
            });
        });

        // Preview PDF buttons open preview in new tab
        $(".preview-pdf-btn").on("click", function () {
            const templateId = $(this).data("template-id");
            const opportunityId = $(this).data("opportunity-id");

            // Build preview URL
            const previewUrl =
                formapressOpportunityEditor.ajax_url +
                "?action=formapress_crm_preview_pdf" +
                "&template_id=" +
                templateId +
                "&opportunity_id=" +
                opportunityId +
                "&nonce=" +
                formapressOpportunityEditor.nonce;

            // Open in new tab
            window.open(previewUrl, "_blank");
        });

        // Add activity button
        $(".add-activity-btn, .toggle-add-activity").on("click", function () {
            $(".activity-quick-add").slideToggle(300);
        });

        // Cancel add activity
        $(".cancel-add-activity").on("click", function () {
            $(".activity-quick-add").slideUp(300);
            $("#quick-add-activity-form")[0].reset();
        });
    }

    /**
     * Initialize Activity Timeline functionality
     */
    function initActivityTimeline() {
        // Timeline filters
        $(".timeline-filter").on("click", function () {
            const filter = $(this).data("filter");

            // Update active state
            $(".timeline-filter").removeClass("active");
            $(this).addClass("active");

            // Filter timeline items
            if (filter === "all") {
                $(".timeline-item").show();
            } else {
                $(".timeline-item").hide();
                $(`.timeline-item[data-status="${filter}"]`).show();
            }
        });

        // Activity form submission
        $("#quick-add-activity-form").on("submit", function (e) {
            e.preventDefault();

            const $form = $(this);
            const $submitBtn = $form.find('button[type="submit"]');
            const formData = new FormData(this);

            // Add AJAX action
            formData.append("action", "formapress_add_activity");

            // Disable submit button
            $submitBtn
                .prop("disabled", true)
                .html('<span class="dashicons dashicons-update-alt"></span> Enregistrement...');

            $.ajax({
                url: formapressOpportunityEditor.ajax_url,
                method: "POST",
                data: formData,
                processData: false,
                contentType: false,
                success: function (response) {
                    if (response.success) {
                        // Show success message
                        alert(response.data.message || "Activité ajoutée avec succès.");

                        // Reload page to show new activity
                        window.location.reload();
                    } else {
                        alert(response.data.message || "Erreur lors de l'ajout de l'activité.");
                        $submitBtn
                            .prop("disabled", false)
                            .html('<span class="dashicons dashicons-yes"></span> Ajouter l\'activité');
                    }
                },
                error: function (xhr, status, error) {
                    console.error("AJAX error:", error);
                    alert("Erreur lors de la communication avec le serveur.");
                    $submitBtn
                        .prop("disabled", false)
                        .html('<span class="dashicons dashicons-yes"></span> Ajouter l\'activité');
                },
            });
        });
    }

    /**
     * Initialize probability slider
     */
    function initProbabilitySlider() {
        const $probabilitySlider = $("#opportunity-probability");
        const $probabilityDisplay = $("#probability-display");

        if ($probabilitySlider.length && $probabilityDisplay.length) {
            // Update display when slider moves
            $probabilitySlider.on("input", function () {
                const value = $(this).val();
                $probabilityDisplay.text(value + "%");
            });
        }
    }

    /**
     * Initialize pipeline step clicks
     */
    function initStageChange() {
        const $stageSteps = $(".stage-step");
        const $stageInput = $("#opportunity-stage");

        if ($stageSteps.length && $stageInput.length) {
            $stageSteps.on("click", function () {
                const newStage = $(this).data("stage");

                // Update hidden input
                $stageInput.val(newStage);

                // Update visual state
                $stageSteps.removeClass("current past");
                $(this).addClass("current");

                // Mark previous steps as past
                $(this).prevAll(".stage-step").addClass("past");

                // Show success feedback
                $(this).css("transform", "scale(1.05)");
                setTimeout(() => {
                    $(this).css("transform", "");
                }, 200);
            });
        }
    }
})(jQuery);
