<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreDepartmentRequest;
use Illuminate\Http\Request;
use App\Models\Department;
use App\Models\Faculty;
use Illuminate\Http\JsonResponse;


class DepartmentController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    // GET /api/admin/departments
    public function index(): JsonResponse
    {
        $departments = Department::with('faculty')
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
            ]);

        return response()->json([
            'success'     => true,
            'departments' => $departments,
        ]);
    }
    /**
     * Store a newly created resource in storage.
     */
    // POST /api/admin/departments
    public function store(StoreDepartmentRequest $request): JsonResponse
    {
        // Verify faculty exists
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
    /**
     * Display the specified resource.
     */
    // GET /api/admin/departments/{id}
    public function show(int $id): JsonResponse
    {
        $department = Department::with('faculty', 'programs')
            ->findOrFail($id);

        return response()->json([
            'success'    => true,
            'department' => $department,
        ]);
    }
    /**
     * Update the specified resource in storage.
     */
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
    /**
     * Remove the specified resource from storage.
     */
    // DELETE /api/admin/departments/{id}
    public function destroy(int $id): JsonResponse
    {
        $department = Department::withCount('programs')->findOrFail($id);

        if ($department->programs_count > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete department that has programs. Remove programs first.',
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
