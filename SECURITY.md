# Security Policy

Cypht takes the security of our users and their mail seriously. This document explains how to report a vulnerability and how we handle it.

Please report security issues to **security [at] cypht.org**. That mailbox is the central intake for vulnerability reports so we can triage them privately and consistently.

Do **not** open a public GitHub issue, pull request, or discussion for a suspected vulnerability. Public reports can put users at risk before a fix is available.

## Supported versions

We investigate reports that affect currently supported Cypht code. Supported code includes the `master` branch, the 2.x line, and the 1.4.x line. Security fixes are applied there as appropriate.

| Version | Supported |
| --- | --- |
| `master` (development) | Yes |
| 2.x | Yes |
| 1.4.x | Yes |


## Reporting a vulnerability

Email **security [at] cypht.org** with as much of the following as you can:

- A clear description of the issue and its impact (for example: account takeover, XSS, CSRF, SSRF, information disclosure, remote code execution)
- Affected version, commit, or Docker image tag
- Step-by-step reproduction instructions
- Proof of concept, request/response samples, or screenshots
- Your environment: PHP version, and how Cypht is deployed (source, Docker, or another integration)
- How you would like to be credited, or that you prefer to remain anonymous


## What happens next

1. **Intake.** We acknowledge the report and track it privately.
2. **Analysis.** We review the report, related code, and the claimed impact.
3. **Reproduction.** We try to reproduce the issue to confirm it is real and to understand the conditions required.
4. **Fix.** Confirmed vulnerabilities are patched. We aim to ship a release (or a security-only patch release) once a fix is ready.
5. **Release and credit.** After the security release is published, we credit the reporter by name or handle if they want that; otherwise we keep the report anonymous.

Cypht is a volunteer project and does not currently offer a paid bug bounty. We still treat valid reports as a priority and are grateful for responsible disclosure.

## Coordinated disclosure

Do **not** disclose the issue publicly until we have published a security release. That includes GitHub issues, pull requests, social media, blog posts, write-ups, proof-of-concept code, and any other public channel.

Once the security release is published, you are free to discuss the issue. If you want credit, we will name you in the release notes and related public communication.

If we cannot reproduce the report, or if we determine it is not a security vulnerability, we will explain why. If you later disagree, you are welcome to follow up with more information.

Thank you for helping keep Cypht and its users safe.
