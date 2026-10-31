# Mail test routing

Environment Settings → Mail stores `mail.testRecipient` and `mail.testMode` in the selected environment JSON. No project defaults or SMTP credentials are changed by this update.

- DEV always uses test routing, including when DEBUG is false or the test-mode switch is off.
- STAGING and LIVE use test routing when `mail.testMode` is true. DEBUG does not select delivery mode.
- An empty test recipient uses `testmail@systopic.media`. A nonempty invalid address blocks sending.
- Immediately before SMTP delivery, To/CC/BCC become one test recipient, Reply-To becomes that address, and the subject receives one `Test mail // ` prefix. This includes explicit SMTP server arguments, direct public recipient-array assignments and direct SMTPMailer calls.
- DEV's local Mailpit fallback uses the same rule. Without SMTP configuration, other stages still refuse delivery.

Test routing changes the envelope, not message contents or attachments. Use an authorised test mailbox. Login-code contents remain excluded from the normal email log.

## Verification

`php tests/environment-settings.php` checks JSON persistence and recipient validation in temporary files.

`python3 tests/mail-routing.py` runs the real SMTP sender against a temporary server bound only to 127.0.0.1. It checks DEV with DEBUG on/off, explicit STAGING/LIVE test mode, normal delivery envelopes, CC/BCC removal, direct SMTP callers, the default recipient, invalid-recipient blocking and sensitive-log redaction. It never connects to a real SMTP service.
