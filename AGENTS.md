# Agent instructions

This is the standalone public repository for the Grav 2 plugin `void-metadata`.
Keep the plugin slug, public PHP API and runtime contract stable.

Site-specific configuration belongs to the consuming Grav site. Do not copy
redirect maps, taxonomy maps, content-type policies, excluded templates, themes,
accounts, runtime data or secrets into this repository.

Before a release:

- run `composer validate --strict` and `composer check`;
- lint every PHP file, including files under `tests/`;
- verify the plugin in a clean Grav 2 installation both without and with the
  optional `virtual-collections` plugin;
- update `CHANGELOG.md`, then use a SemVer tag matching the release.

Keep CI and tests focused on the standalone plugin contract. Do not commit
`vendor/`, generated runtime data, cache, logs, credentials, private
moderation records or site-specific configuration.
