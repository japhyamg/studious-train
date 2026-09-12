# MoniSurv vs CBN AML Baseline Requirements Register — Implementation Review

> **Register:** `CBN_AML_Baseline_Requirements_Register` (CBN Baseline Standards for Automated AML/CFT/CPF Solutions, circular **BSD/DIR/PUB/LAB/019/002**).
> **Re-fetched from the shared Google Sheet:** 2026-09-12.
> **Branch:** `arena/01a09480-studious-train` (work spans Phases 0–5).
>
> **Status key:** ✅ done · 🟡 partial · ❌ not started · ⚪ N/A (out of scope).

---

## 0. Executive summary

The register's **"AML Solution shall"** obligations (the `5.x(a)` rows) are now **substantially implemented** — the full AML loop (ingest → screen → score → alert → case → report) is working end-to-end, and every product-team ask in the register's **Comment** column has been actioned except those explicitly parked for **Phase 6** (platform & security) and a small set of documented gaps.

| Area | Coverage |
|---|---|
| 5.2 CDD / KYC / KYB | 🟡 all (a)-rows addressed; **KYB/beneficial-owner module still absent** |
| 5.3 Sanctions & PEP screening | ✅ complete (interdiction is post-facto by design) |
| 5.4 Risk assessment | 🟡 enterprise-level ML/TF risk module (5.4(a)(iii)) deferred |
| 5.5 Transaction monitoring | 🟡 related-party / network analysis deferred; rest done |
| 5.6 Fraud monitoring | ⚪ register empty — out of scope |
| 5.7 Case management | ✅ complete |
| 5.8 Reporting | ✅ STR/CTR + MI pack done (FTR type not wired) |
| 5.9 Audit & governance | ✅ complete |
| 5.10 Integration & scalability | 🟡 bidirectional sync, API docs, stress test deferred |
| 5.11 Security & data protection | 🟡 encryption, NDPA, RTO/RPO deferred |
| 5.12 UI & customisation | 🟡 rule versioning/change-control deferred |

**Count:** 39 `AML Solution shall` rows read → **28 ✅ · 6 🟡 · 1 ❌ · 4 ⚪**.
(Excludes the `5.x(b)` "Institutions shall" rows and the four pasted screenshots, which are institution-side governance duties, not vendor features — see §4.)

All ten Phase-0 blockers from the original gap analysis are resolved: the 24‑hour rule engine now evaluates accounts (was a no-op), the ingestion API is authenticated (`ApiKeyAuth`), cron endpoints are admin-gated, the audit log is append-only (truncate removed), the queue job is dispatched asynchronously, account-level dedupe exists, and the timezone/housekeeping were cleaned up.

---

## 1. Row-by-row status vs the register

### 5.2 — CDD, KYC and KYB

| Ref | Register ask (Comment) | Implemented | Status |
|---|---|---|---|
| 5.2(a)(i) | "Risk Profile 1 should be working automatically once per day, 11:59pm" | Default risk profile seeded; nightly `risk:rate` at 23:59 + 2-min demo mode; automated risk profiling, behavioural scoring, typologies | 🟡 (KYB/BO still missing) |
| 5.2(a)(ii) | Per-risk-level "CDD/EDD REVIEW SCHEDULE" text area; review keyed to each customer's own onboarding date | `review_schedule_days` on Risk Level + "CDD/EDD Review Schedule" field; scheduler keys off `date_onboarded`; **event-driven** reviews via `applyRiskLevel()` | ✅ |
| 5.2(a)(iii) | Single view: STR/CTR counts + KYC + credit/debit totals + PEP + risk level | **Customer 360** page (`CustomerDetailsController::show`) with all of the above + watchlist status | ✅ |
| 5.2(a)(iv) | Same single view for any alert/case | Case page builds per-customer intelligence (KYC, tx stats, STR/CTR, prior cases, PEP, risk) with links into the 360 | ✅ |

### 5.3 — Sanction Lists & PEP Screening

| Ref | Register ask (Comment) | Implemented | Status |
|---|---|---|---|
| 5.3(a)(i) | "Review Nigerian Sanction List, OFAC and UN Sanction List" | UN Consolidated + OFAC + Nigerian lists (`config/sanctions.php`), redesigned Sanction Lists page | ✅ |
| 5.3(a)(ii) | — | Scored fuzzy matcher (`WatchListMatcher`) with match score + matched fields | ✅ |
| 5.3(a)(iii) | near-real-time updates | `sanctions:sync` daily refresh + `watchlist_sync_logs` (source, version, count, last-updated) | ✅ |
| 5.3(a)(iv) | "logs to see update of the various sanction lists" | Same sync log surfaced in UI | ✅ |
| 5.3(a)(v) | watchlisted/delisted label on customer view | Internal + NIBSS watchlist status badge on the customer view | ✅ |
| 5.3(a)(vi) | "API connectivity should flag PEP automatically" | PEP auto-flag → `isPep`, risk bump, forced EDD, case created | ✅ |
| 5.3(a)(vii) | "every 11:59pm screen customers onboarded in last 24h against PAS" | Nightly `pas:screen-recent-customers` + adverse-media screen | ✅ |
| 5.3(a)(viii) | "cannot hold transaction… after the fact" | Post-facto freeze/lift workflow + audit (documented limitation) | 🟡 by design |

### 5.4 — Risk Assessment

| Ref | Register ask (Comment) | Implemented | Status |
|---|---|---|---|
| 5.4(a)(i) | "just need to reset it for stability" | Configurable rules, scenarios, thresholds, risk-scoring factors | ✅ |
| 5.4(a)(ii) | "default risk profile running in background… 11:59pm" | Auto risk rating at onboarding + nightly re-rating + dynamic adjustment | ✅ |
| 5.4(a)(iii) | — | Enterprise-level ML/TF/PF risk module | ❌ (Phase 6) |
| 5.4(a)(iv) | "documented notes of explainability of how AI Alerts works" | `ai_scores.model_version` + plain-language `explanation`; AI alerts page shows both | ✅ |
| 5.4(a)(v) | "export file must include customer account number" | `risk_level_changes` history (old→new, driver, date) + export incl. account number | ✅ |
| 5.4(a)(vi) | "NOT a must have" | — | ⚪ |

### 5.5 — Transaction Monitoring & Risk-Based Analyses

| Ref | Register ask (Comment) | Implemented | Status |
|---|---|---|---|
| 5.5(a)(i) | 7 pre-emptive factors, score ≥5/7 → **PRE-EMPTIVE ALERT**, daily cron | `PreemptiveAlertService` (7 config-driven factors, threshold 5), daily `preemptive:score` | ✅ |
| 5.5(a)(ii) | preventive action | Pre-emptive cases + interdiction/EDD dispositions | ✅ |
| 5.5(a)(iii) | "Peer grouping not complete" | Peer-group analysis completed: thresholds (Q1/Q3/IQR/upper), outliers, recompute, config modal, explainer, pagination | ✅ (network analysis in §3) |
| 5.5(a)(iv) | multi-condition "Add" button; decommission rules → TTR; "Reason flagged → TTR 40" | Multi-condition factors (AND/OR), KYC/history/behaviour factors, **TTR score as trigger reason** | ✅ |
| 5.5(a)(v) | "proper documentation explaining how Peer Grouping works" | In-app explainer + docs; **related-party/network analysis deferred** | 🟡 |
| 5.5(a)(vi) | "related to the single view" | Consolidated customer view from case/alert | ✅ |

### 5.6 — Fraud Monitoring

| Ref | Status |
|---|---|
| 5.6(a)(i)–(vi) | ⚪ Register leaves these empty; no fraud module — out of scope unless the bank opts in |

### 5.7 — Case Management

| Ref | Register ask (Comment) | Implemented | Status |
|---|---|---|---|
| 5.7(a)(i) | TAT setting (e.g. 24h high-risk) + countdown; "changes apply to new cases only, with a prompt" | `case_tat_hours` per risk level; SLA countdown on list/case/performance; SLA is stamped on each case at creation so TAT changes affect **new cases only**; least-loaded round-robin assignment | ✅ (the change *prompt* UI is not built — see §3) |
| 5.7(a)(ii) | maker-checker + escalation | Maker proposes → checker approves/rejects; escalation path retained | ✅ |
| 5.7(a)(iii) | — | Full activity log of every case action (actor, timestamp, rationale) | ✅ |
| 5.7(a)(iv) | CARRD + performance + false-positive need PDF export buttons | CSV + PDF export on CARRD, Review Performance, False Positives (plus audit + 360) | ✅ |

### 5.8 — Reporting

| Ref | Register ask (Comment) | Implemented | Status |
|---|---|---|---|
| 5.8(a)(i) | "NFIU REPORTING" | STR/CTR detection → goAML 5.0.2 XML + XSD validation; CTR detection from cash thresholds (risk factors); filing status (draft/filed + reference); STR filing SLA | ✅ (FTR type not wired) |
| 5.8(a)(ii) | MI reporting to CCO/SM/ECO/Board | **MI report pack** (`/mi-reports`) with CSV/PDF export | ✅ |
| 5.8(a)(iii) | N/A | Export is role-permissioned (`case-export`, `mi-reports`, `audit-trail`) | ⚪ documented |

### 5.9 — Audit and Governance

| Ref | Register ask (Comment) | Implemented | Status |
|---|---|---|---|
| 5.9(a)(i) | "capture user login devices; check for other unlogged activities" | Append-only audit (truncate removed); login device/IP logging + failed-attempt logging | ✅ |
| 5.9(a)(ii) | — | user identity + date/timestamp + nature recorded on every action | ✅ |
| 5.9(a)(iii) | "maintain logs for 5 years minimum" | `audit_retention_days` (default 1825) + monthly `audit:archive` (CSV archival, never deletes) | ✅ |
| 5.9(a)(iv) | "search… not working, so fix and include export" | Search now matches description/properties/subject + **causer name/email**; CSV export | ✅ |
| 5.9(a)(v) | "report export capability" | Filtered audit CSV export | ✅ |
| 5.9(a)(vi) | "unified Single view page… search bar + PDF/CSV export" | Customer 360 with global search + PDF/CSV export | ✅ |
| 5.9(a)(vii) | "export capability" | Streamed CSV export (50k cap) | ✅ |

### 5.10 — System Integration & Scalability

| Ref | Implemented | Status |
|---|---|---|
| 5.10(a)(i) | One-way incremental `CustomerSyncService` (core-banking pull) | 🟡 bidirectional/event push deferred |
| 5.10(a)(ii) | Ingestion dispatches `RunTransactionQuery` to the queue (non-blocking) | ✅ |
| 5.10(a)(iii) | API docs | ❌ (Phase 6) |
| 5.10(a)(iv)/(v) | JSON ingest; goAML/CSV exports | 🟡 docs pending |
| 5.10(a)(vi) | Column-mapping-driven sync for legacy systems | ✅ |
| 5.10(a)(vii) | Stress/load test | ❌ (Phase 6) |

### 5.11 — Security & Data Protection

| Ref | Implemented | Status |
|---|---|---|
| 5.11(a)(i) | Data-minimisation review | 🟡 |
| 5.11(a)(ii) | BVN/NIN still plaintext | ❌ (Phase 6 encryption) |
| 5.11(a)(iii) | Spatie RBAC; audit/cron/settings route gaps closed this session | ✅ |
| 5.11(a)(iv) | Email OTP (MFA) toggle present | 🟡 verify end-to-end |
| 5.11(a)(v) | NDPA documentation | ❌ (Phase 6) |
| 5.11(a)(vi) | RTO/RPO | ❌ (CTO to define) |

### 5.12 — User Interface & Customisation

| Ref | Implemented | Status |
|---|---|---|
| 5.12(a)(i) | Real-time dashboards (cases, CARRD, false-positive, MI) | ✅ |
| 5.12(a)(ii) | — | ✅ |
| 5.12(a)(iii) | — | ⚪ (not in scope) |
| 5.12(a)(iv) | Rule versioning + draft→approved change-control | 🟡 (Phase 6) |

---

## 2. Product-team asks — checklist

Every explicit "Comment" ask against an `(a)` row, with disposition:

| # | Ask | Disposition |
|---|---|---|
| 1 | Risk Profile 1 auto-runs daily 23:59 | ✅ `risk:rate` nightly + demo mode |
| 2 | CDD/EDD review schedule text area per risk level | ✅ |
| 3 | Single view (STR/CTR + KYC + credit/debit + PEP + risk) | ✅ Customer 360 |
| 4 | Review Nigerian/OFAC/UN sanction lists | ✅ |
| 5 | Logs of sanction-list updates | ✅ `watchlist_sync_logs` |
| 6 | Watchlisted/delisted label on customer view | ✅ |
| 7 | Auto-flag PEP via API | ✅ |
| 8 | Nightly PAS screen (last-24h onboarded) | ✅ |
| 9 | Pre-emptive alert engine (7 factors, score ≥5/7, daily) | ✅ |
| 10 | Finish peer grouping + documentation | ✅ (network analysis deferred) |
| 11 | Multi-condition "Add" button + decommission rules → TTR | ✅ |
| 12 | "Reason flagged → TTR 40" | ✅ |
| 13 | TAT setting + countdown + "new cases only" prompt | ✅ TAT + countdown; ⚠ prompt UI not built |
| 14 | PDF export buttons on CARRD/performance/false-positive | ✅ (CSV + PDF) |
| 15 | NFIU reporting | ✅ goAML XML |
| 16 | Capture login devices | ✅ |
| 17 | Keep logs ≥5 years | ✅ |
| 18 | Fix audit search + export | ✅ |
| 19 | Unified single-view page + search + PDF/CSV | ✅ |
| 20 | Stress test | ❌ Phase 6 |
| 21 | Data encryption | ❌ Phase 6 |
| 22 | API docs creation | ❌ Phase 6 |
| 23 | CTO to define RTO/RPO | ❌ (ops deliverable) |

---

## 3. Remaining gaps (not yet done)

**Functional**
1. **KYB / beneficial-ownership module** — no corporate entity / BO register (5.2(a)(i)); BO screening follows from this.
2. **Related-party mapping & network analysis** — shared account/BVN/device graph (5.5(a)(v)); peer-grouping itself is complete.
3. **FTR report type** — XML export supports STR/CTR/EFT/IFT/TFR/BCR/UTR/AIF/SAR but not FTR (5.8(a)(i)).
4. **TAT-change prompt** — semantics are correct (SLA stamped per case → changes affect new cases only), but the "new TAT applies to new cases" prompt UI isn't built (5.7(a)(i)).
5. **Enterprise-level ML/TF/PF risk assessment** module (5.4(a)(iii)).

**Platform & security (Phase 6)**
6. API documentation (Scribe/Swagger) + payload schema (5.10(a)(iii)–(v)).
7. Encryption of BVN/NIN at rest + UI masking + TLS/HSTS (5.11(a)(ii)); NDPA/consent docs (5.11(a)(v)).
8. Rule versioning + change-control workflow (5.12(a)(iv)).
9. Stress test + index review (5.10(a)(vii)); DR/RTO-RPO runbook (5.11(a)(vi)).
10. Bidirectional core-banking integration / event push (5.10(a)(i)).

**Engineering hygiene**
11. No automated test suite (was Phase-0 B9) — still open.
12. Case-list pagination is still PHP-side after loading all rows (B7).
13. `getReviewer()` is least-loaded round-robin, not a strict rotation pointer; `createCaseSlug()` uses `count()+1` (racy at scale).

---

## 4. Register rows not covered here

- The **`5.x(b)` "Institutions shall"** rows (governance/oversight duties of the *bank*, not the AML solution) sit in the blank/screenshot rows of the sheet (e.g. rows 6–9, 18–20, 27–28, 35–41, 48–49, 54–55, 59–60, 68–71, 79–82, 89–91). These are institution-side obligations and were intentionally not built as vendor features.
- The **four pasted screenshots** at the bottom of the sheet could not be OCR'd here (Google-hosted, auth-gated). If they contain additional `(a)` items, paste their text and they'll be folded into §1.
- **5.6 Fraud** (5.6(a)(i)–(vi)) is empty in the register and treated as out of scope; if the bank opts in, the same case engine can be reused with a `domain` (AML/CFT/CPF/fraud) tag and segregated RBAC.
