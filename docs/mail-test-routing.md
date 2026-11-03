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

## Delivery format and appearance

The SMTP sender emits CRLF-normalized MIME with quoted-printable text, a plain-text alternative to HTML, correctly nested attachments and a random Message-ID under the sender domain. BCC addresses remain envelope-only; header values reject line breaks. SMTP DATA is dot-stuffed. These measures improve interoperability; they do not guarantee inbox delivery. Domain authentication (SPF, DKIM, DMARC), sender reputation and mailbox-provider policies remain installation responsibilities.

Built-in activation, reset, invitation, sign-in-code, security-notice and SMTP-test messages use `Mail\Letter`. It provides light colours inline and dark colours through `prefers-color-scheme`, with Outlook `data-ogsc` support. Clients that strip these rules retain the light version; actual client rendering must be tested separately. No recipient tracking is introduced.

### Logo variants (1 November 2026)

System letters use Theme's `wordLight` (light surface) and `wordDark` (dark
surface) slots, including project overrides. The general logo slot no longer
bypasses those variants. Both word marks have transparent surrounds; there is no
logo plate. Light is the inline/default fallback; dark-mode CSS and Outlook's
`data-ogsc` selector switch variants. Clients stripping those styles keep the
light fallback. Existing delivered messages are unchanged.

`tests/mail-logo-ui.mjs` renders the real Letter template and checks
light → dark → light without reloading at 1280, 834 and 390 px. Screenshots were
visually inspected. Actual mail-client theme toggles still need checking using a
newly generated message; no delivery is performed by this fixture.

Code-bearing activation, password-reset, invitation and sign-in emails share a
short confidentiality notice: do not forward the email or share its code/link,
because those credentials can grant account access. `tests/mail-code-notices.php`
renders all four templates (both CodeMail purposes) using disposable values and
checks that the notice appears once. The logo's lower spacing is 20 px.
