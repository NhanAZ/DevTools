# DevTools 1.0.13

DevTools loads folder plugins and shared development virions on Axolotl-PM and builds standalone plugin PHARs.

## Changes

- Allow object-class diagnostics inside the same short-circuit `instanceof ... || throw` guard when the inspected variable is the guarded operand. This supports dependency diagnostics such as InvMenu's guarded `$graphic::class` message without permitting dynamic class loading or static dispatch.
- Keep dynamic class factories, static calls, computed targets and mismatched guarded variables rejected.

## Previous changes

- Permit a variable operand in `instanceof`, whose runtime type check does not autoload a class; computed dynamic targets remain rejected.
- Rewrite only the exact `Fiber::getCurrent()`, non-null guard and `suspend()` pattern to the native `\Fiber::suspend()` call.
- Rewrite `ReflectionClass($className)` to reflect the bound object only when the same non-static closure immediately assigned the variable from `get_called_class()`. Permit `ReflectionClass($this)` when a non-static method or closure has a bound object.
- Preserve fail-closed checks for other dynamic static calls, arbitrary reflection class strings and computed `instanceof` targets.

These narrow rules clear the relevant VAPM Fiber, reflection and `instanceof` patterns in libBedrock's prepared dependency sources. Its build still stops at a VAPM autoloader path stored as a string that no longer matches after namespace shading. No complete libBedrock PHAR or release eligibility is claimed.

## Install

Download `DevTools-1.0.13.phar` and verify its SHA-256 with `SHA256SUMS.txt`. Stop the server, place the file at `plugins/DevTools.phar`, start the server, and run `/devtools status`.

The examples ZIP contains the HelloShared plugin and SharedGreeting library. Follow the [example guide](https://github.com/NhanAZ/DevTools/blob/v1.0.13/examples/README.md) to load source and build a standalone PHAR.

## Compatibility and verification

PHP 8.1 is the source and build baseline. The release candidate gate runs PHPStan max, syntax, style, unit tests, documentation checks and artifact validation on the selected commit. The [runtime validation guide](https://github.com/NhanAZ/DevTools/blob/v1.0.13/docs/runtime-validation.md) describes the pinned Axolotl-PM test and its limits.

DevTools 1.0.12 remains available for rollback. A successful dependency preparation or build does not prove a server boot or gameplay behavior. Runtime evidence for this release is recorded separately from candidate metadata.

## Release assets

- `DevTools-1.0.13.phar` contains the installable plugin.
- `DevTools-examples-1.0.13.zip` contains examples, a build workflow and a coding agent template.
- `RELEASE_NOTES.md` describes this release.
- `candidate.json` identifies the source commit, build run and asset hashes.
- `SHA256SUMS.txt` lists checksums.

## Documentation

- [Installation](https://github.com/NhanAZ/DevTools/blob/v1.0.13/docs/installation.md)
- [CLI and JSON](https://github.com/NhanAZ/DevTools/blob/v1.0.13/docs/cli.md)
- [Dependency support](https://github.com/NhanAZ/DevTools/blob/v1.0.13/docs/dependencies.md)
- [GitHub workflows](https://github.com/NhanAZ/DevTools/blob/v1.0.13/docs/github-actions.md)
