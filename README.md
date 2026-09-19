# Void Metadata

Plugin Grav 2 per le regole condivise di metadata, canonical URL e sitemap.
Mantiene separata la policy SEO comune dalla configurazione specifica del sito.

## Cosa offre

- `content_description(page, fallback)`: usa l'override `metadata.description`
  oppure ricava una description testuale dal contenuto della pagina, con limite
  configurabile.
- `void_canonical(page)`: restituisce la canonical assoluta della pagina e
  gestisce anche la paginazione Grav (`/page:2`).
- `void_sitemap_entries()`: raccoglie le pagine pubblicate e routable, esclude
  template e route noindex e integra opzionalmente `virtual-collections`.
- `SitemapRenderer`: accetta soltanto URL HTTP(S) dello stesso origin o route
  locali senza query e fragment; normalizza encoding e trailing slash,
  deduplica le entry e produce XML valido.

## Requisiti

- Grav 2.0+
- PHP 8.3+

## Installazione

Installazione manuale durante lo sviluppo:

1. Copiare o clonare il repository in `user/plugins/void-metadata`.
2. Eseguire `composer install` nella directory del plugin.
3. Abilitare il plugin in `user/config/plugins/void-metadata.yaml`:

```yaml
enabled: true
```

Quando il pacchetto sarà pubblicato sul Grav Package Manager, sarà possibile
usare `bin/gpm install void-metadata` dalla directory di Grav.

## Configurazione

Il file predefinito è `void-metadata.yaml`. Le opzioni più comuni possono essere
sovrascritte nella configurazione del sito:

```yaml
enabled: true
description_limit: 160
description_fallback: true
title_separator: ' | '
noindex_robots: 'noindex,nofollow,noarchive,nosnippet'
sitemap_excluded_templates: [sitemap, feed, error, search]
noindex_routes:
  - /search/*
  - /private/*
```

`sitemap_route_maps` può contenere percorsi di configurazione Grav che
restituiscono una lista di route aggiuntive. Le route, le mappe dei contenuti e
gli eventuali template esclusi propri del sito devono restare nella
configurazione del sito che installa il plugin.

## Sviluppo e verifica

```sh
composer validate --strict
composer install
composer check
php -l void-metadata.php
php -l src/MetadataPolicy.php
php -l src/SitemapRenderer.php
php -l tests/run.php
php -l tests/plugin-run.php
php -l tests/clean-grav.php
```

La suite copre policy titolo/robots, description, canonical, normalizzazione e
rendering XML della sitemap, oltre all'integrazione opzionale con
`virtual-collections`. Dopo aver copiato il plugin in un'installazione Grav 2
pulita, lo smoke check di compatibilità si esegue con:

```sh
php tests/clean-grav.php /path/to/grav
```

La verifica di release va ripetuta sia senza sia con `virtual-collections`
abilitato e deve includere una richiesta HTTP a una pagina che usi le funzioni
Twig del plugin.

## Release

Aggiornare `CHANGELOG.md`, verificare la suite su una installazione pulita,
creare un tag SemVer (ad esempio `1.0.0`) e pubblicare il tag insieme al
repository. Non includere dati runtime, cache, log, credenziali o configurazioni
specifiche dei siti.

## Licenza

MIT. Vedere [LICENSE](LICENSE).
