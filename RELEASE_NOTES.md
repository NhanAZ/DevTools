# DevTools 1.0.8

DevTools loads folder plugins and shared development virions on Axolotl-PM and builds standalone plugin PHARs.

## Changes

- Prepare Composer Virion v3 packages from a `dev-` branch when the reviewed lockfile pins a full Git commit. The generated internal version contains that commit, and provenance retains the original Composer version and source reference.
- Require source and distribution commit references to agree when both are present. Exact commit matching prevents PHP version comparison from treating different hexadecimal revisions as equal.
- Allow `import-poggit-libraries: false` in `devtools.yml` when a project retains `.poggit.yml` for Poggit but selects its DevTools libraries from Composer. The default still imports matching Poggit libraries.
- Preserve the dynamic class shading checks and the guarded object-class behavior from 1.0.7.

Root plugin classmaps and multiple package roots remain unsupported. DevTools also continues to reject dynamic class references it cannot prove safe to shade. A package passing preparation is not proof that its plugin will build or run.

## Install

Download `DevTools-1.0.8.phar` and verify its SHA-256 with `SHA256SUMS.txt`. Stop the server, place the file at `plugins/DevTools.phar`, start the server, and run `/devtools status`.

The examples ZIP contains the HelloShared plugin and SharedGreeting library. Follow the [example guide](https://github.com/NhanAZ/DevTools/blob/v1.0.8/examples/README.md) to load source and build a standalone PHAR.

## Compatibility and verification

PHP 8.1 is the source and build baseline. The release candidate gate runs PHPStan max, syntax, style, unit tests, documentation checks and artifact validation on the selected commit. The [runtime validation guide](https://github.com/NhanAZ/DevTools/blob/v1.0.8/docs/runtime-validation.md) describes the pinned Axolotl-PM test and its limits.

The 1.0.7 release remains available for rollback. A successful dependency preparation or build does not prove a server boot or gameplay behavior. Runtime evidence for this release is recorded separately from candidate metadata.

## Release assets

- `DevTools-1.0.8.phar` contains the installable plugin.
- `DevTools-examples-1.0.8.zip` contains examples, a build workflow and a coding agent template.
- `RELEASE_NOTES.md` describes this release.
- `candidate.json` identifies the source commit, build run and asset hashes.
- `SHA256SUMS.txt` lists checksums.

## Documentation

- [Installation](https://github.com/NhanAZ/DevTools/blob/v1.0.8/docs/installation.md)
- [CLI and JSON](https://github.com/NhanAZ/DevTools/blob/v1.0.8/docs/cli.md)
- [Dependency support](https://github.com/NhanAZ/DevTools/blob/v1.0.8/docs/dependencies.md)
- [GitHub workflows](https://github.com/NhanAZ/DevTools/blob/v1.0.8/docs/github-actions.md)
