<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ReceiptResource;
use App\Models\OfficialReceipt;
use App\Support\PortalSession;
use Illuminate\Http\Request;

class ReceiptController extends Controller
{
    public function index(Request $request)
    {
        $query = OfficialReceipt::with('student')->latest('issued_at');

        if ($request->filled('student_id')) {
            $query->where('student_id', $request->student_id);
        }
        if (PortalSession::role($request) === 'student') {
            $studentId = PortalSession::studentId($request);
            abort_unless($studentId, 403, 'Student account is not available.');
            $query->where('student_id', $studentId);
        }

        return ReceiptResource::collection($query->paginate($request->integer('per_page', 15)));
    }

    public function show(Request $request, OfficialReceipt $receipt)
    {
        if (PortalSession::role($request) === 'student') {
            abort_unless((int) PortalSession::studentId($request) === (int) $receipt->student_id, 404);
        }

        return new ReceiptResource($receipt->load('student'));
    }
}
