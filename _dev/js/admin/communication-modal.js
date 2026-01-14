/**
 * FormaPress CRM - Communication Modal
 *
 * Handles modal interactions for sending templated communications.
 */

(function ($) {
    "use strict";

    const CommunicationModal = {
        modal: null,
        form: null,
        currentTemplateId: null,

        /**
         * Initialize modal functionality
         */
        init() {
            this.modal = $("#crm-communication-modal");
            this.form = $("#crm-send-communication-form");

            if (!this.modal.length || !this.form.length) {
                return;
            }

            this.bindEvents();
        },

        /**
         * Bind event handlers
         */
        bindEvents() {
            // Close modal handlers
            this.modal.find(".crm-modal-close, .crm-modal-cancel").on("click", () => this.close());
            this.modal.find(".crm-modal-overlay").on("click", () => this.close());

            // Escape key closes modal
            $(document).on("keydown", (e) => {
                if (e.key === "Escape" && this.modal.is(":visible")) {
                    this.close();
                }
            });

            // Form submission
            this.form.on("submit", (e) => {
                e.preventDefault();
                this.send();
            });

            // Listen for custom event from opportunity-editor.js
            $(document).on("open-communication-modal", (e, data) => {
                if (data.templateId && data.templateType === "email_template") {
                    this.openWithEmailTemplate(data.templateId);
                } else if (data.documentTemplateId) {
                    // Open modal with document pre-selected
                    this.openWithDocument(data.documentTemplateId);
                } else {
                    this.open();
                }
            });

            // Legacy: Listen for template button clicks directly (fallback)
            $(document).on("click", ".template-action-button", (e) => {
                e.preventDefault();
                const $button = $(e.currentTarget);
                const templateId = $button.data("template-id");
                const templateType = $button.data("template-type");

                if (templateType === "email_template") {
                    this.openWithEmailTemplate(templateId);
                } else {
                    this.open();
                }
            });
        },

        /**
         * Open modal with email template pre-filled
         */
        openWithEmailTemplate(templateId) {
            const opportunityId = this.form.find('input[name="opportunity_id"]').val();
            const personId = this.form.find('input[name="person_id"]').val();
            const companyId = this.form.find('input[name="company_id"]').val();

            // Show loading state
            this.form.find(".comm-loading").show();
            this.open();

            // Fetch template content via AJAX
            $.ajax({
                url: formapressCrmModal.ajaxUrl,
                type: "POST",
                data: {
                    action: "formapress_crm_preview_email_template",
                    nonce: formapressCrmModal.previewNonce,
                    template_id: templateId,
                    opportunity_id: opportunityId,
                    person_id: personId,
                    company_id: companyId,
                },
                success: (response) => {
                    if (response.success) {
                        this.form.find('input[name="email_template_id"]').val(templateId);
                        this.form.find("#comm-subject").val(response.data.subject);

                        // Set TinyMCE editor content
                        if (typeof tinymce !== "undefined" && tinymce.get("comm_body")) {
                            tinymce.get("comm_body").setContent(response.data.body);
                        } else {
                            // Fallback if TinyMCE not loaded yet
                            this.form.find("#comm_body").val(response.data.body);
                        }
                    } else {
                        this.showError(response.data.message || formapressCrmModal.i18n.error);
                    }
                },
                error: () => {
                    this.showError(formapressCrmModal.i18n.error);
                },
                complete: () => {
                    this.form.find(".comm-loading").hide();
                },
            });
        },

        /**
         * Open modal with document template pre-selected
         */
        openWithDocument(documentTemplateId) {
            this.open();

            // Pre-check the document template checkbox
            this.form
                .find(`input[name="document_template_ids[]"][value="${documentTemplateId}"]`)
                .prop("checked", true);
        },

        /**
         * Open modal (empty or with data)
         */
        open() {
            this.modal.fadeIn(200);
            this.form.find(".comm-error").hide();
            $("body").addClass("crm-modal-open");
        },

        /**
         * Close modal
         */
        close() {
            this.modal.fadeOut(200);
            this.reset();
            $("body").removeClass("crm-modal-open");
        },

        /**
         * Reset form to empty state
         */
        reset() {
            this.form[0].reset();
            this.form.find('input[name="email_template_id"]').val("");
            this.form.find(".comm-error").hide();
            this.form.find(".comm-loading").hide();
            this.currentTemplateId = null;

            // Clear TinyMCE editor content
            if (typeof tinymce !== "undefined" && tinymce.get("comm_body")) {
                tinymce.get("comm_body").setContent("");
            }
        },

        /**
         * Send communication via AJAX
         */
        send() {
            // Trigger TinyMCE save to sync editor content to textarea
            if (typeof tinymce !== "undefined") {
                tinymce.triggerSave();
            }

            // Validate form
            if (!this.form[0].checkValidity()) {
                this.form[0].reportValidity();
                return;
            }

            // Show loading state
            this.form.find(".comm-loading").show();
            this.form.find(".crm-modal-send").prop("disabled", true);
            this.form.find(".comm-error").hide();

            // Prepare form data
            const formData = new FormData(this.form[0]);
            formData.append("action", "formapress_crm_send_communication");
            formData.append("nonce", formapressCrmModal.sendNonce);

            // Send via AJAX
            $.ajax({
                url: formapressCrmModal.ajaxUrl,
                type: "POST",
                data: formData,
                processData: false,
                contentType: false,
                success: (response) => {
                    if (response.success) {
                        this.onSendSuccess(response.data);
                    } else {
                        this.showError(response.data.message || formapressCrmModal.i18n.error);
                    }
                },
                error: (xhr) => {
                    console.error("Send communication error:", xhr);
                    this.showError(formapressCrmModal.i18n.error);
                },
                complete: () => {
                    this.form.find(".comm-loading").hide();
                    this.form.find(".crm-modal-send").prop("disabled", false);
                },
            });
        },

        /**
         * Handle successful send
         */
        onSendSuccess(data) {
            // Show success message
            this.showSuccess(data.message || formapressCrmModal.i18n.success);

            // Reload page to show new activity in timeline
            setTimeout(() => {
                window.location.reload();
            }, 1500);
        },

        /**
         * Show error message
         */
        showError(message) {
            const $error = this.form.find(".comm-error");
            $error.html(`<span class="dashicons dashicons-warning"></span> ${message}`).fadeIn();
        },

        /**
         * Show success message
         */
        showSuccess(message) {
            const $success = $('<div class="comm-success"></div>');
            $success.html(`<span class="dashicons dashicons-yes-alt"></span> ${message}`);
            this.form.find(".comm-error").replaceWith($success);
        },
    };

    // Initialize on document ready
    $(document).ready(() => {
        CommunicationModal.init();
    });
})(jQuery);
