# Roles And Permissions For Laravel 5

Package for handling roles and permissions in Laravel 5.8+.

- [Installation](#installation)
    - [Composer](#composer)
    - [Service Provider](#service-provider)
    - [Config File And Migrations](#config-file-and-migrations)
    - [HasRoleAndPermission Trait And Contract](#hasroleandpermission-trait-and-contract)
- [Usage](#usage)
    - [Levels](#levels)
    - [Creating Roles](#creating-roles)
    - [Attaching And Detaching Roles](#attaching-and-detaching-roles)
    - [Checking For Roles](#checking-for-roles)
    - [Creating Permissions](#creating-permissions)
    - [Attaching And Detaching Permissions](#attaching-and-detaching-permissions)
    - [Checking For Permissions](#checking-for-permissions)
    - [Join Checking Roles/Permissions](#join-checking-roles/permissions)
    - [Permissions Inheriting](#permissions-inheriting)
    - [Entity Check](#entity-check)
    - [Blade Extensions](#blade-extensions)
    - [Middleware](#middleware)
- [Config File](#config-file)
- [More Information](#more-information)
- [License](#license)

## Installation

Still in develop, but this package is easy to set up. Just follow a couple of steps.

### Composer

Pull this package in through Composer (file `composer.json`).

```js
{
    "require": {
        "php": ">=7.1.3",
        "laravel/framework": "5.8.*",
        "wffranco/roles": "~1.0",
    }
}
```

### Service Provider

Add the package to your application service providers in `config/app.php` file.

```php
'providers' => [
    ...

    /**
     * Third Party Service Providers...
     */
    Wffranco\Roles\RolesServiceProvider::class,

],
```

### Config File And Migrations

Publish the package config file and migrations to your application. Run these commands inside your terminal.

    php artisan vendor:publish --provider="Wffranco\Roles\RolesServiceProvider" --tag=config
    php artisan vendor:publish --provider="Wffranco\Roles\RolesServiceProvider" --tag=migrations

And also run migrations.

    php artisan migrate

> This uses the default users table which is in Laravel. You should already have the migration file for the users table available and migrated.

### HasRoleAndPermission Trait And Contract

Include `HasRoleAndPermission` trait and contract inside your `User` model.

```php
use Wffranco\Roles\Traits\HasRoleAndPermission;
use Wffranco\Roles\Contracts\HasRoleAndPermission as HasRoleAndPermissionContract;

class User extends Authenticatable implements HasRoleAndPermissionContract
{
    use Notifiable, HasRoleAndPermission;
```

And that's it!

## Usage

### Levels

When you are creating roles, there is optional parameter `level`. It is set to `1` by default, but you can overwrite it and then you can do something like this:

```php
if ($user->level() > 4) {
    //
}
```

> If user has multiple roles, method `level` returns the highest one.

`Level` has also big effect on inheriting permissions. About it later.

### Creating Roles

```php
use Wffranco\Roles\Models\Role;

$adminRole = Role::create([
    'name' => 'Admin',
    'slug' => 'admin',
    'description' => '', // optional
    'level' => 1, // optional, set to 1 by default
]);

$moderatorRole = Role::create([
    'name' => 'Forum Moderator',
    'slug' => 'forum.moderator',
]);
```

> Because of `Slugable` trait, if you make a mistake and for example leave a space in slug parameter, it'll be replaced with a dot automatically, because of `str_slug` function.

### Attaching And Detaching Roles

It's really simple. You fetch a user from database and call `attachRole` method. There is `BelongsToMany` relationship between `User` and `Role` model.

```php
use App\User;

$user = User::find($id);

$user->attachRole($adminRole); // you can pass whole object, or just an id
```

```php
$user->detachRole($adminRole); // in case you want to detach role
$user->detachAllRoles(); // in case you want to detach all roles
```

### Checking For Roles

You can now check if the user has required role.

```php
if ($user->is('admin')) { // you can pass an id or slug
    // or alternatively $user->hasRole('admin')
}
```

You can also do this:

```php
if ($user->isAdmin()) {
    //
}
```

And of course, there is a way to check for multiple roles, using and/or operators:

```php
if ($user->is('admin|moderator')) {
    /*
    | Or alternatively:
    | $user->is(['admin', 'moderator']) // or operator: first braket
    */

    // if user has at least one role
}

if ($user->is('admin&moderator')) {
    /*
    | Or alternatively:
    | $user->is([['admin', 'moderator']]) // and operator: second braket
    */

    // if user has all roles
}

// Mixed or/and
if ($user->is('admin|moderator&publisher')) {
    /*
    | Or alternatively:
    | $user->is(['admin', ['moderator', 'publisher']])
    | $user->is(['admin', 'moderator&publisher'])
    */
}
```
You can mix operators, and parentheses (only in strings) to group conditions.
You can also use the method `hasRole` instead of `is`.

### Creating Permissions

It's very simple thanks to `Permission` model.

```php
use Wffranco\Roles\Models\Permission;

$createUsersPermission = Permission::create([
    'name' => 'Create users',
    'slug' => 'create.users',
    'description' => '', // optional
]);

$deleteUsersPermission = Permission::create([
    'name' => 'Delete users',
    'slug' => 'delete.users',
]);
```

### Attaching And Detaching Permissions

You can attach permissions to a role or directly to a specific user (and of course detach them as well).

```php
use App\User;
use Wffranco\Roles\Models\Role;

$role = Role::find($roleId);
$role->attachPermission($createUsersPermission); // permission attached to a role

$user = User::find($userId);
$user->attachPermission($deleteUsersPermission); // permission attached to a user
```

```php
$role->detachPermission($createUsersPermission); // in case you want to detach permission
$role->detachAllPermissions(); // in case you want to detach all permissions

$user->detachPermission($deleteUsersPermission);
$user->detachAllPermissions();
```

### Checking For Permissions

```php
if ($user->can('create.users') { // you can pass an id or slug
    //
}

if ($user->canDeleteUsers()) {
    //
}
```

You can check for multiple permissions the same way as roles.
You can also use `hasPermission` instead of `can`.

### Join Checking Roles/Permissions

Supose you have a blog where only can publish admins or moderators with write permission.
Right now you can do it that way:

```php
if ($user->isAdmin() || $user->isModerator() && $user->can('blog.write')) {
}
// or
if ($user->is('admin') || $user->is('moderator') && $user->can('blog.write')) {
}
```

For complex rules, you can combine roles & permissions with the `has` method.
Now you can do something like that:
```php
if ($user->has('role:admin|role:moderator&permission:blog.write')) {
}
// you can even abbreviate role & permission to their first letter
if ($user->has('r:admin|r:moderator&p:blog.write')) {
}
```

As in PHP, in all 3 methods (is/can/has) the `or` operator evaluate at last, unless you use parentheses.
```php
// Force 'or' first.
if ($user->has('(r:admin|r:moderator)&p:blog.write')) {
}
```

### Permissions Inheriting

Role with higher level is inheriting permission from roles with lower level.

There is an example of this `magic`:

You have three roles: `user`, `moderator` and `admin`. User has a permission to read articles, moderator can manage comments and admin can create articles. User has a level 1, moderator level 2 and admin level 3. It means, moderator and administrator has also permission to read articles, but administrator can manage comments as well.

> If you don't want permissions inheriting feature in you application, simply ignore `level` parameter when you're creating roles.

### Entity Check

Let's say you have an article and you want to edit it. This article belongs to a user (there is a column `user_id` in articles table).

```php
use App\Article;
use Wffranco\Roles\Models\Permission;

$editArticlesPermission = Permission::create([
    'name' => 'Edit articles',
    'slug' => 'edit.articles',
    'model' => 'App\Article',
]);

$user->attachPermission($editArticlesPermission);

$article = Article::find(1);

if ($user->allowed('edit.articles', $article)) { // $user->allowedEditArticles($article)
    //
}
```

This condition checks if the current user is the owner of article. If not, it will be looking inside user permissions for a row we created before.

```php
if ($user->allowed('edit.articles', $article, false)) { // now owner check is disabled
    //
}
```

### Blade Extensions

There are four Blade extensions. Basically, it is replacement for classic if statements.

```php
@role('admin') // @if(Auth::check() && Auth::user()->is('admin'))
    // user is admin
@endrole

@permission('edit.articles') // @if(Auth::check() && Auth::user()->can('edit.articles'))
    // user can edit articles
@endpermission

@level(2) // @if(Auth::check() && Auth::user()->level() >= 2)
    // user has level 2 or higher
@endlevel

@allowed('edit', $article) // @if(Auth::check() && Auth::user()->allowed('edit', $article))
    // show edit button
@endallowed

@role('admin|moderator', 'all') // @if(Auth::check() && Auth::user()->is('admin|moderator', 'all'))
    // user is admin and also moderator
@else
    // something else
@endrole
```

### Middleware

This package comes with `VerifyRole`, `VerifyPermission` and `VerifyLevel` middleware. You must add them inside your `app/Http/Kernel.php` file.

```php
/**
 * The application's route middleware.
 *
 * @var array
 */
protected $routeMiddleware = [
    'auth' => \App\Http\Middleware\Authenticate::class,
    'auth.basic' => \Illuminate\Auth\Middleware\AuthenticateWithBasicAuth::class,
    'guest' => \App\Http\Middleware\RedirectIfAuthenticated::class,
    'role' => \Wffranco\Roles\Middleware\VerifyRole::class,
    'permission' => \Wffranco\Roles\Middleware\VerifyPermission::class,
    'level' => \Wffranco\Roles\Middleware\VerifyLevel::class,
];
```

Now you can easily protect your routes.

```php
Route::middleware('role:admin')
    ->get('/example', 'ExampleController@index');

Route::middleware('permission:edit.articles')
    ->post('/example', 'ExampleController@index');

Route::middleware('level:2')
    ->get('/example', 'ExampleController@index');

```

It throws `\Wffranco\Roles\Exceptions\RoleDeniedException`, `\Wffranco\Roles\Exceptions\PermissionDeniedException` or `\Wffranco\Roles\Exceptions\LevelDeniedException` exceptions if it goes wrong.

You can catch these exceptions inside `app/Exceptions/Handler.php` file and do whatever you want.

```php
/**
 * Render an exception into an HTTP response.
 *
 * @param  \Illuminate\Http\Request  $request
 * @param  \Exception  $e
 * @return \Illuminate\Http\Response
 */
public function render($request, Exception $e)
{
    if ($e instanceof \Wffranco\Roles\Exceptions\RoleDeniedException) {
        // you can for example flash message, redirect...
        return redirect()->back();
    }

    return parent::render($request, $e);
}
```

## Config File

You can change connection for models, slug separator, models path and there is also a handy pretend feature. Have a look at config file for more information.

## More Information

For more information, please have a look at [HasRoleAndPermission](https://github.com/wffranco/roles/blob/master/src/Contracts/HasRoleAndPermission.php) contract.

## License

This package is free software distributed under the terms of the MIT license.
