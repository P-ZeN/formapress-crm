# Opportunity → ZQPM Modal Enhancement Plan

**Date**: January 16, 2026
**Status**: Planning
**Priority**: High

## 📋 Overview

Enhance the existing multistep modal for creating ZQPM from opportunities (when status = "win") with:
1. **Nested modal** to create a new session when none exist for the selected formation
2. **Company/Contact data propagation** from opportunity to ZQPM on creation

## 🎯 Current State Analysis

### Existing Components

**PHP Files:**
- `/formapress-crm/admin/admin-opportunity-editor.php` (lines 607-717) - Modal HTML
- `/formapress-crm/includes/opportunity-zqpm-integration.php` - AJAX handlers
  - `formapress_crm_create_zqpm_from_opportunity()` - Creates ZQPM
  - `formapress_crm_get_formations()` - Lists formations
  - `formapress_crm_get_available_sessions()` - Lists sessions

**JS Files:**
- `/formapress-crm/_dev/js/admin/opportunity-zqpm.js` - Modal logic
  - 3-step modal: Formation → Session → Confirm
  - AJAX calls for formations/sessions/creation

**Modal Flow (Current):**
```
Step 1: Select Formation
   ↓
Step 2: Select Session (or link to create new - opens in new tab)
   ↓
Step 3: Confirm & Create ZQPM
```

### ZQPM Data Structure

**Critical Meta Keys:**
```php
// Core session link
'zqpm_session_id' => int         // Required

// Company data (array of company IDs)
'zqpm_infos_entreprise' => array( int, int, ... )

// Per-company session-specific data (stored in zqpmmeta table)
// Key: 'entreprise_infos_session'
// Value: array(
//     'date_contact' => date,
//     'lieu_formation' => string,
//     'locaux_ok' => int,
//     'attentes' => string,
//     'tarif_jour' => float
// )
```

**Company Person Linking:**
- Company: `crm_company` CPT or v1 `zqpm_entreprise` CPT
- Contacts: `crm_person` CPT with `person_type` = `company_contact`
- Link via `_crm_entreprise_ids` meta on person record

### Opportunity Available Data

From `admin-opportunity-editor.php` button (line 575):
```php
data-opportunity-id="<?php echo $post_id; ?>"
data-formation-id="<?php echo $formation_id; ?>"
data-company-id="<?php echo $company_id; ?>"
data-person-id="<?php echo $person_id; ?>"
```

## 🎨 Feature 1: Nested Session Creation Modal

### User Journey
```
1. User in Step 2 (Session Selection)
2. No sessions available for formation
3. Click "+ Créer une nouvelle session"
4. Nested modal opens OVER parent modal
5. Fill session form (title, dates, places)
6. Click "Créer la session"
7. Nested modal closes
8. New session appears in parent modal select
9. Continue to Step 3
```

### UI Design

**Nested Modal Structure:**
```html
<!-- Nested Session Creation Modal (z-index higher than parent) -->
<div id="zqpm-create-session-modal" class="zqpm-modal zqpm-nested-modal" style="display: none;">
    <div class="zqpm-modal-overlay"></div>
    <div class="zqpm-modal-content zqpm-nested-content">
        <div class="zqpm-modal-header">
            <h2>Créer une nouvelle session</h2>
            <button type="button" class="zqpm-modal-close">&times;</button>
        </div>
        <div class="zqpm-modal-body">
            <!-- Formation (pre-filled, readonly) -->
            <div class="zqpm-form-field">
                <label>Formation :</label>
                <input type="text" id="session-formation-name" readonly />
                <input type="hidden" id="session-formation-id" />
            </div>

            <!-- Session Title -->
            <div class="zqpm-form-field">
                <label for="session-title">Titre de la session : <span class="required">*</span></label>
                <input type="text" id="session-title" required />
            </div>

            <!-- Date Début -->
            <div class="zqpm-form-field">
                <label for="session-date-debut">Date de début : <span class="required">*</span></label>
                <input type="date" id="session-date-debut" required />
            </div>

            <!-- Date Fin -->
            <div class="zqpm-form-field">
                <label for="session-date-fin">Date de fin : <span class="required">*</span></label>
                <input type="date" id="session-date-fin" required />
            </div>

            <!-- Places (taxonomy) -->
            <div class="zqpm-form-field">
                <label for="session-places">Lieu(x) : <span class="required">*</span></label>
                <select id="session-places" multiple required>
                    <!-- Populated via AJAX from zform_places taxonomy -->
                </select>
            </div>

            <div class="zqpm-error-message" style="display: none;"></div>
        </div>
        <div class="zqpm-modal-footer">
            <button type="button" class="button button-secondary cancel-session-creation">Annuler</button>
            <button type="button" class="button button-primary confirm-session-creation">Créer la session</button>
        </div>
    </div>
</div>
```

**CSS Considerations:**
```scss
// Nested modal should sit above parent
.zqpm-nested-modal {
    z-index: 100002; // Parent is 100000

    .zqpm-modal-content {
        max-width: 600px; // Smaller than parent
        margin: 5% auto; // Higher position
    }
}

// Dim parent modal when nested is open
.zqpm-modal.dimmed {
    .zqpm-modal-content {
        opacity: 0.3;
        pointer-events: none;
    }
}
```

### Backend Implementation

**New AJAX Handler:**
```php
// File: formapress-crm/includes/opportunity-zqpm-integration.php

/**
 * AJAX: Create a new zform_session from modal.
 */
function formapress_crm_create_session_from_modal() {
    check_ajax_referer('formapress_create_zqpm_nonce', 'nonce');

    if (!current_user_can('edit_posts')) {
        wp_send_json_error(['message' => __('Permission refusée.', 'formapress-crm')]);
    }

    $formation_id = isset($_POST['formation_id']) ? absint($_POST['formation_id']) : 0;
    $title = isset($_POST['title']) ? sanitize_text_field($_POST['title']) : '';
    $date_debut = isset($_POST['date_debut']) ? sanitize_text_field($_POST['date_debut']) : '';
    $date_fin = isset($_POST['date_fin']) ? sanitize_text_field($_POST['date_fin']) : '';
    $places = isset($_POST['places']) ? array_map('absint', $_POST['places']) : array();

    // Validate
    if (!$formation_id || !$title || !$date_debut || !$date_fin) {
        wp_send_json_error(['message' => __('Tous les champs obligatoires doivent être remplis.', 'formapress-crm')]);
    }

    // Create session
    $session_data = array(
        'post_type' => 'zform_session',
        'post_status' => 'publish',
        'post_title' => $title,
        'post_author' => get_current_user_id(),
    );

    $session_id = wp_insert_post($session_data);

    if (is_wp_error($session_id)) {
        wp_send_json_error(['message' => __('Erreur lors de la création de la session.', 'formapress-crm')]);
    }

    // Set formation_id meta (CRITICAL for ZQPM)
    update_post_meta($session_id, 'formation_id', $formation_id);

    // Set dates
    update_post_meta($session_id, 'dates', array(
        'debut' => $date_debut,
        'fin' => $date_fin
    ));

    // Set places taxonomy
    if (!empty($places)) {
        wp_set_post_terms($session_id, $places, 'zform_places', false);
    }

    // Get formation title for response
    $formation = get_post($formation_id);
    $formation_title = $formation ? $formation->post_title : '';

    wp_send_json_success(array(
        'message' => __('Session créée avec succès !', 'formapress-crm'),
        'session_id' => $session_id,
        'session_title' => $title,
        'session_date' => date('d/m/Y', strtotime($date_debut)),
        'formation_title' => $formation_title,
    ));
}
add_action('wp_ajax_formapress_create_session_from_modal', 'formapress_crm_create_session_from_modal');

/**
 * AJAX: Get places taxonomy terms for session form.
 */
function formapress_crm_get_places_taxonomy() {
    check_ajax_referer('formapress_create_zqpm_nonce', 'nonce');

    $terms = get_terms(array(
        'taxonomy' => 'zform_places',
        'hide_empty' => false,
    ));

    if (is_wp_error($terms)) {
        wp_send_json_error(['message' => __('Erreur lors du chargement des lieux.', 'formapress-crm')]);
    }

    $result = array();
    foreach ($terms as $term) {
        $result[] = array(
            'id' => $term->term_id,
            'name' => $term->name,
        );
    }

    wp_send_json_success($result);
}
add_action('wp_ajax_formapress_get_places_taxonomy', 'formapress_crm_get_places_taxonomy');
```

### Frontend Implementation

**JS Enhancements:**
```javascript
// File: formapress-crm/_dev/js/admin/opportunity-zqpm.js

// Add after line 48 (after ESC key handler)
$(document).on('click', '.zqpm-create-new-session', function(e) {
    e.preventDefault();
    openSessionModal();
});

function openSessionModal() {
    const formationId = selectedFormationId;
    const formationName = $('#zqpm-formation-select option:selected').text();

    // Pre-fill formation info
    $('#session-formation-id').val(formationId);
    $('#session-formation-name').val(formationName);

    // Load places
    loadPlaces();

    // Show nested modal
    $('#zqpm-create-session-modal').fadeIn(200);
    $('.zqpm-modal:first').addClass('dimmed'); // Dim parent
}

function closeSessionModal() {
    $('#zqpm-create-session-modal').fadeOut(200);
    $('.zqpm-modal:first').removeClass('dimmed');
    $('#zqpm-session-creation-form')[0].reset();
    $('.zqpm-error-message', '#zqpm-create-session-modal').hide();
}

// Close handlers
$('.cancel-session-creation, #zqpm-create-session-modal .zqpm-modal-close').on('click', closeSessionModal);

function loadPlaces() {
    $.ajax({
        url: formapressOpportunityZQPM.ajax_url,
        type: 'POST',
        data: {
            action: 'formapress_get_places_taxonomy',
            nonce: formapressOpportunityZQPM.nonce
        },
        success: function(response) {
            if (response.success && response.data) {
                const $select = $('#session-places');
                $select.empty();
                response.data.forEach(function(place) {
                    $select.append(new Option(place.name, place.id));
                });
            }
        }
    });
}

$('.confirm-session-creation').on('click', function() {
    const formationId = $('#session-formation-id').val();
    const title = $('#session-title').val();
    const dateDebut = $('#session-date-debut').val();
    const dateFin = $('#session-date-fin').val();
    const places = $('#session-places').val(); // Array

    // Validate
    if (!title || !dateDebut || !dateFin || !places || places.length === 0) {
        showSessionError('Veuillez remplir tous les champs obligatoires.');
        return;
    }

    const $btn = $(this);
    $btn.prop('disabled', true).html('<span class="spinner is-active"></span> Création...');

    $.ajax({
        url: formapressOpportunityZQPM.ajax_url,
        type: 'POST',
        data: {
            action: 'formapress_create_session_from_modal',
            nonce: formapressOpportunityZQPM.nonce,
            formation_id: formationId,
            title: title,
            date_debut: dateDebut,
            date_fin: dateFin,
            places: places
        },
        success: function(response) {
            if (response.success) {
                // Add new session to parent modal select
                const newSession = response.data;
                const sessionLabel = newSession.formation_title + ' - ' + newSession.session_date;
                const $parentSelect = $('#zqpm-session-select');
                $parentSelect.append(new Option(sessionLabel, newSession.session_id));

                // Select the new session
                $parentSelect.val(newSession.session_id).trigger('change');
                selectedSessionId = newSession.session_id;

                // Update sessions data array
                sessionsData.push({
                    id: newSession.session_id,
                    title: newSession.session_title,
                    date: newSession.session_date,
                    formation_title: newSession.formation_title
                });

                // Close nested modal
                closeSessionModal();

                // Show success message in parent modal
                showError('✓ Session créée avec succès !', 'success');
            } else {
                showSessionError(response.data.message);
            }
        },
        error: function() {
            showSessionError('Erreur réseau. Veuillez réessayer.');
        },
        complete: function() {
            $btn.prop('disabled', false).text('Créer la session');
        }
    });
});

function showSessionError(message, type = 'error') {
    const $error = $('.zqpm-error-message', '#zqpm-create-session-modal');
    $error.removeClass('success error').addClass(type);
    $error.html(message).fadeIn();
}
```

## 🔗 Feature 2: Company/Contact Data Propagation

### Requirements

**From Opportunity to ZQPM:**
1. Save company ID(s) to `zqpm_infos_entreprise` array
2. Link company contacts (referents) properly
3. Initialize per-company session data in `zqpmmeta` table

### Implementation

**Update AJAX Handler:**
```php
// File: formapress-crm/includes/opportunity-zqpm-integration.php
// Update existing formapress_crm_create_zqpm_from_opportunity() function

function formapress_crm_create_zqpm_from_opportunity() {
    // ... existing validation code ...

    // Create ZQPM post (existing)
    $zqpm_id = wp_insert_post($zqpm_data);

    if (is_wp_error($zqpm_id)) {
        wp_send_json_error(['message' => __('Erreur lors de la création du Suivi de session.', 'formapress-crm')]);
    }

    // Set ZQPM meta data (existing)
    update_post_meta($zqpm_id, 'zqpm_session_id', $session_id);

    if ($formation_id) {
        update_post_meta($zqpm_id, 'formation_id', $formation_id);
    }

    // ====== NEW: Company/Contact Data Propagation ======

    // 1. Save company to zqpm_infos_entreprise array
    if ($company_id) {
        $company_post_type = get_post_type($company_id);

        // Verify it's a valid company
        if (in_array($company_post_type, array('crm_company', 'zqpm_entreprise'), true)) {
            update_post_meta($zqpm_id, 'zqpm_infos_entreprise', array($company_id));

            // Initialize per-company session data (stored in zqpmmeta table)
            // This ensures the company appears in Step 0 "Informations entreprise"
            if (function_exists('update_zqpmmeta')) {
                update_zqpmmeta($zqpm_id, $company_id, 'entreprise_infos_session', array(
                    'date_contact' => date('Y-m-d'),
                    'lieu_formation' => '',
                    'locaux_ok' => 0,
                    'attentes' => '',
                    'tarif_jour' => ''
                ));
            }
        }
    }

    // 2. Link referent (if provided and belongs to company)
    if ($person_id && $company_id) {
        $person_type = wp_get_object_terms($person_id, 'person_type', array('fields' => 'slugs'));

        // Verify person is a company_contact
        if (!is_wp_error($person_type) && in_array('company_contact', $person_type, true)) {
            // Verify person is linked to this company
            $person_companies = get_post_meta($person_id, '_crm_entreprise_ids', true);
            $company_ids_array = !empty($person_companies) ? array_map('intval', explode(',', $person_companies)) : array();

            if (in_array($company_id, $company_ids_array, true)) {
                // Store referent info in zqpm_params meta (used by referent lists)
                $zqpm_params = get_post_meta($zqpm_id, 'zqpm_params', true);
                if (!is_array($zqpm_params)) {
                    $zqpm_params = array();
                }

                // Add referent reference
                $zqpm_params['primary_referent_id'] = $person_id;
                $zqpm_params['primary_company_id'] = $company_id;

                update_post_meta($zqpm_id, 'zqpm_params', $zqpm_params);
            }
        }
    }

    // ====== END NEW ======

    // Link opportunity to ZQPM (existing)
    update_post_meta($opportunity_id, '_crm_opportunity_zqpm_id', $zqpm_id);
    update_post_meta($zqpm_id, '_crm_source_opportunity_id', $opportunity_id);

    wp_send_json_success(array(
        'message' => __('Suivi de session créé avec succès !', 'formapress-crm'),
        'zqpm_id' => $zqpm_id,
        'zqpm_title' => get_the_title($zqpm_id),
        'edit_url' => get_edit_post_link($zqpm_id, 'raw'),
        'redirect_url' => get_edit_post_link($zqpm_id, 'raw'),
    ));
}
```

## 📦 File Changes Summary

### New Files
None (all enhancements to existing files)

### Modified Files

**PHP:**
1. `/formapress-crm/includes/opportunity-zqpm-integration.php`
   - Add `formapress_crm_create_session_from_modal()` AJAX handler
   - Add `formapress_crm_get_places_taxonomy()` AJAX handler
   - Update `formapress_crm_create_zqpm_from_opportunity()` for company/contact data

**JavaScript:**
2. `/formapress-crm/_dev/js/admin/opportunity-zqpm.js`
   - Add nested modal open/close functions
   - Add session creation form validation
   - Add AJAX call for session creation
   - Add new session to parent select after creation

**HTML:**
3. `/formapress-crm/admin/admin-opportunity-editor.php`
   - Add nested session creation modal markup (after main modal, line ~718)

**SCSS:**
4. `/formapress-crm/_dev/scss/opportunity-zqpm.scss`
   - Add nested modal z-index and dimming styles
   - Style session creation form

## ✅ Testing Checklist

### Session Creation Modal
- [ ] Open parent modal, select formation with no sessions
- [ ] Click "+ Créer une nouvelle session" opens nested modal
- [ ] Formation name pre-filled and readonly
- [ ] All form fields render correctly
- [ ] Places taxonomy loads and allows multi-select
- [ ] Form validation works (required fields)
- [ ] Session creates successfully
- [ ] New session appears in parent modal select
- [ ] New session auto-selected after creation
- [ ] Nested modal closes after success
- [ ] ESC key closes nested modal
- [ ] Click outside closes nested modal

### Company/Contact Propagation
- [ ] Create opportunity with company + contact
- [ ] Mark as "won"
- [ ] Open ZQPM creation modal
- [ ] Create ZQPM
- [ ] Verify ZQPM edit screen shows company in Step 0
- [ ] Verify `zqpm_infos_entreprise` meta is array with company ID
- [ ] Verify `zqpmmeta` table has `entreprise_infos_session` entry
- [ ] Verify contact/referent linked properly (if applicable)

### Edge Cases
- [ ] No company on opportunity → ZQPM creates without company
- [ ] Company without contact → ZQPM creates with company only
- [ ] Contact not linked to company → Contact not added
- [ ] v1 zqpm_entreprise company → Works correctly
- [ ] v2 crm_company → Works correctly

## 🚀 Deployment Steps

1. Update PHP files
2. Update JS file (will auto-compile via Vite watch)
3. Update SCSS (will auto-compile via Vite watch)
4. Clear WordPress transients/cache
5. Test in development environment
6. Test with real opportunity data
7. Deploy to production

## 📝 Notes

**Vite Build Process:**
- Build processes are ALWAYS running (per copilot instructions)
- No manual `npm run dev` or `npm run build` commands needed
- Files will auto-compile on save

**ZQPM Meta Structure:**
- `zqpm_infos_entreprise` is an ARRAY of company IDs
- Per-company data stored in custom `zqpmmeta` table
- Supports multiple companies per session (though opportunity has one)

**Session CPT:**
- Post type: `zform_session`
- Required meta: `formation_id` (int)
- Optional meta: `dates` (array with 'debut' and 'fin')
- Taxonomy: `zform_places` (locations)

**Person Types:**
- `company_contact` - Company referents
- `trainee` - Trainees
- `instructor` - Instructors
- Link via `_crm_entreprise_ids` (comma-separated int string)
