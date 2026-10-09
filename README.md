# DevTools

Load Axolotl-PM development plugins from folders, share development virions once, and build standalone production PHARs.

DevTools supports [Axolotl-PM](https://github.com/axolotl-pm/PocketMine-MP). Use it on a development server or through the CLI and GitHub Actions.

[![CI](https://github.com/NhanAZ/DevTools/actions/workflows/ci.yml/badge.svg)](https://github.com/NhanAZ/DevTools/actions/workflows/ci.yml)
[![Latest release](https://img.shields.io/github/v/release/NhanAZ/DevTools)](https://github.com/NhanAZ/DevTools/releases/latest)
[![MIT license](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)

## Quick start

1. Download `DevTools-1.0.0.phar` from the [latest release](https://github.com/NhanAZ/DevTools/releases/latest). Do not download "Source code" unless you are developing DevTools.
2. Stop your Axolotl-PM server, copy the PHAR to `plugins/DevTools.phar`, and start the server again.
3. Confirm the console says that folder loading, shared virions, and PHAR building are ready. Run `/devtools status` as an operator.
4. Put a development plugin folder containing `plugin.yml` and `src/` directly inside `plugins/`.
5. Run `/devtools doctor YourPlugin`. When ready for production, run `/devtools build YourPlugin` and take the result from `build/`.

Install DevTools itself as a PHAR. Its folder loader must be running before the server can load source plugins.

## Build a plugin on GitHub

For a normal plugin, `plugin.yml` and `src/` are enough. Copy the complete workflow from the [GitHub Actions guide](docs/github-actions.md#short-build-workflow) to `.github/workflows/build.yml`, set its builder revision as documented, and push the repository. Download the PHAR from the workflow run's **Artifacts** section. This path needs no server source checkout or project-local Composer installation.

Add a virion when the plugin needs a shared development library.

- Put the local package in server-root `virions/<VirionName>/`.
- Declare it in the plugin's `devtools.yml` with a version constraint.
- DevTools loads the shared source during development and shades it into the production PHAR.

If the plugin has no virion, omit `devtools.yml` and `virions/`. See [folder plugins](docs/plugin-directories.md) for the ordinary layout and [shared virions](docs/shared-virions.md) when a shared package is actually needed.

## What it does

- Loads folder plugins through the server's normal plugin manager, so duplicate names, API compatibility, dependencies, cycles, load order, and enable failures remain under server handling.
- Loads one compatible copy of each local development virion from `virions/` on the shared thread-safe class loader.
- Selects the highest semantic virion version satisfying every declared constraint and reports skipped, missing, duplicate, or conflicting packages.
- Builds a selected plugin into a standalone SHA-256 PHAR by AST-shading its declared virions into a private namespace.
- Optionally runs PHPStan at level `0` through `10` or `max` against an explicitly selected server source before building.
- Safely extracts a PHAR through complete preflight validation and transactional replacement.

It does not download plugin or virion source during server startup or normal build resolution, replace Composer, provide hot reload, or act as a marketplace or package registry.

## Requirements

- PHP `8.1` or newer, 64-bit, with the extensions required by the server
- Axolotl-PM with the API declared by `plugin.yml`
- `phar.readonly=0` only when building PHARs

See [runtime validation](docs/runtime-validation.md) for the tested server and PHP versions. DevTools is an independent community project.

## Minimal layout

All paths below are relative to the server directory.

| Path | Contents |
| --- | --- |
| `plugins/DevTools.phar` | Installed DevTools plugin |
| `plugins/MyPlugin/plugin.yml` | Plugin manifest |
| `plugins/MyPlugin/src/` | Plugin source |
| `plugins/MyPlugin/resources/` | Optional plugin resources |
| `plugins/MyPlugin/devtools.yml` | Optional build and virion declarations |
| `virions/ExampleVirion/` | Local library with `virion.yml` and `src/` |
| `build/` | Generated PHARs |

Declare a shared virion to include it in the production build.

```yaml
# plugins/MyPlugin/devtools.yml
virions:
  - name: ExampleVirion
    version: ^1.0.0
```

For any compatible version, use a plain name.

```yaml
virions:
  - ExampleVirion
```

The [HelloShared plugin and SharedGreeting library](examples/README.md) demonstrate folder loading, version declarations and standalone building. Both examples are maintained in this repository.

## Build on every commit

Plugin repositories can use the [reusable workflow](docs/github-actions.md#short-build-workflow) for a normal build, or `NhanAZ/DevTools@v1.0.0` as a step-level GitHub Action when the workflow needs explicit setup or PHPStan server checkouts. The tested workflow in `examples/.github/workflows/build.yml` builds a standalone PHAR on every push and pull request and uploads it to the workflow run's **Artifacts** section. PHPStan remains off unless the workflow explicitly selects a level and server source. See [Build a PHAR on every commit](docs/github-actions.md) for both paths.

## Build with a coding agent

Use the [coding agent guide](docs/ai-agent.md) for CLI automation or GitHub workflow setup. An [`AGENTS.md` template](examples/agent/AGENTS.md) is included in the examples ZIP.

## Commands

All commands require `devtools.command`, which operators receive by default.

```text
/devtools status
/devtools doctor [plugin]
/devtools virions
/devtools build <plugin> [--overwrite]
/devtools extract <phar> [--overwrite]
```

Migration aliases `/makeplugin` and `/extractplugin` are also available.

## Documentation

Most users only need this README, the installation guide, and troubleshooting. The other guides are reference material for the workflow you choose.

### Start here

- [Installation, update, rollback, and uninstall](docs/installation.md)
- [Folder plugin behavior](docs/plugin-directories.md)
- [Building and validating PHARs](docs/building.md)
- [Troubleshooting](docs/troubleshooting.md)

### Choose an advanced workflow

- [CLI commands and versioned JSON contract](docs/cli.md)
- [Local and locked Composer dependency preparation](docs/dependencies.md)
- [Clean Axolotl-PM runtime verification](docs/runtime-validation.md)
- [Configuration reference](docs/configuration.md)
- [Shared virions and version resolution](docs/shared-virions.md)
- [Building a PHAR on every commit with GitHub Actions](docs/github-actions.md)
- [Building a PHAR with a coding agent](docs/ai-agent.md)
- [FAQ](docs/faq.md)
- [Migration guide](docs/migration.md)

### Maintain DevTools

- [Dependency ownership and packaging policy](docs/dependency-policy.md)
- [Developer workflow](docs/development.md)
- [Architecture](ARCHITECTURE.md)
- [Security policy](SECURITY.md)

## Production guidance

Use folder plugins and shared virions only for development. Editing source directly on a production server makes deployments non-reproducible and can leave partially changed code after a restart. Production servers should receive only tested PHAR artifacts from `/devtools build` or a trusted release.

## License and provenance

DevTools is MIT licensed. The bundled `nikic/php-parser` uses BSD-3-Clause. See [third-party notices](THIRD_PARTY_NOTICES.md) for its license and provenance.
