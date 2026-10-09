# Security policy

## Supported versions

The latest released version receives security fixes.

## Reporting

Do not publish an exploitable archive, path traversal, arbitrary file write, or unsafe shading case before maintainers can respond. Use the repository's private security-advisory feature when available. Otherwise, open a minimal issue that requests a private contact and omits exploit details.

Include the DevTools version, Axolotl-PM version and API, operating system, relevant manifest and configuration, the smallest safe reproduction, expected destination, and observed destination. Never include credentials or private server data.

## Security boundaries

DevTools treats plugin and virion source as trusted development code when it is eventually loaded by PHP. Metadata inspection, building, and extraction still avoid executing source. Builds do not access the network. Staging and extraction reject symbolic links, ambiguous namespaces, non-portable archive paths, and destination escape. Existing build outputs require explicit overwrite. Extraction prepares a sibling tree before atomically installing it with backup and restore.
