# DevTools 1.0.3

DevTools loads folder plugins and shared development virions on Axolotl-PM and builds standalone plugin PHARs.

## Changes

- Reusable build and release jobs obtain their tool revision from the commit containing the running workflow file.
- A single pinned workflow reference now selects both workflow logic and builder code. Dependabot can update that reference without leaving a second revision input behind.
- Existing callers may still pass `devtools-ref`; the workflow warns that this input is deprecated and ignores it.

The change affects reusable workflow revision selection. It does not alter plugin source, the PHAR builder's output format or Axolotl-PM server APIs.

## Install

Download `DevTools-1.0.3.phar` and verify its SHA-256 with `SHA256SUMS.txt`. Stop the server, place the file at `plugins/DevTools.phar`, start the server, and run `/devtools status`.

The examples ZIP contains the HelloShared plugin and SharedGreeting library. Follow the [example guide](https://github.com/NhanAZ/DevTools/blob/v1.0.3/examples/README.md) to load source and build a standalone PHAR.

## Compatibility and verification

PHP 8.1 is the source and build baseline. The release candidate gate runs PHPStan max, syntax, style, unit tests, documentation checks and artifact validation on the selected commit. The [runtime validation guide](https://github.com/NhanAZ/DevTools/blob/v1.0.3/docs/runtime-validation.md) describes the pinned Axolotl-PM test and its limits.

The 1.0.2 release remains available for rollback. A successful build verifies packaging. It does not prove a server boot or gameplay behavior. Runtime evidence for this release is recorded separately from candidate metadata.

## Release assets

- `DevTools-1.0.3.phar` contains the installable plugin.
- `DevTools-examples-1.0.3.zip` contains examples, a build workflow and a coding agent template.
- `RELEASE_NOTES.md` describes this release.
- `candidate.json` identifies the source commit, build run and asset hashes.
- `SHA256SUMS.txt` lists checksums.

## Documentation

- [Installation](https://github.com/NhanAZ/DevTools/blob/v1.0.3/docs/installation.md)
- [CLI and JSON](https://github.com/NhanAZ/DevTools/blob/v1.0.3/docs/cli.md)
- [Dependency support](https://github.com/NhanAZ/DevTools/blob/v1.0.3/docs/dependencies.md)
- [GitHub workflows](https://github.com/NhanAZ/DevTools/blob/v1.0.3/docs/github-actions.md)
