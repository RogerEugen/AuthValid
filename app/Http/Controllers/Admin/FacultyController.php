<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreFacultyRequest;
use App\Models\Faculty;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FacultyController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    // GET /api/admin/faculties
    public function index(): JsonResponse
    {
        $faculties = Faculty::with('departments')
            ->orderBy('name')
            ->get()
            ->map(fn($f) => [
                'id'              => $f->id,
                'name'            => $f->name,
                'code'            => $f->code,
                'is_active'       => $f->is_active,
                'departments_count' => $f->departments->count(),
            ]);

        return response()->json([
            'success'   => true,
            'faculties' => $faculties,
        ]);
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
