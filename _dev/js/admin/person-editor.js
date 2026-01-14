/**
 * FormaPress CRM - Person Editor JavaScript
 *
 * Handles:
 * - Select2 initialization for company multi-select
 * - Person type checkbox interactions
 * - Dynamic attribute panels based on selected types
 * - Form validation and submission
 */

(function ($) {
    "use strict";

    $(document).ready(function () {
        initCompanySelector();
        initPersonTypeToggles();
        initFormValidation();
    });

    /**
     * Initialize Select2 for company multi-select
     */
    function initCompanySelector() {
        const $companySelect = $("#associated-companies");

        if (!$companySelect.length) {
            return;
        }

        $companySelect.select2({
            width: "100%",
            placeholder: "Rechercher des entreprises...",
            allowClear: true,
            multiple: true,
            ajax: {
                url: formapressPersonEditor.ajax_url,
                dataType: "json",
                delay: 300,
                data: function (params) {
                    return {
                        action: "formapress_search_companies_for_person",
                        nonce: formapressPersonEditor.nonce,
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
    }

    /**
     * Handle person type checkbox interactions
     */
    function initPersonTypeToggles() {
        const $typeCheckboxes = $('input[name="person_types[]"]');

        $typeCheckboxes.on("change", function () {
            updateTypeBadges();
        });

        // Initialize badge display
        updateTypeBadges();
    }

    /**
     * Update person type badges in header
     */
    function updateTypeBadges() {
        const $badgeContainer = $(".banner-types");
        const $typeCheckboxes = $('input[name="person_types[]"]:checked');

        if (!$badgeContainer.length) {
            return;
        }

        // Clear existing badges
        $badgeContainer.empty();

        // Add badge for each selected type
        $typeCheckboxes.each(function () {
            const typeSlug = $(this).val();
            const typeLabel = $(this).closest("label").find("span").first().text();
            const typeIcon = getTypeIcon(typeSlug);

            const $badge = $('<span class="person-type-badge" data-type="' + typeSlug + '"></span>')
                .append('<span class="dashicons ' + typeIcon + '"></span>')
                .append(" " + typeLabel);

            $badgeContainer.append($badge);
        });

        // Show placeholder if no types selected
        if ($typeCheckboxes.length === 0) {
            $badgeContainer.html('<span class="no-types">Aucun type sélectionné</span>');
        }
    }

    /**
     * Get icon for person type
     */
    function getTypeIcon(typeSlug) {
        const icons = {
            instructor: "dashicons-welcome-learn-more",
            trainee: "dashicons-welcome-learn-more",
            company_contact: "dashicons-businessman",
            prospect: "dashicons-visibility",
            funder_contact: "dashicons-money-alt",
        };
        return icons[typeSlug] || "dashicons-admin-users";
    }

    /**
     * Form validation
     */
    function initFormValidation() {
        const $form = $("#person-editor-form");

        $form.on("submit", function (e) {
            const errors = [];

            // Validate required fields
            const prenom = $("#person_prenom").val().trim();
            const nom = $("#person_nom").val().trim();
            const email = $("#person_email").val().trim();

            if (!prenom) {
                errors.push("Le prénom est requis.");
            }

            if (!nom) {
                errors.push("Le nom est requis.");
            }

            if (!email) {
                errors.push("L'email est requis.");
            } else if (!isValidEmail(email)) {
                errors.push("L'email n'est pas valide.");
            }

            // Check that at least one person type is selected
            const $typeCheckboxes = $('input[name="person_types[]"]:checked');
            if ($typeCheckboxes.length === 0) {
                errors.push("Veuillez sélectionner au moins un type de personne.");
            }

            // Display errors if any
            if (errors.length > 0) {
                e.preventDefault();
                alert(errors.join("\n"));
                return false;
            }

            return true;
        });
    }

    /**
     * Validate email format
     */
    function isValidEmail(email) {
        const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return re.test(email);
    }
})(jQuery);
