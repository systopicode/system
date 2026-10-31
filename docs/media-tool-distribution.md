# Project-local FFmpeg / FFprobe distribution

System and CMS invoke external executables; they do not include FFmpeg binaries.
For shared hosting, a project can supply compatible files under var/bin/ or bin/.
The central License view includes full LGPL 2.1, GPL 2 and GPL 3 texts plus a
build-dependent FFmpeg notice. These alternatives do not identify a build's
actual licence or satisfy its source obligations by themselves.

For every project release carrying binaries, keep alongside them:

- Exact version, platform, original download URL and SHA-256 of each executable.
- The actual build's licence output / copyright notices and configuration.
- All required third-party notices, corresponding source archives and build
  scripts/configuration for that exact build. Check whether GPL/version3/nonfree
  options were enabled. Do not redistribute a non-redistributable build.
- A copy of licenses/ffmpeg/distribution.json. The built-in download helper writes
  known download provenance and copies the general licence texts there. Its
  requires-build-review status is deliberately not a distribution approval.

For FTP-provisioned binaries, fill the adjacent distribution.example.json and
retain the actual sources/build records before distributing the project.
Host-managed executables are separate from a project binary distribution.

Do not put project binaries or their generated manifests into the System/CMS
source packages. No binaries are installed on a real project by this change.
