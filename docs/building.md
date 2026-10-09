# Building standalone PHARs

## From the server

The same operations are available outside a running server through the [CLI](cli.md). [Composer preparation](dependencies.md) is explicit and occurs before the build. Neither normal build nor server startup downloads dependency code.

Run these commands as an operator.

```text
/devtools doctor MyPlugin
/devtools build MyPlugin
```

The artifact is written to server-root `build/MyPlugin.phar`. Existing output is protected. Use `/devtools build MyPlugin --overwrite` only after confirming that replacement is intended.

Server `doctor` checks project structure, build declarations and that project's local dependency selection using the same resolver as the CLI. It does not prove that all installed plugins have compatible shared requirements or that a plugin enabled successfully. Use `/devtools virions` for the actual shared registry and the server log for enable failures. A doctor run does not perform shading, PHPStan or a runtime test.

Unknown or repeated server build and extract options are rejected before writing output. If two source folders declare the same plugin name, select an explicit folder path immediately under `plugins/` or remove the duplicate name. DevTools will not choose by discovery order.

## What the builder validates

Before installing output, DevTools validates the plugin manifest and main class, local configuration, declared virions and versions, namespace safety, PHP syntax, and standalone references. It stages source without changing it, AST-shades virions into a deterministic plugin-private namespace, creates a SHA-256 PHAR, reopens it, and checks the manifest, main source, shaded roots, and signature.

The default package includes `plugin.yml`, `src/`, `resources/`, common license and notice files, declared virions, and explicit `include-paths`. It excludes `.git`, `.github`, IDE files, tests, caches, logs, `build/`, ordinary development `vendor/`, temporary files, and undeclared project files.

Builds fail rather than guessing when PHP code constructs dynamic virion class names, stores affected namespaces in strings, uses ambiguous grouped imports, references undeclared development namespaces, or retains a manifest dependency on DevTools or DEVirion.

## Building DevTools from source

Release users do not need Composer. Maintainers can run the complete build gate.

```text
composer install
composer check
```

To build and inspect the artifact without running the full gate, use these commands.

```text
composer build
composer artifact
```

The self-build is `build/DevTools.phar`. Official release automation renames it to `DevTools-<version>.phar` and publishes `SHA256SUMS.txt` after all checks pass.

DevTools currently has no declared runtime virions. Its self-build therefore ignores unrelated virions found in the server's `virions/` directory, so an installed development virion cannot change the result of `composer check`. Normal plugin builds still reject unproven dynamic class references when local virions are available.

## Production verification

Use the release checksum to verify a download.

```text
# Linux and macOS (run beside the downloaded release assets)
sha256sum -c SHA256SUMS.txt

# Windows PowerShell
Get-FileHash .\DevTools-<version>.phar -Algorithm SHA256
```

For another plugin, test the generated PHAR on a clean compatible server without its source folder, DevTools, or shared virion directories. Exercise the plugin behavior that requires its bundled libraries and resources.
