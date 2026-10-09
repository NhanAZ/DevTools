# Dependencies and private bundled virions

DevTools supports local folder and PHAR virions and an explicit, bounded import of installed Composer Virion v3 packages. Server startup and `build` never download or update dependencies. Composer remains responsible for solving its own graph and creating `composer.lock`.

## Local packages

Keep existing `virion.yml` packages in the selected virions directory. A plugin declares direct requirements in `devtools.yml`, `composer.json` `extra.devtools.virions`, or matching legacy `.poggit.yml` project libraries. No additional configuration is needed for a plugin without dependencies.

```yaml
virions:
  - name: SharedGreeting
    version: ^1.0.0
```

A local virion can declare its own dependencies using the same `virions` list in **its `virion.yml`**. Both shared development loading and builds follow these transitive requirements. Missing packages, unsatisfied constraints and duplicate copies of the selected version fail with the requesting manifest in the diagnostic. The highest compatible installed version is selected. This is a deterministic local selection policy. It does not search alternative historical dependency graphs or replace Composer's solver. Pin a compatible local set if selection fails or does not converge. A transitive shared-graph failure prevents registration of that discovered graph. Fix the diagnostic before restarting.

Only dependencies reachable from the final selected roots belong to a build. Dependencies of discarded versions, including their now-orphaned cycles, are removed. Compatible reachable cycles are selected once. A graph whose highest-compatible choices oscillate fails with a request to pin versions, rather than searching alternative graphs.

For a package supplied from Git outside Composer, use the repository and full commit supplied by its author, check out that commit explicitly, and place the documented package subdirectory in `virions/`. DevTools does not infer repository URLs from package names, fetch Git revisions, or maintain a second Git lockfile. Source acquisition for these packages remains an external, explicit step. `.poggit.yml` library names and constraints remain a migration input, not a download instruction.

## Composer preparation

Run Composer in the plugin project, with a reviewed lockfile. Dependency install scripts and plugins are not needed for the supported formats.

```sh
composer install --no-dev --no-scripts --no-plugins --no-interaction
php /path/to/DevTools/bin/devtools.php prepare --project=. --virions=virions-prepared --json
php -d phar.readonly=0 /path/to/DevTools/bin/devtools.php build --project=. --virions=virions-prepared --out=build --json
```

`prepare` reads `composer.json`, `composer.lock`, `vendor/composer/installed.json` and package metadata as data. It does not execute `vendor/autoload.php`, package code, Composer scripts or Composer plugins. `build` and `doctor` infer the exact locked runtime graph from the same files. Do not repeat Composer dependencies in `devtools.yml`. `require-dev` packages are excluded. The Axolotl-PM server package is a runtime platform input and is not copied into a plugin.

Preparation requires a **new destination directory**. All selected packages are staged and checked before that directory is installed. An existing destination is never overwritten. For an update, prepare into a new directory and switch `--virions` after success. With no Composer runtime dependencies, preparation returns an empty result without creating a directory. To use prepared packages in shared development loading, place the resulting package folders in the server's configured virions directory. They have ordinary `virion.yml` manifests and therefore use the existing loader.

Supported Composer packages must meet the following requirements.

- A locked semantic tag such as `1.2.3` or `v1.2.3`, with `extra.virion.spec` `3.0` or `3.1` and one `namespace-root`.
- One PSR-4 mapping for that namespace to a single directory, or one namespace-based PSR-0 mapping to a single directory. Every class must match its namespace, filename and case. Legacy underscore-to-directory PSR-0 class layouts are not supported.
- Runtime package dependencies that also satisfy this contract. Composer's locked graph decides their versions. DevTools does not solve constraints or accept virtual packages or replacements as missing implementation code. The one platform exception is `pocketmine/pocketmine-mp` when the locked `axolotl-pm/pocketmine-mp` package explicitly replaces it. Axolotl-PM itself is never bundled as a virion.
- PHP platform constraints using caret versions or comparison ranges, optionally separated by `||`, `php-64bit` with `*`, and `ext-*` requirements with `*`. These are preserved in generated metadata and checked during preparation and shared loading. Composer checks `composer-runtime-api` during installation, so DevTools omits it from the generated runtime manifest. Other platform expressions fail explicitly. The generated manifest also retains DevTools' PHP 8.1 baseline. This does not override the package's stricter Composer constraint.
- Resources colocated under the selected source directory when code relies on `__DIR__` relative paths. Their bytes and relative layout are preserved.

DevTools explicitly rejects `shared-namespace-root`, classmap and files autoloading, multiple source roots, custom vendor directories, branch and development versions, package root `resources/`, and general Composer packages without supported virion metadata. Root plugin `autoload.files` and classmap entries are also rejected because Axolotl-PM does not execute Composer's bootstrap automatically. Root Composer mappings must use one directory per namespace within `src/`. Paths outside `src/` and arrays of source roots are rejected. Composer mappings do not register another runtime loader. Plugin namespace and filename layout remain governed by `plugin.yml` and `src/`.

Root mappings must also resolve each namespace to exactly the directory used by Axolotl-PM, including case. For example, with `src-namespace-prefix: MyPlugin`, `MyPlugin\\Extra\\` must map to `src/Extra/`. Mapping it to `src/extra/` or a namespace outside that plugin prefix is rejected, even though the directory lies under `src/`.

The existing DevTools self-build explicitly packages its PHP parser source and license. That narrow self-packaging path is retained. Arbitrary `include-paths` on a user plugin do not turn off Composer validation or imply that a copied vendor directory will autoload.

## Provenance and output safety

The Composer content hash must match the current root configuration. Installed package version, source and distribution metadata, autoload, requirements and virion metadata must agree with the lock. Preparation records the source and distribution references, complete lock SHA-256, canonical package metadata SHA-256, generated manifest SHA-256, and a deterministic hash of prepared source paths and bytes in `devtools-provenance.json`.

Build selection and Composer-bound shared loading check the **package** metadata fingerprint, prepared manifest and source hashes. Two plugins with different lockfiles may share the same prepared package when that package's locked metadata is identical. Changing a tag's revision, prepared manifest or prepared source requires preparation again. Prepared packages from an older version without `manifestSha256` must be prepared again into a new directory. Provenance also accompanies the PHAR under `META-INF/virions/`. Dependency metadata appears in the build JSON result. These checks detect drift against reviewed local Composer inputs. They are not independent cryptographic authentication of upstream source.

Source input is not modified. Symlinks in package paths or staged content are rejected. A failed build preserves any previously successful output. Keep the package's license and notice obligations when distributing the result. Standard license and notice files are copied into the artifact.

## Shared source and private PHAR behavior

Shared development loading registers one selected namespace and version in the server process. Objects and static state from that namespace can be shared across plugins. Incompatible requirements for that shared graph fail instead of creating hidden per-plugin loaders.

A build shades the complete selected graph into the plugin's private namespace. Separate plugins can bundle separate versions, but their library types are distinct and static state is separate. Do not exchange objects of a privately bundled library as a cross-plugin API. Define that API in an intentionally external plugin dependency instead. There is no public or shared namespace contract for bundled virions.

Namespaces and supported static PHP class references are rewritten by the existing AST shader. Dynamic class strings, dynamically selected class operations and other unprovable cases remain errors. Successful preparation therefore does **not** guarantee that a package passes shading or boots on Axolotl-PM. Async worker behavior must be checked separately on the pinned server runtime. Ordinary PHP unit execution is not that check.

## Compatibility limits

Preparation and shading are separate checks. A package can satisfy the supported Composer metadata format yet fail because its code selects a library class dynamically. Do not disable the guard or assume successful preparation guarantees a standalone artifact.

The dedicated Composer fixtures test a transitive dependency graph, execution of shaded classes, colocated resources, licenses and provenance. Failure cases cover stale locks, changed prepared source, unsupported autoload layouts and preservation of existing output. These tests do not establish compatibility with every Composer package.

See [migration](migration.md) for the workflows supported when moving from other tools.
