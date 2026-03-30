<?php
namespace App\Http\Controllers\Registrar;

use App\Http\Controllers\Controller;
use App\Models\CsvImportLog;
use App\Models\Department;
use App\Models\Program;
use App\Models\StaffProfile;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Tymon\JWTAuth\Facades\JWTAuth;

class CsvImportController extends Controller
{
    // ─────────────────────────────────────────────
    // GET IMPORT HISTORY
    // ─────────────────────────────────────────────
    public function index(): JsonResponse
    {
        $logs = CsvImportLog::where('uploaded_by', JWTAuth::user()->id)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn($log) => [
                'uuid'              => $log->uuid,
                'import_type'       => $log->import_type,
                'original_filename' => $log->original_filename,
                'total_rows'        => $log->total_rows,
                'successful_rows'   => $log->successful_rows,
                'failed_rows'       => $log->failed_rows,
                'status'            => $log->status,
                'processed_at'      => $log->processed_at,
                'created_at'        => $log->created_at,
            ]);

        return response()->json([
            'success' => true,
            'imports' => $logs,
        ]);
    }

    

    // ─────────────────────────────────────────────
    // UPLOAD STUDENT CSV
    // ─────────────────────────────────────────────
    public function importStudents(Request $request): JsonResponse
    {
        $request->validate([
            'csv_file' => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
        ]);

        $file     = $request->file('csv_file');
        $uploader = JWTAuth::user();

        // Store file safely
        $storedName = Str::uuid() . '_students.csv';
        $file->storeAs('csv_imports', $storedName, 'local');

        // Create import log
        $log = CsvImportLog::create([
            'uploaded_by'       => $uploader->id,
            'import_type'       => 'students',
            'original_filename' => $file->getClientOriginalName(),
            'stored_filename'   => $storedName,
            'total_rows'        => 0,
            'successful_rows'   => 0,
            'failed_rows'       => 0,
            'status'            => 'processing',
        ]);

        // Parse CSV
        $handle  = fopen($file->getRealPath(), 'r');
        $headers = fgetcsv($handle); // skip header row
        $headers = array_map('trim', $headers);

        $rows       = [];
        $errors     = [];
        $rowNumber  = 1;
        $successful = 0;
        $failed     = 0;

        while (($row = fgetcsv($handle)) !== false) {
            $rowNumber++;
            $data = array_combine($headers, array_map('trim', $row));
            $rows[] = ['number' => $rowNumber, 'data' => $data];
        }
        fclose($handle);

        $log->update(['total_rows' => count($rows)]);

        // Process each row
        foreach ($rows as $item) {
            $rowNum = $item['number'];
            $data   = $item['data'];

            // Validate row
            $validator = Validator::make($data, [
                'first_name'          => ['required', 'string'],
                'last_name'           => ['required', 'string'],
                'email'               => ['required', 'email'],
                'registration_number' => ['required', 'string'],
                'program_code'        => ['required', 'string'],
                'year_of_study'       => ['required', 'integer', 'min:1', 'max:6'],
                'semester'            => ['required', 'integer', 'min:1', 'max:2'],
                'academic_year'       => ['required', 'string'],
                'gender'              => ['required', 'in:male,female,other'],
                'admission_year'      => ['required', 'integer'],
                'enrollment_status'   => ['required', 'in:active,suspended,deferred,graduated'],
            ]);

            if ($validator->fails()) {
                $errors[] = [
                    'row'     => $rowNum,
                    'data'    => $data,
                    'errors'  => $validator->errors()->all(),
                ];
                $failed++;
                continue;
            }

            // Check program exists
            $program = Program::where('code', strtoupper($data['program_code']))->first();
            if (!$program) {
                $errors[] = [
                    'row'    => $rowNum,
                    'data'   => $data,
                    'errors' => ["Program code '{$data['program_code']}' not found."],
                ];
                $failed++;
                continue;
            }

            // Check email duplicate
            if (User::where('email', $data['email'])->exists()) {
                $errors[] = [
                    'row'    => $rowNum,
                    'data'   => $data,
                    'errors' => ["Email '{$data['email']}' already exists."],
                ];
                $failed++;
                continue;
            }

            // Check registration number duplicate
            if (StudentProfile::where('registration_number', $data['registration_number'])->exists()) {
                $errors[] = [
                    'row'    => $rowNum,
                    'data'   => $data,
                    'errors' => ["Registration number '{$data['registration_number']}' already exists."],
                ];
                $failed++;
                continue;
            }

            // Create user and profile in a transaction
            try {
                DB::transaction(function () use ($data, $program) {
                    // Create user — default password = last_name
                    $user = User::create([
                        'uuid'                 => (string) Str::uuid(),
                        'first_name'           => $data['first_name'],
                        'last_name'            => $data['last_name'],
                        'email'                => strtolower($data['email']),
                        'phone'                => $data['phone'] ?? null,
                        'password'             => Hash::make($data['last_name']),
                        'role'                 => 'student',
                        'department_id'        => $program->department_id,
                        'is_active'            => true,
                        'must_change_password' => true,
                        'created_via'          => 'csv_import',
                    ]);

                    // Create student profile
                    StudentProfile::create([
                        'user_id'             => $user->id,
                        'registration_number' => $data['registration_number'],
                        'program_id'          => $program->id,
                        'year_of_study'       => (int) $data['year_of_study'],
                        'semester'            => (int) $data['semester'],
                        'academic_year'       => $data['academic_year'],
                        'gender'              => $data['gender'],
                        'date_of_birth'       => !empty($data['date_of_birth']) ? $data['date_of_birth'] : null,
                        'nationality'         => !empty($data['nationality']) ? $data['nationality'] : 'Tanzanian',
                        'admission_year'      => (int) $data['admission_year'],
                        'enrollment_status'   => $data['enrollment_status'],
                    ]);
                });

                $successful++;

            } catch (\Exception $e) {
                $errors[] = [
                    'row'    => $rowNum,
                    'data'   => $data,
                    'errors' => ['Database error: ' . $e->getMessage()],
                ];
                $failed++;
            }
        }

        // Update log with final results
        $log->update([
            'successful_rows' => $successful,
            'failed_rows'     => $failed,
            'status'          => $failed === 0 ? 'completed' : ($successful === 0 ? 'failed' : 'completed'),
            'processed_at'    => now(),
            'notes'           => $failed > 0
                ? "{$failed} rows failed. Check errors."
                : 'All rows imported successfully.',
        ]);

        return response()->json([
            'success'         => true,
            'message'         => "{$successful} students imported successfully. {$failed} failed.",
            'import_uuid'     => $log->uuid,
            'total_rows'      => count($rows),
            'successful_rows' => $successful,
            'failed_rows'     => $failed,
            'errors'          => $errors,
        ], 200);
    }

    // ─────────────────────────────────────────────
    // UPLOAD STAFF CSV
    // ─────────────────────────────────────────────
    public function importStaff(Request $request): JsonResponse
    {
        $request->validate([
            'csv_file' => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
        ]);

        $file     = $request->file('csv_file');
        $uploader = JWTAuth::user();

        $storedName = Str::uuid() . '_staff.csv';
        $file->storeAs('csv_imports', $storedName, 'local');

        $log = CsvImportLog::create([
            'uploaded_by'       => $uploader->id,
            'import_type'       => 'staff',
            'original_filename' => $file->getClientOriginalName(),
            'stored_filename'   => $storedName,
            'total_rows'        => 0,
            'successful_rows'   => 0,
            'failed_rows'       => 0,
            'status'            => 'processing',
        ]);

        $handle  = fopen($file->getRealPath(), 'r');
        $headers = array_map('trim', fgetcsv($handle));

        $rows      = [];
        $errors    = [];
        $rowNumber = 1;
        $successful = 0;
        $failed     = 0;

        while (($row = fgetcsv($handle)) !== false) {
            $rowNumber++;
            $data   = array_combine($headers, array_map('trim', $row));
            $rows[] = ['number' => $rowNumber, 'data' => $data];
        }
        fclose($handle);

        $log->update(['total_rows' => count($rows)]);

        $allowedRoles = ['lecturer', 'hod', 'dean', 'rector', 'registrar', 'admin'];

        foreach ($rows as $item) {
            $rowNum = $item['number'];
            $data   = $item['data'];

            $validator = Validator::make($data, [
                'first_name'      => ['required', 'string'],
                'last_name'       => ['required', 'string'],
                'email'           => ['required', 'email'],
                'staff_number'    => ['required', 'string'],
                'department_code' => ['required', 'string'],
                'role'            => ['required', 'in:' . implode(',', $allowedRoles)],
                'title'           => ['required', 'in:Mr,Mrs,Ms,Dr,Prof'],
                'gender'          => ['required', 'in:male,female,other'],
                'employment_type' => ['required', 'in:fulltime,parttime,contract'],
            ]);

            if ($validator->fails()) {
                $errors[] = [
                    'row'    => $rowNum,
                    'data'   => $data,
                    'errors' => $validator->errors()->all(),
                ];
                $failed++;
                continue;
            }

            // Find department
            $department = Department::where('code', strtoupper($data['department_code']))->first();
            if (!$department) {
                $errors[] = [
                    'row'    => $rowNum,
                    'data'   => $data,
                    'errors' => ["Department code '{$data['department_code']}' not found."],
                ];
                $failed++;
                continue;
            }

            // Check duplicates
            if (User::where('email', $data['email'])->exists()) {
                $errors[] = [
                    'row'    => $rowNum,
                    'data'   => $data,
                    'errors' => ["Email '{$data['email']}' already exists."],
                ];
                $failed++;
                continue;
            }

            if (StaffProfile::where('staff_number', $data['staff_number'])->exists()) {
                $errors[] = [
                    'row'    => $rowNum,
                    'data'   => $data,
                    'errors' => ["Staff number '{$data['staff_number']}' already exists."],
                ];
                $failed++;
                continue;
            }

            try {
                DB::transaction(function () use ($data, $department) {
                    $user = User::create([
                        'uuid'                 => (string) Str::uuid(),
                        'first_name'           => $data['first_name'],
                        'last_name'            => $data['last_name'],
                        'email'                => strtolower($data['email']),
                        'phone'                => $data['phone'] ?? null,
                        'password'             => Hash::make($data['last_name']),
                        'role'                 => $data['role'],
                        'department_id'        => $department->id,
                        'is_active'            => true,
                        'must_change_password' => true,
                        'created_via'          => 'csv_import',
                    ]);

                    StaffProfile::create([
                        'user_id'         => $user->id,
                        'staff_number'    => $data['staff_number'],
                        'title'           => $data['title'],
                        'gender'          => $data['gender'],
                        'date_of_birth'   => !empty($data['date_of_birth']) ? $data['date_of_birth'] : null,
                        'nationality'     => !empty($data['nationality']) ? $data['nationality'] : 'Tanzanian',
                        'specialization'  => $data['specialization'] ?? null,
                        'employment_type' => $data['employment_type'],
                        'office_location' => $data['office_location'] ?? null,
                        'joined_date'     => !empty($data['joined_date']) ? $data['joined_date'] : null,
                    ]);
                });

                $successful++;

            } catch (\Exception $e) {
                $errors[] = [
                    'row'    => $rowNum,
                    'data'   => $data,
                    'errors' => ['Database error: ' . $e->getMessage()],
                ];
                $failed++;
            }
        }

        $log->update([
            'successful_rows' => $successful,
            'failed_rows'     => $failed,
            'status'          => $failed === 0 ? 'completed' : ($successful === 0 ? 'failed' : 'completed'),
            'processed_at'    => now(),
            'notes'           => $failed > 0
                ? "{$failed} rows failed."
                : 'All rows imported successfully.',
        ]);

        return response()->json([
            'success'         => true,
            'message'         => "{$successful} staff imported successfully. {$failed} failed.",
            'import_uuid'     => $log->uuid,
            'total_rows'      => count($rows),
            'successful_rows' => $successful,
            'failed_rows'     => $failed,
            'errors'          => $errors,
        ], 200);
    }

    // ─────────────────────────────────────────────
    // GET IMPORT DETAILS (show errors of a specific import)
    // ─────────────────────────────────────────────
    public function show(string $uuid): JsonResponse
    {
        $log = CsvImportLog::where('uuid', $uuid)
            ->where('uploaded_by', JWTAuth::user()->id)
            ->firstOrFail();

        return response()->json([
            'success' => true,
            'import'  => $log,
        ]);
    }
}