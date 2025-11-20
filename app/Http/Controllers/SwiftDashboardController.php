<?php

namespace App\Http\Controllers;

use App\Models\SwiftMessage;
use App\Services\SwiftProcessingService;
use Illuminate\Http\Request;

class SwiftDashboardController extends Controller
{
    public function index()
    {
        // Fetch all messages
        $messages = SwiftMessage::orderBy('message_date', 'desc')->get();

        // Group by Date -> Then by Message Type
        $grouped = $messages->groupBy('message_date')->map(function ($dateGroup) {
            return $dateGroup->groupBy('mt_type');
        });

        return view('dashboard', ['groupedMessages' => $grouped]);
    }

    public function downloadCsv($date, $type, SwiftProcessingService $service)
    {
        $messages = SwiftMessage::where('message_date', $date)
            ->where('mt_type', $type)
            ->get()
            ->pluck('parsed_data')
            ->toArray();

        if (empty($messages)) {
            abort(404);
        }

        $csvContent = $service->generateCsvContent($messages);
        $filename = "SWIFT_{$type}_{$date}.csv";

        return response($csvContent)
            ->header('Content-Type', 'text/csv')
            ->header('Content-Disposition', "attachment; filename=\"$filename\"");
    }
}