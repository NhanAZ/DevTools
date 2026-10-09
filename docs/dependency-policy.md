# DevTools dependency policy

DevTools targets Axolotl-PM only. Folder loading, virion selection, dependency preparation, staging, diagnostics and artifact orchestration remain owned by this project. Small utilities use PHP or the existing Axolotl-PM APIs. This does not mean replacing the PHP parser or QA tools with new subsystems.

## Direct dependencies

The accepted Composer runtime requirements are PHP `^8.1`, `ext-json`, `ext-phar`, `ext-yaml`, and `nikic/php-parser:^5.6`. The parser is essential for AST-based shading and its existing license is retained. The current lock selects `nikic/php-parser` `v5.8.0`.

The accepted development requirements are `friendsofphp/php-cs-fixer:^3.64`, `phpstan/phpstan:^2.1`, `phpunit/phpunit:^10.5`, and `axolotl-pm/pocketmine-mp:dev-stable`. Axolotl-PM is pinned by `composer.lock`, rather than resolved afresh for a test run. Packages such as Symfony components or Composer utilities are dependencies of those tools/server packages, not direct runtime dependencies introduced by DevTools.

The standalone CLI currently uses Axolotl-PM manifest value objects from the development installation. Consequently, its documented source-checkout installation includes development dependencies, even though it never starts a server. On a server, Axolotl-PM itself supplies those classes. The shipped DevTools plugin PHAR does not contain Axolotl-PM, PHPUnit, PHPStan, PHP CS Fixer, or Composer's autoloader.

PHP 8.1 is the source, CLI and build minimum. Executed local QA and runtime evidence identifies its actual PHP version separately. A PHP 8.4 test does not certify an executed PHP 8.1 test.

## Artifact boundary

The self-build's `devtools.yml` includes only `vendor/nikic/php-parser/lib` and its license. `bin/validate-artifact.php` enforces that allowlist against the actual PHAR, in addition to its existing signature, manifest, secret and development-file checks. Regression tests deliberately add QA, server or Composer bootstrap files to a valid artifact and require the release gate to reject them.

References to upstream implementations are research provenance, not copied libraries renamed under `src/`. See [third-party notices](../THIRD_PARTY_NOTICES.md). Generic shading regressions use fixtures owned by DevTools. Successfully building any particular external package is not the acceptance criterion for the shader.

## User dependencies and CI tooling

Dependencies of a plugin being built belong to that plugin. Keep the plugin's required libraries when configuring a build. Unsupported preparation or shading contracts produce a diagnostic. They do not justify removing the dependency to obtain a green build. See [supported dependency formats](dependencies.md).

GitHub checkout, upload and download actions, the pinned Axolotl-PM PHP setup helper, actionlint, GitHub CLI, Node's standard library and the runner's archive utilities are CI tooling. They are not runtime plugin libraries or startup integrations. Publication and credentials remain in the workflow layer. Dependency preparation and network access are explicit. Server startup and ordinary build do not fetch dependencies.

The direct dependency declarations, copied source, scripts, workflows and final PHAR all need review when this boundary changes. Do not hide a new library by copying it into `src/`, and do not replace a mature subsystem with custom code solely to reduce a package count.
