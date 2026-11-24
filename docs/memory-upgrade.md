# Memoria upgrade PHP

## Ultima esecuzione: 2026-11-24, PHP 8.6
- Modalità: **LEGACY**, PHP supportati 8.4, 8.5, 8.6. Motivo: CI al massimo su 8.1, `php >=7.4`, Laravel richiesto ≤ 8.
- Repo non fork. Branch di default: `master`. Ultima versione stabile: 3.1.1 (GitHub Release, tag senza prefisso `v`).

## Gate: VIA LIBERA
Albero risolto su 8.4, 8.5, 8.6. Nessun bloccante.

| Pacchetto | Prima | Dopo | Verdetto |
|---|---|---|---|
| php | >=7.4 | ^8.4 | BUMP |
| illuminate/* | ^5.0..^8.0 | ^12.0\|^13.0 | BUMP |
| jenssegers/mongodb | ^3.6..^3.8 | mongodb/laravel-mongodb ^5.6 | REPLACE (abbandonato) |
| orchestra/testbench | ^3..^6 | ^10.0\|^11.0 | BUMP |
| phpunit/phpunit | ^5..^9 | ^11.5\|^12.0\|^13.0 | BUMP |
| phpunit/phpcov | ^7\|^8 | ^11.0\|^12.0\|^13.0 | BUMP |
| laravel/pint, larastan/larastan | assenti | ^1.0, ^3.0 | aggiunti |

- Framework target: Laravel 13 (con 12 mantenuto). Laravel 11 escluso: tutte le release 11.x hanno security advisory di Composer.
- `mongodb/laravel-mongodb` 5.2-5.5 non funziona con le prime release di Laravel 12 (firma `timeout()`): lower bound `^5.6`. La 5.8.0 con `mongodb/mongodb` ai minimi fallisce (`SortDirection`), problema upstream.
- `ext-mongodb` caricata da Herd su tutte e tre le versioni (2.5.2 su 8.4/8.5, 2.5.7 su 8.6).

## Versione
3.1.1 → **4.0.0** (MAJOR): PHP minimo alzato, framework tolti, driver sostituito con cambio di namespace.

## Fatto da sapere
- Il branch `develop` contiene già una riscrittura 4.0 non rilasciata (124 commit avanti a `master`, `UPGRADE-4.0.md`, CHANGELOG `4.0.0 - Unreleased`). La PR #101 parte da `master` ed è un aggiornamento minimo: serve una decisione umana su quale strada tenere prima di rilasciare 4.0.0.
- Gli stati intermedi Laravel 8→9→10→11 non sono stati committati: non installabili su PHP 8.4+.
- Test: i trait vanno usati in classi host (`tests/Support/`), `Helper::isArray()` confligge con `Assert::isArray()` (final) di PHPUnit 13.
- `Request::merge()` di Laravel 12/13 rifiuta gli `stdClass`: `MainMongoTrait::setRequest()` li converte in array.
- `$collection` non è più letta da `mongodb/laravel-mongodb` (solo `$table`): `MDModel::getTable()` la onora.
- Test locali: MongoDB 8.0 in Docker (`docker run -d --name lmas-mongo -p 27017:27017 mongo:8.0`), 69 test OK su 8.4, 8.5, 8.6.

## Layout docs
Prima: nessuna cartella `docs/`. Dopo: `docs/README.md` (indice) + `docs/v4/` (index, installation, usage, testing, upgrading). Nessun `.oa-docs.json`.

## QA
Pint (preset `laravel`), PHPStan/Larastan livello 5, nessun baseline. Livello 6: 136 errori, livello 7: 149 (tipi mancanti sull'API pubblica). Script composer: `lint`, `lint:check`, `stan`, `test`. CI: `build-ci.yml` con job `quality` e matrice test.

## Badge
Latest Release, Packagist, Downloads, PHP, License, Scrutinizer, codecov: OK. Tests: "no status" fino al primo run su `master`. StyleCI: rimosso (404). Nessuna release creata (esiste già la 3.1.1).

## Branch / PR
`chore/php-8.6-upgrade` / https://github.com/offline-agency/laravel-mongo-auto-sync/pull/101

## Da fare dopo il merge
```bash
awk '/^## \[4.0.0\]/{f=1;next} /^## /{f=0} f' CHANGELOG.md > release-notes-4.0.0.md
gh release create 4.0.0 --target master --title "4.0.0" --notes-file release-notes-4.0.0.md
```
Poi sostituire "Unreleased" con la data nel `CHANGELOG.md`.
