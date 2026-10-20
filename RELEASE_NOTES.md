# DevTools 1.0.18

DevTools loads folder plugins and shared development virions on Axolotl-PM and builds standalone plugin PHARs.

## Changes

- Verify compatibility with Axolotl-PM 5.50.0 at commit `fe5b3dee173b5e839783ab6fb7280005aa0a4d73`, resolving [issue #6](https://github.com/NhanAZ/DevTools/issues/6).
- Synchronize the development dependency, CI server source and compatibility monitor with that revision.
- Update the locked server dependencies to BedrockProtocol 63.0.1 and NetherNet 0.3.1.
- Keep runtime smoke servers on loopback RakNet and test that workflow pins match the lockfile.

## Install

Download `DevTools-1.0.18.phar` and verify its SHA-256 with `SHA256SUMS.txt`. Stop the server, place the file at `plugins/DevTools.phar`, start the server, and run `/devtools status`.

The examples ZIP contains the HelloShared plugin and SharedGreeting library. Follow the [example guide](https://github.com/NhanAZ/DevTools/blob/v1.0.18/examples/README.md) to load source and build a standalone PHAR.

## Compatibility and verification

PHP 8.4 remains the source, CLI and build minimum. Installing the source checkout's development dependencies now requires `ext-webrtc` through NetherNet; use a current Axolotl-PM PHP binary.

The release candidate gate runs PHPStan max, syntax, style, unit tests, documentation checks and artifact validation. The full CI quality matrix runs on PHP 8.4 and PHP 8.5. The [runtime validation guide](https://github.com/NhanAZ/DevTools/blob/v1.0.18/docs/runtime-validation.md) covers folder plugins, shared virions and standalone PHARs on Axolotl-PM 5.50.0 with Windows x64 and PHP 8.4.24 ZTS. These tests use RakNet and do not test NetherNet client connections or gameplay.

DevTools 1.0.17 remains available for rollback. Runtime evidence is recorded separately from candidate metadata; CI on PHP 8.5 does not claim an Axolotl-PM server test on PHP 8.5.

## Release assets

- `DevTools-1.0.18.phar` contains the installable plugin.
- `DevTools-examples-1.0.18.zip` contains examples, a build workflow and a coding agent template.
- `RELEASE_NOTES.md` describes this release.
- `candidate.json` identifies the source commit, build run and asset hashes.
- `SHA256SUMS.txt` lists checksums.

## Documentation

- [Installation](https://github.com/NhanAZ/DevTools/blob/v1.0.18/docs/installation.md)
- [CLI and JSON](https://github.com/NhanAZ/DevTools/blob/v1.0.18/docs/cli.md)
- [Dependency support](https://github.com/NhanAZ/DevTools/blob/v1.0.18/docs/dependencies.md)
- [GitHub workflows](https://github.com/NhanAZ/DevTools/blob/v1.0.18/docs/github-actions.md)
