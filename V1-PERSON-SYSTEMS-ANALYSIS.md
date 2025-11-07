# FormaPress v1 Person Systems Analysis

**Date**: November 6, 2025
**Purpose**: Comprehensive analysis of v1 person management systems to inform unified crm_person architecture
**Status**: Complete

---

## Executive Summary

FormaPress v1 manages persons across **6 different systems** with no unified approach:

1. **zform_instructor** (CPT) - Instructors/trainers
2. **zqpm_entreprise** (CPT) - Companies with **referents embedded in post meta**
3. **zqpmReferent** (PHP class only) - Company contacts stored as serialized arrays in entreprise meta
4. **zRegistration_infos** (Database table) - Trainees/registrants
5. **zqpm_financeur** (CPT) - Funders/OPCOs/payment entities
6. **zqpmCommanditaire** (PHP class) - Used for financeur instantiation

**The Worst Design Error**: Referents (company contacts) are stored as serialized arrays in `zqpm_entreprise` post meta instead of being separate entities. This makes it impossible to:
- Search for contacts across companies
- Track contact history
- Link contacts to opportunities/deals
- Migrate contacts when companies merge
- Use WordPress query features for contacts

---

## System 1: zform_instructor (Instructors)

### Overview
- **Type**: Custom Post Type
- **Location**: `zformations/custom_posts_types/zform_instructor.php`
- **Count**: ~13 records (estimate from production)
- **Menu**: Under 'zform_partners'

### CPT Configuration
```php
'post_type' => 'zform_instructor'
'supports' => ['title', 'editor', 'thumbnail']
'hierarchical' => true
'has_archive' => ZLABEL_INSTRUCTORS_ALL_SLUG
'show_in_menu' => 'zform_partners'
```

### Meta Fields
| Field | Type | Description |
|-------|------|-------------|
| `sessions_ids` | array | Array of zform_session IDs assigned to instructor |
| `instructors_attributs` | array | Dynamic custom fields (configurable via options) |

### Custom Attributes System
- Configuration stored in WordPress option: `zform_instructors_attributes`
- Field types supported:
  - `text` - Simple text input
  - `number` - Numeric input
  - `wyswyg` - Rich text editor
  - `list` - Select dropdown
  - `download` - File upload
- Each attribute has:
  - `name` - Display label
  - `type` - Field type
  - Value stored in `instructors_attributs` meta array with sanitized key

### UI Features
- **Session Assignment Interface**:
  - Hierarchical accordion: Categories → Formations → Sessions
  - Checkbox selection for assigning sessions
  - "Add Session" quick links per formation
  - Prevents duplicate formation display
- **Custom Columns**: Shows session count with accordion display
- **Quick Add**: AJAX functionality for quick instructor creation

### Metaboxes (235 lines)
1. **sessions_ids**: Session assignment with checkbox interface
2. **attributs**: Dynamic custom fields based on options configuration
3. **calendar**: Agenda/schedule display (function exists, implementation not examined)

### Code Organization
- CPT registration: `zform_instructor.php`
- Metaboxes: `zform_instructor_metaboxes.php`
- No dedicated PHP class

---

## System 2: zqpm_entreprise (Companies) + zqpmReferent (Contacts)

### Overview - THE PROBLEM SYSTEM
- **Type**: Custom Post Type with embedded contacts
- **Location**: `zformations_qualiopi_process_manager/cpts/entreprise.php`
- **Class**: `zformations_qualiopi_process_manager/classes/zqpm_entreprise.class.php`
- **Count**: ~88 companies (from production data)
- **Menu**: Under 'zform_partners'

### CPT Configuration
```php
'post_type' => 'zqpm_entreprise'
'supports' => ['title', 'custom-fields']
'hierarchical' => true
'has_archive' => 'entreprises'
'show_in_menu' => 'zform_partners'
'show_in_rest' => true
```

### Company Fields (zqpmEntreprise class)
| Field | Type | Description |
|-------|------|-------------|
| `raison_sociale` | string | Company name (also in post_title) |
| `adresse` | string | Street address |
| `cp` | string | Postal code |
| `ville` | string | City |
| `siret` | string | SIRET number (French business ID) |
| `site` | string | Website URL |
| `opco` | array | OPCO/funder associations (serialized) |
| `date_contact` | string | Date of first contact |
| `lieu_formation` | string | Training location preference |
| `locaux_ok` | int | Whether premises are suitable (1/0) |
| `attentes` | string | Expectations/needs |
| `tarif_jour` | string | Daily rate |
| `referent` | **array** | **Serialized array of zqpmReferent objects** |

### Referent Fields (zqpmReferent class) - THE DESIGN ERROR
**CRITICAL**: Referents are NOT a CPT. They're stored as serialized arrays in entreprise post meta.

| Field | Type | Description |
|-------|------|-------------|
| `civilite` | string | Title (Mme/Mr) |
| `nom` | string | Last name |
| `prenom` | string | First name |
| `poste` | string | Job position |
| `tel` | string | Phone number |
| `mail` | string | Email address (required) |
| `tokens_infos` | array | Token data for template parsing |
| `entreprise_id` | int | Parent company ID (set at runtime) |
| `id` | int | Array index within company's referent array |

### Storage Structure - THE ROOT OF THE PROBLEM
```php
// In zqpm_entreprise post meta:
'referent' => serialize([
    0 => [
        'civilite' => 'Mme',
        'nom' => 'Dupont',
        'prenom' => 'Marie',
        'poste' => 'RH Manager',
        'tel' => '0123456789',
        'mail' => 'marie@company.fr',
        'tokens_infos' => [...]
    ],
    1 => [
        'civilite' => 'Mr',
        'nom' => 'Martin',
        'prenom' => 'Paul',
        'poste' => 'Training Coordinator',
        'tel' => '0198765432',
        'mail' => 'paul@company.fr',
        'tokens_infos' => [...]
    ]
])
```

### Why This Is The "Worst Design Error"

#### 1. **No Entity Independence**
- Referents don't exist as WordPress entities
- No post ID, no permalink, no taxonomy support
- Cannot be queried independently
- Cannot have their own meta fields
- Cannot track history or changes

#### 2. **Search Impossible**
```php
// CANNOT DO:
$contact = get_posts([
    'post_type' => 'crm_person',
    'meta_query' => [
        ['key' => 'email', 'value' => 'marie@company.fr']
    ]
]);

// MUST DO:
$companies = get_posts(['post_type' => 'zqpm_entreprise', 'numberposts' => -1]);
foreach ($companies as $company) {
    $referents = maybe_unserialize(get_post_meta($company->ID, 'referent', true));
    foreach ($referents as $ref) {
        if ($ref['mail'] === 'marie@company.fr') {
            // Found it... but now what?
        }
    }
}
```

#### 3. **Relationship Tracking Impossible**
- Cannot link referent to opportunities/deals
- Cannot track which referent initiated which training
- Cannot see referent's interaction history
- Cannot assign tasks to referents
- Cannot track email communications per referent

#### 4. **Data Integrity Issues**
- Referent data duplicated if person works at multiple companies
- No way to update contact info across all occurrences
- Email changes require updating every company record
- No validation or consistency checks

#### 5. **Migration Nightmares**
- Cannot merge companies without losing referent associations
- Cannot move referents between companies cleanly
- Cannot split companies while preserving relationships
- No audit trail for changes

#### 6. **UI Limitations**
- Referents only editable within company metabox
- No dedicated referent management screen
- No bulk operations on referents
- No referent-centric views
- Cannot see "all contacts at companies we work with"

#### 7. **Token System Workarounds**
```php
// tokens_infos stored per referent in serialized array
// Makes token generation complex and error-prone
$referent->tokens_infos = [
    'formation_id' => 123,
    'rstatus' => 1,
    'respdatas' => [...],
    'date_envoi' => '2024-11-15',
    'qdate_submission' => '2024-11-16'
];
```

### Per-Session Company Data
Companies also have session-specific data stored in custom `zqpmmeta` table:

**Table**: `zqpmmeta`
```sql
CREATE TABLE zqpmmeta (
    meta_id INT,
    zqp_id INT,       -- Suivi ID (session tracking)
    object_id INT,    -- Entreprise ID
    meta_key VARCHAR, -- 'entreprise_infos_session'
    meta_value TEXT   -- Serialized session-specific data
)
```

**Session-Specific Fields**:
| Field | Type | Description |
|-------|------|-------------|
| `date_contact` | date | Contact date for this session |
| `lieu_formation` | string | Training location for this session |
| `locaux_ok` | int | Premises suitable? (1/0) |
| `attentes` | string | Expectations for this session |
| `opco` | array | Funders for this session |
| `mode_tarification` | int | Pricing mode (0-3) |
| `tarif_horaire` | float | Hourly rate (mode 0) |
| `forfait_jour` | float | Daily flat rate (mode 1) |
| `forfait_session` | float | Session flat rate (mode 2) |
| `modalites_particulieres` | string | Special conditions (mode 3) |
| `frais_annexes` | float | Additional fees per day |

### AJAX Operations
1. **zqpm_get_entreprise_form_ajax**: Load add/edit form for company
2. **zqpm_update_entreprise_ajax**: Save company + referents
3. **zqpm_get_entreprise_ajax**: Load company display for suivi metabox

### Code Organization
- CPT registration: `cpts/entreprise.php` (122 lines)
- PHP class: `classes/zqpm_entreprise.class.php` (128 lines)
- Referent class: `classes/zqpm_referent.class.php` (29 lines)
- 20+ file references throughout ZQPM plugin

---

## System 3: zRegistration_infos (Trainees)

### Overview
- **Type**: Custom database table (NOT a CPT)
- **Location**: `zformations_qualiopi_process_manager/classes/zregistration_infos.class.php`
- **Table**: `wp_zform_registrations`
- **Count**: ~927 records (largest dataset)

### Database Table Structure
```sql
CREATE TABLE wp_zform_registrations (
    id INT PRIMARY KEY AUTO_INCREMENT,
    formation_id INT,
    session_id INT,
    date_submission DATETIME,
    status VARCHAR,
    datas TEXT  -- Serialized registration form data
)
```

### zRegistration_infos Class Fields
| Field | Type | Source | Description |
|-------|------|--------|-------------|
| `id` | int | table | Registration ID |
| `registration_id` | int | table | Alias for id |
| `formation_id` | int | table | Training program ID |
| `session_id` | int | table | Session ID |
| `stagiaire_id` | int | table | Alias for id |
| `date_submission` | datetime | table | Registration date |
| `status` | string | table | Registration status |
| `datas` | array | table | Unserialized form data |
| `visio` | string | datas | Remote/on-site (oui/non) |
| `user_id` | int | computed | WordPress user ID |
| `user` | WP_User | computed | User object |
| `user_profile_url` | string | computed | Profile URL |
| `name` | string | datas | Last name |
| `nicename` | string | user | User nicename |
| `firstname` | string | datas | First name |
| `mail` | string | user | Email from user account |
| `societe` | int | datas | Company ID (zqpm_entreprise) |
| `adresse` | string | datas | Address |
| `cp` | string | datas | Postal code |
| `ville` | string | datas | City |
| `infos_session` | array | zqpmmeta | Session-specific info |

### Dynamic Data Fields
The `datas` field contains serialized registration form data. Fields are configurable via WordPress option `zform_registrations`:

```php
$zform_registrations = get_option('zform_registrations');
// Returns array of field definitions:
[
    'field_slug' => [
        'name' => 'Field Label',
        'type' => 'text|email|tel|select|textarea|checkbox|...',
        'required' => 1|0
    ]
]
```

Common fields from context:
- `nom` - Last name
- `prenom` - First name
- `email` - Email address
- `telephone` - Phone
- `societe` - Company ID (reference to zqpm_entreprise)
- `visio` - Remote participation preference
- Various custom fields per organization

### User Association
- Trainees are linked to WordPress users via `zqpm_get_user_infos($registration)`
- User nicename used in profile URL: `site.com/stagiaires/{nicename}`
- Email comes from WordPress user account, not registration data

### Per-Session Trainee Data
Like companies, trainees have session-specific data in `zqpmmeta` table:

```php
get_zqpmmeta($zqp_id, $registration_id, 'individuel_infos_session');
```

### Import Functionality
- CSV import: `zqpm_import_csv_stagiaires_handler()`
- Template generation: `zqpm_get_registration_import_template_handler()`
- Batch processing: `zqpm_batch_import_stagiaires()`
- Company linking: `zqpm_add_entreprise_to_stagiaire()`

### Querying Functions
```php
zqpm_get_all_registrations_for_session($session_id);
zqpm_delete_registration_for_session($id);
zqpm_update_registration($id, $datas);
zqpm_get_stagiaire_id_by_user_id($user_id, $session_id);
```

### Why Not A CPT?
Unknown. Likely historical reasons or perceived table simplicity. Issues:
- No WordPress admin UI
- No post meta capabilities
- No taxonomy support
- No featured images
- No standard WordPress queries
- Custom functions needed for all operations

---

## System 4: zqpm_financeur (Funders) + zqpmCommanditaire (Proxy Class)

### Overview
- **Type**: Custom Post Type
- **Location**: `zformations_qualiopi_process_manager/cpts/financeur.php`
- **Class**: `zformations_qualiopi_process_manager/classes/class-zqpmcommanditaire.php`
- **Count**: Unknown (not in production data snapshot)
- **Menu**: Under 'zform_partners'

### CPT Configuration
```php
'post_type' => 'zqpm_financeur'
'supports' => ['title', 'custom-fields']
'hierarchical' => true
'has_archive' => 'financeurs'
'show_in_menu' => 'zform_partners'
'show_in_rest' => true
```

### Financeur/Commanditaire Fields
| Field | Type | Description |
|-------|------|-------------|
| `nom` | string | Funder name (also in post_title) |
| `type` | int | Funder type (OPCO, Pôle Emploi, etc.) |
| `mail` | string | Contact email |
| `telephone` | string | Phone number |
| `site` | string | Website URL |
| `representant` | string | Representative name |
| `adresse` | string | Street address |
| `cp` | string | Postal code |
| `ville` | string | City |
| `siret` | string | SIRET number |
| `numero_compta` | string | Accounting number |
| `commanditaire_tokens_infos` | array | Token data (serialized) |

### Funder Types (Pre-defined)
```php
0  => 'AGEFPIH'
1  => 'Caisse des Dépôts'
2  => 'Etat'
3  => 'Fond d\'assurance formation des non salariés'
4  => 'Instances Européennes'
5  => 'OPACIF'
6  => 'OPCA'
7  => 'OPCO'
8  => 'Pôle Emploi'
9  => 'Région'
10 => 'Autre'
11 => 'Autre public'
```

### Class Naming Confusion
- CPT is named `zqpm_financeur` (funder)
- PHP class is named `zqpmCommanditaire` (sponsor/ordering party)
- Both terms used interchangeably throughout code
- Constructor accepts post ID or array
- Initializes `datas` array for token compatibility

### Usage Pattern
Financeurs are linked to sessions via companies:
1. Company has session-specific data in zqpmmeta
2. Session data includes `opco` field (array of financeur IDs)
3. Multiple funders can be associated per company per session

```php
$entreprise_infos_session['opco'] = [42, 87]; // Array of zqpm_financeur IDs
```

### Querying Functions
```php
zqpm_get_financeurs_list();                    // Get all funders
zqpm_get_financeurs_for_zqp_id($zqp_id);      // Get funders for a session
zqpm_setup_financeur_entreprise_select($id);   // Generate select dropdown
```

### Code Organization
- CPT registration: `cpts/financeur.php` (309 lines)
- PHP class: `classes/class-zqpmcommanditaire.php` (149 lines)

---

## Cross-System Integration Points

### 1. Session Assignment
**Instructors → Sessions**:
```php
$instructor_sessions = get_post_meta($instructor_id, 'sessions_ids', false);
// Returns: [123, 456, 789]
```

**Trainees → Sessions**:
```php
// Via zform_registrations table
SELECT * FROM wp_zform_registrations WHERE session_id = 123;
```

### 2. Company Relationships
**Companies → Trainees**:
```php
$registration->datas['societe'] = 42; // Company ID in registration data
```

**Companies → Sessions**:
```php
// Via ZQPM tracking post meta
$companies = get_post_meta($zqp_id, 'zqpm_infos_entreprise', true);
// Returns: [42, 87, 156]
```

**Companies → Funders**:
```php
// Via zqpmmeta table, per session
$session_data = get_zqpmmeta($zqp_id, $company_id, 'entreprise_infos_session');
$funders = $session_data['opco']; // Array of financeur IDs
```

### 3. Token System Integration
All person types have `tokens_infos` or similar for email templates:
- **Instructors**: In `instructors_attributs` meta (not seen in code review)
- **Referents**: In serialized referent array
- **Trainees**: Dynamic based on registration form
- **Funders**: In `commanditaire_tokens_infos` meta

Token data structure:
```php
[
    'formation_id' => int,
    'rstatus' => int,           // Response status
    'respdatas' => array,       // Response data
    'date_envoi' => string,     // Send date
    'qdate_submission' => string // Questionnaire submission date
]
```

### 4. zqpmmeta Table (Custom Meta Storage)
Custom table for session-specific metadata:

```sql
CREATE TABLE zqpmmeta (
    meta_id BIGINT,
    zqp_id BIGINT,      -- Suivi/session tracking ID
    object_id BIGINT,   -- Person/company ID
    meta_key VARCHAR,   -- Key (e.g., 'entreprise_infos_session')
    meta_value TEXT     -- Serialized data
)
```

Used for:
- Company session-specific data: `entreprise_infos_session`
- Trainee session-specific data: `individuel_infos_session`
- Allows same company/trainee to have different data per session

Functions:
```php
get_zqpmmeta($zqp_id, $object_id, $meta_key);
update_zqpmmeta($zqp_id, $object_id, $meta_key, $meta_value);
```

---

## Data Volume Assessment (from Production)

Based on `FORMAPRESS-V2-DATA-AUDIT.md`:

| Entity Type | v1 System | Count | Status |
|------------|-----------|-------|--------|
| Instructors | zform_instructor CPT | ~13 | Active |
| Companies | zqpm_entreprise CPT | 88 | Active |
| Referents | Embedded in companies | Unknown | **Embedded** |
| Trainees | zform_registrations table | 927 | Active |
| Funders | zqpm_financeur CPT | Unknown | Active |
| Sessions | zform_session CPT | 247 | Active |
| Suivis | zqpm_suivi CPT | 152 | Active |

**Estimated Referent Count**: If average 1.5 referents per company = ~132 referents embedded in meta

---

## Migration Complexity Assessment

### Easy Migrations
1. **zform_instructor → crm_person (instructor)**
   - Simple 1:1 CPT → CPT migration
   - Map custom attributes to person meta
   - Preserve sessions_ids relationships
   - **Complexity**: LOW

2. **zqpm_financeur → crm_person (funder_contact)**
   - 1:1 CPT → CPT migration
   - Note: These are organizations, might also need crm_company representation
   - Map type taxonomy
   - **Complexity**: LOW-MEDIUM (decision on person vs company)

### Medium Complexity Migrations
3. **zRegistration_infos → crm_person (trainee)**
   - Database table → CPT migration
   - Create WordPress posts from table rows
   - Map user_id relationships
   - Preserve session registrations
   - **Complexity**: MEDIUM

### High Complexity Migrations
4. **zqpmEntreprise + zqpmReferent → crm_company + crm_person (company_contact)**
   - Must extract referents from serialized meta
   - Create separate crm_person posts for each referent
   - Link persons to company via meta
   - Preserve session-specific company data
   - Handle multiple referents per company
   - **Complexity**: HIGH

### Migration Order (Recommended)
1. **crm_company** - Create companies first (from zqpm_entreprise)
2. **crm_person (company_contact)** - Extract referents, link to companies
3. **crm_person (trainee)** - Migrate registrations, link to companies if societe field present
4. **crm_person (instructor)** - Migrate instructors with session links
5. **crm_person (funder_contact)** - Decide if keeping as persons or converting to companies

---

## Technical Debt Summary

### Critical Issues (v2 Must Fix)
1. ✅ **Referents in post meta** - Extracting to crm_person
2. ✅ **Trainees in custom table** - Moving to crm_person CPT
3. ✅ **Fragmented person management** - Unified crm_person
4. ✅ **No unified search** - Will have unified person queries
5. ✅ **Inconsistent token systems** - v2 Token Manager handles all

### Design Inconsistencies
- Some persons are CPTs (instructor, financeur)
- Some persons are embedded (referent)
- Some persons are in custom tables (trainee)
- Naming confusion (financeur vs commanditaire)
- Multiple meta storage systems (post_meta, zqpmmeta, serialized)

### Performance Concerns
- Serialized data requires full deserialization for any operation
- Custom table queries don't benefit from WordPress caching
- No indexed searches for referents
- zqpmmeta table adds complexity

---

## v2 Architecture Requirements (Derived from Analysis)

### 1. Unified crm_person CPT
**person_type taxonomy terms needed**:
- `instructor` - From zform_instructor
- `company_contact` - From zqpmReferent embedded data
- `trainee` - From zRegistration_infos table
- `funder_contact` - From zqpm_financeur (if treating as persons)
- `prospect` - New for v2

### 2. Unified crm_company CPT
**Sources**:
- zqpm_entreprise (companies)
- Possibly zqpm_financeur (if treating as organizations)

### 3. Meta Field Structure for crm_person

#### Core Identity Fields (All Person Types)
```php
'civilite'      => 'Mme|Mr'
'first_name'    => string
'last_name'     => string
'email'         => string
'phone'         => string
'address'       => string
'postal_code'   => string
'city'          => string
```

#### Relationship Fields
```php
'company_id'        => int      // Link to crm_company
'user_id'           => int      // Link to WordPress user (trainees)
'v1_source'         => string   // Migration tracking
'v1_source_id'      => int      // Original ID in v1 system
```

#### Role-Specific Fields

**Instructor Meta**:
```php
'instructor_sessions'       => array   // Session IDs
'instructor_custom_fields'  => array   // From v1 attributes system
```

**Company Contact Meta**:
```php
'contact_position'     => string   // Job title
'contact_is_primary'   => bool     // Primary contact for company
```

**Trainee Meta**:
```php
'trainee_registrations'    => array   // Registration IDs
'trainee_remote_pref'      => string  // Visio preference
```

**Funder Contact Meta**:
```php
'funder_type'         => string   // OPCO, Pôle Emploi, etc.
'funder_siret'        => string   // If applicable
'funder_account_num'  => string   // Accounting number
```

### 4. Meta Field Structure for crm_company
```php
'company_name'      => string
'siret'             => string
'address'           => string
'postal_code'       => string
'city'              => string
'website'           => string
'primary_contact'   => int      // crm_person ID
'v1_source'         => 'zqpm_entreprise'
'v1_source_id'      => int
```

### 5. Preserve Session-Specific Data
Continue using zqpmmeta table for session-specific person/company data:
- Allows same person/company to have different data per session
- Pricing modes, dates, preferences per engagement
- Don't change this structure - it works

### 6. Token System Integration
FormaPress_Token_Manager (Week 1) already handles:
- Unified token storage in database
- Works with any entity type
- Compatible with v1 token patterns
- No special person-type handling needed

---

## Implementation Plan (High-Level)

### Phase 1: Person Manager Foundation (Week 2, Task 2.3)
1. Create FormaPress_Person_Manager class (~400-500 lines)
2. CRUD operations for crm_person CPT
3. person_type taxonomy management
4. v1 compatibility layer (read v1, write v2)
5. Meta field helpers
6. Search/query capabilities

### Phase 2: Company System (Week 2, Task 2.4)
1. Create crm_company CPT
2. Create FormaPress_Company_Manager (~200-300 lines)
3. Company-person relationship management
4. Migration from zqpm_entreprise

### Phase 3: One-Time Migration (Week 3+)
1. Migrate companies (zqpm_entreprise → crm_company)
2. Extract and migrate referents (meta arrays → crm_person company_contact)
3. Migrate trainees (table → crm_person trainee)
4. Migrate instructors (CPT → crm_person instructor)
5. Handle financeurs (decide person vs company)
6. Update all relationships
7. Verify data integrity
8. Keep v1 data for rollback capability

### Phase 4: CRM Features (Week 3+)
Build on unified person/company foundation:
- Opportunities/Deals management
- Pipeline stages
- Task management
- Email tracking
- Interaction history
- Custom person views

---

## Key Decisions for v2 Design

### 1. Financeurs: Person or Company?
**Current state**: CPT treated as organizations with contact info

**Options**:
- A) Migrate to crm_company (they're organizations like OPCOs)
- B) Migrate to crm_person with funder_contact type
- C) Create dedicated crm_funder CPT

**Recommendation**:
- Migrate organization data to crm_company
- If there are specific contact persons at funders, create crm_person records
- Most funders are just organizations (OPCO, Pôle Emploi) not individuals

### 2. Preserve zqpmmeta Table?
**Current usage**: Session-specific person/company data

**Recommendation**: YES, preserve it
- Allows same entity to have different data per session
- Pricing, dates, preferences vary by engagement
- Well-established pattern, works fine
- Migrating to post meta would lose per-session granularity

### 3. Handle Dynamic Form Fields?
**Current**: Registration forms have configurable fields (`zform_registrations` option)

**Recommendation**:
- Store form config in same option (no change)
- Store submitted values in person meta as before
- FormaPress_Person_Manager can handle dynamic meta keys
- Don't try to standardize what should be configurable

### 4. WordPress User Association?
**Current**: Trainees linked to WP users

**Recommendation**:
- Keep user_id in person meta
- One crm_person can link to one WP user
- But not all persons need user accounts (contacts, instructors maybe don't)
- Make user linking optional, available for all person types

---

## Conclusion

FormaPress v1's person management is fragmented across 6 systems with critical design flaws:

**The Worst Error**: Referents stored as serialized arrays in company post meta makes them unsearchable, untrackable, and un-relatable.

**The v2 Solution**:
- Unified `crm_person` CPT with `person_type` taxonomy
- Unified `crm_company` CPT
- Extract referents to proper person records
- Migrate trainees from table to CPT
- Consolidate instructors under person management
- Build CRM features on this solid foundation

**Migration Complexity**: HIGH but achievable
- 88 companies
- ~927 trainees
- ~132 estimated referents (embedded)
- ~13 instructors
- Unknown number of funders

**Next Step**: Design detailed crm_person architecture in `PERSON-UNIFICATION-DESIGN.md`

---

**Analysis Complete**: November 2024
**Analyzed By**: GitHub Copilot
**Ready For**: Person Unification Design Phase (Task 2.2)
