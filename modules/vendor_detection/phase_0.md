# Phase 0 — Vendor Detection & Data Requests (Design Scope)

## Purpose

This document defines the **scope, definitions, and data sources** for the vendor detection and data‑access facilitation feature discussed in Cypht issue #710.
Phase 0 is **design‑only**: no code, only shared understanding and explicit decisions to guide later implementation phases.

The goal is to ensure that vendor detection, GDPR/data‑request integration, and UI behavior are **consistent, explainable, and modular**.

---

## 1. Definition of “Vendor / Platform Sender”

### 1.1 What we mean by “vendor sender”

A **vendor sender** is a third‑party platform or service that sends emails either:

* **On its own behalf** (e.g. Salesforce sending account notifications), or
* **On behalf of customer organizations** (e.g. Amazon SES, Mailchimp, SendGrid).

These vendors typically belong to one of the following categories:

* Email Service Providers (ESP)
* CRM platforms
* Marketing automation platforms
* Transactional email infrastructure providers

Examples include (non‑exhaustive):

* Amazon SES
* Salesforce
* Microsoft (Outlook / Dynamics / Azure mail services)
* Mailchimp
* SendGrid

### 1.2 What is *not* considered a vendor sender

The following are **out of scope** for vendor detection:

* Individually hosted SMTP servers with no identifiable platform
* Personal or small self‑managed mail servers
* Pure mailbox providers when no platform indicators are present

---

## 2. Detection Scope & Evidence Rubric

Vendor detection is **heuristic‑based** and may never be 100% certain. Therefore, detection results must include **evidence** and a **confidence level**.

### 2.1 Accepted technical evidence

Vendor detection may rely on one or more of the following:

* **DKIM signatures**

  * DKIM `d=` domain matching a known vendor domain
* **SMTP `Received:` headers**

  * Hostnames or domains known to belong to a vendor
* **SPF / Return‑Path domains**
* **Vendor‑specific headers**

  * e.g. `X-SES-*`, `X-SG-*`, `X-Mailer` values

The `From:` header alone is **not considered reliable evidence**.

---

## 3. Confidence Levels

Detection results must include a confidence tier indicating how reliable the identification is.

### 3.1 Confidence tiers

| Level      | Meaning                                                                                                   |
| ---------- | --------------------------------------------------------------------------------------------------------- |
| **High**   | Strong cryptographic or explicit vendor evidence (e.g. DKIM signed by vendor domain, proprietary headers) |
| **Medium** | Consistent infrastructure patterns (e.g. `Received:` chain + known domains)                               |
| **Low**    | Weak heuristics only (e.g. hostname resemblance, indirect indicators)                                     |

The confidence tier must be derived from documented rules, not implicit assumptions.

---

## 4. Separation of Roles: Platform vs Data Controller

Some emails involve **two distinct actors**:

1. **Technical sending platform** (e.g. Amazon SES)
2. **Data‑controlling organization** (e.g. `example.com`)

The system must:

* Distinguish these roles when possible
* Allow both to be surfaced to the user

Typical behavior:

* **Abuse reporting** → platform level
* **Data access / deletion (GDPR)** → data‑controlling organization

This distinction must be preserved throughout the design.

---

## 5. Data Sources for Data‑Access Requests

### 5.1 Primary source

The primary source for determining whether an organization supports structured data‑access requests is:

* **[https://www.datarequests.org](https://www.datarequests.org)**

### 5.2 Usage policy

* Cypht will **not** perform live network requests to datarequests.org at runtime
* Instead, Cypht will rely on a **locally stored snapshot** of relevant entries

This ensures:

* User privacy
* Offline functionality
* Predictable behavior

### 5.3 Update policy

The datarequests.org snapshot:

* Is stored in a versioned data file (e.g. JSON)
* Is updated manually per release, or via an optional maintenance script
* Is treated as **informational**, not authoritative or legally binding

---

## 6. Expected Outputs of Phase 0

Phase 0 is considered complete when:

* A written definition of “vendor sender” exists
* Accepted detection evidence is documented
* Confidence tiers are clearly defined
* Data sources and update policies are documented

No code, UI, or integration work is expected at this stage.

---

## 7. Design Principles

* **Modularity first**: detection, registry lookup, UI, and actions remain separate components
* **Explainability**: all detections must be justifiable with visible evidence
* **User agency**: Cypht facilitates actions but does not make decisions on behalf of users
* **Privacy‑respecting**: no unsolicited data sharing or background network calls

---

## 8. Relationship to Future Phases

This document provides the foundation for:

* Phase 1 — Vendor Detection Core
* Phase 3 — Data Request Actions (GDPR)
* Phase 4 — Abuse / Spam Reporting

Any changes to scope or definitions should update this document before code changes are made.
