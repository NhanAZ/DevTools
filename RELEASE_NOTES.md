# DevTools 1.0.10

DevTools loads folder plugins and shared development virions on Axolotl-PM and builds standalone plugin PHARs.

## Changes

- Prepare a locked Composer virion when one PHP file declares its correctly named class plus additional classes in the same namespace and directory. DevTools creates a small autoload shim for each additional class and records its source file in dependency provenance.
- Continue to reject misnamed standalone classes, duplicate class declarations and unproven dynamic class references during shading. The new preparation support does not authorize a package to build or run when another gate fails.

This release does not change the public plugin API or the behavior of existing builds without colocated classes. The libBedrock dependency graph can now pass Composer preparation with generated shims for VAPM, but its current full PHAR still fails the separate dynamic class shading check in SOFe AwaitGenerator. No libBedrock release is implied.

## Install

Download `DevTools-1.0.10.phar` and verify its SHA-256 with `SHA256SUMS.txt`. Stop the server, place the file at `plugins/DevTools.phar`, start the server, and run `/devtools status`.

The examples ZIP contains the HelloShared plugin and SharedGreeting library. Follow the [example guide](https://github.com/NhanAZ/DevTools/blob/v1.0.10/examples/README.md) to load source and build a standalone PHAR.

## Compatibility and verification

PHP 8.1 is the source and build baseline. The release candidate gate runs PHPStan max, syntax, style, unit tests, documentation checks and artifact validation on the selected commit. The [runtime validation guide](https://github.com/NhanAZ/DevTools/blob/v1.0.10/docs/runtime-validation.md) describes the pinned Axolotl-PM test and its limits.

The 1.0.9 release remains available for rollback. A successful dependency preparation or build does not prove a server boot or gameplay behavior. Runtime evidence for this release is recorded separately from candidate metadata.

## Release assets

- `DevTools-1.0.10.phar` contains the installable plugin.
- `DevTools-examples-1.0.10.zip` contains examples, a build workflow and a coding agent template.
- `RELEASE_NOTES.md` describes this release.
- `candidate.json` identifies the source commit, build run and asset hashes.
- `SHA256SUMS.txt` lists checksums.

## Documentation

- [Installation](https://github.com/NhanAZ/DevTools/blob/v1.0.10/docs/installation.md)
- [CLI and JSON](https://github.com/NhanAZ/DevTools/blob/v1.0.10/docs/cli.md)
- [Dependency support](https://github.com/NhanAZ/DevTools/blob/v1.0.10/docs/dependencies.md)
- [GitHub workflows](https://github.com/NhanAZ/DevTools/blob/v1.0.10/docs/github-actions.md)
