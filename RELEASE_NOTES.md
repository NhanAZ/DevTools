# DevTools 1.0.7

DevTools loads folder plugins and shared development virions on Axolotl-PM and builds standalone plugin PHARs.

## Changes

- Require an unannotated `return $object::class` to follow a top-level `instanceof ... || throw ...` guard immediately in the same function.
- Reject guards inside optional branches and guards invalidated by an intervening assignment. Version 1.0.6 accepted those shapes even though they did not prove the runtime value was an object.
- Preserve the explicit annotation path and the Composer virion preparation behavior introduced in earlier releases.
- Read the Axolotl-PM smoke target from the committed Composer lock and update the runtime reproduction guide to the current 5.49.1 source pin.

Root plugin classmaps and multiple package roots remain unsupported. DevTools also continues to reject dynamic class references it cannot prove safe to shade. A package passing preparation is not proof that its plugin will build or run.

## Install

Download `DevTools-1.0.7.phar` and verify its SHA-256 with `SHA256SUMS.txt`. Stop the server, place the file at `plugins/DevTools.phar`, start the server, and run `/devtools status`.

The examples ZIP contains the HelloShared plugin and SharedGreeting library. Follow the [example guide](https://github.com/NhanAZ/DevTools/blob/v1.0.7/examples/README.md) to load source and build a standalone PHAR.

## Compatibility and verification

PHP 8.1 is the source and build baseline. The release candidate gate runs PHPStan max, syntax, style, unit tests, documentation checks and artifact validation on the selected commit. The [runtime validation guide](https://github.com/NhanAZ/DevTools/blob/v1.0.7/docs/runtime-validation.md) describes the pinned Axolotl-PM test and its limits.

The 1.0.5 release remains available for rollback. Version 1.0.6 remains available as a historical release, but its unannotated guard check is broader than this release's proven pattern. A successful build verifies packaging. It does not prove a server boot or gameplay behavior. Runtime evidence for this release is recorded separately from candidate metadata.

## Release assets

- `DevTools-1.0.7.phar` contains the installable plugin.
- `DevTools-examples-1.0.7.zip` contains examples, a build workflow and a coding agent template.
- `RELEASE_NOTES.md` describes this release.
- `candidate.json` identifies the source commit, build run and asset hashes.
- `SHA256SUMS.txt` lists checksums.

## Documentation

- [Installation](https://github.com/NhanAZ/DevTools/blob/v1.0.7/docs/installation.md)
- [CLI and JSON](https://github.com/NhanAZ/DevTools/blob/v1.0.7/docs/cli.md)
- [Dependency support](https://github.com/NhanAZ/DevTools/blob/v1.0.7/docs/dependencies.md)
- [GitHub workflows](https://github.com/NhanAZ/DevTools/blob/v1.0.7/docs/github-actions.md)
