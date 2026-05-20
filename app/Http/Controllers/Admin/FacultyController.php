<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreFacultyRequest;
use App\Models\Faculty;
use App\Models\StaffProfile;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class FacultyController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    // GET /api/admin/faculties
    public function index(): JsonResponse
    {
        $faculties = Faculty::with(['departments', 'dean'])
            ->orderBy('name')
            ->get()
            ->map(fn($f) => [
                'id'              => $f->id,
                'name'            => $f->name,
                'code'            => $f->code,
                'is_active'       => $f->is_active,
                'departments_count' => $f->departments->count(),
                'dean_user_id'    => $f->dean_user_id,
                'dean_name'       => $f->dean
                    ? trim($f->dean->first_name . ' ' . $f->dean->last_name)
                    : null,
                'dean_email'      => $f->dean?->email,
            ]);

        return response()->json([
            'success'   => true,
            'faculties' => $faculties,
        ]);
    }

    // POST /api/admin/faculties/{id}/assign-dean
    public function assignDean(Request $request, int $id): JsonResponse
    {
        $faculty = Faculty::findOrFail($id);

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
            if ($faculty->dean_user_id) {
                User::where('id', $faculty->dean_user_id)->update(['is_active' => false]);
            }

            $dean = User::create([
                'first_name'           => trim($request->first_name),
                'last_name'            => trim($request->last_name),
                'email'                => strtolower(trim($request->email)),
                'phone'                => $request->phone,
                'password'             => Hash::make(trim($request->last_name)),
                'role'                 => 'dean',
                'department_id'        => null,
                'is_active'            => true,
                'must_change_password' => true,
                'created_via'          => 'manual',
            ]);

            StaffProfile::create([
                'user_id'         => $dean->id,
                'staff_number'    => $request->staff_number
                    ?? ('NIT/DEAN/' . str_pad($dean->id, 3, '0', STR_PAD_LEFT)),
                'title'           => rtrim($request->title ?? 'Dr', '.'),
                'gender'          => $request->gender ?? 'Male',
                'specialization'  => $request->specialization ?? $faculty->name,
                'employment_type' => 'fulltime',
                'office_location' => 'Faculty Office',
            ]);

            $faculty->update(['dean_user_id' => $dean->id]);

            return response()->json([
                'success' => true,
                'message' => 'Dean created and assigned to ' . $faculty->name . ' successfully.',
                'dean'    => [
                    'id'            => $dean->id,
                    'name'          => $dean->first_name . ' ' . $dean->last_name,
                    'email'         => $dean->email,
                    'faculty'       => $faculty->name,
                    'temp_password' => trim($request->last_name),
                ],
            ], 201);
        } catch (\Exception $e) {
            Log::error('Dean creation failed', ['error' => $e->getMessage(), 'faculty_id' => $id]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create Dean: ' . $e->getMessage(),
            ], 500);
        }
    }

    // POST /api/admin/faculties/{id}/replace-dean
    public function replaceDean(Request $request, int $id): JsonResponse
    {
        $faculty = Faculty::findOrFail($id);

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
            if ($faculty->dean_user_id) {
                User::where('id', $faculty->dean_user_id)
                    ->update(['is_active' => false, 'role' => 'lecturer']);
            }

            $dean = User::create([
                'first_name'           => trim($request->first_name),
                'last_name'            => trim($request->last_name),
                'email'                => strtolower(trim($request->email)),
                'phone'                => $request->phone,
                'password'             => Hash::make(trim($request->last_name)),
                'role'                 => 'dean',
                'department_id'        => null,
                'is_active'            => true,
                'must_change_password' => true,
                'created_via'          => 'manual',
            ]);

            StaffProfile::create([
                'user_id'         => $dean->id,
                'staff_number'    => $request->staff_number
                    ?? ('NIT/DEAN/' . str_pad($dean->id, 3, '0', STR_PAD_LEFT)),
                'title'           => rtrim($request->title ?? 'Dr', '.'),
                'gender'          => $request->gender ?? 'Male',
                'specialization'  => $request->specialization ?? $faculty->name,
                'employment_type' => 'fulltime',
                'office_location' => 'Faculty Office',
            ]);

            $faculty->update(['dean_user_id' => $dean->id]);

            return response()->json([
                'success' => true,
                'message' => 'Dean replaced successfully.',
                'dean'    => [
                    'id'            => $dean->id,
                    'name'          => $dean->first_name . ' ' . $dean->last_name,
                    'email'         => $dean->email,
                    'temp_password' => trim($request->last_name),
                ],
            ], 201);
        } catch (\Exception $e) {
            Log::error('Dean replace failed', ['error' => $e->getMessage(), 'faculty_id' => $id]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to replace Dean: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    // POST /api/admin/faculties
    public function store(StoreFacultyRequest $request): JsonResponse
    {
        $faculty = Faculty::create([
            'name'      => $request->name,
            'code'      => strtoupper($request->code),
            'is_active' => $request->is_active ?? true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Faculty created successfully.',
            'faculty' => $faculty,
        ], 201);
    }
    /**
     * Display the specified resource.
     */
    // GET /api/admin/faculties/{id}
    public function show(int $id): JsonResponse
    {
        $faculty = Faculty::with('departments.programs')->findOrFail($id);

        return response()->json([
            'success' => true,
            'faculty' => $faculty,
        ]);
    }
    /**
     * Update the specified resource in storage.
     */
    // PUT /api/admin/faculties/{id}
    public function update(StoreFacultyRequest $request, int $id): JsonResponse
    {
        $faculty = Faculty::findOrFail($id);
        $faculty->update([
            'name'      => $request->name,
            'code'      => strtoupper($request->code),
            'is_active' => $request->is_active ?? $faculty->is_active,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Faculty updated successfully.',
            'faculty' => $faculty,
        ]);
    }
    /**
     * Remove the specified resource from storage.
     */
    // DELETE /api/admin/faculties/{id}
    public function destroy(int $id): JsonResponse
    {
        $faculty = Faculty::withCount('departments')->findOrFail($id);

        if ($faculty->departments_count > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete faculty that has departments. Remove departments first.',
            ], 422);
        }

        $faculty->delete();

        return response()->json([
            'success' => true,
            'message' => 'Faculty deleted successfully.',
        ]);
    }
}
