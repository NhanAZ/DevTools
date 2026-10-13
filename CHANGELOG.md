# Changelog

## 1.0.12 - 2026-10-13

- Allow only variable class operands of `instanceof`, which perform type checks without autoloading; computed targets remain rejected.
- Rewrite the exact `Fiber::getCurrent()`, non-null guard and `suspend()` pattern to `\Fiber::suspend()`; other dynamic static calls remain rejected.
- Rewrite `ReflectionClass($className)` only when the immediately preceding statement assigns that variable from `get_called_class()` inside a non-static closure with a bound object. Also allow `ReflectionClass($this)` where `$this` is available.
- Keep regression tests for each accepted pattern and for similar unproven patterns that must remain rejected.

## 1.0.11 - 2026-10-13

- Allow dynamic `is_a()` target types when `allow_string` is omitted and the target is a variable. PHP compares that target without autoloading it; calls with an explicit third argument remain subject to the dynamic-reference guard.
- Add regression tests for the allowed comparison and the rejected autoload-enabled form.

## 1.0.10 - 2026-10-11

- Prepare locked Composer virions when a PHP file declares its matching class and additional classes in the same namespace and directory. Generate a small autoload shim for each additional class and record the mapping in provenance.
- Keep standalone classes with incorrect paths rejected, detect duplicate class declarations regardless of letter case, and retain the existing dynamic class shading guard.

## 1.0.9 - 2026-10-11

- Require annotated runtime class factories to have two unconditional throwing guards immediately before a direct return of the new object.
- Reject unreachable checks and a class name reassigned after validation. The previous scan could mistake either case for a safe shaded factory.
- Keep the existing explicitly guarded factory pattern and reject unproven dynamic class operations.

## 1.0.8 - 2026-10-11

- Prepare Composer virions from `dev-` branches only when `composer.lock` pins a full Git commit and source and distribution references agree. Give each prepared revision a distinct internal version and keep the original Composer version in provenance.
- Match internal commit versions by exact revision because PHP version comparison can consider different hexadecimal suffixes equal.
- Add `import-poggit-libraries: false` for projects that keep legacy Poggit declarations but use their reviewed Composer lock as the DevTools dependency source. The default still imports Poggit libraries.
- Keep dynamic class shading guards in place. Preparation alone does not certify a standalone PHAR.

## 1.0.7 - 2026-10-11

- Require an unannotated object-class return to follow a top-level `instanceof ... || throw ...` guard immediately in the same function.
- Reject guards inside optional branches and guards invalidated by an intervening assignment. Keep the explicit annotation path and other 1.0.6 dependency preparation behavior.
- Derive the Axolotl-PM runtime smoke target from the committed Composer lock and align the reproduction guide with the current 5.49.1 pin.

## 1.0.6 - 2026-10-11

- Initially allow an object-class expression after an explicit `instanceof ... || throw ...` guard; version 1.0.7 tightens the control-flow requirement.
- Add a regression test for guarded object-class inspection without weakening rejection of unproven dynamic class operations.

## 1.0.5 - 2026-10-11

- Permit explicitly annotated, guarded runtime class factories after `class_exists()` and `is_subclass_of()` prove the selected class is a supported implementation.
- Permit explicitly annotated object-class inspection such as `$callback::class` while continuing to reject unproven dynamic class references and operations.
- Add regression coverage and document the annotation contract for consumers that use safe runtime class selection.

## 1.0.4 - 2026-10-11

- Prepare locked Composer virions whose PSR-4 or namespace-based PSR-0 mapping uses a one-element directory array.
- Prepare single-root classmap packages only when their PHP source stays inside the declared namespace tree and passes the existing class-layout validation.
- Keep root plugin classmaps, multiple package roots, dynamic class references during shading and unverified dependency source changes rejected.

## 1.0.3 - 2026-10-10

- Check out the build and release verification tools from the exact commit that defines each reusable workflow job.
- Let Dependabot update one reusable workflow reference without a second revision input becoming stale.
- Keep `devtools-ref` as a deprecated, ignored input for existing callers; warn when it is provided.

## 1.0.2 - 2026-10-10

- Let the reusable plugin build workflow check out a full, pinned Axolotl-PM source commit for PHPStan analysis.
- Inspect the PHAR archive before the reusable workflow uploads its artifact.
- Exercise the reusable workflow with a fixture plugin, PHPStan maximum level and the pinned Axolotl-PM source in hosted CI.
- Keep the step-level action and existing workflow inputs compatible with version 1.0.1.

## 1.0.1 - 2026-10-10

- Treat `pocketmine/pocketmine-mp` as the server platform only when the locked Axolotl-PM package explicitly replaces it. Continue rejecting missing or unrelated packages.
- Accept a locked Composer virion's `php-64bit` requirement and check the PHP integer size during preparation and shared loading.
- Keep `composer-runtime-api` as an installation constraint checked by Composer. It is not a plugin runtime dependency and is omitted from generated virion manifests.
- Add regression tests for the Composer replacement and platform requirements.

## 1.0.0 - 2026-10-10

Initial release for Axolotl-PM.

- Load folder plugins through the server's plugin manager.
- Load compatible local virions with transitive dependency and collision checks.
- Build standalone PHARs with private AST shading, resources, licenses and output protection.
- Inspect and extract PHARs with archive validation.
- Prepare supported Composer Virion v3 packages from an installed, locked graph.
- Provide CLI commands with versioned JSON, diagnostic codes and artifact hashes.
- Build and publish plugin artifacts through GitHub workflows with optional PHPStan.
- Include a dedicated example plugin, library and coding agent template.
