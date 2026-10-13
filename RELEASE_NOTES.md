# DevTools 1.0.11

DevTools loads folder plugins and shared development virions on Axolotl-PM and builds standalone plugin PHARs.

## Changes

- Allow a variable target type in `is_a($objectOrClass, $class)` when the optional `allow_string` argument is omitted. Keep the fail-closed guard when callers explicitly enable string input, and keep the other dynamic-class checks unchanged.
- Add regression coverage for the supported comparison and the rejected autoload-enabled form.

The change supports type matching against caller-provided catch maps, including the pinned AwaitGenerator implementation in libBedrock. In a local build of libBedrock's existing prepared dependencies, this removes the AwaitGenerator `is_a()` diagnostic; the build then reaches a separate dynamic Fiber static call in VAPM and still fails. No complete libBedrock PHAR or release eligibility is claimed. DevTools does not relax dynamic construction, reflection, `class_exists()`, `is_subclass_of()`, or dynamic static calls.

## Install

Download `DevTools-1.0.11.phar` and verify its SHA-256 with `SHA256SUMS.txt`. Stop the server, place the file at `plugins/DevTools.phar`, start the server, and run `/devtools status`.

The examples ZIP contains the HelloShared plugin and SharedGreeting library. Follow the [example guide](https://github.com/NhanAZ/DevTools/blob/v1.0.11/examples/README.md) to load source and build a standalone PHAR.

## Compatibility and verification

PHP 8.1 is the source and build baseline. The release candidate gate runs PHPStan max, syntax, style, unit tests, documentation checks and artifact validation on the selected commit. The [runtime validation guide](https://github.com/NhanAZ/DevTools/blob/v1.0.11/docs/runtime-validation.md) describes the pinned Axolotl-PM test and its limits.

The 1.0.10 release remains available for rollback. A successful dependency preparation or build does not prove a server boot or gameplay behavior. Runtime evidence for this release is recorded separately from candidate metadata.

## Release assets

- `DevTools-1.0.11.phar` contains the installable plugin.
- `DevTools-examples-1.0.11.zip` contains examples, a build workflow and a coding agent template.
- `RELEASE_NOTES.md` describes this release.
- `candidate.json` identifies the source commit, build run and asset hashes.
- `SHA256SUMS.txt` lists checksums.

## Documentation

- [Installation](https://github.com/NhanAZ/DevTools/blob/v1.0.11/docs/installation.md)
- [CLI and JSON](https://github.com/NhanAZ/DevTools/blob/v1.0.11/docs/cli.md)
- [Dependency support](https://github.com/NhanAZ/DevTools/blob/v1.0.11/docs/dependencies.md)
- [GitHub workflows](https://github.com/NhanAZ/DevTools/blob/v1.0.11/docs/github-actions.md)
