<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreDepartmentRequest;
use App\Models\Department;
use App\Models\Faculty;
use App\Models\StaffProfile;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class DepartmentController extends Controller
{
    // GET /api/admin/departments
    public function index(): JsonResponse
    {
        $departments = Department::with(['faculty', 'hod'])
            ->withCount('programs')
            ->orderBy('name')
            ->get()
            ->map(fn($d) => [
                'id'             => $d->id,
                'name'           => $d->name,
                'code'           => $d->code,
                'is_active'      => $d->is_active,
                'faculty_id'     => $d->faculty_id,
                'faculty_name'   => $d->faculty?->name,
                'programs_count' => $d->programs_count,
                'hod_user_id'    => $d->hod_user_id,
                'hod_name'       => $d->hod
                    ? trim($d->hod->first_name . ' ' . $d->hod->last_name)
                    : null,
                'hod_email'      => $d->hod?->email,
            ]);

        return response()->json([
            'success'     => true,
            'departments' => $departments,
        ]);
    }

    // POST /api/admin/departments/{id}/assign-hod
    public function assignHod(Request $request, int $id): JsonResponse
    {
        $department = Department::findOrFail($id);

        Log::info('Assign HOD request', [
            'department_id' => $id,
            'email'         => $request->email,
            'first_name'    => $request->first_name,
            'last_name'     => $request->last_name,
        ]);

        $validator = Validator::make($request->all(), [
            'first_name'     => ['required', 'string', 'max:100'],
            'last_name'      => ['required', 'string', 'max:100'],
            'email'          => ['required', 'email', 'unique:users,email'],
            'phone'          => ['nullable', 'string', 'max:20'],
            'staff_number'   => ['nullable', 'string', 'max:50'],
            'title'          => ['nullable', 'string', 'max:20'],
            'gender'         => ['nullable', 'in:Male,Female,Other'],
            'specialization' => ['nullable', 'string', 'max:150'],
        ]);

        if ($validator->fails()) {
            Log::warning('HOD validation failed', $validator->errors()->toArray());
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        try {
            // Deactivate previous HOD if exists
            if ($department->hod_user_id) {
                User::where('id', $department->hod_user_id)
                    ->update(['is_active' => false]);
            }

            // Create HOD user account
            $hod = User::create([
                'first_name'           => trim($request->first_name),
                'last_name'            => trim($request->last_name),
                'email'                => strtolower(trim($request->email)),
                'phone'                => $request->phone,
                'password'             => Hash::make(trim($request->last_name)),
                'role'                 => 'hod',
                'department_id'        => $department->id,
                'is_active'            => true,
                'must_change_password' => true,
                'created_via'          => 'manual',
            ]);

            Log::info('HOD user created', ['user_id' => $hod->id]);

            // Create staff profile
            StaffProfile::create([
                'user_id'         => $hod->id,
                'staff_number'    => $request->staff_number
                    ?? ('NIT/HOD/' . str_pad($hod->id, 3, '0', STR_PAD_LEFT)),
                'title' => rtrim($request->title ?? 'Dr', '.'),
                'gender'          => $request->gender ?? 'Male',
                'specialization'  => $request->specialization ?? $department->name,
                'employment_type' => 'fulltime',
                'office_location' => 'Department Office',
            ]);

            // Link HOD to department
            $department->update(['hod_user_id' => $hod->id]);

            Log::info('HOD assigned to department', [
                'hod_id'        => $hod->id,
                'department_id' => $department->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'HOD created and assigned to ' . $department->name . ' successfully.',
                'hod'     => [
                    'id'            => $hod->id,
                    'name'          => $hod->first_name . ' ' . $hod->last_name,
                    'email'         => $hod->email,
                    'department'    => $department->name,
                    'temp_password' => trim($request->last_name),
                ],
            ], 201);

        } catch (\Exception $e) {
            Log::error('HOD creation failed', [
                'error'         => $e->getMessage(),
                'department_id' => $id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create HOD: ' . $e->getMessage(),
            ], 500);
        }
    }

    // POST /api/admin/departments/{id}/replace-hod
    public function replaceHod(Request $request, int $id): JsonResponse
    {
        $department = Department::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'first_name'     => ['required', 'string', 'max:100'],
            'last_name'      => ['required', 'string', 'max:100'],
            'email'          => ['required', 'email', 'unique:users,email'],
            'phone'          => ['nullable', 'string', 'max:20'],
            'staff_number'   => ['nullable', 'string', 'max:50'],
            'title'          => ['nullable', 'string', 'max:20'],
            'gender'         => ['nullable', 'in:Male,Female,Other'],
            'specialization' => ['nullable', 'string', 'max:150'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        try {
            // Deactivate old HOD — demote to lecturer
            if ($department->hod_user_id) {
                User::where('id', $department->hod_user_id)
                    ->update([
                        'is_active' => false,
                        'role'      => 'lecturer',
                    ]);
            }

            // Create new HOD
            $hod = User::create([
                'first_name'           => trim($request->first_name),
                'last_name'            => trim($request->last_name),
                'email'                => strtolower(trim($request->email)),
                'phone'                => $request->phone,
                'password'             => Hash::make(trim($request->last_name)),
                'role'                 => 'hod',
                'department_id'        => $department->id,
                'is_active'            => true,
                'must_change_password' => true,
                'created_via'          => 'manual',
            ]);

            StaffProfile::create([
                'user_id'         => $hod->id,
                'staff_number'    => $request->staff_number
                    ?? ('NIT/HOD/' . str_pad($hod->id, 3, '0', STR_PAD_LEFT)),
                'title' => rtrim($request->title ?? 'Dr', '.'),
                'gender'          => $request->gender ?? 'Male',
                'specialization'  => $request->specialization ?? $department->name,
                'employment_type' => 'fulltime',
                'office_location' => 'Department Office',
            ]);

            $department->update(['hod_user_id' => $hod->id]);

            return response()->json([
                'success' => true,
                'message' => 'HOD replaced successfully.',
                'hod'     => [
                    'id'            => $hod->id,
                    'name'          => $hod->first_name . ' ' . $hod->last_name,
                    'email'         => $hod->email,
                    'temp_password' => trim($request->last_name),
                ],
            ], 201);

        } catch (\Exception $e) {
            Log::error('HOD replace failed', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to replace HOD: ' . $e->getMessage(),
            ], 500);
        }
    }

    // POST /api/admin/departments
    public function store(StoreDepartmentRequest $request): JsonResponse
    {
        $faculty = Faculty::findOrFail($request->faculty_id);

        $department = Department::create([
            'faculty_id' => $faculty->id,
            'name'       => $request->name,
            'code'       => strtoupper($request->code),
            'is_active'  => $request->is_active ?? true,
        ]);

        return response()->json([
            'success'    => true,
            'message'    => 'Department created successfully.',
            'department' => $department->load('faculty'),
        ], 201);
    }

    // GET /api/admin/departments/{id}
    public function show(int $id): JsonResponse
    {
        $department = Department::with('faculty', 'programs')->findOrFail($id);

        return response()->json([
            'success'    => true,
            'department' => $department,
        ]);
    }

    // PUT /api/admin/departments/{id}
    public function update(StoreDepartmentRequest $request, int $id): JsonResponse
    {
        $department = Department::findOrFail($id);
        $department->update([
            'faculty_id' => $request->faculty_id,
            'name'       => $request->name,
            'code'       => strtoupper($request->code),
            'is_active'  => $request->is_active ?? $department->is_active,
        ]);

        return response()->json([
            'success'    => true,
            'message'    => 'Department updated successfully.',
            'department' => $department->load('faculty'),
        ]);
    }

    // DELETE /api/admin/departments/{id}
    public function destroy(int $id): JsonResponse
    {
        $department = Department::withCount('programs')->findOrFail($id);

        if ($department->programs_count > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete department that has programs.',
            ], 422);
        }

        $department->delete();

        return response()->json([
            'success' => true,
            'message' => 'Department deleted successfully.',
        ]);
    }

    // GET /api/admin/faculties/{facultyId}/departments
    public function byFaculty(int $facultyId): JsonResponse
    {
        $departments = Department::where('faculty_id', $facultyId)
            ->where('is_active', true)
            ->withCount('programs')
            ->orderBy('name')
            ->get();

        return response()->json([
            'success'     => true,
            'departments' => $departments,
        ]);
    }
}