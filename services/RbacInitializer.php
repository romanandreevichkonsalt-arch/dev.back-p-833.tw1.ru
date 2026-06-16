<?php

namespace app\services;

use yii\rbac\ManagerInterface;
use yii\rbac\Permission;
use yii\rbac\Role;

class RbacInitializer
{
    public const ROLE_ADMIN = 'admin';
    public const ROLE_CATALOG_MANAGER = 'catalogManager';
    public const ROLE_SUPPORT = 'support';

    public const PERM_MANAGE_CATEGORIES = 'manageCategories';
    public const PERM_MANAGE_PRODUCTS = 'manageProducts';
    public const PERM_MANAGE_ATTRIBUTES = 'manageAttributes';
    public const PERM_MANAGE_USERS = 'manageUsers';
    public const PERM_MANAGE_ROLES = 'manageRoles';
    public const PERM_VIEW_TOKENS = 'viewTokens';
    public const PERM_VIEW_SMS_CODES = 'viewSmsCodes';
    public const PERM_VIEW_EXTERNAL_IDENTITIES = 'viewExternalIdentities';

    public function init(ManagerInterface $auth): void
    {
        $permissions = [
            self::PERM_MANAGE_CATEGORIES => 'Управление категориями',
            self::PERM_MANAGE_PRODUCTS => 'Управление товарами',
            self::PERM_MANAGE_ATTRIBUTES => 'Управление атрибутами',
            self::PERM_MANAGE_USERS => 'Управление пользователями',
            self::PERM_MANAGE_ROLES => 'Управление ролями',
            self::PERM_VIEW_TOKENS => 'Просмотр API-токенов',
            self::PERM_VIEW_SMS_CODES => 'Просмотр SMS-кодов',
            self::PERM_VIEW_EXTERNAL_IDENTITIES => 'Просмотр внешних identity',
        ];

        foreach ($permissions as $name => $description) {
            $this->ensurePermission($auth, $name, $description);
        }

        $admin = $this->ensureRole($auth, self::ROLE_ADMIN, 'Полный доступ');
        $catalogManager = $this->ensureRole($auth, self::ROLE_CATALOG_MANAGER, 'Каталог');
        $support = $this->ensureRole($auth, self::ROLE_SUPPORT, 'Поддержка');

        foreach (array_keys($permissions) as $permissionName) {
            $this->ensureChild($auth, $admin, $auth->getPermission($permissionName));
        }

        foreach ([self::PERM_MANAGE_CATEGORIES, self::PERM_MANAGE_PRODUCTS, self::PERM_MANAGE_ATTRIBUTES] as $perm) {
            $this->ensureChild($auth, $catalogManager, $auth->getPermission($perm));
        }

        foreach ([self::PERM_VIEW_TOKENS, self::PERM_VIEW_SMS_CODES, self::PERM_VIEW_EXTERNAL_IDENTITIES, self::PERM_MANAGE_USERS] as $perm) {
            $this->ensureChild($auth, $support, $auth->getPermission($perm));
        }
    }

    public function assignRole(ManagerInterface $auth, int $userId, string $roleName): void
    {
        $role = $auth->getRole($roleName);
        if ($role !== null && $auth->getAssignment($roleName, (string) $userId) === null) {
            $auth->assign($role, (string) $userId);
        }
    }

    public static function roleLabels(): array
    {
        return [
            self::ROLE_ADMIN => 'Администратор',
            self::ROLE_CATALOG_MANAGER => 'Менеджер каталога',
            self::ROLE_SUPPORT => 'Поддержка',
        ];
    }

    private function ensurePermission(ManagerInterface $auth, string $name, string $description): Permission
    {
        $permission = $auth->getPermission($name);
        if ($permission !== null) {
            return $permission;
        }

        $permission = $auth->createPermission($name);
        $permission->description = $description;
        $auth->add($permission);

        return $permission;
    }

    private function ensureRole(ManagerInterface $auth, string $name, string $description): Role
    {
        $role = $auth->getRole($name);
        if ($role !== null) {
            return $role;
        }

        $role = $auth->createRole($name);
        $role->description = $description;
        $auth->add($role);

        return $role;
    }

    private function ensureChild(ManagerInterface $auth, Role $parent, ?Permission $child): void
    {
        if ($child !== null && !$auth->hasChild($parent, $child)) {
            $auth->addChild($parent, $child);
        }
    }
}
