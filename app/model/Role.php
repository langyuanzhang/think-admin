<?php

declare(strict_types=1);
/**
 * This file is part of TAnt.
 * @link     https://github.com/edenleung/think-admin
 * @document https://www.kancloud.cn/manual/thinkphp6_0
 * @contact  QQ Group 996887666
 * @author   Eden Leung 758861884@qq.com
 * @copyright 2019 Eden Leung
 * @license  https://github.com/edenleung/think-admin/blob/6.0/LICENSE.txt
 */

namespace app\model;

use app\model\validate\RoleValidate;
use think\exception\ValidateException;
use xiaodi\Permission\Contract\RoleContract;

class Role extends \think\Model implements RoleContract
{
    use \app\traits\CurdEvent;

    use \xiaodi\Permission\Traits\Role;
    use \app\traits\ValidateError;

    /**
     * 获取角色列表.
     */
    public function getList(int $page, int $pageSize)
    {
        $total = Role::count();
        $roles = Role::limit($pageSize)->page($page)->select();
        foreach ($roles as $role) {
            $role->permissions = $role->permissions()->select()->column('id');
        }

        return ['data' => $roles, 'tree' => $this->getTree(), 'pagination' => ['total' => $total, 'current' => intval($page), 'pageSize' => intval($pageSize)]];
    }

    /**
     * 添加角色.
     */
    public function addRole(array $data)
    {
        if ($this->validate('create', $data) === false) {
            return false;
        }

        $role = Role::create($data);

        // 绑定关系
        if (! empty($data['rules'])) {
            $role->assignPermissions($data['rules']);
        }
    }

    /**
     * 更新角色.
     */
    public function updateRole(int $id, array $data)
    {
        if ($this->validate('update', $data) === false) {
            return false;
        }

        $role = Role::find($id);
        if (empty($role)) {
            return false;
        }

        $role->save($data);

        // 解除关系
        $role->removeAllPermission();

        // 绑定关系
        if (! empty($data['rules'])) {
            $role->assignPermissions($data['rules']);

            // 如当前角色有删除一些权限并且有子角色时，子角色也一并删除权限
            $role->updateChildrenRole($data['rules']);
        }
    }

    /**
     * 更新子角色权限
     *
     * @param array $rules
     */
    protected function updateChildrenRole(array $rules)
    {
        // 对比差异 获取子角色要删除的权限
        $delete_rules = array_diff($this->permissions->column('id'), $rules);

        if (!empty($delete_rules)) {
            $permissions = Permission::whereIn('id', $delete_rules)->select();

            $roles = $this->childrenRole();
            foreach($roles as $role) {
                foreach($permissions as $permission) {
                    $role->removePermission($permission);
                }
            }
        }
    }

    /**
     * 获取当前角色的所有子角色
     *
     * @return void
     */
    protected function childrenRole()
    {
        $roles = Role::select();
        $category = new \extend\Category();
        $children = $category->getTree($roles, $this->id);

        return $children;
    }

    /**
     * 当前角色是否存在子角色
     *
     * @return boolean
     */
    protected function hasChildrenRole()
    {
        $roles = Role::select();

        $category = new \extend\Category();
        $children = $category->getChild($this->id, $roles);

        return !empty($children);
    }

    /**
     * 删除角色.
     */
    public function deleteRole(int $id)
    {
        $role = $this->find($id);
        if (empty($role)) {
            return false;
        }

        if ($role->hasChildrenRole()) {
            $this->error = '请先删除子角色';
            return false;
        }

        $role->removeAllPermission();
        return $role->delete();
    }

    /**
     * 验证数据.
     *
     * @param string $scene 验证场景
     * @param array $data 验证数据
     */
    protected function validate(string $scene, array $data)
    {
        try {
            validate(RoleValidate::class)
                ->scene($scene)
                ->check($data);
        } catch (ValidateException $e) {
            $this->error = $e->getError();
            return false;
        }

        return true;
    }

    /**
     * 绑定关系.
     */
    protected function assignPermissions(array $rules)
    {
        $rules = implode(',', $rules);
        $permissions = Permission::whereIn('id', $rules)->select();
        foreach ($permissions as $permission) {
            $this->assignPermission($permission);
        }
    }

    protected function getTree()
    {
        $data = $this->order('pid asc')->select();
        $category = new \extend\Category();
        $children = $category->formatTree($data, 'children');

        $result = [
            [
                'title' => '根',
                'value'    => 0,
                'children' => $children
            ]
        ];

        return $result;
    }
}
