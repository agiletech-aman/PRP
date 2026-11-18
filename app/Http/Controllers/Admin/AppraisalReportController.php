<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Appraisal;
use App\Models\Department;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AppraisalReportController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search'));
        $departmentId = $request->integer('department');

        return view('admin.appraisals.reports', [
            'appraisals' => Appraisal::query()
                ->with(['employee.department', 'manager', 'department', 'ratings'])
                ->when($search !== '', function ($query) use ($search) {
                    $query->whereHas('employee', function ($query) use ($search) {
                        $query->where('name', 'like', "%{$search}%")
                            ->orWhere('employee_code', 'like', "%{$search}%");
                    });
                })
                ->when($departmentId > 0, fn ($query) => $query->where('department_id', $departmentId))
                ->latest('sent_at')
                ->latest('id')
                ->paginate(15)
                ->withQueryString(),
            'departments' => Department::orderBy('name')->get(),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $search = trim((string) $request->query('search'));
        $departmentId = $request->integer('department');
        $fileName = 'appraisal-performance-report-'.now()->format('Y-m-d').'.csv';

        $appraisals = Appraisal::query()
            ->with(['employee.department', 'manager', 'department', 'ratings'])
            ->when($search !== '', function ($query) use ($search) {
                $query->whereHas('employee', function ($query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('employee_code', 'like', "%{$search}%");
                });
            })
            ->when($departmentId > 0, fn ($query) => $query->where('department_id', $departmentId))
            ->latest('sent_at')
            ->latest('id')
            ->lazy();

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$fileName.'"',
        ];

        return response()->stream(function () use ($appraisals) {
            $output = fopen('php://output', 'w');
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, [
                'Employee Name', 'Employee Code', 'Department', 'Manager',
                'Appraisal', 'Status', 'Self Rating', 'Manager Rating',
                'HR Rating', 'HR Remarks', 'Sent Date', 'Completed Date',
            ]);

            foreach ($appraisals as $appraisal) {
                fputcsv($output, [
                    $appraisal->employee->name,
                    $appraisal->employee->employee_code,
                    $appraisal->department?->name ?? $appraisal->employee->department?->name,
                    $appraisal->manager?->name,
                    $appraisal->title,
                    $appraisal->status->label(),
                    $appraisal->averageRating('employee'),
                    $appraisal->averageRating('manager'),
                    $appraisal->averageRating('hr'),
                    $appraisal->hr_remarks,
                    $appraisal->sent_at?->format('Y-m-d'),
                    $appraisal->manager_submitted_at?->format('Y-m-d'),
                ]);
            }

            fclose($output);
        }, Response::HTTP_OK, $headers);
    }
}
