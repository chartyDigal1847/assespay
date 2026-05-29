<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== Enrollments Check ===\n\n";

$enrollments = DB::table('enrollments')->get();

foreach ($enrollments as $e) {
    echo "ID: {$e->id}\n";
    echo "Name: {$e->student_name}\n";
    echo "Email: {$e->email}\n";
    echo "Grade: {$e->grade_level}\n";
    echo "School Year: {$e->school_year}\n";
    echo "Status: {$e->status}\n";
    echo "---\n\n";
}

echo "=== Tuition Fees Check ===\n\n";

$fees = DB::table('tuition_fees')->get();

foreach ($fees as $f) {
    echo "Grade {$f->grade_level} ({$f->school_year}): ₱" . number_format($f->tuition_fee + $f->misc_fee + $f->other_fee) . "\n";
}
