# Mail delivery, dark appearance and tertiary buttons

1. SMTP: PASS local transport tests (`mail-routing.py`, `mail-smtp-failures.py`), MIME fixture (`mail-mime.php`) plus Python standard-library MIME parsing. DEV, STAGING/LIVE test mode, CC/BCC envelope handling, rejection diagnoses, Unicode, binary attachments and header-injection rejection checked. No external mail sent. `mail-subject.php`, `mail-test-delivery.php`, `mail-failure.php`, `mail-probe-errors.php` passed.
2. Mail appearance: shared `Mail\Letter` used by activation, reset, invitation, code, security notices and test delivery. Browser fixture rendered at 1280/834/390px in both colour schemes, screenshots inspected, no horizontal overflow or JS errors. BLOCKED: real Apple Mail/Gmail/Outlook and physical iPad mail-client rendering not exercised. Project-specific overridden templates remain installation-owned.
3. Outline: existing shared button geometry, radius and focus retained; dashboard legal links use the new outline variant. Local actual-CSS fixture hover and keyboard focus checked, screenshots inspected in light/dark. BLOCKED: live authenticated dashboard interaction not exercised in this run.

PHP syntax and whitespace checks passed. Existing project PHP error log timestamp/size unchanged. SPF/DKIM/DMARC and actual inbox placement require a domain-owner/client delivery test.
