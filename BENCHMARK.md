# Benchmarks

You can use the provided command to benchmark your MongoDB models.

```bash
php artisan mongo-sync:benchmark "App\Models\Article" 1000
```

## Comparison MongoDB vs MySQL

This section is a placeholder for your own benchmark results.

| Operation | MongoDB (ms) | MySQL (ms) | Notes |
|-----------|--------------|------------|-------|
| Insert    |              |            |       |
| Read      |              |            |       |
| Update    |              |            |       |
| Delete    |              |            |       |

## Examples

### Write Heavy
MongoDB tends to be faster for write-heavy operations involving nested documents due to denormalization (single document write vs multiple table inserts).

### Read Heavy
Reads are extremely fast in MongoDB when fetching a document with all its related data embedded (no joins).

