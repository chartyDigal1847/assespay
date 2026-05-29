<?php

namespace App\Support;

use App\Models\Student;
use App\Services\PortalUserService;
use Illuminate\Http\Request;

class PortalSession
{
    public static function role(Request $request): ?string
    {
        return app(PortalUserService::class)->role($request);
    }

    public static function portalId(Request $request): ?string
    {
        return app(PortalUserService::class)->portalId($request);
    }

    public static function studentId(Request $request): ?int
    {
        return app(PortalUserService::class)->studentIdForSession($request);
    }

    public static function ensureStudent(Request $request): ?Student
    {
        return app(PortalUserService::class)->ensureStudentRecord($request);
    }
}
