# DevTools 1.0.2

DevTools loads folder plugins and shared development virions on Axolotl-PM and builds standalone plugin PHARs.

## Changes

- The reusable build workflow accepts a full Axolotl-PM source commit SHA. It checks out that revision and passes its source to PHPStan analysis.
- The reusable workflow inspects the completed PHAR before uploading it as an artifact.
- Hosted CI exercises this path with a fixture plugin at PHPStan maximum level.
- The step-level action and existing reusable workflow inputs remain available.

The new input lets a plugin keep its build workflow small while pinning the builder and server source independently. It does not automatically change plugin source, compatibility claims or release versions.

## Install

Download `DevTools-1.0.2.phar` and verify its SHA-256 with `SHA256SUMS.txt`. Stop the server, place the file at `plugins/DevTools.phar`, start the server, and run `/devtools status`.

The examples ZIP contains the HelloShared plugin and SharedGreeting library. Follow the [example guide](https://github.com/NhanAZ/DevTools/blob/v1.0.2/examples/README.md) to load source and build a standalone PHAR.

## Compatibility and verification

PHP 8.1 is the source and build baseline. The release candidate gate runs PHPStan max, syntax, style, unit tests, documentation checks and artifact validation on the selected commit. The [runtime validation guide](https://github.com/NhanAZ/DevTools/blob/v1.0.2/docs/runtime-validation.md) describes the pinned Axolotl-PM test and its limits.

The previous 1.0.1 release remains available for rollback. This update changes workflow preparation and inspection, not the PHAR builder's output format or server API. A successful build verifies packaging. It does not prove a server boot or gameplay behavior.

## Release assets

- `DevTools-1.0.2.phar` contains the installable plugin.
- `DevTools-examples-1.0.2.zip` contains examples, a build workflow and a coding agent template.
- `RELEASE_NOTES.md` describes this release.
- `candidate.json` identifies the source commit, build run and asset hashes.
- `SHA256SUMS.txt` lists checksums.

## Documentation

- [Installation](https://github.com/NhanAZ/DevTools/blob/v1.0.2/docs/installation.md)
- [CLI and JSON](https://github.com/NhanAZ/DevTools/blob/v1.0.2/docs/cli.md)
- [Dependency support](https://github.com/NhanAZ/DevTools/blob/v1.0.2/docs/dependencies.md)
- [GitHub workflows](https://github.com/NhanAZ/DevTools/blob/v1.0.2/docs/github-actions.md)
