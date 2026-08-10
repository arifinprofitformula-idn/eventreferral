# Graph Report - .  (2026-08-10)

## Corpus Check
- 81 files · ~165,849 words
- Verdict: corpus is large enough that graph structure adds value.

## Summary
- 234 nodes · 268 edges · 65 communities detected
- Extraction: 89% EXTRACTED · 11% INFERRED · 0% AMBIGUOUS · INFERRED: 29 edges (avg confidence: 0.82)
- Token cost: 0 input · 0 output

## Community Hubs (Navigation)
- [[_COMMUNITY_AI Landing Builder|AI Landing Builder]]
- [[_COMMUNITY_Event Data Admin|Event Data Admin]]
- [[_COMMUNITY_Event SDK|Event SDK]]
- [[_COMMUNITY_Brand Access Control|Brand Access Control]]
- [[_COMMUNITY_Event Templates Assets|Event Templates Assets]]
- [[_COMMUNITY_Attendance Codes|Attendance Codes]]
- [[_COMMUNITY_Walk-in Settings|Walk-in Settings]]
- [[_COMMUNITY_Mailketing Calendar|Mailketing Calendar]]
- [[_COMMUNITY_Calendar Pages|Calendar Pages]]
- [[_COMMUNITY_Shared Config|Shared Config]]
- [[_COMMUNITY_Visitor Analytics|Visitor Analytics]]
- [[_COMMUNITY_Production Deployment|Production Deployment]]
- [[_COMMUNITY_Admin Users|Admin Users]]
- [[_COMMUNITY_Event Settings|Event Settings]]
- [[_COMMUNITY_Rewards|Rewards]]
- [[_COMMUNITY_Tracking Settings|Tracking Settings]]
- [[_COMMUNITY_Lucky Draw Names|Lucky Draw Names]]
- [[_COMMUNITY_Challenge Landing|Challenge Landing]]
- [[_COMMUNITY_Referrer Dashboard|Referrer Dashboard]]
- [[_COMMUNITY_Referral Landing Flow|Referral Landing Flow]]
- [[_COMMUNITY_Event Referrals|Event Referrals]]
- [[_COMMUNITY_Link Builder UI|Link Builder UI]]
- [[_COMMUNITY_Dashboard WhatsApp|Dashboard WhatsApp]]
- [[_COMMUNITY_Attendance WhatsApp|Attendance WhatsApp]]
- [[_COMMUNITY_CSV Export|CSV Export]]
- [[_COMMUNITY_Lucky Draw Confirm|Lucky Draw Confirm]]
- [[_COMMUNITY_Lucky Draw Void|Lucky Draw Void]]
- [[_COMMUNITY_Mailketing Lists|Mailketing Lists]]
- [[_COMMUNITY_Message Templates|Message Templates]]
- [[_COMMUNITY_Theme Styling|Theme Styling]]
- [[_COMMUNITY_Event API|Event API]]
- [[_COMMUNITY_Admin Home|Admin Home]]
- [[_COMMUNITY_Lucky Draw Display|Lucky Draw Display]]
- [[_COMMUNITY_AI Settings|AI Settings]]
- [[_COMMUNITY_Documentation|Documentation]]
- [[_COMMUNITY_Email Settings|Email Settings]]
- [[_COMMUNITY_Attendance Reports|Attendance Reports]]
- [[_COMMUNITY_Events Admin|Events Admin]]
- [[_COMMUNITY_Analytics Export|Analytics Export]]
- [[_COMMUNITY_Public Index|Public Index]]
- [[_COMMUNITY_Integrations|Integrations]]
- [[_COMMUNITY_Admin Login|Admin Login]]
- [[_COMMUNITY_Admin Logout|Admin Logout]]
- [[_COMMUNITY_Lucky Draw Control|Lucky Draw Control]]
- [[_COMMUNITY_Marketing Content|Marketing Content]]
- [[_COMMUNITY_Legacy Migration|Legacy Migration]]
- [[_COMMUNITY_Brand Setup|Brand Setup]]
- [[_COMMUNITY_Attendance Checkin|Attendance Checkin]]
- [[_COMMUNITY_Attendance Confirm|Attendance Confirm]]
- [[_COMMUNITY_Attendance Finalize|Attendance Finalize]]
- [[_COMMUNITY_Referrer Creation|Referrer Creation]]
- [[_COMMUNITY_Event Info API|Event Info API]]
- [[_COMMUNITY_AI Landing API|AI Landing API]]
- [[_COMMUNITY_Marketing API|Marketing API]]
- [[_COMMUNITY_Publish Landing|Publish Landing]]
- [[_COMMUNITY_Style Captions|Style Captions]]
- [[_COMMUNITY_Lead Submission|Lead Submission]]
- [[_COMMUNITY_Tracking API|Tracking API]]
- [[_COMMUNITY_Referrer Index|Referrer Index]]
- [[_COMMUNITY_Bootstrap|Bootstrap]]
- [[_COMMUNITY_Referrer Export|Referrer Export]]
- [[_COMMUNITY_Referrer Login|Referrer Login]]
- [[_COMMUNITY_Referrer Logout|Referrer Logout]]
- [[_COMMUNITY_Followup Update|Followup Update]]
- [[_COMMUNITY_Safe ZIP Upload|Safe ZIP Upload]]

## God Nodes (most connected - your core abstractions)
1. `render_landing_template()` - 11 edges
2. `get_db()` - 8 edges
3. `call_ai_content_provider()` - 8 edges
4. `generate_ai_landing_page()` - 8 edges
5. `clean()` - 7 edges
6. `generate_single_style_copy()` - 7 edges
7. `init()` - 6 edges
8. `build_marketing_prompt()` - 6 edges
9. `build_single_style_prompt()` - 6 edges
10. `ai_post_json()` - 6 edges

## Surprising Connections (you probably didn't know these)
- `Event SDK` --semantically_similar_to--> `HTML SDK Contract`  [INFERRED] [semantically similar]
  docs\CHANGELOG.md → docs\README-EVENTS.md
- `Referral Link Contract` --semantically_similar_to--> `Event Aware Referrals`  [INFERRED] [semantically similar]
  docs\README-EVENTS.md → docs\CHANGELOG.md
- `Formula 5 Plus Event Page` --references--> `Formula 5 Plus Flyer`  [INFERRED]
  e\formula-5-plus\index.html → assets\flyers\formula-5-plus.jpeg
- `Rahasia Cuan Emas 2026 Event Page` --references--> `Rahasia Cuan Event Flyer`  [INFERRED]
  e\rahasia-cuan\index.html → assets\flyers\rahasia-cuan.jpeg
- `get_ai_provider_settings()` --calls--> `get_db()`  [INFERRED]
  includes\ai_content.php → config.php

## Hyperedges (group relationships)
- **Event SDK Contract Implementation** — docs_readme_events_html_sdk_contract, e_formula_5_plus_index_registration_form, e_rahasia_cuan_index_registration_form, includes_event_templates_bold_fomo_index_bold_fomo_template, includes_event_templates_elegant_gold_index_elegant_gold_template, includes_event_templates_modern_clean_index_modern_clean_template [INFERRED 0.95]
- **Production Runtime Layout** — readme_deployment_via_github, deploy_shared_layout_example_production_layout, docs_deployment_production_deployment_pattern, docs_deployment_shared_runtime_secrets [INFERRED 0.85]
- **Multi Event Referral System** — docs_changelog_multi_event_system, docs_changelog_event_aware_referrals, docs_readme_events_referral_link_contract, readme_referral_whatsapp_flow, e_formula_5_plus_index_formula_5_plus_event_page, e_rahasia_cuan_index_rahasia_cuan_event_page [INFERRED 0.85]

## Communities

### Community 0 - "AI Landing Builder"

Cohesion: 0.17
Nodes (26): ai_apply_ca_bundle(), ai_post_json(), ai_provider_error_message(), build_landing_page_prompt(), build_marketing_prompt(), build_single_style_prompt(), call_ai_content_provider(), call_gemini_api() (+18 more)

### Community 1 - "Event Data Admin"

Cohesion: 0.12
Nodes (15): save_ai_provider_settings(), get_db(), convert_landing_markdown_bold(), get_event_by_slug(), get_event_rewards(), landing_block_field(), landing_nested_block_field(), lighten_hex_color() (+7 more)

### Community 2 - "Event SDK"

Cohesion: 0.24
Nodes (11): applyEventData(), applyReferrerData(), applyTracking(), bindForm(), bindVisitorTracking(), collectExtraFields(), getDeviceType(), getUtmParams() (+3 more)

### Community 3 - "Brand Access Control"

Cohesion: 0.18
Nodes (9): get_current_brand(), require_admin_for_brand(), require_brand_or_404(), require_superadmin_for_brand(), lucky_draw_admin_brand(), lucky_draw_find_event(), lucky_draw_json(), lucky_draw_require_csrf() (+1 more)

### Community 4 - "Event Templates Assets"

Cohesion: 0.14
Nodes (15): Formula 5 Plus Flyer, Rahasia Cuan Event Flyer, RahasiaEmas.id Logo, Event SDK, AI Template Generation, HTML SDK Contract, Landing Page Event Contract, Event ZIP Contract (+7 more)

### Community 5 - "Attendance Codes"

Cohesion: 0.22
Nodes (9): attendance_code_valid(), attendance_info_source_options(), attendance_participant_status_options(), attendance_rate_limit_exceeded(), attendance_window_state(), create_event_attendance_record(), generate_attendance_qr_token(), validate_attendance_extra_fields() (+1 more)

### Community 6 - "Walk-in Settings"

Cohesion: 0.24
Nodes (7): validate_walkin_attendee_fields(), clean(), normalize_whatsapp(), upsert_event_record(), kalender_whatsapp_url(), lucky_draw_status_event(), lucky_draw_status_json()

### Community 7 - "Mailketing Calendar"

Cohesion: 0.44
Nodes (8): build_google_calendar_url(), build_invitation_email_html(), mailketing_add_subscriber(), mailketing_get_lists(), mailketing_parse_event_start(), mailketing_request(), mailketing_send_email(), send_event_invitation_email()

### Community 8 - "Calendar Pages"

Cohesion: 0.38
Nodes (3): kalender_challenge_url(), kalender_event_url(), kalender_valid_slug()

### Community 9 - "Shared Config"

Cohesion: 0.33
Nodes (0): 

### Community 10 - "Visitor Analytics"

Cohesion: 0.4
Nodes (0): 

### Community 11 - "Production Deployment"

Cohesion: 0.4
Nodes (5): Example Production Shared Layout, Runtime Public Folders, Production Deployment Pattern, Shared Runtime Secrets, Deployment via GitHub

### Community 12 - "Admin Users"

Cohesion: 0.67
Nodes (0): 

### Community 13 - "Event Settings"

Cohesion: 0.67
Nodes (0): 

### Community 14 - "Rewards"

Cohesion: 0.67
Nodes (0): 

### Community 15 - "Tracking Settings"

Cohesion: 0.67
Nodes (0): 

### Community 16 - "Lucky Draw Names"

Cohesion: 0.67
Nodes (0): 

### Community 17 - "Challenge Landing"

Cohesion: 0.67
Nodes (0): 

### Community 18 - "Referrer Dashboard"

Cohesion: 0.67
Nodes (0): 

### Community 19 - "Referral Landing Flow"

Cohesion: 0.67
Nodes (3): Landing Page Acara, Rahasiaemas.id Shared Hosting Installation Guide, Referral WhatsApp Flow

### Community 20 - "Event Referrals"

Cohesion: 0.67
Nodes (3): Event Aware Referrals, Multi Event System, Referral Link Contract

### Community 21 - "Link Builder UI"

Cohesion: 1.0
Nodes (0): 

### Community 22 - "Dashboard WhatsApp"

Cohesion: 1.0
Nodes (0): 

### Community 23 - "Attendance WhatsApp"

Cohesion: 1.0
Nodes (0): 

### Community 24 - "CSV Export"

Cohesion: 1.0
Nodes (0): 

### Community 25 - "Lucky Draw Confirm"

Cohesion: 1.0
Nodes (0): 

### Community 26 - "Lucky Draw Void"

Cohesion: 1.0
Nodes (0): 

### Community 27 - "Mailketing Lists"

Cohesion: 1.0
Nodes (0): 

### Community 28 - "Message Templates"

Cohesion: 1.0
Nodes (0): 

### Community 29 - "Theme Styling"

Cohesion: 1.0
Nodes (0): 

### Community 30 - "Event API"

Cohesion: 1.0
Nodes (0): 

### Community 31 - "Admin Home"

Cohesion: 1.0
Nodes (0): 

### Community 32 - "Lucky Draw Display"

Cohesion: 1.0
Nodes (0): 

### Community 33 - "AI Settings"

Cohesion: 1.0
Nodes (0): 

### Community 34 - "Documentation"

Cohesion: 1.0
Nodes (0): 

### Community 35 - "Email Settings"

Cohesion: 1.0
Nodes (0): 

### Community 36 - "Attendance Reports"

Cohesion: 1.0
Nodes (0): 

### Community 37 - "Events Admin"

Cohesion: 1.0
Nodes (0): 

### Community 38 - "Analytics Export"

Cohesion: 1.0
Nodes (0): 

### Community 39 - "Public Index"

Cohesion: 1.0
Nodes (0): 

### Community 40 - "Integrations"

Cohesion: 1.0
Nodes (0): 

### Community 41 - "Admin Login"

Cohesion: 1.0
Nodes (0): 

### Community 42 - "Admin Logout"

Cohesion: 1.0
Nodes (0): 

### Community 43 - "Lucky Draw Control"

Cohesion: 1.0
Nodes (0): 

### Community 44 - "Marketing Content"

Cohesion: 1.0
Nodes (0): 

### Community 45 - "Legacy Migration"

Cohesion: 1.0
Nodes (0): 

### Community 46 - "Brand Setup"

Cohesion: 1.0
Nodes (0): 

### Community 47 - "Attendance Checkin"

Cohesion: 1.0
Nodes (0): 

### Community 48 - "Attendance Confirm"

Cohesion: 1.0
Nodes (0): 

### Community 49 - "Attendance Finalize"

Cohesion: 1.0
Nodes (0): 

### Community 50 - "Referrer Creation"

Cohesion: 1.0
Nodes (0): 

### Community 51 - "Event Info API"

Cohesion: 1.0
Nodes (0): 

### Community 52 - "AI Landing API"

Cohesion: 1.0
Nodes (0): 

### Community 53 - "Marketing API"

Cohesion: 1.0
Nodes (0): 

### Community 54 - "Publish Landing"

Cohesion: 1.0
Nodes (0): 

### Community 55 - "Style Captions"

Cohesion: 1.0
Nodes (0): 

### Community 56 - "Lead Submission"

Cohesion: 1.0
Nodes (0): 

### Community 57 - "Tracking API"

Cohesion: 1.0
Nodes (0): 

### Community 58 - "Referrer Index"

Cohesion: 1.0
Nodes (0): 

### Community 59 - "Bootstrap"

Cohesion: 1.0
Nodes (0): 

### Community 60 - "Referrer Export"

Cohesion: 1.0
Nodes (0): 

### Community 61 - "Referrer Login"

Cohesion: 1.0
Nodes (0): 

### Community 62 - "Referrer Logout"

Cohesion: 1.0
Nodes (0): 

### Community 63 - "Followup Update"

Cohesion: 1.0
Nodes (0): 

### Community 64 - "Safe ZIP Upload"

Cohesion: 1.0
Nodes (1): Safe ZIP Event Upload

## Knowledge Gaps
- **14 isolated node(s):** `Rahasiaemas.id Shared Hosting Installation Guide`, `Referral WhatsApp Flow`, `Runtime Public Folders`, `Multi Event System`, `Event SDK` (+9 more)
  These have ≤1 connection - possible missing edges or undocumented components.
- **Thin community `Link Builder UI`** (2 nodes): `buat-link.php`, `ui_icon()`
  Too small to be a meaningful cluster - may be noise or needs more connections extracted.
- **Thin community `Dashboard WhatsApp`** (2 nodes): `dashboard.php`, `whatsapp_link()`
  Too small to be a meaningful cluster - may be noise or needs more connections extracted.
- **Thin community `Attendance WhatsApp`** (2 nodes): `event-attendance.php`, `whatsapp_link_att()`
  Too small to be a meaningful cluster - may be noise or needs more connections extracted.
- **Thin community `CSV Export`** (2 nodes): `export.php`, `format_extra_fields_csv()`
  Too small to be a meaningful cluster - may be noise or needs more connections extracted.
- **Thin community `Lucky Draw Confirm`** (2 nodes): `lucky-draw-confirm.php`, `lucky_draw_confirm_json()`
  Too small to be a meaningful cluster - may be noise or needs more connections extracted.
- **Thin community `Lucky Draw Void`** (2 nodes): `lucky-draw-void.php`, `lucky_draw_void_json()`
  Too small to be a meaningful cluster - may be noise or needs more connections extracted.
- **Thin community `Mailketing Lists`** (2 nodes): `mailketing_get_lists.php`, `mailketing_lists_json()`
  Too small to be a meaningful cluster - may be noise or needs more connections extracted.
- **Thin community `Message Templates`** (2 nodes): `message_templates.php`, `build_participant_reply_templates()`
  Too small to be a meaningful cluster - may be noise or needs more connections extracted.
- **Thin community `Theme Styling`** (2 nodes): `theme.php`, `get_theme_css_vars()`
  Too small to be a meaningful cluster - may be noise or needs more connections extracted.
- **Thin community `Event API`** (1 nodes): `event.php`
  Too small to be a meaningful cluster - may be noise or needs more connections extracted.
- **Thin community `Admin Home`** (1 nodes): `index.php`
  Too small to be a meaningful cluster - may be noise or needs more connections extracted.
- **Thin community `Lucky Draw Display`** (1 nodes): `lucky-draw-display.php`
  Too small to be a meaningful cluster - may be noise or needs more connections extracted.
- **Thin community `AI Settings`** (1 nodes): `ai-settings.php`
  Too small to be a meaningful cluster - may be noise or needs more connections extracted.
- **Thin community `Documentation`** (1 nodes): `documentation.php`
  Too small to be a meaningful cluster - may be noise or needs more connections extracted.
- **Thin community `Email Settings`** (1 nodes): `email-settings.php`
  Too small to be a meaningful cluster - may be noise or needs more connections extracted.
- **Thin community `Attendance Reports`** (1 nodes): `event-attendance-report.php`
  Too small to be a meaningful cluster - may be noise or needs more connections extracted.
- **Thin community `Events Admin`** (1 nodes): `events.php`
  Too small to be a meaningful cluster - may be noise or needs more connections extracted.
- **Thin community `Analytics Export`** (1 nodes): `export-analytics.php`
  Too small to be a meaningful cluster - may be noise or needs more connections extracted.
- **Thin community `Public Index`** (1 nodes): `index.php`
  Too small to be a meaningful cluster - may be noise or needs more connections extracted.
- **Thin community `Integrations`** (1 nodes): `integrations.php`
  Too small to be a meaningful cluster - may be noise or needs more connections extracted.
- **Thin community `Admin Login`** (1 nodes): `login.php`
  Too small to be a meaningful cluster - may be noise or needs more connections extracted.
- **Thin community `Admin Logout`** (1 nodes): `logout.php`
  Too small to be a meaningful cluster - may be noise or needs more connections extracted.
- **Thin community `Lucky Draw Control`** (1 nodes): `lucky-draw-control.php`
  Too small to be a meaningful cluster - may be noise or needs more connections extracted.
- **Thin community `Marketing Content`** (1 nodes): `marketing-content.php`
  Too small to be a meaningful cluster - may be noise or needs more connections extracted.
- **Thin community `Legacy Migration`** (1 nodes): `migrate-legacy.php`
  Too small to be a meaningful cluster - may be noise or needs more connections extracted.
- **Thin community `Brand Setup`** (1 nodes): `setup-brand.php`
  Too small to be a meaningful cluster - may be noise or needs more connections extracted.
- **Thin community `Attendance Checkin`** (1 nodes): `attendance-checkin.php`
  Too small to be a meaningful cluster - may be noise or needs more connections extracted.
- **Thin community `Attendance Confirm`** (1 nodes): `attendance-confirm.php`
  Too small to be a meaningful cluster - may be noise or needs more connections extracted.
- **Thin community `Attendance Finalize`** (1 nodes): `attendance-finalize.php`
  Too small to be a meaningful cluster - may be noise or needs more connections extracted.
- **Thin community `Referrer Creation`** (1 nodes): `create_referrer.php`
  Too small to be a meaningful cluster - may be noise or needs more connections extracted.
- **Thin community `Event Info API`** (1 nodes): `event_info.php`
  Too small to be a meaningful cluster - may be noise or needs more connections extracted.
- **Thin community `AI Landing API`** (1 nodes): `generate_ai_landing.php`
  Too small to be a meaningful cluster - may be noise or needs more connections extracted.
- **Thin community `Marketing API`** (1 nodes): `generate_marketing_content.php`
  Too small to be a meaningful cluster - may be noise or needs more connections extracted.
- **Thin community `Publish Landing`** (1 nodes): `publish_ai_landing.php`
  Too small to be a meaningful cluster - may be noise or needs more connections extracted.
- **Thin community `Style Captions`** (1 nodes): `regenerate_style_caption.php`
  Too small to be a meaningful cluster - may be noise or needs more connections extracted.
- **Thin community `Lead Submission`** (1 nodes): `submit_lead.php`
  Too small to be a meaningful cluster - may be noise or needs more connections extracted.
- **Thin community `Tracking API`** (1 nodes): `track.php`
  Too small to be a meaningful cluster - may be noise or needs more connections extracted.
- **Thin community `Referrer Index`** (1 nodes): `index.php`
  Too small to be a meaningful cluster - may be noise or needs more connections extracted.
- **Thin community `Bootstrap`** (1 nodes): `bootstrap.php`
  Too small to be a meaningful cluster - may be noise or needs more connections extracted.
- **Thin community `Referrer Export`** (1 nodes): `export.php`
  Too small to be a meaningful cluster - may be noise or needs more connections extracted.
- **Thin community `Referrer Login`** (1 nodes): `login.php`
  Too small to be a meaningful cluster - may be noise or needs more connections extracted.
- **Thin community `Referrer Logout`** (1 nodes): `logout.php`
  Too small to be a meaningful cluster - may be noise or needs more connections extracted.
- **Thin community `Followup Update`** (1 nodes): `update-followup.php`
  Too small to be a meaningful cluster - may be noise or needs more connections extracted.
- **Thin community `Safe ZIP Upload`** (1 nodes): `Safe ZIP Event Upload`
  Too small to be a meaningful cluster - may be noise or needs more connections extracted.

## Suggested Questions
_Questions this graph is uniquely positioned to answer:_

- **Why does `get_db()` connect `Event Data Admin` to `AI Landing Builder`, `Brand Access Control`, `Walk-in Settings`, `Mailketing Calendar`?**
  _High betweenness centrality (0.077) - this node is a cross-community bridge._
- **Why does `get_ai_provider_settings()` connect `AI Landing Builder` to `Event Data Admin`, `Attendance Codes`?**
  _High betweenness centrality (0.046) - this node is a cross-community bridge._
- **Why does `clean()` connect `Walk-in Settings` to `Brand Access Control`, `Attendance Codes`?**
  _High betweenness centrality (0.042) - this node is a cross-community bridge._
- **Are the 7 inferred relationships involving `get_db()` (e.g. with `get_ai_provider_settings()` and `save_ai_provider_settings()`) actually correct?**
  _`get_db()` has 7 INFERRED edges - model-reasoned connections that need verification._
- **Are the 6 inferred relationships involving `clean()` (e.g. with `lucky_draw_find_event()` and `lucky_draw_status_event()`) actually correct?**
  _`clean()` has 6 INFERRED edges - model-reasoned connections that need verification._
- **What connects `Rahasiaemas.id Shared Hosting Installation Guide`, `Referral WhatsApp Flow`, `Runtime Public Folders` to the rest of the system?**
  _14 weakly-connected nodes found - possible documentation gaps or missing edges._
- **Should `Event Data Admin` be split into smaller, more focused modules?**
  _Cohesion score 0.12 - nodes in this community are weakly interconnected._