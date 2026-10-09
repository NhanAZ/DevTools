# DevTools 1.0.0

DevTools loads folder plugins and shared development virions on Axolotl-PM, then builds standalone plugin PHARs for distribution.

## Install

Download `DevTools-1.0.0.phar` and verify its SHA-256 with `SHA256SUMS.txt`. Stop the server, place the file at `plugins/DevTools.phar`, start the server, and run `/devtools status`.

The examples ZIP contains the HelloShared plugin and SharedGreeting library. Follow the [example guide](https://github.com/NhanAZ/DevTools/blob/v1.0.0/examples/README.md) to load source, use a virion and build a standalone PHAR.

## Features

- Folder loading through Axolotl-PM's plugin manager, with project diagnostics.
- Local folder and PHAR virions, compatible version selection, transitive dependencies, collision checks and async worker class loading.
- Private AST shading that preserves source, resources and licenses. Failed builds protect existing output.
- CLI commands for `build`, `doctor`, `inspect`, `extract` and `prepare`, with JSON schema version 1, stable diagnostic codes and artifact hashes.
- Explicit preparation of supported Composer Virion v3 packages from an installed, locked dependency graph.
- Composite and reusable GitHub workflows with optional PHPStan, artifact metadata, tag releases and nightly prereleases.

CLI installation uses a source checkout with locked development dependencies. The server PHAR is not an executable CLI bundle.

## Compatibility and limits

PHP 8.1 is the source, CLI and build baseline. CI runs on PHP 8.1 and 8.2. Runtime smoke tests use dedicated plugin and library fixtures on Axolotl-PM with PHP 8.4.24 ZTS. See [runtime validation](https://github.com/NhanAZ/DevTools/blob/v1.0.0/docs/runtime-validation.md) for the pinned target and procedure.

Shared source plugins use one compatible library version. A built PHAR receives private library types and static state, so privately bundled objects must not be exchanged through cross-plugin APIs. Intentional external plugin dependencies continue to use Axolotl-PM's dependency mechanism.

Composer support is limited to the documented virion metadata, autoload, platform and resource layouts. Unsupported dynamic class references and package structures fail explicitly. Successful packaging does not establish server boot or gameplay behavior.

## Release assets

- `DevTools-1.0.0.phar` contains the installable plugin.
- `DevTools-examples-1.0.0.zip` contains dedicated examples, a build workflow and a coding agent template.
- `RELEASE_NOTES.md` describes this release.
- `candidate.json` identifies the source commit, build run and asset hashes.
- `SHA256SUMS.txt` lists checksums.

## Documentation

- [Installation](https://github.com/NhanAZ/DevTools/blob/v1.0.0/docs/installation.md)
- [CLI and JSON](https://github.com/NhanAZ/DevTools/blob/v1.0.0/docs/cli.md)
- [Dependency support](https://github.com/NhanAZ/DevTools/blob/v1.0.0/docs/dependencies.md)
- [GitHub workflows](https://github.com/NhanAZ/DevTools/blob/v1.0.0/docs/github-actions.md)
