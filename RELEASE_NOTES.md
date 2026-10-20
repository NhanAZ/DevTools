# DevTools 1.0.17

DevTools loads folder plugins and shared development virions on Axolotl-PM and builds standalone plugin PHARs.

## Changes

- Reject linked source paths, including Windows directory junctions, and avoid following them during cleanup.
- Protect existing build outputs, detect collisions with bundled virion resources and metadata, and retain supported license and notice files.
- Fix namespace preparation for numeric-leading package names and source inspection of attributed anonymous classes.
- Close shading guard gaps involving imported aliases, named arguments and argument unpacking.
- Correct virion version ordering for prereleases, build metadata and large version components.
- Improve manifest and path diagnostics, support qualified server command aliases, and remove duplicate validation and unused imports.

## Install

Download `DevTools-1.0.17.phar` and verify its SHA-256 with `SHA256SUMS.txt`. Stop the server, place the file at `plugins/DevTools.phar`, start the server, and run `/devtools status`.

The examples ZIP contains the HelloShared plugin and SharedGreeting library. Follow the [example guide](https://github.com/NhanAZ/DevTools/blob/v1.0.17/examples/README.md) to load source and build a standalone PHAR.

## Compatibility and verification

PHP 8.4 remains the source, CLI and build minimum. The release candidate gate runs PHPStan max, syntax, style, unit tests, documentation checks and artifact validation. The full CI quality matrix runs on PHP 8.4 and PHP 8.5. The [runtime validation guide](https://github.com/NhanAZ/DevTools/blob/v1.0.17/docs/runtime-validation.md) describes the server checks on Windows x64 with PHP 8.4.24 ZTS.

DevTools 1.0.16 remains available for rollback. Runtime evidence is recorded separately from candidate metadata; CI on PHP 8.5 does not claim an Axolotl-PM server test on PHP 8.5.

## Release assets

- `DevTools-1.0.17.phar` contains the installable plugin.
- `DevTools-examples-1.0.17.zip` contains examples, a build workflow and a coding agent template.
- `RELEASE_NOTES.md` describes this release.
- `candidate.json` identifies the source commit, build run and asset hashes.
- `SHA256SUMS.txt` lists checksums.

## Documentation

- [Installation](https://github.com/NhanAZ/DevTools/blob/v1.0.17/docs/installation.md)
- [CLI and JSON](https://github.com/NhanAZ/DevTools/blob/v1.0.17/docs/cli.md)
- [Dependency support](https://github.com/NhanAZ/DevTools/blob/v1.0.17/docs/dependencies.md)
- [GitHub workflows](https://github.com/NhanAZ/DevTools/blob/v1.0.17/docs/github-actions.md)
