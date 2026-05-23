<?php

namespace App\Http\Controllers\Api;

use App\Enums\AuditAction;
use App\Enums\EventCategory;
use App\Enums\EventSeverity;
use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\ChangeUserStatusRequest;
use App\Http\Requests\DisableUserRequest;
use App\Http\Requests\EnableUserRequest;
use App\Http\Requests\LockUserRequest;
use App\Http\Requests\ResetUserAccessRequest;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UnlockUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Requests\UpdateUserRolesRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\ApiResponse;
use App\Services\Audit\AuditLogger;
use App\Services\Events\EventLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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

        $summary = [
            'total' => User::query()->count(),
            'active' => User::query()->where('is_active', true)->where('is_locked', false)->whereNull('disabled_at')->count(),
            'locked' => User::query()->where('is_locked', true)->count(),
            'disabled' => User::query()->whereNotNull('disabled_at')->count(),
            'inactive' => User::query()->where('is_active', false)->whereNull('disabled_at')->count(),
        ];

        $paginator = $query->orderBy('created_at', 'desc')->paginate($perPage);
        $users = UserResource::collection($paginator->items());
        $lastUpdated = User::query()->max('updated_at');

        return ApiResponse::list($users, $paginator, $summary, $lastUpdated, 'Users retrieved');
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

    /**
     * @deprecated Use POST /users/{id}/enable. Kept as thin wrapper for FE transition.
     */
    public function activate(ChangeUserStatusRequest $request, $id, AuditLogger $audit, EventLogger $events)
    {
        $enableRequest = EnableUserRequest::createFrom($request);
        $enableRequest->setContainer(app())->setRedirector(app('redirect'));
        $enableRequest->merge($request->all());

        return $this->enable($enableRequest, $id, $audit, $events);
    }

    /**
     * @deprecated Use POST /users/{id}/disable. Kept as thin wrapper for FE transition.
     *             Legacy clients that do not send a reason will receive a 422.
     */
    public function deactivate(ChangeUserStatusRequest $request, $id, AuditLogger $audit, EventLogger $events)
    {
        // Legacy clients passed reason_note; map it to the new reason field if present.
        if ($request->filled('reason_note') && ! $request->filled('reason')) {
            $request->merge(['reason' => $request->input('reason_note')]);
        }

        $disableRequest = DisableUserRequest::createFrom($request);
        $disableRequest->setContainer(app())->setRedirector(app('redirect'));
        $disableRequest->merge($request->all());
        // Trigger validation on the wrapper request now that we are inside the controller.
        $disableRequest->validateResolved();

        return $this->disable($disableRequest, $id, $audit, $events);
    }

    public function disable(DisableUserRequest $request, $id, AuditLogger $audit, EventLogger $events)
    {
        $user = User::find($id);
        if (! $user) {
            return $this->error('User not found', 'USER_NOT_FOUND', 404);
        }
        if (! is_null($user->disabled_at)) {
            return $this->error('User is already disabled', 'ALREADY_DISABLED', 409);
        }

        $reason = $request->input('reason');
        $reasonCode = $request->input('reason_code');

        return DB::transaction(function () use ($user, $reason, $reasonCode, $audit, $events) {
            $old = $audit->snapshotModel($user);

            // TODO: re-enable when auth lands — set disabled_by_user_id from request()->user()
            //       and enforce self-lockout prevention.
            $user->update([
                'is_active' => false,
                'disabled_at' => now(),
                'disabled_reason' => $reason,
            ]);

            $audit->record(
                $user,
                AuditAction::USER_DISABLED,
                $old,
                $audit->snapshotModel($user->fresh()),
                $reason,
                $reasonCode
            );

            $events->record(
                'user.disabled',
                $user,
                "User {$user->username} disabled",
                ['reason' => $reason, 'reason_code' => $reasonCode],
                EventCategory::SECURITY,
                EventSeverity::WARNING
            );

            return $this->success(new UserResource($user->fresh()), 'User disabled');
        });
    }

    public function enable(EnableUserRequest $request, $id, AuditLogger $audit, EventLogger $events)
    {
        $user = User::find($id);
        if (! $user) {
            return $this->error('User not found', 'USER_NOT_FOUND', 404);
        }
        if (is_null($user->disabled_at) && $user->is_active) {
            return $this->error('User is already enabled', 'ALREADY_ENABLED', 409);
        }

        $reason = $request->input('reason');
        $reasonCode = $request->input('reason_code');

        return DB::transaction(function () use ($user, $reason, $reasonCode, $audit, $events) {
            $old = $audit->snapshotModel($user);

            $user->update([
                'is_active' => true,
                'disabled_at' => null,
                'disabled_reason' => null,
                'disabled_by_user_id' => null,
            ]);

            $audit->record(
                $user,
                AuditAction::USER_ENABLED,
                $old,
                $audit->snapshotModel($user->fresh()),
                $reason,
                $reasonCode
            );

            $events->record(
                'user.enabled',
                $user,
                "User {$user->username} enabled",
                ['reason' => $reason, 'reason_code' => $reasonCode],
                EventCategory::SECURITY,
                EventSeverity::INFO
            );

            return $this->success(new UserResource($user->fresh()), 'User enabled');
        });
    }

    public function lock(LockUserRequest $request, $id, AuditLogger $audit, EventLogger $events)
    {
        $user = User::find($id);
        if (! $user) {
            return $this->error('User not found', 'USER_NOT_FOUND', 404);
        }
        if ($user->is_locked) {
            return $this->error('User is already locked', 'ALREADY_LOCKED', 409);
        }

        $reason = $request->input('reason');
        $reasonCode = $request->input('reason_code');

        return DB::transaction(function () use ($user, $reason, $reasonCode, $audit, $events) {
            $old = $audit->snapshotModel($user);

            // TODO: re-enable when auth lands — set locked_by_user_id from request()->user()
            //       and enforce self-lockout prevention.
            $user->update([
                'is_locked' => true,
                'locked_at' => now(),
                'locked_reason' => $reason,
            ]);

            $audit->record(
                $user,
                AuditAction::USER_LOCKED,
                $old,
                $audit->snapshotModel($user->fresh()),
                $reason,
                $reasonCode
            );

            $events->record(
                'user.locked',
                $user,
                "User {$user->username} locked",
                ['reason' => $reason, 'reason_code' => $reasonCode],
                EventCategory::SECURITY,
                EventSeverity::WARNING
            );

            return $this->success(new UserResource($user->fresh()), 'User locked');
        });
    }

    public function unlock(UnlockUserRequest $request, $id, AuditLogger $audit, EventLogger $events)
    {
        $user = User::find($id);
        if (! $user) {
            return $this->error('User not found', 'USER_NOT_FOUND', 404);
        }
        if (! $user->is_locked) {
            return $this->error('User is not locked', 'NOT_LOCKED', 409);
        }

        $reason = $request->input('reason');
        $reasonCode = $request->input('reason_code');

        return DB::transaction(function () use ($user, $reason, $reasonCode, $audit, $events) {
            $old = $audit->snapshotModel($user);

            $user->update([
                'is_locked' => false,
                'locked_at' => null,
                'locked_reason' => null,
                'locked_by_user_id' => null,
            ]);

            $audit->record(
                $user,
                AuditAction::USER_UNLOCKED,
                $old,
                $audit->snapshotModel($user->fresh()),
                $reason,
                $reasonCode
            );

            $events->record(
                'user.unlocked',
                $user,
                "User {$user->username} unlocked",
                ['reason' => $reason, 'reason_code' => $reasonCode],
                EventCategory::SECURITY,
                EventSeverity::INFO
            );

            return $this->success(new UserResource($user->fresh()), 'User unlocked');
        });
    }

    public function resetAccess(ResetUserAccessRequest $request, $id, AuditLogger $audit, EventLogger $events)
    {
        $user = User::find($id);
        if (! $user) {
            return $this->error('User not found', 'USER_NOT_FOUND', 404);
        }

        $reason = $request->input('reason');
        $reasonCode = $request->input('reason_code');

        return DB::transaction(function () use ($user, $reason, $reasonCode, $audit, $events) {
            $old = $audit->snapshotModel($user);

            $revokedTokenCount = 0;
            if (method_exists($user, 'tokens')) {
                $revokedTokenCount = (int) $user->tokens()->count();
                $user->tokens()->delete();
            }

            $user->remember_token = null;
            $user->save();

            $audit->record(
                $user,
                AuditAction::USER_ACCESS_RESET,
                $old,
                $audit->snapshotModel($user->fresh()),
                $reason,
                $reasonCode
            );

            $events->record(
                'user.access_reset',
                $user,
                "Access reset triggered for user {$user->username}",
                [
                    'reason' => $reason,
                    'reason_code' => $reasonCode,
                    'revoked_token_count' => $revokedTokenCount,
                ],
                EventCategory::SECURITY,
                EventSeverity::WARNING
            );

            return $this->success(
                new UserResource($user->fresh()),
                'Access reset triggered. Existing sessions and API tokens were revoked.'
            );
        });
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
