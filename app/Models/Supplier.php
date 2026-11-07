<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Supplier extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'email'];

    protected static function booted(): void
    {
        // embedded in cached product responses
        static::saved(fn () => Product::flushCache());
        static::deleted(fn () => Product::flushCache());
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class)->withTimestamps();
    }
}
