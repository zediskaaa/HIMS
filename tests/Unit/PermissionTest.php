<?php

namespace Tests\Unit;

use App\Enums\Permission;
use Tests\TestCase;

class PermissionTest extends TestCase
{
    public function test_every_permission_has_label_description_and_module(): void
    {
        foreach (Permission::cases() as $permission) {
            $label = $permission->label();
            $this->assertIsString($label);
            $this->assertNotEmpty($label, "Permission {$permission->value} should have a non-empty label.");

            $description = $permission->description();
            $this->assertIsString($description);
            $this->assertNotEmpty($description, "Permission {$permission->value} should have a non-empty description.");

            $module = $permission->module();
            $this->assertIsString($module);
            $this->assertNotEmpty($module, "Permission {$permission->value} should have a non-empty module.");
        }
    }

    public function test_by_module_groups_every_permission_case(): void
    {
        $grouped = Permission::byModule();
        $totalGrouped = 0;

        foreach ($grouped as $moduleName => $permissions) {
            $this->assertIsString($moduleName);
            $this->assertNotEmpty($permissions);
            $totalGrouped += count($permissions);
        }

        $this->assertSame(count(Permission::cases()), $totalGrouped);
    }
}
