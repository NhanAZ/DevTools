# Build and release Axolotl-PM plugins on GitHub

The composite action calls `bin/devtools.php build --json`, using the same builder as the CLI and server command. It verifies the reported PHAR SHA-256 and exposes the actual path from that result. PHPStan and Composer dependency preparation are opt-in. A successful build verifies packaging. It does not prove an Axolotl-PM runtime boot.

These interfaces are available in `v1.0.0`. The composite action can use `NhanAZ/DevTools@v1.0.0` directly. Reusable workflows require an explicit full 40-character lowercase **builder commit** in `devtools-ref`. A tag is accepted in the outer `uses:` reference but not in that input. Resolve the release tag before copying the reusable examples.

```sh
git ls-remote https://github.com/NhanAZ/DevTools.git 'refs/tags/v1.0.0^{}'
```

Copy the first column (the annotated tag's commit) into every `REVIEWED_DEVTOOLS_COMMIT_SHA` below. The workflow reference uses `v1.0.0`, which must resolve to that same commit. This avoids confusing the plugin caller's `github.sha` with the builder revision. No release is performed merely by installing DevTools.

## Short build workflow

After replacing the revision placeholder, create `.github/workflows/build.yml` in a plugin repository.

```yaml
name: Build PHAR
on:
  push:
  pull_request:
  workflow_dispatch:
permissions:
  contents: read
jobs:
  build:
    uses: NhanAZ/DevTools/.github/workflows/build-plugin.yml@v1.0.0
    with:
      devtools-ref: REVIEWED_DEVTOOLS_COMMIT_SHA
      php-version: '8.1'
      retention-days: 14
```

The reusable workflow checks out the plugin, sets up Axolotl-PM PHP using setup-helper commit `b8c3f9add4f2ad4a5e2624a1112aba899ee8db0e`, checks out the selected DevTools revision, builds, and uploads one artifact containing the PHAR plus `build-metadata.json`. Find that artifact under **Actions -> workflow run -> Artifacts**. The metadata contains the tool version, plugin version, resolved dependency information, checks, and PHAR hash from the versioned [CLI contract](cli.md).

Default `project: .` expects `plugin.yml` and `src/` at the repository root. A plugin without virions needs no Composer manifest or additional DevTools configuration. Local virions remain usable through `virions: virions`. The included `examples/.github/workflows/build.yml` uses the v1.0.0 composite action for `HelloShared` and `SharedGreeting`.

## Explicit Composer dependency preparation

If the project uses supported Composer Virion v3 packages, commit its `composer.lock` and enable the preparation input.

```yaml
    with:
      devtools-ref: REVIEWED_DEVTOOLS_COMMIT_SHA
      prepare-dependencies: true
      virions: .devtools-virions
```

The action explicitly runs `composer install --no-dev --no-scripts --no-plugins`, then `devtools prepare`, before analysis and build. It fails when a Composer project has no lockfile. It does not run `composer update`, guess library repositories, or include arbitrary `vendor/` files. Composer installation is a network-capable preparation step. Ordinary CLI builds and server startup remain local. See [dependency preparation](dependencies.md) for the exact accepted package format and destination rules. Use a dedicated generated destination such as `.devtools-virions`, separate from manually maintained virions.

For private Composer sources, configure Composer authentication in your own workflow before the action. Do not put tokens into plugin manifests or DevTools configuration. Complex source checkouts and mixed preparation requirements should use the composite action below so the preparation is explicit.

## Step-level action

Use the composite action when your repository needs extra checkouts or checks. Set up a suitable PHP runtime with YAML and PHAR extensions, plus Composer, before invoking it. The reusable workflow's pinned Axolotl-PM setup procedure can be copied for that purpose.

```yaml
      - id: build
        uses: NhanAZ/DevTools@v1.0.0
        with:
          project: .
          virions: virions
          output: build

      - uses: actions/upload-artifact@v7.0.1
        with:
          name: ${{ steps.build.outputs.plugin-name }}-${{ github.sha }}
          path: ${{ steps.build.outputs.phar }}
          if-no-files-found: error
          retention-days: 14
```

The composite action installs its own locked tool dependencies with scripts and plugins disabled. It does not upload anything on its own. Its relative paths resolve from `GITHUB_WORKSPACE`. It passes absolute paths to the CLI. Direct CLI defaults resolve from the chosen project, as documented in [CLI usage](cli.md).

| Name | Kind | Default | Meaning |
| --- | --- | --- | --- |
| `project` | input | `.` | Plugin root. |
| `virions` | input | `virions` | Local prepared virion directory. |
| `output` | input | `build` | Output directory. |
| `overwrite` | input | `false` | Explicitly permit replacing output. |
| `prepare-dependencies` | input | `false` | Install locked runtime Composer packages safely, then prepare supported virions. |
| `phpstan` | input | `off` | `off`, `0` through `10`, or `max`. |
| `phpstan-server` | input | empty | Local Axolotl-PM source path, required with PHPStan. |
| `phpstan-paths` | input | empty | Newline-separated local dependency source paths for symbol discovery. |
| `phar` | output | - | Absolute path to the verified PHAR. |
| `plugin-name` | output | - | Name from the build result. |
| `plugin-version` | output | - | Version from the build result. |
| `sha256` | output | - | SHA-256 of the exact PHAR. |
| `metadata` | output | - | Absolute path to the CLI JSON build result. |

The reusable build workflow requires `devtools-ref` and adds `php-version`, `pm-version-major`, `artifact-name`, and `retention-days` inputs. It exports `plugin-name`, `plugin-version`, `sha256`, `artifact-name`, and immutable `artifact-id`. Local artifact paths are only meaningful inside the build job. Another job must download using `artifact-id`.

## Optional static analysis

Check out the Axolotl-PM source, then pass its local path to the composite action.

```yaml
      - uses: actions/checkout@v7.0.1
        with:
          repository: axolotl-pm/PocketMine-MP
          ref: b9a3b244993fb8a6df97241f3a3fbe44e2076f4c
          path: .devtools-server
          persist-credentials: false
      - id: build
        uses: NhanAZ/DevTools@v1.0.0
        with:
          phpstan: '4'
          phpstan-server: .devtools-server
```

The analysis helper installs that server's production dependencies with scripts and plugins disabled. It analyzes plugin `src/` and discovers symbols from Axolotl-PM, declared virions, and optional `phpstan-paths`. Level 4 is an example, not the default. Missing sources or analysis errors fail the action. This is static analysis, not a running server test. Newline-separated server paths remain accepted for existing callers, but only Axolotl-PM is officially supported.

## Tag release

Create `.github/workflows/release.yml` in the plugin repository.

```yaml
name: Release PHAR
on:
  push:
    tags: ['v*']
permissions:
  contents: write
  actions: read
jobs:
  release:
    uses: NhanAZ/DevTools/.github/workflows/release-plugin.yml@v1.0.0
    with:
      devtools-ref: REVIEWED_DEVTOOLS_COMMIT_SHA
      mode: tag
```

The reusable release workflow builds with read-only repository permissions. Its publish job uses the same required immutable DevTools SHA, downloads the immutable artifact ID, checks the PHAR bytes against both downloaded metadata and the SHA-256 passed directly from the build job, then publishes **those bytes without rebuilding**. The release assets are the PHAR, `build-metadata.json`, and `SHA256SUMS.txt`. The publish job executes verification tools, not plugin code. Only publication has `contents: write`.

A tag must match the complete `plugin.yml` version, with an optional leading `v`. For example, `v1.2.3-rc.1` requires `version: 1.2.3-rc.1`, not `1.2.3`. Versions have three numeric components and optional prerelease and build suffixes. Prerelease suffixes automatically set GitHub prerelease status. Input `prerelease: 'true'` may mark an otherwise stable version as a prerelease. `'false'` cannot turn an explicit prerelease version into a stable release. The default is `'auto'`.

An existing release is not replaced. A rerun that reaches an already published release fails rather than overwriting its assets. The workflow exports `artifact-id`, `sha256`, and `release-url`. Tag publication is allowed only for tag push events. Pull requests cannot publish through this workflow.

## Optional nightly prereleases

Create a separate workflow only if nightly publication is wanted.

```yaml
name: Nightly PHAR
on:
  schedule:
    - cron: '0 18 * * *'
  workflow_dispatch:
permissions:
  contents: write
  actions: read
jobs:
  nightly:
    uses: NhanAZ/DevTools/.github/workflows/release-plugin.yml@v1.0.0
    with:
      devtools-ref: REVIEWED_DEVTOOLS_COMMIT_SHA
      mode: nightly
```

Nightly runs use `nightly-<full commit SHA>` tags, always prerelease and never latest. They create an immutable release per commit rather than force-moving a shared `nightly` tag. A repeated run of the same commit fails at publication if that nightly release already exists. There is no automatic deletion or retention policy for releases. GitHub artifact retention does not delete release assets. Scheduled and manual branch events are accepted. Pull-request events are excluded.

## Select the builder revision

Reusable workflows require a full commit SHA in `devtools-ref`. The outer workflow reference may use that SHA or a release tag resolving to it. Branch names, tags and short SHAs are rejected in the input. The workflow verifies the checked-out HEAD and CLI contract before building. Select both references explicitly because the caller's `github.sha` identifies the plugin, not DevTools.

## Releasing DevTools itself

The project's own release process is separate from the consumer plugin workflow. `release-candidate.yml` performs all build and check work from an explicitly selected commit and uploads a reviewable candidate **before** a release tag or publication is needed. `release.yml` only promotes an explicitly selected successful candidate run and artifact ID for an already existing tag. It verifies source, run, attempt and workflow identity through GitHub's API, checks the downloaded archive against GitHub's SHA-256 digest, and checks every PHAR, examples ZIP and release-notes hash before publishing. It never rebuilds, regenerates notes, or repackages examples. See the exact [maintainer release procedure](development.md#release-process).

## Validation boundaries

Local behavior tests exercise build-result parsing, hash handoff, tampered bytes or metadata, tag mismatches, prerelease rules, and nightly tag generation. CI runs these tests and the actual composite action against the documented plugin example. Workflow linting does not prove hosted upload, download or publication behavior. Runtime artifact validation belongs in a separate Axolotl-PM test before publication when your project requires it. The release workflow guarantees packaging and byte continuity rather than gameplay correctness.
