# Changelog

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
