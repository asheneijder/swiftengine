<?php

namespace App\Http\Controllers;

use App\Models\SwiftMessage; // Assuming you have a model
use Carbon\Carbon;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index()
    {
        // 1. Fetch messages (simulated based on your JSON file)
        $messages = SwiftMessage::orderBy('created_at', 'desc')->get();

        // 2. Group by Date (e.g., "Nov 21, 2025")
        $groupedMessages = $messages->groupBy(function ($item) {
            // Handle MongoDB date format conversion if necessary
            return Carbon::parse($item->created_at)->format('M d, Y');
        });

        // 3. Inside each Date, Group by Message Type
        $groupedMessages = $groupedMessages->map(function ($dateGroup) {
            return $dateGroup->groupBy('Message Type');
        });

        return view('dashboard', [
            'groupedMessages' => $groupedMessages
        ]);
    }

    public function downloadCsv(\Illuminate\Http\Request $request)
    {
        $dateStr = $request->input('date'); // e.g., "Nov 21, 2025"
        $msgType = $request->input('type'); // e.g., "MT103 ..."

        // 1. Parse the date
        try {
            $date = \Carbon\Carbon::parse($dateStr);
        } catch (\Exception $e) {
            return back()->with('error', 'Invalid date format');
        }

        // 2. Fetch Data (Adjust 'SwiftMessage' to your actual model name)
        // We filter by the Date part of created_at and the Message Type
        $messages = \App\Models\SwiftMessage::whereDate('created_at', $date->format('Y-m-d'))
            ->where('Message Type', $msgType)
            ->get();

        if ($messages->isEmpty()) {
            return back()->with('error', 'No data found to export.');
        }

        // 3. Prepare Headers (Dynamic based on data, excluding system fields)
        $excludeKeys = ['_id', 'updated_at', 'created_at'];

        // Collect all unique keys from all messages in this group to ensure no columns are missed
        $headers = $messages->flatMap(function ($item) {
            return array_keys($item->toArray());
        })->unique()->filter(function ($key) use ($excludeKeys) {
            return !in_array($key, $excludeKeys);
        })->values()->toArray();

        // 4. Generate CSV Stream
        $filename = \Illuminate\Support\Str::slug($msgType) . '_' . $date->format('Ymd') . '.csv';

        $headersResponse = [
            "Content-type"        => "text/csv",
            "Content-Disposition" => "attachment; filename=$filename",
            "Pragma"              => "no-cache",
            "Cache-Control"       => "must-revalidate, post-check=0, pre-check=0",
            "Expires"             => "0"
        ];

        $callback = function () use ($messages, $headers) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $headers);

            foreach ($messages as $msg) {
                $row = [];
                $msgArray = $msg->toArray();

                foreach ($headers as $header) {
                    $value = $msgArray[$header] ?? '';
                    // Handle nested arrays if any exist in MongoDB data
                    if (is_array($value) || is_object($value)) {
                        $value = json_encode($value);
                    }
                    $row[] = $value;
                }
                fputcsv($file, $row);
            }
            fclose($file);
        };

        return response()->stream($callback, 200, $headersResponse);
    }
}
