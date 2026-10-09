# DevTools 1.0.1

DevTools loads folder plugins and shared development virions on Axolotl-PM and builds standalone plugin PHARs.

## Changes

- Composer preparation now recognizes `pocketmine/pocketmine-mp` as the server platform when the locked Axolotl-PM package explicitly replaces it.
- Composer virions may require 64-bit PHP. DevTools checks the running PHP integer size during preparation and shared loading.
- `composer-runtime-api` remains an installation requirement checked by Composer. It is not included in a generated virion manifest.

Unrelated virtual packages and replacements still fail when their implementation is absent. Virion source must satisfy the documented namespace and autoload layout. This release does not loosen those checks.

## Install

Download `DevTools-1.0.1.phar` and verify its SHA-256 with `SHA256SUMS.txt`. Stop the server, place the file at `plugins/DevTools.phar`, start the server, and run `/devtools status`.

The examples ZIP contains the HelloShared plugin and SharedGreeting library. Follow the [example guide](https://github.com/NhanAZ/DevTools/blob/v1.0.1/examples/README.md) to load source and build a standalone PHAR.

## Compatibility and verification

PHP 8.1 is the source and build baseline. The release gate runs PHPStan max, syntax, style, unit tests, documentation checks and artifact validation on the selected candidate commit. The [runtime validation guide](https://github.com/NhanAZ/DevTools/blob/v1.0.1/docs/runtime-validation.md) describes the pinned Axolotl-PM test and its limits.

The `1.0.0` release PHAR also passed an additional isolated folder and clean PHAR smoke test on Axolotl-PM `5.49.1` with PHP 8.4.24 ZTS. Those tests did not cover every virion or gameplay path. See the release candidate workflow and attached artifact for the exact `1.0.1` source and bytes.

Composer dependency installation remains an explicit step. DevTools does not fetch packages during server startup or an ordinary build. A successful build verifies packaging and does not prove a server boot or gameplay behavior.

## Release assets

- `DevTools-1.0.1.phar` contains the installable plugin.
- `DevTools-examples-1.0.1.zip` contains examples, a build workflow and a coding agent template.
- `RELEASE_NOTES.md` describes this release.
- `candidate.json` identifies the source commit, build run and asset hashes.
- `SHA256SUMS.txt` lists checksums.

## Documentation

- [Installation](https://github.com/NhanAZ/DevTools/blob/v1.0.1/docs/installation.md)
- [CLI and JSON](https://github.com/NhanAZ/DevTools/blob/v1.0.1/docs/cli.md)
- [Dependency support](https://github.com/NhanAZ/DevTools/blob/v1.0.1/docs/dependencies.md)
- [GitHub workflows](https://github.com/NhanAZ/DevTools/blob/v1.0.1/docs/github-actions.md)
