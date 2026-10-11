# DevTools 1.0.9

DevTools loads folder plugins and shared development virions on Axolotl-PM and builds standalone plugin PHARs.

## Changes

- Reject an annotated dynamic class factory unless two top-level throwing guards directly precede `return new $variable()`: one checks `class_exists()` and the other checks `is_subclass_of()` against a static class.
- Reject checks hidden in optional branches and a class name reassigned after validation. Earlier versions could count these checks even when they did not guard the object creation.
- Preserve the Composer virion preparation and import settings from 1.0.8. The change tightens shading validation; it does not make previously rejected dynamic packet references safe.

Root plugin classmaps and multiple package roots remain unsupported. DevTools also continues to reject dynamic class references it cannot prove safe to shade. A package passing preparation is not proof that its plugin will build or run.

## Install

Download `DevTools-1.0.9.phar` and verify its SHA-256 with `SHA256SUMS.txt`. Stop the server, place the file at `plugins/DevTools.phar`, start the server, and run `/devtools status`.

The examples ZIP contains the HelloShared plugin and SharedGreeting library. Follow the [example guide](https://github.com/NhanAZ/DevTools/blob/v1.0.9/examples/README.md) to load source and build a standalone PHAR.

## Compatibility and verification

PHP 8.1 is the source and build baseline. The release candidate gate runs PHPStan max, syntax, style, unit tests, documentation checks and artifact validation on the selected commit. The [runtime validation guide](https://github.com/NhanAZ/DevTools/blob/v1.0.8/docs/runtime-validation.md) describes the pinned Axolotl-PM test and its limits.

The 1.0.8 release remains available for rollback. A successful dependency preparation or build does not prove a server boot or gameplay behavior. Runtime evidence for this release is recorded separately from candidate metadata.

## Release assets

- `DevTools-1.0.9.phar` contains the installable plugin.
- `DevTools-examples-1.0.9.zip` contains examples, a build workflow and a coding agent template.
- `RELEASE_NOTES.md` describes this release.
- `candidate.json` identifies the source commit, build run and asset hashes.
- `SHA256SUMS.txt` lists checksums.

## Documentation

- [Installation](https://github.com/NhanAZ/DevTools/blob/v1.0.9/docs/installation.md)
- [CLI and JSON](https://github.com/NhanAZ/DevTools/blob/v1.0.9/docs/cli.md)
- [Dependency support](https://github.com/NhanAZ/DevTools/blob/v1.0.9/docs/dependencies.md)
- [GitHub workflows](https://github.com/NhanAZ/DevTools/blob/v1.0.9/docs/github-actions.md)
