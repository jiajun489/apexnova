<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    use HasFactory;

    protected $fillable = ['name'];

    protected static function booted(): void
    {
        // embedded in cached product responses
        static::saved(fn () => Product::flushCache());
        static::deleted(fn () => Product::flushCache());
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
