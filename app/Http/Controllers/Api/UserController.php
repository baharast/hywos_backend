<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\ChangeUserStatusRequest;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Requests\UpdateUserRolesRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\ApiResponse;
use Illuminate\Http\Request;

class UserController extends ApiController
{
    public function index(Request $request)
    {
        $perPage = (int) $request->query('per_page', 25);
        $query = User::with('roles');

        if ($request->filled('search')) {
            $search = '%' . $request->query('search') . '%';
            $query->where(function ($query) use ($search) {
                $query->where('name', 'like', $search)
                    ->orWhere('username', 'like', $search)
                    ->orWhere('email', 'like', $search);
            });
        }

        if ($request->filled('status')) {
            $status = strtolower($request->query('status'));
            switch ($status) {
                case 'active':
                    $query->where('is_active', true)->where('is_locked', false)->whereNull('disabled_at');
                    break;
                case 'inactive':
                    $query->where('is_active', false)->whereNull('disabled_at');
                    break;
                case 'locked':
                    $query->where('is_locked', true);
                    break;
                case 'disabled':
                    $query->whereNotNull('disabled_at');
                    break;
            }
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', filter_var($request->query('is_active'), FILTER_VALIDATE_BOOLEAN));
        }

        if ($request->filled('language')) {
            $query->where('preferred_language', $request->query('language'));
        }

        if ($request->filled('role')) {
            $query->whereHas('roles', function ($query) use ($request) {
                $query->where('name', $request->query('role'));
            });
        }

        $paginator = $query->orderBy('created_at', 'desc')->paginate($perPage);
        $users = UserResource::collection($paginator->items());

        return ApiResponse::success($users, 'Users retrieved', 200, [
            'current_page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'last_page' => $paginator->lastPage(),
        ]);
    }

    public function store(StoreUserRequest $request)
    {
        $data = $request->validated();
        $roles = $data['roles'] ?? null;
        unset($data['roles']);

        $user = User::create($data);
        if ($roles !== null) {
            $user->syncRoles($roles);
        }

        return $this->created(new UserResource($user), 'User created');
    }

    public function show($id)
    {
        $user = User::find($id);
        if (! $user) {
            return $this->error('User not found', 'USER_NOT_FOUND', 404);
        }

        return $this->success(new UserResource($user), 'User retrieved');
    }

    public function update(UpdateUserRequest $request, $id)
    {
        $user = User::find($id);
        if (! $user) {
            return $this->error('User not found', 'USER_NOT_FOUND', 404);
        }

        $data = $request->validated();
        $roles = $data['roles'] ?? null;
        unset($data['roles']);

        $user->update($data);
        if ($roles !== null) {
            $user->syncRoles($roles);
        }

        return $this->success(new UserResource($user), 'User updated');
    }

    public function activate(ChangeUserStatusRequest $request, $id)
    {
        $user = User::find($id);
        if (! $user) {
            return $this->error('User not found', 'USER_NOT_FOUND', 404);
        }

        $user->update(['is_active' => true]);

        return $this->success(new UserResource($user), 'User activated');
    }

    public function deactivate(ChangeUserStatusRequest $request, $id)
    {
        $user = User::find($id);
        if (! $user) {
            return $this->error('User not found', 'USER_NOT_FOUND', 404);
        }

        $user->update(['is_active' => false]);

        return $this->success(new UserResource($user), 'User deactivated');
    }

    public function updateRoles(UpdateUserRolesRequest $request, $id)
    {
        $user = User::find($id);
        if (! $user) {
            return $this->error('User not found', 'USER_NOT_FOUND', 404);
        }

        $user->syncRoles($request->validated('roles'));

        return $this->success(new UserResource($user), 'User roles updated');
    }

    public function destroy($id)
    {
        $user = User::find($id);
        if (! $user) {
            return $this->error('User not found', 'USER_NOT_FOUND', 404);
        }

        $user->update(['is_active' => false]);

        return $this->success(new UserResource($user), 'User deactivated');
    }
}
