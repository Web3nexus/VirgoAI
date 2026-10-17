<?php

namespace App\Http\Controllers\Admin;

use App\Billing\Money;
use App\Billing\Wallet;
use App\Http\Controllers\Controller;
use App\Jobs\SendTemplatedEmail;
use App\Models\AdminAuditLog;
use App\Models\Generation;
use App\Models\LedgerEntry;
use App\Models\User;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AdminUserController extends Controller
{
    /** Users, filtered by status: active (default), suspended, or deleted. */
    public function index(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('q', ''));
        $status = (string) $request->query('status', 'active');

        $users = User::query()
            ->withTrashed()
            ->when($status === 'deleted', fn ($query) => $query->onlyTrashed())
            ->when($status !== 'deleted', fn ($query) => $query->whereNull('deleted_at'))
            ->when($status === 'suspended', fn ($query) => $query->whereNotNull('suspended_at'))
            ->when($search !== '', fn ($query) => $query->where(fn ($inner) => $inner
                ->where('name', 'ilike', "%{$search}%")
                ->orWhere('email', 'ilike', "%{$search}%")))
            ->withCount('generations')
            ->latest('id')
            ->paginate(25);

        return response()->json([
            'data' => $users->getCollection()->map(fn (User $user) => $this->summary($user))->values(),
            'meta' => ['current_page' => $users->currentPage(), 'last_page' => $users->lastPage(), 'total' => $users->total()],
        ]);
    }

    /** Create a user from the admin area. Admins choose the password and the role. */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required', 'string', 'min:12', 'max:255'],
            'role' => ['required', 'string', Rule::in([User::ROLE_USER, User::ROLE_SUPER_ADMIN])],
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => strtolower($data['email']),
            'password' => Hash::make($data['password']),
        ]);
        $user->forceFill(['role' => $data['role']])->save();

        AdminAuditLog::record($request->user(), 'user.created', $user, ['role' => $data['role']]);

        return response()->json($this->summary($user), 201);
    }

    public function show(User $user): JsonResponse
    {
        $runs = Generation::query()->where('user_id', $user->id);

        return response()->json([
            ...$this->summary($user),
            'created_at' => $user->created_at?->toIso8601String(),
            'deleted_at' => $user->deleted_at?->toIso8601String(),
            'totals' => [
                'succeeded' => (clone $runs)->where('status', Generation::STATUS_SUCCEEDED)->count(),
                'failed' => (clone $runs)->where('status', Generation::STATUS_FAILED)->count(),
                'paid_usd' => Money::toUsd((int) (clone $runs)->where('status', Generation::STATUS_SUCCEEDED)->sum('price_micros')),
            ],
            'recent_runs' => (clone $runs)->latest('created_at')->limit(10)->get()->map(fn (Generation $run) => [
                'id' => $run->id,
                'tool' => $run->tool,
                'model_key' => $run->model_key,
                'status' => $run->status,
                'price_usd' => Money::toUsd((int) $run->price_micros),
                'created_at' => $run->created_at?->toIso8601String(),
            ])->values(),
            'ledger' => $user->ledgerEntries()->limit(30)->get()->map(fn (LedgerEntry $entry) => [
                'id' => $entry->id,
                'type' => $entry->type,
                'amount_usd' => Money::toUsd((int) $entry->amount_micros),
                'balance_after_usd' => Money::toUsd((int) $entry->balance_after_micros),
                'note' => $entry->note,
                'created_at' => $entry->created_at?->toIso8601String(),
            ])->values(),
        ]);
    }

    /**
     * Edit a user's name, email, role, or suspension. The owner cannot demote or suspend their own account.
     */
    public function update(Request $request, User $user): JsonResponse
    {
        $actor = $request->user();

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:120'],
            'email' => ['sometimes', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'role' => ['sometimes', 'string', Rule::in([User::ROLE_USER, User::ROLE_SUPER_ADMIN])],
            'suspended' => ['sometimes', 'boolean'],
        ]);

        if ($user->is($actor) && (($data['role'] ?? null) === User::ROLE_USER || ($data['suspended'] ?? false))) {
            throw ValidationException::withMessages(['user' => 'You cannot demote or suspend your own account.']);
        }

        if (($data['role'] ?? null) === User::ROLE_USER && $user->isSuperAdmin()
            && User::query()->where('role', User::ROLE_SUPER_ADMIN)->count() <= 1) {
            throw ValidationException::withMessages(['role' => 'At least one super admin must remain.']);
        }

        $changes = [];

        foreach (['name', 'email'] as $field) {
            if (isset($data[$field]) && $data[$field] !== $user->{$field}) {
                $changes[$field] = ['from' => $user->{$field}, 'to' => $data[$field]];
                $user->{$field} = $field === 'email' ? strtolower($data[$field]) : $data[$field];
            }
        }

        if (isset($data['role']) && $data['role'] !== $user->role) {
            $changes['role'] = ['from' => $user->role, 'to' => $data['role']];
            $user->role = $data['role'];
        }

        $becameSuspended = false;
        if (isset($data['suspended'])) {
            $nowSuspended = (bool) $data['suspended'];
            if ($nowSuspended !== $user->isSuspended()) {
                $changes['suspended'] = ['from' => $user->isSuspended(), 'to' => $nowSuspended];
                $user->suspended_at = $nowSuspended ? now() : null;
                $becameSuspended = $nowSuspended;
            }
        }

        if ($changes !== []) {
            $user->save();
            AdminAuditLog::record($actor, 'user.updated', $user, $changes);
        }

        if ($becameSuspended) {
            SendTemplatedEmail::dispatch('account_suspended', $user->email, ['name' => $user->name], $user->id);
        }

        return response()->json($this->summary($user->refresh()));
    }

    /**
     * Delete keeps the ledger and run history. The account cannot sign in afterwards, and it can be restored.
     */
    public function destroy(Request $request, User $user): JsonResponse
    {
        $actor = $request->user();

        if ($user->is($actor)) {
            throw ValidationException::withMessages(['user' => 'You cannot delete your own account.']);
        }

        if ($user->isSuperAdmin() && User::query()->where('role', User::ROLE_SUPER_ADMIN)->count() <= 1) {
            throw ValidationException::withMessages(['user' => 'The last super admin cannot be deleted.']);
        }

        $user->delete();
        AdminAuditLog::record($actor, 'user.deleted', $user);

        return response()->json(['message' => 'User deleted.']);
    }

    public function restore(Request $request, int $user): JsonResponse
    {
        $account = User::withTrashed()->findOrFail($user);

        if (! $account->trashed()) {
            throw ValidationException::withMessages(['user' => 'This user is not deleted.']);
        }

        $account->restore();
        AdminAuditLog::record($request->user(), 'user.restored', $account);

        return response()->json($this->summary($account->refresh()));
    }

    /**
     * Add or remove credit. Removing credit cannot take the balance below zero. Added credit is emailed.
     */
    public function credit(Request $request, User $user, Wallet $wallet): JsonResponse
    {
        $data = $request->validate([
            'amount_usd' => ['required', 'numeric', 'not_in:0', 'between:-100000,100000'],
            'note' => ['required', 'string', 'min:3', 'max:255'],
        ]);

        try {
            $entry = $wallet->adjust($user, Money::fromUsd($data['amount_usd']), $data['note'], $request->user());
        } catch (DomainException $e) {
            throw ValidationException::withMessages(['amount_usd' => $e->getMessage()]);
        }

        AdminAuditLog::record($request->user(), 'user.credit', $user, [
            'amount_usd' => Money::toUsd($entry->amount_micros),
            'note' => $data['note'],
        ]);

        if ($entry->amount_micros > 0) {
            $user->refresh();
            SendTemplatedEmail::dispatch('credit_granted', $user->email, [
                'name' => $user->name,
                'amount' => Money::format($entry->amount_micros),
                'balance' => Money::format($user->balance_micros),
            ], $user->id);
        }

        return response()->json($this->summary($user->refresh()));
    }

    /** @return array<string, mixed> */
    private function summary(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'suspended' => $user->isSuspended(),
            'deleted' => $user->trashed(),
            'balance_usd' => Money::toUsd((int) $user->balance_micros),
            'runs' => $user->generations_count ?? $user->generations()->count(),
        ];
    }
}
