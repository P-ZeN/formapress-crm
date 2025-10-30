# FormaPress CRM

**Goal:** Simple CRM for French training organizations with automated Bilan Pédagogique et Financier (BPF).

## Key Differentiators

1. **Simple** - Competitor is "too complex". Add a lead in 30 seconds, zero training.
2. **Standard CRM** - Leads → Prospects → Customers pipeline like Pipedrive
3. **BPF Ready** - Captures financial data for automated Qualiopi annual report

## Core Features

- **Person Management** - Unified contacts (leads, trainees, instructors)
- **Sales Pipeline** - Visual Kanban board (Lead → Qualified → Quote → Won)
- **Financial Tracking** - Session revenue/costs for BPF generation
- **Auto-sync** - New registrations/instructors auto-create CRM records

## Implementation Strategy

**Phase 1 (Weeks 1-8):** Build CRM for NEW data only. Ship to sales team.
**Phase 2 (Months 3-18):** Migrate legacy data from ~20 production sites gradually.

## Data We Need for BPF (capture now, format later)

- Session revenue & costs per session
- Trainee counts & hours delivered
- Funding sources (optional detail: OPCO, CPF, etc.)
- Instructor rates & availability

**Action:** Get official BPF format from customer ASAP to finalize template.

## Files

- `formapress-crm.php` - Main plugin file
- `includes/cpt-person.php` - Person CPT (contacts)
- `includes/cpt-opportunity.php` - Sales pipeline
- `includes/cpt-activity.php` - Activity logging
- `includes/legacy-sync.php` - Bridge to old zformations system
- `includes/bpf-data-capture.php` - Financial data for BPF
- `includes/funding-model.php` - Detailed funding breakdown (optional)
