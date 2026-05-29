<?php

namespace App\Services;

use App\Models\Balance;
use App\Models\Student;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class DeorisClearanceSync
{
    public function sync(Student $student, Balance $balance): void
    {
        if (! $student->portal_user_id && ! $student->email) {
            return;
        }

        try {
            if (! Schema::connection('deoris')->hasColumn('users', 'clearcheck_passed')) {
                return;
            }

            $cleared = (float) $balance->current_balance <= 0;

            DB::connection('deoris')
                ->table('users')
                ->where('role', 'student')
                ->where(function ($query) use ($student) {
                    if ($student->portal_user_id) {
                        $query->where('id', $student->portal_user_id);
                    }

                    if ($student->email) {
                        $method = $student->portal_user_id ? 'orWhereRaw' : 'whereRaw';
                        $query->{$method}('LOWER(email) = ?', [strtolower($student->email)]);
                    }
                })
                ->update([
                    'clearcheck_passed' => $cleared,
                    'updated_at' => now(),
                ]);
        } catch (\Throwable $e) {
            Log::error('[AssessPay] Failed to sync DEORIS clearance', [
                'student_id' => $student->id,
                'portal_user_id' => $student->portal_user_id,
                'message' => $e->getMessage(),
            ]);
        }
    }
}
