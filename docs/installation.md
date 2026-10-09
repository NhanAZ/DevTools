# Installation

## Fresh install

1. Use Axolotl-PM with the API declared by DevTools and its required PHP extensions. See the [verified runtime target](runtime-validation.md).
2. Open the [latest GitHub release](https://github.com/NhanAZ/DevTools/releases/latest).
3. Download the installable PHAR from the [latest DevTools release](https://github.com/NhanAZ/DevTools/releases/latest) and compare its SHA-256 with the release checksum and recorded build provenance. GitHub's automatic "Source code" archives are not installable plugin artifacts.
4. Stop the server and copy the file to `plugins/DevTools.phar`.
5. Start the server. DevTools creates server-root `virions/` and `build/` directories if they are missing.
6. Run `/devtools status` as an operator and check that the displayed version matches the release.

DevTools cannot bootstrap itself from a source folder on an otherwise unmodified server. Always install its release PHAR first.

## Confirm folder loading

Download and extract the release asset `DevTools-examples-<version>.zip`. Copy `HelloShared/` to `plugins/HelloShared/` and `SharedGreeting/` to `virions/SharedGreeting/`, then restart. The server log should report one loaded virion and enable `HelloShared`. Run these commands.

```text
/devtools virions
/devtools doctor HelloShared
```

Remove the example afterward if it is not needed.

## Update

1. Read the release notes and migration guide.
2. Stop the server.
3. Keep the previous DevTools PHAR as a rollback copy outside `plugins/`.
4. Replace `plugins/DevTools.phar` with the new artifact.
5. Start the server and run `/devtools status`, `/devtools virions`, and `/devtools doctor`.

Do not keep two DevTools PHARs in `plugins/`. Axolotl-PM will detect a duplicate plugin name.

## Roll back

Stop the server, replace `plugins/DevTools.phar` with the previously working release artifact, and restart. DevTools does not maintain a database or mutate plugin source, so rollback requires no data conversion unless a future release note explicitly says otherwise.

## Uninstall

1. Build any folder plugin that must continue running in production.
2. Stop the server.
3. Remove `plugins/DevTools.phar`.
4. Remove or archive folder plugins that depended on the DevTools loader. Axolotl-PM cannot load them afterward.
5. Remove `virions/` and `build/` only if their contents are no longer needed. DevTools never deletes these user directories during uninstall.
