# DevTools 1.0.4

DevTools loads folder plugins and shared development virions on Axolotl-PM and builds standalone plugin PHARs.

## Changes

- Prepare a locked Composer virion when its single PSR-4 or namespace-based PSR-0 source directory is written as a one-element array.
- Prepare a package with one classmap root when every PHP file lies inside its declared namespace tree and its classes pass DevTools' existing namespace and filename checks.
- Preserve lockfile, installed-package metadata, source hash, symlink and dependency validation for these packages.

Root plugin classmaps and multiple package roots remain unsupported. DevTools also continues to reject dynamic class references it cannot prove safe to shade. A package passing preparation is not proof that its plugin will build or run.

## Install

Download `DevTools-1.0.4.phar` and verify its SHA-256 with `SHA256SUMS.txt`. Stop the server, place the file at `plugins/DevTools.phar`, start the server, and run `/devtools status`.

The examples ZIP contains the HelloShared plugin and SharedGreeting library. Follow the [example guide](https://github.com/NhanAZ/DevTools/blob/v1.0.4/examples/README.md) to load source and build a standalone PHAR.

## Compatibility and verification

PHP 8.1 is the source and build baseline. The release candidate gate runs PHPStan max, syntax, style, unit tests, documentation checks and artifact validation on the selected commit. The [runtime validation guide](https://github.com/NhanAZ/DevTools/blob/v1.0.4/docs/runtime-validation.md) describes the pinned Axolotl-PM test and its limits.

The 1.0.3 release remains available for rollback. A successful build verifies packaging. It does not prove a server boot or gameplay behavior. Runtime evidence for this release is recorded separately from candidate metadata.

## Release assets

- `DevTools-1.0.4.phar` contains the installable plugin.
- `DevTools-examples-1.0.4.zip` contains examples, a build workflow and a coding agent template.
- `RELEASE_NOTES.md` describes this release.
- `candidate.json` identifies the source commit, build run and asset hashes.
- `SHA256SUMS.txt` lists checksums.

## Documentation

- [Installation](https://github.com/NhanAZ/DevTools/blob/v1.0.4/docs/installation.md)
- [CLI and JSON](https://github.com/NhanAZ/DevTools/blob/v1.0.4/docs/cli.md)
- [Dependency support](https://github.com/NhanAZ/DevTools/blob/v1.0.4/docs/dependencies.md)
- [GitHub workflows](https://github.com/NhanAZ/DevTools/blob/v1.0.4/docs/github-actions.md)
