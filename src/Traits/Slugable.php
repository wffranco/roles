<?php

namespace Wffranco\Roles\Traits;

use Wffranco\Helpers\Str;

trait Slugable
{
    /**
     * Set slug attribute.
     *
     * @param string $value
     * @return void
     */
    public function setSlugAttribute($value)
    {
        $this->attributes['slug'] = Str::dot($value, config('roles.separator', '.'));
    }

    public static function find($slug)
    {
        if (!is_numeric($slug) && is_string($slug)) {
            return static::where('slug', Str::dot($slug))->first();
        }

        return parent::find($slug);
    }
}
