<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    // ponytail: hardcoded threshold, move to config when it needs to vary
    public const LOW_STOCK_THRESHOLD = 10;

    // Cached list responses are keyed by this version; any write swaps it, so every old entry is skipped at once.
    // ponytail: version key instead of cache tags, because the database/file stores do not support tags
    private const CACHE_VERSION_KEY = 'products:cache-version';

    protected $fillable = ['category_id', 'name', 'price', 'stock'];

    protected function casts(): array
    {
        return ['price' => 'decimal:2', 'stock' => 'integer'];
    }

    protected static function booted(): void
    {
        // afterCommit: inside the controller transaction this runs after suppliers are synced too
        foreach (['saved', 'deleted', 'restored', 'forceDeleted'] as $event) {
            static::$event(fn () => DB::afterCommit(fn () => static::flushCache()));
        }
    }

    public static function cacheVersion(): string
    {
        return Cache::rememberForever(self::CACHE_VERSION_KEY, fn () => (string) Str::uuid());
    }

    public static function flushCache(): void
    {
        Cache::forever(self::CACHE_VERSION_KEY, (string) Str::uuid());
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function suppliers(): BelongsToMany
    {
        return $this->belongsToMany(Supplier::class)->withTimestamps();
    }

    /** Mutator: collapse stray whitespace so "  Big   Hammer " is stored as "Big Hammer". */
    protected function name(): Attribute
    {
        return Attribute::make(set: fn (string $value) => Str::squish($value));
    }

    /** Accessor: out_of_stock / low_stock / in_stock, derived from stock. */
    protected function stockStatus(): Attribute
    {
        return Attribute::make(get: fn () => match (true) {
            $this->stock === 0 => 'out_of_stock',
            $this->stock <= self::LOW_STOCK_THRESHOLD => 'low_stock',
            default => 'in_stock',
        });
    }

    /** Scope: apply validated list filters; absent keys are ignored. */
    public function scopeFilter(Builder $query, array $filters): Builder
    {
        return $query
            ->when($filters['category_id'] ?? null, fn ($q, $v) => $q->where('category_id', $v))
            ->when(isset($filters['min_price']), fn ($q) => $q->where('price', '>=', $filters['min_price']))
            ->when(isset($filters['max_price']), fn ($q) => $q->where('price', '<=', $filters['max_price']))
            ->when(isset($filters['min_stock']), fn ($q) => $q->where('stock', '>=', $filters['min_stock']))
            ->when(isset($filters['max_stock']), fn ($q) => $q->where('stock', '<=', $filters['max_stock']))
            ->when($filters['stock_status'] ?? null, fn ($q, $status) => match ($status) {
                'out_of_stock' => $q->where('stock', 0),
                'low_stock' => $q->whereBetween('stock', [1, self::LOW_STOCK_THRESHOLD]),
                'in_stock' => $q->where('stock', '>', self::LOW_STOCK_THRESHOLD),
            });
    }
}
