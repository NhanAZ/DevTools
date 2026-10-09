# Contributing

Keep changes focused on folder loading, shared development virions, or standalone builds and extraction.

1. Read `PROJECT_MAP.md` and `AGENTS.md`.
2. Change the owning module and its tests.
3. Add a fixture only when it represents real behavior or a regression.
4. Run the focused Composer test command.
5. Run `composer qa` before submitting a change.

Security-sensitive changes must test the rejected path as well as the success path. Build changes should verify that source remains unchanged and an existing output survives failures. Shading changes must use the AST. Do not add regex replacement over PHP source.

Do not add network downloads, hot reload, package registry behavior, publishing automation, or compatibility claims without a verified server test.
