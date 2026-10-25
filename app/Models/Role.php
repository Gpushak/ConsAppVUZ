<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['slug', 'name'])]
class Role extends Model
{
    public const STUDENT = 'student';

    public const TEACHER = 'teacher';

    public const ADMIN = 'admin';

    public static function idFor(string $slug): int
    {
        return (int) static::query()->where('slug', $slug)->value('id');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
