<?php

namespace Wffranco\Roles\Traits;

use Wffranco\Helpers\Str;
use Wffranco\Helpers\AndOr;
use Wffranco\Roles\Models\Role;
use Wffranco\Roles\Models\Permission;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

trait HasRoleAndPermission
{
    /**
     * Property for caching roles.
     *
     * @var \Illuminate\Database\Eloquent\Collection|null
     */
    protected $roles;

    /**
     * Property for caching permissions.
     *
     * @var \Illuminate\Database\Eloquent\Collection|null
     */
    protected $permissions;

    /**
     * User belongs to many roles.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function roles()
    {
        return $this->belongsToMany(config('roles.models.role'))->withTimestamps();
    }

    /**
     * Get all roles as collection.
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getRoles()
    {
        return $this->roles ?: $this->roles = $this->roles()->get();
    }

    /**
     * Check if the user has a role or roles.
     *
     * @param int|string|array $roles
     * @param bool $all
     * @return bool
     */
    public function is($roles)
    {
        if ($this->isPretendEnabled()) {
            return $this->pretend('is');
        }

        return AndOr::validate($roles, function($role) {
            return $this->hasRole(Str::dot($role, config('roles.separator', '.')));
        });
    }

    /**
     * Check if the user has role.
     *
     * @param int|string $role
     * @return bool
     */
    public function hasRole($role)
    {
        $role instanceof Role or $role = Role::find($role);

        return $this->getRoles()->contains($role);
    }

    /**
     * Attach role to a user.
     *
     * @param int|\Wffranco\Roles\Models\Role $role
     * @return null|bool
     */
    public function attachRole($role)
    {
        return !$this->getRoles()->contains($role) ? $this->roles()->attach($role) : true;
    }

    /**
     * Detach role from a user.
     *
     * @param int|\Wffranco\Roles\Models\Role $role
     * @return int
     */
    public function detachRole($role)
    {
        $this->roles = null;

        return $this->roles()->detach($role);
    }

    /**
     * Detach all roles from a user.
     *
     * @return int
     */
    public function detachAllRoles()
    {
        $this->roles = null;

        return $this->roles()->detach();
    }

    /**
     * Get role level of a user.
     *
     * @return int
     */
    public function level()
    {
        return ($role = $this->getRoles()->sortByDesc('level')->first()) ? $role->level : 0;
    }

    /**
     * Get all permissions from roles.
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function rolePermissions()
    {
        $permissionModel = app(config('roles.models.permission'));

        if (!$permissionModel instanceof Model) {
            throw new InvalidArgumentException('[roles.models.permission] must be an instance of \Illuminate\Database\Eloquent\Model');
        }

        return $permissionModel::select(['permissions.*', 'permission_role.created_at as pivot_created_at', 'permission_role.updated_at as pivot_updated_at'])
            ->join('permission_role', 'permission_role.permission_id', '=', 'permissions.id')
            ->join('roles', 'roles.id', '=', 'permission_role.role_id')
            ->whereIn('roles.id', $this->getRoles()->pluck('id')->toArray())
            ->orWhere('roles.level', '<', $this->level())
            ->groupBy(['permissions.id', 'pivot_created_at', 'pivot_updated_at']);
    }

    /**
     * User belongs to many permissions.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function userPermissions()
    {
        return $this->belongsToMany(config('roles.models.permission'))->withTimestamps();
    }

    /**
     * Get all permissions as collection.
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getPermissions()
    {
        return $this->permissions ?: $this->permissions = $this->rolePermissions()->get()->merge($this->userPermissions()->get());
    }

    /**
     * Check if the user has a permission or permissions.
     *
     * @param int|string|array $permission
     * @param bool $all
     * @return bool
     */
    public function can($permissions, $more = false)
    {
        if (is_array($more)) return parent::can($permission, $more); //use original 'can' method

        if ($this->isPretendEnabled()) {
            return $this->pretend('can');
        }

        return AndOr::validate($permissions, function($permission) {
            return $this->hasPermission(Str::dot($permission, config('roles.separator', '.')));
        });
    }

    /**
     * Check if the user has a permission.
     *
     * @param int|string $permission
     * @return bool
     */
    public function hasPermission($permission)
    {
        $permission instanceof Permission or $permission = Permission::find($permission);

        return $this->getPermissions()->contains($permission);
    }

    /**
     * Check if the user is allowed to manipulate with entity.
     *
     * @param string $providedPermissions
     * @param \Illuminate\Database\Eloquent\Model $entity
     * @param bool $owner
     * @param string $ownerColumn
     * @return bool
     */
    public function allowed($providedPermissions, Model $entity, $owner = true, $ownerColumn = 'user_id')
    {
        if ($this->isPretendEnabled()) {
            return $this->pretend('allowed');
        }

        if ($owner === true && $entity->{$ownerColumn} == $this->id) {
            return true;
        }

        return AndOr::validate($providedPermissions, function($permission) use ($entity) {
            return $this->isAllowed(Str::dot($permission, config('roles.separator', '.')), $entity);
        });
    }

    /**
     * Check if the user is allowed to manipulate with provided entity.
     *
     * @param string $providedPermission
     * @param \Illuminate\Database\Eloquent\Model $entity
     * @return bool
     */
    protected function isAllowed($providedPermission, Model $entity)
    {
        foreach ($this->getPermissions() as $permission) {
            if (
                $permission->model != '' &&
                get_class($entity) == $permission->model &&
                ($permission->id == $providedPermission || $permission->slug === $providedPermission)
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Attach permission to a user.
     *
     * @param int|\Wffranco\Roles\Models\Permission $permission
     * @return null|bool
     */
    public function attachPermission($permission)
    {
        return $this->getPermissions()->contains($permission) ?: $this->userPermissions()->attach($permission);
    }

    /**
     * Detach permission from a user.
     *
     * @param int|\Wffranco\Roles\Models\Permission $permission
     * @return int
     */
    public function detachPermission($permission)
    {
        $this->permissions = null;

        return $this->userPermissions()->detach($permission);
    }

    /**
     * Detach all permissions from a user.
     *
     * @return int
     */
    public function detachAllPermissions()
    {
        $this->permissions = null;

        return $this->userPermissions()->detach();
    }

    /**
     * Check if pretend option is enabled.
     *
     * @return bool
     */
    private function isPretendEnabled()
    {
        return (bool) config('roles.pretend.enabled');
    }

    /**
     * Allows to pretend or simulate package behavior.
     *
     * @param string $option
     * @return bool
     */
    private function pretend($option)
    {
        return (bool) config('roles.pretend.options.' . $option);
    }

    /**
     * Check if the user has roles and/or permissions.
     *
     * @param int|string $permission
     * @return bool
     */
    public function has($rules)
    {
        if ($this->isPretendEnabled()) {
            return $this->pretend('has');
        }

        return AndOr::validate($rules, function($rule) {
            list($type, $value) = explode(':', $rule);
            $type = ['r' => 'role', 'p' => 'permission'][$type] ?? $type;
            return $this->{Str::camel('has.'.$type)}(Str::dot($value, config('roles.separator', '.')));
        });
    }


    /**
     * Handle dynamic method calls.
     *
     * @param string $method
     * @param array $parameters
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        if (starts_with($method, 'is')) {
            return $this->is(Str::dot(substr($method, 2), config('roles.separator', '.')));
        } elseif (starts_with($method, 'can')) {
            return $this->can(Str::dot(substr($method, 3), config('roles.separator', '.')));
        } elseif (starts_with($method, 'allowed')) {
            return $this->allowed(Str::dot(substr($method, 7), config('roles.separator', '.')), $parameters[0], (isset($parameters[1])) ? $parameters[1] : true, (isset($parameters[2])) ? $parameters[2] : 'user_id');
        }

        return parent::__call($method, $parameters);
    }
}
