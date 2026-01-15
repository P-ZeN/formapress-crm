/**
 * Invoice Editor JavaScript
 *
 * Handles interactions for the custom invoice editor.
 */

(function ($) {
    "use strict";

    $(document).ready(function () {
        // Initialize Select2 for searchable dropdowns
        initializeSelect2();

        // Form validation
        $("#invoice-editor-form").on("submit", function (e) {
            if (!validateInvoiceForm()) {
                e.preventDefault();
                return false;
            }
        });

        // Auto-calculate due date when invoice date changes
        $("#invoice_date").on("change", function () {
            var invoiceDate = $(this).val();
            if (invoiceDate && !$("#due_date").val()) {
                var date = new Date(invoiceDate);
                date.setDate(date.getDate() + 30);
                var dueDate = date.toISOString().split("T")[0];
                $("#due_date").val(dueDate);
            }
        });
    });

    /**
     * Initialize Select2 for AJAX dropdowns
     */
    function initializeSelect2() {
        // Initialize Select2 for AJAX-powered dropdowns
        $(".select2-ajax").each(function () {
            var $select = $(this);
            var ajaxAction = $select.data("ajax-action");

            // Special handling for person search (include company filter)
            if (ajaxAction === "formapress_search_persons") {
                $select.select2({
                    ajax: {
                        url: formapressInvoiceEditor.ajax_url,
                        dataType: "json",
                        delay: 250,
                        data: function (params) {
                            var companyId = $("#company_id").val();
                            return {
                                action: ajaxAction,
                                term: params.term,
                                company_id: companyId,
                                nonce: formapressInvoiceEditor.nonce,
                            };
                        },
                        processResults: function (data) {
                            return {
                                results: data.results,
                            };
                        },
                        cache: true,
                    },
                    minimumInputLength: 0,
                    placeholder: $(this).find("option:first").text(),
                    allowClear: true,
                    width: "100%",
                });
            } else {
                $select.select2({
                    ajax: {
                        url: formapressInvoiceEditor.ajax_url,
                        dataType: "json",
                        delay: 250,
                        data: function (params) {
                            return {
                                action: ajaxAction,
                                term: params.term,
                                nonce: formapressInvoiceEditor.nonce,
                            };
                        },
                        processResults: function (data) {
                            return {
                                results: data.results,
                            };
                        },
                        cache: true,
                    },
                    minimumInputLength: 2,
                    placeholder: $(this).find("option:first").text(),
                    allowClear: true,
                    width: "100%",
                });
            }
        });

        // Handle company selection change - load contacts
        $("#company_id").on("change", function () {
            var companyId = $(this).val();
            var $personSelect = $("#person_id");

            // Clear current person selection
            $personSelect.val(null).trigger("change");

            if (companyId) {
                // Fetch contacts for this company
                $.ajax({
                    url: formapressInvoiceEditor.ajax_url,
                    type: "POST",
                    data: {
                        action: "formapress_crm_get_company_contacts",
                        company_id: companyId,
                        nonce: formapressInvoiceEditor.nonce,
                    },
                    success: function (response) {
                        if (response.success && response.data.contacts.length > 0) {
                            // Clear existing options except placeholder
                            $personSelect.empty().append('<option value="">— Sélectionner un contact —</option>');

                            // Add contacts as options
                            $.each(response.data.contacts, function (index, contact) {
                                var option = new Option(contact.text, contact.id, false, index === 0); // Select first contact by default
                                $personSelect.append(option);
                            });

                            // Trigger change to update Select2
                            $personSelect.trigger("change");

                            // Show alert if no contact selected
                            if (response.data.contacts.length === 0) {
                                alert(
                                    "Attention: Cette entreprise n'a aucun contact enregistré. Veuillez en créer un avant de créer la facture."
                                );
                            }
                        } else if (response.success && response.data.contacts.length === 0) {
                            // No contacts found
                            $personSelect.empty().append('<option value="">— Aucun contact disponible —</option>');
                            alert(
                                "Attention: Cette entreprise n'a aucun contact enregistré. Veuillez en créer un avant de créer la facture."
                            );
                        }
                    },
                });
            }
        });
    }

    /**
     * Validate invoice form before submission
     */
    function validateInvoiceForm() {
        var errors = [];

        // Check title
        var title = $("#invoice_title").val().trim();
        if (!title) {
            errors.push("Le titre de la facture est requis.");
        }

        // Check amount
        var amount = parseFloat($("#amount").val());
        if (isNaN(amount) || amount <= 0) {
            errors.push("Le montant doit être supérieur à 0.");
        }

        // Check invoice date
        var invoiceDate = $("#invoice_date").val();
        if (!invoiceDate) {
            errors.push("La date de facture est requise.");
        }

        // Check contact person is selected
        var personId = $("#person_id").val();
        if (!personId) {
            errors.push("Un contact est requis. Veuillez sélectionner un contact.");
        }

        // Show errors if any
        if (errors.length > 0) {
            alert("Erreurs de validation:\n\n" + errors.join("\n"));
            return false;
        }

        return true;
    }

    /**
     * Payment Tracking System
     */
    var payments = [];
    var currentPaymentIndex = null;

    // Load existing payments on page load
    function loadPaymentsFromForm() {
        $('[name="invoice_payments[]"]').each(function () {
            try {
                var payment = JSON.parse($(this).val());
                payments.push(payment);
            } catch (e) {
                console.error("Failed to parse payment:", e);
            }
        });
        renderPaymentsList();
        updateBalanceSummary();
    }

    // Render payments list
    function renderPaymentsList() {
        var $list = $(".payments-list");
        $list.empty();

        if (payments.length === 0) {
            $list.html('<p class="no-payments">Aucun paiement enregistré</p>');
            return;
        }

        payments.forEach(function (payment, index) {
            var html =
                '<div class="payment-item" data-index="' +
                index +
                '">' +
                '<div class="payment-item-content">' +
                '<span class="payment-date">' +
                '<span class="dashicons dashicons-calendar-alt"></span> ' +
                formatDate(payment.date) +
                "</span>" +
                '<span class="payment-amount">' +
                formatCurrency(payment.amount) +
                "</span>" +
                '<span class="payment-method">' +
                getPaymentMethodLabel(payment.method) +
                "</span>";

            if (payment.reference) {
                html += '<span class="payment-reference">Réf: ' + escapeHtml(payment.reference) + "</span>";
            }

            html +=
                "</div>" +
                '<div class="payment-item-actions">' +
                '<button type="button" class="button edit-payment-btn" data-index="' +
                index +
                '">' +
                '<span class="dashicons dashicons-edit"></span> Modifier' +
                "</button>" +
                '<button type="button" class="button delete-payment-btn" data-index="' +
                index +
                '">' +
                '<span class="dashicons dashicons-trash"></span> Supprimer' +
                "</button>" +
                "</div>" +
                "</div>";

            $list.append(html);
        });

        // Update hidden form fields
        updateHiddenPaymentFields();
    }

    // Update hidden form fields with payment data
    function updateHiddenPaymentFields() {
        $('[name="invoice_payments[]"]').remove();

        payments.forEach(function (payment) {
            $("<input>")
                .attr("type", "hidden")
                .attr("name", "invoice_payments[]")
                .val(JSON.stringify(payment))
                .appendTo("#invoice-editor-form");
        });
    }

    // Update balance summary
    function updateBalanceSummary() {
        var totalAmount = parseFloat($("#amount").val()) || 0;
        var totalPaid = 0;

        payments.forEach(function (payment) {
            totalPaid += parseFloat(payment.amount) || 0;
        });

        var balanceDue = totalAmount - totalPaid;

        $(".total-amount").text(formatCurrency(totalAmount));
        $(".total-paid").text(formatCurrency(totalPaid));
        $(".balance-due").text(formatCurrency(balanceDue));

        // Update payment status badge if needed
        var $statusField = $("#payment_status");
        if (balanceDue <= 0) {
            $statusField.val("paid");
        } else if (totalPaid > 0) {
            $statusField.val("partial");
        } else {
            $statusField.val("pending");
        }
    }

    // Open payment modal for adding
    $(document).on("click", "#add-payment-btn", function (e) {
        e.preventDefault();
        currentPaymentIndex = null;
        openPaymentModal();
    });

    // Open payment modal for editing
    $(document).on("click", ".edit-payment-btn", function (e) {
        e.preventDefault();
        currentPaymentIndex = $(this).data("index");
        var payment = payments[currentPaymentIndex];
        openPaymentModal(payment);
    });

    // Delete payment
    $(document).on("click", ".delete-payment-btn", function (e) {
        e.preventDefault();
        var index = $(this).data("index");

        if (confirm("Êtes-vous sûr de vouloir supprimer ce paiement ?")) {
            payments.splice(index, 1);
            renderPaymentsList();
            updateBalanceSummary();
        }
    });

    // Open payment modal
    function openPaymentModal(payment) {
        if (payment) {
            $("#payment_date").val(payment.date || "");
            $("#payment_amount").val(payment.amount || "");
            $("#payment_method").val(payment.method || "check");
            $("#payment_reference").val(payment.reference || "");
            $("#payment_notes").val(payment.notes || "");
            $(".payment-modal h3").text("Modifier un paiement");
        } else {
            $("#payment-form")[0].reset();
            $("#payment_date").val(new Date().toISOString().split("T")[0]);
            $(".payment-modal h3").text("Ajouter un paiement");
        }

        $(".payment-modal").fadeIn(200);
    }

    // Close payment modal
    $(document).on("click", ".payment-modal-close, #cancel-payment-btn", function (e) {
        e.preventDefault();
        $(".payment-modal").fadeOut(200);
    });

    // Close modal on backdrop click
    $(document).on("click", ".payment-modal", function (e) {
        if ($(e.target).hasClass("payment-modal")) {
            $(".payment-modal").fadeOut(200);
        }
    });

    // Save payment
    $(document).on("click", "#save-payment-btn", function (e) {
        e.preventDefault();

        // Validate payment form
        var paymentDate = $("#payment_date").val();
        var paymentAmount = parseFloat($("#payment_amount").val());
        var paymentMethod = $("#payment_method").val();

        if (!paymentDate) {
            alert("La date du paiement est requise.");
            return;
        }

        if (isNaN(paymentAmount) || paymentAmount <= 0) {
            alert("Le montant doit être supérieur à 0.");
            return;
        }

        // Create payment object
        var payment = {
            date: paymentDate,
            amount: paymentAmount,
            method: paymentMethod,
            reference: $("#payment_reference").val(),
            notes: $("#payment_notes").val(),
        };

        // Add or update payment
        if (currentPaymentIndex !== null) {
            payments[currentPaymentIndex] = payment;
        } else {
            payments.push(payment);
        }

        // Update UI
        renderPaymentsList();
        updateBalanceSummary();
        $(".payment-modal").fadeOut(200);
    });

    // Update balance when amount changes
    $("#amount").on("change", function () {
        updateBalanceSummary();
    });

    // Helper functions
    function formatCurrency(amount) {
        return new Intl.NumberFormat("fr-FR", {
            style: "currency",
            currency: "EUR",
        }).format(amount);
    }

    function formatDate(dateString) {
        var date = new Date(dateString);
        return new Intl.DateTimeFormat("fr-FR").format(date);
    }

    function getPaymentMethodLabel(method) {
        var methods = {
            check: "Chèque",
            bank_transfer: "Virement",
            cash: "Espèces",
            card: "Carte bancaire",
            other: "Autre",
        };
        return methods[method] || method;
    }

    function escapeHtml(text) {
        var map = {
            "&": "&amp;",
            "<": "&lt;",
            ">": "&gt;",
            '"': "&quot;",
            "'": "&#039;",
        };
        return text.replace(/[&<>"']/g, function (m) {
            return map[m];
        });
    }

    // Initialize payments on page load
    loadPaymentsFromForm();
})(jQuery);
