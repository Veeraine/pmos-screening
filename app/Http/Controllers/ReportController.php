<?php

namespace App\Http\Controllers;

use App\Emr\EmrAdapter;
use App\Http\Requests\ShowReportRequest;
use App\Reports\ReportPeriod;
use App\Reports\ScreeningReport;
use Illuminate\View\View;

class ReportController extends Controller
{
    /**
     * Show the screening statistics for the chosen period. Doctors only.
     */
    public function index(ShowReportRequest $request, EmrAdapter $emr): View
    {
        return view('reports.index', [
            'report' => ScreeningReport::for($request->period(), $emr),
            'choices' => ReportPeriod::choices(),
        ]);
    }
}
