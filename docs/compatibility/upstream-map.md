# Upstream map: offline-agency/laravel-mongo-auto-sync vs mongodb/laravel-mongodb 5.11.0

> Sections 0-5 are a snapshot of the analysis at commit `e7374dd` (written during the work, hence the first person). Section 6 lists what changed afterwards and supersedes them where they differ, including the version drift and documentation rows. Classification labels (NATIVO_EREDITATO, DIVERGENZA_STATICA, ADAPTER_NECESSARIO, VALORE_CUSTOM, NON_APPLICABILE, NON_VERIFICATO) mean: inherited from laravel-mongodb, static divergence, adapter still needed, package-specific value, not applicable, not verified.

Target path in repo: `docs/compatibility/upstream-map.md`

## 0. Baseline, scope and evidence levels

| Item | Value | Evidence |
|---|---|---|
| Upstream tag | `5.11.0`, annotated tag object `9c40f85f253c9a7a170662d22e3d0a74cc87449c` -> commit `0634653039468ceb0268a69192bdac64469ed043`; tagger date 2026-09-10T12:27:40Z (the release publication time 12:33:03Z was given in the brief; I did not re-query it) | VERIFIED (`gh api .../git/refs/tags/5.11.0`, `.../git/tags/9c40f85...`) |
| vendor version | `vendor/composer/installed.json`: mongodb/laravel-mongodb 5.11.0 @ 0634653..., mongodb/mongodb 2.5.0, laravel/framework v13.35.0, orchestra/testbench v11.3.0, pestphp/pest v4.7.8 | VERIFIED |
| vendor == tag | Byte-identical to `gh api "repos/mongodb/laravel-mongodb/contents/<path>?ref=5.11.0"` for: src/Eloquent/DocumentModel.php, Eloquent/Builder.php, Eloquent/Model.php, Eloquent/EmbedsRelations.php, Eloquent/HybridRelations.php, Relations/EmbedsOneOrMany.php, Relations/EmbedsMany.php, Relations/EmbedsOne.php, Query/Builder.php, Helpers/QueriesRelationships.php, Concerns/ManagesTransactions.php | VERIFIED (`cmp`) |
| Other upstream files quoted | Helpers/QueriesRelationshipAggregates.php, Query/Grammar.php, Connection.php: read from vendor only, not compared byte by byte with the tag | STATIC (vendor) |
| Local worktree | branch `fix/upstream-compatibility` | |

**The worktree changed while I was working.** HEAD moved 67777f7 -> 28ee83d -> 454403f -> 3b48558 -> e7374dd. This map describes **e7374dd** (working tree clean at the end of the analysis). Changes since the 67777f7 baseline in the brief:
- `28ee83d fix(eloquent): delegate raw() and timestamp handling of the builder to laravel-mongodb`: `src/Eloquent/Builder.php` went from blob `1c0fd99d9321` (232 lines) to `36ca0d9c7c2a` (86 lines). update/insert/insertGetId/delete/raw/addUpdatedAtColumn were removed; only increment/decrement are left. Adds tests/Compatibility/BuilderParityTest.php.
- `454403f`: tests/Compatibility/AggregateParityTest.php and the Cmp{Native,Package}{Parent,Item,Note} fixtures.
- `3b48558`: tests/Compatibility/ArrayCastTest.php and the Cmp*ArrayCast fixtures.
- `e7374dd`: `.github/workflows/build-ci.yml` (blob `1f7d4db1b7a8` -> `2b8b73a671c3`) adds the reference, lowest and replica-set jobs.

For Builder, both states are documented (section 2.2). Every other `src/` file has the same blob at 67777f7 and at e7374dd.

Evidence levels:
- **VERIFIED**: I ran it or observed it (test run, probe, `cmp`, `gh api`).
- **STATIC**: I read the code but did not run it.
- **NON_VERIFICATO**: not read, or read only partially.

### Test runs (VERIFIED)
- `php vendor/bin/pest` (MONGO_PORT=27017 standalone, DB `unittest_map`) at e7374dd: **306 passed, 3 skipped**. The 3 skipped tests are `tests/Compatibility/TransactionTest.php` (no replica set). An earlier run that started while HEAD was 454403f gave the same counts.
- `tests/Compatibility/TransactionTest.php` with MONGO_PORT=27018 (a local rs0 replica set was listening): **3 passed** (7 assertions).
- Differential probes in a scratch file outside the repo (not part of the repository; the parity cases that matter are now tests under `tests/Compatibility/`). Each probe compares a native laravel-mongodb model (`CmpNativeParent`/`CmpNativeItem`) with its package twin (`CmpPackageParent`/`CmpPackageItem`). Results are quoted in section 3 as P1 to P8.

---

## 1. Summary of the inheritance chain

```
Illuminate\Database\Eloquent\Model
 └─ MongoDB\Laravel\Eloquent\Model (abstract, uses DocumentModel → HybridRelations, EmbedsRelations)
     └─ OfflineAgency\MongoAutoSync\Eloquent\Model (uses EmbedsRelationships: overrides embedsMany/embedsOne)
         └─ Http\Models\MDModel (Helper, MainMongoTrait, ModelAdditionalMethod, PlainMongoTrait, RelationshipMongoTrait)
             ├─ DefaultMini ($primaryKey = 'ref_id')
             └─ DefaultGeo ($primaryKey = 'cadastralCode')

Illuminate Eloquent\Builder → MongoDB\Laravel\Eloquent\Builder → OfflineAgency\...\Eloquent\Builder (increment/decrement only, at e7374dd)

Illuminate Relations\Relation
 ├─ MongoDB\Laravel\Relations\EmbedsOneOrMany → EmbedsMany / EmbedsOne      (upstream)
 └─ OfflineAgency\...\Relationships\EmbedsOneOrMany → EmbedsMany / EmbedsOne  (package: parallel hierarchy, NOT a subclass of upstream)
```

The package relations are a **parallel copy**, not subclasses of the upstream relations. Any upstream `instanceof MongoDB\Laravel\Relations\EmbedsOneOrMany` check is therefore false for them (section 3.3). This is VERIFIED: P2 prints `get_parent_class($p->items())` = `OfflineAgency\MongoAutoSync\Relationships\EmbedsOneOrMany`.

---

## 2. Per-symbol inventory

Column legend: **Up** = upstream equivalent at 5.11.0 (vendor path `vendor/mongodb/laravel-mongodb/`). **Dec** = decision. **Cls** = classification.

### 2.1 `src/Eloquent/Model.php`: blob `903a3a8aed3e` (unchanged since 67777f7)

| Symbol (local lines) | Up | What / why | Difference still needed | Discriminating test | Dec | Consequences | Cls |
|---|---|---|---|---|---|---|---|
| `protected $parentRelation` (L18) | `DocumentModel::$parentRelation` is `private Relation` (DocumentModel.php:67) | Redeclared because the upstream one is private | None functionally. The upstream private property is simply never initialised: DocumentModel only reads it through `getParentRelation()` (L617, L685), which the local class overrides | none needed | deprecate (remove in the next major) | Protected, untyped property. Subclasses may write `$this->parentRelation` directly, so removing it is a BC break for them | DIVERGENZA_STATICA |
| `setParentRelation(Relation)` (L26-29) | `DocumentModel::setParentRelation` L607-610 | Identical body | none | EmbeddedRelationsTest (whole file), Coverage/EloquentBuilderTest:42 | remove with the property (delegate) | none (same signature, no return type on either side) | DIVERGENZA_STATICA |
| `getParentRelation(): ?Relation` (L36-39) | `DocumentModel::getParentRelation` L615-618 | Identical | none | same | remove (delegate) | none | DIVERGENZA_STATICA |
| `setAttribute($key,$value)` (L48-57) | `DocumentModel::setAttribute` L257-297 | Delegates to the parent. Only addition: a dotted key that is listed in `getDates()` is converted with `fromDateTime()` before delegating | Keep the addition. Upstream dot handling (L283-290) stores through `parent::setAttribute('__LARAVEL_TEMPORARY_KEY__', ...)`, so `isDateAttribute()` never matches the dotted key and the value stays a Carbon in memory. `Grammar::prepareFieldsForQuery` (Grammar.php:89-90) converts DateTimeInterface to UTCDateTime at write time, so the **persisted** BSON type is likely the same. Only the in-memory attribute differs (STATIC inference, not probed) | Coverage/EloquentModelTest:7 and :17 (ElqDotted with `CREATED_AT='meta.created_at'`); Compatibility/ModelParityTest:17, :29, :87, :101 | keep (already decided) | **Scope is narrower than the comment says.** Laravel 13 `HasAttributes::getDates()` (vendor/laravel/framework/.../HasAttributes.php:1671-1677) returns only the created/updated columns, and the `$dates` property is ignored. So `tests/Models/User.php:30 $dates = ['birthday','entry.date']` and `MDModel::$dates` (MDModel.php:21-25) are dead configuration. The conversion only applies to dotted **timestamp** columns. The docblock "dotted keys listed in $dates" is misleading and should be fixed | VALORE_CUSTOM |
| `getAttribute` | `DocumentModel::getAttribute` L184-214 | Override removed (already decided) | none | ModelParityTest:87 | delegate (done) | none | NATIVO_EREDITATO |
| `newEloquentBuilder($query)` (L62-65) | `DocumentModel::newEloquentBuilder` L621-624 | Returns the package Builder | Needed as long as Builder keeps the increment/decrement override | BuilderParityTest:100 | adapt (drop it if Builder is removed) | Return type: `OfflineAgency\...\Builder` (subclass of the upstream Builder), so it is compatible | ADAPTER_NECESSARIO |
| `use EmbedsRelationships` | `DocumentModel` uses `EmbedsRelations` (L62) | Overrides embedsMany/embedsOne (see 2.3) | see 2.3 | see 2.3 | see 2.3 | see 2.3 | VALORE_CUSTOM |

### 2.2 `src/Eloquent/Builder.php`

#### State at e7374dd: blob `36ca0d9c7c2a`

| Symbol | Up (Eloquent/Builder.php) | What / why | Difference still needed | Test | Dec | Consequences | Cls |
|---|---|---|---|---|---|---|---|
| `increment($column,$amount,$extra)` L23-49 | L213-224 | Embedded path: return 0 for a non-string column, read `$model->{$column}`, set it to null, `syncOriginalAttribute`, then `$model->update([$column=>$value])`. Non-embedded: `parent::increment` | Behavioural divergence on the embedded path, VERIFIED (P3): **package** events = `updating, saving, updating, updated, saved, updated` per call, and the embedded `updated_at` is rewritten. **Native** events = `updating, updated`, embedded `updated_at` not touched. Persisted `n` and `$extra` are identical (n=5, x=1 on both). Both ignore `$amount` when called directly on the builder (`$child->newQuery()->increment('n',10)` returns 1 and leaves n=5 on both). The upstream path passes `$extra` in `$values`, which `performUpdate` ignores; extra is persisted through `getDirty()` because `Model::incrementOrDecrement` force-fills it (Laravel Model.php:1144) | Compatibility/BuilderParityTest:100 (asserts `$saves === 2`, package-only); Coverage/EloquentBuilderTest:53, :65, :76 | keep for now, document as VALORE_CUSTOM. Long term: decide whether the extra saving/saved events are a contract; if not, delegate | Observers see saving/saved and a second updating per increment. MDModel embedded models use the observer, whose `saved` hook can dispatch SyncTargetJob when background sync is on. Return type int on both. Upstream returns 1 for a non-string Expression column through `update()`; the package returns 0 | VALORE_CUSTOM |
| `decrement` L59-85 | L226-237 | Mirror of increment | same | same | same | same | VALORE_CUSTOM |
| update / insert / insertGetId / delete | L153-211 | Removed in 28ee83d; inherited | none. Upstream calls `$relation->performUpdate/Insert/Delete($this->model, ...)` by duck typing (no instanceof), so it works with package relations | EloquentBuilderTest:20, :27, :42; EmbeddedRelationsTest (full suite passes) | delegate (done) | none | NATIVO_EREDITATO |
| raw | L246-276 | Removed in 28ee83d | none | BuilderParityTest:50; EloquentBuilderTest:100-146 | delegate (done) | see 3.1 | NATIVO_EREDITATO |
| addUpdatedAtColumn | L319-337 | Removed in 28ee83d | none | BuilderParityTest:63; EloquentBuilderTest:147 | delegate (done) | see 3.2 | NATIVO_EREDITATO |
| getConnection | L339-342 (`: Connection`) | never overridden | none | n/a | inherit | none | NATIVO_EREDITATO |
| aggregate, search, vectorSearch, firstOrCreate, createOrFirst, ensureOrderForCursorPagination, `$passthru`, QueriesRelationships, QueriesRelationshipAggregates | L82-146, L279-311, L345-363, L51-75, L41-42 | inherited | none | BuilderParityTest:75 (firstOrCreate/chunkById/paginate); AggregateParityTest | inherit | aggregates fail on package embedded relations (3.3) | NATIVO_EREDITATO |

#### State at the 67777f7 baseline: blob `1c0fd99d9321` (historical, already superseded)

| Symbol (old lines) | Up | Divergence found | Cls at 67777f7 |
|---|---|---|---|
| update L30-44 | L153-166 | Same logic plus a redundant `method_exists(..., 'getParentRelation')` | DIVERGENZA_STATICA |
| insert L52-63 / insertGetId L72-83 / delete L88-99 | L169-211 | `EloquentBuilder::insert()` is not a method of Illuminate's Eloquent Builder: the static-style call falls into `__call` -> `$passthru` (Laravel Builder.php:2349-2350). Equivalent to upstream `parent::insert` | DIVERGENZA_STATICA |
| increment/decrement L109-171 | L213-237 | As above, plus `EloquentBuilder::increment` (equivalent to `parent::`) | VALORE_CUSTOM |
| raw L179-207 | L246-276 | See 3.1: `instanceof Cursor`, no type map, no `prepareFieldsForResult`, no `MongoDB\BSON\Document`, no `id`-keyed arrays, plus a LogicException guard | ADAPTER (wrong) -> fixed by delegation |
| addUpdatedAtColumn L218-231 | L319-337 | No `isset($values['$set'][$column])` guard; `array_merge` instead of `array_replace` | DIVERGENZA (bug) -> fixed |

### 2.3 `src/Eloquent/EmbedsRelationships.php`: blob `04d7e18be8f6`

| Symbol | Up | What / why | Difference needed | Test | Dec | Consequences | Cls |
|---|---|---|---|---|---|---|---|
| `embedsMany()` L23-51 | `EmbedsRelations::embedsMany` L31-53 | Same algorithm (backtrace name, default localKey/foreignKey) but builds `OfflineAgency\...\EmbedsMany` | Only the relation class differs. The phpdoc return `\MongoDB\Laravel\Relations\EmbedsMany<...>\|EmbedsMany` is wrong: it never returns the upstream class | EmbeddedRelationsTest; AggregateParityTest:30 | **decision point**: deprecate the custom relations and move to the upstream ones, or adapt (section 3.3, risks R1/R2) | Public API: users who type-hint `MongoDB\Laravel\Relations\EmbedsMany` get a TypeError. withCount/withSum etc. throw (VERIFIED). Persisted format bug through EmbedsMany (VERIFIED P1/P7/P8) | VALORE_CUSTOM (with DIVERGENZA) |
| `embedsOne()` L62-90 | L65-87 | idem with EmbedsOne | idem | Coverage/EloquentRelationsTest:58-89 | idem | idem | VALORE_CUSTOM |

### 2.4 `src/Relationships/EmbedsOneOrMany.php`: blob `8750a000358d` vs `Relations/EmbedsOneOrMany.php`

| Method (local lines) | Up lines | Difference | Impact / test | Dec | Cls |
|---|---|---|---|---|---|
| props `$query,$parent,$related` L40-55 | inherited from Relation | Redundant redeclaration (typing only) | none | remove if aligning | DIVERGENZA_STATICA |
| `__construct(Mongo Builder, Mongo Model, Mongo Model, ...)` L67-90 | L57-79 | (a) Does not call `Relation::__construct` (Laravel Relation.php:99-106); it sets `related` from the argument instead of `$query->getModel()`. (b) No `isDocumentModel` checks (upstream L59-65), but the parameters are narrower: `MongoDB\Laravel\Eloquent\Builder`/`Model` instead of Illuminate types, so a third-party DocumentModel-trait model (not extending `MongoDB\Laravel\Eloquent\Model`) gets a TypeError. (c) Order: the package **replaces the query with the parent relation query first and then calls addConstraints()**. Upstream adds the constraint to its own query (inside parent::__construct) and then replaces the query, which discards the constraint for nested relations. So a nested package relation has the extra `where(<path>.<parentKey> = key)` on the shared parent query | Nested embedded: EmbeddedRelationsTest (nested cases, STATIC: I did not check which ones). Nested `delete()` / `toBase()` usage NON_VERIFICATO | adapt (call parent ctor, widen types) | DIVERGENZA_STATICA |
| addConstraints L95-100 | L83-88 | same | | inherit | NATIVO (copy) |
| addEagerConstraints L105-108 | L92-95 | same | | | NATIVO (copy) |
| abstract performInsert/Update/Delete L115-130 | (concrete in subclasses) | Package declares them abstract | none | keep | VALORE_CUSTOM |
| match L135-148 | L99-110 | `method_exists` guard | eager loading VERIFIED working (P2 `with('items')` = [0,1] on both) | | NATIVO (copy) |
| get L156-162 | L112-116 | same | | | NATIVO (copy) |
| `count()` L169-177 | `count($columns='*'): int` L127-132 | Package has no `$columns` param (silently ignored) and no return type, but is null-safe. Upstream EmbedsMany never hits null (`getEmbedded() ?: []`, EmbedsMany.php:334-337). Upstream EmbedsOne would call `count(null)` → TypeError (STATIC; package EmbedsOne count not probed) | EloquentRelationsTest:28; P6 (both 0 for empty EmbedsMany) | adapt (add param) | VALORE_CUSTOM |
| save / saveMany / create L184-224 | L139-179 | same | | | NATIVO (copy) |
| createMany L232-243 | L186-195 | Skips non-array records (upstream: TypeError) | | keep | VALORE_CUSTOM |
| getIdsArrayFrom L251-268 | L204-221 | same | | | NATIVO (copy) |
| getEmbedded / setEmbedded L275-303 | L224-245 | setEmbedded returns void (upstream returns the parent model) | EmbedsMany::associate return value | | DIVERGENZA_STATICA |
| getForeignKeyValue L311-322 | L254-262 | Uses `getBaseQuery()->convertKey()` (public API Query\Builder.php:1224, not @internal) | | keep | NATIVO (copy) |
| toCollection L330-344 | L269-282 | Drops null models; **does not call eagerLoadRelations** (upstream L277-279) | Nested `with('items.x')` on embedded children not eager-loaded (NON_VERIFICATO) | adapt | DIVERGENZA_STATICA |
| toModel L352-373 | L291-312 | same (no return type) | | | NATIVO (copy) |
| getParentRelation L380-389 | L319-322 | same | | | NATIVO (copy) |
| getQuery L396-405 | L326-331 | clone, same | | | NATIVO (copy) |
| **getBaseQuery** L412-420 | upstream overrides **toBase** L335-340 | The package overrides `getBaseQuery()` (cloned) but not `toBase()`. `Relation::toBase()` (Relation.php:406-409) returns `$this->query->toBase()`, which is not cloned when there are no scopes, so callers of `$relation->toBase()` mutate the shared relation query. Upstream perform* use `toBase()` (clone) | NON_VERIFICATO at runtime | adapt (override toBase) | DIVERGENZA_STATICA |
| isNested L427-430 | L347-350 | `!=` vs `!==` | | | NATIVO (copy) |
| getPathHierarchy / getQualifiedParentKeyName L438-459 | L359-379 | `instanceof self` guard: nesting a package relation under an upstream relation (or the reverse) is ignored | mixed nesting not supported | | DIVERGENZA_STATICA |
| getParentKey L466-469 | L386-389 | same | | | NATIVO (copy) |
| `getUpdateValues` L478-487 | L399-413 | Upstream recursively prefixes keys inside `$`-operators (`$inc`, `$set`...); the package does not | Only matters if dirty keys start with `$` (not in normal flows) | adapt (copy upstream) | DIVERGENZA_STATICA |
| getQualifiedForeignKeyName L494-497 | L420-423 | same | | | NATIVO (copy) |
| whereInMethod | L432-436 returns `'whereIn'` | Not overridden; `Relation::whereInMethod` (Relation.php:506-512) may return `whereIntegerInRaw` for int keys | Irrelevant: eager constraints are a no-op | none | NON_APPLICABILE |

### 2.5 `src/Relationships/EmbedsMany.php`: blob `a26262e2d1f8` vs `Relations/EmbedsMany.php`

| Method (local) | Up | Difference | Evidence / test | Dec | Cls |
|---|---|---|---|---|---|
| initRelation L21-28 | L33-40 | same | | | NATIVO (copy) |
| getResults L35-41 | L43-46 | uses `getEmbeddedAsIndexed()` (accepts object-shaped legacy data) | | keep the read tolerance | VALORE_CUSTOM |
| performInsert L48-73 | L53-76 | `getBaseQuery()->push(..., true)` vs `toBase()->push` (both cloned) | EmbeddedRelationsTest | | NATIVO (copy) |
| performUpdate(Model, array $values=[]) L81-105 | performUpdate(Model) L83-107 | extra ignored param | | | NATIVO (copy) |
| performDelete L112-133 | L114-133 | Returns 1/0. Upstream returns `parent->save()` (bool) when nested, else the pull count (int) | return type change | keep | VALORE_CUSTOM |
| associate L140-148 | L140-147 | `getResults()->contains` vs `$this->contains` (via __call): same | | | NATIVO (copy) |
| **dissociate** L156-177 | L156-177 | No `array_key_exists($primaryKey)` guard (upstream L166), so a warning on records without a key. **Leaves holes in the keys** (see setEmbedded) | **P1 VERIFIED** | adapt | DIVERGENZA (bug) |
| destroy L185-205 | L186-203 | filter+in_array vs `only($ids)` | EmbeddedRelationsTest "embeds many destroy" | | DIVERGENZA_STATICA (equivalent) |
| delete() L212-222 | `delete($id=null): int` L212-224 | No `$id` param or guard | | adapt | DIVERGENZA_STATICA |
| detach L230-233 | L233-236 | same | | | NATIVO (copy) |
| attach L243-254 | L243-246 | Returns the model even when the save is cancelled | EloquentRelationsTest:16 | keep | VALORE_CUSTOM |
| associateNew L262-277 / associateExisting L285-305 | L255-296 | `isset` guard; returns the model (upstream returns the setEmbedded() result = parent) | | | DIVERGENZA_STATICA |
| paginate L314-338 | L307-331 | No `$total` param and no Closure `$perPage` (Laravel ≥ 11 signature) | | adapt | DIVERGENZA_STATICA |
| getEmbeddedAsIndexed L346-366 | (getEmbedded `?: []` L334-337) | Reads both list and object shapes | | keep | VALORE_CUSTOM |
| **setEmbedded** L375-387 | L340-347 `array_values($records)` | **Keeps the original keys** (`$records[(string)$key]`) instead of re-indexing. After dissociate/destroy the array is no longer a list and BSON-encodes as an **object** | **VERIFIED P1/P7/P8** (see 3.4) | **fix: use array_values** | DIVERGENZA (data-corrupting bug) |
| __call L396-406 | L350-357 | same | EloquentRelationsTest:37, :47 | | NATIVO (copy) |
| whereInMethod | L366-369 | not overridden | | | NON_APPLICABILE |

### 2.6 `src/Relationships/EmbedsOne.php`: blob `9368d5d3c9d0` vs `Relations/EmbedsOne.php`

| Method (local) | Up | Difference | Test | Dec | Cls |
|---|---|---|---|---|---|
| initRelation L19-26 / getResults L33-36 | L22-34 | same | EloquentRelationsTest:73 | | NATIVO (copy) |
| getEager L45-50 | L36-42 | Returns an empty collection when nothing is embedded. Upstream calls `toCollection([null])`, which yields `[null]` and then eagerLoadRelations (fix 5817cd3; upstream behaviour STATIC, not probed) | EloquentRelationsTest:58 | keep | VALORE_CUSTOM |
| performInsert L57-79 / performUpdate L87-105 | L49-96 | getBaseQuery vs toBase | | | NATIVO (copy) |
| performDelete(Model $model) L112-132 | performDelete() L103-121 | Required param (upstream none); returns 1/0 vs bool/int | EloquentRelationsTest:83, :89 | keep | VALORE_CUSTOM |
| associate / dissociate L139-156 | L128-141 | Return the model / null instead of the parent | | | DIVERGENZA_STATICA |
| delete() L163-171 | `delete($id=null): int` L152-157 | Returns 0 when empty; no `$id` param | EloquentRelationsTest:83 | | VALORE_CUSTOM |
| whereInMethod | L166-169 | not overridden | | | NON_APPLICABILE |

### 2.7 `src/Extensions/MongoCollection.php`: blob `2ecfea7c7d3d`
`hasItem`, `moveFirst`, `exist` on top of `Illuminate\Database\Eloquent\Collection`. There is no upstream equivalent. Returned by `ModelAdditionalMethod::newCollection` for MDModel. Test: tests/MongoCollectionTest.php. Decision: keep. Cls: **VALORE_CUSTOM**. Note: it compares `ref_id == $id` loosely (ObjectId vs string works through `__toString`, STATIC).

### 2.8 `src/Http/Models/*`
| File (blob) | Content | Up | Cls / Dec |
|---|---|---|---|
| MDModel.php `9dbffb5745c5` | Base model with the traits, `connection='mongodb'`, `$dates` (dead on Laravel ≥10, see 2.1), getId/getCollection/getRandom | none | VALORE_CUSTOM. Keep, but remove or document the dead `$dates`. Test: MDModelTest |
| MSModel.php `f36a3761ca9f` | Illuminate Model on `mysql` | none | NON_APPLICABILE (unrelated to upstream; candidate to deprecate) |
| DefaultMini.php `33a902c59ce8` | `$primaryKey='ref_id'` | none. Upstream `DocumentModel::setAttribute` converts only `id`/`_id` (L274-280), so ref_id is not converted or checked by upstream. The package validates it with `SyncHelper::assertSafeRefIds` | VALORE_CUSTOM |
| DefaultGeo.php `9252099a6ed3` | `$primaryKey='cadastralCode'` | none | VALORE_CUSTOM |

### 2.9 `src/Traits/*`
| Symbol | Up | Notes | Test | Dec | Cls |
|---|---|---|---|---|---|
| MainMongoTrait (`174760911f81`): boot (whenBooted observe), storeWithSync, updateWithSync, destroyWithSync, validateRelationshipRequest, getters/setters | none | Custom model events `storeWithSync`/`updateWithSync`/`destroyWithSync` through `fireModelEvent` | StoreWIthSyncTest, UpdateWithSyncTest, MainMongoTraitTest, Coverage/TraitsMainMongoTest, Compatibility/UntrustedIdTest | keep | VALORE_CUSTOM |
| PlainMongoTrait (`fc5c9b8fe167`) storeEditAllItems | none | Writes `UTCDateTime` directly for `is-md` fields | MongoSyncTraitTest | keep | VALORE_CUSTOM |
| RelationshipMongoTrait (`292b90b5fe96`) updateRelationWithSync L148-172, removeRelationWithSync L222-244, targetQuery L192-201, touchedTimestamp L179-187 | Uses the public `Query\Builder::update` L808-821, then `performUpdate` L1196-1215 (`inheritConnectionOptions` gives the session) | Already decided. Joins transactions (VERIFIED with TransactionTest on rs0). **Consequence:** target writes **bypass Eloquent events** on the target model (no saving/updated/saved), and `updated_at` is set manually. Mini attributes go through `Grammar::prepareFieldsForQuery(root=false)`; with `rename_embedded_id_field=true` (default, Connection.php:88) any `id` key inside a mini model is stored as `_id` (STATIC, no fixture with an `id` key in a mini model) | TargetSyncTest, Compatibility/TargetTouchTest:7, TransactionTest (rs only), Coverage/TraitsTargetSyncTest | keep | ADAPTER_NECESSARIO |
| RelationshipMongoTrait processAllRelationships / processEmbed* / batchHandleSubTarget / handleSubTarget (@deprecated) / processReferencedRelationship (applies a `ReferencedWritePlan`) / deleteReferencedObj | none | `processReferencedRelationship` L403-409 uses `$attributes['id']` from the JSON payload without validation (only `ref_id` is checked). P4 VERIFIED: `find(json_decode('{"$ne":null}'))` throws `Error: Object of class stdClass could not be converted to string` on native and package alike, so it is a 500, not an operator injection | Coverage/TraitsReferencedRelationshipsTest | keep; consider validating `id` like ref_id | VALORE_CUSTOM |
| ModelAdditionalMethod (`db2dccbe2a97`) newCollection, getRandomRow (uses `raw()` + aggregate, now upstream raw), mini model builders | none | getRandomRow relies on upstream raw() returning a Collection for a CursorInterface (Builder.php:252-257) | Coverage/HelpersModelAdditionalMethodTest:32-44 | keep | VALORE_CUSTOM |
| Helper (`f76e5c916ced`) | none | option validation | HelperTest | keep | VALORE_CUSTOM |

### 2.10 `src/Helpers/SyncHelper.php`: blob `cdd944279339`
| Symbol | Up | Notes | Test | Cls |
|---|---|---|---|---|
| assertSafeRefIds L202-226 | `Query\Builder::assertKeyIsNotOperator` L1245-1268 is **@internal** (L1248) and only checks arrays (not stdClass) | Own implementation (does not call the @internal method): string/int/null only. Only validates JSON **lists**; a single JSON object payload is "left to the parser" (L198, L209-211) | UntrustedIdTest | VALORE_CUSTOM (keep) |
| idVariants L233-243 | `convertKey`/private `castKey` L1224-1243 | Returns both string and ObjectId for legacy data; independent of upstream | TargetSyncTest, BackgroundSyncTest:246 | VALORE_CUSTOM |
| refIds, sameId, ml/cl/isML/isMD/is_EM..., getRequestToBeSync etc. | none | pure helpers | Coverage/HelpersSyncHelperTest | VALORE_CUSTOM |

### 2.11 `src/Jobs/SyncTargetJob.php` (`fea5f0d9db89`) and `src/Observers/MongoAutoSyncObserver.php` (`7dd352e6f2d5`)
- No upstream equivalent. **VALORE_CUSTOM**, keep. Tests: BackgroundSyncTest (15 tests), Coverage/TraitsTargetSyncTest, TransactionTest:54.
- The job implements `ShouldQueue` (not `ShouldQueueAfterCommit`) and is dispatched from the `saved`/`deleted` observer hooks. Upstream ManagesTransactions notifies the `transactionsManager` (ManagesTransactions.php begin/commit/rollback at L57-88 of the file), so `after_commit` works. VERIFIED: TransactionTest:54 passes on rs0 **only because it sets `queue.connections.sync.after_commit=true`**. `grep after_commit README.md UPGRADE-4.0.md CHANGELOG.md config` finds nothing. Undocumented requirement → risk R5.

### 2.12 `src/Console/*`
| File (blob) | Read | Up | Cls |
|---|---|---|---|
| DropCollection.php `421b16d4e220` | full | none (public Eloquent API: count/cursor) | VALORE_CUSTOM |
| SyncCollection.php `b8e57db14382` | full | none (chunk + SyncTargetJob) | VALORE_CUSTOM |
| BenchmarkCommand.php `673f2188fead` | full | none | VALORE_CUSTOM |
| AnalyseDatabase.php `c2bb5aad59b7` | L55-135 + grep only | none (count/chunk/find) | VALORE_CUSTOM (rest NON_VERIFICATO) |
| CheckModelConfig.php `a39823bc25c9` | grep only | none | NON_VERIFICATO |
| GenerateModelDocumentation.php `bb23d57809cd` | grep only | none | NON_VERIFICATO |
Tests: tests/Console/*, Coverage/Console*, DropCollectionTest, GenerateModelDocumentationTest (all pass).

### 2.13 Other files
| File | Read | Finding | Cls |
|---|---|---|---|
| src/MongoAutoSyncServiceProvider.php `ac1d8bc4d5ae` | full | Publishes config, binds 6 commands. Does not register MongoDBServiceProvider (tests add it in TestCase) | VALORE_CUSTOM |
| src/syncUtils.php `9f476d69a374` | full | Global functions guarded by `function_exists` (`cl`, `ml`, `isML`, `is_EM`...). Generic names can collide: a host app defining `ml()` first silently wins. `getFullUrlImgByKey`/`getSiteGeneralValueByKey` are stubs returning '' | VALORE_CUSTOM (consider deprecating) |
| config/laravel-mongo-auto-sync.php `752aa14d6c24` | full | No `after_commit`/transaction option; `fb_id` unrelated | VALORE_CUSTOM |
| composer.json `1b8bdf7ed626` | full | `mongodb/laravel-mongodb ^5.11`, `mongodb/mongodb ^2.4.2`, `ext-mongodb ^2.4`, `illuminate/* ^12.69\|^13.30`, PHP ^8.4. Autoload psr-4 `OfflineAgency\MongoAutoSync\` → src/, files src/syncUtils.php | NON_APPLICABILE |
| Dockerfile `7780c2911201` | full | `pecl install mongodb` unpinned (latest ext), PHP 8.4 | NON_APPLICABILE |
| docker-compose.yml `7efd28c8460c` | full | **Standalone** mongo:8.0, so TransactionTest is skipped in docker | NON_APPLICABILE |
| .github/workflows/build-ci.yml `2b8b73a671c3` | job/step names only (grep) | tests matrix (8.4/8.5 x L12/13 x Mongo 7/8), `reference` (exact laravel-mongodb 5.11.0 + mongodb 2.5.0), `lowest` (ext 2.4.0, --prefer-lowest), `replica-set` (rs0 on 27018). Step bodies only partly read | partially NON_VERIFICATO |
| .github/workflows/release.yml `e63ce6f12da7` | full | conventional-commit auto release | NON_APPLICABILE |
| README.md `a47b56b72a96`, CHANGELOG.md `30270b6d9c8e`, UPGRADE-4.0.md `bee7d9dc8859` | grep only | **Doc drift**: CHANGELOG:23 and UPGRADE:9 say `laravel-mongodb ^5.2`, UPGRADE:8 says Laravel `^12.8 \| ^13.0`, but composer requires ^5.11 / ^12.69\|^13.30. UPGRADE:17 links the `/current/` upstream guide (not pinned to 5.11). No mention of after_commit, aggregates on embedded relations, or the increment event difference | DIVERGENZA_STATICA (docs); remainder NON_VERIFICATO |
| phpunit.xml.dist, phpstan.neon (level max, src/ only), tests/TestCase.php, tests/Pest.php, tests/config/database.php | full | Tests default to MONGO_HOST=localhost / DB unittest | NON_APPLICABILE |
| src/Exceptions/*, docs/plans, docs/pr, docs/upgrade/extracted_methods.php, BENCHMARK.md, CONTRIBUTING.md, composer.lock (except installed.json), most tests/*.php bodies | **not read** | | NON_VERIFICATO |
| docs/compatibility/ | does not exist yet, although TransactionTest's skip message refers to `docs/compatibility/test-matrix.md` | | NON_VERIFICATO |

**tests/ structure** (listing VERIFIED, bodies mostly not read): `tests/*.php` (legacy feature tests: EmbeddedRelationsTest 37 tests, StoreWIthSyncTest, UpdateWithSyncTest, TargetSyncTest, BackgroundSyncTest...), `tests/Coverage/` (coverage-driven unit/integration), `tests/Compatibility/` (differential native-vs-package: Aggregate/ArrayCast/Builder/ModelParity, TargetTouch, Transaction, UntrustedId), `tests/Console/`, `tests/Models/` (fixtures incl. `Cmp*` native/package twins), `tests/ConsoleFixtures/`, `tests/BrokenModels/`, `tests/config/`.

---

## 3. Evidence on the five requested points

### 3.1 `raw()`: package (67777f7) vs upstream 5.11.0

Upstream `vendor/mongodb/laravel-mongodb/src/Eloquent/Builder.php`:
```
246    public function raw($value = null)
249        $results = $this->query->raw($value);
252        if ($results instanceof CursorInterface) {
253            $results->setTypeMap(['root' => 'array', 'document' => 'array', 'array' => 'array']);
254            $results = array_map(fn ($document) => $this->query->getGrammar()->prepareFieldsForResult($document), iterator_to_array($results));
256            return $this->model->hydrate($results);
260        if (is_object($results) && (property_exists($results, '_id') || property_exists($results, 'id'))) {
261            $results = (array) match (true) {
262                $results instanceof BSONDocument => $results->getArrayCopy(),
263                $results instanceof Document => $results->toPHP(['root' => 'array', 'document' => 'array', 'array' => 'array']),
264                default => $results,
269        if (is_array($results) && (array_key_exists('_id', $results) || array_key_exists('id', $results))) {
270            $results = $this->query->getGrammar()->prepareFieldsForResult($results);
272            return $this->model->newFromBuilder($results);
275        return $results;
```
Package at 67777f7 (`git show 67777f7:src/Eloquent/Builder.php`):
```
183        if (! $query instanceof QueryBuilder) { throw new LogicException('raw() requires a MongoDB query builder, ...
186        $results = $query->raw($expression);
189        if ($results instanceof Cursor) {                       // MongoDB\Driver\Cursor (concrete), not CursorInterface
190            $results = iterator_to_array($results, false);       // no setTypeMap: BSONDocument rows
193            return $this->model->hydrate($results);              // no prepareFieldsForResult: "_id" kept, UTCDateTime kept
195        elseif ($results instanceof BSONDocument) {               // MongoDB\BSON\Document not handled
199            return $this->model->newFromBuilder((array) $results);
201        elseif (is_array($results) && array_key_exists('_id', $results)) {   // 'id'-only arrays not handled
206        return $results;                                         // scalars: same as upstream
```
Differences: (a) `Cursor` vs `CursorInterface`: a `MongoDB\Model\CodecCursor` or other non-Driver cursor would not be hydrated. (b) No type map: nested values stay BSONDocument/BSONArray. (c) No `prepareFieldsForResult` (Grammar.php:119-160): `_id` is not aliased to `id` and UTCDateTime is not converted to Carbon. (d) `BSON\Document` and `id`-keyed arrays are not recognised. (e) Scalars are the same.

At e7374dd the override is gone. VERIFIED: `BuilderParityTest:50` compares the native and package results for cursor/aggregate/findOne/array/scalar and passes. The commit message of 28ee83d records that the package models "came back with an `_id` attribute". **Cls now NATIVO_EREDITATO.** Observable public change from 67777f7: `getAttributes()` of raw-hydrated models now has `id` instead of `_id`, and dates are Carbon. This is a public behaviour change to put in the changelog.

### 3.2 increment/decrement and addUpdatedAtColumn

- Upstream embedded path: `Builder.php:218-221` `return $this->update(array_merge([$column => $this->model->{$column}], $extra));`, then `update()` L158-162 `$relation->performUpdate($this->model, $values); return 1;`. performUpdate ignores `$values` and uses `getDirty()` (EmbedsMany.php:95).
- Package embedded path (e7374dd `Builder.php:27-46`): null-and-sync-original trick, then `$this->model->update([$column => $value])`, i.e. `Model::update` → `fill` + `save`, which fires saving/updating/updated/saved and calls `updateTimestamps()`.
- Model-level flow (Laravel Model.php:1134-1162): the attribute is already incremented and `$extra` force-filled before the builder call. So in both implementations `$amount` is applied by the **model**, not the builder.
- VERIFIED P3:

| | native | package |
|---|---|---|
| return of `$child->increment('n',4,['x'=>1])` | 1 | 1 |
| stored n / x | 5 / 1 | 5 / 1 |
| events on the item class | updating, updated | updating, saving, updating, updated, saved, updated |
| embedded updated_at changed | false | true |
| `$child->newQuery()->increment('n',10)` | returns 1, n stays 5 (amount ignored) | returns 1, n stays 5 (amount ignored) |

- addUpdatedAtColumn upstream `Builder.php:319-337`: it adds top-level `updated_at` unless `isset($values['$set'][$column])` (L327-329) and uses `array_replace`. `Query\Builder::update` (L808-821) then moves non-`$` keys into `$set`. So yes, upstream ends up setting `updated_at` **inside `$set`**, and an explicit `$set.updated_at` is respected.
- Package at 67777f7 had no guard: `['updated_at'=>fresh, '$set'=>['updated_at'=>X]]` → `$values['$set']['updated_at'] = fresh` overwrote X. That is the conflict. Fixed by deletion in 28ee83d. VERIFIED by `BuilderParityTest:63` (native and package).
- Top-level `increment()` delegates to the parent → Illuminate `Builder::increment` (Laravel Builder.php:1382-1387) → `addUpdatedAtColumn($extra)` → Query `increment` puts `$extra` in `$set` (Query/Builder.php:874-878). Same for both. VERIFIED by EloquentBuilderTest:53.

### 3.3 Upstream `instanceof` on embedded relations: complete list

`grep -rn "EmbedsOneOrMany\|EmbedsMany\|EmbedsOne" vendor/mongodb/laravel-mongodb/src` (excluding Relations/Embeds*):

| Location | Check | Consequence for package relations (`instanceof` false) | Evidence |
|---|---|---|---|
| Eloquent/EmbedsRelations.php:8-9, 52, 86 | construction only | Overridden by the package trait | STATIC |
| Helpers/QueriesRelationshipAggregates.php:130 `assertEmbeddedConstraintsSupported` | `! $relation instanceof EmbedsOneOrMany` → return | The package relation skips the embedded-constraint assertion and is rejected later at L291 | STATIC |
| QueriesRelationshipAggregates.php:167 `hydrateAggregate` | embedded → aggregate in memory | Never reached for the package | STATIC |
| QueriesRelationshipAggregates.php:291 `assertAggregateRelationSupported` | embedded → allowed; otherwise must be HasOneOrMany/BelongsToMany/BelongsTo | **withCount/withExists/withSum/withAvg/withMin/withMax on package embedded relations throw `LogicException('EmbedsMany is not supported for relation aggregates.')`** (L310-313). Native ones work | VERIFIED `AggregateParityTest:21`, `:30` |

Note: `QueriesRelationshipAggregates` is marked **@internal** (L45), so its behaviour is not a contract either.

Other paths, which have no instanceof on embeds:
- `QueriesRelationships::has()` → `addHybridHas` → `assertHybridRelationSupported` (QueriesRelationships.php:141-152) rejects **both**. VERIFIED P2: native and package both throw `LogicException: EmbedsMany is not supported for hybrid query constraints.`, so this is parity, not a regression.
- Eager loading (`with('items')`): VERIFIED P2, `[0,1]` on both.
- `Eloquent\Builder::update/insert/insertGetId/delete/increment/decrement`: duck-typed `getParentRelation()` → `performX`. Works (full suite).
- `DocumentModel::getRelationsWithoutParent` (L681-691) calls `$parentRelation->getQualifiedForeignKeyName()`, which the package defines (EmbedsOneOrMany.php:494). OK.
- `HybridRelations`: no reference to embeds (grep).

### 3.4 Persisted-format bug in package EmbedsMany (found during point 3)

`EmbedsMany::setEmbedded` (package L375-387) keeps keys; upstream (L340-347) uses `array_values`. VERIFIED:

| Probe | native | package |
|---|---|---|
| P1: `dissociate($b)` then `$parent->save()`, `$type` of `items` | in-memory list = true, stored **array** | in-memory list = false, stored **object** |
| P8: `$child->delete()` (pull + in-memory dissociate), then `$parent->name='x'; $parent->save()` (`items` is dirty) | array | **object** |
| P7: after P1, `$parent->items()->create([...])` | ok, count=3 | `BulkWriteException: Cannot apply $addToSet to non-array field. Field named 'items' has non-array type object` |
| P7: `where('items.k','c')->count()` | 1 | **0** |

No existing test covers this. The "embeds many destroy" test in EmbeddedRelationsTest creates again without saving the parent in between. Test to add: `tests/Compatibility/EmbeddedFormatParityTest.php`, which runs P1/P7/P8 for native and package and asserts `$type == 'array'` and that `create()` after the save succeeds.

### 3.5 "array" cast deprecation and storage of arrays

- Present at 5.11.0: `DocumentModel::isJsonCastable` L300-314 raises E_USER_DEPRECATED `The "array" cast on attribute "%s" ... stores values as a JSON-encoded string in MongoDB...`. STATIC, and VERIFIED by `tests/Compatibility/ArrayCastTest.php` (native and package both emit the deprecation and store `'["a","b"]'`).
- The package itself: `grep -rnF '$casts' src` → **no hits**. `function casts` → no hits. Package models store arrays **natively** (raw attribute assignment, `tempEM` lists, `SyncHelper::ml()` arrays; `is-array` items in `castValueToBeSaved` return PHP arrays, ModelAdditionalMethod.php:217-218). Only the test fixtures declare casts: `tests/Models/CmpPackageModel.php:20` / `CmpNativeModel.php:20` (`decimal:2`, `datetime`) and `CmpPackageArrayCast.php:18` / `CmpNativeArrayCast.php:18` (`'tags' => 'array'`, which exists on purpose to test the deprecation). **Cls NATIVO_EREDITATO**: the package neither triggers nor suppresses the deprecation.

### 3.6 Upstream id helpers and public contracts

| Helper | Location @5.11.0 | Marked | Used by the package? | Contract? |
|---|---|---|---|---|
| `Query\Builder::convertKey($id)` | Query/Builder.php:1224-1229 (calls `assertKeyIsNotOperator` then private `castKey` L1232-1243) | public, no @internal | yes: `Relationships/EmbedsOneOrMany.php:321` | **Public**: safe to rely on |
| `Query\Builder::assertKeyIsNotOperator(mixed)` | L1245-1268 | **@internal** (L1248). Checks arrays only; a stdClass passes | no (the package has its own `SyncHelper::assertSafeRefIds`) | Not a contract: do not call |
| `aliasIdForQuery` / `aliasIdForResult` | **no equivalent at 5.11.0** (`grep -rn aliasIdFor vendor/.../src` returns nothing) | | no (grep in src/tests/config: none) | Replaced by `Query\Grammar::prepareFieldsForQuery` (Grammar.php:46-102) / `prepareFieldsForResult` (L119-160): public, documented "Users can override this method". The project memory note "aliasIdForResult _id→id rename" refers to an API that does not exist in 5.11.0 |
| `Connection::getRenameEmbeddedIdField()` / config `rename_embedded_id_field` | Connection.php:419 / :88 (default true) | public | indirectly (target writes and reads) | Public. Affects `id`↔`_id` inside embedded docs |
| `Concerns\ManagesTransactions` (`getSession`, `beginTransaction`, `commit`, `rollBack`, `transaction`) | Concerns/ManagesTransactions.php:16-17 **@internal** trait used by Connection | | The package uses none directly; it relies on public `Query\Builder::update` inheriting the session (`inheritConnectionOptions`, Query/Builder.php:1205) | `transaction/beginTransaction/commit/rollBack` are Laravel `ConnectionInterface` contracts. `getSession()` is not: avoid it |
| Id conversion in where | Query/Builder.php:1405-1414: `_id` → `convertKey` (operator check); `*._id` → `castKey` (no check) | private logic | `targetQuery()->whereIn('_id', idVariants)` | Behavioural, covered by UntrustedIdTest:94 |

---

## 4. Summary table

| Symbol | Classification | Decision |
|---|---|---|
| Eloquent\Model::$parentRelation / set/getParentRelation | DIVERGENZA_STATICA | deprecate → remove (major) |
| Eloquent\Model::setAttribute | VALORE_CUSTOM | keep (fix docblock: only dotted timestamp columns; `$dates` is dead) |
| Eloquent\Model::getAttribute (removed) | NATIVO_EREDITATO | delegate (done) |
| Eloquent\Model::newEloquentBuilder | ADAPTER_NECESSARIO | keep while Builder exists |
| Eloquent\Builder::increment/decrement (embedded) | VALORE_CUSTOM | keep + document events/updated_at; or delegate in the next major |
| Eloquent\Builder update/insert/insertGetId/delete/raw/addUpdatedAtColumn | NATIVO_EREDITATO (since 28ee83d) | delegate (done) |
| Eloquent\Builder getConnection, firstOrCreate, aggregates... | NATIVO_EREDITATO | inherit |
| EmbedsRelationships::embedsMany/embedsOne | VALORE_CUSTOM (+DIVERGENZA) | decision needed: migrate to the upstream relations (subclass them) or keep the parallel copy |
| Relationships\EmbedsOneOrMany (ctor, toBase, toCollection, getUpdateValues, getPathHierarchy) | DIVERGENZA_STATICA | adapt (or subclass upstream) |
| Relationships\EmbedsOneOrMany count/createMany | VALORE_CUSTOM | keep |
| Relationships\EmbedsMany::setEmbedded / dissociate | DIVERGENZA (data bug, VERIFIED) | **fix now: array_values + key guard** |
| Relationships\EmbedsMany getEmbeddedAsIndexed / attach / performDelete ints | VALORE_CUSTOM | keep |
| Relationships\EmbedsMany paginate/delete signatures | DIVERGENZA_STATICA | adapt |
| Relationships\EmbedsOne getEager / delete / performDelete(Model) | VALORE_CUSTOM | keep |
| Extensions\MongoCollection | VALORE_CUSTOM | keep |
| Http\Models\MDModel / DefaultMini / DefaultGeo | VALORE_CUSTOM | keep (remove dead `$dates`) |
| Http\Models\MSModel | NON_APPLICABILE | deprecate candidate |
| Traits\RelationshipMongoTrait target writes (Query\Builder) | ADAPTER_NECESSARIO | keep (done) |
| Traits\* (Main, Plain, ModelAdditionalMethod, Helper, rest of Relationship) | VALORE_CUSTOM | keep |
| Helpers\SyncHelper (assertSafeRefIds, idVariants...) | VALORE_CUSTOM | keep |
| Jobs\SyncTargetJob, Observers\MongoAutoSyncObserver | VALORE_CUSTOM | keep + document after_commit |
| Console\Drop/Sync/Benchmark/Analyse | VALORE_CUSTOM | keep |
| Console\CheckModelConfig, GenerateModelDocumentation | NON_VERIFICATO | read before claiming |
| ServiceProvider, config, syncUtils | VALORE_CUSTOM | keep (syncUtils: consider deprecating generic global names) |
| composer.json, Dockerfile, docker-compose, release.yml, phpunit/phpstan | NON_APPLICABILE | n/a |
| build-ci.yml (new jobs) | NON_VERIFICATO (partially read) | review |
| README/CHANGELOG/UPGRADE | DIVERGENZA_STATICA (version drift); otherwise NON_VERIFICATO | update |
| "array" cast | NATIVO_EREDITATO | n/a (the package uses no casts) |
| convertKey (public) / assertKeyIsNotOperator (@internal) / prepareFieldsFor* (public) / ManagesTransactions (@internal) | contract notes, see 3.6 | rely only on the public ones |

## 5. Top risks

1. **R1, data corruption (VERIFIED).** Package `EmbedsMany::setEmbedded` keeps sparse keys. After `dissociate()`, nested `performDelete()`, or `child->delete()` followed by any parent save, the embedded list is persisted as a BSON **object**. Later `create()/save()` on the relation then fails (`$addToSet to non-array field`), and `where('items.k', ...)` queries silently miss. Fix: `array_values` as upstream (EmbedsMany.php:340-347), plus a differential test. Existing data may need a migration. `getEmbeddedAsIndexed` already tolerates the object shape on read, which is why the suite does not catch it.
2. **R2, parallel relation hierarchy.** Package relations are not `MongoDB\Laravel\Relations\*`, so upstream features gated by `instanceof` (relation aggregates, VERIFIED) are unavailable, and future upstream fixes in Relations (getUpdateValues operators, toBase cloning, eager loading in toCollection, ctor checks) are not inherited. The phpdoc return types of embedsMany/embedsOne claim the upstream classes.
3. **R3, embedded increment semantics (VERIFIED).** It fires saving/saved and an extra updating, and touches the embedded `updated_at`, unlike native. With background sync enabled, `saved` on an MDModel can dispatch SyncTargetJob. This is currently enforced by BuilderParityTest:100 (`$saves === 2`), so it is a de-facto contract and should be decided explicitly.
4. **R4, nested-relation query construction differs** (constraint added to the shared parent query; `toBase()` not cloned). NON_VERIFICATO at runtime and not covered by a differential test.
5. **R5, background sync and transactions.** Jobs run before commit unless the queue connection sets `after_commit` (only the test sets it). The requirement is undocumented. Transaction tests only run with a replica set (docker-compose is standalone; CI now has a `replica-set` job, not fully read).
6. **R6, public behaviour change from 28ee83d.** raw()-hydrated models now expose `id` (not `_id`) and Carbon dates. CHANGELOG/UPGRADE also still say `^5.2` and Laravel `^12.8|^13.0`.
7. **R7, dead `$dates` configuration** in MDModel/User (Laravel ≥ 10 ignores it). Users expecting `$dates` conversion for dotted non-timestamp fields get none.
8. **R8, unvalidated `id` in referenced-relationship payloads** (`processReferencedRelationship`). Not injectable through `find()` (VERIFIED P4: Error/500 on both native and package), but it is unvalidated input that reaches queries.

## 6. Updates after e7374dd (branch A)

Sections 0-5 describe commit `e7374dd`. Changes made afterwards on `fix/upstream-compatibility`:

| Item | Change | Evidence |
| --- | --- | --- |
| R1 `Relationships\EmbedsMany::setEmbedded` | **fixed** in `b96d174`: records re-indexed as upstream (`array_values`); `EmbedsOneOrMany::setEmbedded` docblock now `array<array-key, mixed>\|null` | `tests/Compatibility/EmbeddedListTest.php` (delete and dissociate, native vs package) |
| `Eloquent\Model::setAttribute` docblock | corrected: the delta only applies to dotted timestamp columns (`getDates()`), `$dates` is not used by Laravel 12/13 | static, Laravel 13 `HasAttributes::getDates()` |
| R5 `after_commit` | documented in UPGRADE-4.0.md | `tests/Compatibility/TransactionTest.php` (replica set) |
| R6 `raw()` returns `id` and Carbon dates | documented in CHANGELOG/UPGRADE | `tests/Compatibility/BuilderParityTest.php` |
| R3 embedded increment events | kept (VALORE_CUSTOM), documented; delegation proposed in roadmap D-adapter | `BuilderParityTest` |
| R2 parallel relation hierarchy | not changed (hierarchy change = separate proposal) | roadmap D-adapter, `AggregateParityTest` |
| R7 dead `$dates` | not changed | roadmap R7 |
| R8 unvalidated referenced `id` | fixed: `ReferencedWritePlanner` (ownership-scoped lookup, string ids, duplicates) | #46 |

Files marked NON_VERIFICATO above that were read afterwards (by the branch author, not by the map's author):

| File | Upstream equivalent | Classification | Decision |
| --- | --- | --- | --- |
| `src/Console/CheckModelConfig.php` | none | VALORE_CUSTOM | keep (exit 1 on invalid configuration, sub-namespaces since `bee0ba9`) |
| `src/Console/GenerateModelDocumentation.php` | none | VALORE_CUSTOM | keep |
| `src/Console/AnalyseDatabase.php` | none | VALORE_CUSTOM | rewritten for #43 on branch C (`fix/43-analysis-command`) |
| `src/Exceptions/*` | none (package exceptions extend `MongoAutoSyncException` -> `Exception`) | VALORE_CUSTOM | keep |
| `.github/workflows/build-ci.yml` | n/a | NON_APPLICABILE | jobs `lint`, `static-analysis`, `tests` (matrix), `reference`, `lowest`, `replica-set` written on this branch; actionlint clean |
| `docs/*`, `BENCHMARK.md`, `CONTRIBUTING.md` | n/a | NON_APPLICABILE | `BENCHMARK.md` holds an empty table (roadmap H) |

### Review findings fixed after `73dc688`

| Item | Change | Evidence |
| --- | --- | --- |
| `EmbedsMany::delete()` / `EmbedsOne::delete()` | restored the laravel-mongodb `$id` guard (`delete($id)` used to remove every item) | `tests/Compatibility/ReviewFindingsTest.php` |
| `Eloquent\Builder::increment()/decrement()` (embedded) | `forceFill` + `save` instead of `update` (non mass assignable columns were lost) | `ReviewFindingsTest` |
| `RelationshipMongoTrait::targetQuery()` | raw `_id` filter: `whereIn('_id')` converts a 24-hex string to an ObjectId and missed string `_id` targets | `ReviewFindingsTest` |
| `SyncHelper::assertSafeRefIds()` | rejects non-string payloads, checks JSON objects as well as lists, accepts only string/null ids, checks `id`/`_id` of referenced relationships | `SafeRefIdsTest`, `ReviewFindingsTest` |
| composer.json | `laravel/framework` required (the package uses `Illuminate\Foundation`); `mongodb/mongodb` is not used directly: `conflict <2.4.2` | static (imports of `src/`) |
| laravel-mongodb 5.11 `ManagesTransactions::transaction()` | upstream: after a rolled-back closure transaction `transactionLevel()` stays 1 and its `afterCommit` callbacks run at the next commit (reproduced on rs0) | documented in UPGRADE-4.0.md; to be reported upstream |
