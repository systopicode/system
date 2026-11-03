# Mobile block 2 — original items 5 and 6

Shared table heading sizing now stays flexible on expansion; closed/open row padding matches. Rows without details also receive the heading marker. Detail content uses the available width and wraps long audit SQL. Sorting grips are hidden for expanded rows. Existing disclosure buttons remain keyboard-accessible.

Theme contrast is a footer accordion directly under Colours, using the existing summary/button structure. Mobile secondary header actions move into the existing More menu; highlighted actions remain visible. Action nodes return to their original positions on desktop.

Verification (local Dev, read-only):
- WebKit at 390, 834 and 1440 px: Global Settings, Media Settings, Theme, Backup; screenshots captured and inspected for the changed layouts. No page errors.
- Touch-enabled WebKit at 390 px: Media Settings, Database Status and Audit toggles. Heading offset and width unchanged on expansion; sorting grip hidden in expanded media-format row.
- Backup More menu opens; resize to desktop restores actions and removes More.
- PHP lint: Contrast.php, ThemeFile.php. JS syntax: tableFit.js, mobile.js.
- No settings, database audit actions, backup restores or deletions executed.

Remaining verification: physical iPhone/iPad Safari (including native browser chrome) is not controlled by these emulated tests. The prior Device Hub input failure remains. Dark appearance and relevant server logs were not checked in this block; no server-side errors occurred in the exercised views, but this does not substitute for inspecting logs.
