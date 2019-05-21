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
}
