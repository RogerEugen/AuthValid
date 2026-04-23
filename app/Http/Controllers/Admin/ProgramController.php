<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreProgramRequest;
use Illuminate\Http\Request;
use App\Models\Department;
use App\Models\Program;
use Illuminate\Http\JsonResponse;

class ProgramController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    // GET /api/admin/programs
    public function index(): JsonResponse
    {
        $programs = Program::with('department.faculty')
            ->orderBy('name')
            ->get()
            ->map(fn($p) => [
                'id'               => $p->id,
                'name'             => $p->name,
                'code'             => $p->code,
                'level'            => $p->level,
                'duration_years'   => $p->duration_years,
                'duration_display' => $p->duration_display,
                'is_active'        => $p->is_active,
                'department_id'    => $p->department_id,
                'department_name'  => $p->department?->name,
                'faculty_name'     => $p->department?->faculty?->name,
            ]);

        return response()->json([
            'success'  => true,
            'programs' => $programs,
        ]);
    }
    /**
     * Store a newly created resource in storage.
     */
    // POST /api/admin/programs
    public function store(StoreProgramRequest $request): JsonResponse
    {
        $department = Department::findOrFail($request->department_id);

        // ✅ Normalize level to lowercase always
        $level = strtolower($request->level);

        // // ✅ Map 'bachelors' → 'degree' if someone sends that
        // $levelMap = [
        //     'bachelors' => 'degree',
        //     'bachelor'  => 'degree',
        //     'bachelor of' => 'degree',
        // ];
        // $level = $levelMap[$level] ?? $level;

        // ✅ Generate duration_display if not provided
        $durationYears   = $request->duration_years;
        $durationDisplay = $request->duration_display
            ?? ($durationYears . ' year' . ($durationYears > 1 ? 's' : ''));

        $program = Program::create([
            'department_id'    => $department->id,
            'name'             => $request->name,
            'code'             => strtoupper($request->code),
            'level'            => $level,
            'duration_years'   => $durationYears,
            'duration_display' => $durationDisplay,
            'is_active'        => $request->is_active ?? true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Program created successfully.',
            'program' => $program->load('department.faculty'),
        ], 201);
    }

    /**
     * Update the specified resource in storage.
     */
    // PUT /api/admin/programs/{id}
    public function update(StoreProgramRequest $request, int $id): JsonResponse
    {
        $program = Program::findOrFail($id);
        $program->update([
            'department_id'    => $request->department_id,
            'name'             => $request->name,
            'code'             => strtoupper($request->code),
            // 'level'            => $request->level,
            'level' => strtolower($request->level),
            'duration_years'   => $request->duration_years,
            'duration_display' => $request->duration_display,
            'is_active'        => $request->is_active ?? $program->is_active,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Program updated successfully.',
            'program' => $program->load('department.faculty'),
        ]);
    }
 
    /**
     * Remove the specified resource from storage.
     */
    // DELETE /api/admin/programs/{id}
    public function destroy(int $id): JsonResponse
    {
        $program = Program::withCount('students')->findOrFail($id);

        if ($program->students_count > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete program that has enrolled students.',
            ], 422);
        }

        $program->delete();

        return response()->json([
            'success' => true,
            'message' => 'Program deleted successfully.',
        ]);
    }


    // GET /api/admin/departments/{departmentId}/programs
    public function byDepartment(int $departmentId): JsonResponse
    {
        $programs = Program::where('department_id', $departmentId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return response()->json([
            'success'  => true,
            'programs' => $programs,
        ]);
    }
}
