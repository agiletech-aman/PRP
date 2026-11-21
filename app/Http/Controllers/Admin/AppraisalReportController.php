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

    /**
     * Export one appraisal with the self, manager and HR rating of every KRA.
     */
    public function exportDetail(Appraisal $appraisal): StreamedResponse
    {
        $appraisal->load(['employee.department', 'manager', 'department', 'ratings']);

        $fileName = 'appraisal-report-'.str($appraisal->employee->employee_code ?: $appraisal->employee->name)->slug().'-'.now()->format('Y-m-d').'.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$fileName.'"',
        ];

        return response()->stream(function () use ($appraisal) {
            $output = fopen('php://output', 'w');
            fwrite($output, "\xEF\xBB\xBF");

            foreach ([
                ['Employee Name', $appraisal->employee->name],
                ['Employee Code', $appraisal->employee->employee_code],
                ['Department', $appraisal->department?->name ?? $appraisal->employee->department?->name],
                ['Manager', $appraisal->manager?->name],
                ['Appraisal', $appraisal->title],
                ['Status', $appraisal->status->label()],
                ['Sent Date', $appraisal->sent_at?->format('d-m-Y')],
                ['Completed Date', $appraisal->manager_submitted_at?->format('d-m-Y')],
            ] as $row) {
                fputcsv($output, $row);
            }

            fputcsv($output, []);
            fputcsv($output, ['#', 'Category', 'KRA / Factor', 'Self Rating', 'Manager Rating', 'HR Rating']);

            foreach ($appraisal->ratings as $index => $rating) {
                fputcsv($output, [
                    $index + 1,
                    $rating->category_name,
                    $rating->factor_name,
                    $rating->employee_rating,
                    $rating->manager_rating,
                    $rating->hr_rating,
                ]);
            }

            fputcsv($output, [
                '', '', 'Average',
                $appraisal->averageRating('employee'),
                $appraisal->averageRating('manager'),
                $appraisal->averageRating('hr'),
            ]);

            fputcsv($output, []);
            fputcsv($output, ['Employee Comments', $appraisal->employee_comments]);
            fputcsv($output, ['Manager Comments', $appraisal->manager_comments]);
            fputcsv($output, ['HR Remarks', $appraisal->hr_remarks ?: $appraisal->hr_comments]);

            fclose($output);
        }, Response::HTTP_OK, $headers);
    }
}
