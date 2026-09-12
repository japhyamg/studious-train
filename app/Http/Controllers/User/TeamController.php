<?php

namespace App\Http\Controllers\User;

use App\Models\User;
use App\Models\AssignedRule;
use App\Models\TransactionRule;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;
use App\Http\Controllers\Controller;
use App\Models\Permission;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Validator;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Routing\Controllers\HasMiddleware;

class TeamController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:team-list|team-view|team-create|team-update|team-delete|assign-rules|manage-roles', only: ['index', 'show']),
            new Middleware('permission:team-create', only: ['store']),
            new Middleware('permission:team-update', only: ['update']),
            new Middleware('permission:team-delete', only: ['destroy']),
            new Middleware('permission:assign-rules', only: ['showAssignedRules', 'updateAssignedRule', 'delete', 'bulkDelete']),
            new Middleware('permission:manage-roles', only: ['listRole', 'createRole', 'storeRole', 'updateRole', 'getRole', 'deleteRole']),
        ];
    }

    public function index()
    {
        $data['team'] = User::all();
        $data['roles'] = Role::where('guard_name', 'web')->pluck('name');
        return view('users.team.index', $data);
    }

    public function show(Request $request)
    {
        if ($request->has('id')) {
            $user = User::find($request->id);
            if ($user) {
                $user->role_names = $user->getRoleNames()->toArray();
                return Response::json(['status' => 'success', 'data' => $user]);
            }
        }
        return Response::json(['status' => 'failed']);
    }

    public function store(Request $request)
    {
        Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique(User::class)],
            'roles' => ['required'],
        ])->validate();

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make('password'),
        ]);

        $roles = Role::whereIn('name', $request->roles)->get()->pluck('id');
        $user->roles()->sync($roles);

        return redirect(route('manage-team.index'))->with('success', 'Team member added successfully');
    }

    public function update(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email,' . $request->user_id],
            'roles' => ['required'],
        ]);

        if ($validator->fails()) {
            return redirect(route('manage-team.index'))->with('error', implode(' ', $validator->errors()->all()));
        }

        $user = User::find($request->user_id);
        if (!$user) {
            return redirect(route('manage-team.index'))->with('error', 'Invalid selection.');
        }

        $user->update(['name' => $request->name, 'email' => $request->email]);
        $user->syncRoles($request->roles);

        return redirect(route('manage-team.index'))->with('success', 'User updated successfully.');
    }

    public function destroy(Request $request)
    {
        if ($request->has('id')) {
            $user = User::find($request->id);
            if ($user) {
                $user->delete();
                return Response::json(['status' => 'success']);
            }
        }
        return Response::json(['status' => 'failed']);
    }

    // Assign Rules
    public function showAssignedRules()
    {
        $data['reviewers'] = getTeamReviewers();
        $data['assigned_rules'] = AssignedRule::all();
        $data['rules'] = TransactionRule::whereNotIn('id', $data['assigned_rules']->pluck('transaction_rule_id'))->orderBy('created_at', 'DESC')->get();
        return view('users.team.assign', $data);
    }

    public function updateAssignedRule(Request $request)
    {
        $validator = Validator::make($request->all(), ['reviewer' => 'required', 'rules' => 'required']);

        if ($validator->fails()) {
            return redirect(route('manage-team.assign-rule.index'))->with('error', implode(' ', $validator->errors()->all()));
        }

        $reviewer = User::find($request->reviewer);
        $reviewer->assigned_rules()->sync($request->rules);

        return redirect(route('manage-team.assign-rule.index'))->with('success', 'Rule(s) assigned successfully');
    }

    public function delete($id)
    {
        $assigned = AssignedRule::find($id);
        if ($assigned) {
            $assigned->delete();
            return redirect(route('manage-team.assign-rule.index'))->with('success', 'Deleted successfully.');
        }
        return redirect(route('manage-team.assign-rule.index'))->with('error', 'Invalid Action.');
    }

    public function bulkDelete(Request $request)
    {
        $request->validate(['assigned' => 'required']);
        AssignedRule::whereIn('id', $request->assigned)->delete();
        return redirect(route('manage-team.assign-rule.index'))->with('success', 'Deleted successfully');
    }

    // Manage Roles
    public function listRole()
    {
        $roles = Role::orderBy('id', 'DESC')->paginate(10);
        return view('users.team.manage-roles', compact('roles'));
    }

    public function createRole()
    {
        $permissions = Permission::orderBy('category', 'asc')->get()->groupBy('category');
        return view('users.team.create-role', compact('permissions'));
    }

    public function storeRole(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|unique:roles,name',
            'permissions' => 'required',
        ]);
        if ($validator->fails()) return back()->with('error', $validator->errors()->first());

        $role = Role::create(['name' => strtolower($request->input('name'))]);
        $role->syncPermissions(array_map('intval', $request->input('permissions')));

        return redirect(route('manage-team.manage-roles'))->with('success', 'Role created successfully');
    }

    public function getRole($id)
    {
        $role = Role::find($id);
        if (!$role) return back()->with('error', 'Invalid Action');

        $permissions = Permission::orderBy('category', 'asc')->get()->groupBy('category');
        $rolePermissions = $role->permissions->pluck('id')->toArray();

        return view('users.team.edit-role', compact('role', 'permissions', 'rolePermissions'));
    }

    public function updateRole(Request $request)
    {
        $role = Role::find($request->role_id);
        if (!$role) return back()->with('error', 'Invalid Action');

        $validator = Validator::make($request->all(), ['name' => 'required', 'permissions' => 'required']);
        if ($validator->fails()) return back()->with('error', $validator->errors()->first());

        $role->name = $request->input('name');
        $role->save();
        $role->syncPermissions(array_map('intval', $request->input('permissions')));

        return redirect(route('manage-team.manage-roles'))->with('success', 'Role updated successfully');
    }

    public function deleteRole(Request $request)
    {
        if ($request->has('id')) {
            Role::where('id', $request->id)->delete();
            return Response::json(['status' => 'success']);
        }
        return Response::json(['status' => 'failed']);
    }
}
