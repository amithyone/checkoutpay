<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\AdminSidebarMenu;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminSidebarMenuController extends Controller
{
    public function update(Request $request, AdminSidebarMenu $menu): JsonResponse
    {
        $validated = $request->validate([
            'order' => ['required', 'array', 'min:1'],
            'order.*' => ['required', 'string', 'max:64'],
        ]);

        $menu->saveOrder($request->user('admin'), $validated['order']);

        return response()->json(['message' => 'Menu order saved.']);
    }

    public function reset(AdminSidebarMenu $menu): JsonResponse
    {
        $menu->resetOrder(request()->user('admin'));

        return response()->json(['message' => 'Menu order reset to default.']);
    }
}
