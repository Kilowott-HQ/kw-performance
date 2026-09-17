# Working conventions for this repo

- **Version bump on every change**: whenever a code change is made to the plugin, bump `Version:` in the `kw-performance.php` header and the `KWPERF_VERSION` constant together, following the existing `YY.MM.NN` scheme (reset `NN` to `01` on a new month, otherwise increment `NN`).
- **Commit and push automatically**: after making a change (with the version bump included in the same commit), commit it and push directly to `origin/main` without asking for confirmation each time. This instruction itself is the standing authorization for that push.
- **Do not create a release**: do not tag, do not run the `Release` GitHub Actions workflow (`.github/workflows/release.yml`), and do not create a GitHub Release / build the distributable zip via CI. Releases are cut separately and explicitly by the user, not automatically alongside every change.
