# DevTools 1.0.16

DevTools loads folder plugins and shared development virions on Axolotl-PM and builds standalone plugin PHARs.

## Changes

- Require PHP 8.4 or newer for the source checkout, CLI, build and plugin runtime.
- Run the complete CI quality gate on PHP 8.4 and PHP 8.5.
- Default reusable GitHub build and publication workflows to PHP 8.4.
- Generate prepared Composer virion manifests with DevTools' PHP 8.4 baseline.

## Install

Download `DevTools-1.0.16.phar` and verify its SHA-256 with `SHA256SUMS.txt`. Stop the server, place the file at `plugins/DevTools.phar`, start the server, and run `/devtools status`.

The examples ZIP contains the HelloShared plugin and SharedGreeting library. Follow the [example guide](https://github.com/NhanAZ/DevTools/blob/v1.0.16/examples/README.md) to load source and build a standalone PHAR.

## Compatibility and verification

PHP 8.4 is the source, CLI and build minimum. The release candidate gate runs PHPStan max, syntax, style, unit tests, documentation checks and artifact validation. The full CI quality matrix runs on PHP 8.4 and PHP 8.5. The [runtime validation guide](https://github.com/NhanAZ/DevTools/blob/v1.0.16/docs/runtime-validation.md) records the executed server test on Windows x64 with PHP 8.4.24 ZTS. CI on PHP 8.5 does not claim an Axolotl-PM server boot on PHP 8.5.

DevTools 1.0.15 remains available for rollback. A successful dependency preparation or build does not prove a server boot or gameplay behavior. Runtime evidence is recorded separately from candidate metadata.

## Release assets

- `DevTools-1.0.16.phar` contains the installable plugin.
- `DevTools-examples-1.0.16.zip` contains examples, a build workflow and a coding agent template.
- `RELEASE_NOTES.md` describes this release.
- `candidate.json` identifies the source commit, build run and asset hashes.
- `SHA256SUMS.txt` lists checksums.

## Documentation

- [Installation](https://github.com/NhanAZ/DevTools/blob/v1.0.16/docs/installation.md)
- [CLI and JSON](https://github.com/NhanAZ/DevTools/blob/v1.0.16/docs/cli.md)
- [Dependency support](https://github.com/NhanAZ/DevTools/blob/v1.0.16/docs/dependencies.md)
- [GitHub workflows](https://github.com/NhanAZ/DevTools/blob/v1.0.16/docs/github-actions.md)
