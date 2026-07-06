# Remaining Security Gaps

This file tracks security items that are still not fully implemented in the application.

Related living system reference:
- [CODEX_CONTEXT.md](./CODEX_CONTEXT.md)

## Implemented Recently

- Privileged-user MFA at login
- Strong password policy
- Account lockout after repeated failures
- Server-enforced inactivity timeout
- Password expiry enforcement for active sessions
- Admin IP allowlisting
- Bank-level tenant segregation
- Assigned-portfolio enforcement
- Meta webhook signature validation
- Meta webhook replay protection
- WhatsApp opt-out suppression
- Lawful-basis gating for WhatsApp sends
- Encrypted Meta secret storage in `system_settings`
- Export approval workflow
- Unusual export detection with escalation email
- Meta permission validation against Graph API
- Production WhatsApp send blocking when Meta permission validation is stale or failed
- Sensitive-field masking
- Browser-layer copy/cut/paste/drop restrictions on selected sensitive inputs

## Partial / Limited Controls

### Screenshot Prevention

Status: `partial`

What exists:
- Sensitive-route watermark deterrence tied to the logged-in user and route context.
- Print deterrence / sensitive-route styling where applicable.

Important limitation:
- Standard web applications cannot reliably block operating-system or browser screenshots.
- This control should be described as a deterrence and attribution measure, not absolute screenshot prevention.

### Meta Token Rotation Workflow

Status: `partial`

What exists:
- Token expiry tracking
- Rotation metadata
- Rotation audit logging
- Permission validation health checks
- Production send blocking when validation is stale/failed

What is still missing:
- Scheduled rotation reminders/escalations
- Forced rotation workflow
- Historical rotation register/reporting

### Separate Dev / Prod Meta App Enforcement

Status: `partial`

What exists:
- `meta_environment`
- Production-only validation requirements
- Permission validation checks

What is still missing:
- Hard enforcement that development/staging/production use different Meta app identities
- Explicit environment-specific app-ID mismatch policy

### Least-Privilege Meta Permission Governance

Status: `partial`

What exists:
- Graph token validation
- Required scope checks
- Recommended scope checks
- Audit logging around validation

What is still missing:
- Formal approval workflow for Meta configuration changes
- Historical permission review trail/report

## Not Yet Implemented

### File Transfer / Import Hardening

- Encrypted bank API import integration
- Bank-approved secure file-transfer portal integration
- Bank-facing reconciliation report workflow

### Monitoring and Governance Depth

- Full read-event auditing for every sensitive record view
- Dedicated file download audit coverage outside governed export flows
- In-app unusual export monitoring dashboard / case management
- Upstream bank suppression-list ingestion and sync workflow

### Formal Operational Response

- Formal in-app incident response workflow beyond the current security incident case management baseline
- Formal in-app breach notification workflow with regulator/bank notification evidence and closure pack

### Secure SFTP / Bank File-Transfer Ingestion

Status: `partial`

What exists:
- Bank-scoped transfer profiles
- Stored environment / host / credential governance
- Test and sync run logging
- Compliance Console management UI
- Safe failure when SFTP adapter support is missing

What is still missing:
- Active SFTP adapter package installation in the environment
- Controlled file pull into staged import upload records
- Automated scheduled polling
- Archived remote-file handling after successful retrieval

## Out of Application Scope

These should not be claimed as implemented by the web application itself:

- Firewall / network perimeter enforcement
- Database network isolation
- Encrypted backups
- Point-in-time recovery operations
- Server patching
- SSH key-only access
- Root login disablement
- Centralized SIEM / SOC monitoring
- Endpoint protection / EDR
- Intrusion detection
- Penetration testing program
- Secure SDLC / code-review governance outside the running app

## Update Rule

Whenever one of the items above is implemented, this file and `CODEX_CONTEXT.md` should be updated in the same task.
